<?php
/**
 * Trade turístico (etapa 6): quem responde por um lugar do inventário e o
 * que ele quer mudar na ficha.
 * Softsul Software
 *
 * Só em destino ativo (o licenciado). O pedido nasce na ficha pública do
 * portal (public/reivindicar.php): CNPJ ou Cadastur, comprovante e o aceite
 * do termo. A equipe aprova, rejeita com motivo ou transfere a titularidade
 * (o responsável anterior fica revogado). Quando o Cadastur do pedido bate
 * com a base oficial e o contato bate com o do Cadastur ou da ficha, o
 * pedido chega marcado "Confere" e se aprova num clique.
 *
 * Aprovado, o responsável entra com o papel prestador e propõe mudanças em
 * contato, funcionamento, capacidade, texto para o turista, tribos e
 * fotos. Nada vai direto para a ficha: a proposta vira uma revisão com o
 * antes e o depois (inventario_revisoes); ao aprovar, a ficha muda, a data
 * da última conferência renova e o EventCity recebe a versão nova. CNPJ,
 * razão social, ficha, tipo, endereço e conservação ficam com a equipe.
 *
 * Os arquivos (comprovante e fotos à espera) ficam em storage/trade/<destino>/,
 * fora do DocumentRoot, e saem só por /trade-arquivo, com login.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/Inventario.php';
require_once __DIR__ . '/svg.php';
require_once __DIR__ . '/funcoes.php';

final class Trade
{
    public const TERMO_VERSAO = '2026-11';

    /** O termo que a pessoa aceita ao pedir (o mesmo texto fica no pedido pela versão). */
    public const TERMO = [
        'Declaro que respondo por este estabelecimento como proprietário, sócio, gerente ou procurador, e que o comprovante enviado é verdadeiro.',
        'As informações que eu mandar para a ficha passam pela conferência da equipe de turismo do destino antes de aparecer no portal.',
        'Sei que a equipe pode recusar ou desfazer o meu acesso se o comprovante não conferir ou se o estabelecimento mudar de dono.',
        'Meu nome, e-mail e WhatsApp servem só para o meu acesso e para a equipe falar comigo: não vão para a ficha pública.',
    ];

    public const VINCULOS = ['proprietario' => 'Proprietário', 'socio' => 'Sócio', 'gerente' => 'Gerente', 'procurador' => 'Procurador', 'outro' => 'Outro'];

    public const SITUACOES = ['pendente' => ['Esperando análise', 'at'], 'aprovada' => ['Aprovado', 'ok'], 'rejeitada' => ['Rejeitado', 'ruim'], 'revogada' => ['Revogado', 'nu']];

    public const SITUACOES_REVISAO = ['aguardando' => ['Esperando conferência', 'at'], 'aprovada' => ['Aprovada', 'ok'], 'devolvida' => ['Devolvida para ajuste', 'at'], 'rejeitada' => ['Rejeitada', 'ruim']];

    /** O que o responsável pode propor, coluna => rótulo. Capacidade só nas fichas que a têm. */
    public const CAMPOS = [
        'telefone' => 'Telefone', 'ramal' => 'Ramal', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail', 'site' => 'Site', 'instagram' => 'Instagram',
        'horario' => 'Horário de funcionamento', 'formas_pagamento' => 'Formas de pagamento', 'idiomas' => 'Atendimento em', 'idiomas_outro' => 'Outro idioma',
        'descricao_promocional' => 'Texto para o turista',
        'capacidade_uh' => 'Unidades habitacionais', 'capacidade_leitos' => 'Leitos', 'capacidade_assentos' => 'Lugares sentados', 'capacidade_publico' => 'Público simultâneo',
        'facilidades' => 'Facilidades das tribos',
    ];

    private const COMPROVANTES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private static function pdo(): PDO
    {
        return Database::conexao();
    }

    // ------------------------------------------------------------- DESTINO

    /** O trade está aberto neste destino? Só no licenciado. */
    public static function aberto(array $empresa): bool
    {
        return ($empresa['modo_operacao'] ?? '') === 'ativo' && (int) ($empresa['ativo'] ?? 0) === 1;
    }

    /** O lugar, para o pedido público: só o que o portal mostra, em destino ativo. Null se não pode pedir. */
    public static function lugarParaPedir(int $itemId): ?array
    {
        $q = self::pdo()->prepare("SELECT i.id, i.empresa_id, i.nome, i.cnpj, i.cadastur, i.cadastur_prestador_id, i.email, i.whatsapp, i.telefone,
                                          i.logradouro, i.numero, i.bairro, f.nome AS ficha_nome, f.forma, t.nome AS tipo_nome,
                                          e.nome_fantasia AS destino, e.slug, e.modo_operacao, e.ativo
                                   FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                                   LEFT JOIN inventario_tipos t ON t.id = i.tipo_id JOIN empresas e ON e.id = i.empresa_id
                                   WHERE i.id = ? AND i.situacao IN ('homologada', 'desatualizada')");
        $q->execute([$itemId]);
        $l = $q->fetch();
        return $l && $l['forma'] !== 'municipio' && self::aberto($l) ? $l : null;
    }

    // ------------------------------------------------------------- PEDIDOS

    /**
     * Confere o pedido com a ficha e com a base do Cadastur: cnpj_ficha (o
     * CNPJ é o da ficha), cadastur_base (o número está no Cadastur deste
     * lugar), contato (o e-mail ou o WhatsApp é o do Cadastur ou o da ficha).
     */
    public static function conferir(array $lugar, ?string $cnpj, ?string $cadastur, string $email, ?string $whatsapp): array
    {
        $digitos = fn ($v) => preg_replace('/\D/', '', (string) $v);
        $r = ['cnpj_ficha' => $cnpj !== null && $cnpj === $digitos($lugar['cnpj']), 'cadastur_base' => false, 'contato' => false];
        $emails = [mb_strtolower((string) $lugar['email'])];
        $fones = [$digitos($lugar['whatsapp']), $digitos($lugar['telefone'])];
        if ($cadastur !== null) {
            $q = self::pdo()->prepare('SELECT id, documento, email, telefone FROM cadastur_prestadores
                                       WHERE REGEXP_REPLACE(certificado, "[^0-9]", "") = ? AND (id = ? OR (? <> "" AND documento = ?)) LIMIT 1');
            $q->execute([$digitos($cadastur), (int) $lugar['cadastur_prestador_id'], $digitos($lugar['cnpj']), $digitos($lugar['cnpj'])]);
            if ($c = $q->fetch()) {
                $r['cadastur_base'] = true;
                $emails[] = mb_strtolower((string) $c['email']);
                $fones[] = $digitos($c['telefone']);
            }
        }
        $email = mb_strtolower($email);
        $dominio = substr((string) strrchr($email, '@'), 1);
        $publicos = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'yahoo.com.br', 'live.com', 'icloud.com', 'bol.com.br', 'uol.com.br', 'terra.com.br'];
        foreach (array_filter($emails) as $e) {
            if ($e === $email || ($dominio !== '' && !in_array($dominio, $publicos, true) && str_ends_with($e, '@' . $dominio))) {
                $r['contato'] = true;
            }
        }
        $fim = substr($digitos($whatsapp), -8);
        foreach (array_filter($fones) as $f) {
            if (strlen($fim) === 8 && substr($f, -8) === $fim) {
                $r['contato'] = true;
            }
        }
        return $r;
    }

    /** O pedido se aprova num clique: documento e contato conferem. */
    public static function confere(?array $c): bool
    {
        return $c && ($c['cadastur_base'] || $c['cnpj_ficha']) && $c['contato'];
    }

    /** O que conferiu, em frases curtas. */
    public static function conferenciaEmTexto(?array $c): array
    {
        if (!$c) {
            return [];
        }
        return array_values(array_filter([
            $c['cnpj_ficha'] ? 'CNPJ igual ao da ficha' : null,
            $c['cadastur_base'] ? 'Cadastur deste lugar na base do Ministério' : null,
            $c['contato'] ? 'contato igual ao do Cadastur ou da ficha' : null,
        ]));
    }

    private static function pasta(int $eid): string
    {
        $pasta = __DIR__ . '/../storage/trade/' . $eid;
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0770, true);
        }
        return $pasta;
    }

    public static function caminho(int $eid, string $arquivo): string
    {
        return self::pasta($eid) . '/' . basename($arquivo);
    }

    /** Guarda o comprovante enviado. Devolve [arquivo, mime] ou a mensagem de erro. */
    public static function guardarComprovante(int $eid, array $f): array|string
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            return 'Anexe o comprovante (cartão do CNPJ, certificado do Cadastur ou procuração).';
        }
        if ((int) $f['size'] > 10 * 1048576) {
            return 'O comprovante passa de 10 MB.';
        }
        $dados = (string) file_get_contents((string) $f['tmp_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($dados);
        if (!isset(self::COMPROVANTES[$mime])) {
            return 'Mande o comprovante em PDF, JPG ou PNG.';
        }
        if ($mime !== 'application/pdf') {
            // Foto do documento: sai sem os metadados do celular.
            require_once __DIR__ . '/Imagem.php';
            $img = Imagem::foto($dados, 2000);
            if ($img === null) {
                return 'O arquivo do comprovante não abriu.';
            }
            [$dados] = $img;
            $mime = 'image/jpeg';
        }
        $nome = 'comprovante-' . bin2hex(random_bytes(12)) . '.' . self::COMPROVANTES[$mime];
        if (@file_put_contents(self::caminho($eid, $nome), $dados) === false) {
            return 'Não consegui guardar o comprovante agora. Tente de novo em instantes.';
        }
        return [$nome, $mime];
    }

    /** Grava o pedido. $p já validado. Devolve o id. */
    public static function pedir(array $lugar, array $p, array $comprovante, ?string $ip): int
    {
        $eid = (int) $lugar['empresa_id'];
        $conf = self::conferir($lugar, $p['cnpj'], $p['cadastur'], $p['email'], $p['whatsapp']);
        self::pdo()->prepare('INSERT INTO inventario_reivindicacoes (empresa_id, item_id, nome, email, whatsapp, vinculo, cnpj, cadastur, conferencia,
                                     comprovante, comprovante_tipo, termo_versao, aceite_em, aceite_ip, criado_em)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())')
            ->execute([$eid, (int) $lugar['id'], $p['nome'], $p['email'], $p['whatsapp'], $p['vinculo'], $p['cnpj'], $p['cadastur'],
                       json_encode($conf), $comprovante[0], $comprovante[1], self::TERMO_VERSAO, $ip]);
        $id = (int) self::pdo()->lastInsertId();
        Inventario::registrarHistoricoDe($eid, (int) $lugar['id'], 'trade', null, null, 'pedido de responsável de ' . $p['nome']);
        Auditoria::registrar('criou', 'inventario_reivindicacoes', $id, 'pediu para responder por ' . $lugar['nome'], null,
            ['vinculo' => $p['vinculo'], 'confere' => self::confere($conf)], $eid);
        return $id;
    }

    /** Pedido aberto do mesmo e-mail para o mesmo lugar (evita repetir). */
    public static function pedidoAberto(int $itemId, string $email): bool
    {
        $q = self::pdo()->prepare("SELECT 1 FROM inventario_reivindicacoes WHERE item_id = ? AND email = ? AND situacao IN ('pendente', 'aprovada')");
        $q->execute([$itemId, $email]);
        return (bool) $q->fetchColumn();
    }

    public static function pedido(int $id): ?array
    {
        $q = self::pdo()->prepare('SELECT r.*, i.nome AS item_nome, i.cnpj AS item_cnpj, i.cadastur AS item_cadastur, i.situacao AS item_situacao,
                                          f.codigo AS ficha_codigo, f.nome AS ficha_nome, a.nome AS analisado_nome, v.nome AS revogado_nome
                                   FROM inventario_reivindicacoes r JOIN inventario_itens i ON i.id = r.item_id JOIN inventario_fichas f ON f.id = i.ficha_id
                                   LEFT JOIN usuarios a ON a.id = r.analisado_por LEFT JOIN usuarios v ON v.id = r.revogado_por
                                   WHERE r.id = ? AND r.empresa_id = ?');
        $q->execute([$id, Empresa::id()]);
        $r = $q->fetch();
        if ($r) {
            $r['conferencia'] = json_decode((string) $r['conferencia'], true) ?: null;
        }
        return $r ?: null;
    }

    /** O responsável aprovado do lugar (o pedido), ou null. */
    public static function responsavel(int $itemId): ?array
    {
        $q = self::pdo()->prepare("SELECT r.*, u.nome AS usuario_nome FROM inventario_reivindicacoes r LEFT JOIN usuarios u ON u.id = r.usuario_id
                                   WHERE r.item_id = ? AND r.empresa_id = ? AND r.situacao = 'aprovada' ORDER BY r.id DESC LIMIT 1");
        $q->execute([$itemId, Empresa::id()]);
        return $q->fetch() ?: null;
    }

    /**
     * Aprova o pedido: a conta (a mesma do e-mail, se já existe) entra no
     * destino como prestador; o responsável anterior do lugar, se houver,
     * fica revogado (transferência de titularidade). Devolve o link do
     * convite para a pessoa criar a senha (null se ela já tem senha).
     */
    public static function aprovar(int $id): array
    {
        $r = self::pedido($id);
        if (!$r || $r['situacao'] !== 'pendente') {
            return ['erro' => 'Este pedido já foi analisado.'];
        }
        $pdo = self::pdo();
        $eid = (int) $r['empresa_id'];
        $eu = (int) Auth::usuarioOuNulo()['id'];
        $anterior = self::responsavel((int) $r['item_id']);
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT id, nome, senha_hash, ativo, softsul FROM usuarios WHERE email = ?');
            $q->execute([$r['email']]);
            $u = $q->fetch();
            if (!$u) {
                $pdo->prepare('INSERT INTO usuarios (nome, email, telefone, ativo, empresa_padrao_id, criado_em) VALUES (?, ?, ?, 1, ?, NOW())')
                    ->execute([(string) nomeProprio((string) $r['nome']), $r['email'], $r['whatsapp'], $eid]);
                $u = ['id' => (int) $pdo->lastInsertId(), 'senha_hash' => null, 'ativo' => 1];
            }
            $uid = (int) $u['id'];
            $papel = (int) $pdo->query("SELECT id FROM papeis WHERE chave = 'prestador'")->fetchColumn();
            $q = $pdo->prepare('SELECT id, papel_id, ativo FROM usuario_empresa WHERE usuario_id = ? AND empresa_id = ?');
            $q->execute([$uid, $eid]);
            $vinculo = $q->fetch();
            if (!$vinculo) {
                $pdo->prepare('INSERT INTO usuario_empresa (usuario_id, empresa_id, papel_id, ativo, criado_em) VALUES (?, ?, ?, 1, NOW())')->execute([$uid, $eid, $papel]);
                $pdo->prepare('INSERT INTO usuario_empresa_papel (usuario_id, empresa_id, papel_id) VALUES (?, ?, ?)')->execute([$uid, $eid, $papel]);
            } elseif (!(int) $vinculo['ativo'] && (int) $vinculo['papel_id'] === $papel) {
                // Quem já foi responsável aqui e tinha perdido o acesso volta.
                $pdo->prepare('UPDATE usuario_empresa SET ativo = 1, atualizado_em = NOW() WHERE id = ?')->execute([(int) $vinculo['id']]);
            }
            // Da equipe do destino, a pessoa continua com o papel que tem: a
            // aprovação só a liga ao lugar.
            if ($anterior && (int) $anterior['usuario_id'] !== $uid) {
                self::revogarDentro((int) $anterior['id'], 'Titularidade transferida para ' . $r['nome'] . '.', $eu);
            } elseif ($anterior) {
                $pdo->prepare("UPDATE inventario_reivindicacoes SET situacao = 'revogada', motivo = 'Pedido novo da mesma pessoa aprovado.', revogado_por = ?, revogado_em = NOW() WHERE id = ?")
                    ->execute([$eu, (int) $anterior['id']]);
            }
            $pdo->prepare("UPDATE inventario_reivindicacoes SET situacao = 'aprovada', usuario_id = ?, analisado_por = ?, analisado_em = NOW() WHERE id = ?")
                ->execute([$uid, $eu, $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Inventario::registrarHistorico((int) $r['item_id'], 'trade', null, null,
            ($anterior && (int) $anterior['usuario_id'] !== $uid ? 'titularidade transferida para ' : 'responsável aprovado: ') . $r['nome']);
        Auditoria::registrar('alterou', 'inventario_reivindicacoes', $id, 'aprovou ' . $r['nome'] . ' como responsável por ' . $r['item_nome']
            . ($anterior && (int) $anterior['usuario_id'] !== $uid ? ' (transferência de ' . $anterior['nome'] . ')' : ''), ['situacao' => 'pendente'], ['situacao' => 'aprovada', 'usuario_id' => $uid], $eid);
        $link = null;
        $enviado = false;
        if (empty($u['senha_hash'])) {
            require_once __DIR__ . '/Convite.php';
            $link = Convite::criar($uid, 'convite', $eu);
            $enviado = Convite::enviar((string) $r['email'], (string) $r['nome'], $link, 'convite');
        }
        return ['erro' => null, 'link' => $link, 'enviado' => $enviado, 'transferiu' => $anterior && (int) $anterior['usuario_id'] !== $uid ? $anterior['nome'] : null];
    }

    public static function rejeitar(int $id, string $motivo): bool
    {
        $r = self::pedido($id);
        $motivo = mb_substr(trim($motivo), 0, 500);
        if (!$r || $r['situacao'] !== 'pendente' || $motivo === '') {
            return false;
        }
        self::pdo()->prepare("UPDATE inventario_reivindicacoes SET situacao = 'rejeitada', motivo = ?, analisado_por = ?, analisado_em = NOW() WHERE id = ?")
            ->execute([$motivo, (int) Auth::usuarioOuNulo()['id'], $id]);
        Auditoria::registrar('alterou', 'inventario_reivindicacoes', $id, 'rejeitou o pedido de ' . $r['nome'] . ' para ' . $r['item_nome'],
            ['situacao' => 'pendente'], ['situacao' => 'rejeitada', 'motivo' => $motivo]);
        return true;
    }

    /** Tira o acesso do responsável a este lugar (venda do ponto, saiu da empresa). */
    public static function revogar(int $id, string $motivo): bool
    {
        $r = self::pedido($id);
        $motivo = mb_substr(trim($motivo), 0, 500);
        if (!$r || $r['situacao'] !== 'aprovada' || $motivo === '') {
            return false;
        }
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            self::revogarDentro($id, $motivo, (int) Auth::usuarioOuNulo()['id']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Inventario::registrarHistorico((int) $r['item_id'], 'trade', null, null, 'responsável revogado: ' . $r['nome']);
        Auditoria::registrar('alterou', 'inventario_reivindicacoes', $id, 'revogou ' . $r['nome'] . ' como responsável por ' . $r['item_nome'],
            ['situacao' => 'aprovada'], ['situacao' => 'revogada', 'motivo' => $motivo]);
        return true;
    }

    /**
     * Dentro da transação de quem chama: revoga o pedido, recusa a revisão
     * que estava esperando e, se a pessoa não responde por mais nenhum lugar
     * do destino e é só prestador aqui, desliga o acesso dela ao destino.
     */
    private static function revogarDentro(int $id, string $motivo, int $eu): void
    {
        $pdo = self::pdo();
        $q = $pdo->prepare('SELECT empresa_id, item_id, usuario_id FROM inventario_reivindicacoes WHERE id = ?');
        $q->execute([$id]);
        $r = $q->fetch();
        $pdo->prepare("UPDATE inventario_reivindicacoes SET situacao = 'revogada', motivo = ?, revogado_por = ?, revogado_em = NOW() WHERE id = ?")
            ->execute([$motivo, $eu, $id]);
        if (!$r || !$r['usuario_id']) {
            return;
        }
        $pdo->prepare("UPDATE inventario_revisoes SET situacao = 'rejeitada', nota = ?, moderado_por = ?, moderado_em = NOW()
                       WHERE item_id = ? AND usuario_id = ? AND situacao IN ('aguardando', 'devolvida')")
            ->execute(['O acesso a este lugar foi encerrado: ' . $motivo, $eu, (int) $r['item_id'], (int) $r['usuario_id']]);
        $q = $pdo->prepare("SELECT COUNT(*) FROM inventario_reivindicacoes WHERE usuario_id = ? AND empresa_id = ? AND situacao = 'aprovada'");
        $q->execute([(int) $r['usuario_id'], (int) $r['empresa_id']]);
        if (!(int) $q->fetchColumn()) {
            $pdo->prepare("UPDATE usuario_empresa ue JOIN papeis p ON p.id = ue.papel_id AND p.chave = 'prestador'
                           SET ue.ativo = 0, ue.atualizado_em = NOW()
                           WHERE ue.usuario_id = ? AND ue.empresa_id = ?
                             AND NOT EXISTS (SELECT 1 FROM usuario_empresa_papel uep JOIN papeis p2 ON p2.id = uep.papel_id
                                             WHERE uep.usuario_id = ue.usuario_id AND uep.empresa_id = ue.empresa_id AND p2.chave <> 'prestador')")
                ->execute([(int) $r['usuario_id'], (int) $r['empresa_id']]);
        }
    }

    /** Pedidos do destino, por situação. */
    public static function pedidos(array $situacoes): array
    {
        $m = implode(',', array_fill(0, count($situacoes), '?'));
        $q = self::pdo()->prepare("SELECT r.id, r.item_id, r.nome, r.email, r.whatsapp, r.vinculo, r.cnpj, r.cadastur, r.conferencia, r.situacao, r.motivo,
                                          r.criado_em, r.analisado_em, r.revogado_em, i.nome AS item_nome, f.codigo AS ficha_codigo,
                                          (SELECT COUNT(*) FROM inventario_reivindicacoes o WHERE o.item_id = r.item_id AND o.situacao = 'aprovada' AND o.id <> r.id) AS tem_responsavel
                                   FROM inventario_reivindicacoes r JOIN inventario_itens i ON i.id = r.item_id JOIN inventario_fichas f ON f.id = i.ficha_id
                                   WHERE r.empresa_id = ? AND r.situacao IN ({$m}) ORDER BY r.criado_em DESC LIMIT 500");
        $q->execute([Empresa::id(), ...$situacoes]);
        return array_map(function ($r) {
            $r['conferencia'] = json_decode((string) $r['conferencia'], true) ?: null;
            return $r;
        }, $q->fetchAll());
    }

    // ------------------------------------------------------- MEUS LUGARES

    /** Os lugares por que a pessoa responde no destino atual. */
    public static function meusLugares(int $uid): array
    {
        $q = self::pdo()->prepare("SELECT i.id, i.nome, i.situacao, f.codigo AS ficha_codigo, f.nome AS ficha_nome, t.nome AS tipo_nome, r.analisado_em,
                                          (SELECT m.arquivo FROM inventario_midias m WHERE m.item_id = i.id AND m.experiencia_id IS NULL ORDER BY m.capa DESC, m.ordem LIMIT 1) AS foto,
                                          (SELECT v.situacao FROM inventario_revisoes v WHERE v.item_id = i.id AND v.usuario_id = r.usuario_id ORDER BY v.id DESC LIMIT 1) AS revisao
                                   FROM inventario_reivindicacoes r JOIN inventario_itens i ON i.id = r.item_id
                                   JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
                                   WHERE r.usuario_id = ? AND r.empresa_id = ? AND r.situacao = 'aprovada' AND i.situacao <> 'arquivada'
                                   ORDER BY i.nome");
        $q->execute([$uid, Empresa::id()]);
        return $q->fetchAll();
    }

    /** A pessoa responde por este lugar? */
    public static function responde(int $uid, int $itemId): bool
    {
        $q = self::pdo()->prepare("SELECT 1 FROM inventario_reivindicacoes WHERE usuario_id = ? AND item_id = ? AND empresa_id = ? AND situacao = 'aprovada'");
        $q->execute([$uid, $itemId, Empresa::id()]);
        return (bool) $q->fetchColumn();
    }

    // ------------------------------------------------------------- REVISÕES

    /** Os campos que esta ficha deixa o responsável propor. */
    public static function camposDaFicha(array $item): array
    {
        $capacidades = array_values(Inventario::CAPACIDADES[(int) ($item['ficha_ordem'] ?? 0)] ?? []);
        if (in_array($item['eventcity'] ?? '', ['espaco_eventos', 'atrativo'], true)) {
            $capacidades[] = 'capacidade_publico';
        }
        return array_filter(self::CAMPOS, fn ($c) => !str_starts_with($c, 'capacidade_') || in_array($c, $capacidades, true), ARRAY_FILTER_USE_KEY);
    }

    /** A revisão ainda aberta (esperando ou devolvida) do lugar, ou null. */
    public static function revisaoAberta(int $itemId): ?array
    {
        $q = self::pdo()->prepare("SELECT * FROM inventario_revisoes WHERE item_id = ? AND empresa_id = ? AND situacao IN ('aguardando', 'devolvida') ORDER BY id DESC LIMIT 1");
        $q->execute([$itemId, Empresa::id()]);
        $r = $q->fetch();
        return $r ? self::decodificar($r) : null;
    }

    public static function ultimaRevisao(int $itemId, int $uid): ?array
    {
        $q = self::pdo()->prepare('SELECT * FROM inventario_revisoes WHERE item_id = ? AND usuario_id = ? AND empresa_id = ? ORDER BY id DESC LIMIT 1');
        $q->execute([$itemId, $uid, Empresa::id()]);
        $r = $q->fetch();
        return $r ? self::decodificar($r) : null;
    }

    public static function revisao(int $id): ?array
    {
        $q = self::pdo()->prepare('SELECT v.*, u.nome AS autor_nome, u.email AS autor_email, m.nome AS moderado_nome, i.nome AS item_nome, i.situacao AS item_situacao,
                                          f.codigo AS ficha_codigo, f.nome AS ficha_nome
                                   FROM inventario_revisoes v JOIN usuarios u ON u.id = v.usuario_id LEFT JOIN usuarios m ON m.id = v.moderado_por
                                   JOIN inventario_itens i ON i.id = v.item_id JOIN inventario_fichas f ON f.id = i.ficha_id
                                   WHERE v.id = ? AND v.empresa_id = ?');
        $q->execute([$id, Empresa::id()]);
        $r = $q->fetch();
        return $r ? self::decodificar($r) : null;
    }

    private static function decodificar(array $r): array
    {
        $r['alteracoes'] = json_decode((string) $r['alteracoes'], true) ?: [];
        $r['fotos'] = json_decode((string) $r['fotos'], true) ?: [];
        return $r;
    }

    public static function revisoes(array $situacoes): array
    {
        $m = implode(',', array_fill(0, count($situacoes), '?'));
        $q = self::pdo()->prepare("SELECT v.id, v.item_id, v.situacao, v.alteracoes, v.fotos, v.criado_em, v.atualizado_em, v.moderado_em,
                                          u.nome AS autor_nome, i.nome AS item_nome, f.codigo AS ficha_codigo
                                   FROM inventario_revisoes v JOIN usuarios u ON u.id = v.usuario_id JOIN inventario_itens i ON i.id = v.item_id
                                   JOIN inventario_fichas f ON f.id = i.ficha_id
                                   WHERE v.empresa_id = ? AND v.situacao IN ({$m}) ORDER BY COALESCE(v.atualizado_em, v.criado_em) DESC LIMIT 500");
        $q->execute([Empresa::id(), ...$situacoes]);
        return array_map([self::class, 'decodificar'], $q->fetchAll());
    }

    /** Valor atual de cada campo que o responsável pode propor (facilidades em lista de ids). */
    public static function valoresAtuais(array $item): array
    {
        $v = [];
        foreach (array_keys(self::camposDaFicha($item)) as $c) {
            $v[$c] = $c === 'facilidades' ? Inventario::facilidades((int) $item['id']) : ($item[$c] ?? null);
        }
        return $v;
    }

    /** Normaliza para comparar: JSON sem espaço e em ordem, número como texto, vazio como null. */
    private static function normal(string $campo, mixed $v): mixed
    {
        if ($campo === 'facilidades') {
            $ids = array_map('intval', (array) $v);
            sort($ids);
            return $ids;
        }
        if ($v === null || $v === '') {
            return null;
        }
        if (in_array($campo, ['horario', 'formas_pagamento', 'idiomas'], true)) {
            $d = self::enxuto(is_string($v) ? json_decode($v, true) : $v);
            return $d === null ? null : json_encode($d, JSON_UNESCAPED_UNICODE);
        }
        return is_scalar($v) ? (string) $v : $v;
    }

    /** Sem o vazio (null, falso, texto vazio, lista vazia) e em ordem, para o mesmo horário gravado por caminhos diferentes ser igual. */
    private static function enxuto(mixed $d): mixed
    {
        if (!is_array($d)) {
            return $d === null || $d === false || $d === '' ? null : $d;
        }
        $lista = array_is_list($d);
        $d = array_filter(array_map([self::class, 'enxuto'], $d), fn ($x) => $x !== null);
        if (!$d) {
            return null;
        }
        if ($lista) {
            $d = array_values($d);
            if (!array_filter($d, 'is_array')) {
                sort($d);
            }
        } else {
            ksort($d);
        }
        return $d;
    }

    /**
     * Grava a proposta do responsável: só o que mudou em relação à ficha,
     * mais as fotos novas. Atualiza a revisão aberta (a devolvida volta a
     * esperar) ou cria uma. Sem mudança nem foto, apaga a proposta aberta.
     * Devolve quantos campos mudaram.
     *
     * @param array<string,mixed> $novos coluna => valor (como o FichaFormulario lê)
     * @param list<array{arquivo:string,latitude:?float,longitude:?float}> $fotosNovas
     */
    public static function propor(array $item, int $uid, array $novos, array $fotosNovas, array $tirarFotos = []): int
    {
        $eid = Empresa::id();
        $atuais = self::valoresAtuais($item);
        $alteracoes = [];
        foreach ($atuais as $c => $antes) {
            if (!array_key_exists($c, $novos)) {
                continue;
            }
            if (self::normal($c, $antes) !== self::normal($c, $novos[$c])) {
                $alteracoes[$c] = ['antes' => $antes, 'depois' => $novos[$c]];
            }
        }
        $aberta = self::revisaoAberta((int) $item['id']);
        if ($aberta && (int) $aberta['usuario_id'] !== $uid) {
            $aberta = null;
        }
        $fotos = $aberta['fotos'] ?? [];
        foreach ($tirarFotos as $arq) {
            foreach ($fotos as $k => $f) {
                if ($f['arquivo'] === $arq) {
                    @unlink(self::caminho($eid, $arq));
                    unset($fotos[$k]);
                }
            }
        }
        $fotos = array_values(array_merge($fotos, $fotosNovas));
        $pdo = self::pdo();
        if (!$alteracoes && !$fotos) {
            if ($aberta) {
                $pdo->prepare('DELETE FROM inventario_revisoes WHERE id = ?')->execute([(int) $aberta['id']]);
            }
            return 0;
        }
        $json = json_encode($alteracoes, JSON_UNESCAPED_UNICODE);
        $jsonFotos = $fotos ? json_encode($fotos) : null;
        if ($aberta) {
            $pdo->prepare("UPDATE inventario_revisoes SET alteracoes = ?, fotos = ?, situacao = 'aguardando', atualizado_em = NOW() WHERE id = ?")
                ->execute([$json, $jsonFotos, (int) $aberta['id']]);
            $id = (int) $aberta['id'];
        } else {
            $pdo->prepare("INSERT INTO inventario_revisoes (empresa_id, item_id, usuario_id, origem, alteracoes, fotos, situacao, criado_em) VALUES (?, ?, ?, 'trade', ?, ?, 'aguardando', NOW())")
                ->execute([$eid, (int) $item['id'], $uid, $json, $jsonFotos]);
            $id = (int) $pdo->lastInsertId();
        }
        Auditoria::registrar($aberta ? 'alterou' : 'criou', 'inventario_revisoes', $id, 'propôs mudanças em ' . $item['nome'] . ': '
            . implode(', ', array_map(fn ($c) => self::CAMPOS[$c] ?? $c, array_keys($alteracoes))) . ($fotos ? ($alteracoes ? ', ' : '') . plural(count($fotos), 'foto nova', 'fotos novas') : ''));
        return count($alteracoes) + count($fotos);
    }

    /** Guarda uma foto que o responsável mandou, até a revisão. Devolve o registro ou a mensagem de erro. */
    public static function guardarFotoProposta(array $f): array|string
    {
        require_once __DIR__ . '/Imagem.php';
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            return 'A foto não chegou inteira.';
        }
        if ((int) $f['size'] > 20 * 1048576) {
            return 'A foto passa de 20 MB.';
        }
        $dados = (string) file_get_contents((string) $f['tmp_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($dados);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return 'Use fotos em JPG, PNG ou WebP.';
        }
        $gps = $mime === 'image/jpeg' ? Imagem::gpsDoExif($dados) : null;
        $img = Imagem::foto($dados);
        if ($img === null) {
            return 'O arquivo não é uma imagem válida.';
        }
        $nome = 'foto-' . bin2hex(random_bytes(12)) . '.jpg';
        if (@file_put_contents(self::caminho(Empresa::id(), $nome), $img[0]) === false) {
            return 'Não consegui guardar a foto agora.';
        }
        return ['arquivo' => $nome, 'latitude' => $gps[0] ?? null, 'longitude' => $gps[1] ?? null];
    }

    /**
     * Aprova a revisão: aplica o depois na ficha, guarda as fotos, renova a
     * última conferência (a desatualizada volta a homologada) e avisa o
     * EventCity. Devolve os campos em que a ficha tinha mudado desde a
     * proposta (a equipe é avisada; vale o que o responsável mandou).
     */
    public static function aprovarRevisao(int $id): ?array
    {
        $v = self::revisao($id);
        if (!$v || $v['situacao'] !== 'aguardando') {
            return null;
        }
        $item = Inventario::buscar((int) $v['item_id']);
        if (!$item) {
            return null;
        }
        $eid = Empresa::id();
        $eu = (int) Auth::usuarioOuNulo()['id'];
        $atuais = self::valoresAtuais($item);
        $mudaram = [];
        $colunas = [];
        $facilidades = null;
        foreach ($v['alteracoes'] as $c => $par) {
            if (!array_key_exists($c, $atuais)) {
                continue;
            }
            if (self::normal($c, $atuais[$c]) !== self::normal($c, $par['antes'])) {
                $mudaram[] = self::CAMPOS[$c] ?? $c;
            }
            if ($c === 'facilidades') {
                $facilidades = (array) $par['depois'];
            } else {
                $colunas[$c] = $par['depois'];
            }
        }
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $sets = array_map(fn ($c) => "{$c} = ?", array_keys($colunas));
            $situacao = $item['situacao'] === 'desatualizada' ? ", situacao = 'homologada'" : '';
            $pdo->prepare('UPDATE inventario_itens SET ' . implode('', array_map(fn ($s) => $s . ', ', $sets)) . "ultima_auditoria_em = NOW(), atualizado_em = NOW(), atualizado_por = ?{$situacao}
                           WHERE id = ? AND empresa_id = ?")
                ->execute([...array_values($colunas), $eu, (int) $item['id'], $eid]);
            // A capacidade da hospedagem e da alimentação mora também no
            // campo da ficha (de onde a coluna sai ao salvar no painel).
            foreach (Inventario::CAPACIDADES[(int) $item['ficha_ordem']] ?? [] as $chave => $coluna) {
                if (!array_key_exists($coluna, $colunas)) {
                    continue;
                }
                $q = $pdo->prepare('SELECT id FROM inventario_atributos WHERE ficha_id = ? AND chave = ?');
                $q->execute([(int) $item['ficha_id'], $chave]);
                if ($aid = (int) $q->fetchColumn()) {
                    $pdo->prepare('DELETE FROM inventario_item_atributos WHERE item_id = ? AND atributo_id = ? AND empresa_id = ?')->execute([(int) $item['id'], $aid, $eid]);
                    if ($colunas[$coluna] !== null) {
                        $pdo->prepare('INSERT INTO inventario_item_atributos (item_id, atributo_id, empresa_id, valor_numero) VALUES (?, ?, ?, ?)')
                            ->execute([(int) $item['id'], $aid, $eid, (int) $colunas[$coluna]]);
                    }
                }
            }
            if ($facilidades !== null) {
                Inventario::salvarFacilidades((int) $item['id'], $facilidades);
            }
            $pdo->prepare("UPDATE inventario_revisoes SET situacao = 'aprovada', nota = NULL, moderado_por = ?, moderado_em = NOW() WHERE id = ?")->execute([$eu, $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $falhas = [];
        foreach ($v['fotos'] as $f) {
            $arq = self::caminho($eid, (string) $f['arquivo']);
            if (is_file($arq)) {
                $gps = isset($f['latitude'], $f['longitude']) ? [(float) $f['latitude'], (float) $f['longitude']] : null;
                $erro = Inventario::guardarFotoDeDados((int) $item['id'], (string) file_get_contents($arq), (string) $v['autor_nome'], null, $gps);
                $erro === null ? @unlink($arq) : $falhas[] = $erro;
            }
        }
        $resumo = implode(', ', array_map(fn ($c) => self::CAMPOS[$c] ?? $c, array_keys($v['alteracoes'])));
        Inventario::registrarHistorico((int) $item['id'], 'trade', null, null,
            mb_substr('revisão do responsável aprovada' . ($resumo !== '' ? ': ' . $resumo : '') . ($v['fotos'] ? ' (' . plural(count($v['fotos']), 'foto', 'fotos') . ')' : ''), 0, 500));
        Auditoria::registrar('alterou', 'inventario_itens', (int) $item['id'], 'aprovou a revisão de ' . $v['autor_nome'] . ' em ' . $item['nome'],
            array_map(fn ($p) => $p['antes'], $v['alteracoes']), array_map(fn ($p) => $p['depois'], $v['alteracoes']), $eid);
        if (in_array($item['situacao'], ['homologada', 'desatualizada'], true)) {
            require_once __DIR__ . '/EventCity.php';
            EventCity::avisarItem($eid, (int) $item['id']);
        }
        return ['mudaram' => $mudaram, 'falhas' => $falhas];
    }

    /** Devolve (com o que ajustar) ou rejeita a revisão. */
    public static function moderar(int $id, string $situacao, string $nota): bool
    {
        $v = self::revisao($id);
        $nota = mb_substr(trim($nota), 0, 1000);
        if (!$v || $v['situacao'] !== 'aguardando' || $nota === '' || !in_array($situacao, ['devolvida', 'rejeitada'], true)) {
            return false;
        }
        self::pdo()->prepare('UPDATE inventario_revisoes SET situacao = ?, nota = ?, moderado_por = ?, moderado_em = NOW() WHERE id = ?')
            ->execute([$situacao, $nota, (int) Auth::usuarioOuNulo()['id'], $id]);
        if ($situacao === 'rejeitada') {
            foreach ($v['fotos'] as $f) {
                @unlink(self::caminho(Empresa::id(), (string) $f['arquivo']));
            }
        }
        Auditoria::registrar('alterou', 'inventario_revisoes', $id, ($situacao === 'devolvida' ? 'devolveu' : 'rejeitou') . ' a revisão de ' . $v['autor_nome'] . ' em ' . $v['item_nome'],
            ['situacao' => 'aguardando'], ['situacao' => $situacao, 'nota' => $nota]);
        return true;
    }

    // ------------------------------------------------------------- LEITURA

    /** O valor de um campo para ler na tela (antes e depois). */
    public static function legivel(string $campo, mixed $v): string
    {
        if ($campo === 'facilidades') {
            $ids = array_map('intval', (array) $v);
            if (!$ids) {
                return '';
            }
            $m = implode(',', array_fill(0, count($ids), '?'));
            $q = self::pdo()->prepare("SELECT nome FROM tribo_facilidades WHERE id IN ({$m}) ORDER BY nome");
            $q->execute($ids);
            return implode(', ', $q->fetchAll(PDO::FETCH_COLUMN));
        }
        if ($v === null || $v === '') {
            return '';
        }
        if (in_array($campo, ['telefone', 'whatsapp'], true) && ctype_digit((string) $v)) {
            return formatarTelefone((string) $v);
        }
        if ($campo === 'instagram') {
            return '@' . $v;
        }
        if (in_array($campo, ['formas_pagamento', 'idiomas'], true)) {
            return implode(', ', (array) (json_decode((string) $v, true) ?: []));
        }
        if ($campo === 'horario') {
            return self::horarioEmTexto(json_decode((string) $v, true) ?: []);
        }
        if (str_starts_with($campo, 'capacidade_')) {
            return milhar((int) $v);
        }
        return (string) $v;
    }

    /** "Seg 08:00 às 18:00; Sáb 08:00 às 12:00; feriados: fecha". */
    public static function horarioEmTexto(array $h): string
    {
        if (!$h) {
            return '';
        }
        if (!empty($h['24h'])) {
            $partes = ['24 horas'];
        } else {
            $partes = [];
            foreach (Inventario::DIAS as $dia => $rotulo) {
                $faixas = array_filter((array) ($h[$dia] ?? []), fn ($f) => ($f[0] ?? '') !== '' && ($f[1] ?? '') !== '');
                if ($faixas) {
                    $partes[] = mb_substr($rotulo, 0, 3) . ' ' . implode(' e ', array_map(fn ($f) => $f[0] . ' às ' . $f[1], $faixas));
                }
            }
            if (!$partes) {
                $partes[] = 'sem dias abertos';
            }
        }
        if (!empty($h['periodos'])) {
            $partes[] = 'atende em ' . mb_strtolower(implode(', ', array_map(fn ($p) => Inventario::PERIODOS[$p] ?? $p, (array) $h['periodos'])));
        }
        if (isset($h['feriados_abre'])) {
            $partes[] = 'feriados: ' . ($h['feriados_abre'] ? 'abre' : 'fecha');
        }
        foreach (['feriados' => '', 'temporada' => ''] as $k => $_) {
            if (!empty($h[$k])) {
                $partes[] = (string) $h[$k];
            }
        }
        return implode('; ', $partes);
    }

    /** Pedidos esperando e revisões esperando, para o número do menu. */
    public static function esperando(int $eid): int
    {
        $q = self::pdo()->prepare("SELECT (SELECT COUNT(*) FROM inventario_reivindicacoes WHERE empresa_id = ? AND situacao = 'pendente')
                                        + (SELECT COUNT(*) FROM inventario_revisoes WHERE empresa_id = ? AND situacao = 'aguardando')");
        $q->execute([$eid, $eid]);
        return (int) $q->fetchColumn();
    }
}
