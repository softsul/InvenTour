<?php
/**
 * Blocos do mapa base do destino (OpenStreetMap, os mesmos do mapa do
 * portal, em portal/mapa-base/<destino>) para o mapa da coleta: no painel
 * e no app InvenTour Campo, onde o pin da coordenada é ajustado.
 * Softsul Software
 *
 * Só o destino de quem está logado (ou do aparelho conectado). Bloco que
 * não existe (mar, fora do recorte) devolve 204: o mapa segue sem ele.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Campo.php';

Campo::abrirParaApp();
if (!Auth::usuarioOuNulo() || !Empresa::id()) {
    http_response_code(401);
    exit;
}
$z = (int) ($_GET['z'] ?? -1);
$x = (int) ($_GET['x'] ?? -1);
$y = (int) ($_GET['y'] ?? -1);
if ($z < 0 || $z > 15 || $x < 0 || $y < 0 || $x >= 2 ** $z || $y >= 2 ** $z) {
    http_response_code(400);
    exit;
}
$arq = dirname(__DIR__, 2) . '/portal/mapa-base/' . Empresa::id() . "/{$z}/{$x}/{$y}.pbf";
header('Cache-Control: private, max-age=604800');
if (!is_file($arq)) {
    http_response_code(204);
    exit;
}
header('Content-Type: application/x-protobuf');
header('Content-Length: ' . filesize($arq));
readfile($arq);
