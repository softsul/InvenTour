<?php
/**
 * /{chave}.txt: a chave do IndexNow, que prova ao buscador que o aviso de
 * página nova saiu de quem cuida do portal (app/Bing.php). Outro nome de
 * arquivo não existe.
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Tenant.php';
require_once __DIR__ . '/../app/Bing.php';

$chave = Bing::chaveIndexNow();
header('Content-Type: text/plain; charset=utf-8');
if (!Tenant::atual() || !hash_equals($chave, (string) ($_GET['chave'] ?? ''))) {
    http_response_code(404);
    exit;
}
header('Cache-Control: public, max-age=86400');
echo $chave;
