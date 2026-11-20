<?php
/**
 * Gera a migração do catálogo das fichas a partir da especificação completa
 * de cada ficha oficial do MTur 2011 (docs/especificacao/fichas-2011/NN.json).
 * Softsul Software
 *
 *   php scripts/gerar_migracao_fichas.php docs/especificacao/fichas-2011 migracoes/016-fichas-completas.sql
 *
 * Cada especificação lista TODOS os itens numerados do PDF, cada um com
 * destino: núcleo, campo existente (com ajustes), campo novo, título ou
 * ignorado (fax, cheque, cabeçalho). Daqui sai SQL que:
 *   - ajusta os campos existentes (rótulo, grupo, tipo, opções, config);
 *   - cria os campos novos;
 *   - desliga os que não correspondem à ficha (ativo = 0, nada é apagado);
 *   - refaz a ordem de todos os campos da ficha na ordem do PDF.
 * Confira cada especificação com scripts/validar_fichas.php antes.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';

$pasta = rtrim($argv[1] ?? '', '/');
$saida = $argv[2] ?? '';
if (!is_dir($pasta) || $saida === '') {
    fwrite(STDERR, "uso: php scripts/gerar_migracao_fichas.php <pasta das especificações> <arquivo .sql>\n");
    exit(1);
}
$pdo = Database::conexao();
$q = fn (?string $v): string => $v === null ? 'NULL' : $pdo->quote($v);
$j = fn ($v): string => $v === null ? 'NULL' : $pdo->quote(json_encode($v, JSON_UNESCAPED_UNICODE));
$TIPOS = ['texto', 'texto_longo', 'numero', 'sim_nao', 'escolha', 'multipla', 'tabela', 'data'];

/** A configuração do campo (outros, maximo, colunas tipadas, linhas). */
$config = function (array $d, array $atual = []) {
    $c = $atual;
    foreach (['outros', 'maximo', 'linhas'] as $k) {
        if (array_key_exists($k, $d)) {
            $c[$k] = $d[$k];
        }
    }
    if (array_key_exists('colunas', $d)) {
        $tipadas = array_filter((array) $d['colunas'], 'is_array');
        $c['colunas'] = $tipadas ? array_map(fn ($x) => is_array($x) ? $x + ['tipo' => 'texto'] : ['rotulo' => (string) $x, 'tipo' => 'texto'], array_values((array) $d['colunas'])) : null;
    }
    $c = array_filter($c, fn ($v) => $v !== null && $v !== false && $v !== [] && $v !== 0);
    return $c ?: null;
};
/** As opções de uma tabela são os nomes das colunas. */
$opcoesDe = function (array $d): ?array {
    if (array_key_exists('colunas', $d)) {
        return array_map(fn ($x) => is_array($x) ? (string) $x['rotulo'] : (string) $x, array_values((array) $d['colunas']));
    }
    return array_key_exists('opcoes', $d) ? array_values((array) $d['opcoes']) : null;
};

$sql = "-- InvenTour: o catálogo das 54 fichas completo, item a item do PDF oficial (MTur 2011).\n"
     . "-- Softsul Software. Gerado por scripts/gerar_migracao_fichas.php a partir de\n"
     . "-- docs/especificacao/fichas-2011 em " . date('d/m/Y') . ". Não editar à mão.\n\nSTART TRANSACTION;\n\n";
