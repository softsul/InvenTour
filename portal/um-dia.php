<?php
/**
 * Um dia no destino, montado pela equipe de turismo: um dos roteiros do tipo
 * "Um dia aqui" publicados (onde começa, a hora de cada parada, o tempo em
 * cada lugar e onde termina são dela), sorteado. Cada dia tem
 * um número (?s=): o link guarda o mesmo dia, e "Outro dia" sorteia de novo.
 * Sem nada definido pela equipe, a página não existe. Softsul Software.
 */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';

$semente = (int) ($_GET['s'] ?? 0) ?: (int) date('Ymd');
$dia = Portal::umDia($eid, $semente);
if (!$dia) {
    http_response_code(404);
}
$passos = $dia['passos'] ?? [];
$itens = array_map(fn ($p) => $p['item'], $passos);
$rota = Portal::rotaNoMapa($itens);
$ids = implode('.', array_map(fn ($i) => (int) $i['id'], $itens));
$jeito = fn (?int $m) => $m === null ? '' : ($m <= 1200 ? 'a pé, ' . Portal::metros($m) : 'de carro, ' . Portal::metros($m));
$titulo = ($dia['titulo'] ?? '') !== '' ? (string) $dia['titulo'] : 'Um dia em ' . $destino['nome_fantasia'];
PortalLayout::inicio($destino, $titulo, true, ['ativo' => 'um-dia',
    'imagem' => $itens ? Portal::foto($eid, $itens[0]['foto'] ?? null, 'm') : null,
    'descricao' => 'Um dia inteiro em ' . $destino['nome_fantasia'] . ', sugerido pela equipe de turismo: o que fazer, onde comer e onde dormir.']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> / <span>Um dia aqui</span></nav>
<?php if (!$dia): ?>
  <h1>Um dia em <?= e((string) $destino['nome_fantasia']) ?></h1>
  <p class="aviso-vazio">A equipe de turismo ainda não preparou sugestões de um dia no destino. Veja <a href="<?= e(Portal::url('/o-que-fazer')) ?>">o que fazer</a> e os <a href="<?= e(Portal::url('/roteiros')) ?>">roteiros</a>.</p>
<?php else: ?>
  <span class="rotulo"><?= isset($_GET['s']) ? 'Outro dia' : 'Sugestão de hoje' ?> · da equipe de turismo de <?= e((string) $destino['nome_fantasia']) ?></span>
  <h1><?= e($titulo) ?></h1>
  <?php if (($dia['descricao'] ?? '') !== ''): ?><p class="lead"><?= e((string) $dia['descricao']) ?></p><?php endif; ?>
  <div class="acoes-roteiro">
    <a class="cta" href="<?= e(Portal::url('/um-dia', ['s' => random_int(10000, 99999999)])) ?>"><?= icone('trocar') ?>Outro dia</a>
    <button type="button" class="cta claro" data-guardar-varios="<?= e($ids) ?>"><?= icone('mais') ?>Guardar o dia no meu roteiro</button>
    <?php if ($rota): ?><a class="cta claro" href="<?= e($rota) ?>" rel="noopener"><?= icone('mapa') ?>Abrir a rota</a><?php endif; ?>
  </div>
  <ol class="dia">
    <?php foreach ($passos as $p): ?>
      <li><span class="hora"><?= e($p['hora']) ?></span>
        <div><?php if ($p['rotulo'] !== '' || $p['duracao'] !== ''): ?><p class="passo-rotulo"><?= e(implode(' · ', array_filter([$p['rotulo'], $p['duracao'] !== '' ? 'cerca de ' . $p['duracao'] : '']))) ?></p><?php endif; ?>
          <?php if ($p['metros'] !== null): ?><p class="trajeto"><?= e($jeito($p['metros'])) ?> da parada anterior</p><?php endif; ?>
          <?php if (!empty($p['nota'])): ?><p class="trajeto"><?= e((string) $p['nota']) ?></p><?php endif; ?>
          <?= PortalLayout::cartao($eid, $p['item']) ?></div></li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>
<?php PortalLayout::fim();
