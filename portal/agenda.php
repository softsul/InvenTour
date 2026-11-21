<?php
/** Agenda: os eventos dos próximos 60 dias, lidos do EventCity (cache). Softsul Software. */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
require_once __DIR__ . '/../app/EventCity.php';
$eventos = EventCity::agendaEmCache($eid) ?? naoAchou($destino, 'A agenda do destino não está disponível agora.');
PortalLayout::inicio($destino, 'Agenda', true, ['ativo' => 'agenda', 'descricao' => 'Os próximos eventos em ' . $destino['nome_fantasia'] . '.']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> › <span>Agenda</span></nav>
<h1>Agenda</h1>
<p class="lead">Os próximos eventos, da agenda oficial do destino no EventCity.</p>
<ul class="lista-eventos grande">
  <?php foreach ($eventos as $ev): ?>
    <li><a href="<?= e((string) ($ev['url'] ?? '#')) ?>" rel="noopener">
      <?php if (!empty($ev['capa'])): ?><img src="<?= e((string) $ev['capa']) ?>" alt="" loading="lazy"><?php endif; ?>
      <span class="data"><?= e(date('d/m', strtotime((string) $ev['data_inicio']))) ?><?= !empty($ev['hora_inicio']) ? ' · ' . e(substr((string) $ev['hora_inicio'], 0, 5)) : '' ?></span>
      <b><?= e((string) $ev['titulo']) ?></b><?php if (!empty($ev['segmento'])): ?><em><?= e((string) $ev['segmento']) ?></em><?php endif; ?></a></li>
  <?php endforeach; ?>
</ul>
<?php PortalLayout::fim();
