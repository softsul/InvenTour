<?php
/**
 * robots.txt do portal: tudo aberto, fora o que é só do visitante (Meu
 * roteiro, a busca e o QR), com o sitemap deste host. Portal ainda fechado
 * (projeto, avaliação vencida) não vai aos buscadores.
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Tenant.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$destino = Tenant::atual();
$aberto = $destino && ($destino['modo_operacao'] === 'ativo'
    || ($destino['modo_operacao'] === 'avaliacao' && (string) $destino['avaliacao_ate'] >= date('Y-m-d')));
if (!$aberto) {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
echo "User-agent: *\nAllow: /\nDisallow: /meu-roteiro\nDisallow: /busca\nDisallow: /qr/\nDisallow: /verificar/\nDisallow: /surpresa\n\nSitemap: https://" . Tenant::host() . "/sitemap.xml\n";
