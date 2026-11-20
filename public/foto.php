<?php
/**
 * Serve as fotos dos cadastros (pessoas e destinos), recortadas em quadrado,
 * redimensionadas e com controle de acesso.
 * Softsul Software
 *
 *   /foto/usuario/3/64    /foto/empresa/1/128
 *
 * As imagens ficam fora do DocumentRoot (storage/fotos): em public/, a foto
 * de cada pessoa ficaria aberta a quem adivinhasse o nome do arquivo. O
 * redimensionamento acontece uma vez e fica em cache (como no Orbit). Quem
 * pode ver: a equipe Softsul vê tudo; os demais, a própria foto, a de quem
 * divide um destino com eles e a do destino em que têm vínculo.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Foto.php';

$eu = Auth::usuarioOuNulo();
$tipo = (string) ($_GET['tipo'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
// Tamanho fora da lista (ou ausente, em /foto.php?tipo=...&id=... sem tam)
// vira 64: ler $_GET['tam'] de novo sem o ?? dava largura 0 e erro 500.
$tam = (int) ($_GET['tam'] ?? 64);
$tam = in_array($tam, [32, 64, 128, 256], true) ? $tam : 64;
if (!$eu || !isset(Foto::TIPOS[$tipo]) || !in_array($tipo, ['usuario', 'empresa'], true) || $id <= 0) {
    http_response_code(404);
    exit;
}
$pdo = Database::conexao();

$pode = (bool) $eu['softsul'];
if (!$pode && $tipo === 'usuario') {
    $q = $pdo->prepare('SELECT 1 FROM usuario_empresa a JOIN usuario_empresa b ON b.empresa_id = a.empresa_id
                        WHERE a.usuario_id = ? AND b.usuario_id = ? AND a.ativo = 1 LIMIT 1');
    $q->execute([(int) $eu['id'], $id]);
    $pode = $id === (int) $eu['id'] || (bool) $q->fetchColumn();
} elseif (!$pode) {
    $q = $pdo->prepare('SELECT 1 FROM usuario_empresa WHERE usuario_id = ? AND empresa_id = ? AND ativo = 1');
    $q->execute([(int) $eu['id'], $id]);
    $pode = (bool) $q->fetchColumn();
}
$s = $pdo->prepare('SELECT foto FROM ' . Foto::TIPOS[$tipo] . ' WHERE id = ?');
$s->execute([$id]);
$relativo = (string) $s->fetchColumn();
if (!$pode || $relativo === '') {
    http_response_code(404);
    exit;
}

$cfg = require __DIR__ . '/../app/config.php';
// basename em cada pedaço: o caminho vem do banco, mas um "../" gravado por
// engano não pode sair da pasta de fotos.
$origem = $cfg['caminhos']['fotos'] . '/' . implode('/', array_map('basename', explode('/', $relativo)));
if (!is_readable($origem)) {
    http_response_code(404);
    exit;
}
$pastaCache = $cfg['caminhos']['tmp'] . '/miniaturas';
if (!is_dir($pastaCache)) {
    @mkdir($pastaCache, 0770, true);
}
$cache = $pastaCache . '/' . md5($relativo . '|' . $tam . '|' . filemtime($origem)) . '.webp';
if (!is_readable($cache)) {
    $info = @getimagesize($origem);
    $img = match ($info[2] ?? 0) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($origem),
        IMAGETYPE_PNG  => @imagecreatefrompng($origem),
        IMAGETYPE_WEBP => @imagecreatefromwebp($origem),
        default        => false,
    };
    if (!$img) {
        http_response_code(404);
        exit;
    }
    [$l, $a] = [imagesx($img), imagesy($img)];
    $lado = min($l, $a);
    $novo = imagecreatetruecolor($tam, $tam);
    imagecopyresampled($novo, $img, 0, 0, (int) (($l - $lado) / 2), (int) (($a - $lado) / 2), $tam, $tam, $lado, $lado);
    imagewebp($novo, $cache, 82);
}
header('Content-Type: image/webp');
header('Cache-Control: private, max-age=86400');
readfile($cache);
