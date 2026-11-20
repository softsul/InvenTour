<?php
/**
 * Gera a migração com os campos específicos de cada ficha do MTur.
 * Softsul Software
 *
 *   php scripts/gerar_seed_atributos.php [docs/especificacao/atributos-2011.json] [migracoes/009-atributos-das-fichas.sql]
 *
 * O JSON foi extraído em 20/11/2026 dos PDFs oficiais de 2011, ficha a
 * ficha (seção 3, Características, e os campos próprios das seções 1 e 2).
 * A ficha é referida pela ordem na capitulação (inventario_fichas.ordem).
 * Opções anacrônicas dentro de listas ativas (fax, cheque, 3G) ficam em
 * opcoes_inativas: guardadas para importação antiga, fora do formulário.
 */
declare(strict_types=1);

$raiz = dirname(__DIR__);
$json = $argv[1] ?? $raiz . '/docs/especificacao/atributos-2011.json';
$saida = $argv[2] ?? $raiz . '/migracoes/009-atributos-das-fichas.sql';
$campos = json_decode((string) file_get_contents($json), true, 512, JSON_THROW_ON_ERROR);

$tipos = ['texto', 'texto_longo', 'numero', 'sim_nao', 'escolha', 'multipla', 'tabela'];
$s = fn (?string $v): string => $v === null || trim($v) === ''
    ? 'NULL'
    : "'" . str_replace(["\\", "'"], ["\\\\", "''"], trim(preg_replace('/\s+/u', ' ', str_replace(["\u{2013}", "\u{2014}"], ',', $v)))) . "'";
$j = fn (array $v): string => $v ? $s(json_encode(array_values($v), JSON_UNESCAPED_UNICODE)) : 'NULL';

$sql = "-- InvenTour, etapa 2: campos específicos de cada ficha do MTur (seção 3 e\n"
     . "-- campos próprios das seções 1 e 2), mais as opções anacrônicas guardadas.\n"
     . "-- Softsul Software. Gerada por scripts/gerar_seed_atributos.php a partir de\n"
     . "-- docs/especificacao/atributos-2011.json em " . date('d/m/Y') . ". Não editar.\n\n"
     . "ALTER TABLE inventario_atributos\n"
     . "    ADD COLUMN opcoes_inativas JSON NULL COMMENT 'opções anacrônicas (fax, cheque, 3G): aceitas na importação, fora do formulário' AFTER opcoes;\n\n"
     . "-- Páginas iniciais conferidas no PDF de 2011.\n"
     . "UPDATE inventario_fichas SET pagina_2011 = 358 WHERE ordem = 49;\n"
     . "UPDATE inventario_fichas SET pagina_2011 = 374 WHERE ordem = 52;\n\n";
$porFicha = [];
$vistos = [];
foreach ($campos as $c) {
    $ordem = (int) $c['ficha'];
    $chave = preg_replace('/[^a-z0-9_]/', '_', mb_strtolower((string) $c['chave']));
    if (isset($vistos[$ordem][$chave])) {
        fwrite(STDERR, "chave repetida na ficha {$ordem}: {$chave}\n");
        exit(1);
    }
    $vistos[$ordem][$chave] = true;
    $tipo = in_array($c['tipo'], $tipos, true) ? $c['tipo'] : 'texto';
    $porFicha[$ordem][] = sprintf(
        "(@f, %s, %s, %s, '%s', %s, %s, %d, %d, %d, %d)",
        $s((string) $c['grupo']), $s($chave), $s((string) $c['rotulo']), $tipo,
        $j((array) ($c['opcoes'] ?? [])), $j((array) ($c['opcoes_inativas'] ?? [])),
        !empty($c['obrigatorio']), !empty($c['portal']), ($c['ativo'] ?? true) ? 1 : 0, (int) ($c['ordem'] ?? 0)
    );
}
ksort($porFicha);
foreach ($porFicha as $ordem => $linhas) {
    $sql .= "SET @f = (SELECT id FROM inventario_fichas WHERE ordem = {$ordem});\n"
          . "INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES\n  "
          . implode(",\n  ", $linhas) . ";\n\n";
}
file_put_contents($saida, $sql);
echo count($campos) . ' campos em ' . count($porFicha) . ' fichas: ' . basename($saida) . "\n";
