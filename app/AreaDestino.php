<?php
/**
 * A área do destino: fotos e GPS da coleta só valem dentro dela.
 * Softsul Software
 *
 * A divisa é a oficial do município (malha municipal do IBGE, gratuita,
 * baixada uma vez e guardada em municipio_areas). Perto da divisa o GPS
 * varia e há atrativos na própria linha (rio, rodovia, ponte): vale uma
 * margem de tolerância (empresas.area_margem_m, 2 km por padrão). Destino
 * sem divisa conhecida (IBGE fora do ar, por exemplo) não bloqueia nada.
 *
 * O que acontece fora da área é escolha de cada destino (empresas.area_regra,
 * nos Dados do destino): alertar (grava e avisa, o padrão) ou bloquear
 * (recusa o GPS, o plus code e a foto, e a homologação trava).
 *
 * A mesma conta roda no app, sem sinal (assets/coleta.js), com a geometria
 * que vai na lista baixada.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Empresa.php';

final class AreaDestino
{
    private const IBGE = 'https://servicodados.ibge.gov.br/api/v3/malhas/municipios/%d?formato=application/vnd.geo%%2Bjson&qualidade=intermediaria';

    /** @var array<int,?array> por empresa */
    private static array $cache = [];

    /**
     * A área do destino: ['poligonos' => [[[lng, lat], ...], ...], 'margem_m', 'municipio', bbox] ou null.
     */
    public static function doDestino(?int $empresaId = null): ?array
    {
        $eid = $empresaId ?? Empresa::id();
        if (array_key_exists($eid, self::$cache)) {
            return self::$cache[$eid];
        }
        $pdo = Database::conexao();
        $q = $pdo->prepare('SELECT e.municipio_id, e.area_margem_m, e.area_regra, m.nome FROM empresas e LEFT JOIN municipios m ON m.id = e.municipio_id WHERE e.id = ?');
        $q->execute([$eid]);
        $e = $q->fetch();
        if (!$e || !$e['municipio_id']) {
            return self::$cache[$eid] = null;
        }
        $mid = (int) $e['municipio_id'];
        $q = $pdo->prepare('SELECT geojson FROM municipio_areas WHERE municipio_id = ?');
        $q->execute([$mid]);
        $geo = $q->fetchColumn();
        if ($geo === false) {
            $geo = self::baixar($mid);
        }
        $poligonos = $geo ? self::aneisExternos(json_decode((string) $geo, true) ?: []) : [];
        if (!$poligonos) {
            return self::$cache[$eid] = null;
        }
        return self::$cache[$eid] = ['poligonos' => $poligonos, 'margem_m' => (int) $e['area_margem_m'], 'municipio' => (string) $e['nome'],
                                     'regra' => (string) $e['area_regra']];
    }

    /** Baixa a divisa do IBGE e guarda. Devolve o GeoJSON da geometria ou null. */
    public static function baixar(int $municipioId): ?string
    {
        $ch = curl_init(sprintf(self::IBGE, $municipioId));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'InvenTour (app.inventour.com.br)']);
        $corpo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $j = $codigo === 200 ? json_decode((string) $corpo, true) : null;
        $g = $j['features'][0]['geometry'] ?? null;
        if (!is_array($g) || !in_array($g['type'] ?? '', ['Polygon', 'MultiPolygon'], true)) {
            return null;
        }
        $xs = $ys = [];
        foreach (self::aneisExternos($g) as $anel) {
            foreach ($anel as [$x, $y]) {
                $xs[] = $x;
                $ys[] = $y;
            }
        }
        $geo = json_encode($g);
        Database::conexao()->prepare('REPLACE INTO municipio_areas (municipio_id, geojson, lat_min, lat_max, lng_min, lng_max, fonte, atualizado_em) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$municipioId, $geo, min($ys), max($ys), min($xs), max($xs), 'IBGE, malha municipal (qualidade intermediária)']);
        return $geo;
    }

    /** Os anéis externos (cada polígono), em [[lng, lat], ...]. Buraco é raro em município e fica de fora. */
    private static function aneisExternos(array $g): array
    {
        return match ($g['type'] ?? '') {
            'Polygon' => isset($g['coordinates'][0]) ? [$g['coordinates'][0]] : [],
            'MultiPolygon' => array_values(array_filter(array_map(fn ($p) => $p[0] ?? null, (array) $g['coordinates']))),
            default => [],
        };
    }

    /**
     * Onde fica o ponto: ['dentro' => bool (já com a margem), 'distancia_m' => metros até a divisa
     * quando está fora da linha (0 dentro dela), 'municipio' => nome]. Sem área conhecida, dentro.
     */
    public static function conferir(float $lat, float $lng, ?int $empresaId = null): array
    {
        $a = self::doDestino($empresaId);
        if (!$a) {
            return ['dentro' => true, 'distancia_m' => 0, 'municipio' => null, 'sem_area' => true];
        }
        foreach ($a['poligonos'] as $anel) {
            if (self::noPoligono($lng, $lat, $anel)) {
                return ['dentro' => true, 'distancia_m' => 0, 'municipio' => $a['municipio']];
            }
        }
        $d = PHP_INT_MAX;
        foreach ($a['poligonos'] as $anel) {
            $d = min($d, self::distanciaAoAnel($lat, $lng, $anel));
        }
        $d = (int) round($d);
        return ['dentro' => $d <= $a['margem_m'], 'distancia_m' => $d, 'municipio' => $a['municipio']];
    }

    /** O destino recusa o que fica fora da área? (sem divisa conhecida, nunca) */
    public static function bloqueia(?int $empresaId = null): bool
    {
        $a = self::doDestino($empresaId);
        return $a !== null && $a['regra'] === 'bloquear';
    }

    /** Texto para o aviso: "a 3,4 km da divisa de Pato Branco". */
    public static function descrever(array $c): string
    {
        $km = $c['distancia_m'] >= 1000 ? number_format($c['distancia_m'] / 1000, 1, ',', '') . ' km' : $c['distancia_m'] . ' m';
        return 'fora de ' . ($c['municipio'] ?? 'destino') . ' (a ' . $km . ' da divisa)';
    }

    private static function noPoligono(float $x, float $y, array $anel): bool
    {
        $dentro = false;
        $n = count($anel);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $anel[$i];
            [$xj, $yj] = $anel[$j];
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $dentro = !$dentro;
            }
        }
        return $dentro;
    }

    /** Menor distância (em metros) do ponto até a linha do anel, numa projeção local. */
    private static function distanciaAoAnel(float $lat, float $lng, array $anel): float
    {
        $kx = 111320 * cos(deg2rad($lat));
        $ky = 110574;
        $min = PHP_FLOAT_MAX;
        $n = count($anel);
        for ($i = 0; $i < $n - 1; $i++) {
            $ax = ($anel[$i][0] - $lng) * $kx;
            $ay = ($anel[$i][1] - $lat) * $ky;
            $bx = ($anel[$i + 1][0] - $lng) * $kx;
            $by = ($anel[$i + 1][1] - $lat) * $ky;
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $l2 = $dx * $dx + $dy * $dy;
            $t = $l2 > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $l2)) : 0;
            $px = $ax + $t * $dx;
            $py = $ay + $t * $dy;
            $min = min($min, sqrt($px * $px + $py * $py));
        }
        return $min;
    }
}
