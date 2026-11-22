<?php
/**
 * Integração com o EventCity, por destino (etapa 5): liga a ponte, o
 * endereço e o segredo combinado (HMAC, docs/INTEGRACAO-INVENTOUR.md). O
 * segredo fica cifrado em ajustes e nunca volta para a tela. Mostra a
 * situação da agenda em cache e testa a conexão.
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/EventCity.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('configuracoes.ver');
$eid = (int) $empresa['id'];
$podeEditar = Auth::pode('configuracoes.editar');
$cfg = EventCity::config($eid);
$teste = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeEditar) {
        avisar('A página ficou aberta tempo demais, ou você não pode alterar as integrações.', 'ruim');
    } elseif (($_POST['acao'] ?? '') === 'testar') {
        $n = EventCity::atualizarAgenda($eid);
        avisar($n === null ? 'O EventCity não respondeu (ou recusou a assinatura). A agenda continua escondida no portal.'
            : 'O EventCity respondeu: ' . plural($n, 'evento', 'eventos') . ' nos próximos 90 dias.', $n === null ? 'atencao' : 'bom');
    } else {
        $valores = ['ligado' => empty($_POST['ligado']) ? '0' : '1',
                    'url' => rtrim(trim((string) ($_POST['url'] ?? '')), '/') ?: 'https://app.eventcity.com.br'];
        $cifradas = [];
        if (trim((string) ($_POST['segredo'] ?? '')) !== '') {
            $valores['segredo'] = trim((string) $_POST['segredo']);
            $cifradas[] = 'segredo';
        }
        if (!preg_match('#^https://[a-z0-9.-]+$#i', $valores['url'])) {
            avisar('O endereço do EventCity precisa começar com https://.', 'ruim');
        } else {
            Ajustes::salvar('eventcity', $valores, $eid, (int) Auth::usuarioOuNulo()['id'], $cifradas);
            Auditoria::registrar('alterou', 'ajustes', null, 'alterou a integração com o EventCity' . ($cifradas ? ' (segredo trocado)' : ''), null,
                ['ligado' => $valores['ligado'], 'url' => $valores['url']], $eid);
            avisar('Integração salva.');
        }
    }
    redirecionar('/configuracoes/eventcity');
}
$agenda = EventCity::agendaEmCache($eid);
paginaInicio('EventCity', 'configuracoes');
?>
<div class="trilha"><a href="/configuracoes">Configurações</a><?= icone('seta') ?><span>EventCity</span></div>
<form method="post" action="/configuracoes/eventcity">
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <div class="cab-pag">
    <div><h1>EventCity</h1><p>A agenda de eventos do destino no portal e o aviso dos lugares homologados para o EventCity.</p></div>
    <?php if ($podeEditar): ?><div class="acoes">
      <button type="submit" class="botao claro" name="acao" value="testar"<?= $cfg['ligado'] && $cfg['segredo'] !== '' ? '' : ' disabled title="Ligue e informe o segredo antes"' ?>><?= icone('trocar') ?>Testar agora</button>
      <button type="submit" class="botao" name="acao" value="salvar"><?= icone('check') ?>Salvar</button></div><?php endif; ?>
  </div>
  <div class="cartao">
    <div class="grade">
      <div class="campo c-12"><?= interruptor('ligado', 'Ponte com o EventCity', $cfg['ligado'], 'Ligada: a agenda entra no portal e os lugares homologados são avisados',
          'Desligada: o portal não mostra a agenda', !$podeEditar) ?></div>
      <div class="campo c-6"><label for="url">Endereço do EventCity</label>
        <div class="entrada"><input type="text" id="url" name="url" maxlength="120" value="<?= e($cfg['url']) ?>"<?= $podeEditar ? '' : ' readonly' ?>></div></div>
      <div class="campo c-6"><label for="segredo">Segredo combinado</label>
        <div class="entrada"><input type="password" id="segredo" name="segredo" maxlength="200" autocomplete="new-password"
          placeholder="<?= $cfg['segredo'] !== '' ? 'Guardado. Digite só para trocar' : 'O mesmo cadastrado no EventCity' ?>"<?= $podeEditar ? '' : ' readonly' ?>></div>
        <span class="ajuda">Fica cifrado e não volta para a tela.</span></div>
    </div>
  </div>
</form>
<div class="cartao">
  <h2 class="titulo-secao">Situação</h2>
  <p><?= $agenda !== null ? 'Agenda no portal: ' . plural(count($agenda), 'evento', 'eventos') . ' nos próximos 90 dias, atualizada a cada 5 minutos.'
      : 'A agenda não está no portal: a ponte está desligada ou o EventCity ainda não respondeu.' ?></p>
</div>
<?php paginaFim();
