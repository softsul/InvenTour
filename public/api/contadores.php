<?php
/**
 * Os números do menu, sem recarregar a tela (inventour.js, a cada 30 s).
 * Softsul Software
 *
 * Cada módulo com fila (fichas esperando homologação, por exemplo) põe o
 * seu número aqui, respeitando a permissão do módulo e o destino atual.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): O menu pergunta a cada 30 s por aba aberta.
Limite::porUsuario('contadores', 60, 60);
header('Cache-Control: no-store');
if (!Auth::usuarioOuNulo()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
$saida = ['ok' => true] + contadoresDoMenu();
// Cada contagem tem o escopo do destino na consulta (empresa_id = Empresa::id()).
echo json_encode($saida);
