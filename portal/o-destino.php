<?php
/** O destino: os textos oficiais (marcados para o portal) e a linha do tempo do turismo. Softsul Software. */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
$narrativas = Portal::narrativas($eid);
$marcos = Portal::marcos($eid);
PortalLayout::inicio($destino, 'O destino', true, ['ativo' => 'o-destino', 'descricao' => 'História, geografia, economia e infraestrutura de ' . $destino['nome_fantasia'] . '.']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> › <span>O destino</span></nav>
<h1><?= e((string) $destino['nome_fantasia']) ?></h1>
<?php if (count($narrativas) > 1): ?>
<nav class="indice"><?php foreach ($narrativas as $n): ?><a href="#<?= e((string) $n['secao']) ?>"><?= e((string) $n['titulo']) ?></a><?php endforeach; ?><?= $marcos ? '<a href="#linha">Linha do tempo</a>' : '' ?></nav>
<?php endif; ?>
<?php foreach ($narrativas as $n): ?>
  <section class="narrativa" id="<?= e((string) $n['secao']) ?>"><h2><?= e((string) $n['titulo']) ?></h2><div class="texto"><?= $n['conteudo_html'] ?></div></section>
<?php endforeach; ?>
<?php if ($marcos): ?>
<section class="narrativa" id="linha"><h2>Linha do tempo do turismo</h2>
  <ol class="linha-portal">
    <?php foreach ($marcos as $m): $data = match ($m['precisao']) { 'ano' => substr((string) $m['data_marco'], 0, 4), 'mes' => date('m/Y', strtotime((string) $m['data_marco'])), default => date('d/m/Y', strtotime((string) $m['data_marco'])) }; ?>
      <li><span class="data"><?= e($data) ?></span><b><?= e((string) $m['titulo']) ?></b><?php if ($m['descricao']): ?><p><?= e((string) $m['descricao']) ?></p><?php endif; ?></li>
    <?php endforeach; ?>
  </ol>
</section>
<?php endif; ?>
<?php if (!$narrativas && !$marcos): ?><p class="aviso-vazio">Os textos sobre o destino aparecem aqui quando a equipe os publicar.</p><?php endif; ?>
<?php PortalLayout::fim();
