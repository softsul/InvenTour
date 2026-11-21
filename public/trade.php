<?php
/**
 * Trade turístico: os pedidos de responsável, as revisões que os
 * responsáveis mandaram e quem responde por cada lugar.
 * Softsul Software
 *
 * Abas: Pedidos (o que espera análise primeiro; o que confere com o
 * Cadastur se aprova aqui mesmo, num clique), Revisões (o antes e o depois
 * de cada proposta) e Responsáveis (quem responde hoje, com a revogação e o
 * histórico). Cada pedido e cada revisão abre em modal (reivindicacao.php,
 * revisao.php). Só em destino ativo; nos outros modos a tela explica.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Trade.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('trade.ver');
Auth::exigirModuloLigado('trade');
$podeEditar = Auth::pode('trade.editar');

// Aprovar num clique (pedido que confere), direto da lista.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeEditar) {
        avisar('A página ficou aberta tempo demais, ou você não pode aprovar pedidos.', 'ruim');
    } else {
        $pedido = Trade::pedido((int) ($_POST['id'] ?? 0));
        if ($pedido && Trade::confere($pedido['conferencia'])) {
            $r = Trade::aprovar((int) $pedido['id']);
            if ($r['erro']) {
                avisar($r['erro'], 'atencao');
            } else {
                avisar($pedido['nome'] . ' agora responde por ' . $pedido['item_nome'] . ($r['transferiu'] ? ' (no lugar de ' . $r['transferiu'] . ')' : '') . '.');
                if ($r['link']) {
                    $_SESSION['trade_convite'] = ['nome' => $pedido['nome'], 'whatsapp' => $pedido['whatsapp'], 'link' => $r['link'], 'enviado' => $r['enviado']];
                }
            }
        } else {
            avisar('Este pedido precisa ser aberto para conferir o comprovante.', 'atencao');
        }
    }
    redirecionar('/trade');
}
$convite = $_SESSION['trade_convite'] ?? null;
unset($_SESSION['trade_convite']);

$aberto = Trade::aberto($empresa);
$pedidos = Trade::pedidos(['pendente', 'rejeitada']);
$pendentes = array_values(array_filter($pedidos, fn ($p) => $p['situacao'] === 'pendente'));
$rejeitados = array_values(array_filter($pedidos, fn ($p) => $p['situacao'] === 'rejeitada'));
$revisoes = Trade::revisoes(['aguardando', 'devolvida', 'aprovada', 'rejeitada']);
$esperando = array_values(array_filter($revisoes, fn ($v) => $v['situacao'] === 'aguardando'));
$responsaveis = Trade::pedidos(['aprovada', 'revogada']);
$aba = in_array($_GET['aba'] ?? '', ['pedidos', 'revisoes', 'responsaveis'], true) ? (string) $_GET['aba']
    : (!$pendentes && $esperando ? 'revisoes' : 'pedidos');

paginaInicio('Trade turístico', 'trade');
?>
<div class="cab-pag">
  <div>
    <h1>Trade turístico</h1>
    <p>Quem responde pelos lugares de <?= e((string) $empresa['nome_fantasia']) ?> pede o acesso pela ficha do portal. Aqui a equipe confere o pedido e cada mudança que o responsável manda: o portal só muda depois da aprovação.</p>
  </div>
</div>

<?php if (!$aberto): ?>
  <p class="aviso info"><?= icone('alerta') ?><span>O trade só pede acesso em destino ativo (licenciado). Neste modo, o botão "Sou o responsável" não aparece no portal e só a equipe mexe nas fichas.</span></p>
<?php endif; ?>

<?php if ($convite): ?>
  <div class="cartao">
    <h2 class="titulo-secao">Link para <?= e((string) $convite['nome']) ?> criar a senha</h2>
    <p class="ajuda"><?= $convite['enviado'] ? 'O link foi por e-mail. Se preferir, mande também pelo WhatsApp.' : 'O e-mail não saiu. Copie o link e mande pelo WhatsApp, só para a pessoa.' ?> Vale por 7 dias e serve uma vez só.</p>
    <div class="copiavel">
      <div class="entrada"><input readonly value="<?= e((string) $convite['link']) ?>" id="link-trade" aria-label="Link para criar a senha" class="dado"></div>
      <button type="button" class="botao claro" data-copiar="link-trade"><?= icone('copiar') ?>Copiar</button>
      <?php if ($convite['whatsapp']): ?>
        <a class="botao claro" target="_blank" rel="noopener" href="https://wa.me/<?= e((string) $convite['whatsapp']) ?>?text=<?= rawurlencode('Olá! O seu acesso ao InvenTour foi aprovado. Por este link você cria a sua senha: ' . $convite['link']) ?>"><?= icone('whatsapp') ?>WhatsApp</a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<nav class="abas-chamado" role="tablist" aria-label="Partes do trade">
  <?php foreach (['pedidos' => ['Pedidos', 'usuarios', count($pendentes)], 'revisoes' => ['Revisões', 'lapis', count($esperando)], 'responsaveis' => ['Responsáveis', 'predio', null]] as $k => [$rot, $ic, $n]): ?>
    <button type="button" role="tab" data-aba="<?= $k ?>" aria-selected="<?= $aba === $k ? 'true' : 'false' ?>"><?= icone($ic) ?><?= e($rot) ?><?= $n ? '<span class="conta-aba">' . $n . '</span>' : '' ?></button>
  <?php endforeach; ?>
</nav>

<div class="painel-aba" data-painel="pedidos"<?= $aba === 'pedidos' ? '' : ' hidden' ?>>
  <?php if (!$pendentes && !$rejeitados): ?>
    <div class="vazio"><?= icone('usuarios') ?><p><strong>Nenhum pedido ainda.</strong></p>
      <p>O responsável por um lugar homologado pede o acesso pelo link "Sou o responsável por este lugar", no fim da ficha do portal.</p></div>
  <?php else: ?>
    <div class="rolagem">
      <table class="dados">
        <thead><tr><th>Quem pede</th><th>Lugar</th><th>Conferência</th><th>Situação</th><th>Pedido em</th><th class="acao"></th></tr></thead>
        <tbody>
        <?php foreach (array_merge($pendentes, $rejeitados) as $p):
          [$rot, $tom] = Trade::SITUACOES[$p['situacao']];
          $confere = Trade::confere($p['conferencia']);
          $url = '/reivindicacao?id=' . (int) $p['id']; ?>
          <tr>
            <td class="elastica"><span class="cli"><?= avatarIniciais((string) $p['nome'], 'avatar') ?>
              <span class="txt"><b><a href="<?= e($url) ?>" data-ficha="Pedido de <?= e($p['nome']) ?>"><?= e((string) $p['nome']) ?></a></b>
              <span><?= e(Trade::VINCULOS[$p['vinculo']] ?? '') ?></span></span></span></td>
            <td><?= e((string) $p['item_nome']) ?> <span class="dado"><?= e((string) $p['ficha_codigo']) ?></span>
              <?php if ((int) $p['tem_responsavel'] && $p['situacao'] === 'pendente'): ?><br><span class="pilula at">Já tem responsável: transferência</span><?php endif; ?></td>
            <td><?= $confere ? '<span class="pilula ok">Confere com o Cadastur</span>' : '<span class="pilula nu">Conferir o comprovante</span>' ?></td>
            <td><span class="pilula <?= $tom ?>"><?= e($rot) ?></span></td>
            <td class="dado"><?= e(dataBr((string) $p['criado_em'], 'd/m/Y')) ?></td>
            <td class="acao"><div class="acoes-linha">
              <?php if ($confere && $p['situacao'] === 'pendente' && $podeEditar): ?>
                <form method="post" action="/trade"><input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="aprovar" data-confirmar="Aprovar <?= e($p['nome']) ?> como responsável por <?= e($p['item_nome']) ?>?" data-balao-titulo="Aprovar agora" data-balao="O Cadastur e o contato conferem. Aprova e gera o link para a pessoa criar a senha." aria-label="Aprovar <?= e($p['nome']) ?>"><?= icone('check') ?></button></form>
              <?php endif; ?>
              <a href="<?= e($url) ?>" data-ficha="Pedido de <?= e($p['nome']) ?>" data-balao-titulo="Abrir" data-balao="O pedido, o comprovante e a decisão." aria-label="Abrir o pedido de <?= e($p['nome']) ?>"><?= icone('olho') ?></a>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="painel-aba" data-painel="revisoes"<?= $aba === 'revisoes' ? '' : ' hidden' ?>>
  <?php if (!$revisoes): ?>
    <div class="vazio"><?= icone('lapis') ?><p><strong>Nenhuma revisão ainda.</strong></p>
      <p>Quando um responsável muda horário, contato, capacidade ou fotos em Meus lugares, a proposta chega aqui com o antes e o depois.</p></div>
  <?php else: ?>
    <div class="rolagem">
      <table class="dados">
        <thead><tr><th>Lugar</th><th>O que muda</th><th>Quem mandou</th><th>Situação</th><th>Em</th><th class="acao"></th></tr></thead>
        <tbody>
        <?php foreach ($revisoes as $v):
          [$rot, $tom] = Trade::SITUACOES_REVISAO[$v['situacao']];
          $campos = array_map(fn ($c) => Trade::CAMPOS[$c] ?? $c, array_keys($v['alteracoes']));
          if ($v['fotos']) { $campos[] = plural(count($v['fotos']), 'foto nova', 'fotos novas'); }
          $url = '/revisao?id=' . (int) $v['id']; ?>
          <tr>
            <td class="elastica"><b><a href="<?= e($url) ?>" data-ficha="Revisão de <?= e($v['item_nome']) ?>"><?= e((string) $v['item_nome']) ?></a></b> <span class="dado"><?= e((string) $v['ficha_codigo']) ?></span></td>
            <td><?= e(implode(', ', $campos)) ?></td>
            <td><?= e((string) $v['autor_nome']) ?></td>
            <td><span class="pilula <?= $tom ?>"><?= e($rot) ?></span></td>
            <td class="dado"><?= e(dataBr((string) ($v['moderado_em'] ?: $v['atualizado_em'] ?: $v['criado_em']), 'd/m/Y')) ?></td>
            <td class="acao"><div class="acoes-linha"><a href="<?= e($url) ?>" data-ficha="Revisão de <?= e($v['item_nome']) ?>" data-balao-titulo="Abrir" data-balao="O antes e o depois, para aprovar, devolver ou rejeitar." aria-label="Abrir a revisão de <?= e($v['item_nome']) ?>"><?= icone('olho') ?></a></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="painel-aba" data-painel="responsaveis"<?= $aba === 'responsaveis' ? '' : ' hidden' ?>>
  <?php if (!$responsaveis): ?>
    <div class="vazio"><?= icone('predio') ?><p><strong>Nenhum lugar tem responsável ainda.</strong></p>
      <p>Aprovado o pedido, o responsável aparece aqui. Para tirar o acesso (venda do ponto, saída da empresa), abra e revogue; o histórico da ficha fica.</p></div>
  <?php else: ?>
    <div class="rolagem">
      <table class="dados">
        <thead><tr><th>Responsável</th><th>Lugar</th><th>Situação</th><th>Desde</th><th class="acao"></th></tr></thead>
        <tbody>
        <?php foreach ($responsaveis as $p): [$rot, $tom] = Trade::SITUACOES[$p['situacao']]; $url = '/reivindicacao?id=' . (int) $p['id']; ?>
          <tr>
            <td class="elastica"><span class="cli"><?= avatarIniciais((string) $p['nome'], 'avatar') ?>
              <span class="txt"><b><a href="<?= e($url) ?>" data-ficha="Responsável <?= e($p['nome']) ?>"><?= e((string) $p['nome']) ?></a></b><span><?= e(Trade::VINCULOS[$p['vinculo']] ?? '') ?></span></span></span></td>
            <td><?= e((string) $p['item_nome']) ?> <span class="dado"><?= e((string) $p['ficha_codigo']) ?></span></td>
            <td><span class="pilula <?= $tom ?>"><?= e($rot) ?></span><?= $p['situacao'] === 'revogada' && $p['motivo'] ? '<br><span class="nota">' . e((string) $p['motivo']) . '</span>' : '' ?></td>
            <td class="dado"><?= e(dataBr((string) ($p['analisado_em'] ?: $p['criado_em']), 'd/m/Y')) ?></td>
            <td class="acao"><div class="acoes-linha"><a href="<?= e($url) ?>" data-ficha="Responsável <?= e($p['nome']) ?>" data-balao-titulo="Abrir" data-balao="O pedido aprovado, o link de acesso e a revogação." aria-label="Abrir <?= e($p['nome']) ?>"><?= icone('olho') ?></a></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php paginaFim();
