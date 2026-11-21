<?php
/**
 * Os arquivos do trade, atrás de login: o comprovante de um pedido e as
 * fotos de uma revisão que ainda espera a equipe.
 * Softsul Software
 *
 * Ficam em storage/trade/<destino>/, fora do DocumentRoot. O comprovante só
 * a equipe vê (trade.ver); a foto da revisão vê a equipe e quem a mandou.
 * Vai inline, para abrir no visualizador do painel.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Trade.php';

$eu = Auth::exigirLogin();
$empresa = Empresa::exigir();
$eid = (int) $empresa['id'];

if (isset($_GET['pedido'])) {
    Auth::exigirPermissao('trade.ver');
    $r = Trade::pedido((int) $_GET['pedido']) ?: negarRegistro();
    $arquivo = (string) $r['comprovante'];
    $tipo = (string) $r['comprovante_tipo'];
} else {
    $v = Trade::revisao((int) ($_GET['revisao'] ?? 0)) ?: negarRegistro();
    if (!Auth::pode('trade.ver') && (int) $v['usuario_id'] !== (int) $eu['id']) {
        negarRegistro();
    }
    $nomes = array_column($v['fotos'], 'arquivo');
    $arquivo = (string) ($_GET['f'] ?? '');
    if (!in_array($arquivo, $nomes, true)) {
        negarRegistro();
    }
    $tipo = 'image/jpeg';
}
$caminho = Trade::caminho($eid, $arquivo);
if ($arquivo === '' || !is_file($caminho)) {
    negarRegistro();
}
// Miniatura (cartões e galerias); a grande só no visualizador.
if (!empty($_GET['mini']) && $tipo === 'image/jpeg') {
    require_once __DIR__ . '/../app/Imagem.php';
    if ($m = Imagem::miniaturas((string) file_get_contents($caminho))['m'] ?? null) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=300');
        echo $m;
        exit;
    }
}
header('Content-Type: ' . $tipo);
header('Content-Disposition: inline');
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($caminho);
