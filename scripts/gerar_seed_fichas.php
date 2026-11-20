<?php
/**
 * Gera a migração com as 54 fichas do MTur e os tipos de cada uma.
 * Softsul Software
 *
 *   php scripts/gerar_seed_fichas.php [docs/especificacao/fichas-2011.json] [migracoes/006-fichas-e-tipos-do-mtur.sql]
 *
 * Depois da 006, a 007 ligou cada subtipo ao tipo (pai_id). Uma carga nova
 * precisa repetir aquele UPDATE.
 *
 * O JSON foi montado em 20/11/2026 a partir do PDF oficial de 2011 (tipos) e
 * do manual de 2006 (definições). A migração gerada é a que vale: depois de
 * aplicada não se edita (o migrar.php confere o hash); correção entra numa
 * migração nova ou pela tela Fichas e tipos.
 */
declare(strict_types=1);

$raiz = dirname(__DIR__);
$json = $argv[1] ?? $raiz . '/docs/especificacao/fichas-2011.json';
$saida = $argv[2] ?? $raiz . '/migracoes/006-fichas-e-tipos-do-mtur.sql';
$fichas = json_decode((string) file_get_contents($json), true, 512, JSON_THROW_ON_ERROR);

$formas = ['municipio', 'pontual', 'linear'];
$eventcity = ['atrativo', 'hospedagem', 'gastronomia', 'espaco_eventos', 'servico_apoio'];
$s = fn (?string $v): string => $v === null || trim($v) === ''
    ? 'NULL'
    : "'" . str_replace(["\\", "'"], ["\\\\", "''"], trim(preg_replace('/\s+/u', ' ', $v))) . "'";
$semTravessao = fn (string $v): string => str_replace(["\u{2013}", "\u{2014}"], ',', $v);

$sql = "-- InvenTour, etapa 1: as 54 fichas do Inventário da Oferta Turística (MTur,\n"
     . "-- 2011) e os tipos e subtipos de cada uma, com a definição do manual de 2006.\n"
     . "-- Softsul Software. Gerada por scripts/gerar_seed_fichas.php a partir de\n"
     . "-- docs/especificacao/fichas-2011.json em " . date('d/m/Y') . ". Não editar: corrigir\n"
     . "-- numa migração nova ou pela tela Fichas e tipos.\n\n";
$totalTipos = 0;
$ordensVistas = [];
foreach ($fichas as $i => $f) {
    $ordem = (int) ($f['ordem'] ?? ($i + 1));
    if (isset($ordensVistas[$ordem])) {
        fwrite(STDERR, "ordem repetida: {$ordem}\n");
        exit(1);
    }
    $ordensVistas[$ordem] = true;
    $cat = strtoupper((string) $f['categoria']);
    if (!in_array($cat, ['A', 'B', 'C'], true)) {
        fwrite(STDERR, "categoria inválida na ficha {$ordem}\n");
        exit(1);
    }
    $forma = in_array($f['forma'] ?? '', $formas, true) ? $f['forma'] : 'pontual';
    $ec = in_array($f['eventcity'] ?? '', $eventcity, true) ? $f['eventcity'] : null;
    $codigos = implode(', ', array_map('strval', (array) ($f['codigos_mtur'] ?? [])));
    $sql .= sprintf(
        "INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (%d, %s, %s, '%s', %s, %s, '%s', %s, %s);\n",
        $ordem, $s((string) $f['codigo']), $s($codigos), $cat, $s($semTravessao((string) $f['grupo'])), $s($semTravessao((string) $f['nome'])),
        $forma, $ec === null ? 'NULL' : "'{$ec}'", isset($f['pagina']) ? (int) $f['pagina'] : 'NULL'
    );
    $vistos = [];
    $linhas = [];
    foreach ((array) ($f['tipos'] ?? []) as $j => $t) {
        $nome = trim((string) ($t['nome'] ?? ''));
        // "Outros" se repete em tipos diferentes da mesma ficha: a chave é o
        // código mais o nome (migração 007).
        $chave = mb_strtolower(($t['codigo'] ?? '') . '|' . $nome);
        if ($nome === '' || isset($vistos[$chave])) {
            continue;
        }
        $vistos[$chave] = true;
        $linhas[] = sprintf("(@f, %s, %s, %s, %d)", $s((string) ($t['codigo'] ?? '')), $s($semTravessao($nome)),
            $s($semTravessao((string) ($t['definicao'] ?? ''))), $j + 1);
    }
    if ($linhas) {
        $sql .= "SET @f = LAST_INSERT_ID();\n"
              . "INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES\n  "
              . implode(",\n  ", $linhas) . ";\n";
        $totalTipos += count($linhas);
    }
    $sql .= "\n";
}
file_put_contents($saida, $sql);
echo count($fichas) . " fichas e {$totalTipos} tipos em " . basename($saida) . "\n";
