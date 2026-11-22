<?php
/**
 * Bing Webmaster da plataforma: a chave de API, o teste, a leitura manual e
 * a situação de cada portal na conta do Bing.
 * Softsul Software
 *
 * A chave é colada aqui e gravada cifrada (Ajustes, grupo bing, empresa 0):
 * nunca volta ao navegador, só os quatro últimos caracteres. O job
 * puxar_bing usa a mesma chave todo dia às 7h15, e o avisar_indexnow, de
 * hora em hora.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Ajustes.php';
require_once __DIR__ . '/../app/Bing.php';
require_once __DIR__ . '/../app/Auditoria.php';

Auth::exigirLogin();
if (!Auth::ehSoftsul()) {
    paginaErro(403, 'Esta tela é da equipe Softsul.', 'O Bing Webmaster vale para todos os destinos.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        avisar('A página ficou aberta tempo demais. Tente de novo.', 'ruim');
    } elseif ($acao === 'chave') {
        $chave = trim((string) ($_POST['api_chave'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9]{16,64}$/', $chave)) {
            avisar('Isso não parece a chave de API do Bing Webmaster (letras e números, sem espaço).', 'ruim');
        } else {
            Ajustes::salvar('bing', ['api_chave' => $chave], 0, (int) Auth::usuarioOuNulo()['id'], ['api_chave']);
            Auditoria::registrar('alterou', 'ajustes', null, 'gravou a chave de API do Bing Webmaster');
            avisar('Chave gravada. Use Testar para conferir o acesso.');
        }
    } elseif ($acao === 'testar') {
        try {
            $n = count(Bing::sites());
            avisar('Conectado: a conta tem ' . plural($n, 'site', 'sites') . ' no Bing.');
        } catch (Throwable $e) {
            avisar('Não conectou: ' . $e->getMessage(), 'ruim');
        }
    } elseif ($acao === 'puxar') {
        try {
            $log = Bing::puxar();
            Auditoria::registrar('alterou', 'ajustes', null, 'puxou os números do Bing Webmaster');
            avisar('Números atualizados. ' . implode('; ', $log) . '.');
        } catch (Throwable $e) {
            avisar('Não deu para puxar: ' . $e->getMessage(), 'ruim');
        }
    }
    redirecionar('/configuracoes/bing');
}

$final = Bing::finalDaChave();
$pdo = Database::conexao();
$ultimo = $pdo->query('SELECT MAX(dia) FROM bing_diaria')->fetchColumn();
$sites = $pdo->query('SELECT e.nome_fantasia, s.url, s.verificado, s.sitemap, s.conferido_em FROM bing_sites s JOIN empresas e ON e.id = s.empresa_id ORDER BY e.nome_fantasia')->fetchAll();
$marca = Ajustes::ler('bing', 'indexnow_marca', '', 0);
paginaInicio('Bing Webmaster', 'configuracoes');
?>
<div class="trilha"><a href="/configuracoes">Configurações</a><?= icone('seta') ?><span>Bing Webmaster</span></div>
<div class="cab-pag">
  <div><h1>Bing Webmaster</h1><p>Cliques, impressões e termos de busca de cada portal aberto no Bing, que também abastece o Yahoo, o DuckDuckGo, o Ecosia, o Copilot e a busca do ChatGPT. Puxados todo dia às 7h15; cada portal novo é cadastrado e verificado sozinho.</p></div>
</div>

<div class="colunas">
  <div class="cartao">
    <h2 class="titulo-secao">Conexão</h2>
    <ul class="lista-saude">
      <li><span class="item-saude"><?= icone('chave') ?><span class="txt-saude"><b>Chave de API</b><span><?= $final !== '' ? 'Gravada, termina em ' . e($final) : 'Nenhuma chave gravada' ?></span></span>
        <span class="pilula <?= $final !== '' ? 'ok' : 'ruim' ?>"><?= $final !== '' ? 'Gravada' : 'Falta a chave' ?></span></span></li>
      <li><span class="item-saude"><?= icone('calendario') ?><span class="txt-saude"><b>Dados até</b><span><?= $ultimo ? e(dataBr((string) $ultimo, 'd/m/Y')) . ' (o Bing entrega com 2 a 3 dias de atraso; os termos, por semana)' : 'Nada puxado ainda' ?></span></span></span></li>
      <li><span class="item-saude"><?= icone('enviar') ?><span class="txt-saude"><b>IndexNow</b><span><?= $marca !== '' ? 'Último aviso em ' . e(dataBr($marca, 'd/m/Y H:i')) . '; lugar e roteiro que mudam vão ao Bing de hora em hora' : ($final !== '' ? 'O primeiro aviso sai na próxima hora' : 'Começa a avisar quando a chave estiver gravada') ?></span></span></span></li>
    </ul>
    <?php if ($final !== ''): ?>
    <form method="post" action="/configuracoes/bing" class="cartao-rodape">
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <button type="submit" class="botao claro" name="acao" value="testar"><?= icone('check') ?>Testar</button>
      <button type="submit" class="botao claro" name="acao" value="puxar"><?= icone('baixar') ?>Puxar agora</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="cartao">
    <h2 class="titulo-secao"><?= $final !== '' ? 'Trocar a chave' : 'Gravar a chave' ?></h2>
    <form method="post" action="/configuracoes/bing">
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="chave">
      <div class="grade">
        <div class="campo c-12">
          <label for="api_chave">Chave de API</label>
          <div class="entrada"><?= icone('chave') ?><input type="password" id="api_chave" name="api_chave" autocomplete="off" spellcheck="false" required></div>
          <span class="ajuda">Bing Webmaster Tools, Configurações (a engrenagem no alto), Acesso à API, Chave de API, Gerar. A chave é da conta, não de um site, e fica cifrada no banco; não aparece de novo nesta tela.</span>
        </div>
      </div>
      <div class="cartao-rodape"><button type="submit" class="botao"><?= icone('check') ?>Gravar a chave</button></div>
    </form>
  </div>
</div>

<?php if ($sites): ?>
<div class="cartao">
  <h2 class="titulo-secao">Portais no Bing</h2>
  <div class="rolagem">
    <table class="dados">
      <thead><tr><th>Destino</th><th>Endereço</th><th>Verificação</th><th>Sitemap</th><th>Conferido</th></tr></thead>
      <tbody>
      <?php foreach ($sites as $s): ?>
        <tr>
          <td class="elastica"><?= e($s['nome_fantasia']) ?></td>
          <td class="dado"><?= e($s['url']) ?></td>
          <td><span class="pilula <?= $s['verificado'] ? 'ok' : 'at' ?>"><?= $s['verificado'] ? 'Verificado' : 'Aguardando' ?></span></td>
          <td><span class="pilula <?= $s['sitemap'] ? 'ok' : 'nu' ?>"><?= $s['sitemap'] ? 'Enviado' : 'Ainda não' ?></span></td>
          <td class="dado"><?= e(dataBr((string) $s['conferido_em'], 'd/m H:i')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php paginaFim();
