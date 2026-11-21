<?php
/**
 * Abertura comum das páginas do portal: o destino pelo host e a regra de
 * portal aberto (licença ativa ou avaliação no prazo; a prévia interna do
 * painel abre sempre). Fechado, mostra o aviso e para. Softsul Software.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Limite.php';
require_once __DIR__ . '/../../app/Tenant.php';
require_once __DIR__ . '/../../app/PortalLayout.php';

$destino = Tenant::exigir();
$eid = (int) $destino['id'];
$aberto = $destino['modo_operacao'] === 'ativo'
    || ($destino['modo_operacao'] === 'avaliacao' && (string) $destino['avaliacao_ate'] >= date('Y-m-d'))
    || Portal::previa($eid);
header('Cache-Control: ' . (Portal::previa($eid) ? 'no-store' : 'public, max-age=300'));
if (!$aberto) {
    PortalLayout::inicio(null, (string) $destino['nome_fantasia'], false);
    echo '<p class="sobre">Inventário oficial da oferta turística</p><h1>' . e((string) $destino['nome_fantasia']) . '</h1>'
        . '<p class="lead">O inventário turístico de ' . e((string) $destino['nome_fantasia']) . ' está em estruturação. O portal oficial abre quando o destino ativar a licença do InvenTour.</p>';
    PortalLayout::fim();
    exit;
}

/** 404 do portal, dentro da moldura do destino. */
function naoAchou(array $destino, string $texto = 'Esta página não existe ou saiu do ar.'): never
{
    http_response_code(404);
    PortalLayout::inicio($destino, 'Não encontrado', false);
    echo '<h1>Não encontrado</h1><p class="lead">' . e($texto) . '</p><p><a class="cta" href="' . e(Portal::url('/')) . '">Ir para o início</a></p>';
    PortalLayout::fim();
    exit;
}
