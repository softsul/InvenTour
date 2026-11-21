<?php
/**
 * Lista de um pilar (Onde dormir, Onde comer, O que fazer, Serviços) ou da
 * busca. Ferramentas no alto: aberto agora (pelo horário da ficha), perto de
 * mim (o portal.js ordena pela distância do GPS do visitante) e lista ou
 * mapa. Os filtros (tipo, zona, acessibilidade, perfil de viajante) ficam em
 * "Refinar". Ordem orgânica (Portal::itens). Softsul Software.
 */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';

$pilar = (string) ($_GET['p'] ?? '');
$busca = $pilar === '';
if (!$busca && !isset(Portal::PILARES[$pilar])) {
    naoAchou($destino);
}
$filtros = ['q' => trim((string) ($_GET['q'] ?? '')), 'zona' => (string) ($_GET['zona'] ?? ''), 'acess' => (string) ($_GET['acess'] ?? ''),
            'tribo' => (int) ($_GET['tribo'] ?? 0), 'ficha' => (int) ($_GET['ficha'] ?? 0), 'aberto' => !empty($_GET['aberto'])];
$vazioDeBusca = $busca && $filtros['q'] === '' && !$filtros['aberto'];
$itens = $vazioDeBusca ? [] : Portal::itens($eid, $busca ? null : $pilar, $filtros, 2000);
// Na busca, a agenda do EventCity entra junto: eventos pelo título, pelo
// segmento e pelo lugar onde acontecem.
$eventos = [];
if ($busca && $filtros['q'] !== '' && !$filtros['aberto']) {
    require_once __DIR__ . '/../app/EventCity.php';
    $eventos = EventCity::buscar($eid, $filtros['q'], array_column($itens, 'id'));
}
[$titulo, $sub] = $busca ? [$filtros['aberto'] && $filtros['q'] === '' ? 'Aberto agora' : 'Busca', 'Hotéis, restaurantes, atrativos, serviços e a agenda de ' . $destino['nome_fantasia']] : Portal::PILARES[$pilar];

