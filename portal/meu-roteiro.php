<?php
/**
 * Meu roteiro: os lugares que o visitante guardou (no próprio navegador,
 * sem conta e sem cookie). A lista chega no endereço (?i=12.40.7), o que já
 * é o link para compartilhar; o portal.js monta esse endereço a partir do
 * que está guardado. Softsul Software.
 */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
$ids = array_slice(array_filter(array_map('intval', explode('.', (string) ($_GET['i'] ?? '')))), 0, 30);
$itens = Portal::porIds($eid, $ids);
$rota = Portal::rotaNoMapa($itens);
PortalLayout::inicio($destino, 'Meu roteiro', false, ['ativo' => '']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> › <span>Meu roteiro</span></nav>
<h1>Meu roteiro</h1>
<div data-meu-roteiro data-ids="<?= e(implode('.', array_keys($itens))) ?>">
<?php if (!$itens): ?>
  <p class="aviso-vazio">Ainda não há nada aqui. Toque em <?= icone('mais') ?> nos lugares que quiser visitar e eles aparecem nesta página, guardados neste aparelho.</p>
<?php else: ?>
  <p class="lead"><?= plural(count($itens), 'lugar', 'lugares') ?>, na ordem em que você guardou.</p>
  <div class="acoes-roteiro">
    <?php if ($rota): ?><a target="_blank" rel="noopener" class="cta" href="<?= e($rota) ?>"><?= icone('mapa') ?>Abrir a rota no Google Maps</a><?php endif; ?>
    <button type="button" class="cta claro" data-compartilhar-roteiro><?= icone('enviar') ?>Compartilhar o link</button>
    <button type="button" class="cta claro" data-limpar-roteiro><?= icone('lixeira') ?>Limpar</button>
  </div>
  <ol class="paradas"><?php foreach ($itens as $i): ?><li><?= PortalLayout::cartao($eid, $i) ?></li><?php endforeach; ?></ol>
<?php endif; ?>
</div>
<?php PortalLayout::fim();
