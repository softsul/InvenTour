<?php
/**
 * Início do portal do destino: a foto do destino, a busca, os pilares com
 * as contagens e os destaques (experiências, roteiros, o que fazer e a
 * agenda do EventCity, quando houver). Softsul Software.
 */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
require_once __DIR__ . '/../app/EventCity.php';

$nome = (string) $destino['nome_fantasia'];
$contagens = Portal::contagens($eid);
$experiencias = array_slice(Portal::experiencias($eid), 0, 6);
$roteiros = array_slice(Portal::roteiros($eid), 0, 3);
$fazer = array_slice(array_filter(Portal::itens($eid, 'o-que-fazer'), fn ($i) => $i['foto']), 0, 8, true);
$agenda = array_slice(EventCity::agendaEmCache($eid) ?? [], 0, 4);
$narrativas = Portal::narrativas($eid);
$apresentacao = '';
foreach ($narrativas as $n) {
    if ($n['secao'] === 'apresentacao') {
        // O primeiro parágrafo do texto de apresentação, sem as marcas.
        preg_match('#<p>(.*?)</p>#s', (string) $n['conteudo_html'], $m);
        $apresentacao = trim(html_entity_decode(strip_tags($m[1] ?? ''), ENT_QUOTES, 'UTF-8'));
    }
}
$cfg = require __DIR__ . '/../app/config.php';
$temCapa = is_readable(dirname(rtrim((string) $cfg['caminhos']['midias'], '/')) . '/cadernos/' . $eid . '/capa.jpg');
$icones = ['onde-dormir' => 'casa', 'onde-comer' => 'gastronomia', 'o-que-fazer' => 'natureza', 'servicos' => 'negocios'];

PortalLayout::inicio($destino, $nome, true, ['ativo' => 'inicio', 'imagem' => $temCapa ? '/capa.jpg' : null,
    'descricao' => $apresentacao !== '' ? $apresentacao : null]);
?>
<section class="heroi<?= $temCapa ? ' com-foto' : '' ?>">
  <?php if ($temCapa): ?><img class="heroi-foto" src="/capa.jpg" alt=""><?php endif; ?>
  <div class="heroi-txt">
    <p class="sobre">Turismo oficial</p>
    <h1><?= e($nome) ?></h1>
    <?php if ($apresentacao !== ''): ?><p class="lead"><?= e(mb_strimwidth($apresentacao, 0, 220, '...')) ?></p><?php endif; ?>
    <form class="busca-grande" method="get" action="/busca" role="search">
      <?php if (Portal::previa($eid)): ?><input type="hidden" name="previa" value="<?= e((string) $_GET['previa']) ?>"><?php endif; ?>
      <span class="entrada"><?= icone('busca') ?><input type="search" name="q" placeholder="O que você procura?" aria-label="Buscar no portal"></span>
      <button type="submit">Buscar</button>
    </form>
  </div>
</section>

<section class="pilares">
  <?php foreach (Portal::PILARES as $k => [$titulo, $sub]): ?>
    <a class="pilar" href="<?= e(Portal::url('/' . $k)) ?>"><?= icone($icones[$k]) ?><b><?= e($titulo) ?></b><span><?= e($sub) ?></span>
      <em><?= $contagens[$k] ? plural($contagens[$k], 'lugar', 'lugares') : 'em breve' ?></em></a>
  <?php endforeach; ?>
</section>

<?php if ($experiencias): ?>
<section class="bloco">
  <div class="bloco-cab"><h2>Experiências</h2><a href="<?= e(Portal::url('/experiencias')) ?>">Ver todas</a></div>
  <div class="grade-cartoes">
    <?php foreach ($experiencias as $x): $f = Portal::foto($eid, $x['foto'] ?: $x['foto_item'], 'm'); ?>
      <a class="cartao-xp" href="<?= e(Portal::url('/experiencias', ['x' => (int) $x['id']])) ?>">
        <?php if ($f): ?><img src="<?= e($f) ?>" alt="" loading="lazy"><?php endif; ?>
        <b><?= e((string) $x['titulo']) ?></b><span><?= e((string) $x['item_nome']) ?></span></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($fazer): ?>
<section class="bloco">
  <div class="bloco-cab"><h2>O que fazer</h2><a href="<?= e(Portal::url('/o-que-fazer')) ?>">Ver tudo</a></div>
  <div class="grade-cartoes"><?php foreach ($fazer as $i) { echo PortalLayout::cartao($eid, $i); } ?></div>
</section>
<?php endif; ?>

<?php if ($roteiros): ?>
<section class="bloco">
  <div class="bloco-cab"><h2>Roteiros</h2><a href="<?= e(Portal::url('/roteiros')) ?>">Ver todos</a></div>
  <div class="lista-roteiros">
    <?php foreach ($roteiros as $r): ?>
      <a class="roteiro-linha" href="<?= e(Portal::url('/roteiro/' . (int) $r['id'] . '-' . Portal::slug((string) $r['titulo']))) ?>"><?= icone('mapa') ?>
        <span><b><?= e((string) $r['titulo']) ?></b><em><?= plural((int) $r['paradas'], 'parada', 'paradas') ?><?= $r['duracao'] ? ' · ' . e((string) $r['duracao']) : '' ?></em></span></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($agenda): ?>
<section class="bloco">
  <div class="bloco-cab"><h2>Agenda</h2><a href="<?= e(Portal::url('/agenda')) ?>">Ver a agenda</a></div>
  <ul class="lista-eventos">
    <?php foreach ($agenda as $ev): ?>
      <li><a href="<?= e((string) ($ev['url'] ?? '#')) ?>" rel="noopener"><span class="data"><?= e(date('d/m', strtotime((string) $ev['data_inicio']))) ?></span>
        <b><?= e((string) $ev['titulo']) ?></b></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (!array_sum($contagens) && !$experiencias): ?>
  <p class="aviso-vazio">Os lugares de <?= e($nome) ?> aparecem aqui assim que o inventário for homologado pela equipe do destino.</p>
<?php endif; ?>
<?php PortalLayout::fim();
