<?php
/**
 * Busca de município do IBGE para o campo de escolha (seletorBuscavel).
 * Softsul Software
 *
 * GET q: pelo menos duas letras do nome (com ou sem acento) ou dígitos do
 * código IBGE. GET id: um município pelo código exato, usado para
 * pré-selecionar o que veio da consulta de CNPJ. Devolve até 20, com quem
 * começa pelo texto digitado primeiro.
 *
 * A busca pelo nome usa a coluna slug (o nome sem acento, minúsculo e com
 * hífen), que tem índice: "sao jose" e "São José" viram o mesmo
 * "sao-jose", e o LIKE "sao-jose%" percorre só o pedaço do índice que
 * interessa. O LIKE com % no começo fica para completar a lista quando o
 * prefixo não basta, e mesmo assim só procura início de palavra
 * ("%-branco%"), que é o que a pessoa digita.
 *
 * O texto vai pronto para HTML em "nome" e "extra" (o inventour.js os põe
 * na lista como HTML), e cru em "texto", que vai para o valor do campo.
 * Atrás de login: aberto, viraria consulta pública de graça.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): Busca ao vivo do campo de município.
Limite::porUsuario('municipios', 120, 60);
header('Cache-Control: no-store');
if (!Auth::usuarioOuNulo()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Entre de novo.']);
    exit;
}
$pdo = Database::conexao();
$base = 'SELECT m.id, m.nome, e.uf FROM municipios m JOIN estados e ON e.id = m.estado_id';

$saida = function (array $linhas): never {
    echo json_encode(array_map(fn ($m) => [
        'id'    => (int) $m['id'],
        'nome'  => e($m['nome']),
        'uf'    => $m['uf'],
        'extra' => e($m['uf'] . ' · IBGE ' . $m['id']),
        'texto' => $m['nome'] . '/' . $m['uf'],
    ], $linhas), JSON_UNESCAPED_UNICODE);
    exit;
};

// Um município pelo código exato.
if (isset($_GET['id'])) {
    $q = $pdo->prepare("{$base} WHERE m.id = ?");
    $q->execute([(int) soDigitos((string) $_GET['id'])]);
    $saida($q->fetchAll());
}

$busca = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($busca) < 2) {
    $saida([]);
}

// Só dígitos: código IBGE. O código tem sete dígitos, então o começo dele
// é um intervalo de ids, e intervalo na chave primária é o mais barato que há.
if (ctype_digit($busca)) {
    $digitos = substr($busca, 0, 7);
    $falta = 7 - strlen($digitos);
    $de = (int) $digitos * (10 ** $falta);
    $ate = $de + (10 ** $falta) - 1;
    $q = $pdo->prepare("{$base} WHERE m.id BETWEEN ? AND ? ORDER BY m.id LIMIT 20");
    $q->execute([$de, $ate]);
    $saida($q->fetchAll());
}

// "Pato Branco/PR" ou "pato branco, pr": a UF depois da barra ou da vírgula
// restringe a busca. Sem o separador não se adivinha: "Vila Pa" não é Pará.
$uf = null;
if (preg_match('~^(.+?)\s*[/,]\s*([A-Za-z]{2})$~u', $busca, $m)) {
    $s = $pdo->prepare('SELECT id FROM estados WHERE uf = ?');
    $s->execute([strtoupper($m[2])]);
    if ($estadoId = $s->fetchColumn()) {
        $uf = (int) $estadoId;
        $busca = $m[1];
    }
}
$slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(semAcento($busca))), '-');
if (strlen($slug) < 2) {
    $saida([]);
}
$filtroUf = $uf !== null ? ' AND m.estado_id = ?' : '';
$comUf = fn (array $a) => $uf !== null ? [...$a, $uf] : $a;

// Primeiro quem começa pelo texto (usa o índice ix_municipios_slug).
$q = $pdo->prepare("{$base} WHERE m.slug LIKE ?{$filtroUf} ORDER BY m.slug = ? DESC, m.nome, e.uf LIMIT 20");
$q->execute([...$comUf([$slug . '%']), $slug]);
$achados = $q->fetchAll();

// Depois, se sobrar lugar, quem tem uma palavra começando pelo texto
// ("branco" acha "Pato Branco"). São 5.571 linhas: a varredura é pequena.
if (count($achados) < 20) {
    $ja = array_map(fn ($a) => (int) $a['id'], $achados) ?: [0];
    $marcas = implode(',', array_fill(0, count($ja), '?'));
    $q = $pdo->prepare("{$base} WHERE m.slug LIKE ?{$filtroUf} AND m.id NOT IN ({$marcas}) ORDER BY m.nome, e.uf LIMIT " . (20 - count($achados)));
    $q->execute([...$comUf(['%-' . $slug . '%']), ...$ja]);
    $achados = array_merge($achados, $q->fetchAll());
}
$saida($achados);
