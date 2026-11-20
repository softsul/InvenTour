<?php
/**
 * Limite de requisições por chave, em janela fixa, sobre a tabela `limites`.
 * Softsul Software
 *
 * Por que existe: o painel tem APIs que consultam serviço externo (CEP,
 * CNPJ), buscas ao vivo que vão ao banco a cada tecla e telas públicas
 * (esqueci a senha, convite, entrada pela digital); o portal recebe o
 * tráfego aberto da internet. Sem limite, um script qualquer transforma
 * cada uma delas em consulta gratuita ou em carga no banco. O bloqueio de
 * login por tentativas_login (Auth) continua sendo o do login; este aqui é
 * o freio geral das outras rotas.
 *
 * Como conta: janela fixa (o minuto ou a hora alinhados ao relógio) e uma
 * linha por chave e janela. Cada requisição faz UMA ida ao banco:
 *
 *     INSERT ... VALUES (chave, janela, LAST_INSERT_ID(1))
 *     ON DUPLICATE KEY UPDATE contagem = LAST_INSERT_ID(contagem + 1)
 *
 * e lê o valor novo por lastInsertId(), sem SELECT antes. Duas requisições
 * ao mesmo tempo nunca leem o mesmo número, porque o incremento é atômico
 * na linha. A janela fixa deixa passar até o dobro do máximo na virada do
 * minuto; para freio de abuso isso basta, e custa uma linha por janela em
 * vez de uma por requisição.
 *
 * Log: acima do máximo, uma linha no logs/seguranca.log com o evento
 * limite_excedido, no formato que o filtro do fail2ban já lê. Só a
 * requisição de número máximo+1 escreve, então é UMA linha por chave e
 * janela, mesmo num ataque de milhares de requisições: o log não enche e o
 * fail2ban (maxretry 30) só bane quem estoura o limite em 30 janelas.
 *
 * Se o banco recusar (o usuário do portal sem INSERT na tabela, por
 * exemplo), o limite deixa passar: o freio nunca derruba o site que ele
 * protege. O erro vai para o log do PHP no máximo uma vez por hora.
 *
 * NÚMEROS ESCOLHIDOS E O PORQUÊ (mudou aqui, muda no relatório da entrega):
 *
 *   Rota                        Limite              Chave    Por quê
 *   api/cep                     20 por minuto       usuário  consulta serviço externo (BrasilAPI); quem
 *                                                            cadastra digita um CEP por ficha
 *   api/cnpj                    20 por minuto       usuário  consulta serviço externo, pago pela Softsul
 *   api/busca                   120 por minuto      usuário  busca ao vivo do Ctrl+K: uma chamada por tecla
 *   api/organizadores-busca     120 por minuto      usuário  busca ao vivo do campo de escolha
 *   api/apelido-organizador     120 por minuto      usuário  conferência do apelido do link do organizador
 *   api/municipios              120 por minuto      usuário  busca ao vivo do campo de município
 *   api/regioes-turisticas      120 por minuto      usuário  busca ao vivo do filtro de região turística
 *   api/datas-do-periodo        120 por minuto      usuário  aviso de feriado ao escolher a data do evento
 *   api/comandos                30 por minuto       usuário  carregada ao abrir a paleta, não a cada tecla
 *   api/contadores              60 por minuto       usuário  o menu pergunta a cada 30 s por aba; 60 cobre
 *                                                            dezenas de abas abertas
 *   api/sessao                  60 por minuto       usuário  chamada ao voltar para a aba; sem sessão vale o IP
 *   api/tema                    30 por minuto       usuário  um clique no menu; mais que isso é script
 *   api/foto-whatsapp           10 por minuto       usuário  botão do editor de foto; consulta o WhatsApp
 *   api/passkey-registrar       20 por minuto       usuário  cadastro de aparelho, dois passos por cadastro
 *   api/passkey-entrar          20 por minuto       IP       público; dois passos por entrada, então 10
 *                                                            entradas por minuto saindo do mesmo IP
 *   esqueci-a-senha             20 por 10 minutos   IP       público; uma pessoa usa 2 a 4 requisições. O
 *                                                            limite de envio por e-mail (3 por hora) e por
 *                                                            IP (10 por hora) da própria tela continua valendo
 *   convite                     60 por 10 minutos   IP       público, protege o token contra tentativa e erro;
 *                                                            folgado porque um treinamento numa prefeitura
 *                                                            põe 20 pessoas no mesmo IP definindo senha
 *   portal (toda página)        120 por minuto      IP       leitura pública; uma pessoa navegando não passa
 *                                                            de 20 páginas por minuto, e o resto é folga para
 *                                                            NAT de rede móvel e de prefeitura
 *
 * Nas rotas com login a chave é o usuário, e não o IP, porque uma
 * prefeitura inteira sai por um IP só: limitar por IP ali puniria a equipe
 * toda pelo que uma pessoa faz.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';

final class Limite
{
    /** Tamanho da coluna limites.chave: chave maior vira hash, para não estourar. */
    private const TAMANHO_CHAVE = 120;

    private static ?PDO $pdo = null;

    /**
     * Usa outra conexão, a do portal por exemplo. Sem isto vale a do painel.
     * O portal chama com Database::conexaoPortal(), porque lá a conexão do
     * painel não deve nem ser aberta.
     */
    public static function usarConexao(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /** true quando a requisição ainda está dentro do limite. */
    public static function contar(string $chave, int $maximo, int $janelaSeg): bool
    {
        return self::registrar($chave, $janelaSeg) <= $maximo;
    }

    /**
     * Conta e, acima do limite, responde 429 e encerra. $motivo é o texto
     * para quem lê o log do PHP saber qual limite disparou; o seguranca.log
     * fica no formato fixo do fail2ban.
     */
    public static function exigir(string $chave, int $maximo, int $janelaSeg, string $motivo): void
    {
        $contagem = self::registrar($chave, $janelaSeg);
        if ($contagem <= $maximo) {
            return;
        }
        // Só a primeira requisição acima do máximo escreve: uma linha por
        // chave e janela, por maior que seja o ataque.
        if ($contagem === $maximo + 1) {
            Auditoria::falhaDeAcesso('limite_excedido');
            error_log('inventour limite excedido: ' . $motivo . ' (' . $chave . ', ' . $maximo . ' em ' . $janelaSeg . ' s)');
        }
        self::responder429(self::fimDaJanela($janelaSeg) - time());
    }

    /** Atalho: limite pelo IP de quem pede (IPv6 agrupado por /64). */
    public static function porIp(string $escopo, int $maximo, int $janelaSeg): void
    {
        self::exigir($escopo . ':ip:' . self::ipAgrupado(), $maximo, $janelaSeg, $escopo . ' por IP');
    }

    /**
     * Atalho: limite pela pessoa da sessão, ou pelo IP quando não há sessão.
     * Lê o id direto da sessão, sem Auth::usuarioOuNulo(), para não contar
     * como uso (api/sessao depende disso) nem gastar consulta a mais.
     */
    public static function porUsuario(string $escopo, int $maximo, int $janelaSeg): void
    {
        if (class_exists('Auth', false)) {
            Auth::iniciarSessao();
        }
        $uid = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($uid > 0) {
            self::exigir($escopo . ':u:' . $uid, $maximo, $janelaSeg, $escopo . ' por usuário');
            return;
        }
        self::porIp($escopo, $maximo, $janelaSeg);
    }

    /**
     * O IP real (REMOTE_ADDR: o servidor não está atrás de proxy, então
     * nenhum cabeçalho X-Forwarded-For é confiável). IPv6 vira o /64: quem
     * tem um endereço IPv6 normalmente tem a sub-rede inteira, bilhões de
     * endereços, e contar por endereço deixaria cada requisição com um novo.
     * IPv4 escrito como IPv6 (::ffff:1.2.3.4) volta a ser IPv4.
     */
    public static function ipAgrupado(?string $ip = null): string
    {
        $ip = $ip ?? (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return '0.0.0.0';
        }
        if (strlen($bin) === 4) {
            return (string) inet_ntop($bin);
        }
        if (str_starts_with($bin, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($bin, 12));
        }
        return inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    /**
     * Soma um na chave e devolve a contagem nova, numa ida só ao banco.
     * Falha do banco devolve 0 (dentro do limite): ver o cabeçalho.
     */
    private static function registrar(string $chave, int $janelaSeg): int
    {
        $janelaSeg = max(1, $janelaSeg);
        if (strlen($chave) > self::TAMANHO_CHAVE) {
            $chave = 'h:' . sha1($chave);
        }
        $inicio = intdiv(time(), $janelaSeg) * $janelaSeg;
        try {
            $pdo = self::$pdo ?? Database::conexao();
            // FROM_UNIXTIME converte no fuso da conexão (-03:00), o mesmo do
            // NOW() da faxina que apaga as janelas velhas.
            $pdo->prepare(
                'INSERT INTO limites (chave, janela, contagem) VALUES (?, FROM_UNIXTIME(?), LAST_INSERT_ID(1))
                 ON DUPLICATE KEY UPDATE contagem = LAST_INSERT_ID(contagem + 1)'
            )->execute([$chave, $inicio]);
            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            self::avisarFalha($e);
            return 0;
        }
    }

    private static function fimDaJanela(int $janelaSeg): int
    {
        $janelaSeg = max(1, $janelaSeg);
        return (intdiv(time(), $janelaSeg) + 1) * $janelaSeg;
    }

    /**
     * 429 com Retry-After e corpo mínimo: JSON para as APIs (o inventour.js
     * lê JSON), uma linha de texto para página. Sem HTML, sem layout e sem
     * consulta: quem passou do limite não deve custar mais nada.
     */
    private static function responder429(int $segundos): never
    {
        $segundos = max(1, $segundos);
        if (!headers_sent()) {
            http_response_code(429);
            header('Retry-After: ' . $segundos);
            header('Cache-Control: no-store');
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $querJson = str_starts_with($uri, '/api/') || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        if ($querJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['ok' => false, 'erro' => 'Muitas requisições. Tente de novo em ' . $segundos . ' s.'], JSON_UNESCAPED_UNICODE);
        } else {
            if (!headers_sent()) {
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo 'Muitas requisições. Tente de novo em ' . $segundos . ' s.';
        }
        exit;
    }

    /**
     * Uma linha no log do PHP por hora, no máximo: sem a permissão no banco,
     * cada requisição do portal falharia, e o log do Apache viraria isso.
     */
    private static function avisarFalha(Throwable $e): void
    {
        $marca = sys_get_temp_dir() . '/inventour-limite-falhou';
        if (!is_file($marca) || filemtime($marca) < time() - 3600) {
            @touch($marca);
            error_log('inventour limite: contagem falhou, requisição liberada: ' . $e->getMessage());
        }
    }
}
