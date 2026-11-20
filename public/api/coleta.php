<?php
/**
 * API da tela de campo (/coleta).
 * Softsul Software
 *
 *   GET  /api/coleta            o que levar para o campo: fichas do escopo,
 *                               com tipos, e os itens em coleta
 *   POST /api/coleta            {acao: salvar|enviar, uuid, ...} em JSON
 *
 * O celular guarda tudo na fila dele e manda quando tem sinal. Cada envio
 * leva um uuid gerado lá: se o sinal cai depois de gravar e antes da
 * resposta, o reenvio encontra o uuid em inventario_coletas e recebe a mesma
 * resposta, sem duplicar o item. O token CSRF vai no cabeçalho X-CSRF.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Esteira.php';
require_once __DIR__ . '/../../app/Limite.php';
require_once __DIR__ . '/../../app/Campo.php';
require_once __DIR__ . '/../../app/PlusCode.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// O app InvenTour Campo chama de outra origem, com o token do aparelho.
Campo::abrirParaApp();

function responder(array $dados, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::usuarioOuNulo()) {
    responder(['ok' => false, 'motivo' => 'sessao'], 401);
}
if (!Empresa::id() || !Auth::pode('coleta.ver')) {
    responder(['ok' => false, 'motivo' => 'permissao'], 403);
}
Limite::porUsuario('coleta', 600, 60);
$pdo = Database::conexao();
$eid = Empresa::id();
$uid = (int) Auth::usuarioOuNulo()['id'];

// Teste de conexão do botão Sincronizar: rápido e sem dados.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ping'])) {
    responder(['ok' => true, 'em' => date('c')]);
}

// ------------------------------------------------------------------ DADOS
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    [$fe, $ae] = Esteira::filtroEscopo();
    $q = $pdo->prepare("SELECT f.id, f.codigo, f.nome, f.categoria, f.forma FROM inventario_fichas f WHERE f.ativo = 1{$fe} ORDER BY f.ordem");
    $q->execute($ae);
    $fichas = [];
    foreach ($q->fetchAll() as $f) {
        $fichas[] = ['id' => (int) $f['id'], 'codigo' => $f['codigo'], 'nome' => $f['nome'], 'categoria' => $f['categoria'], 'forma' => $f['forma'],
                     'tipos' => array_map(fn ($t) => ['id' => (int) $t['id'], 'nome' => $t['nome']], Inventario::tipos((int) $f['id']))];
    }
    $q = $pdo->prepare("SELECT i.id, i.ficha_id, i.tipo_id, i.situacao, i.nome, i.logradouro, i.numero, i.bairro, i.referencia, i.zona,
                               i.latitude, i.longitude, i.plus_code, i.telefone, i.whatsapp, i.instagram, i.site, i.conservacao,
                               i.devolucao_nota, f.codigo AS ficha_codigo,
                               (SELECT COUNT(*) FROM inventario_midias m WHERE m.item_id = i.id AND m.experiencia_id IS NULL) AS fotos
                        FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                        WHERE i.empresa_id = ? AND i.situacao IN ('pre_cadastro', 'em_campo'){$fe}
                        ORDER BY i.devolucao_nota IS NULL, i.nome");
    $q->execute([$eid, ...$ae]);
    $itens = array_map(function ($i) {
        foreach (['id', 'ficha_id', 'tipo_id', 'fotos'] as $c) {
            $i[$c] = $i[$c] === null ? null : (int) $i[$c];
        }
        $i['latitude'] = $i['latitude'] === null ? null : (float) $i['latitude'];
        $i['longitude'] = $i['longitude'] === null ? null : (float) $i['longitude'];
        return $i;
    }, $q->fetchAll());
    $empresa = Empresa::atual();
    $u = Auth::usuarioOuNulo();
    responder(['ok' => true, 'destino' => $empresa['nome_fantasia'] ?? '', 'usuario' => $u['nome'] ?? '', 'em' => date('c'),
               'csrf' => Auth::porAparelho() ? null : Auth::tokenCsrf(),
               'pode_criar' => Auth::pode('coleta.criar') || Auth::pode('inventario.criar'), 'fichas' => $fichas, 'itens' => $itens]);
}

// ------------------------------------------------------------------ ENVIOS
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok' => false, 'motivo' => 'metodo'], 405);
}
if (!Auth::porAparelho() && !Auth::conferirCsrf($_SERVER['HTTP_X_CSRF'] ?? null)) {
    responder(['ok' => false, 'motivo' => 'csrf'], 403);
}
$corpo = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($corpo)) {
    responder(['ok' => false, 'motivo' => 'corpo'], 400);
}
$uuid = strtolower((string) ($corpo['uuid'] ?? ''));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid)) {
    responder(['ok' => false, 'motivo' => 'uuid'], 400);
}
// O mesmo envio de novo: a mesma resposta.
$ja = $pdo->prepare('SELECT resultado FROM inventario_coletas WHERE empresa_id = ? AND uuid = ?');
$ja->execute([$eid, $uuid]);
if (($anterior = $ja->fetchColumn()) !== false) {
    responder((json_decode((string) $anterior, true) ?: ['ok' => true]) + ['repetido' => true]);
}
$registrar = function (string $tipo, ?int $itemId, array $resultado) use ($pdo, $eid, $uuid, $uid): void {
    $pdo->prepare('INSERT INTO inventario_coletas (empresa_id, uuid, tipo, usuario_id, item_id, resultado, recebido_em) VALUES (?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$eid, $uuid, $tipo, $uid, $itemId, json_encode($resultado, JSON_UNESCAPED_UNICODE)]);
};
$acao = (string) ($corpo['acao'] ?? '');
$itemId = (int) ($corpo['item_id'] ?? 0);

/** O item tem de ser do destino, do escopo da pessoa e estar em coleta. */
$itemEmColeta = function (int $id): ?array {
    $item = Inventario::buscar($id);
    if (!$item || !Esteira::dentroDoEscopo((string) $item['ficha_codigo'])) {
        return null;
    }
    return $item;
};

