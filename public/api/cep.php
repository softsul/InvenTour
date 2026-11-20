<?php
/**
 * Endereço pelo CEP, consultado pelo servidor na BrasilAPI.
 * Softsul Software
 *
 * A CSP do painel não deixa o navegador falar com terceiros, então a
 * consulta passa por aqui e volta no mesmo formato da BrasilAPI (cep/v2),
 * que é o que o inventour.js espera. Atrás de login: aberto, viraria
 * consultor de CEP de graça.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/funcoes.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): Consulta serviço externo: o limite protege a cota da BrasilAPI.
Limite::porUsuario('cep', 20, 60);
if (!Auth::usuarioOuNulo()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Entre de novo.']);
    exit;
}
$cep = soDigitos($_GET['cep'] ?? '');
if (strlen($cep) !== 8) {
    http_response_code(422);
    echo json_encode(['erro' => 'CEP com 8 dígitos.']);
    exit;
}
$cfg = require __DIR__ . '/../../app/config.php';
$cepCfg = $cfg['cep'];
$ch = curl_init($cepCfg['brasilapi'] . $cep);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => (int) $cepCfg['timeout_s'],
    CURLOPT_USERAGENT      => 'InvenTour/1.0 (+' . rtrim((string) $cfg['app']['url'], '/') . ')',
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$corpo = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$d = is_string($corpo) ? json_decode($corpo, true) : null;
if ($status !== 200 || !is_array($d)) {
    http_response_code(404);
    echo json_encode(['erro' => 'CEP não encontrado.']);
    exit;
}
// Só o que a tela usa: não é preciso repassar a resposta inteira de fora.
$saida = array_intersect_key($d, array_flip(['cep', 'state', 'city', 'neighborhood', 'street']));
// O código IBGE do município, achado pelo nome sem acento e pela UF, para o
// campo de município (que grava o código, e não o nome) se escolher sozinho.
// A BrasilAPI não devolve o código; o slug da tabela municipios é o nome
// sem acento, então a procura usa o índice.
if (!empty($d['city']) && !empty($d['state'])) {
    require_once __DIR__ . '/../../app/Database.php';
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(semAcento((string) $d['city']))), '-');
    $m = Database::conexao()->prepare('SELECT m.id FROM municipios m JOIN estados e ON e.id = m.estado_id WHERE m.slug = ? AND e.uf = ? LIMIT 1');
    $m->execute([$slug, strtoupper((string) $d['state'])]);
    if ($ibge = $m->fetchColumn()) {
        $saida['ibge'] = (int) $ibge;
    }
}
echo json_encode($saida, JSON_UNESCAPED_UNICODE);
