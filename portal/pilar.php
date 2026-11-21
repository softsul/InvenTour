<?php
/**
 * Lista de um pilar (Onde dormir, Onde comer, O que fazer, Serviços) ou da
 * busca, com os filtros por tipo (ficha), zona, acessibilidade e tribo, na
 * ordem orgânica (Portal::itens). Softsul Software.
 */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';

$pilar = (string) ($_GET['p'] ?? '');
$busca = $pilar === '' ;
if (!$busca && !isset(Portal::PILARES[$pilar])) {
    naoAchou($destino);
}
$filtros = ['q' => trim((string) ($_GET['q'] ?? '')), 'zona' => (string) ($_GET['zona'] ?? ''), 'acess' => (string) ($_GET['acess'] ?? ''),
            'tribo' => (int) ($_GET['tribo'] ?? 0), 'ficha' => (int) ($_GET['ficha'] ?? 0)];
$itens = $busca && $filtros['q'] === '' ? [] : Portal::itens($eid, $busca ? null : $pilar, $filtros);
[$titulo, $sub] = $busca ? ['Busca', 'Hotéis, restaurantes, atrativos e serviços de ' . $destino['nome_fantasia']] : Portal::PILARES[$pilar];

// Os tipos (fichas) do pilar que têm algum lugar, para o filtro.
$fichasDoPilar = [];
if (!$busca) {
    foreach (Portal::itens($eid, $pilar) as $i) {
        $fichasDoPilar[(int) $i['ficha_ordem']] = (string) $i['ficha_nome'];
    }
    ksort($fichasDoPilar);
}
$tribos = Portal::tribos();
$acao = $busca ? '/busca' : '/' . $pilar;
PortalLayout::inicio($destino, $busca ? 'Busca' : $titulo, !$busca, ['ativo' => $busca ? '' : $pilar, 'descricao' => $sub . ' em ' . $destino['nome_fantasia'] . '.']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> › <span><?= e($titulo) ?></span></nav>
<h1><?= e($titulo) ?></h1>
<p class="lead"><?= e($sub) ?></p>

<form class="filtros-portal" method="get" action="<?= e($acao) ?>" data-envia-ao-mudar>
  <?php if (Portal::previa($eid)): ?><input type="hidden" name="previa" value="<?= e((string) $_GET['previa']) ?>"><?php endif; ?>
  <?php if ($busca): ?>
    <label class="campo-filtro largo"><span>Buscar</span><span class="entrada"><input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nome, tipo ou bairro"></span></label>
  <?php endif; ?>
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

<?php if ($busca && $filtros['q'] === ''): ?>
  <p class="aviso-vazio">Digite o que procura: o nome do lugar, o tipo (hotel, pizzaria, museu) ou o bairro.</p>
<?php elseif (!$itens): ?>
  <p class="aviso-vazio">Nenhum lugar com esses filtros<?= $filtros['q'] !== '' ? ' para "' . e($filtros['q']) . '"' : '' ?>. Tire algum filtro para ver mais.</p>
<?php else: ?>
  <p class="conta-lista"><?= plural(count($itens), 'lugar', 'lugares') ?></p>
  <div class="grade-cartoes"><?php foreach ($itens as $i) { echo PortalLayout::cartao($eid, $i); } ?></div>
<?php endif; ?>
<?php PortalLayout::fim();
