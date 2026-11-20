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
// Portal aberto só com licença ou na avaliação de 30 dias (PLANEJAMENTO 2.2).
$aberto = $destino['modo_operacao'] === 'ativo'
    || ($destino['modo_operacao'] === 'avaliacao' && (string) $destino['avaliacao_ate'] >= date('Y-m-d'));
PortalLayout::inicio($destino, (string) $destino['nome_fantasia'], $aberto);
?>
  <p class="sobre">Inventário oficial da oferta turística</p>
  <h1><?= e((string) $destino['nome_fantasia']) ?></h1>
<?php if ($aberto): ?>
  <p class="lead">Atrativos, hospedagem, gastronomia, serviços e a infraestrutura de apoio ao turismo de <?= e((string) $destino['nome_fantasia']) ?>, num só lugar. O portal está em preparação.</p>
<?php else: ?>
  <p class="lead">O inventário turístico de <?= e((string) $destino['nome_fantasia']) ?> está em estruturação. O portal oficial abre quando o destino ativar a licença do InvenTour.</p>
<?php endif; ?>
<?php PortalLayout::fim();
