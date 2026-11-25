<?php
/**
 * Distâncias da ficha a partir do ponto do item (aeroporto, rodoviária,
 * estações, pontos de ônibus e de táxi, sede do município), para a ficha do
 * item no painel e para a coleta (painel e app InvenTour Campo).
 * Softsul Software
 *
 *   GET /api/distancias?lat=-26.2253&lng=-52.6758
 *   {ok, pronto, distancias: {dist_aeroporto: {km, nome, modo}, ...}}
 *
 * Os pontos vêm de destino_transporte (OpenStreetMap, pela fila); a
 * estrada, do OSRM. Só a coordenada sai daqui, nada da pessoa. Ver
 * app/Transporte.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Campo.php';
require_once __DIR__ . '/../../app/Limite.php';
require_once __DIR__ . '/../../app/Transporte.php';

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
Limite::porUsuario('distancias', 30, 60);
$lat = is_numeric($_GET['lat'] ?? null) ? round((float) $_GET['lat'], 5) : null;
$lng = is_numeric($_GET['lng'] ?? null) ? round((float) $_GET['lng'], 5) : null;
if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
    responder(['ok' => false, 'motivo' => 'coordenada'], 422);
}
$eid = Empresa::id();
if (!Transporte::pronto($eid)) {
    // Os pontos ainda não vieram (a fila busca): melhor não preencher zero em tudo.
    responder(['ok' => true, 'pronto' => false, 'distancias' => (object) []]);
}
responder(['ok' => true, 'pronto' => true, 'distancias' => Transporte::distancias($eid, $lat, $lng)]);
