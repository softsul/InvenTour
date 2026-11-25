<?php
/**
 * Distâncias da ficha (aeroporto, rodoviária, ferroviária, marítima ou
 * fluvial, metrô, ponto de ônibus, ponto de táxi e sede do município)
 * calculadas sozinhas a partir do ponto do item.
 * Softsul Software
 *
 * Os pontos de transporte vêm do OpenStreetMap (ODbL) pela Overpass API, uma
 * vez por destino e de novo a cada 30 dias (job destino_transporte da
 * fila), e ficam em destino_transporte: nada se pergunta lá fora a cada
 * item. Os grandes (aeroporto com código IATA, rodoviária, estações) num
 * raio de 200 km; os pontos de ônibus e de táxi só na área do município.
 *
 * A distância é pela estrada (OSRM, roteador público do OpenStreetMap, um
 * pedido por cálculo com os três candidatos mais perto de cada tipo); sem
 * resposta dele, em linha reta, e a tela diz qual foi. Ponto de ônibus e de
 * táxi são sempre em linha reta (é a pé, e perto). Mais de 200 km, ou nenhum
 * no raio, fica zero (pedido do Luiz, 25/11/2026).
 *
 * Igual para todos os destinos: nada aqui depende de cidade.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/MapaBase.php';

final class Transporte
{
    /** chave do atributo da ficha => tipo do ponto (null: a sede do município). */
    public const CHAVES = [
        'dist_aeroporto'    => 'aeroporto',
        'dist_rodoviaria'   => 'rodoviaria',
        'dist_ferroviaria'  => 'ferroviaria',
        'dist_maritima'     => 'maritima',
        'dist_metroviaria'  => 'metroviaria',
        'dist_ponto_onibus' => 'ponto_onibus',
        'dist_ponto_taxi'   => 'ponto_taxi',
        'dist_sede_km'      => null,
    ];
    /** O que aparece quando o ponto não tem nome no mapa. */
    private const SEM_NOME = [
        'aeroporto' => 'aeroporto sem nome no mapa', 'rodoviaria' => 'rodoviária sem nome no mapa',
        'ferroviaria' => 'estação sem nome no mapa', 'maritima' => 'terminal de barco ou balsa sem nome no mapa',
        'metroviaria' => 'estação de metrô sem nome no mapa', 'ponto_onibus' => 'ponto de ônibus mais perto', 'ponto_taxi' => 'ponto de táxi mais perto',
    ];
    /** Os que se medem a pé, em linha reta. */
    public const A_PE = ['ponto_onibus', 'ponto_taxi'];
    public const LIMITE_KM = 200;
    private const VALIDADE_DIAS = 30;
    private const ESPELHOS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
        'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
    ];
    private const OSRM = 'https://router.project-osrm.org/table/v1/driving/';
    private const AGENTE = 'InvenTour/1.0 (+https://inventour.com.br)';

    // ------------------------------------------------------------ os pontos

    /** O próximo destino sem pontos ou com pontos vencidos; 0 se nenhum. */
    public static function proximo(): int
    {
        $q = Database::conexao()->query('SELECT id FROM empresas WHERE ativo = 1 AND municipio_id IS NOT NULL
            AND (transporte_em IS NULL OR transporte_em < NOW() - INTERVAL ' . self::VALIDADE_DIAS . ' DAY)
            ORDER BY transporte_em IS NOT NULL, transporte_em, id LIMIT 1');
        return (int) $q->fetchColumn();
    }

    /**
     * Busca os pontos do destino na Overpass e troca os gravados. Devolve a
     * contagem por tipo. Se todos os espelhos falharem, lança exceção e os
     * pontos antigos ficam (a fila tenta de novo).
     */
    public static function buscar(int $eid): array
    {
        $r = MapaBase::retangulo($eid);
        if (!$r) {
            throw new RuntimeException('destino sem divisa do município');
        }
        [$o, $s, $l, $n] = $r;
        $clat = ($s + $n) / 2;
        $clng = ($o + $l) / 2;
        // Raio de 200 km a partir da borda da área, não do centro.
        $raio = (int) round(self::LIMITE_KM * 1000 + self::haversine($clat, $clng, $n, $l) * 1000);
        $em = sprintf('around:%d,%.6f,%.6f', $raio, $clat, $clng);
        $caixa = sprintf('%.6f,%.6f,%.6f,%.6f', $s, $o, $n, $l);
        $q = Database::conexao()->prepare('SELECT m.nome FROM empresas e JOIN municipios m ON m.id = e.municipio_id WHERE e.id = ?');
        $q->execute([$eid]);
        $municipio = str_replace(['\\', '"'], '', (string) $q->fetchColumn());
        $consulta = '[out:json][timeout:120];('
            . 'node["place"~"^(city|town|village)$"]["name"="' . $municipio . '"](' . $caixa . ');'
            . 'nwr["aeroway"="aerodrome"]["iata"](' . $em . ');'
            . 'nwr["amenity"="bus_station"](' . $em . ');'
            . 'nwr["railway"~"^(station|halt)$"]["usage"!="freight"]["railway:traffic_mode"!="freight"]["disused"!="yes"](' . $em . ');'
            . 'nwr["amenity"="ferry_terminal"](' . $em . ');'
            . 'nwr["highway"="bus_stop"](' . $caixa . ');'
            . 'nwr["public_transport"="platform"]["bus"="yes"](' . $caixa . ');'
            . 'nwr["amenity"="taxi"](' . $caixa . ');'
            . ');out center tags;';
        $dados = self::overpass($consulta);
        $pontos = [];
        $vistos = [];
        foreach ((array) ($dados['elements'] ?? []) as $el) {
            $t = (array) ($el['tags'] ?? []);
            $lat = $el['lat'] ?? ($el['center']['lat'] ?? null);
            $lng = $el['lon'] ?? ($el['center']['lon'] ?? null);
            $tipo = self::tipoDe($t);
            if ($tipo === null || !is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }
            $osm = substr((string) ($el['type'] ?? 'n'), 0, 1) . (int) ($el['id'] ?? 0);
            if (isset($vistos[$osm])) {
                continue;
            }
            $vistos[$osm] = true;
            $nome = trim((string) ($t['name'] ?? $t['official_name'] ?? ''));
            // Estação desativada que ficou no mapa com o aviso no nome.
            if (preg_match('/desativad|abandonad|extint/iu', $nome)) {
                continue;
            }
            $pontos[] = [$tipo, $nome !== '' ? mb_substr($nome, 0, 200) : null, round((float) $lat, 6), round((float) $lng, 6), $osm];
        }
        $pdo = Database::conexao();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM destino_transporte WHERE empresa_id = ?')->execute([$eid]);
            $ins = $pdo->prepare('INSERT INTO destino_transporte (empresa_id, tipo, nome, latitude, longitude, osm) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($pontos as $p) {
                $ins->execute([$eid, ...$p]);
            }
            $pdo->prepare('UPDATE empresas SET transporte_em = NOW() WHERE id = ?')->execute([$eid]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $conta = array_fill_keys(array_filter(array_values(self::CHAVES)), 0) + ['sede' => 0];
        foreach ($pontos as $p) {
            $conta[$p[0]]++;
        }
        return $conta;
    }

    /** O tipo do ponto pelas etiquetas do OpenStreetMap, ou null. */
    private static function tipoDe(array $t): ?string
    {
        if (isset($t['place'])) {
            return 'sede';
        }
        if (($t['aeroway'] ?? '') === 'aerodrome') {
            return !empty($t['iata']) ? 'aeroporto' : null;
        }
        if (($t['amenity'] ?? '') === 'bus_station') {
            return 'rodoviaria';
        }
        if (($t['amenity'] ?? '') === 'ferry_terminal') {
            return 'maritima';
        }
        if (($t['amenity'] ?? '') === 'taxi') {
            return 'ponto_taxi';
        }
        if (($t['highway'] ?? '') === 'bus_stop' || (($t['public_transport'] ?? '') === 'platform' && ($t['bus'] ?? '') === 'yes')) {
            return 'ponto_onibus';
        }
        if (in_array($t['railway'] ?? '', ['station', 'halt'], true)) {
            if (($t['station'] ?? '') === 'subway' || ($t['subway'] ?? '') === 'yes') {
                return 'metroviaria';
            }
            // Bonde, VLT e monotrilho não são estação ferroviária.
            if (in_array($t['station'] ?? '', ['light_rail', 'monorail', 'funicular', 'tram'], true)) {
                return null;
            }
            return 'ferroviaria';
        }
        return null;
    }

    private static function overpass(string $consulta): array
    {
        $erros = [];
        foreach (self::ESPELHOS as $i => $url) {
            for ($tentativa = 0; $tentativa < 2; $tentativa++) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query(['data' => $consulta]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 150,
                    CURLOPT_CONNECTTIMEOUT => 15,
                    CURLOPT_USERAGENT => self::AGENTE,
                ]);
                $corpo = curl_exec($ch);
                $cod = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);
                $d = is_string($corpo) ? json_decode($corpo, true) : null;
                if ($cod === 200 && is_array($d) && isset($d['elements'])) {
                    return $d;
                }
                $erros[] = parse_url($url, PHP_URL_HOST) . ' ' . $cod;
                // Cheio ou sobrecarregado: espera antes de tentar de novo, como pede a Overpass.
                if (in_array($cod, [429, 502, 503, 504], true)) {
                    sleep(10 * ($tentativa + 1));
                } else {
                    break;
                }
            }
        }
        throw new RuntimeException('Overpass sem resposta: ' . implode(', ', $erros));
    }

    // ------------------------------------------------------------ as distâncias

    /** Os pontos do destino, para o app levar e calcular sem sinal: [tipo, nome, lat, lng]. */
    public static function pontos(int $eid): array
    {
        $q = Database::conexao()->prepare('SELECT tipo, nome, latitude, longitude FROM destino_transporte WHERE empresa_id = ? AND tipo <> \'sede\' ORDER BY tipo, id');
        $q->execute([$eid]);
        return array_map(fn ($p) => [(string) $p['tipo'], $p['nome'] !== null ? (string) $p['nome'] : self::SEM_NOME[$p['tipo']] ?? null, (float) $p['latitude'], (float) $p['longitude']], $q->fetchAll());
    }

    /** A sede do município do destino: [lat, lng, nome] ou null. Primeiro a do OpenStreetMap. */
    public static function sede(int $eid): ?array
    {
        $q = Database::conexao()->prepare('SELECT latitude, longitude, nome FROM destino_transporte WHERE empresa_id = ? AND tipo = \'sede\' ORDER BY id LIMIT 1');
        $q->execute([$eid]);
        if ($m = $q->fetch()) {
            return [(float) $m['latitude'], (float) $m['longitude'], (string) $m['nome']];
        }
        $q = Database::conexao()->prepare('SELECT m.latitude, m.longitude, m.nome FROM empresas e JOIN municipios m ON m.id = e.municipio_id WHERE e.id = ?');
        $q->execute([$eid]);
        $m = $q->fetch();
        return $m && $m['latitude'] !== null ? [(float) $m['latitude'], (float) $m['longitude'], (string) $m['nome']] : null;
    }

    /** Se o destino já tem os pontos buscados. */
    public static function pronto(int $eid): bool
    {
        $q = Database::conexao()->prepare('SELECT transporte_em IS NOT NULL FROM empresas WHERE id = ?');
        $q->execute([$eid]);
        return (bool) $q->fetchColumn();
    }

    /**
     * As distâncias a partir do ponto, por chave de atributo:
     * ['km' => float, 'nome' => ?string, 'modo' => 'estrada'|'reta'|'longe'].
     * 'longe': nenhum até 200 km, km = 0.
     */
    public static function distancias(int $eid, float $lat, float $lng): array
    {
        $porTipo = [];
        foreach (self::pontos($eid) as [$tipo, $nome, $plat, $plng]) {
            $porTipo[$tipo][] = ['nome' => $nome, 'lat' => $plat, 'lng' => $plng, 'reta' => self::haversine($lat, $lng, $plat, $plng)];
        }
        $sede = self::sede($eid);
        $saida = [];
        $estrada = [];   // [chave, candidato] para perguntar ao OSRM
        foreach (self::CHAVES as $chave => $tipo) {
            if ($tipo === null) {
                $cand = $sede ? [['nome' => $sede[2], 'lat' => $sede[0], 'lng' => $sede[1], 'reta' => self::haversine($lat, $lng, $sede[0], $sede[1])]] : [];
            } else {
                $cand = $porTipo[$tipo] ?? [];
                usort($cand, fn ($a, $b) => $a['reta'] <=> $b['reta']);
                $cand = array_slice($cand, 0, 3);
            }
            if (!$cand || ($tipo !== null && $cand[0]['reta'] > self::LIMITE_KM)) {
                if ($tipo !== null) {
                    $saida[$chave] = ['km' => 0.0, 'nome' => null, 'modo' => 'longe'];
                }
                continue;
            }
            $saida[$chave] = ['km' => round($cand[0]['reta'], 1), 'nome' => $cand[0]['nome'], 'modo' => 'reta'];
            if (!in_array($tipo, self::A_PE, true) && $cand[0]['reta'] >= 0.3) {
                foreach ($cand as $c) {
                    $estrada[] = [$chave, $c];
                }
            }
        }
        if ($estrada) {
            $km = self::pelaEstrada($lat, $lng, array_map(fn ($e) => [$e[1]['lat'], $e[1]['lng']], $estrada));
            if ($km !== null) {
                $melhor = [];
                foreach ($estrada as $i => [$chave, $c]) {
                    if ($km[$i] !== null && (!isset($melhor[$chave]) || $km[$i] < $melhor[$chave][0])) {
                        $melhor[$chave] = [$km[$i], $c['nome']];
                    }
                }
                foreach ($melhor as $chave => [$k, $nome]) {
                    $saida[$chave] = $chave !== 'dist_sede_km' && $k > self::LIMITE_KM
                        ? ['km' => 0.0, 'nome' => null, 'modo' => 'longe']
                        : ['km' => round($k, 1), 'nome' => $nome, 'modo' => 'estrada'];
                }
            }
        }
        return $saida;
    }

    /**
     * Distância de carro do ponto até cada destino (km), pelo OSRM, num
     * pedido só. Null se o OSRM não respondeu; null na posição sem rota.
     *
     * @param list<array{0:float,1:float}> $destinos
     * @return list<?float>|null
     */
    private static function pelaEstrada(float $lat, float $lng, array $destinos): ?array
    {
        $coords = [sprintf('%.6f,%.6f', $lng, $lat)];
        foreach ($destinos as [$dlat, $dlng]) {
            $coords[] = sprintf('%.6f,%.6f', $dlng, $dlat);
        }
        $ch = curl_init(self::OSRM . implode(';', $coords) . '?sources=0&annotations=distance');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_USERAGENT => self::AGENTE]);
        $corpo = curl_exec($ch);
        $cod = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $d = is_string($corpo) ? json_decode($corpo, true) : null;
        if ($cod !== 200 || !is_array($d) || ($d['code'] ?? '') !== 'Ok' || !isset($d['distances'][0])) {
            return null;
        }
        $linha = array_slice((array) $d['distances'][0], 1);
        return array_map(fn ($m) => is_numeric($m) ? (float) $m / 1000 : null, $linha);
    }

    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = M_PI / 180;
        $a = sin(($lat2 - $lat1) * $r / 2) ** 2 + cos($lat1 * $r) * cos($lat2 * $r) * sin(($lng2 - $lng1) * $r / 2) ** 2;
        return 6371.0 * 2 * asin(min(1.0, sqrt($a)));
    }
}
