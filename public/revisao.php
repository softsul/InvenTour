<?php
/**
 * Uma revisão do responsável, em modal: o que está na ficha e o que ele
 * propõe, lado a lado, com as fotos novas.
 * Softsul Software
 *
 * A equipe aprova (a ficha muda, a última conferência renova e o EventCity
 * recebe a versão nova), devolve com o que ajustar (o responsável vê a nota
 * em Meus lugares e manda de novo) ou rejeita. Se a ficha mudou desde a
 * proposta, a linha avisa: aprovando, vale o que o responsável mandou.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Trade.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('trade.ver');
$v = Trade::revisao((int) ($_GET['id'] ?? 0)) ?: negarRegistro();
$id = (int) $v['id'];
$podeEditar = Auth::pode('trade.editar');
$aqui = '/revisao?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeEditar) {
        avisar('A página ficou aberta tempo demais, ou você não pode conferir revisões.', 'ruim');
        redirecionar(noPopup($aqui));
    }
    if ($acao === 'aprovar') {
        $res = Trade::aprovarRevisao($id);
        if ($res === null) {
            avisar('Esta revisão já foi conferida.', 'atencao');
            redirecionar(noPopup($aqui));
        }
        avisar('Revisão aprovada: ' . $v['item_nome'] . ' foi atualizado no portal.');
        if ($res['mudaram']) {
            avisar('A ficha tinha mudado desde a proposta em: ' . implode(', ', $res['mudaram']) . '. Ficou o que o responsável mandou.', 'atencao');
        }
        foreach ($res['falhas'] as $f) {
            avisar('Uma foto não entrou: ' . $f, 'atencao');
        }
        fichaSalva('/trade?aba=revisoes');
    }
    if (in_array($acao, ['devolvida', 'rejeitada'], true)) {
        if (Trade::moderar($id, $acao, (string) ($_POST['nota'] ?? ''))) {
            avisar($acao === 'devolvida' ? 'Revisão devolvida para ' . $v['autor_nome'] . ' com a sua nota.' : 'Revisão rejeitada.');
            fichaSalva('/trade?aba=revisoes');
        }
        avisar('Escreva a nota para o responsável.', 'atencao');
    }
    redirecionar(noPopup($aqui));
}

$item = Inventario::buscar((int) $v['item_id']) ?: negarRegistro();
$atuais = Trade::valoresAtuais($item);
[$rot, $tom] = Trade::SITUACOES_REVISAO[$v['situacao']];
$eid = (int) $empresa['id'];

paginaFicha('Revisão de ' . $v['item_nome'], 'trade');
?>
<div class="trilha"><a href="/trade?aba=revisoes">Trade turístico</a><?= icone('seta') ?><span>Revisão de <?= e((string) $v['item_nome']) ?></span></div>
<div class="cab-pag">
  <div>
    <h1 data-titulo><?= e((string) $v['item_nome']) ?></h1>
    <p>Mandada por <?= e((string) $v['autor_nome']) ?> em <?= e(dataBr((string) ($v['atualizado_em'] ?: $v['criado_em']), 'd/m/Y H:i')) ?> · <span class="pilula <?= $tom ?>"><?= e($rot) ?></span></p>
  </div>
  <div class="acoes"><a class="botao claro" href="<?= e(noPopup(endereco('item', (int) $item['id'], (string) $item['nome']))) ?>"><?= icone('olho') ?>Ficha completa</a></div>
</div>

<?php if ($v['nota']): ?>
  <p class="aviso <?= $v['situacao'] === 'rejeitada' ? 'ruim' : 'atencao' ?>"><?= icone('alerta') ?><span>Nota da equipe<?= $v['moderado_nome'] ? ' (' . e((string) $v['moderado_nome']) . ')' : '' ?>: <?= e((string) $v['nota']) ?></span></p>
<?php endif; ?>

<?php if ($v['alteracoes']): ?>
<div class="cartao">
  <h2 class="titulo-secao">O que muda</h2>
  <div class="rolagem">
    <table class="dados comparar">
      <thead><tr><th>Campo</th><th><?= $v['situacao'] === 'aguardando' ? 'Hoje na ficha' : 'Antes' ?></th><th>Proposta</th></tr></thead>
      <tbody>
      <?php foreach ($v['alteracoes'] as $c => $par):
        $antes = Trade::legivel($c, $par['antes']);
        $hoje = $v['situacao'] === 'aguardando' && array_key_exists($c, $atuais) ? Trade::legivel($c, $atuais[$c]) : $antes; ?>
        <tr>
          <td><b><?= e(Trade::CAMPOS[$c] ?? $c) ?></b></td>
          <td><?= $hoje !== '' ? e($hoje) : '<span class="nulo">vazio</span>' ?>
            <?php if ($hoje !== $antes): ?><br><span class="nota"><?= icone('alerta') ?>A ficha mudou depois da proposta (era: <?= e($antes !== '' ? $antes : 'vazio') ?>).</span><?php endif; ?></td>
          <td class="proposta"><?= ($d = Trade::legivel($c, $par['depois'])) !== '' ? e($d) : '<span class="nulo">vazio</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($v['fotos']): ?>
<div class="cartao">
  <h2 class="titulo-secao"><?= plural(count($v['fotos']), 'Foto nova', 'Fotos novas') ?></h2>
  <div class="galeria-item">
    <?php foreach ($v['fotos'] as $f): $u = '/trade-arquivo?revisao=' . $id . '&f=' . rawurlencode((string) $f['arquivo']); ?>
      <figure><a href="<?= e($u) ?>" data-visualizar="foto" data-galeria="revisao-<?= $id ?>"><img src="<?= e($u . '&mini=1') ?>" alt="Foto nova de <?= e($v['item_nome']) ?>" loading="lazy"></a></figure>
    <?php endforeach; ?>
  </div>
  <p class="ajuda">Ao aprovar, entram na ficha com o crédito de <?= e((string) $v['autor_nome']) ?>. A capa continua a mesma.</p>
</div>
<?php endif; ?>

<?php if ($podeEditar && $v['situacao'] === 'aguardando'): ?>
<div class="cartao">
  <h2 class="titulo-secao">Conferência</h2>
  <form method="post" action="<?= e($aqui) ?>">
    <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="aprovar">
    <p class="ajuda">Aprovando, a ficha muda agora, o portal mostra a versão nova e o EventCity recebe o aviso.</p>
    <div class="cartao-rodape"><button type="submit" class="botao"><?= icone('check') ?>Aprovar e atualizar a ficha</button></div>
  </form>
  <form method="post" action="<?= e($aqui) ?>">
    <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
    <div class="campo"><label for="nota">Nota para o responsável</label>
      <div class="entrada"><textarea id="nota" name="nota" rows="3" maxlength="1000" placeholder="O que ajustar, ou por que não vale"></textarea></div></div>
    <div class="cartao-rodape">
      <button type="submit" class="botao claro" name="acao" value="devolvida"><?= icone('trocar') ?>Devolver para ajuste</button>
      <button type="submit" class="botao claro perigo" name="acao" value="rejeitada"><?= icone('fechar') ?>Rejeitar</button>
    </div>
  </form>
</div>
<?php endif; ?>
<?php paginaFichaFim();
