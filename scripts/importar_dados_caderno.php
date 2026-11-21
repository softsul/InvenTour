<?php
/**
 * Completa as fichas do inventário com o que um caderno já publicado traz
 * além dos quadros: site, e-mail, razão social, CNPJ, início de atividade,
 * descrição, capacidade, campos próprios da ficha e regras (tarifas,
 * serviços, horário de atendimento).
 * Softsul Software
 *
 *   php scripts/importar_dados_caderno.php <empresa_id> [--gravar] < dados.json
 *
 * A entrada é uma lista JSON de {"item": id, "nucleo": {coluna: valor},
 * "attr": {atributo_id: {"numero"|"opcoes"|"outro"}}, "regras": [texto]}.
 * Só preenche o que está vazio: o que o pesquisador já anotou fica como
 * está. As regras vão para "Outras regras e informações" da ficha (ou para
 * as observações, se a ficha não tem esse campo). Sem --gravar, só mostra o
 * que faria. Feito para o caderno de Pato Branco 2025 (21/11/2026).
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auditoria.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$eid = (int) ($argv[1] ?? 0);
$gravar = in_array('--gravar', $argv, true);
$lista = json_decode((string) stream_get_contents(STDIN), true);
if (!$eid || !is_array($lista)) {
    fwrite(STDERR, "Uso: php scripts/importar_dados_caderno.php <empresa_id> [--gravar] < dados.json\n");
    exit(1);
}
const COLUNAS = ['site', 'email', 'instagram', 'razao_social', 'cnpj', 'inicio_atividade_data', 'rede', 'natureza', 'descricao_tecnica',
                 'descricao_promocional', 'capacidade_uh', 'capacidade_leitos', 'capacidade_assentos', 'capacidade_publico', 'observacoes'];
const ORIGEM = 'Importado do Inventário 2025';

$pdo = Database::conexao();
$buscaItem = $pdo->prepare("SELECT * FROM inventario_itens WHERE id = ? AND empresa_id = ? AND situacao <> 'arquivada'");
$attrs = $pdo->prepare('SELECT id, chave, tipo, opcoes FROM inventario_atributos WHERE ficha_id = ? AND ativo = 1');
$jaTem = $pdo->prepare('SELECT atributo_id FROM inventario_item_atributos WHERE item_id = ? AND empresa_id = ?');
$insAttr = $pdo->prepare('INSERT INTO inventario_item_atributos (item_id, atributo_id, empresa_id, valor_texto, valor_numero, valor_outro, ausencia)
                          VALUES (?, ?, ?, ?, ?, ?, NULL)');
$hist = $pdo->prepare("INSERT INTO inventario_item_historico (item_id, empresa_id, usuario_id, acao, de, para, nota, criado_em)
                       VALUES (?, ?, NULL, 'alterou', NULL, NULL, ?, NOW())");
$vazio = fn ($v) => $v === null || $v === '' || $v === '0' || $v === 0;
[$fichas, $campos, $especificos] = [0, 0, 0];

foreach ($lista as $d) {
    $buscaItem->execute([(int) $d['item'], $eid]);
    $item = $buscaItem->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        echo "  item {$d['item']} não existe no destino ou está arquivado\n";
        continue;
    }
    $nucleo = array_intersect_key((array) ($d['nucleo'] ?? []), array_flip(COLUNAS));
    $tecnica = (string) ($nucleo['descricao_tecnica'] ?? '');
    // O portal mostra a descrição promocional: sem ela, vale o texto importado
    // (menos as listas de "Sistemas:" dos auditórios, que são só técnicas).
    if ($tecnica !== '' && !str_starts_with($tecnica, 'Sistemas:')) {
        $nucleo['descricao_promocional'] = $tecnica;
    }
    $muda = [];
    foreach ($nucleo as $col => $v) {
        if ($vazio($item[$col]) && !$vazio($v)) {
            $muda[$col] = is_string($v) ? mb_substr(trim($v), 0, in_array($col, ['descricao_tecnica', 'descricao_promocional', 'observacoes'], true) ? 10000 : 255) : $v;
        }
    }
    // os campos próprios da ficha
    $attrs->execute([(int) $item['ficha_id']]);
    $catalogo = [];
    foreach ($attrs->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $a['opcoes'] = json_decode((string) $a['opcoes'], true) ?: [];
        $catalogo[(int) $a['id']] = $a;
        $porChave[(int) $item['ficha_id']][$a['chave']] = (int) $a['id'];
    }
    $jaTem->execute([(int) $item['id'], $eid]);
    $existentes = array_map('intval', $jaTem->fetchAll(PDO::FETCH_COLUMN));
    $novos = [];
    $regras = array_values(array_filter((array) ($d['regras'] ?? []), 'is_string'));
    if ($regras) {
        $texto = ORIGEM . ":\n" . implode("\n", $regras);
        $aidRegras = $porChave[(int) $item['ficha_id']]['outras_regras'] ?? null;
        if ($aidRegras) {
            $d['attr'][$aidRegras] = ['texto' => $texto];
        } elseif ($vazio($item['observacoes'])) {
            $muda['observacoes'] = $texto;
        }
    }
    foreach ((array) ($d['attr'] ?? []) as $aid => $v) {
        $aid = (int) $aid;
        $a = $catalogo[$aid] ?? null;
        if (!$a || in_array($aid, $existentes, true)) {
            continue;
        }
        [$texto, $numero, $outro] = [null, null, null];
        if ($a['tipo'] === 'numero' && isset($v['numero'])) {
            $numero = (float) $v['numero'];
        } elseif ($a['tipo'] === 'multipla') {
            $marcadas = array_values(array_intersect($a['opcoes'], (array) ($v['opcoes'] ?? [])));
            $texto = $marcadas ? json_encode($marcadas, JSON_UNESCAPED_UNICODE) : null;
            $outro = $texto && array_intersect(['Outros', 'Outras'], $marcadas) ? ($v['outro'] ?? null) : null;
        } elseif ($a['tipo'] === 'escolha' && in_array($v['opcoes'] ?? null, $a['opcoes'], true)) {
            $texto = (string) $v['opcoes'];
        } elseif (in_array($a['tipo'], ['texto', 'texto_longo'], true) && !empty($v['texto'])) {
            $texto = mb_substr((string) $v['texto'], 0, $a['tipo'] === 'texto_longo' ? 10000 : 500);
        }
        if ($texto !== null || $numero !== null) {
            $novos[$aid] = [$a['chave'], $texto, $numero, $outro];
        }
    }
    if (!$muda && !$novos) {
        continue;
    }
    $fichas++;
    $campos += count($muda);
    $especificos += count($novos);
    echo ($gravar ? '+ ' : '  ') . "#{$item['id']} {$item['nome']}: " . implode(', ', array_merge(array_keys($muda), array_column($novos, 0))) . "\n";
    if (!$gravar) {
        continue;
    }
    $pdo->beginTransaction();
    try {
        if ($muda) {
            $pdo->prepare('UPDATE inventario_itens SET ' . implode(' = ?, ', array_keys($muda)) . ' = ?, atualizado_em = NOW() WHERE id = ? AND empresa_id = ?')
                ->execute([...array_values($muda), (int) $item['id'], $eid]);
        }
        foreach ($novos as $aid => [, $texto, $numero, $outro]) {
            $insAttr->execute([(int) $item['id'], $aid, $eid, $texto, $numero, $outro]);
        }
        $hist->execute([(int) $item['id'], $eid, 'Completado com os dados importados do Inventário 2025 (só campos vazios).']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "  erro em #{$item['id']}: {$e->getMessage()}\n";
        continue;
    }
    Auditoria::registrar('alterou', 'inventario_itens', (int) $item['id'], 'completou ' . $item['nome'] . ' com a importação do Inventário 2025',
        array_intersect_key($item, $muda) ?: null,
        $muda + ($novos ? ['campos_da_ficha' => array_combine(array_column($novos, 0), array_map(fn ($n) => $n[1] ?? $n[2], $novos))] : []), $eid);
}
printf("%s: %d fichas, %d campos do núcleo, %d campos próprios da ficha.\n", $gravar ? 'Gravado' : 'Simulação', $fichas, $campos, $especificos);
