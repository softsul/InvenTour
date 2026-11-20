<?php
/**
 * Início do portal do destino (provisório).
 * Softsul Software
 *
 * O destino vem do host (Tenant). Enquanto o web app do destino não fica
 * pronto, a página diz de quem é o portal e que o inventário está a caminho.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Limite.php';
require_once __DIR__ . '/../app/Tenant.php';
require_once __DIR__ . '/../app/PortalLayout.php';

$destino = Tenant::exigir();
header('Cache-Control: public, max-age=300');
PortalLayout::inicio($destino, (string) $destino['nome_fantasia']);
?>
  <p class="sobre">Inventário oficial da oferta turística</p>
  <h1><?= e((string) $destino['nome_fantasia']) ?></h1>
  <p class="lead">Atrativos, hospedagem, gastronomia, serviços e a infraestrutura de apoio ao turismo de <?= e((string) $destino['nome_fantasia']) ?>, num só lugar. O portal está em preparação.</p>
<?php PortalLayout::fim();
