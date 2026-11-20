<?php
/**
 * Conexão do app InvenTour Campo: POST {codigo, aparelho} troca o código
 * gerado no painel pelo token do aparelho.
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Campo.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
Campo::abrirParaApp();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'motivo' => 'metodo']);
    exit;
}
// Código de 8 letras: poucas tentativas por IP, para não dar para adivinhar.
Limite::porIp('campo-conectar', 10, 600);
$corpo = json_decode((string) file_get_contents('php://input'), true) ?: [];
$r = Campo::conectar((string) ($corpo['codigo'] ?? ''), (string) ($corpo['aparelho'] ?? ''));
if (!$r) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'motivo' => 'codigo'], JSON_UNESCAPED_UNICODE);
    exit;
}
[$token, $nome, $destino] = $r;
echo json_encode(['ok' => true, 'token' => $token, 'usuario' => $nome, 'destino' => $destino], JSON_UNESCAPED_UNICODE);
