<?php
/**
 * Contagem do Cadastur por município e atividade, para o mapa da tela
 * Cadastur (visão da plataforma).
 * Softsul Software
 *
 * Devolve as atividades numa lista e, para cada código IBGE, quantos
 * prestadores há de cada atividade (pela posição na lista), os vencidos e
 * a soma de leitos. A busca e a situação da lista vêm junto (q, situacao)
 * e entram na conta pelo mesmo Cadastur::recorte da lista; atividade, UF,
 * município e região turística o mapa filtra no navegador, sem voltar aqui.
 *
 * Vai junto o Mapa do Turismo mais recente: para cada código IBGE que está
 * nele, [região, categoria], com os nomes das regiões e das categorias. O
 * cartão do município mostra a região, e o filtro de região da lista vira
 * o recorte do mapa.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Cadastur.php';
require_once __DIR__ . '/../../app/MapaTurismo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');
if (!Auth::usuarioOuNulo()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Entre de novo.']);
    exit;
}
if (!Auth::pode('cadastur.ver')) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão.']);
    exit;
}
$pdo = Database::leitura();
$atividades = $pdo->query('SELECT DISTINCT atividade FROM cadastur_prestadores ORDER BY atividade')->fetchAll(PDO::FETCH_COLUMN);
$indice = array_flip($atividades);
$municipios = [];
[$onde, $args] = Cadastur::recorte([
    'q'        => mb_substr((string) ($_GET['q'] ?? ''), 0, 100),
    'situacao' => Lista::escolhas('situacao', array_keys(Cadastur::SITUACOES)),
]);
$onde[] = 'c.municipio_id IS NOT NULL';
$q = $pdo->prepare('SELECT c.municipio_id, c.atividade, COUNT(*) AS n, SUM(c.validade < CURDATE()) AS vencidos, COALESCE(SUM(c.leitos), 0) AS leitos
                    FROM cadastur_prestadores c WHERE ' . implode(' AND ', $onde) . ' GROUP BY c.municipio_id, c.atividade');
$q->execute($args);
foreach ($q as $l) {
    $m = &$municipios[(string) $l['municipio_id']];
    $m['a'][$indice[$l['atividade']]] = (int) $l['n'];
    if ((int) $l['vencidos']) {
        $m['v'][$indice[$l['atividade']]] = (int) $l['vencidos'];
    }
    if ((int) $l['leitos']) {
        $m['l'] = ($m['l'] ?? 0) + (int) $l['leitos'];
    }
    unset($m);
}
$turismo = [];
$regioes = [];
foreach ($pdo->query('SELECT t.municipio_id, t.regiao_id, t.categoria, r.nome, e.uf FROM mapa_turismo t
                      JOIN regioes_turisticas r ON r.id = t.regiao_id JOIN estados e ON e.id = r.estado_id
                      WHERE t.ano = (SELECT MAX(ano) FROM mapa_turismo)') as $t) {
    $turismo[(string) $t['municipio_id']] = [(int) $t['regiao_id'], (int) $t['categoria']];
    $regioes[(string) $t['regiao_id']] ??= $t['nome'] . '/' . $t['uf'];
}
echo json_encode(['atividades' => $atividades, 'municipios' => $municipios, 'turismo' => (object) $turismo, 'regioes' => (object) $regioes,
    'categorias' => MapaTurismo::CATEGORIAS], JSON_UNESCAPED_UNICODE);
