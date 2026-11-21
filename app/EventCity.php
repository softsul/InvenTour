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
        $f = self::arquivoCache($eid);
        @mkdir(dirname($f), 02770, true);
        $tmp = $f . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, json_encode(['em' => date('c'), 'eventos' => $eventos], JSON_UNESCAPED_UNICODE));
        rename($tmp, $f);
        return count($eventos);
    }

    /** Envelope do aviso de um item (tipo item), no formato combinado. */
    public static function avisoDoItem(int $itemId): ?array
    {
        $q = Database::conexao()->prepare('SELECT i.*, f.codigo AS ficha_codigo, f.nome AS ficha_nome, t.nome AS tipo_nome
                                           FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
                                           WHERE i.id = ?');
        $q->execute([$itemId]);
        $i = $q->fetch();
        if (!$i) {
            return null;
        }
        return ['aviso_id' => self::uuid(), 'tipo' => 'item', 'versao' => (string) $i['atualizado_em'],
                'dados' => ['id' => (int) $i['id'], 'situacao' => $i['situacao'], 'nome' => $i['nome'], 'ficha' => $i['ficha_codigo'] . ' ' . $i['ficha_nome'],
                            'tipo' => $i['tipo_nome'], 'cnpj' => $i['cnpj'], 'cadastur' => $i['cadastur'], 'whatsapp' => $i['whatsapp'],
                            'logradouro' => $i['logradouro'], 'numero' => $i['numero'], 'bairro' => $i['bairro'], 'cep' => $i['cep'],
                            'latitude' => $i['latitude'] !== null ? (float) $i['latitude'] : null, 'longitude' => $i['longitude'] !== null ? (float) $i['longitude'] : null,
                            'capacidade_uh' => $i['capacidade_uh'] !== null ? (int) $i['capacidade_uh'] : null,
                            'capacidade_leitos' => $i['capacidade_leitos'] !== null ? (int) $i['capacidade_leitos'] : null,
                            'capacidade_publico' => $i['capacidade_publico'] !== null ? (int) $i['capacidade_publico'] : null]];
    }

    /** Põe o aviso de um item na fila, se o destino tem a ponte ligada. */
    public static function avisarItem(int $eid, int $itemId): void
    {
        if (!self::config($eid)['ligado']) {
            return;
        }
        require_once __DIR__ . '/Fila.php';
        Fila::enfileirar('eventcity_aviso', ['empresa_id' => $eid, 'item_id' => $itemId], null, $eid);
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
