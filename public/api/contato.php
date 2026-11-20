<?php
/**
 * Fale conosco do site institucional (inventour.com.br, no outro servidor):
 * o modal de lá posta aqui. Público, sem sessão; só a origem do site passa
 * pelo CORS, e o reCAPTCHA confere o domínio da resposta.
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Contato.php';

$origem = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
// O InvenTour (inventour.com.br, no servidor CentOS) usa o mesmo recebimento
// para o Avise-me no lançamento.
$doInventour = in_array($origem, ['https://inventour.com.br', 'https://www.inventour.com.br'], true);
if ($doInventour || in_array($origem, ['https://inventour.com.br', 'https://www.inventour.com.br'], true)) {
    header('Access-Control-Allow-Origin: ' . $origem);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Max-Age: 86400');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Use o formulário.'], JSON_UNESCAPED_UNICODE);
    exit;
}
Limite::porIp('contato', 5, 3600);
$erro = $doInventour ? Contato::enviar($_POST, 'site inventour.com.br', 'InvenTour') : Contato::enviar($_POST, 'site inventour.com.br');
if ($erro !== null) {
    http_response_code(422);
    echo json_encode(['erro' => $erro], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