if ($acao === 'enviar') {
    $item = $itemEmColeta($itemId) ?? responder(['ok' => false, 'motivo' => 'item'], 404);
    if (!Auth::pode('coleta.editar') && !Auth::pode('inventario.editar')) {
        responder(['ok' => false, 'motivo' => 'permissao'], 403);
    }
    $r = in_array($item['situacao'], Esteira::EM_COLETA, true)
        ? (Esteira::enviar($itemId) ? ['ok' => true, 'item_id' => $itemId, 'situacao' => 'em_conferencia'] : ['ok' => false, 'motivo' => 'situacao'])
        : ['ok' => true, 'item_id' => $itemId, 'situacao' => $item['situacao'], 'aviso' => 'já não estava em coleta'];
    $registrar('item', $itemId, $r);
    responder($r);
}

if ($acao !== 'salvar') {
    responder(['ok' => false, 'motivo' => 'acao'], 400);
}

// ------------------------------------------------------------------ SALVAR
$d = (array) ($corpo['dados'] ?? []);
$txt = fn (string $c, int $max) => ($v = mb_substr(trim((string) ($d[$c] ?? '')), 0, $max)) !== '' ? $v : null;
$num = fn (string $c) => isset($d[$c]) && is_numeric($d[$c]) ? (float) $d[$c] : null;

if ($itemId) {
    $item = $itemEmColeta($itemId) ?? responder(['ok' => false, 'motivo' => 'item'], 404);
    if (!Auth::pode('coleta.editar') && !Auth::pode('inventario.editar')) {
        responder(['ok' => false, 'motivo' => 'permissao'], 403);
    }
    if (!in_array($item['situacao'], Esteira::EM_COLETA, true)) {
        // Enviado ou homologado enquanto o celular estava sem sinal: não
        // sobrescreve; a pessoa fala com quem coordena.
        $r = ['ok' => false, 'motivo' => 'situacao', 'situacao' => $item['situacao'], 'item_id' => $itemId];
        $registrar('item', $itemId, $r);
        responder($r);
    }
    $fichaId = (int) $item['ficha_id'];
} else {
    if (!Auth::pode('coleta.criar') && !Auth::pode('inventario.criar')) {
        responder(['ok' => false, 'motivo' => 'permissao'], 403);
    }
    $ficha = Inventario::ficha((int) ($corpo['ficha_id'] ?? 0));
    if (!$ficha || !Esteira::dentroDoEscopo((string) $ficha['codigo'])) {
        responder(['ok' => false, 'motivo' => 'ficha'], 400);
    }
    $fichaId = (int) $ficha['id'];
    $item = null;
}