$tot = ['ajustados' => 0, 'novos' => 0, 'desligados' => 0];
foreach (glob("{$pasta}/*.json") ?: [] as $arq) {
    $s = json_decode((string) file_get_contents($arq), true, 512, JSON_THROW_ON_ERROR);
    $ordemFicha = (int) $s['ordem'];
    $fichaId = (int) $pdo->query("SELECT id FROM inventario_fichas WHERE ordem = {$ordemFicha}")->fetchColumn();
    if (!$fichaId) {
        throw new RuntimeException("ficha de ordem {$ordemFicha} não existe");
    }
    $atuais = [];
    $st = $pdo->prepare('SELECT * FROM inventario_atributos WHERE ficha_id = ?');
    $st->execute([$fichaId]);
    foreach ($st as $a) {
        $atuais[(int) $a['id']] = $a;
    }
    $sql .= "-- Ficha {$s['codigo']} (ordem {$ordemFicha}), {$s['pdf']}\n";
    // Fase 1: libera as chaves. O campo desligado ganha o sufixo _inativo_<id>
    // e o renomeado passa por uma chave provisória, para nenhum novo colidir.
    $usadas = [];
    foreach ($atuais as $id => $a) {
        $usadas[(string) $a['chave']] = $id;
    }
    $desligar = [];
    foreach ($s['desativar'] ?? [] as $d) {
        $id = (int) $d['atributo_id'];
        isset($atuais[$id]) || throw new RuntimeException("{$s['codigo']}: desativar {$id} não é da ficha");
        $desligar[$id] = (string) ($d['motivo'] ?? '');
        $sql .= "UPDATE inventario_atributos SET ativo = 0, chave = " . $q(mb_substr($atuais[$id]['chave'] . '_inativo_' . $id, 0, 80)) . " WHERE id = {$id}; -- "
            . str_replace(["\n", '--'], ' ', $desligar[$id]) . "\n";
        unset($usadas[(string) $atuais[$id]['chave']]);
        $tot['desligados']++;
    }
    foreach ($s['itens'] as $it) {
        if (($it['destino'] ?? '') === 'atributo' && isset($it['ajustes']['chave']) && $it['ajustes']['chave'] !== $atuais[(int) $it['atributo_id']]['chave']) {
            $id = (int) $it['atributo_id'];
            $sql .= "UPDATE inventario_atributos SET chave = 'tmp_{$id}' WHERE id = {$id};\n";
            unset($usadas[(string) $atuais[$id]['chave']]);
        }
    }
    // As chaves finais não podem bater com campo que ficou como está (inclusive os inativos de antes).
    $finais = [];
    foreach ($s['itens'] as $it) {
        $d = $it['destino'] ?? '';
        $c = $d === 'novo' ? (string) $it['novo']['chave'] : ($d === 'atributo' && isset($it['ajustes']['chave']) ? (string) $it['ajustes']['chave'] : null);
        if ($c === null || ($d === 'atributo' && $c === $atuais[(int) $it['atributo_id']]['chave'])) {
            continue;
        }
        if (isset($usadas[$c]) || isset($finais[$c])) {
            throw new RuntimeException("{$s['codigo']}: a chave {$c} já existe na ficha (atributo " . ($usadas[$c] ?? $finais[$c]) . ')');
        }
        $finais[$c] = 'novo';
    }
    $ordem = 0;
    foreach ($s['itens'] as $it) {
        $destino = $it['destino'] ?? '';
        if ($destino === 'atributo') {
            $id = (int) $it['atributo_id'];
            $a = $atuais[$id] ?? throw new RuntimeException("{$s['codigo']}: atributo {$id} não é da ficha");
            $ordem += 10;
            $aj = (array) ($it['ajustes'] ?? []);
            $sets = ["ordem = {$ordem}", 'ativo = 1'];
            foreach (['chave' => 80, 'rotulo' => 200, 'grupo' => 120, 'ajuda' => 500] as $c => $max) {
                if (array_key_exists($c, $aj)) {
                    $sets[] = "{$c} = " . $q($aj[$c] === null ? null : mb_substr((string) $aj[$c], 0, $max));
                }
            }
            if (isset($aj['tipo'])) {
                in_array($aj['tipo'], $TIPOS, true) || throw new RuntimeException("tipo {$aj['tipo']}");
                $sets[] = 'tipo = ' . $q($aj['tipo']);
            }
            if (($op = $opcoesDe($aj)) !== null) {
                $sets[] = 'opcoes = ' . $j($op);
            }
            $cfgAtual = json_decode((string) ($a['config'] ?? ''), true) ?: [];
            if (array_intersect(['outros', 'maximo', 'linhas', 'colunas'], array_keys($aj))) {
                $sets[] = 'config = ' . $j($config($aj, $cfgAtual));
            }
            if (count($sets) > 2) {
                $tot['ajustados']++;
            }
            $sql .= 'UPDATE inventario_atributos SET ' . implode(', ', $sets) . " WHERE id = {$id};\n";
        } elseif ($destino === 'novo') {
            $n = (array) $it['novo'];
            in_array($n['tipo'], $TIPOS, true) || throw new RuntimeException("tipo {$n['tipo']}");
            $ordem += 10;
            $tot['novos']++;
            $sql .= 'INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, config, ajuda, obrigatorio, portal, ativo, ordem) VALUES ('
                . implode(', ', [$fichaId, $q(mb_substr((string) $n['grupo'], 0, 120)), $q(mb_substr((string) $n['chave'], 0, 80)),
                    $q(mb_substr((string) $n['rotulo'], 0, 200)), $q($n['tipo']), $j($opcoesDe($n)), $j($config($n)),
                    $q(isset($n['ajuda']) ? mb_substr((string) $n['ajuda'], 0, 500) : null), !empty($n['obrigatorio']) ? 1 : 0,
                    !empty($n['portal']) ? 1 : 0, 1, $ordem]) . ");\n";
        }
    }
    $sql .= "\n";
}
file_put_contents($saida, $sql . "COMMIT;\n");
echo "Gerado {$saida}: {$tot['ajustados']} campos ajustados, {$tot['novos']} novos, {$tot['desligados']} desligados.\n";
