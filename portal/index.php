<?php
/**
 * Início do portal: o guia de campo do destino. Capa com a foto do destino,
 * o nome em tipografia gigante e as coordenadas; a busca e os atalhos (aberto
 * agora, perto de mim, surpreenda-me, um dia aqui); os números de agora; o
 * índice dos pilares; um dia montado pelos lugares mais próximos; o mapa do
 * destino com a divisa oficial; experiências, roteiros e a agenda.
 * Softsul Software.
 */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
require_once __DIR__ . '/../app/EventCity.php';

$nome = (string) $destino['nome_fantasia'];
$contagens = Portal::contagens($eid);
$todos = Portal::itens($eid, null, [], 2000);
$abertos = count(array_filter($todos, fn ($i) => (Portal::agora($i['horario'] ?? null)['aberto'] ?? false) === true));
$experiencias = array_slice(Portal::experiencias($eid), 0, 6);
$roteiros = array_slice(Portal::roteiros($eid), 0, 3);
$agenda = array_slice(EventCity::agendaEmCache($eid) ?? [], 0, 5);
$semente = (int) date('Ymd');
$umDia = Portal::umDia($eid, $semente);
$dia = array_slice($umDia['passos'] ?? [], 0, 4);
$apresentacao = '';
foreach (Portal::narrativas($eid) as $n) {
    if ($n['secao'] === 'apresentacao') {
        preg_match('#<p>(.*?)</p>#s', (string) $n['conteudo_html'], $m);
        $apresentacao = trim(html_entity_decode(strip_tags($m[1] ?? ''), ENT_QUOTES, 'UTF-8'));
    }
}
$cfg = require __DIR__ . '/../app/config.php';
$temCapa = is_readable(dirname(rtrim((string) $cfg['caminhos']['midias'], '/')) . '/cadernos/' . $eid . '/capa.jpg');
$coord = PortalLayout::coordenadaDoDestino($destino);
$previa = Portal::previa($eid);

PortalLayout::inicio($destino, $nome, true, ['ativo' => '', 'imagem' => $temCapa ? '/capa.jpg' : null, 'descricao' => $apresentacao ?: null, 'classe' => 'inicio']);
?>
<section class="capa">
  <?php if ($temCapa): ?><img src="/capa.jpg" alt=""><?php endif; ?>
  <div class="capa-txt">
    <span class="rotulo"><?= $coord !== '' ? e($coord) . ' · ' : '' ?>Guia oficial de turismo</span>
    <h1><?= e($nome) ?></h1>
    <?php if ($apresentacao !== ''): ?><p class="lead"><?= e(mb_strimwidth($apresentacao, 0, 170, '...')) ?></p><?php endif; ?>
    <form class="capa-busca" method="get" action="/busca" role="search">
      <?php if ($previa): ?><input type="hidden" name="previa" value="<?= e((string) $_GET['previa']) ?>"><?php endif; ?>
      <label for="busca-capa">Para onde?</label>
      <span class="entrada"><input type="search" id="busca-capa" name="q" placeholder="hotel, pizza, museu, parque..."></span>
      <button type="submit">Buscar</button>
    </form>
    <div class="atalhos">
      <a class="carimbo" href="<?= e(Portal::url('/busca', ['aberto' => 1])) ?>"><?= icone('relogio') ?>Aberto agora</a>
      <a class="carimbo" href="<?= e(Portal::url('/o-que-fazer', ['perto' => 1])) ?>"><?= icone('mapa') ?>Perto de mim</a>
      <a class="carimbo" href="<?= e(Portal::url('/surpresa')) ?>"><?= icone('trocar') ?>Surpreenda-me</a>
      <?php if ($umDia): ?><a class="carimbo" href="<?= e(Portal::url('/um-dia')) ?>"><?= icone('calendario') ?>Um dia aqui</a><?php endif; ?>
    </div>
  </div>
</section>

<div class="agora">
  <div><b><?= milhar(count($todos)) ?></b><span>lugares no guia</span></div>
  <div><b><?= milhar($abertos) ?></b><span>abertos agora, <?= date('H:i') ?></span></div>
  <div><b><?= milhar($contagens['onde-comer']) ?></b><span>para comer e beber</span></div>
  <div><b><?= $agenda ? milhar(count(EventCity::agendaEmCache($eid) ?? [])) : milhar($contagens['o-que-fazer']) ?></b><span><?= $agenda ? 'eventos nos próximos 60 dias' : 'coisas para fazer' ?></span></div>
</div>

<section class="secao">
  <div class="secao-cab"><div><span class="rotulo">Índice</span><h2>O que procura?</h2></div></div>
  <ol class="indice-pilares">
    <?php foreach (Portal::PILARES as $k => [$titulo, $sub]): ?>
      <li><a class="p-<?= $k ?>" href="<?= e(Portal::url('/' . $k)) ?>"><b><?= e($titulo) ?></b><em><?= $contagens[$k] ? plural($contagens[$k], 'lugar', 'lugares') : 'em breve' ?></em><span><?= e($sub) ?></span></a></li>
    <?php endforeach; ?>
  </ol>
