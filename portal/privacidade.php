<?php
/**
 * Política de privacidade no portal do destino.
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Limite.php';
require_once __DIR__ . '/../app/Tenant.php';
require_once __DIR__ . '/../app/PortalLayout.php';

$destino = Tenant::exigir();
$quem = (string) $destino['nome_fantasia'];
$semContato = true;
PortalLayout::inicio($destino, 'Privacidade');
?>
  <h1>Privacidade</h1>
<?php require __DIR__ . '/../app/vistas/privacidade.php'; ?>
<?php PortalLayout::fim();
