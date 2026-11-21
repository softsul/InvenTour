<?php
/** Um roteiro: as paradas em ordem, com a dica de cada uma e a rota no mapa. Softsul Software. */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
$r = Portal::roteiro($eid, (int) ($_GET['id'] ?? 0)) ?? naoAchou($destino, 'Este roteiro não está publicado.');
$rota = Portal::rotaNoMapa($r['paradas']);
$primeira = reset($r['paradas']);
PortalLayout::inicio($destino, (string) $r['titulo'], true, ['ativo' => 'roteiros', 'imagem' => $primeira ? Portal::foto($eid, $primeira['foto'], 'm') : null,
    'descricao' => $r['descricao'] ? mb_strimwidth((string) $r['descricao'], 0, 200, '...') : 'Roteiro em ' . $destino['nome_fantasia'] . '.']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> › <a href="<?= e(Portal::url('/roteiros')) ?>">Roteiros</a> › <span><?= e((string) $r['titulo']) ?></span></nav>
<h1><?= e((string) $r['titulo']) ?></h1>
<p class="lead"><?= plural(count($r['paradas']), 'parada', 'paradas') ?><?= $r['duracao'] ? ' · ' . e((string) $r['duracao']) : '' ?></p>
<?php if ($r['descricao']): ?><div class="texto"><?= nl2br(e((string) $r['descricao'])) ?></div><?php endif; ?>
<?php if ($rota): ?><p><a class="cta" href="<?= e($rota) ?>" rel="noopener"><?= icone('mapa') ?>Abrir a rota no Google Maps</a></p><?php endif; ?>
<ol class="paradas">
  <?php foreach ($r['paradas'] as $p): ?>
    <li><?= PortalLayout::cartao($eid, $p) ?><?php if ($p['nota']): ?><p class="nota-parada"><?= e((string) $p['nota']) ?></p><?php endif; ?></li>
  <?php endforeach; ?>
</ol>
<?php PortalLayout::fim();
