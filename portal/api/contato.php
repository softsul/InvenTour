<?php
/**
 * Fale conosco do portal: recebe o modal (Contato::modal) e manda a
 * mensagem por e-mail. Mesma origem do portal, sem sessão nem cookie. O
 * mesmo do EventCity (portal/api/contato.php de lá).
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Limite.php';
require_once __DIR__ . '/../../app/Tenant.php';
require_once __DIR__ . '/../../app/Contato.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Use o formulário.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$destino = Tenant::atual();
// Cinco mensagens por hora por rede: sobra para quem escreve de verdade.
Limite::porIp('contato', 5, 3600);
$erro = Contato::enviar($_POST, $destino ? 'portal de turismo de ' . $destino['nome_fantasia'] : 'portal InvenTour');
if ($erro !== null) {
    http_response_code(422);
    echo json_encode(['erro' => $erro], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
