<?php
/**
 * Ponto de QR da cidade (phygital): /qr/<apelido>. Conta a leitura do dia
 * (sem IP e sem cookie) e leva ao destino configurado no painel: o início,
 * a ficha de um lugar, um roteiro, um pilar, a agenda ou O destino. O
 * apelido não muda; o destino pode mudar sem reimprimir o cartaz.
 * Softsul Software.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Limite.php';
require_once __DIR__ . '/../app/Tenant.php';
require_once __DIR__ . '/../app/Portal.php';

$destino = Tenant::exigir();
$eid = (int) $destino['id'];
$pdo = Database::conexaoPortal();
$q = $pdo->prepare('SELECT id, apelido, destino_tipo, destino_ref FROM qr_pontos WHERE empresa_id = ? AND apelido = ? AND ativo = 1');
$q->execute([$eid, strtolower((string) ($_GET['a'] ?? ''))]);
$p = $q->fetch();
header('Cache-Control: no-store');
if (!$p) {
    header('Location: /', true, 302);
    exit;
}
$pdo->prepare('INSERT INTO qr_leituras (ponto_id, empresa_id, dia, leituras) VALUES (?, ?, CURDATE(), 1) ON DUPLICATE KEY UPDATE leituras = leituras + 1')
    ->execute([(int) $p['id'], $eid]);
$ref = (string) $p['destino_ref'];
$para = match ($p['destino_tipo']) {
    'ficha'   => '/lugar/' . (int) $ref,
    'roteiro' => '/roteiro/' . (int) $ref,
    'pilar'   => isset(Portal::PILARES[$ref]) ? '/' . $ref : '/',
    'agenda'  => '/agenda',
    'destino' => '/o-destino',
    default   => '/',
};
header('Location: ' . $para, true, 302);
