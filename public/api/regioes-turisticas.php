<?php
/**
 * Busca de região turística do Mapa do Turismo para o filtro de múltipla
 * escolha (multiEscolhaFiltro com fonte), nas telas Cadastur e Mapa do
 * Turismo.
 * Softsul Software
 *
 * GET q: pelo menos duas letras do nome da região, ou "nome/UF". Devolve
 * até 20 regiões do Mapa mais recente, com quem começa pelo texto primeiro.
 * São cerca de 360 linhas: a varredura é pequena.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): busca ao vivo do filtro.
Limite::porUsuario('regioes-turisticas', 120, 60);
header('Cache-Control: no-store');
if (!Auth::usuarioOuNulo() || !Auth::ehSoftsul() || !(Auth::pode('mapa-turismo.ver') || Auth::pode('cadastur.ver'))) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem acesso.']);
    exit;
}
$busca = trim((string) ($_GET['q'] ?? ''));
$uf = '';
if (preg_match('~^(.+?)\s*[/,]\s*([A-Za-z]{2})$~u', $busca, $m)) {
    [$busca, $uf] = [$m[1], strtoupper($m[2])];
}
if (mb_strlen($busca) < 2) {
    echo '[]';
    exit;
}
$q = Database::conexao()->prepare(
    'SELECT r.id, r.nome, e.uf FROM regioes_turisticas r JOIN estados e ON e.id = r.estado_id
     WHERE r.ativo = 1 AND r.nome LIKE ? AND (? = "" OR e.uf = ?)
     ORDER BY r.nome LIKE ? DESC, r.nome, e.uf LIMIT 20'
);
$termo = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busca);
$q->execute(['%' . $termo . '%', $uf, $uf, $termo . '%']);
echo json_encode(array_map(fn ($r) => ['id' => (int) $r['id'], 'texto' => $r['nome'] . '/' . $r['uf']], $q->fetchAll()), JSON_UNESCAPED_UNICODE);