</section>

<?php if ($dia): ?>
<section class="secao">
  <div class="secao-cab"><div><span class="rotulo">Sugestão de hoje · da equipe de turismo</span><h2><?= e(($umDia['titulo'] ?? '') !== '' ? (string) $umDia['titulo'] : 'Um dia em ' . $nome) ?></h2></div>
    <a href="<?= e(Portal::url('/um-dia', ['s' => $semente])) ?>">O dia completo</a></div>
  <div class="dia-mini">
    <?php foreach ($dia as $p): $f = Portal::foto($eid, $p['item']['foto'], 'm'); ?>
      <a href="<?= e(PortalLayout::urlLugar($p['item'])) ?>">
        <span class="hora"><?= e($p['hora']) ?> · <?= e($p['rotulo']) ?></span>
        <figure><?php if ($f): ?><img src="<?= e($f) ?>" alt="" loading="lazy"><?php else: ?><span class="sem-foto"><b><?= e(PortalLayout::inicial((string) $p['item']['nome'])) ?></b></span><?php endif; ?></figure>
        <b><?= e((string) $p['item']['nome']) ?></b><em><?= e((string) ($p['item']['tipo_nome'] ?: $p['item']['ficha_nome'])) ?><?= $p['metros'] !== null ? ' · ' . e(Portal::metros($p['metros'])) . ' do anterior' : '' ?></em></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php $mapa = PortalLayout::mapa($eid, $todos, 'Mapa de ' . $nome . ' com os lugares do guia'); ?>
<?php if ($mapa !== ''): ?>
<section class="secao">
  <div class="secao-cab"><div><span class="rotulo">Ruas do OpenStreetMap · divisa do IBGE</span><h2>O mapa do destino</h2></div></div>
  <div class="mapa-caixa" data-mapa><?= $mapa ?></div>
</section>
<?php endif; ?>

<?php if ($experiencias): ?>
<section class="secao">
  <div class="secao-cab"><div><span class="rotulo">Aprovadas pela curadoria</span><h2>Experiências</h2></div><a href="<?= e(Portal::url('/experiencias')) ?>">Ver todas</a></div>
  <div class="dia-mini">
    <?php foreach ($experiencias as $x): $f = Portal::foto($eid, $x['foto'] ?: $x['foto_item'], 'm'); ?>
      <a href="<?= e(Portal::url('/experiencias', ['x' => (int) $x['id']])) ?>"><figure><?php if ($f): ?><img src="<?= e($f) ?>" alt="" loading="lazy"><?php endif; ?></figure>
        <b><?= e((string) $x['titulo']) ?></b><em><?= e((string) $x['item_nome']) ?></em></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($roteiros): ?>
<section class="secao">
  <div class="secao-cab"><div><span class="rotulo">Feitos pela equipe do destino</span><h2>Roteiros</h2></div><a href="<?= e(Portal::url('/roteiros')) ?>">Ver todos</a></div>
  <div class="lista-roteiros">
    <?php foreach ($roteiros as $r): ?>
      <a class="roteiro-linha" href="<?= e(Portal::url('/roteiro/' . (int) $r['id'] . '-' . Portal::slug((string) $r['titulo']))) ?>"><?= icone('mapa') ?>
        <span><b><?= e((string) $r['titulo']) ?></b><em><?= plural((int) $r['paradas'], 'parada', 'paradas') ?><?= $r['duracao'] ? ' · ' . e((string) $r['duracao']) : '' ?></em></span></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($agenda): ?>
<section class="secao">
  <div class="secao-cab"><div><span class="rotulo">Agenda oficial · EventCity</span><h2>Vem aí</h2></div><a href="<?= e(Portal::url('/agenda')) ?>">A agenda</a></div>
  <ul class="lista-eventos">
    <?php foreach ($agenda as $ev): ?>
      <li><a href="<?= e((string) ($ev['url'] ?? '#')) ?>" rel="noopener"><span class="data"><?= e(date('d/m', strtotime((string) ($ev['proximo_dia'] ?? $ev['data_inicio'])))) ?><?= !empty($ev['hora_inicio']) ? '<br>' . e(substr((string) $ev['hora_inicio'], 0, 5)) : '' ?></span>
        <b><?= e((string) $ev['titulo']) ?></b><?php if (!empty($ev['segmento'])): ?><em><?= e((string) $ev['segmento']) ?></em><?php endif; ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (!$todos): ?>
  <p class="aviso-vazio">Os lugares de <?= e($nome) ?> aparecem aqui assim que o inventário for homologado pela equipe do destino.</p>
<?php endif; ?>
<?php PortalLayout::fim();
