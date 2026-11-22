<?php
/**
 * /BingSiteAuth.xml: o código com que o Bing Webmaster confere que o portal
 * é nosso. O job puxar_bing deixa o código de cada host em
 * storage/cache/bing (app/Bing.php); sem código, a página não existe.
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Tenant.php';
require_once __DIR__ . '/../app/Bing.php';

$codigo = Tenant::atual() ? Bing::codigoDoHost(Tenant::host()) : '';
if ($codigo === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit;
}
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0"?>' . "\n<users>\n\t<user>" . htmlspecialchars($codigo, ENT_XML1) . "</user>\n</users>\n";
