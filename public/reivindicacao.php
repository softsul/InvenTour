<?php
/**
 * Um pedido de responsável, em modal: quem pede, o que conferiu, o
 * comprovante (no visualizador) e a decisão.
 * Softsul Software
 *
 * Pendente: aprovar (quando o lugar já tem responsável, a aprovação é a
 * transferência de titularidade) ou rejeitar com o motivo. Aprovado:
 * revogar com o motivo e, se a pessoa ainda não criou a senha, gerar o link
 * de novo. O link aparece aqui para copiar ou mandar pelo WhatsApp.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Trade.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('trade.ver');
$r = Trade::pedido((int) ($_GET['id'] ?? 0)) ?: negarRegistro();
$id = (int) $r['id'];
$podeEditar = Auth::pode('trade.editar');
$aqui = '/reivindicacao?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeEditar) {
        avisar('A página ficou aberta tempo demais, ou você não pode decidir pedidos.', 'ruim');
    } elseif ($acao === 'aprovar') {
        $res = Trade::aprovar($id);
        if ($res['erro']) {
            avisar($res['erro'], 'atencao');
        } else {
            avisar($r['nome'] . ' agora responde por ' . $r['item_nome'] . ($res['transferiu'] ? ', no lugar de ' . $res['transferiu'] : '') . '.');
            if ($res['link']) {
                $_SESSION['trade_convite_' . $id] = ['link' => $res['link'], 'enviado' => $res['enviado']];
            }
        }
    } elseif ($acao === 'rejeitar') {
        Trade::rejeitar($id, (string) ($_POST['motivo'] ?? '')) ? avisar('Pedido rejeitado.') : avisar('Escreva o motivo para rejeitar.', 'atencao');
    } elseif ($acao === 'revogar') {
        Trade::revogar($id, (string) ($_POST['motivo'] ?? '')) ? avisar($r['nome'] . ' não responde mais por ' . $r['item_nome'] . '.') : avisar('Escreva o motivo para revogar.', 'atencao');
    } elseif ($acao === 'link' && $r['situacao'] === 'aprovada' && $r['usuario_id']) {
        require_once __DIR__ . '/../app/Convite.php';
        $q = Database::conexao()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
        $q->execute([(int) $r['usuario_id']]);
        if (!$q->fetchColumn()) {
            $link = Convite::criar((int) $r['usuario_id'], 'convite', (int) Auth::usuarioOuNulo()['id']);
            $_SESSION['trade_convite_' . $id] = ['link' => $link, 'enviado' => Convite::enviar((string) $r['email'], (string) $r['nome'], $link, 'convite')];
            Auditoria::registrar('alterou', 'usuarios', (int) $r['usuario_id'], 'gerou convite novo para o responsável ' . $r['nome']);
        }
    }
    redirecionar(noPopup($aqui));
}
$convite = $_SESSION['trade_convite_' . $id] ?? null;
unset($_SESSION['trade_convite_' . $id]);

$atual = $r['situacao'] === 'pendente' ? Trade::responsavel((int) $r['item_id']) : null;
$semSenha = false;
if ($r['situacao'] === 'aprovada' && $r['usuario_id']) {
    $q = Database::conexao()->prepare('SELECT senha_hash IS NULL FROM usuarios WHERE id = ?');
    $q->execute([(int) $r['usuario_id']]);
    $semSenha = (bool) $q->fetchColumn();
}
[$rot, $tom] = Trade::SITUACOES[$r['situacao']];
$conf = $r['conferencia'] ?? [];
$confere = Trade::confere($conf);
$ehPdf = $r['comprovante_tipo'] === 'application/pdf';

paginaFicha('Pedido de ' . $r['nome'], 'trade');
?>
<div class="trilha"><a href="/trade">Trade turístico</a><?= icone('seta') ?><span>Pedido de <?= e((string) $r['nome']) ?></span></div>
<div class="cab-pag">
  <div>
    <h1 data-titulo><?= e((string) $r['nome']) ?></h1>
    <p><?= e((Trade::VINCULOS[$r['vinculo']] ?? '') . ' · ' . $r['item_nome']) ?> <span class="dado"><?= e((string) $r['ficha_codigo']) ?></span> · <span class="pilula <?= $tom ?>"><?= e($rot) ?></span></p>
  </div>
</div>

<?php if ($convite): ?>
  <div class="cartao">
    <h2 class="titulo-secao">Link para criar a senha</h2>
    <p class="ajuda"><?= $convite['enviado'] ? 'O link foi por e-mail. Se preferir, mande também pelo WhatsApp.' : 'O e-mail não saiu. Copie o link e mande pelo WhatsApp, só para a pessoa.' ?> Vale por 7 dias e serve uma vez só.</p>
    <div class="copiavel">
      <div class="entrada"><input readonly value="<?= e((string) $convite['link']) ?>" id="link-trade" aria-label="Link para criar a senha" class="dado"></div>
      <button type="button" class="botao claro" data-copiar="link-trade"><?= icone('copiar') ?>Copiar</button>
      <?php if ($r['whatsapp']): ?>
        <a class="botao claro" target="_blank" rel="noopener" href="https://wa.me/<?= e((string) $r['whatsapp']) ?>?text=<?= rawurlencode('Olá! O seu acesso ao InvenTour para ' . $r['item_nome'] . ' foi aprovado. Por este link você cria a sua senha: ' . $convite['link']) ?>"><?= icone('whatsapp') ?>WhatsApp</a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="cartao">
  <h2 class="titulo-secao">Conferência</h2>
  <?php if ($confere): ?>
    <p class="aviso bom"><?= icone('check') ?><span>Confere: <?= e(implode(', ', Trade::conferenciaEmTexto($conf))) ?>.</span></p>
  <?php elseif (Trade::conferenciaEmTexto($conf)): ?>
    <p class="aviso atencao"><?= icone('alerta') ?><span>Confere em parte (<?= e(implode(', ', Trade::conferenciaEmTexto($conf))) ?>). Veja o comprovante antes de aprovar.</span></p>
  <?php else: ?>
    <p class="aviso atencao"><?= icone('alerta') ?><span>Nada conferiu sozinho com a ficha nem com o Cadastur. Veja o comprovante e, se precisar, fale com a pessoa antes de aprovar.</span></p>
  <?php endif; ?>
  <dl class="dados-ficha">
    <dt>CNPJ informado</dt><dd class="dado"><?= $r['cnpj'] ? e(formatarCnpj((string) $r['cnpj'])) : '<span class="nulo">-</span>' ?></dd>
    <dt>CNPJ da ficha</dt><dd class="dado"><?= $r['item_cnpj'] ? e(formatarCnpj((string) $r['item_cnpj'])) : '<span class="nulo">-</span>' ?></dd>
    <dt>Cadastur informado</dt><dd class="dado"><?= $r['cadastur'] ? e((string) $r['cadastur']) : '<span class="nulo">-</span>' ?></dd>
    <dt>Cadastur da ficha</dt><dd class="dado"><?= $r['item_cadastur'] ? e((string) $r['item_cadastur']) : '<span class="nulo">-</span>' ?></dd>
    <dt>Comprovante</dt><dd><?php if ($r['comprovante']): ?><a class="botao claro mini" href="/trade-arquivo?pedido=<?= $id ?>" data-visualizar="<?= $ehPdf ? 'pdf' : 'foto' ?>" data-legenda="Comprovante de <?= e($r['nome']) ?>"><?= icone('olho') ?>Ver o comprovante</a><?php else: ?><span class="nulo">-</span><?php endif; ?></dd>
  </dl>
</div>

<div class="cartao">
  <h2 class="titulo-secao">Contato de quem pede</h2>
  <dl class="dados-ficha">
    <dt>E-mail</dt><dd><?= e((string) $r['email']) ?></dd>
    <dt>WhatsApp</dt><dd><?= $r['whatsapp'] ? '<a href="https://wa.me/' . e((string) $r['whatsapp']) . '" target="_blank" rel="noopener">' . e(formatarTelefone((string) $r['whatsapp'])) . '</a>' : '<span class="nulo">-</span>' ?></dd>
    <dt>Termo</dt><dd>aceito em <?= e(dataBr((string) $r['aceite_em'], 'd/m/Y H:i')) ?> (versão <?= e((string) $r['termo_versao']) ?>)</dd>
    <?php if ($r['analisado_em']): ?><dt>Analisado</dt><dd><?= e(dataBr((string) $r['analisado_em'], 'd/m/Y H:i')) ?> por <?= e((string) $r['analisado_nome']) ?></dd><?php endif; ?>
    <?php if ($r['revogado_em']): ?><dt>Revogado</dt><dd><?= e(dataBr((string) $r['revogado_em'], 'd/m/Y H:i')) ?> por <?= e((string) $r['revogado_nome']) ?></dd><?php endif; ?>
    <?php if ($r['motivo']): ?><dt>Motivo</dt><dd><?= e((string) $r['motivo']) ?></dd><?php endif; ?>
  </dl>
  <p class="ajuda">Este contato é só para o acesso e para a equipe falar com a pessoa. Não vai para a ficha pública.</p>
</div>

<?php if ($podeEditar && $r['situacao'] === 'pendente'): ?>
  <div class="cartao">
    <h2 class="titulo-secao"><?= $atual ? 'Transferir a titularidade' : 'Decisão' ?></h2>
    <?php if ($atual): ?>
      <p class="aviso atencao"><?= icone('alerta') ?><span><?= e((string) $atual['nome']) ?> responde hoje por este lugar. Ao aprovar, o acesso de <?= e((string) $atual['nome']) ?> a ele termina e a revisão que estiver esperando é recusada. O histórico da ficha fica.</span></p>
    <?php endif; ?>
    <form method="post" action="<?= e($aqui) ?>">
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="aprovar">
      <div class="cartao-rodape"><button type="submit" class="botao" data-confirmar="<?= $atual ? 'Transferir para ' . e($r['nome']) . '?' : 'Aprovar ' . e($r['nome']) . '?' ?>"><?= icone('check') ?><?= $atual ? 'Transferir para ' . e((string) $r['nome']) : 'Aprovar' ?></button></div>
    </form>
    <form method="post" action="<?= e($aqui) ?>">
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="rejeitar">
      <div class="campo"><label for="motivo">Motivo, para rejeitar</label>
        <div class="entrada"><textarea id="motivo" name="motivo" rows="2" maxlength="500" placeholder="O comprovante é de outra empresa, o CNPJ não confere..."></textarea></div></div>
      <div class="cartao-rodape"><button type="submit" class="botao claro perigo"><?= icone('fechar') ?>Rejeitar</button></div>
    </form>
  </div>
<?php elseif ($podeEditar && $r['situacao'] === 'aprovada'): ?>
  <div class="cartao">
    <h2 class="titulo-secao">Acesso</h2>
    <?php if ($semSenha): ?>
      <p class="ajuda"><?= e((string) $r['nome']) ?> ainda não criou a senha.</p>
      <form method="post" action="<?= e($aqui) ?>"><input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="link">
        <div class="cartao-rodape"><button type="submit" class="botao claro"><?= icone('chave') ?>Gerar o link de novo</button></div></form>
    <?php endif; ?>
    <form method="post" action="<?= e($aqui) ?>">
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="revogar">
      <div class="campo"><label for="motivo">Motivo, para revogar</label>
        <div class="entrada"><textarea id="motivo" name="motivo" rows="2" maxlength="500" placeholder="Vendeu o ponto, saiu da empresa..."></textarea></div></div>
      <div class="cartao-rodape"><button type="submit" class="botao claro perigo" data-confirmar="Tirar o acesso de <?= e($r['nome']) ?> a <?= e($r['item_nome']) ?>?"><?= icone('cadeado') ?>Revogar o acesso</button></div>
    </form>
  </div>
<?php endif; ?>
<?php paginaFichaFim();
