<?php
/**
 * Endereço a partir da coordenada, para a coleta de campo (app e painel).
 * Softsul Software
 *
 *   GET /api/endereco?lat=-26.2253&lng=-52.6758
 *   {ok, logradouro, numero, bairro, cep, cidade, uf, completo}
 *
 * Usa o Nominatim (OpenStreetMap), gratuito, pelo nosso servidor: assim vale
 * a política dele (no máximo 1 pedido por segundo, identificação do
 * sistema), e o mesmo ponto não é perguntado duas vezes (cache por 90 dias,
 * coordenada arredondada a 5 casas, cerca de 1 m). Só a coordenada sai
 * daqui, nada da pessoa. Dados: © colaboradores do OpenStreetMap.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Campo.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
Campo::abrirParaApp();

function responder(array $dados, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::usuarioOuNulo() || !Empresa::id()) {
    responder(['ok' => false, 'motivo' => 'sessao'], 401);
}
if (!Auth::pode('coleta.ver') && !Auth::pode('inventario.editar')) {
    responder(['ok' => false, 'motivo' => 'permissao'], 403);
}
Limite::porUsuario('endereco', 30, 60);
$lat = is_numeric($_GET['lat'] ?? null) ? round((float) $_GET['lat'], 5) : null;
$lng = is_numeric($_GET['lng'] ?? null) ? round((float) $_GET['lng'], 5) : null;
if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
    responder(['ok' => false, 'motivo' => 'coordenada'], 400);
}

$cfg = require __DIR__ . '/../../app/config.php';
$pasta = $cfg['caminhos']['cache'] . '/enderecos';
@mkdir($pasta, 0775, true);
$arquivo = sprintf('%s/%.5f_%.5f.json', $pasta, $lat, $lng);
if (is_file($arquivo) && filemtime($arquivo) > time() - 90 * 86400) {
    responder((json_decode((string) file_get_contents($arquivo), true) ?: ['ok' => false]) + ['cache' => true]);
}

// Um pedido por segundo para o Nominatim, para o sistema todo (trava em arquivo).
$trava = fopen($pasta . '/.trava', 'c+');
flock($trava, LOCK_EX);
$ultimo = (float) stream_get_contents($trava);
$espera = 1.05 - (microtime(true) - $ultimo);
if ($espera > 0) {
    usleep((int) ($espera * 1e6));
}
$ch = curl_init('https://nominatim.openstreetmap.org/reverse?' . http_build_query([
    'format' => 'jsonv2', 'lat' => $lat, 'lon' => $lng, 'zoom' => 18, 'addressdetails' => 1, 'accept-language' => 'pt-BR',
]));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
    CURLOPT_USERAGENT => 'InvenTour/1.0 (inventario turistico; +https://inventour.com.br)']);
$corpo = curl_exec($ch);
$codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
ftruncate($trava, 0);
rewind($trava);
fwrite($trava, (string) microtime(true));
flock($trava, LOCK_UN);
fclose($trava);

$r = $codigo === 200 ? json_decode((string) $corpo, true) : null;
if (!is_array($r) || empty($r['address'])) {
    responder(['ok' => false, 'motivo' => 'sem-endereco'], $codigo === 200 ? 200 : 502);
}
$a = $r['address'];
$primeiro = function (array $chaves) use ($a): ?string {
    foreach ($chaves as $c) {
        if (!empty($a[$c])) {
            return (string) $a[$c];
        }
    }
    return null;
};
$uf = null;
if (!empty($a['ISO3166-2-lvl4']) && preg_match('/^BR-([A-Z]{2})$/', (string) $a['ISO3166-2-lvl4'], $m)) {
    $uf = $m[1];
}
$saida = [
    'ok' => true,
    'logradouro' => $primeiro(['road', 'pedestrian', 'footway', 'path', 'track']),
    'numero' => $primeiro(['house_number']),
    'bairro' => $primeiro(['suburb', 'neighbourhood', 'quarter', 'city_district', 'hamlet', 'village']),
    'cep' => ($cep = preg_replace('/\D/', '', (string) ($a['postcode'] ?? ''))) && strlen($cep) === 8 ? $cep : null,
    'cidade' => $primeiro(['city', 'town', 'municipality', 'village']),
    'uf' => $uf,
    'completo' => (string) ($r['display_name'] ?? ''),
    'credito' => '© colaboradores do OpenStreetMap',
];
file_put_contents($arquivo, json_encode($saida, JSON_UNESCAPED_UNICODE));
responder($saida);