$fichasDoPilar = [];
if (!$busca) {
    foreach (Portal::itens($eid, $pilar, [], 2000) as $i) {
        $fichasDoPilar[(int) $i['ficha_ordem']] = (string) $i['ficha_nome'];
    }
    ksort($fichasDoPilar);
}
$tribos = Portal::tribos();
$acao = $busca ? '/busca' : '/' . $pilar;
$base = array_filter(['q' => $filtros['q'], 'zona' => $filtros['zona'], 'acess' => $filtros['acess'], 'tribo' => $filtros['tribo'] ?: null, 'ficha' => $filtros['ficha'] ?: null]);
$refinando = $filtros['zona'] !== '' || $filtros['acess'] !== '' || $filtros['tribo'] || $filtros['ficha'];
PortalLayout::inicio($destino, $titulo, !$busca, ['ativo' => $busca ? '' : $pilar, 'descricao' => $sub . ' em ' . $destino['nome_fantasia'] . '.', 'classe' => 'lista']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> / <span><?= e($titulo) ?></span></nav>
<span class="rotulo"><?= $busca ? 'Guia de campo' : 'Pilar ' . str_pad((string) (array_search($pilar, array_keys(Portal::PILARES), true) + 1), 2, '0', STR_PAD_LEFT) ?></span>
<h1><?= e($titulo) ?></h1>
<p class="lead"><?= e($sub) ?></p>

<?php if ($busca): ?>
<form class="capa-busca busca-pagina" method="get" action="/busca" role="search">
  <?php if (Portal::previa($eid)): ?><input type="hidden" name="previa" value="<?= e((string) $_GET['previa']) ?>"><?php endif; ?>
  <?php if ($filtros['aberto']): ?><input type="hidden" name="aberto" value="1"><?php endif; ?>
  <label for="q">Buscar</label><span class="entrada"><input type="search" id="q" name="q" value="<?= e($filtros['q']) ?>" placeholder="lugar, tipo, bairro ou evento"></span><button type="submit">Buscar</button>
</form>
<?php endif; ?>

<div class="ferramentas">
  <a class="carimbo<?= $filtros['aberto'] ? ' ligado' : '' ?>" href="<?= e(Portal::url($acao, $base + ($filtros['aberto'] ? [] : ['aberto' => 1]))) ?>" aria-pressed="<?= $filtros['aberto'] ? 'true' : 'false' ?>"><?= icone('relogio') ?>Aberto agora</a>
  <button type="button" class="carimbo" data-perto aria-pressed="false"><?= icone('mapa') ?>Perto de mim</button>
  <button type="button" class="carimbo" data-ver-mapa aria-pressed="false"><?= icone('natureza') ?>Ver no mapa</button>
  <?php if ($itens): ?><span class="conta-lista"><?= plural(count($itens), 'lugar', 'lugares') ?></span><?php endif; ?>
</div>

<details class="refinar"<?= $refinando ? ' open' : '' ?>>
  <summary>Refinar</summary>
  <form class="filtros-portal" method="get" action="<?= e($acao) ?>" data-envia-ao-mudar>
    <?php if (Portal::previa($eid)): ?><input type="hidden" name="previa" value="<?= e((string) $_GET['previa']) ?>"><?php endif; ?>
    <?php if ($filtros['aberto']): ?><input type="hidden" name="aberto" value="1"><?php endif; ?>
    <?php if ($busca): ?><input type="hidden" name="q" value="<?= e($filtros['q']) ?>"><?php endif; ?>
    <?php if (count($fichasDoPilar) > 1): ?>
      <label class="campo-filtro"><span>Tipo</span><span class="entrada"><select name="ficha"><option value="">Todos</option>
        <?php foreach ($fichasDoPilar as $ordem => $nomeFicha): ?><option value="<?= $ordem ?>"<?= $filtros['ficha'] === $ordem ? ' selected' : '' ?>><?= e($nomeFicha) ?></option><?php endforeach; ?>
      </select></span></label>
    <?php endif; ?>
    <label class="campo-filtro"><span>Zona</span><span class="entrada"><select name="zona"><option value="">Cidade e campo</option>
      <option value="urbana"<?= $filtros['zona'] === 'urbana' ? ' selected' : '' ?>>Na cidade</option>
      <option value="rural"<?= $filtros['zona'] === 'rural' ? ' selected' : '' ?>>No campo</option></select></span></label>
    <label class="campo-filtro"><span>Acessibilidade</span><span class="entrada"><select name="acess"><option value="">Todas</option>
      <?php foreach (Portal::SELOS_ACESSIBILIDADE as $k => $r): ?><option value="<?= $k ?>"<?= $filtros['acess'] === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
    </select></span></label>
    <label class="campo-filtro"><span>Perfil de viajante</span><span class="entrada"><select name="tribo"><option value="">Todos</option>
      <?php foreach ($tribos as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $filtros['tribo'] === (int) $t['id'] ? ' selected' : '' ?>><?= e((string) $t['nome']) ?></option><?php endforeach; ?>
    </select></span></label>
    <button type="submit" class="botao-filtrar">Filtrar</button>
  </form>
</details>

<?php if ($eventos): ?>
<section class="busca-eventos">
  <div class="secao-cab"><div><span class="rotulo">Agenda oficial · EventCity</span><h2>Na agenda</h2></div><a href="<?= e(Portal::url('/agenda')) ?>">A agenda</a></div>
  <ul class="lista-eventos">
    <?php foreach ($eventos as $ev): ?>
      <li><a href="<?= e((string) ($ev['url'] ?? '#')) ?>" rel="noopener"><span class="data"><?= e(date('d/m', strtotime((string) ($ev['proximo_dia'] ?? $ev['data_inicio'])))) ?><?= !empty($ev['hora_inicio']) ? '<br>' . e(substr((string) $ev['hora_inicio'], 0, 5)) : '' ?></span>
        <b><?= e((string) $ev['titulo']) ?></b><?php if (!empty($ev['segmento'])): ?><em><?= e((string) $ev['segmento']) ?></em><?php endif; ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($vazioDeBusca): ?>
  <p class="aviso-vazio">Digite o que procura: o nome do lugar, o tipo (hotel, pizzaria, museu), o bairro ou um evento da agenda.</p>
<?php elseif (!$itens): ?>
  <p class="aviso-vazio">Nenhum lugar<?= $eventos ? ' no guia' : '' ?> com esses filtros<?= $filtros['q'] !== '' ? ' para "' . e($filtros['q']) . '"' : '' ?>. Tire algum filtro para ver mais.</p>
<?php else: ?>
  <div class="mapa-caixa" data-mapa data-mapa-lista hidden><?= PortalLayout::mapa($eid, $itens, $titulo . ' no mapa') ?></div>
  <div class="grade-lugares" data-lista-lugares><?php foreach ($itens as $i) { echo PortalLayout::cartao($eid, $i); } ?></div>
<?php endif; ?>
<?php PortalLayout::fim();
