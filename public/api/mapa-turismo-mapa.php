<?php
/**
 * O Mapa do Turismo mais recente por município, para o mapa da tela Mapa
 * do Turismo (visão da plataforma).
 * Softsul Software
 *
 * Para cada código IBGE que está no Mapa: [região, categoria, empregos,
 * estabelecimentos, visitas nacionais, visitas internacionais,
 * arrecadação], com os nomes das regiões e os rótulos das categorias. O
 * mapa pinta cada região numa cor e filtra por UF, região e categoria no
 * navegador, sem voltar aqui.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/MapaTurismo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');
if (!Auth::usuarioOuNulo()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Entre de novo.']);
    exit;
}
if (!Auth::pode('mapa-turismo.ver')) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão.']);
    exit;
}
$pdo = Database::leitura();
$ano = MapaTurismo::ano();
$turismo = [];
$regioes = [];
$q = $pdo->prepare('SELECT t.municipio_id, t.regiao_id, t.categoria, t.empregos, t.estabelecimentos, t.visitas_nacionais,
                           t.visitas_internacionais, t.arrecadacao, r.nome, e.uf
                    FROM mapa_turismo t JOIN regioes_turisticas r ON r.id = t.regiao_id JOIN estados e ON e.id = r.estado_id
                    WHERE t.ano = ?');
$q->execute([(int) $ano]);
$inteiro = static fn ($v): ?int => $v === null ? null : (int) $v;
foreach ($q as $t) {
    $turismo[(string) $t['municipio_id']] = [(int) $t['regiao_id'], (int) $t['categoria'], $inteiro($t['empregos']),
        $inteiro($t['estabelecimentos']), $inteiro($t['visitas_nacionais']), $inteiro($t['visitas_internacionais']),
        $t['arrecadacao'] === null ? null : round((float) $t['arrecadacao'])];
    $regioes[(string) $t['regiao_id']] ??= $t['nome'] . '/' . $t['uf'];
}
echo json_encode(['ano' => $ano, 'turismo' => (object) $turismo, 'regioes' => (object) $regioes,
    'curtas' => MapaTurismo::CATEGORIAS_CURTAS, 'tons' => MapaTurismo::TONS], JSON_UNESCAPED_UNICODE);
