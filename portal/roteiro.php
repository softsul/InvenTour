<?php
/**
 * Um roteiro em linha do tempo: a hora de chegada de cada parada, o tempo no
 * lugar, o deslocamento desde a anterior, a dica e a rota no mapa.
 * Softsul Software.
 */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
$r = Portal::roteiro($eid, (int) ($_GET['id'] ?? 0)) ?? naoAchou($destino, 'Este roteiro não está publicado.');
$rota = Portal::rotaNoMapa($r['paradas']);
$primeira = reset($r['paradas']);
require_once __DIR__ . '/../app/PortalSeo.php';
PortalLayout::inicio($destino, (string) $r['titulo'], true, ['ativo' => 'roteiros',
    'jsonld' => PortalSeo::roteiro($destino, $r, '/roteiro/' . (int) $r['id'] . '-' . Portal::slug((string) $r['titulo'])), 'imagem' => $primeira ? Portal::foto($eid, $primeira['foto'], 'm') : null,
    'descricao' => $r['descricao'] ? mb_strimwidth((string) $r['descricao'], 0, 200, '...') : 'Roteiro em ' . $destino['nome_fantasia'] . '.']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> › <a href="<?= e(Portal::url('/roteiros')) ?>">Roteiros</a> › <span><?= e((string) $r['titulo']) ?></span></nav>
<h1><?= e((string) $r['titulo']) ?></h1>
<p class="lead"><?= plural(count($r['paradas']), 'parada', 'paradas') ?><?= $r['duracao'] ? ' · ' . e((string) $r['duracao']) : '' ?></p>
<?php if ($r['descricao']): ?><div class="texto"><?= nl2br(e((string) $r['descricao'])) ?></div><?php endif; ?>
<?php if ($rota): ?><div class="acoes-roteiro"><a target="_blank" rel="noopener" class="cta" href="<?= e($rota) ?>" target="_blank" rel="noopener"><?= icone('mapa') ?>Abrir a rota no Google Maps</a>
  <button type="button" class="cta claro" data-guardar-varios="<?= e(implode('.', array_map(fn ($p) => (int) $p['id'], $r['paradas']))) ?>"><?= icone('mais') ?>Guardar no meu roteiro</button></div><?php endif; ?>
<?php require_once __DIR__ . '/../app/UmDia.php'; ?>
<?= PortalLayout::linhaDoTempo($eid, UmDia::passos($r['paradas'])) ?>
<?php PortalLayout::fim();
