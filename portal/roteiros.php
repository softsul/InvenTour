<?php
/** Roteiros do destino (os publicados pelo gestor). Softsul Software. */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
$roteiros = Portal::roteiros($eid);
$tipos = ['permanente' => 'Roteiro', 'evento' => 'Roteiro de evento', 'tribo' => 'Por perfil de viajante'];
PortalLayout::inicio($destino, 'Roteiros', true, ['ativo' => 'roteiros', 'descricao' => 'Roteiros prontos para conhecer ' . $destino['nome_fantasia'] . '.']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> › <span>Roteiros</span></nav>
<h1>Roteiros</h1>
<p class="lead">Caminhos prontos, montados pela equipe do destino. Monte também o seu: toque em <?= icone('mais') ?> nos lugares que quiser visitar.</p>
<?php if (!$roteiros): ?><p class="aviso-vazio">Os roteiros do destino aparecem aqui assim que forem publicados.</p><?php endif; ?>
<div class="lista-roteiros">
<?php foreach ($roteiros as $r): ?>
  <a class="roteiro-linha" href="<?= e(Portal::url('/roteiro/' . (int) $r['id'] . '-' . Portal::slug((string) $r['titulo']))) ?>"><?= icone('mapa') ?>
    <span><b><?= e((string) $r['titulo']) ?></b><em><?= e($tipos[$r['tipo']] ?? 'Roteiro') ?> · <?= plural((int) $r['paradas'], 'parada', 'paradas') ?><?= $r['duracao'] ? ' · ' . e((string) $r['duracao']) : '' ?></em>
    <?php if ($r['descricao']): ?><small><?= e(mb_strimwidth((string) $r['descricao'], 0, 160, '...')) ?></small><?php endif; ?></span></a>
<?php endforeach; ?>
</div>
<p><a class="cta claro" href="<?= e(Portal::url('/meu-roteiro')) ?>"><?= icone('mapa') ?>Ver o meu roteiro</a></p>
<?php PortalLayout::fim();
