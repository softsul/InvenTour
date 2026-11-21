<?php
/**
 * Importa fotos de um caderno de inventário já publicado para as fichas do
 * InvenTour, com legenda e crédito.
 * Softsul Software
 *
 *   php scripts/importar_fotos_caderno.php <empresa_id> --origem="Importado do ..." [--gravar] < fotos.ndjson
 *
 * Cada linha da entrada é um JSON {"item": id, "legenda": "...",
 * "credito": "...", "jpeg": "<base64>"}. A entrada vem pelo stdin porque o
 * script roda como o dono do armazenamento, que não lê a pasta de trabalho
 * de quem preparou as fotos. Sem --gravar, só mostra o que faria.
 *
 * A foto passa pelo mesmo tratamento do upload (Imagem::foto e as
 * miniaturas). Vira capa só se o item ainda não tem foto. Foto com a mesma
 * legenda e o mesmo crédito no item não entra de novo, então rodar duas
 * vezes não duplica. Feito para o caderno de Pato Branco 2025 (21/11/2026).
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Imagem.php';
require_once __DIR__ . '/../app/Auditoria.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$eid = (int) ($argv[1] ?? 0);
$gravar = in_array('--gravar', $argv, true);
require_once __DIR__ . '/origem_importacao.php';
$origem = origemDaImportacao($argv, 'Uso: php scripts/importar_fotos_caderno.php <empresa_id> --origem="Importado do ..." [--gravar] < fotos.ndjson');
if (!$eid) {
    fwrite(STDERR, "Uso: php scripts/importar_fotos_caderno.php <empresa_id> --origem=\"Importado do ...\" [--gravar] < fotos.ndjson\n");
    exit(1);
}
$cfg = require __DIR__ . '/../app/config.php';
$pasta = rtrim((string) $cfg['caminhos']['midias'], '/') . '/inventario/' . $eid;
if ($gravar && !is_dir($pasta) && !@mkdir($pasta, 0770, true)) {
    exit("Não consegui criar {$pasta}.\n");
}
$pdo = Database::conexao();
$item = $pdo->prepare("SELECT id, nome FROM inventario_itens WHERE id = ? AND empresa_id = ? AND situacao <> 'arquivada'");
$ja = $pdo->prepare('SELECT COUNT(*), COALESCE(SUM(legenda <=> ? AND credito <=> ?), 0) FROM inventario_midias WHERE item_id = ? AND empresa_id = ? AND experiencia_id IS NULL');
$insere = $pdo->prepare('INSERT INTO inventario_midias (empresa_id, item_id, experiencia_id, arquivo, largura, altura, legenda, credito, capa, ordem, criado_em)
                         VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, NOW())');
[$novas, $repetidas, $erros] = [0, 0, 0];
while (($linha = fgets(STDIN)) !== false) {
    $f = json_decode($linha, true);
    if (!is_array($f)) {
        continue;
    }
    $item->execute([(int) $f['item'], $eid]);
    $i = $item->fetch();
    $legenda = mb_substr(trim((string) $f['legenda']), 0, 200);
    $credito = mb_substr(trim((string) $f['credito']), 0, 120);
    if (!$i) {
        echo "  item {$f['item']} não existe no destino ou está arquivado: {$legenda}\n";
        $erros++;
        continue;
    }
    $ja->execute([$legenda, $credito, (int) $i['id'], $eid]);
    [$quantas, $igual] = array_map('intval', $ja->fetch(PDO::FETCH_NUM));
    if ($igual) {
        $repetidas++;
        continue;
    }
    $foto = Imagem::foto((string) base64_decode((string) $f['jpeg']));
    if ($foto === null) {
        echo "  imagem inválida: {$i['nome']} / {$legenda}\n";
        $erros++;
        continue;
    }
    [$bytes, $l, $a] = $foto;
    echo ($gravar ? '+ ' : '  ') . "{$i['nome']}: {$legenda} ({$l}x{$a}, foto: {$credito})" . ($quantas === 0 ? ' [capa]' : '') . "\n";
    if (!$gravar) {
        $novas++;
        continue;
    }
    $nome = $i['id'] . '-' . bin2hex(random_bytes(6)) . '.jpg';
    if (@file_put_contents($pasta . '/' . $nome, $bytes) === false) {
        echo "  não gravou o arquivo de {$i['nome']}\n";
        $erros++;
        continue;
    }
    foreach (Imagem::miniaturas($bytes) as $sufixo => $miniatura) {
        @file_put_contents($pasta . '/' . Imagem::nomeMiniatura($nome, $sufixo), $miniatura);
    }
    $insere->execute([$eid, (int) $i['id'], $nome, $l, $a, $legenda, $credito, $quantas === 0 ? 1 : 0, $quantas + 1]);
    Auditoria::registrar('item.foto', 'inventario_item', (int) $i['id'], 'Foto (' . $origem . '): ' . $legenda, null,
        ['arquivo' => $nome, 'legenda' => $legenda, 'credito' => $credito], $eid);
    $novas++;
}
printf("%s: %d fotos novas, %d já estavam, %d com erro.\n", $gravar ? 'Gravado' : 'Simulação', $novas, $repetidas, $erros);
