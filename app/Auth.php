<?php
/**
 * Autenticação por sessão, com papéis e permissões por destino.
 * Softsul Software
 *
 * Os critérios são os do Orbit, de propósito: Argon2id, senha de 12
 * caracteres conferida contra listas de vazadas, segundo fator TOTP cifrado e
 * de uso único, passkey como alternativa aos dois fatores, bloqueio por
 * e-mail e por IP, sessão com idade máxima e derrubada quando a senha muda.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TOTP.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/Cripto.php';
require_once __DIR__ . '/Empresa.php';

final class Auth
{
    private static ?array $cache = null;
    private static ?array $permissoesCache = null;

    /**
     * Hash descartável para o e-mail que não existe, do mesmo algoritmo e
     * custo das senhas reais: com outro algoritmo, o tempo de resposta
     * diferente contaria quais e-mails existem.
     */
    private const HASH_FALSO = '$argon2id$v=19$m=65536,t=4,p=1$clpVMzJnZHN1YmtLcVZyZw$rXXG4HPnGgFn1c8d5jLEtGEEGYXVQvaF2mhoT1jiVA4';

    /** Idade máxima de uma sessão, mesmo em uso contínuo. */
    private const SESSAO_MAXIMA_SEG = 7 * 86400;

    public static function iniciarSessao(): void
    {
        if (session_status() !== PHP_SESSION_NONE || PHP_SAPI === 'cli') {
            return;
        }
        $cfg      = require __DIR__ . '/config.php';
        $segundos = $cfg['app']['timeout_sessao_min'] * 60;

        $pastaSessoes = $cfg['caminhos']['sessoes'];
        if (!is_dir($pastaSessoes)) {
            @mkdir($pastaSessoes, 0770, true);
        }
        if (is_dir($pastaSessoes) && is_writable($pastaSessoes)) {
            session_save_path($pastaSessoes);
        }
        // O Ubuntu desliga a coleta do PHP e limpa só a pasta padrão por cron,
        // que não alcança a pasta própria.
        ini_set('session.gc_maxlifetime', (string) $segundos);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.use_strict_mode', '1');

        session_name('INVENTOUR');
        session_set_cookie_params([
            'lifetime' => $segundos,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }

    // ------------------------------------------------------------- LOGIN

    /** Retorna 'invalido', 'bloqueado', 'precisa_2fa', 'precisa_cadastrar_2fa' ou 'sucesso'. */
    public static function tentarLogin(string $email, string $senha): string
    {
        self::iniciarSessao();
        $email = mb_strtolower(trim($email));
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (self::estaBloqueado($email, $ip)) {
            self::anotarTentativa($email, $ip, false);
            Auditoria::falhaDeAcesso('login_bloqueado');
            return 'bloqueado';
        }

        $stmt = Database::conexao()->prepare(
            'SELECT id, nome, email, senha_hash, ativo, softsul, duplo_fator_confirmado
               FROM usuarios WHERE email = ?'
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch() ?: null;

        // password_verify roda mesmo sem usuário, para o tempo de resposta não
        // denunciar quais e-mails existem.
        $hash = $usuario['senha_hash'] ?? self::HASH_FALSO;
        $senhaConfere = password_verify($senha, $hash ?: self::HASH_FALSO);

        if (!$usuario || !$senhaConfere || !$usuario['ativo'] || !$usuario['senha_hash']
            || !self::temAlgumAcesso((int) $usuario['id'], (bool) $usuario['softsul'])) {
            self::anotarTentativa($email, $ip, false);
            Auditoria::falhaDeAcesso('login_negado');
            Auditoria::registrar('login_negado', 'usuarios', $usuario['id'] ?? null, "tentativa para {$email}");
            return 'invalido';
        }

        if (password_needs_rehash((string) $usuario['senha_hash'], PASSWORD_ARGON2ID)) {
            Database::conexao()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                ->execute([self::hashSenha($senha), $usuario['id']]);
        }

        // O acerto da senha não é anotado como sucesso: o login só termina
        // depois do segundo fator, e é lá que se conta o que deu certo.
        $_SESSION['pre_2fa'] = (int) $usuario['id'];
        $_SESSION['pre_2fa_em'] = time();

        if ($usuario['duplo_fator_confirmado']) {
            return 'precisa_2fa';
        }
        if (self::segundoFatorObrigatorio((int) $usuario['id'])) {
            return 'precisa_cadastrar_2fa';
        }
        self::concluirLogin((int) $usuario['id'], '', 'senha');
        return 'sucesso';
    }

    /**
     * Segundo fator obrigatório para a equipe Softsul sempre, e para a
     * equipe de um destino que exija (empresas.exigir_2fa_equipe): gestor,
     * consultor, coordenador, pesquisador e leitor. O prestador (o trade que mantém a
     * própria ficha) fica de fora, como o proponente do EventCity: um
     * cadastro difícil afasta justamente quem alimenta o inventário, e ele
     * não homologa nada nem vê dado de outros. Pode ligar o segundo fator na
     * conta quando quiser.
     */
    public static function segundoFatorObrigatorio(int $usuarioId): bool
    {
        $q = Database::conexao()->prepare(
            "SELECT (u.softsul OR EXISTS (
                 SELECT 1 FROM usuario_empresa ue
                 JOIN usuario_empresa_papel uep ON uep.usuario_id = ue.usuario_id AND uep.empresa_id = ue.empresa_id
                 JOIN papeis p   ON p.id = uep.papel_id
                 JOIN empresas e ON e.id = ue.empresa_id
                 WHERE ue.usuario_id = u.id AND ue.ativo = 1 AND e.ativo = 1
                   AND p.escopo = 'destino' AND p.chave <> 'prestador'
                   AND e.exigir_2fa_equipe = 1))
             FROM usuarios u WHERE u.id = ?"
        );
        $q->execute([$usuarioId]);
        return (bool) $q->fetchColumn();
    }

    /** Quem não é da Softsul precisa de ao menos um vínculo ativo, em destino ativo. */
    public static function temAlgumAcesso(int $usuarioId, bool $softsul): bool
    {
        if ($softsul) {
            return true;
        }
        $q = Database::conexao()->prepare(
            'SELECT 1 FROM usuario_empresa ue JOIN empresas e ON e.id = ue.empresa_id
             WHERE ue.usuario_id = ? AND ue.ativo = 1 AND e.ativo = 1 LIMIT 1'
        );
        $q->execute([$usuarioId]);
        return (bool) $q->fetchColumn();
    }

    /**
     * Segundo passo: o código do aplicativo autenticador.
     * Retorna 'sucesso', 'invalido', 'bloqueado' ou 'expirado'.
     *
     * Os erros vão para tentativas_login, e não para a sessão: contados só na
     * sessão, bastava repetir a senha para ganhar outra rodada.
     */
    public static function confirmarSegundoFator(string $codigo): string
    {
        self::iniciarSessao();
        $id = (int) ($_SESSION['pre_2fa'] ?? 0);
        if (!$id) {
            return 'invalido';
        }
        // Senha certa vale por 5 minutos para digitar o código.
        if (time() - (int) ($_SESSION['pre_2fa_em'] ?? 0) > 300) {
            unset($_SESSION['pre_2fa'], $_SESSION['pre_2fa_em']);
            return 'expirado';
        }
        $stmt = Database::conexao()->prepare(
            'SELECT email, duplo_fator_segredo FROM usuarios WHERE id = ? AND ativo = 1'
        );
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u) {
            return 'invalido';
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (self::estaBloqueado((string) $u['email'], $ip)) {
            unset($_SESSION['pre_2fa'], $_SESSION['pre_2fa_em']);
            Auditoria::falhaDeAcesso('login_bloqueado');
            return 'bloqueado';
        }
        if (!self::conferirCodigo($id, (string) $u['duplo_fator_segredo'], $codigo)) {
            self::anotarTentativa((string) $u['email'], $ip, false, '2fa');
            Auditoria::falhaDeAcesso('codigo_negado');
            Auditoria::registrar('login_negado', 'usuarios', $id, 'segundo fator incorreto');
            return 'invalido';
        }
        self::anotarTentativa((string) $u['email'], $ip, true, '2fa');
        self::concluirLogin($id, '', 'senha');
        return 'sucesso';
    }

    /** Quem acabou de cadastrar o autenticador no meio do login entra sem outro código. */
    public static function concluirDepoisDoCadastro(int $usuarioId): void
    {
        self::iniciarSessao();
        if ((int) ($_SESSION['pre_2fa'] ?? 0) === $usuarioId) {
            self::concluirLogin($usuarioId, 'cadastrou o segundo fator no primeiro acesso', 'senha');
        }
    }

    /**
     * Entrada pela passkey. Não passa pelo segundo fator: o aparelho só
     * assina depois de conferir a digital, o rosto ou o PIN, então já são
     * duas provas, uma que se tem e outra que se é.
     */
    public static function entrarPorBiometria(int $usuarioId, string $aparelho): void
    {
        self::iniciarSessao();
        self::concluirLogin($usuarioId, 'pela biometria do aparelho "' . $aparelho . '"', 'biometria');
    }

    /**
     * Entrada pelo certificado digital A1. Vale pelos dois fatores, como no
     * Orbit: o certificado só entra se a própria pessoa o vinculou, logada,
     * e o computador provou ter a chave privada.
     */
    public static function entrarPorCertificado(int $usuarioId, string $titular): void
    {
        self::iniciarSessao();
        self::concluirLogin($usuarioId, 'pelo certificado digital de ' . $titular, 'certificado');
    }

    private static function concluirLogin(int $usuarioId, string $comoEntrou, string $meio): void
    {
        $pdo = Database::conexao();
        // Id novo no login: quem plantou um id de sessão antes não herda a conta.
        session_regenerate_id(true);
        $lembrar = $_SESSION['lembrar'] ?? null;
        $csrf    = $_SESSION['csrf'] ?? null;
        $_SESSION = [];
        if ($csrf) {
            $_SESSION['csrf'] = $csrf;
        }
        $_SESSION['usuario_id'] = $usuarioId;
        $_SESSION['entrou_por'] = $meio;
        $_SESSION['logado_em']  = time();
        $_SESSION['ultimo_uso'] = time();
        $v = $pdo->prepare('SELECT versao_sessao FROM usuarios WHERE id = ?');
        $v->execute([$usuarioId]);
        $_SESSION['versao_sessao'] = (int) $v->fetchColumn();
        self::$cache = null;
        self::$permissoesCache = null;

        // "Manter conectado" desmarcado: o cookie some ao fechar o navegador.
        if ($lembrar === false && !headers_sent()) {
            $p = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires' => 0, 'path' => $p['path'], 'domain' => $p['domain'],
                'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => 'Lax',
            ]);
        }
        $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW(), ultimo_ip = ? WHERE id = ?')
            ->execute([$_SERVER['REMOTE_ADDR'] ?? null, $usuarioId]);
        Empresa::escolherAoEntrar($usuarioId);
        Auditoria::registrar('entrou', 'usuarios', $usuarioId, $comoEntrou ?: null);
    }

    public static function sair(): void
    {
        self::iniciarSessao();
        if (!empty($_SESSION['usuario_id'])) {
            Auditoria::registrar('saiu', 'usuarios', (int) $_SESSION['usuario_id']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$cache = null;
        self::$permissoesCache = null;
    }

    // ------------------------------------------------ SEGUNDO FATOR

    /** Segredo do autenticador cifrado: um dump sozinho não gera código de ninguém. */
    public static function guardarSegredo(string $claro): string
    {
        return 'v1:' . Cripto::guardar($claro);
    }

    public static function abrirSegredo(?string $guardado): string
    {
        $guardado = (string) $guardado;
        return str_starts_with($guardado, 'v1:') ? Cripto::abrir(substr($guardado, 3)) : '';
    }

    /**
     * Confere o código e o gasta: o mesmo código, ou um de período anterior
     * ao último aceito, não vale de novo. A gravação condicional resolve
     * também dois envios simultâneos do mesmo código.
     */
    public static function conferirCodigo(int $usuarioId, string $segredoGuardado, string $codigo): bool
    {
        $periodo = TOTP::periodoDoCodigo(self::abrirSegredo($segredoGuardado), $codigo);
        if ($periodo === null) {
            return false;
        }
        $up = Database::conexao()->prepare(
            'UPDATE usuarios SET duplo_fator_ultimo_periodo = ?
             WHERE id = ? AND (duplo_fator_ultimo_periodo IS NULL OR duplo_fator_ultimo_periodo < ?)'
        );
        $up->execute([$periodo, $usuarioId, $periodo]);
        return $up->rowCount() === 1;
    }

    /** Grava o autenticador recém cadastrado, já gastando o código que o confirmou. */
    public static function ligarSegundoFator(int $usuarioId, string $segredoClaro, string $codigo): bool
    {
        $periodo = TOTP::periodoDoCodigo($segredoClaro, $codigo);
        if ($periodo === null) {
            return false;
        }
        Database::conexao()->prepare(
            'UPDATE usuarios SET duplo_fator_segredo = ?, duplo_fator_confirmado = 1,
                    duplo_fator_ultimo_periodo = ?, atualizado_em = NOW()
             WHERE id = ?'
        )->execute([self::guardarSegredo($segredoClaro), $periodo, $usuarioId]);
        return true;
    }

    // ------------------------------------------------------- SESSÕES

    /**
     * Derruba as sessões abertas da pessoa. Vale para troca de senha,
     * desativação, troca de papel e reinício do segundo fator. Com
     * $manterEsta, a sessão de quem fez a troca na própria conta continua.
     */
    public static function invalidarSessoes(int $usuarioId, bool $manterEsta = false): void
    {
        $pdo = Database::conexao();
        $pdo->prepare('UPDATE usuarios SET versao_sessao = versao_sessao + 1 WHERE id = ?')
            ->execute([$usuarioId]);
        if ($manterEsta && (int) ($_SESSION['usuario_id'] ?? 0) === $usuarioId) {
            $v = $pdo->prepare('SELECT versao_sessao FROM usuarios WHERE id = ?');
            $v->execute([$usuarioId]);
            $_SESSION['versao_sessao'] = (int) $v->fetchColumn();
        }
        if ((int) ($_SESSION['usuario_id'] ?? 0) === $usuarioId) {
            self::$cache = null;
        }
    }

    /** Regras da senha nova, num lugar só. Devolve a mensagem de erro, ou null. */
    public static function validarSenhaNova(string $nova, string $confirma, string $email, string $nome = ''): ?string
    {
        if (mb_strlen($nova) < 12) {
            return 'A senha precisa ter pelo menos 12 caracteres.';
        }
        if ($nova !== $confirma) {
            return 'As duas senhas não são iguais.';
        }
        $minuscula = mb_strtolower($nova);
        $usuario = mb_strtolower((string) strstr($email, '@', true));
        if ($minuscula === mb_strtolower($email) || ($usuario !== '' && mb_strlen($usuario) >= 4 && str_contains($minuscula, $usuario))) {
            return 'A senha não pode conter o seu e-mail.';
        }
        foreach (preg_split('/\s+/', mb_strtolower(trim($nome))) ?: [] as $parte) {
            if (mb_strlen($parte) >= 4 && str_contains($minuscula, $parte)
                && mb_strlen(preg_replace('/[^\p{L}]/u', '', str_replace($parte, '', $minuscula))) < 4) {
                return 'A senha é quase só o seu nome. Use uma frase que só você saiba.';
            }
        }
        if (count(array_unique(mb_str_split($nova))) < 5) {
            return 'A senha repete poucos caracteres. Use uma frase que só você saiba.';
        }
        if (self::senhaVazada($nova)) {
            return 'Esta senha aparece em listas de senhas vazadas na internet. Escolha outra.';
        }
        return null;
    }

    /**
     * Have I Been Pwned por k-anonimato: só os 5 primeiros caracteres do
     * SHA-1 saem do servidor. Se a consulta falhar, não impede a troca.
     */
    private static function senhaVazada(string $senha): bool
    {
        $sha = strtoupper(sha1($senha));
        $ctx = stream_context_create(['http' => [
            'timeout' => 3, 'ignore_errors' => true,
            'header'  => "User-Agent: InvenTour\r\nAdd-Padding: true\r\n",
        ]]);
        $r = @file_get_contents('https://api.pwnedpasswords.com/range/' . substr($sha, 0, 5), false, $ctx);
        if (!is_string($r) || $r === '') {
            return false;
        }
        foreach (preg_split('/\r?\n/', $r) as $linha) {
            [$resto, $vezes] = array_pad(explode(':', trim($linha)), 2, '0');
            if ($resto === substr($sha, 5) && (int) $vezes > 0) {
                return true;
            }
        }
        return false;
    }

    /** Troca a senha e derruba as outras sessões. */
    public static function definirSenha(int $usuarioId, string $nova, bool $manterEsta = false): void
    {
        Database::conexao()->prepare(
            'UPDATE usuarios SET senha_hash = ?, precisa_trocar_senha = 0, atualizado_em = NOW() WHERE id = ?'
        )->execute([self::hashSenha($nova), $usuarioId]);
        self::invalidarSessoes($usuarioId, $manterEsta);
    }

    // -------------------------------------------------------- BLOQUEIO

    public static function anotarTentativa(string $email, string $ip, bool $sucesso, string $etapa = 'senha'): void
    {
        Database::conexao()
            ->prepare('INSERT INTO tentativas_login (email, ip, sucesso, etapa, criado_em) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$email, $ip, $sucesso ? 1 : 0, $etapa]);
    }

    /**
     * Bloqueia por e-mail E por IP. Só por e-mail, um atacante derruba a
     * conta alheia de propósito; só por IP, varre muitos e-mails de um lugar
     * só. Erros de senha e de código somam no mesmo limite.
     */
    public static function estaBloqueado(string $email, string $ip): bool
    {
        $cfg    = require __DIR__ . '/config.php';
        $max    = (int) $cfg['app']['max_tentativas_login'];
        $janela = (int) $cfg['app']['bloqueio_login_min'];
        $stmt = Database::conexao()->prepare(
            "SELECT SUM(email = ? AND sucesso = 0) AS por_email,
                    SUM(ip = ?    AND sucesso = 0) AS por_ip
             FROM tentativas_login
             WHERE criado_em > DATE_SUB(NOW(), INTERVAL ? MINUTE)
               AND etapa IN ('senha', '2fa')
               AND (email = ? OR ip = ?)"
        );
        $stmt->execute([$email, $ip, $janela, $email, $ip]);
        $r = $stmt->fetch();
        // Por IP é mais folgado: a prefeitura inteira sai pelo mesmo IP.
        return ((int) ($r['por_email'] ?? 0)) >= $max
            || ((int) ($r['por_ip'] ?? 0)) >= $max * 4;
    }

    // -------------------------------------------------------- CONSULTA

    /** Usuário logado, ou null. Não redireciona. */
    /**
     * A sessão ainda vale, sem contar como uso (não estende o tempo de
     * inatividade): para a página saber se deve voltar ao login.
     */
    public static function sessaoAinda(): bool
    {
        self::iniciarSessao();
        if (empty($_SESSION['usuario_id'])) {
            return false;
        }
        $cfg = require __DIR__ . '/config.php';
        $agora = time();
        return $agora - (int) ($_SESSION['logado_em'] ?? $agora) <= self::SESSAO_MAXIMA_SEG
            && $agora - (int) ($_SESSION['ultimo_uso'] ?? $agora) <= ((int) $cfg['app']['timeout_sessao_min']) * 60;
    }

    public static function usuarioOuNulo(): ?array
    {
        self::iniciarSessao();
        if (self::$cache !== null) {
            return self::$cache;
        }
        $id = $_SESSION['usuario_id'] ?? null;
        if (!$id) {
            return null;
        }
        $cfg = require __DIR__ . '/config.php';
        $agora  = time();
        $logado = (int) ($_SESSION['logado_em'] ?? $agora);
        $uso    = (int) ($_SESSION['ultimo_uso'] ?? $agora);
        if ($agora - $logado > self::SESSAO_MAXIMA_SEG
            || $agora - $uso > ((int) $cfg['app']['timeout_sessao_min']) * 60) {
            self::encerrarSessaoVencida();
            return null;
        }
        $_SESSION['ultimo_uso'] = $agora;

        $stmt = Database::conexao()->prepare(
            'SELECT u.id, u.nome, u.apelido, u.departamento, u.funcao, u.email, u.telefone, u.foto, u.softsul, u.papel_softsul_id,
                    u.versao_sessao, u.precisa_trocar_senha, u.duplo_fator_confirmado,
                    u.empresa_padrao_id, u.tema, u.por_pagina
             FROM usuarios u WHERE u.id = ? AND u.ativo = 1'
        );
        $stmt->execute([$id]);
        $usuario = $stmt->fetch() ?: null;
        if (!$usuario || (int) ($_SESSION['versao_sessao'] ?? -1) !== (int) $usuario['versao_sessao']) {
            self::encerrarSessaoVencida();
            return null;
        }
        self::$cache = $usuario;
        // O papel de agora depende do destino escolhido.
        $papel = self::papelAtual();
        if (!$papel) {
            // Perdeu o vínculo com o destino em que estava: tenta outro.
            Empresa::escolherAoEntrar((int) $usuario['id']);
            $papel = self::papelAtual();
            if (!$papel) {
                self::encerrarSessaoVencida();
                return null;
            }
        }
        self::$cache += [
            'papel_id' => (int) $papel['id'], 'papel' => $papel['chave'],
            'papel_nome' => $papel['nome'], 'escopo' => $papel['escopo'],
            'papeis' => $papel['papeis'],
        ];
        return self::$cache;
    }

    /**
     * O papel que vale agora: o da Softsul para a equipe Softsul, e os do
     * vínculo com o destino escolhido para os demais. No destino a pessoa
     * pode ter vários papéis (curador e imprensa, por exemplo): o nome junta
     * todos, o principal é o de menor ordem, e as permissões somam.
     */
    private static function papelAtual(): ?array
    {
        $u = self::$cache;
        $pdo = Database::conexao();
        if ($u['softsul']) {
            $q = $pdo->prepare('SELECT id, chave, nome, escopo FROM papeis WHERE id = ?');
            $q->execute([(int) $u['papel_softsul_id']]);
            $p = $q->fetch();
            return $p ? $p + ['papeis' => [(int) $p['id']]] : null;
        }
        $empresa = Empresa::id();
        if (!$empresa) {
            return null;
        }
        $q = $pdo->prepare(
            'SELECT p.id, p.chave, p.nome, p.escopo
             FROM usuario_empresa ue
             JOIN usuario_empresa_papel uep ON uep.usuario_id = ue.usuario_id AND uep.empresa_id = ue.empresa_id
             JOIN papeis p   ON p.id = uep.papel_id
             JOIN empresas e ON e.id = ue.empresa_id
             WHERE ue.usuario_id = ? AND ue.empresa_id = ? AND ue.ativo = 1 AND e.ativo = 1
             ORDER BY p.ordem'
        );
        $q->execute([(int) $u['id'], $empresa]);
        $todos = $q->fetchAll();
        if (!$todos) {
            return null;
        }
        $escopos = array_column($todos, 'escopo');
        return [
            'id'     => (int) $todos[0]['id'],
            'chave'  => $todos[0]['chave'],
            'nome'   => implode(', ', array_column($todos, 'nome')),
            'escopo' => in_array('destino', $escopos, true) ? 'destino' : $todos[0]['escopo'],
            'papeis' => array_map('intval', array_column($todos, 'id')),
        ];
    }

    /** As chaves dos papéis de agora, para regras como "só o gestor dá o papel de gestor". */
    public static function temPapel(string $chave): bool
    {
        $u = self::usuarioOuNulo();
        if (!$u) {
            return false;
        }
        $marcas = implode(',', array_map('intval', $u['papeis'] ?: [0]));
        return (bool) Database::conexao()->query("SELECT 1 FROM papeis WHERE chave = " . Database::conexao()->quote($chave) . " AND id IN ({$marcas})")->fetchColumn();
    }

    private static function encerrarSessaoVencida(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$cache = null;
        self::$permissoesCache = null;
    }

    /** Limpa o que foi calculado para o destino anterior, depois de trocar de destino. */
    public static function esquecerCache(): void
    {
        self::$cache = null;
        self::$permissoesCache = null;
    }

    public static function ehSoftsul(): bool
    {
        $u = self::usuarioOuNulo();
        return $u !== null && (bool) $u['softsul'];
    }

    /**
     * Todas as permissões efetivas, como chave => true, em quatro etapas:
     * 1. as permissões dos papéis de agora, somadas;
     * 2. as exceções da pessoa (usuario_permissao), que ganham do papel;
     * 3. fora o que é apenas_softsul, para quem não é da Softsul;
     * 4. fora o que não vale neste lugar: na visão da plataforma (empresa 0)
     *    valem os módulos de contexto 'plataforma' e 'ambos'; dentro de um
     *    destino valem 'destino' e 'ambos'.
     * Permissão de módulo inativo nunca entra.
     */
    public static function permissoes(): array
    {
        if (self::$permissoesCache !== null) {
            return self::$permissoesCache;
        }
        $u = self::usuarioOuNulo();
        if (!$u) {
            return self::$permissoesCache = [];
        }
        $pdo = Database::conexao();
        $marcas = implode(',', array_fill(0, count($u['papeis']), '?'));
        $stmt = $pdo->prepare(
            "SELECT DISTINCT pe.chave, m.apenas_softsul
             FROM papel_permissao pp
             JOIN permissoes pe ON pe.id = pp.permissao_id
             JOIN modulos m     ON m.id  = pe.modulo_id
             WHERE pp.papel_id IN ({$marcas}) AND m.ativo = 1"
        );
        $stmt->execute($u['papeis']);
        $mapa = [];
        foreach ($stmt->fetchAll() as $linha) {
            $mapa[$linha['chave']] = (bool) $linha['apenas_softsul'];
        }
        // A exceção individual ganha do papel: é justamente a escolha
        // deliberada. Para a equipe Softsul vale a linha de empresa 0.
        $extra = $pdo->prepare(
            'SELECT pe.chave, up.concedida, m.apenas_softsul
             FROM usuario_permissao up
             JOIN permissoes pe ON pe.id = up.permissao_id
             JOIN modulos m     ON m.id  = pe.modulo_id
             WHERE up.usuario_id = ? AND up.empresa_id = ? AND m.ativo = 1'
        );
        $extra->execute([$u['id'], $u['softsul'] ? 0 : Empresa::id()]);
        foreach ($extra->fetchAll() as $linha) {
            if ($linha['concedida']) {
                $mapa[$linha['chave']] = (bool) $linha['apenas_softsul'];
            } else {
                unset($mapa[$linha['chave']]);
            }
        }
        // Módulo só da Softsul nunca vale para quem é de destino, mesmo que
        // alguém conceda por engano.
        if (!$u['softsul']) {
            $mapa = array_filter($mapa, fn ($soSoftsul) => !$soSoftsul);
        }
        // E cada módulo vale só no seu lugar: dentro de um destino some o que
        // é da plataforma (Destinos), e na visão da plataforma some o que só
        // existe num destino (Eventos, Organizadores).
        $fora = Empresa::id() ? 'plataforma' : 'destino';
        $q = $pdo->prepare('SELECT chave FROM modulos WHERE contexto = ?');
        $q->execute([$fora]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $modulo) {
            foreach (array_keys($mapa) as $chave) {
                if (str_starts_with($chave, $modulo . '.')) {
                    unset($mapa[$chave]);
                }
            }
        }
        return self::$permissoesCache = array_fill_keys(array_keys($mapa), true);
    }

    public static function pode(string $permissao): bool
    {
        return isset(self::permissoes()[$permissao]);
    }

    /** Módulos que a pessoa pode abrir, na ordem do menu. */
    public static function modulosVisiveis(): array
    {
        $podeVer = array_keys(array_filter(
            self::permissoes(),
            fn ($chave) => str_ends_with($chave, '.ver'),
            ARRAY_FILTER_USE_KEY
        ));
        if (!$podeVer) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($podeVer), '?'));
        $stmt = Database::conexao()->prepare(
            'SELECT m.chave, m.nome, m.icone, m.grupo, m.grupo_ordem, m.ordem, m.desligado
             FROM modulos m
             WHERE m.ativo = 1 AND CONCAT(m.chave, ".ver") IN (' . $marcas . ')
               AND m.no_menu = 1
             ORDER BY m.grupo_ordem, m.ordem'
        );
        $stmt->execute($podeVer);
        return $stmt->fetchAll();
    }

    /** Módulo que a plataforma desligou fica no menu, mas a tela não abre. */
    public static function exigirModuloLigado(string $chave): void
    {
        $q = Database::conexao()->prepare('SELECT nome, desligado FROM modulos WHERE chave = ?');
        $q->execute([$chave]);
        $m = $q->fetch();
        if ($m && (int) $m['desligado']) {
            require_once __DIR__ . '/layout.php';
            paginaErro(403, $m['nome'] . ' está desabilitado por enquanto.', 'A plataforma libera este módulo quando ele estiver pronto para uso.');
        }
    }

    // -------------------------------------------------------- BARREIRAS

    public static function exigirLogin(): array
    {
        $u = self::usuarioOuNulo();
        if (!$u) {
            header('Location: /login?destino=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }
        // Segundo fator obrigatório e ainda não cadastrado: nada além do
        // cadastro. Cobre quem passou a ser obrigado depois de já ter sessão.
        // Quem entrou pela digital ou pelo certificado já provou dois fatores,
        // como no Orbit, e não é obrigado a cadastrar o autenticador.
        $aqui = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $soSenha = ($_SESSION['entrou_por'] ?? 'senha') === 'senha';
        if (!in_array($aqui, ['/primeiro-acesso', '/sair'], true)
            && ($u['precisa_trocar_senha']
                || ($soSenha && !$u['duplo_fator_confirmado'] && self::segundoFatorObrigatorio((int) $u['id'])))) {
            header('Location: /primeiro-acesso');
            exit;
        }
        return $u;
    }

    public static function exigirPermissao(string $permissao): array
    {
        $u = self::exigirLogin();
        if (!self::pode($permissao)) {
            Auditoria::registrar('acesso_negado', null, null, "sem permissão {$permissao}");
            require_once __DIR__ . '/layout.php';
            paginaErro(403, 'Esta área não está liberada para você.', 'Se precisar dela, peça ao gestor do seu destino.');
        }
        return $u;
    }

    // ----------------------------------------------------- PREFERÊNCIAS

    /**
     * Quantas linhas a pessoa quer por página, em qualquer lista. Fica na
     * conta, como no Orbit: em tela grande cinquenta linhas fazem rolar o dia
     * inteiro, no celular duzentas não cabem.
     */
    public const POR_PAGINA_OPCOES = [25, 50, 100, 200];

    public static function porPagina(): int
    {
        $n = (int) (self::usuarioOuNulo()['por_pagina'] ?? 0);
        return in_array($n, self::POR_PAGINA_OPCOES, true) ? $n : 50;
    }

    public static function guardarPorPagina(int $quantos): void
    {
        $u = self::usuarioOuNulo();
        if (!$u || !in_array($quantos, self::POR_PAGINA_OPCOES, true)) {
            return;
        }
        Database::conexao()->prepare('UPDATE usuarios SET por_pagina = ? WHERE id = ?')->execute([$quantos, (int) $u['id']]);
        self::$cache['por_pagina'] = $quantos;
    }

    public static function temaEscuro(): bool
    {
        // O escuro é o padrão do InvenTour: só o claro escolhido é exceção.
        return (self::usuarioOuNulo()['tema'] ?? 'escuro') !== 'claro';
    }

    public static function guardarTema(bool $escuro): void
    {
        $u = self::usuarioOuNulo();
        if (!$u) {
            return;
        }
        Database::conexao()->prepare('UPDATE usuarios SET tema = ? WHERE id = ?')->execute([$escuro ? 'escuro' : 'claro', (int) $u['id']]);
        self::$cache['tema'] = $escuro ? 'escuro' : 'claro';
    }

    // ------------------------------------------------------------ CSRF

    public static function tokenCsrf(): string
    {
        self::iniciarSessao();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function conferirCsrf(?string $enviado): bool
    {
        self::iniciarSessao();
        return !empty($_SESSION['csrf']) && is_string($enviado) && hash_equals($_SESSION['csrf'], $enviado);
    }

    /** Argon2id, como no Orbit. */
    public static function hashSenha(string $senha): string
    {
        return password_hash($senha, PASSWORD_ARGON2ID);
    }
}
