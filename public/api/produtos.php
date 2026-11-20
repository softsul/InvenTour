<?php
/**
 * Os produtos da Softsul, para o menu do pé da barra lateral.
 * Softsul Software
 *
 * Produto interno (Pulsar, Vega) chega sem link para quem não é da equipe da
 * Softsul: aparece desabilitado, e nunca some da lista (padrão da família,
 * Luiz, 14/11/2026).
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/ProdutosSoftsul.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');
if (!Auth::usuarioOuNulo()) {
    http_response_code(401);
    echo json_encode(['produtos' => []]);
    exit;
}
$equipe = Auth::ehSoftsul();
$produtos = array_map(static function (array $p) use ($equipe): array {
    $p['bloqueado'] = $p['interno'] && !$equipe;
    if ($p['bloqueado']) {
        $p['login'] = null;
    }
    return $p;
}, ProdutosSoftsul::lista());
echo json_encode(['produtos' => $produtos], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
