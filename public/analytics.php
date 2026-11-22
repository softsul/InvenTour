<?php
/**
 * Google Analytics 4 da plataforma: o código de medição (que liga o aviso
 * de cookies e a etiqueta no portal), o id da propriedade (que a Data API
 * lê), o teste e a leitura manual.
 * Softsul Software
 *
 * A leitura usa a conta de serviço do Search Console, que precisa ser
 * Leitor na propriedade do GA4. O job puxar_analytics roda todo dia às 7h30.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Analytics.php';
require_once __DIR__ . '/../app/Auditoria.php';

Auth::exigirLogin();
if (!Auth::ehSoftsul()) {
    paginaErro(403, 'Esta tela é da equipe Softsul.', 'O Google Analytics vale para todos os destinos.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        avisar('A página ficou aberta tempo demais. Tente de novo.', 'ruim');
    } elseif ($acao === 'gravar') {
        $medicao = strtoupper(trim((string) ($_POST['medicao'] ?? '')));
        $propriedade = trim((string) ($_POST['propriedade'] ?? ''));
        if ($medicao !== '' && !preg_match('/^G-[A-Z0-9]{4,20}$/', $medicao)) {
            avisar('O código de medição começa com G- (por exemplo, G-AB12CD34EF).', 'ruim');
        } elseif ($propriedade !== '' && !preg_match('/^\d{6,15}$/', $propriedade)) {
            avisar('O id da propriedade é só número (por exemplo, 512345678).', 'ruim');
        } else {
            Analytics::gravar($medicao, $propriedade, (int) Auth::usuarioOuNulo()['id']);
            Auditoria::registrar('alterou', 'ajustes', null, 'gravou o Google Analytics (' . ($medicao ?: 'sem código de medição') . ', propriedade ' . ($propriedade ?: 'nenhuma') . ')');
            avisar($medicao !== '' ? 'Gravado. O aviso de cookies já aparece nos portais.' : 'Gravado. Sem código de medição, os portais ficam sem Analytics e sem aviso de cookies.');
        }
    } elseif ($acao === 'principais') {
        Analytics::confirmarPrincipais((int) Auth::usuarioOuNulo()['id']);
        Auditoria::registrar('alterou', 'ajustes', null, 'confirmou os eventos principais no Google Analytics');
        avisar('Anotado. O aviso saiu do Início.');
    } elseif ($acao === 'testar') {
        try {
            $n = Analytics::testar();
            avisar('Conectado: a conta de serviço lê a propriedade (' . plural($n, 'usuário', 'usuários') . ' nos últimos 7 dias).');
        } catch (Throwable $e) {
            avisar('Não conectou: ' . $e->getMessage(), 'ruim');
        }
    } elseif ($acao === 'puxar') {
        try {
            $log = Analytics::puxar();
            Auditoria::registrar('alterou', 'ajustes', null, 'puxou os números do Google Analytics');
            avisar('Números atualizados. ' . implode('; ', $log) . '.');
        } catch (Throwable $e) {
            avisar('Não deu para puxar: ' . $e->getMessage(), 'ruim');
        }
    }
    redirecionar('/configuracoes/analytics');
}

$medicao = Analytics::medicao();
$propriedade = Analytics::propriedade();
$conta = SearchConsole::chave();
$ultimo = Database::conexao()->query('SELECT MAX(dia) FROM analytics_diaria')->fetchColumn();
paginaInicio('Google Analytics', 'configuracoes');
?>
<div class="trilha"><a href="/configuracoes">Configurações</a><?= icone('seta') ?><span>Google Analytics</span></div>
<div class="cab-pag">
  <div><h1>Google Analytics</h1><p>De onde vêm as visitas dos portais (Instagram, WhatsApp, Google), de que cidade, em que aparelho e quanto tempo ficam. Uma propriedade para todos os portais; cada destino sai pelo endereço. O script do Google só carrega depois que o visitante aceita os cookies.</p></div>
</div>

<div class="colunas">
  <div class="cartao">
    <h2 class="titulo-secao">Conexão</h2>
    <ul class="lista-saude">
      <li><span class="item-saude"><?= icone('rede') ?><span class="txt-saude"><b>Código de medição</b><span><?= $medicao !== '' ? e($medicao) . ', com aviso de cookies nos portais' : 'Sem código: portais sem Analytics e sem aviso' ?></span></span>
        <span class="pilula <?= $medicao !== '' ? 'ok' : 'at' ?>"><?= $medicao !== '' ? 'No portal' : 'Desligado' ?></span></span></li>
      <li><span class="item-saude"><?= icone('chave') ?><span class="txt-saude"><b>Leitura</b><span><?= $propriedade !== '' ? 'Propriedade ' . e($propriedade) : 'Falta o id da propriedade' ?><?= $conta ? ', pela conta ' . e($conta['client_email']) : ', e falta a conta de serviço (Google Search Console)' ?></span></span>
        <span class="pilula <?= Analytics::configurado() ? 'ok' : 'ruim' ?>"><?= Analytics::configurado() ? 'Pronta' : 'Incompleta' ?></span></span></li>
      <?php $lembrete = Analytics::lembrete(); ?>
      <li><span class="item-saude"><?= icone('alerta') ?><span class="txt-saude"><b>Eventos principais</b><span><?= match ($lembrete) {
            'marcar' => 'Os cliques chegaram: marque a estrela de clique_whatsapp e clique_rota em Administrador, Eventos, no Google Analytics',
            'feito' => 'clique_whatsapp e clique_rota marcados',
            default => 'Esperando os primeiros cliques em WhatsApp e Como chegar; quando chegarem, vem um e-mail para marcar',
        } ?></span></span>
        <span class="pilula <?= $lembrete === 'feito' ? 'ok' : ($lembrete === 'marcar' ? 'ruim' : 'at') ?>"><?= $lembrete === 'feito' ? 'Marcados' : ($lembrete === 'marcar' ? 'Marcar agora' : 'Esperando') ?></span></span></li>
      <li><span class="item-saude"><?= icone('calendario') ?><span class="txt-saude"><b>Dados até</b><span><?= $ultimo ? e(dataBr((string) $ultimo, 'd/m/Y')) . ' (lidos todo dia às 7h30)' : 'Nada puxado ainda' ?></span></span></span></li>
    </ul>
    <?php if (Analytics::configurado()): ?>
    <form method="post" action="/configuracoes/analytics" class="cartao-rodape">
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <button type="submit" class="botao claro" name="acao" value="testar"><?= icone('check') ?>Testar</button>
      <button type="submit" class="botao claro" name="acao" value="puxar"><?= icone('baixar') ?>Puxar agora</button>
      <?php if ($lembrete !== 'feito'): ?><button type="submit" class="botao claro" name="acao" value="principais"><?= icone('check') ?>Já marquei os eventos principais</button><?php endif; ?>
    </form>
    <?php endif; ?>
  </div>

  <div class="cartao">
    <h2 class="titulo-secao">Propriedade</h2>
    <form method="post" action="/configuracoes/analytics">
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="gravar">
      <div class="grade">
        <div class="campo c-6">
          <label for="medicao">Código de medição</label>
          <div class="entrada"><?= icone('rede') ?><input type="text" id="medicao" name="medicao" value="<?= e($medicao) ?>" placeholder="G-AB12CD34EF" autocomplete="off" spellcheck="false"></div>
        </div>
        <div class="campo c-6">
          <label for="propriedade">Id da propriedade</label>
          <div class="entrada"><?= icone('chave') ?><input type="text" id="propriedade" name="propriedade" value="<?= e($propriedade) ?>" placeholder="512345678" inputmode="numeric" autocomplete="off"></div>
        </div>
        <div class="campo c-12">
          <span class="ajuda">No Google Analytics: Administrador, Criar propriedade "InvenTour" (fuso de Brasília, real), fluxo da Web para inventour.com.br. O código G- está em Fluxos de dados; o id da propriedade, em Detalhes da propriedade. Em Gerenciamento de acesso à propriedade, dê o papel Leitor ao e-mail da conta de serviço<?= $conta ? ' (' . e($conta['client_email']) . ')' : '' ?>. Em Coleta de dados, deixe os Sinais do Google desligados. No Google Cloud, projeto da conta de serviço, ative a Google Analytics Data API (APIs e serviços, Biblioteca); não tem custo.</span>
        </div>
      </div>
      <div class="cartao-rodape"><button type="submit" class="botao"><?= icone('check') ?>Gravar</button></div>
    </form>
  </div>
</div>
<?php paginaFim();
