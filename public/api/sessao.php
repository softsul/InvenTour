<?php
/**
 * A sessão ainda vale? Para a página voltar sozinha ao login quando a pessoa
 * volta para a aba e a sessão já venceu (assets/inventour.js).
 * Softsul Software
 *
 * Não renova a sessão (não conta como uso): só confere.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): Chamada ao voltar para a aba; sem sessão, conta o IP.
Limite::porUsuario('sessao', 60, 60);
header('Cache-Control: no-store');
http_response_code(Auth::sessaoAinda() ? 200 : 401);
echo '{}';
