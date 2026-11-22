<?php
/**
 * Google Search Console da plataforma: a chave da conta de serviço, o
 * teste e a leitura manual.
 * Softsul Software
 *
 * A chave JSON é colada aqui e gravada cifrada (Ajustes, grupo google,
 * empresa 0): nunca vai ao navegador de volta, só o e-mail da conta. O
 * job puxar_busca usa a mesma chave todo dia às 7h.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Ajustes.php';
require_once __DIR__ . '/../app/SearchConsole.php';
require_once __DIR__ . '/../app/Auditoria.php';

Auth::exigirLogin();
if (!Auth::ehSoftsul()) {
    paginaErro(403, 'Esta tela é da equipe Softsul.', 'O Search Console vale para todos os destinos.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        avisar('A página ficou aberta tempo demais. Tente de novo.', 'ruim');
    } elseif ($acao === 'chave') {
        $json = trim((string) ($_POST['chave_json'] ?? ''));
        if (!empty($_FILES['arquivo']['tmp_name']) && is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
            $json = trim((string) file_get_contents($_FILES['arquivo']['tmp_name'], false, null, 0, 20000));
        }
        $c = json_decode($json, true);
        if (!is_array($c) || ($c['type'] ?? '') !== 'service_account' || empty($c['private_key']) || empty($c['client_email'])) {
            avisar('Isso não é a chave JSON de uma conta de serviço do Google.', 'ruim');
        } else {
            Ajustes::salvar('google', ['chave_json' => json_encode($c, JSON_UNESCAPED_SLASHES)], 0, (int) Auth::usuarioOuNulo()['id'], ['chave_json']);
            Auditoria::registrar('alterou', 'ajustes', null, 'gravou a chave do Google Search Console (' . $c['client_email'] . ')');
            avisar('Chave gravada. Use Testar para conferir o acesso.');
        }
    } elseif ($acao === 'testar') {
        try {
            $n = count(SearchConsole::sitemaps());
            avisar('Conectado: a conta enxerga a propriedade e ' . plural($n, 'sitemap', 'sitemaps') . '.');
        } catch (Throwable $e) {
            avisar('Não conectou: ' . $e->getMessage(), 'ruim');
        }
    } elseif ($acao === 'puxar') {
        try {
            $log = SearchConsole::puxar();
            Auditoria::registrar('alterou', 'ajustes', null, 'puxou os números do Search Console');
            avisar('Números atualizados. ' . implode('; ', $log) . '.');
        } catch (Throwable $e) {
            avisar('Não deu para puxar: ' . $e->getMessage(), 'ruim');
        }
    }
    redirecionar('/configuracoes/google');
}

$chave = SearchConsole::chave();
$ultimo = Database::conexao()->query('SELECT MAX(dia) FROM busca_diaria')->fetchColumn();
paginaInicio('Google Search Console', 'configuracoes');
?>
<div class="trilha"><a href="/configuracoes">Configurações</a><?= icone('seta') ?><span>Google Search Console</span></div>
<div class="cab-pag">
  <div><h1>Google Search Console</h1><p>Cliques, impressões e termos de busca de cada portal aberto, puxados do Google todo dia às 7h; o sitemap de cada portal novo é enviado sozinho. A propriedade é <b><?= e(SearchConsole::PROPRIEDADE) ?></b>, que cobre todos os portais.</p></div>
</div>

<div class="colunas">
  <div class="cartao">
    <h2 class="titulo-secao">Conexão</h2>
    <ul class="lista-saude">
      <li><span class="item-saude"><?= icone('chave') ?><span class="txt-saude"><b>Conta de serviço</b><span><?= $chave ? e($chave['client_email']) : 'Nenhuma chave gravada' ?></span></span>
        <span class="pilula <?= $chave ? 'ok' : 'ruim' ?>"><?= $chave ? 'Gravada' : 'Falta a chave' ?></span></span></li>
      <li><span class="item-saude"><?= icone('calendario') ?><span class="txt-saude"><b>Dados até</b><span><?= $ultimo ? e(dataBr((string) $ultimo, 'd/m/Y')) . ' (o Google entrega com 2 a 3 dias de atraso)' : 'Nada puxado ainda' ?></span></span></span></li>
    </ul>
    <?php if ($chave): ?>
    <form method="post" action="/configuracoes/google" class="cartao-rodape">
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <button type="submit" class="botao claro" name="acao" value="testar"><?= icone('check') ?>Testar</button>
      <button type="submit" class="botao claro" name="acao" value="puxar"><?= icone('baixar') ?>Puxar agora</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="cartao">
    <h2 class="titulo-secao"><?= $chave ? 'Trocar a chave' : 'Gravar a chave' ?></h2>
    <form method="post" action="/configuracoes/google" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="chave">
      <div class="grade">
        <div class="campo c-12">
          <label>Arquivo JSON da chave</label>
          <?= zonaDeUpload('arquivo', 'application/json,.json', 'Arraste o arquivo JSON da chave aqui', 'ou clique para escolher; grava assim que o arquivo entra', true) ?>
        </div>
        <div class="campo c-12">
          <label for="chave_json">ou cole o conteúdo</label>
          <div class="entrada"><textarea id="chave_json" name="chave_json" rows="5" spellcheck="false" placeholder='{"type": "service_account", ...}'></textarea></div>
          <span class="ajuda">Google Cloud, Contas de serviço, a conta do Search Console, Chaves, Adicionar chave, JSON. A mesma conta do EventCity serve: basta incluir o e-mail dela como usuário da propriedade inventour.com.br no Search Console. A chave fica cifrada no banco e não aparece de novo nesta tela.</span>
        </div>
      </div>
      <div class="cartao-rodape"><button type="submit" class="botao"><?= icone('check') ?>Gravar a chave</button></div>
    </form>
  </div>
</div>
<?php paginaFim();
