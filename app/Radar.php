<?php
/**
 * Radar de obsolescência (etapa 7, PLANEJAMENTO 2.9).
 * Softsul Software
 *
 * Todo dia (job radar_obsolescencia, 2h): o lugar homologado sem
 * conferência há mais de PRAZO_DIAS, ou com o Cadastur vencido, vira
 * desatualizado. Continua no portal, com o selo "Dados em atualização", e
 * perde prioridade nas listas; nada some.
 *
 * Em destino ativo, cada lugar desatualizado com e-mail ou WhatsApp na ficha
 * recebe um link de uso único (public/confirmar.php), que vale 15 dias:
 *  - "Está tudo igual": a conferência renova e o lugar volta a homologado,
 *    sem trabalho da equipe;
 *  - "Preciso atualizar": quem já responde pelo lugar entra em Meus
 *    lugares; quem ainda não responde pede o acesso (/reivindicar), e a
 *    mudança passa pela revisão da equipe, como todo o trade.
 * Um aviso por lugar a cada REENVIO_DIAS, no máximo: o radar não vira spam.
 * Só o resumo do token vai ao banco (radar_avisos).
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/svg.php';
require_once __DIR__ . '/funcoes.php';

final class Radar
{
    public const PRAZO_DIAS = 180;
    public const VALIDADE_DIAS = 15;
    public const REENVIO_DIAS = 30;

    private static function pdo(): PDO
    {
        return Database::conexao();
    }

    /**
     * Marca os vencidos de todos os destinos e manda os avisos dos ativos.
     *
     * @return list<string> o log do job
     */
    public static function rodar(): array
    {
        $pdo = self::pdo();
        $log = [];
        $vencidos = $pdo->query("SELECT i.id, i.empresa_id, i.nome, i.cadastur_validade
                                 FROM inventario_itens i JOIN empresas e ON e.id = i.empresa_id AND e.ativo = 1
                                 WHERE i.situacao = 'homologada'
                                   AND (COALESCE(i.ultima_auditoria_em, i.homologado_em, i.criado_em) < NOW() - INTERVAL " . self::PRAZO_DIAS . " DAY
                                        OR (i.cadastur_validade IS NOT NULL AND i.cadastur_validade < CURDATE()))")->fetchAll();
        $muda = $pdo->prepare("UPDATE inventario_itens SET situacao = 'desatualizada' WHERE id = ? AND situacao = 'homologada'");
        $hist = $pdo->prepare("INSERT INTO inventario_item_historico (item_id, empresa_id, usuario_id, acao, de, para, nota, criado_em)
                               VALUES (?, ?, NULL, 'mudou_situacao', 'homologada', 'desatualizada', ?, NOW())");
        foreach ($vencidos as $i) {
            $muda->execute([(int) $i['id']]);
            if ($muda->rowCount()) {
                $motivo = $i['cadastur_validade'] && $i['cadastur_validade'] < date('Y-m-d') ? 'radar: Cadastur vencido' : 'radar: sem conferência há mais de ' . self::PRAZO_DIAS . ' dias';
                $hist->execute([(int) $i['id'], (int) $i['empresa_id'], $motivo]);
            }
        }
        if ($vencidos) {
            $log[] = plural(count($vencidos), 'lugar desatualizado', 'lugares desatualizados');
            Auditoria::registrar('alterou', 'inventario_itens', null, 'radar de obsolescência: ' . plural(count($vencidos), 'lugar desatualizado', 'lugares desatualizados'), null, null, 0);
        }
        // Avisos: só destino ativo, só quem tem contato, um a cada REENVIO_DIAS.
        $paraAvisar = $pdo->query("SELECT i.id, i.empresa_id, i.nome, i.email, i.whatsapp, e.nome_fantasia AS destino
                                   FROM inventario_itens i JOIN empresas e ON e.id = i.empresa_id AND e.ativo = 1 AND e.modo_operacao = 'ativo'
                                   WHERE i.situacao = 'desatualizada' AND ((i.email IS NOT NULL AND i.email <> '') OR (i.whatsapp IS NOT NULL AND i.whatsapp <> ''))
                                     AND NOT EXISTS (SELECT 1 FROM radar_avisos a WHERE a.item_id = i.id AND a.criado_em > NOW() - INTERVAL " . self::REENVIO_DIAS . " DAY)
                                   ORDER BY i.empresa_id, i.id LIMIT 200")->fetchAll();
        $enviados = 0;
        foreach ($paraAvisar as $i) {
            if (self::avisar($i)) {
                $enviados++;
            }
        }
        if ($paraAvisar) {
            $log[] = plural($enviados, 'aviso enviado', 'avisos enviados') . ' de ' . count($paraAvisar);
        }
        return $log;
    }

    /** Cria o link de uso único de um lugar e manda pelo e-mail e pelo WhatsApp da ficha. */
    private static function avisar(array $i): bool
    {
        $token = bin2hex(random_bytes(24));
        $cfg = require __DIR__ . '/config.php';
        $link = rtrim((string) $cfg['app']['url'], '/') . '/confirmar?t=' . $token;
        $canais = [];
        $texto = "Olá! O guia oficial de turismo de {$i['destino']} mostra o {$i['nome']}. "
               . 'Para manter o seu negócio em destaque, confirme se horário, WhatsApp e capacidade continuam os mesmos. Leva um clique.';
        if (!empty($i['email']) && filter_var($i['email'], FILTER_VALIDATE_EMAIL)) {
            require_once __DIR__ . '/Email.php';
            if (Email::configurado()) {
                try {
                    Email::enviar((string) $i['email'], 'Os dados do ' . $i['nome'] . ' no guia de turismo de ' . $i['destino'] . ' continuam certos?',
                        Email::molde('Os seus dados continuam certos?', Email::paragrafos($texto . "\n\nO link vale por " . self::VALIDADE_DIAS . ' dias e serve uma vez só.'),
                            ['texto' => 'Conferir os dados', 'url' => $link]));
                    $canais[] = 'email';
                } catch (Throwable $e) {
                    error_log('radar e-mail: ' . $e->getMessage());
                }
            }
        }
        if (!empty($i['whatsapp'])) {
            require_once __DIR__ . '/Waha.php';
            if (Waha::envioLigado()) {
                $r = Waha::enviarTexto((string) $i['whatsapp'], $texto . "\n\n" . $link, (string) $i['nome']);
                if (!empty($r['ok'])) {
                    $canais[] = 'whatsapp';
                }
            }
        }
        if (!$canais) {
            return false;
        }
        self::pdo()->prepare('INSERT INTO radar_avisos (empresa_id, item_id, token_hash, canais, enviado_para, expira_em, criado_em)
                              VALUES (?, ?, ?, ?, ?, NOW() + INTERVAL ' . self::VALIDADE_DIAS . ' DAY, NOW())')
            ->execute([(int) $i['empresa_id'], (int) $i['id'], hash('sha256', $token), implode(',', $canais),
                       in_array('email', $canais, true) ? (string) $i['email'] : (string) $i['whatsapp']]);
        return true;
    }

    /** O aviso ainda válido do token, com o lugar, ou null. */
    public static function achar(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $q = self::pdo()->prepare("SELECT a.*, i.nome, i.situacao, i.telefone, i.whatsapp, i.email, i.site, i.instagram, i.horario,
                                          i.capacidade_uh, i.capacidade_leitos, i.capacidade_assentos, i.capacidade_publico,
                                          e.nome_fantasia AS destino, e.slug, e.dominio_proprio
                                   FROM radar_avisos a JOIN inventario_itens i ON i.id = a.item_id JOIN empresas e ON e.id = a.empresa_id
                                   WHERE a.token_hash = ?");
        $q->execute([hash('sha256', $token)]);
        return $q->fetch() ?: null;
    }

    /** "Está tudo igual": renova a conferência. Devolve false se o link já foi usado ou venceu. */
    public static function confirmarIgual(array $a): bool
    {
        if ($a['respondido_em'] || $a['expira_em'] < date('Y-m-d H:i:s')) {
            return false;
        }
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare("UPDATE radar_avisos SET respondido_em = NOW(), resposta = 'igual' WHERE id = ? AND respondido_em IS NULL");
            $q->execute([(int) $a['id']]);
            if (!$q->rowCount()) {
                $pdo->rollBack();
                return false;
            }
            $pdo->prepare("UPDATE inventario_itens SET ultima_auditoria_em = NOW(),
                                  situacao = IF(situacao = 'desatualizada' AND (cadastur_validade IS NULL OR cadastur_validade >= CURDATE()), 'homologada', situacao)
                           WHERE id = ?")->execute([(int) $a['item_id']]);
            $pdo->prepare("INSERT INTO inventario_item_historico (item_id, empresa_id, usuario_id, acao, de, para, nota, criado_em)
                           VALUES (?, ?, NULL, 'radar', NULL, NULL, 'o estabelecimento confirmou que os dados continuam iguais', NOW())")
                ->execute([(int) $a['item_id'], (int) $a['empresa_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Auditoria::registrar('alterou', 'inventario_itens', (int) $a['item_id'], 'radar: ' . $a['nome'] . ' confirmou que está tudo igual', null, null, (int) $a['empresa_id']);
        return true;
    }

    /** "Preciso atualizar": anota a resposta. Devolve o destino do clique (Meus lugares ou o pedido de acesso). */
    public static function pedirAtualizacao(array $a): string
    {
        self::pdo()->prepare("UPDATE radar_avisos SET respondido_em = COALESCE(respondido_em, NOW()), resposta = COALESCE(resposta, 'atualizar') WHERE id = ?")
            ->execute([(int) $a['id']]);
        $q = self::pdo()->prepare("SELECT 1 FROM inventario_reivindicacoes WHERE item_id = ? AND situacao = 'aprovada'");
        $q->execute([(int) $a['item_id']]);
        return $q->fetchColumn() ? '/meu-lugar?id=' . (int) $a['item_id'] : '/reivindicar?i=' . (int) $a['item_id'];
    }

    /** Saúde do banco de um destino: lugares no portal em dia, desatualizados e as respostas dos avisos em 90 dias. */
    public static function saude(int $eid): array
    {
        $q = self::pdo()->prepare("SELECT COALESCE(SUM(situacao = 'homologada'), 0) em_dia, COALESCE(SUM(situacao = 'desatualizada'), 0) desatualizados
                                   FROM inventario_itens WHERE empresa_id = ? AND situacao IN ('homologada', 'desatualizada')");
        $q->execute([$eid]);
        $s = array_map('intval', $q->fetch(PDO::FETCH_ASSOC) ?: ['em_dia' => 0, 'desatualizados' => 0]);
        $q = self::pdo()->prepare("SELECT COUNT(*) enviados, COALESCE(SUM(resposta = 'igual'), 0) iguais, COALESCE(SUM(resposta = 'atualizar'), 0) atualizar
                                   FROM radar_avisos WHERE empresa_id = ? AND criado_em > NOW() - INTERVAL 90 DAY");
        $q->execute([$eid]);
        $a = array_map('intval', $q->fetch(PDO::FETCH_ASSOC) ?: []);
        $total = $s['em_dia'] + $s['desatualizados'];
        return $s + $a + ['saude' => $total ? (int) round($s['em_dia'] / $total * 100) : null];
    }
}
