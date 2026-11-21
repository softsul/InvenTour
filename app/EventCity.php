<?php
/**
 * Ponte com o EventCity (API v1 de docs/INTEGRACAO-INVENTOUR.md).
 * Softsul Software
 *
 * Assinatura: cabeçalhos X-Destino (IBGE), X-Momento (Unix) e X-Assinatura
 * = hex(hmac_sha256(segredo, momento \n método \n caminho com query \n corpo)).
 * O segredo é por destino, cifrado em ajustes (grupo eventcity), e só a fila
 * e o painel o leem: o portal não tem acesso a ajustes.
 *
 * - Agenda: a fila busca a agenda dos próximos 60 dias a cada 5 minutos e
 *   grava em storage/cache/eventcity/<destino>.json. O portal só lê esse
 *   arquivo: a página nunca espera o EventCity, e se ele cair a Agenda e a
 *   aba de eventos somem (o cache vale 2 horas).
 * - Avisos: item homologado ou alterado depois de homologado vai para a fila
 *   (job eventcity_aviso), que reenvia em caso de falha (a fila tenta de novo).
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Ajustes.php';

final class EventCity
{
    private const VALIDADE_CACHE = 7200;

    public static function config(int $eid): array
    {
        $g = Ajustes::grupo('eventcity', $eid);
        return ['url' => rtrim((string) ($g['url'] ?? '') ?: 'https://app.eventcity.com.br', '/'), 'segredo' => (string) ($g['segredo'] ?? ''),
                'ligado' => ($g['ligado'] ?? '0') === '1'];
    }

    private static function arquivoCache(int $eid): string
    {
        $cfg = require __DIR__ . '/config.php';
        return rtrim((string) $cfg['caminhos']['cache'], '/') . '/eventcity/' . $eid . '.json';
    }

    /** A agenda em cache (o portal lê só daqui), ou null. */
    public static function agendaEmCache(int $eid): ?array
    {
        $f = self::arquivoCache($eid);
        if (!is_file($f) || filemtime($f) < time() - self::VALIDADE_CACHE) {
            return null;
        }
        $j = json_decode((string) @file_get_contents($f), true);
        return is_array($j) && isset($j['eventos']) ? $j['eventos'] : null;
    }

    public static function temAgenda(int $eid): bool
    {
        return (bool) self::agendaEmCache($eid);
    }

    /** Os próximos eventos de um lugar do inventário, pela agenda em cache. */
    public static function eventosDoLocal(int $eid, int $itemId): array
    {
        return array_values(array_filter(self::agendaEmCache($eid) ?? [], fn ($e) => (int) ($e['local_inventour_id'] ?? 0) === $itemId));
    }

    /** Pedido assinado. Devolve [código HTTP, corpo decodificado ou null]. */
    public static function pedir(int $eid, string $metodo, string $caminho, ?array $corpo = null, float $limite = 5.0): array
    {
        $c = self::config($eid);
        if (!$c['ligado'] || $c['segredo'] === '') {
            return [0, null];
        }
        $q = Database::conexao()->prepare('SELECT municipio_id FROM empresas WHERE id = ?');
        $q->execute([$eid]);
        $ibge = (string) $q->fetchColumn();
        $json = $corpo === null ? '' : (string) json_encode($corpo, JSON_UNESCAPED_UNICODE);
        $momento = (string) time();
        $assinatura = hash_hmac('sha256', $momento . "\n" . strtoupper($metodo) . "\n" . $caminho . "\n" . $json, $c['segredo']);
        $ch = curl_init($c['url'] . $caminho);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => strtoupper($metodo), CURLOPT_TIMEOUT => $limite,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_USERAGENT => 'InvenTour (app.inventour.com.br)',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Destino: ' . $ibge, 'X-Momento: ' . $momento, 'X-Assinatura: ' . $assinatura]]);
        if ($json !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        $resposta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$codigo, json_decode((string) $resposta, true)];
    }

    /** Atualiza o cache da agenda (job eventcity_agenda). Devolve quantos eventos vieram, ou null se falhou. */
    public static function atualizarAgenda(int $eid): ?int
    {
        $q = Database::conexao()->prepare('SELECT municipio_id FROM empresas WHERE id = ?');
        $q->execute([$eid]);
        $ibge = (int) $q->fetchColumn();
        [$codigo, $j] = self::pedir($eid, 'GET', '/api/v1/inventour/agenda?ibge=' . $ibge . '&de=' . date('Y-m-d') . '&ate=' . date('Y-m-d', strtotime('+60 days')));
        if ($codigo !== 200 || !is_array($j)) {
            return null;
        }
        $eventos = array_values(array_filter((array) ($j['eventos'] ?? $j), 'is_array'));
        // Evento recorrente traz a data de início original: vale a próxima data (proximo_dia).
        usort($eventos, fn ($a, $b) => strcmp((string) ($a['proximo_dia'] ?? $a['data_inicio'] ?? ''), (string) ($b['proximo_dia'] ?? $b['data_inicio'] ?? '')));
        $f = self::arquivoCache($eid);
        @mkdir(dirname($f), 02770, true);
        $tmp = $f . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, json_encode(['em' => date('c'), 'eventos' => $eventos], JSON_UNESCAPED_UNICODE));
        rename($tmp, $f);
        return count($eventos);
    }

    /**
     * Envelope do aviso de um item (tipo item), nos campos combinados em
     * 21/11/2026 (docs/INTEGRACAO-INVENTOUR.md, linha "API v1 no ar"): id e
     * nome obrigatórios; categoria é o código da ficha (B1, C3); url é a
     * ficha no portal; arquivado vai como situacao "arquivado". Campo nulo
     * não apaga nada do lado do EventCity.
     */
    public static function avisoDoItem(int $itemId): ?array
    {
        $q = Database::conexao()->prepare('SELECT i.*, f.codigo AS ficha_codigo, t.nome AS tipo_nome, e.slug, e.dominio_proprio, e.municipio_id
                                           FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
                                           JOIN empresas e ON e.id = i.empresa_id WHERE i.id = ?');
        $q->execute([$itemId]);
        $i = $q->fetch();
        if (!$i) {
            return null;
        }
        require_once __DIR__ . '/Caderno.php';
        require_once __DIR__ . '/Portal.php';
        $int = fn ($v) => $v !== null ? (int) $v : null;
        $dados = ['id' => (int) $i['id'], 'nome' => (string) $i['nome'], 'categoria' => (string) $i['ficha_codigo'], 'tipo' => $i['tipo_nome'],
                  'cnpj' => $i['cnpj'], 'cadastur' => $i['cadastur'], 'whatsapp' => $i['whatsapp'], 'cep' => $i['cep'],
                  'logradouro' => $i['logradouro'], 'numero' => $i['numero'], 'bairro' => $i['bairro'],
                  'municipio_ibge' => $int($i['municipio_id']),
                  'latitude' => $i['latitude'] !== null ? (float) $i['latitude'] : null, 'longitude' => $i['longitude'] !== null ? (float) $i['longitude'] : null,
                  'capacidade' => $int($i['capacidade_publico']), 'leitos' => $int($i['capacidade_leitos']), 'uhs' => $int($i['capacidade_uh']),
                  'url' => Caderno::portal($i) . '/lugar/' . (int) $i['id'] . '-' . Portal::slug((string) $i['nome'])];
        if ($i['situacao'] === 'arquivada') {
            $dados['situacao'] = 'arquivado';
        }
        return ['aviso_id' => self::uuid(), 'tipo' => 'item', 'versao' => (string) ($i['atualizado_em'] ?: $i['criado_em']),
                'dados' => array_filter($dados, fn ($v) => $v !== null && $v !== '')];
    }

    /** Envelope dos totais do destino (tipo totais): leitos e UHs da hospedagem e capacidade dos espaços para eventos, homologados. */
    public static function avisoDosTotais(int $eid): array
    {
        $q = Database::conexao()->prepare("SELECT COALESCE(SUM(CASE WHEN f.ordem IN (20, 21) THEN i.capacidade_leitos END), 0),
                                                  COALESCE(SUM(CASE WHEN f.ordem IN (20, 21) THEN i.capacidade_uh END), 0),
                                                  COALESCE(SUM(CASE WHEN f.ordem = 25 THEN i.capacidade_publico END), 0)
                                           FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                                           WHERE i.empresa_id = ? AND i.situacao IN ('homologada', 'desatualizada')");
        $q->execute([$eid]);
        [$leitos, $uhs, $eventos] = array_map('intval', $q->fetch(PDO::FETCH_NUM));
        return ['aviso_id' => self::uuid(), 'tipo' => 'totais', 'versao' => date('Y-m-d H:i:s'),
                'dados' => ['leitos' => $leitos, 'uhs' => $uhs, 'capacidade_eventos' => $eventos]];
    }

    /** Põe o aviso de um item na fila, se o destino tem a ponte ligada. */
    public static function avisarItem(int $eid, int $itemId): void
    {
        if (!self::config($eid)['ligado']) {
            return;
        }
        require_once __DIR__ . '/Fila.php';
        Fila::enfileirar('eventcity_aviso', ['empresa_id' => $eid, 'item_id' => $itemId], null, $eid);
        // Os totais do destino mudam junto; um aviso de totais pendente por destino basta.
        $q = Database::conexao()->prepare("SELECT 1 FROM jobs WHERE tipo = 'eventcity_aviso' AND status = 'pendente' AND empresa_id = ? AND payload LIKE '%\"totais\"%'");
        $q->execute([$eid]);
        if (!$q->fetchColumn()) {
            Fila::enfileirar('eventcity_aviso', ['empresa_id' => $eid, 'totais' => true], date('Y-m-d H:i:s', strtotime('+1 minute')), $eid);
        }
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
