<?php
/**
 * A foto do destino no alto do portal: a mesma da capa do caderno oficial
 * (Encadernação), servida daqui porque fica fora da pasta pública.
 * Softsul Software.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Limite.php';
require_once __DIR__ . '/../app/Tenant.php';
$destino = Tenant::exigir();
$cfg = require __DIR__ . '/../app/config.php';
$f = dirname(rtrim((string) $cfg['caminhos']['midias'], '/')) . '/cadernos/' . (int) $destino['id'] . '/capa.jpg';
if (!is_readable($f)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . filesize($f));
readfile($f);
