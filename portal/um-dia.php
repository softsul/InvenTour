<?php
/**
 * Um dia no destino: manhã num atrativo, almoço perto dele, tarde e fim de
 * tarde nos atrativos mais próximos, jantar e onde dormir por perto, tudo
 * pela distância real entre os lugares do inventário (Portal::umDia). Cada
 * dia tem um número (?s=): o link guarda o mesmo dia, e "Montar outro"
 * sorteia de novo. Dá para guardar o dia inteiro no Meu roteiro e abrir a
 * rota no mapa. Softsul Software.
 */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';

$semente = (int) ($_GET['s'] ?? 0) ?: (int) date('Ymd');
$dia = Portal::umDia($eid, $semente);
$itens = array_map(fn ($p) => $p['item'], $dia);
$rota = Portal::rotaNoMapa($itens);
$ids = implode('.', array_map(fn ($i) => (int) $i['id'], $itens));
$jeito = fn (?int $m) => $m === null ? '' : ($m <= 1200 ? 'a pé, ' . Portal::metros($m) : 'de carro, ' . Portal::metros($m));
PortalLayout::inicio($destino, 'Um dia em ' . $destino['nome_fantasia'], true, ['ativo' => 'um-dia',
    'imagem' => $itens ? Portal::foto($eid, $itens[0]['foto'] ?? null, 'm') : null,
    'descricao' => 'Um dia inteiro em ' . $destino['nome_fantasia'] . ', montado pela distância entre os lugares: o que fazer, onde comer e onde dormir.']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> / <span>Um dia aqui</span></nav>
<span class="rotulo"><?= isset($_GET['s']) ? 'Outro roteiro' : 'Sugestão de hoje' ?> · montado pela distância entre os lugares</span>
<h1>Um dia em <?= e((string) $destino['nome_fantasia']) ?></h1>
<p class="lead">Do café da manhã ao travesseiro, sem atravessar a cidade à toa: cada parada é a mais perto da anterior.</p>
<?php if (!$dia): ?>
  <p class="aviso-vazio">Ainda não há lugares suficientes no guia para montar um dia. Volte quando o inventário estiver homologado.</p>
<?php else: ?>
  <div class="acoes-roteiro">
    <a class="cta" href="<?= e(Portal::url('/um-dia', ['s' => random_int(10000, 99999999)])) ?>"><?= icone('trocar') ?>Montar outro dia</a>
    <button type="button" class="cta claro" data-guardar-varios="<?= e($ids) ?>"><?= icone('mais') ?>Guardar o dia no meu roteiro</button>
    <?php if ($rota): ?><a class="cta claro" href="<?= e($rota) ?>" rel="noopener"><?= icone('mapa') ?>Abrir a rota</a><?php endif; ?>
  </div>
  <ol class="dia">
    <?php foreach ($dia as $p): ?>
      <li><span class="hora"><?= e($p['hora']) ?></span>
        <div><p class="passo-rotulo"><?= e($p['rotulo']) ?></p>
          <?php if ($p['metros'] !== null): ?><p class="trajeto"><?= e($jeito($p['metros'])) ?> da parada anterior</p><?php endif; ?>
          <?= PortalLayout::cartao($eid, $p['item']) ?></div></li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>
<?php PortalLayout::fim();
