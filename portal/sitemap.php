<?php
/** /sitemap.xml do portal: as seções, os lugares e os roteiros públicos. Softsul Software. */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
header('Content-Type: application/xml; charset=utf-8');
$base = 'https://' . Tenant::host();
$urls = ['/', '/onde-dormir', '/onde-comer', '/o-que-fazer', '/servicos', '/experiencias', '/roteiros', '/o-destino'];
foreach (Portal::itens($eid, null, [], 5000) as $i) {
    $urls[] = '/lugar/' . (int) $i['id'] . '-' . Portal::slug((string) $i['nome']);
}
foreach (Portal::roteiros($eid) as $r) {
    $urls[] = '/roteiro/' . (int) $r['id'] . '-' . Portal::slug((string) $r['titulo']);
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '<url><loc>' . htmlspecialchars($base . $u, ENT_XML1) . '</loc></url>' . "\n";
}
echo '</urlset>';
