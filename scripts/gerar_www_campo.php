<?php
/**
 * Monta mobile/campo/www, a tela embutida no app InvenTour Campo.
 * Softsul Software
 *
 *   php scripts/gerar_www_campo.php
 *
 * A coleta é a mesma do painel: a marcação sai de app/ColetaTela.php
 * (telaColeta(true), com a câmera do aparelho no lugar da zona de arrastar)
 * e os arquivos são cópias de public/assets (tema.css, inventour.css,
 * coleta.js, fontes e a marca). O que é só do app fica em
 * mobile/campo/fonte (campo.js e campo.css). Rode antes de cada versão do
 * app (o mobile/campo/gerar-versao.sh já chama).
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/funcoes.php';
require_once __DIR__ . '/../app/ColetaTela.php';

$raiz = dirname(__DIR__);
$www = $raiz . '/mobile/campo/www';
$assets = $raiz . '/public/assets';

// Começa do zero: nada de arquivo velho esquecido dentro do app.
if (is_dir($www)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($www, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
}
@mkdir($www . '/assets/fontes', 0775, true);
@mkdir($www . '/assets/marca', 0775, true);

foreach (['tema.css', 'inventour.css', 'coleta.js'] as $a) {
    copy("{$assets}/{$a}", "{$www}/assets/{$a}");
}
foreach (glob("{$assets}/fontes/*.woff2") ?: [] as $f) {
    copy($f, "{$www}/assets/fontes/" . basename($f));
}
foreach (['emblema.svg', 'marca.css'] as $a) {
    if (is_file("{$assets}/marca/{$a}")) {
        copy("{$assets}/marca/{$a}", "{$www}/assets/marca/{$a}");
    }
}
copy($raiz . '/mobile/campo/fonte/campo.js', $www . '/assets/campo.js');
copy($raiz . '/mobile/campo/fonte/campo.css', $www . '/assets/campo.css');

// Versão do app (a do build.gradle, que o gerar-versao.sh acabou de subir), para o menu.
$gradle = (string) @file_get_contents($raiz . '/mobile/campo/android/app/build.gradle');
$versaoApp = preg_match('/versionName "([^"]+)"/', $gradle, $m) ? $m[1] : '?';
$codigoApp = preg_match('/versionCode (\d+)/', $gradle, $m) ? $m[1] : '?';

ob_start();
svgSprite();
$sprite = (string) ob_get_clean();
$logo = logoInvenTour('logo');
// Dentro do app o endereço da marca não leva a versão do painel.
$logo = (string) preg_replace('#/assets/marca/emblema\.svg\?v=\d+#', '/assets/marca/emblema.svg', $logo);

$html = '<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light">
<meta http-equiv="Content-Security-Policy" content="default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\' data: blob: capacitor: https://localhost; font-src \'self\'; connect-src \'self\' https://app.inventour.com.br blob: capacitor: https://localhost; frame-src https://www.google.com; object-src \'none\'; base-uri \'self\'">
<title>InvenTour Campo</title>
<link rel="stylesheet" href="assets/tema.css">
<link rel="stylesheet" href="assets/inventour.css">
<link rel="stylesheet" href="assets/marca/marca.css">
<link rel="stylesheet" href="assets/campo.css">
</head>
<body class="campo" data-versao-codigo="' . e($codigoApp) . '" data-versao-nome="' . e($versaoApp) . '">
' . $sprite . '
<header class="campo-topo">
  ' . $logo . '
  <div class="quem" id="campo-quem"></div>
  <button type="button" class="menu-app" id="campo-menu" aria-label="Menu: sincronizar, histórico e conexão" hidden>' . icone('menu') . '<span class="menu-conta" id="campo-menu-conta" hidden>0</span></button>
</header>

<main class="campo-corpo">
  <section id="campo-conectar" hidden>
    <form class="campo-conectar" id="campo-form" novalidate>
      ' . logoInvenTour('logo') . '
      <h1>Conectar este aparelho</h1>
      <ol>
        <li>No computador, entre no painel do InvenTour.</li>
        <li>Abra Coleta de campo e toque em Gerar código de conexão.</li>
        <li>Digite aqui o código de 8 letras.</li>
      </ol>
      <div class="entrada"><input type="text" id="campo-codigo" maxlength="9" placeholder="ABCD 2345" autocapitalize="characters" autocomplete="off" autocorrect="off" spellcheck="false" aria-label="Código de conexão"></div>
      <p class="aviso ruim" id="campo-erro" hidden></p>
      <button type="submit" class="botao" id="campo-entrar">Conectar</button>
      <p class="rodape">Precisa de internet só para conectar. Depois, a coleta funciona sem sinal e sobe no botão Sincronizar.<br>Produto Softsul Software e Zapping Consultoria.</p>
    </form>
  </section>

  <section id="campo-coleta" hidden>
    <div class="coleta" id="coleta">
' . telaColeta(true, false) . '
    </div>
  </section>
</main>

<button type="button" class="botao flutuante" id="col-novo" hidden>' . icone('mais') . 'Novo item</button>

<dialog class="coleta-dialogo pequeno" id="campo-sobre" aria-labelledby="campo-sobre-titulo">
  <div class="cab-dialogo"><h2 id="campo-sobre-titulo">Menu</h2>
    <button type="button" class="fechar" data-fechar aria-label="Fechar">' . icone('fechar') . '</button></div>
  <div class="corpo-dialogo">
    <div class="menu-sinc">' . telaEstadoColeta() . '</div>
    <p>Conectado como <b id="campo-sobre-quem"></b>.</p>
    <p class="nulo">As gravações ficam neste aparelho até você tocar em Sincronizar. Para trocar de pessoa ou de destino, desconecte e conecte com um código novo.</p>
    <div class="acoes-app">
      <button type="button" class="botao claro" data-fechar>Fechar</button>
      <button type="button" class="botao perigo" id="campo-desconectar">' . icone('sair') . 'Desconectar este aparelho</button>
    </div>
    <p class="versao-app">InvenTour Campo <b>versão ' . e($versaoApp) . '</b> <span class="nulo">(código ' . e($codigoApp) . ')</span></p>
    <p class="nulo">Produto Softsul Software e Zapping Consultoria.</p>
  </div>
</dialog>

<dialog class="coleta-dialogo pequeno atualizar-app" id="campo-atualizar" aria-labelledby="campo-atualizar-titulo">
  <div class="corpo-dialogo">
    <span class="atualizar-icone">' . icone('baixar') . '</span>
    <h2 id="campo-atualizar-titulo">Atualize o InvenTour Campo</h2>
    <p>Saiu a versão <b id="campo-atualizar-nova"></b>. Esta é a <b>' . e($versaoApp) . '</b>, que não pode mais ser usada.</p>
    <p class="nulo">O que está gravado no aparelho não se perde: depois de instalar, abra o app e sincronize normalmente.</p>
    <a class="botao" id="campo-atualizar-baixar" href="#" target="_blank" rel="noopener">' . icone('baixar') . 'Baixar e instalar a versão nova</a>
    <p class="nulo" id="campo-atualizar-ios" hidden>No iPhone, atualize pelo TestFlight ou pela App Store.</p>
  </div>
</dialog>

<script src="assets/campo.js"></script>
</body>
</html>
';
file_put_contents($www . '/index.html', $html);
echo "www do InvenTour Campo pronto em {$www}\n";
