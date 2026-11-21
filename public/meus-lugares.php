<?php
/**
 * Meus lugares: o que o responsável (papel prestador) mantém no portal.
 * Softsul Software
 *
 * Só os lugares do pedido aprovado, no destino atual. Cada um abre em modal
 * (meu-lugar.php) para mandar a atualização; a ficha do portal só muda
 * depois da conferência da equipe, e o cartão diz em que pé está.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Trade.php';
require_once __DIR__ . '/../app/Caderno.php';
require_once __DIR__ . '/../app/Portal.php';

$eu = Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('meus-lugares.ver');
$eid = (int) $empresa['id'];
$lugares = Trade::meusLugares((int) $eu['id']);
$portal = Caderno::portal($empresa);

paginaInicio('Meus lugares', 'meus-lugares');
?>
<div class="cab-pag">
  <div>
    <h1>Meus lugares</h1>
    <p>Mantenha horário, contato, capacidade e fotos em dia no portal de turismo de <?= e((string) $empresa['nome_fantasia']) ?>. A equipe do destino confere cada mudança antes de ela aparecer para o turista.</p>
  </div>
</div>

<?php if (!$lugares): ?>
  <div class="vazio"><?= icone('predio') ?><p><strong>Nenhum lugar ligado à sua conta.</strong></p>
    <p>Procure o seu estabelecimento no <a href="<?= e($portal) ?>" target="_blank" rel="noopener">portal do destino</a> e, no fim da ficha, toque em "Sou o responsável por este lugar".</p></div>
<?php else: ?>
  <div class="grade-lugares">
    <?php foreach ($lugares as $l):
      $url = '/meu-lugar?id=' . (int) $l['id'];
      [$rotRev, $tomRev] = $l['revisao'] ? (Trade::SITUACOES_REVISAO[$l['revisao']] ?? ['', 'nu']) : ['', 'nu'];
      if ($l['revisao'] === 'aprovada') { $rotRev = 'Última atualização aprovada'; } ?>
      <article class="cartao cartao-lugar">
        <?php if ($l['foto']): ?><img src="<?= e(Inventario::urlFoto($eid, (string) $l['foto'], 'm')) ?>" alt="" loading="lazy"><?php else: ?><div class="sem-foto"><?= icone('camera') ?></div><?php endif; ?>
        <div class="cartao-lugar-txt">
          <h2><?= e((string) $l['nome']) ?></h2>
          <p><?= e((string) ($l['tipo_nome'] ?: $l['ficha_nome'])) ?></p>
          <?php if ($rotRev !== ''): ?><span class="pilula <?= $tomRev ?>"><?= e($rotRev) ?></span><?php endif; ?>
        </div>
        <div class="cartao-rodape">
          <a class="botao" href="<?= e($url) ?>" data-ficha="<?= e($l['nome']) ?>"><?= icone('lapis') ?>Atualizar</a>
          <?php if (in_array($l['situacao'], ['homologada', 'desatualizada'], true)): ?>
            <a class="botao claro" href="<?= e($portal . '/lugar/' . (int) $l['id'] . '-' . Portal::slug((string) $l['nome'])) ?>" target="_blank" rel="noopener"><?= icone('olho') ?>No portal</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php paginaFim();
