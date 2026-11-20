<?php
/**
 * Coleta de campo no painel, e a conexão do app InvenTour Campo.
 * Softsul Software
 *
 * A coleta de verdade é no app (mobile/campo): abre sem sinal, usa a câmera
 * e o GPS do aparelho e só fala com o servidor no botão Sincronizar. Aqui
 * fica a mesma tela (app/ColetaTela.php e assets/coleta.js), para usar num
 * notebook ou tablet, e o cartão que conecta o app: um código de 8 letras
 * que vale 15 minutos, e a lista dos aparelhos para desligar o perdido.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Esteira.php';
require_once __DIR__ . '/../app/Campo.php';
require_once __DIR__ . '/../app/ColetaTela.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('coleta.ver');
Auth::exigirModuloLigado('coleta');
$u = Auth::usuarioOuNulo();

$codigo = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        avisar('A página ficou aberta tempo demais. Tente de novo.', 'ruim');
        redirecionar('/coleta');
    }
    if (($_POST['acao'] ?? '') === 'codigo') {
        // Mostrado uma vez, nesta resposta; no banco fica só o hash.
        $codigo = Campo::novoCodigo();
    } elseif (($_POST['acao'] ?? '') === 'desconectar') {
        Campo::desconectar((int) ($_POST['id'] ?? 0));
        avisar('Aparelho desconectado. Ele não envia mais nada.');
        redirecionar('/coleta');
    }
}
$aparelhos = Campo::aparelhos();
$baixar = is_file(__DIR__ . '/baixar/inventour-campo.apk');
$csrf = Auth::tokenCsrf();

paginaInicio('Coleta de campo', 'coleta');
?>
<div class="cab-pag">
  <div>
    <h1>Coleta de campo</h1>
    <p>No celular, use o aplicativo InvenTour Campo: abre sem sinal, usa a câmera e o GPS do aparelho e envia tudo no botão Sincronizar. Aqui está a mesma coleta, para o notebook ou o tablet.</p>
  </div>
  <div class="acoes"><button type="button" class="botao" id="col-novo" hidden><?= icone('mais') ?>Novo item</button></div>
</div>

<div class="cartao app-campo<?= $codigo ? ' com-codigo' : '' ?>">
  <div class="app-campo-texto">
    <h2><?= icone('camera') ?>Aplicativo InvenTour Campo</h2>
    <p>1. Instale no celular. 2. Gere o código aqui. 3. Digite o código no app. O aparelho fica conectado como <b><?= e((string) $u['nome']) ?></b> em <?= e((string) $empresa['nome_fantasia']) ?>, sem senha no celular.</p>
    <div class="app-campo-baixar">
      <?php if ($baixar): ?><a class="botao claro" href="/baixar/inventour-campo.apk"><?= icone('baixar') ?>Android (APK)</a><?php endif; ?>
      <span class="nulo">iPhone: pelo TestFlight, enquanto o app não sai na App Store.</span>
    </div>
  </div>
  <div class="app-campo-codigo">
    <?php if ($codigo): ?>
      <span class="rotulo">Código de conexão</span>
      <b class="codigo dado"><?= e(substr($codigo, 0, 4) . ' ' . substr($codigo, 4)) ?></b>
      <span class="nulo">Vale 15 minutos e uma vez só.</span>
    <?php else: ?>
      <form method="post" action="/coleta"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit" name="acao" value="codigo" class="botao"><?= icone('chave') ?>Gerar código de conexão</button></form>
    <?php endif; ?>
  </div>
  <?php if ($aparelhos): ?>
  <ul class="lista-simples app-campo-aparelhos">
    <?php foreach ($aparelhos as $a): ?>
      <li><?= icone('digital') ?><b><?= e((string) ($a['nome'] ?: 'Aparelho')) ?></b>
        <span class="nulo">conectado em <?= e(dataBr((string) $a['conectado_em'], 'd/m/Y')) ?><?= $a['ultimo_uso_em'] ? ', último envio ' . e(dataBr((string) $a['ultimo_uso_em'], 'd/m H:i')) : '' ?></span>
        <form method="post" action="/coleta" class="em-linha"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <button type="submit" name="acao" value="desconectar" class="como-link" data-confirmar="Desconectar este aparelho? O que estiver só nele não sobe mais.">desconectar</button></form></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>

<div class="coleta" id="coleta" data-csrf="<?= e($csrf) ?>" data-chave="<?= (int) $empresa['id'] ?>-<?= (int) $u['id'] ?>">
<?= telaColeta(false) ?>
</div>
<script src="/assets/coleta.js?v=<?= (int) @filemtime(__DIR__ . '/assets/coleta.js') ?>" defer></script>
<?php paginaFim();