$campos = [];
if (($nome = $txt('nome', 180)) !== null) {
    $campos['nome'] = $nome;
}
$tipo = (int) ($d['tipo_id'] ?? 0);
if ($tipo && in_array($tipo, array_map('intval', array_column(Inventario::tipos($fichaId), 'id')), true)) {
    $campos['tipo_id'] = $tipo;
}
foreach (['logradouro' => 200, 'numero' => 20, 'bairro' => 120, 'referencia' => 255, 'site' => 255] as $c => $max) {
    if (array_key_exists($c, $d)) {
        $campos[$c] = $txt($c, $max);
    }
}
if (array_key_exists('instagram', $d)) {
    $campos['instagram'] = ($ig = ltrim((string) $txt('instagram', 100), '@')) !== '' ? $ig : null;
}
foreach (['telefone', 'whatsapp'] as $c) {
    if (array_key_exists($c, $d)) {
        $campos[$c] = telefoneParaBanco($d[$c] ?: null);
    }
}
if (array_key_exists('zona', $d)) {
    $campos['zona'] = array_key_exists((string) $d['zona'], Inventario::ZONAS) ? (string) $d['zona'] : null;
}
if (array_key_exists('conservacao', $d)) {
    $campos['conservacao'] = array_key_exists((string) $d['conservacao'], Inventario::CONSERVACAO) ? (string) $d['conservacao'] : null;
}
if (array_key_exists('plus_code', $d)) {
    $campos['plus_code'] = PlusCode::valido($pc = strtoupper((string) $txt('plus_code', 16))) ? $pc : null;
}
$lat = $num('latitude');
$lng = $num('longitude');
if ($lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180) {
    [$campos['latitude'], $campos['longitude']] = [round($lat, 6), round($lng, 6)];
} elseif (!empty($campos['plus_code']) && ($item === null || $item['latitude'] === null)) {
    $ref = $pdo->prepare('SELECT m.latitude, m.longitude FROM empresas e JOIN municipios m ON m.id = e.municipio_id WHERE e.id = ?');
    $ref->execute([$eid]);
    $r = $ref->fetch() ?: [];
    if ($c = PlusCode::coordenada($campos['plus_code'], isset($r['latitude']) ? (float) $r['latitude'] : null, isset($r['longitude']) ? (float) $r['longitude'] : null)) {
        [$campos['latitude'], $campos['longitude']] = $c;
    }
}
$nota = $txt('nota', 2000);
// Onde o pesquisador estava ao gravar (vai ao histórico, não ao item).
$aqui = (array) ($corpo['aqui'] ?? []);
$aquiLat = isset($aqui[0]) && is_numeric($aqui[0]) ? (float) $aqui[0] : null;
$aquiLng = isset($aqui[1]) && is_numeric($aqui[1]) ? (float) $aqui[1] : null;
$quando = date('d/m/Y H:i');

$pdo->beginTransaction();
try {
    if ($item) {
        $sets = [];
        $args = [];
        foreach ($campos as $c => $v) {
            $sets[] = "{$c} = ?";
            $args[] = $v;
        }
        if ($nota !== null) {
            $sets[] = "observacoes = CONCAT_WS('\n', observacoes, ?)";
            $args[] = "Campo {$quando}: {$nota}";
        }
        $sets[] = "situacao = 'em_campo'";
        $sets[] = 'coletado_em = NOW()';
        $sets[] = 'pesquisador_id = COALESCE(pesquisador_id, ?)';
        $args[] = $uid;
        $pdo->prepare('UPDATE inventario_itens SET ' . implode(', ', $sets) . ', atualizado_em = NOW(), atualizado_por = ? WHERE id = ? AND empresa_id = ?')
            ->execute([...$args, $uid, $itemId, $eid]);
        Inventario::registrarHistorico($itemId, 'alterou', $item['situacao'] !== 'em_campo' ? $item['situacao'] : null,
            $item['situacao'] !== 'em_campo' ? 'em_campo' : null, 'coleta em campo', $aquiLat, $aquiLng);
    } else {
        if (empty($campos['nome'])) {
            throw new RuntimeException('nome');
        }
        $campos += ['empresa_id' => $eid, 'ficha_id' => $fichaId, 'situacao' => 'em_campo', 'origem' => 'campo', 'pesquisador_id' => $uid,
                    'observacoes' => $nota !== null ? "Campo {$quando}: {$nota}" : null];
        $cols = array_keys($campos);
        $pdo->prepare('INSERT INTO inventario_itens (' . implode(', ', $cols) . ', coletado_em, criado_em, criado_por)
                       VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ', NOW(), NOW(), ?)')
            ->execute([...array_values($campos), $uid]);
        $itemId = (int) $pdo->lastInsertId();
        Inventario::registrarHistorico($itemId, 'criou', null, 'em_campo', 'coleta em campo', $aquiLat, $aquiLng);
    }
    $r = ['ok' => true, 'item_id' => $itemId, 'situacao' => 'em_campo'];
    $registrar('item', $itemId, $r);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responder(['ok' => false, 'motivo' => $e instanceof RuntimeException ? $e->getMessage() : 'erro'], $e instanceof RuntimeException ? 400 : 500);
}
Auditoria::registrar($item ? 'alterou' : 'criou', 'inventario_itens', $itemId, 'coleta em campo: ' . ($campos['nome'] ?? $item['nome'] ?? ''));
responder($r);
