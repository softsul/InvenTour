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
        Campo::desconectar((int) ($_POST['id'] ?? 0), Esteira::coordena());
        avisar('Aparelho desconectado. Ele não envia mais nada.');
        redirecionar('/coleta#app');
    }
}
// Quem coordena a coleta vê (e desliga) os aparelhos da equipe toda.
$daEquipe = Esteira::coordena();
$aparelhos = Campo::aparelhosDoDestino($daEquipe);
$versaoApp = is_file(__DIR__ . '/baixar/versao-inventour-campo.json') ? (json_decode((string) file_get_contents(__DIR__ . '/baixar/versao-inventour-campo.json'), true) ?: []) : [];
$baixar = is_file(__DIR__ . '/baixar/inventour-campo.apk');
$csrf = Auth::tokenCsrf();

paginaInicio('Coleta de campo', 'coleta');
?>
<div class="cab-pag">
  <div>
    <h1>Coleta de campo</h1>
    <p>No celular, a coleta é pelo aplicativo InvenTour Campo (aba Aplicativo). Aqui está a mesma coleta, para o notebook ou o tablet.</p>
  </div>
  <div class="acoes"><button type="button" class="botao" id="col-novo" hidden><?= icone('mais') ?>Novo item</button></div>
</div>

<?php /* Duas abas: a coleta no navegador e o aplicativo (instalar, conectar,
         aparelhos). A aba vem do endereço (#app), para onde o código e o
         desconectar voltam. */ ?>
<nav class="abas-chamado abas-destino" role="tablist" aria-label="Coleta de campo">
  <button type="button" role="tab" data-aba="coleta" aria-selected="<?= $codigo ? 'false' : 'true' ?>"><?= icone('lista') ?>Coleta</button>
  <button type="button" role="tab" data-aba="app" aria-selected="<?= $codigo ? 'true' : 'false' ?>"><?= icone('camera') ?>Aplicativo<span class="conta-aba"><?= count($aparelhos) ?></span></button>
</nav>

<div class="painel-aba" data-painel="app"<?= $codigo ? '' : ' hidden' ?>>
  <div class="app-campo-grade">
    <div class="cartao app-passo">
      <span class="app-passo-num">1</span>
      <h2>Instale no celular</h2>
      <p>O InvenTour Campo abre sem sinal, usa a câmera e o GPS do aparelho e só envia no botão Sincronizar.</p>
      <?php if ($baixar): ?>
        <a class="botao" href="/baixar/inventour-campo.apk"><?= icone('baixar') ?>Baixar para Android</a>
        <span class="nulo"><?= !empty($versaoApp['versionName']) ? 'Versão ' . e((string) $versaoApp['versionName']) . '. ' : '' ?>No celular, permita instalar de fora da loja.</span>
      <?php endif; ?>
      <span class="nulo">iPhone: pelo TestFlight, enquanto o app não sai na App Store.</span>
    </div>
    <div class="cartao app-passo">
      <span class="app-passo-num">2</span>
      <h2>Gere o código</h2>
      <?php if ($codigo): ?>
        <b class="app-codigo dado"><?= e(substr($codigo, 0, 4) . ' ' . substr($codigo, 4)) ?></b>
        <span class="nulo">Vale 15 minutos e uma vez só.</span>
      <?php else: ?>
        <p>O código liga o aparelho a você (<b><?= e((string) $u['nome']) ?></b>) em <?= e((string) $empresa['nome_fantasia']) ?>, sem senha no celular.</p>
        <form method="post" action="/coleta#app"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <button type="submit" name="acao" value="codigo" class="botao"><?= icone('chave') ?>Gerar código de conexão</button></form>
      <?php endif; ?>
    </div>
    <div class="cartao app-passo">
      <span class="app-passo-num">3</span>
      <h2>Digite no app</h2>
      <p>Abra o InvenTour Campo, digite o código e toque em Conectar. O app baixa a lista do destino; daí em diante funciona sem sinal.</p>
    </div>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Aparelhos conectados<?= $daEquipe ? ' da equipe' : '' ?> (<?= count($aparelhos) ?>)</h2></div>
    <?php if (!$aparelhos): ?>
      <div class="vazio"><?= icone('digital') ?><p><strong>Nenhum aparelho conectado.</strong></p><p>Siga os três passos acima no celular de quem vai a campo.</p></div>
    <?php else: ?>
    <div class="rolagem">
      <table class="dados">
        <thead><tr><?= $daEquipe ? '<th>Pessoa</th>' : '' ?><th>Aparelho</th><th>Conectado em</th><th>Último uso</th><th>Envios</th><th class="acao"></th></tr></thead>
        <tbody>
        <?php foreach ($aparelhos as $a): ?>
          <tr>
            <?php if ($daEquipe): ?><td class="elastica"><span class="cli"><?= avatarIniciais((string) $a['pessoa'], 'avatar') ?><span class="txt"><b><?= e((string) $a['pessoa']) ?></b><span><?= (int) $a['usuario_id'] === (int) $u['id'] ? 'você' : 'equipe' ?></span></span></span></td><?php endif; ?>
            <td<?= $daEquipe ? '' : ' class="elastica"' ?>><?= e((string) ($a['nome'] ?: 'Aparelho')) ?></td>
            <td class="dado"><?= e(dataBr((string) $a['conectado_em'], 'd/m/Y H:i')) ?></td>
            <td class="dado"><?= $a['ultimo_uso_em'] ? e(dataBr((string) $a['ultimo_uso_em'], 'd/m/Y H:i')) : '<span class="nulo">-</span>' ?></td>
            <td class="dado"><?= (int) $a['envios'] ?></td>
            <td class="acao"><div class="acoes-linha">
              <form method="post" action="/coleta#app"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button type="submit" name="acao" value="desconectar" title="Desconectar" aria-label="Desconectar <?= e((string) ($a['nome'] ?: 'aparelho')) ?>"
                  data-confirmar="Desconectar este aparelho? O que estiver só nele não sobe mais."><?= icone('sair') ?></button></form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <p class="ajuda">Celular perdido ou pessoa que saiu da equipe: desconecte aqui. Trocar a senha também desliga todos os aparelhos da pessoa.</p>
  </div>
</div>

<div class="painel-aba" data-painel="coleta"<?= $codigo ? ' hidden' : '' ?>>
<div class="coleta" id="coleta" data-csrf="<?= e($csrf) ?>" data-chave="<?= (int) $empresa['id'] ?>-<?= (int) $u['id'] ?>">
<?= telaColeta(false) ?>
</div>
</div>
<script src="/assets/coleta.js?v=<?= (int) @filemtime(__DIR__ . '/assets/coleta.js') ?>" defer></script>
<?php paginaFim();
