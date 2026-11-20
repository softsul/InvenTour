<?php
/**
 * Guarda o tema (claro ou escuro) escolhido no menu do usuário.
 * Softsul Software
 *
 * Fica na conta, e não no aparelho: vale em qualquer computador ou celular.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): Um clique no menu; mais que isso é script.
Limite::porUsuario('tema', 30, 60);
header('Cache-Control: no-store');
if (!Auth::usuarioOuNulo() || $_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::conferirCsrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
Auth::guardarTema(($_POST['tema'] ?? '') === 'escuro');
echo json_encode(['ok' => true]);
