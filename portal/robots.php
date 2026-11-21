<?php
/** robots.txt do portal: tudo aberto, com o sitemap. Softsul Software. */
declare(strict_types=1);
require_once __DIR__ . '/../app/Tenant.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\nAllow: /\nDisallow: /meu-roteiro\nDisallow: /busca\nSitemap: https://" . Tenant::host() . "/sitemap.xml\n";
