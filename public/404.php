<?php
/**
 * Página não encontrada, com a cara do sistema.
 * Softsul Software
 *
 * O Apache chega aqui pelo ErrorDocument do .htaccess (404 e 403), para a
 * folha branca do servidor nunca aparecer.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

$pedido = mb_substr(strtok((string) ($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? ''), '?') ?: '', 0, 120);
paginaErro(404, 'Esta página não existe.',
    ($pedido !== '' && $pedido !== '/404' ? 'Você pediu ' . $pedido . ', e não há nada nesse endereço. ' : '')
    . 'Pode ser um link antigo ou um endereço digitado com um caractere a mais.');
