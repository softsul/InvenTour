<?php
/**
 * API da tela de campo (/coleta).
 * Softsul Software
 *
 *   GET  /api/coleta            o que levar para o campo: fichas do escopo,
 *                               com tipos, o catálogo da ficha completa
 *                               (campos específicos, acessibilidade, tribos)
 *                               e os itens em coleta, cada um com a ficha
 *                               inteira em f (o formato do formulário)
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
require_once __DIR__ . '/../../app/FichaFormulario.php';
require_once __DIR__ . '/../../app/AreaDestino.php';

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
// Com a versão publicada do app: o app antigo se vê e pede para atualizar.
$versaoApp = function (): ?array {
    $j = json_decode((string) @file_get_contents(__DIR__ . '/../baixar/versao-inventour-campo.json'), true);
    return is_array($j) && !empty($j['versionCode'])
        ? ['codigo' => (int) $j['versionCode'], 'nome' => (string) ($j['versionName'] ?? ''), 'android' => 'https://app.inventour.com.br/baixar/inventour-campo.apk']
        : null;
};
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ping'])) {
    responder(['ok' => true, 'em' => date('c'), 'app' => $versaoApp()]);
}

// Ao sair do campo CNPJ ou Cadastur na ficha (com internet): os dados da
// Receita, o Cadastur (cópia local dos dados abertos do MTur) e se o CNPJ já
// está no inventário do destino. Limite próprio: a Receita é serviço externo.
//   GET /api/coleta?cnpj=12345678000190[&ficha=20&item=5]
//   GET /api/coleta?cadastur=12345678000190
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['cnpj']) || isset($_GET['cadastur']))) {
    require_once __DIR__ . '/../../app/Cnpj.php';
    require_once __DIR__ . '/../../app/Cadastur.php';
    $cadastur = function (array $registros): array {
        return array_map(fn ($r) => ['atividade' => (string) $r['atividade'], 'situacao' => Cadastur::situacao($r), 'vigente' => Cadastur::vigente($r),
            'certificado' => (string) ($r['certificado'] ?? ''), 'validade' => $r['validade'] ? (string) $r['validade'] : null,
            'nome' => (string) ($r['nome_fantasia'] ?: $r['nome_juridico']), 'documento' => (string) $r['documento']], $registros);
    };
    if (isset($_GET['cadastur'])) {
        Limite::porUsuario('coleta-cadastur', 30, 60);
        responder(['ok' => true, 'cadastur' => $cadastur(Cadastur::porCertificado((string) $_GET['cadastur']))]);
    }
    Limite::porUsuario('coleta-cnpj', 20, 60);
    $cnpj = Cnpj::normalizar((string) $_GET['cnpj']);
    if (!Cnpj::valido($cnpj)) {
        responder(['ok' => false, 'motivo' => 'invalido', 'erro' => 'CNPJ inválido. Confira os dígitos.']);
    }
    // Já no inventário deste destino, na mesma ficha (o painel recusa igual).
    $dup = $pdo->prepare("SELECT id, nome FROM inventario_itens WHERE empresa_id = ? AND cnpj = ? AND ficha_id = ? AND id <> ? AND situacao <> 'arquivada' LIMIT 1");
    $dup->execute([$eid, $cnpj, (int) ($_GET['ficha'] ?? 0), (int) ($_GET['item'] ?? 0)]);
    $r = Cnpj::consultar($cnpj);
    responder(['ok' => true, 'receita' => $r['ok'] ? $r['dados'] + ['fonte' => $r['fonte']] : null, 'erro_receita' => $r['ok'] ? null : $r['erro'],
               'cadastur' => $cadastur(Cadastur::consultar($cnpj)), 'existente' => $dup->fetch() ?: null]);
}

// ------------------------------------------------------------------ DADOS
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    [$fe, $ae] = Esteira::filtroEscopo();
    $q = $pdo->prepare("SELECT f.id, f.codigo, f.nome, f.categoria, f.forma, f.eventcity, f.ajuda FROM inventario_fichas f WHERE f.ativo = 1{$fe} ORDER BY f.ordem");
    $q->execute($ae);
    $fichas = [];
    foreach ($q->fetchAll() as $f) {
        $fichas[] = ['id' => (int) $f['id'], 'codigo' => $f['codigo'], 'nome' => $f['nome'], 'categoria' => $f['categoria'], 'forma' => $f['forma'],
                     'capacidade' => in_array($f['eventcity'], ['espaco_eventos', 'atrativo'], true), 'ajuda' => $f['ajuda'] ?: null,
                     'tipos' => array_map(fn ($t) => ['id' => (int) $t['id'], 'nome' => ($t['pai_nome'] ? $t['pai_nome'] . ': ' : '') . $t['nome']],
                         Inventario::tipos((int) $f['id']))];
    }
    $q = $pdo->prepare("SELECT i.*, f.codigo AS ficha_codigo,
                               (SELECT COUNT(*) FROM inventario_midias m WHERE m.item_id = i.id AND m.experiencia_id IS NULL) AS fotos
                        FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                        WHERE i.empresa_id = ? AND i.situacao IN ('pre_cadastro', 'em_campo'){$fe}
                        ORDER BY i.devolucao_nota IS NULL, i.nome");
    $q->execute([$eid, ...$ae]);
    $linhas = $q->fetchAll();
    $fichasDosItens = FichaFormulario::paraOAparelho($linhas);
    // As fotos que já estão no servidor, para o item mostrar as miniaturas
    // (e não só a conta). A capa primeiro, depois na ordem da ficha.
    $galeria = [];
    if ($linhas) {
        $ids = array_map(fn ($i) => (int) $i['id'], $linhas);
        $m = $pdo->prepare('SELECT item_id, arquivo, capa, latitude, fora_area FROM inventario_midias
                            WHERE empresa_id = ? AND experiencia_id IS NULL AND item_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
                            ORDER BY item_id, capa DESC, ordem, id');
        $m->execute([$eid, ...$ids]);
        foreach ($m->fetchAll() as $f) {
            $galeria[(int) $f['item_id']][] = [
                'm' => Inventario::urlFoto($eid, (string) $f['arquivo'], 'm'), 'g' => Inventario::urlFoto($eid, (string) $f['arquivo']),
                'capa' => (bool) $f['capa'], 'gps' => $f['latitude'] !== null, 'fora' => (bool) $f['fora_area'],
            ];
        }
    }
    // Na lista, só o que ela mostra; a ficha inteira vai em f.
    $itens = array_map(function ($i) use ($fichasDosItens, $galeria) {
        return ['id' => (int) $i['id'], 'ficha_id' => (int) $i['ficha_id'], 'situacao' => $i['situacao'], 'nome' => $i['nome'],
                'logradouro' => $i['logradouro'], 'numero' => $i['numero'], 'bairro' => $i['bairro'],
                'latitude' => $i['latitude'] === null ? null : (float) $i['latitude'], 'longitude' => $i['longitude'] === null ? null : (float) $i['longitude'],
                'devolucao_nota' => $i['devolucao_nota'], 'ficha_codigo' => $i['ficha_codigo'], 'fotos' => (int) $i['fotos'],
                'galeria' => $galeria[(int) $i['id']] ?? [],
                'f' => (object) ($fichasDosItens[(int) $i['id']] ?? [])];
    }, $linhas);
    $empresa = Empresa::atual();
    $u = Auth::usuarioOuNulo();
    responder(['ok' => true, 'destino' => $empresa['nome_fantasia'] ?? '', 'usuario' => $u['nome'] ?? '', 'em' => date('c'),
               'csrf' => Auth::porAparelho() ? null : Auth::tokenCsrf(),
               'pode_criar' => Auth::pode('coleta.criar') || Auth::pode('inventario.criar'), 'fichas' => $fichas, 'itens' => $itens,
               'catalogo' => FichaFormulario::catalogo(array_column($fichas, 'id')), 'formato' => 2,
               // A área do destino, para o app conferir GPS e fotos sem sinal.
               'area' => AreaDestino::doDestino()]);
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
// A identificação do item no aparelho, enquanto ele não tem número.
$localId = strtolower((string) ($corpo['local_id'] ?? ''));
$localId = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $localId) ? $localId : null;
$registrar = function (string $tipo, ?int $itemId, array $resultado) use ($pdo, $eid, $uuid, $uid, $localId): void {
    $pdo->prepare('INSERT INTO inventario_coletas (empresa_id, uuid, local_id, tipo, usuario_id, aparelho_id, item_id, resultado, recebido_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$eid, $uuid, $localId, $tipo, $uid, Auth::aparelhoId(), $itemId, json_encode($resultado, JSON_UNESCAPED_UNICODE)]);
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

// Gravação de um item novo que o aparelho editou de novo antes de ter o
// número: o item que aquela identificação local já criou. Para gravações
// presas de antes desta correção (sem local_id guardado), o item com o mesmo
// nome, na mesma ficha, criado em campo pela mesma pessoa, se for um só.
if (!$itemId && !isset($d['nome'])) {
    if ($localId) {
        $q = $pdo->prepare('SELECT item_id FROM inventario_coletas WHERE empresa_id = ? AND local_id = ? AND item_id IS NOT NULL ORDER BY id LIMIT 1');
        $q->execute([$eid, $localId]);
        $itemId = (int) $q->fetchColumn();
    }
    $nomeLocal = mb_substr(trim((string) ($corpo['nome'] ?? '')), 0, 180);
    if (!$itemId && $nomeLocal !== '') {
        $q = $pdo->prepare("SELECT id FROM inventario_itens WHERE empresa_id = ? AND ficha_id = ? AND nome = ? AND origem = 'campo' AND criado_por = ?
                            AND criado_em > NOW() - INTERVAL 30 DAY LIMIT 2");
        $q->execute([$eid, (int) ($corpo['ficha_id'] ?? 0), $nomeLocal, $uid]);
        $achados = $q->fetchAll(PDO::FETCH_COLUMN);
        $itemId = count($achados) === 1 ? (int) $achados[0] : 0;
    }
}

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

// A ficha completa, no formato do formulário do painel; só o que veio (o
// aparelho manda o que mudou). Valor inválido (CNPJ, e-mail, telefone) não
// derruba a gravação feita sem sinal: fica de fora e volta como aviso.
$fichaRow = Inventario::ficha($fichaId) ?? responder(['ok' => false, 'motivo' => 'ficha'], 400);
$lido = FichaFormulario::ler($d, $fichaRow, true, $item['horario'] ?? null);
$campos = array_diff_key($lido['nucleo'], $lido['erros']);
if (array_key_exists('nome', $campos) && $campos['nome'] === '') {
    unset($campos['nome']);
}
// A observação vai pela nota de campo (abaixo), que soma em vez de trocar.
unset($campos['observacoes']);
$avisos = array_values($lido['erros']);
$alertas = $lido['alertas'];
$lat = $campos['latitude'] ?? null;
$lng = $campos['longitude'] ?? null;
unset($campos['latitude'], $campos['longitude']);
if ($lat !== null && $lng !== null) {
    [$campos['latitude'], $campos['longitude']] = [round($lat, 6), round($lng, 6)];
} elseif (!empty($campos['plus_code']) && ($item === null || $item['latitude'] === null)) {
    $ref = $pdo->prepare('SELECT m.latitude, m.longitude FROM empresas e JOIN municipios m ON m.id = e.municipio_id WHERE e.id = ?');
    $ref->execute([$eid]);
    $r = $ref->fetch() ?: [];
    if ($c = PlusCode::coordenada($campos['plus_code'], isset($r['latitude']) ? (float) $r['latitude'] : null, isset($r['longitude']) ? (float) $r['longitude'] : null)) {
        $area = AreaDestino::conferir((float) $c[0], (float) $c[1]);
        if (!$area['dentro'] && AreaDestino::bloqueia()) {
            // O destino recusa ponto fora da área: o plus code fica sem coordenada.
            $avisos[] = 'O plus code aponta para um lugar ' . AreaDestino::descrever($area) . '. Este destino só aceita pontos dentro da sua área.';
        } else {
            [$campos['latitude'], $campos['longitude']] = $c;
            if (!$area['dentro']) {
                $alertas[] = 'O plus code aponta para um lugar ' . AreaDestino::descrever($area) . '. Confira se é mesmo o lugar do item.';
            }
        }
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
    $capacidades = FichaFormulario::gravarParcial($itemId, $fichaRow, $lido['especificos'], $lido['acess'], $lido['facilidades'], $lido['acess_outro']);
    if ($capacidades) {
        $pdo->prepare('UPDATE inventario_itens SET ' . implode(' = ?, ', array_keys($capacidades)) . ' = ? WHERE id = ? AND empresa_id = ?')
            ->execute([...array_values($capacidades), $itemId, $eid]);
    }
    $texto = trim(($avisos ? 'Ficou de fora: ' . implode(' ', $avisos) . ' ' : '') . ($alertas ? 'Atenção: ' . implode(' ', $alertas) : ''));
    $r = ['ok' => true, 'item_id' => $itemId, 'situacao' => 'em_campo'] + ($texto !== '' ? ['aviso' => $texto] : []);
    $registrar('item', $itemId, $r);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responder(['ok' => false, 'motivo' => $e instanceof RuntimeException ? $e->getMessage() : 'erro'], $e instanceof RuntimeException ? 400 : 500);
}
Auditoria::registrar($item ? 'alterou' : 'criou', 'inventario_itens', $itemId, 'coleta em campo: ' . ($campos['nome'] ?? $item['nome'] ?? ''));
responder($r);
