<?php
/**
 * Foto da tela de campo: POST multipart com uuid, item_id e foto.
 * Softsul Software
 *
 * A foto vai como saiu da câmera (sem reduzir no celular), para o GPS do
 * EXIF chegar inteiro: Inventario::guardarFoto tira a coordenada e
 * reprocessa. O uuid gerado no celular evita a foto em dobro quando o
 * envio é repetido depois de uma queda de sinal.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Esteira.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(array $dados, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::usuarioOuNulo()) {
    responder(['ok' => false, 'motivo' => 'sessao'], 401);
}
if (!Empresa::id() || (!Auth::pode('coleta.editar') && !Auth::pode('inventario.editar'))) {
    responder(['ok' => false, 'motivo' => 'permissao'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok' => false, 'motivo' => 'metodo'], 405);
}
if (!Auth::conferirCsrf($_SERVER['HTTP_X_CSRF'] ?? null)) {
    responder(['ok' => false, 'motivo' => 'csrf'], 403);
}
Limite::porUsuario('coleta-foto', 200, 60);
$pdo = Database::conexao();
$eid = Empresa::id();
$uid = (int) Auth::usuarioOuNulo()['id'];

$uuid = strtolower((string) ($_POST['uuid'] ?? ''));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid)) {
    responder(['ok' => false, 'motivo' => 'uuid'], 400);
}
$ja = $pdo->prepare('SELECT resultado FROM inventario_coletas WHERE empresa_id = ? AND uuid = ?');
$ja->execute([$eid, $uuid]);
if (($anterior = $ja->fetchColumn()) !== false) {
    responder((json_decode((string) $anterior, true) ?: ['ok' => true]) + ['repetido' => true]);
}
$itemId = (int) ($_POST['item_id'] ?? 0);
$item = Inventario::buscar($itemId);
if (!$item || !Esteira::dentroDoEscopo((string) $item['ficha_codigo'])) {
    responder(['ok' => false, 'motivo' => 'item'], 404);
}
// Foto de item já enviado ainda entra (chegou atrasada pelo sinal), mas não
// em homologado ou arquivado, que só quem coordena mexe.
if (in_array($item['situacao'], ['homologada', 'arquivada'], true) && !Auth::pode('inventario.editar')) {
    responder(['ok' => false, 'motivo' => 'situacao', 'situacao' => $item['situacao']]);
}
$usuario = Auth::usuarioOuNulo();
$falha = Inventario::guardarFoto($itemId, $_FILES['foto'] ?? [], (string) ($usuario['nome'] ?? '') ?: null);
if ($falha !== null) {
    // Sem registrar o uuid: o celular pode tentar de novo.
    responder(['ok' => false, 'motivo' => 'foto', 'mensagem' => $falha], 422);
}
Inventario::registrarHistorico($itemId, 'alterou', null, null, 'foto da coleta em campo');
$r = ['ok' => true, 'item_id' => $itemId];
$pdo->prepare('INSERT IGNORE INTO inventario_coletas (empresa_id, uuid, tipo, usuario_id, item_id, resultado, recebido_em) VALUES (?, ?, ?, ?, ?, ?, NOW())')
    ->execute([$eid, $uuid, 'foto', $uid, $itemId, json_encode($r)]);
responder($r);
