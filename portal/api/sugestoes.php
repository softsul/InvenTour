<?php
/**
 * Sugestões da busca do portal, enquanto o visitante digita: até 6 lugares
 * e 3 eventos da agenda, pela mesma busca da página de resultados
 * (Portal::itens e EventCity::buscar). Mesma origem, sem sessão nem cookie.
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Limite.php';
require_once __DIR__ . '/../../app/Tenant.php';
require_once __DIR__ . '/../../app/PortalLayout.php';
require_once __DIR__ . '/../../app/EventCity.php';

header('Content-Type: application/json; charset=utf-8');
$destino = Tenant::atual();
$q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 60));
if (!$destino || mb_strlen($q) < 2) {
    echo '{"lugares":[],"eventos":[]}';
    exit;
}
$eid = (int) $destino['id'];
$aberto = $destino['modo_operacao'] === 'ativo'
    || ($destino['modo_operacao'] === 'avaliacao' && (string) $destino['avaliacao_ate'] >= date('Y-m-d'))
    || Portal::previa($eid);
if (!$aberto) {
    echo '{"lugares":[],"eventos":[]}';
    exit;
}
// Uma letra por pedido, com folga: quem digita rápido não esbarra.
Limite::porIp('sugestoes', 240, 600);
header('Cache-Control: ' . (Portal::previa($eid) ? 'no-store' : 'public, max-age=120'));

$itens = Portal::itens($eid, null, ['q' => $q], 60);
// Primeiro o que começa com o que foi digitado, depois o que só contém.
$sem = fn (string $s) => mb_strtolower(semAcento($s));
$alvo = $sem($q);
usort($itens, function ($a, $b) use ($sem, $alvo) {
    $pa = str_starts_with($sem((string) $a['nome']), $alvo) ? 0 : 1;
    $pb = str_starts_with($sem((string) $b['nome']), $alvo) ? 0 : 1;
    return $pa <=> $pb ?: strcoll((string) $a['nome'], (string) $b['nome']);
});
$lugares = [];
foreach (array_slice($itens, 0, 6) as $i) {
    $lugares[] = [
        'nome' => (string) $i['nome'],
        'tipo' => (string) ($i['tipo_nome'] ?: $i['ficha_nome']),
        'bairro' => (string) ($i['bairro'] ?? ''),
        'foto' => $i['foto'] ? Portal::foto($eid, (string) $i['foto'], 'p') : null,
        'url' => PortalLayout::urlLugar($i),
    ];
}
$eventos = [];
foreach (array_slice(EventCity::buscar($eid, $q, array_column($itens, 'id')), 0, 3) as $ev) {
    $eventos[] = [
        'nome' => (string) $ev['titulo'],
        'quando' => date('d/m', strtotime((string) ($ev['proximo_dia'] ?? $ev['data_inicio']))) . (!empty($ev['hora_inicio']) ? ' · ' . substr((string) $ev['hora_inicio'], 0, 5) : ''),
        'url' => (string) ($ev['url'] ?? ''),
    ];
}
echo json_encode(['lugares' => $lugares, 'eventos' => $eventos, 'total' => count($itens)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
