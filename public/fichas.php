<?php
/**
 * Fichas e tipos do Inventário da Oferta Turística (MTur, 2011).
 * Softsul Software
 *
 * Catálogo global, da visão da plataforma: as 54 fichas na ordem da
 * capitulação oficial (categorias A, B e C), com os tipos e subtipos de cada
 * uma. A ficha abre em modal (ficha.php), onde se ajustam a orientação de
 * preenchimento e as definições dos tipos. Capitulação e códigos não mudam
 * aqui: são da metodologia, e o caderno oficial depende deles.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

exigirVisaoPlataforma();
Auth::exigirPermissao('fichas.ver');
$pdo = Database::conexao();

const CATEGORIAS_DO_INVENTARIO = [
    'A' => 'A. Infraestrutura de apoio ao turismo',
    'B' => 'B. Serviços e equipamentos turísticos',
    'C' => 'C. Atrativos turísticos',
];

$busca = trim((string) ($_GET['q'] ?? ''));
$categoria = array_key_exists((string) ($_GET['categoria'] ?? ''), CATEGORIAS_DO_INVENTARIO) ? (string) $_GET['categoria'] : '';
$parcial = !empty($_GET['parcial']);

$onde = [];
$args = [];
if ($categoria !== '') {
    $onde[] = 'f.categoria = ?';
    $args[] = $categoria;
}
if ($busca !== '') {
    // A busca acha a ficha pelo nome, pelo código ou por um tipo dela
    // ("pousada" acha Hoteleira e de apoio).
    $como = '%' . $busca . '%';
    $onde[] = '(f.nome LIKE ? OR f.grupo LIKE ? OR f.codigo LIKE ? OR f.codigos_mtur LIKE ?
               OR EXISTS (SELECT 1 FROM inventario_tipos t WHERE t.ficha_id = f.id AND t.nome LIKE ?))';
    array_push($args, $como, $como, $como, $como, $como);
}
$where = $onde ? 'WHERE ' . implode(' AND ', $onde) : '';
$q = $pdo->prepare("SELECT f.*, (SELECT COUNT(*) FROM inventario_tipos t WHERE t.ficha_id = f.id AND t.ativo = 1) AS tipos,
                           (SELECT COUNT(*) FROM inventario_tipos t WHERE t.ficha_id = f.id AND t.ativo = 1
                             AND (t.definicao IS NULL OR t.definicao = '')) AS sem_definicao
                    FROM inventario_fichas f {$where} ORDER BY f.ordem");
$q->execute($args);
$fichas = $q->fetchAll();
$contas = $pdo->query('SELECT categoria, COUNT(*) FROM inventario_fichas GROUP BY categoria')->fetchAll(PDO::FETCH_KEY_PAIR);

const TIPOS_EVENTCITY = ['atrativo' => 'Atrativo', 'hospedagem' => 'Hospedagem', 'gastronomia' => 'Gastronomia',
                         'espaco_eventos' => 'Espaço de eventos', 'servico_apoio' => 'Serviço de apoio'];

$lista = function () use ($fichas, $busca) {
    if (!$fichas): ?>
      <div class="vazio">
        <?= icone('manual') ?>
        <p><strong>Nenhuma ficha<?= $busca !== '' ? ' para "' . e($busca) . '"' : '' ?>.</strong></p>
        <p><?= $busca !== '' ? 'Ajuste a busca.' : 'O catálogo ainda não foi carregado (migração das 54 fichas).' ?></p>
      </div>
    <?php return; endif; ?>
      <div class="rolagem">
        <table class="dados">
          <thead><tr>
            <th class="num">Nº</th><th>Código</th><th>Ficha</th><th>Grupo</th><th class="num">Tipos</th><th>No EventCity</th><th>Situação</th><th class="acao"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($fichas as $f): $url = endereco('ficha', (int) $f['id'], (string) $f['nome']); ?>
            <tr class="<?= $f['ativo'] ? '' : 'inativa' ?>">
              <td class="num dado"><?= (int) $f['ordem'] ?></td>
              <td class="dado" title="<?= e((string) $f['codigos_mtur']) ?>"><?= e((string) $f['codigo']) ?></td>
              <td class="elastica"><a href="<?= e($url) ?>" data-ficha="<?= e((string) $f['nome']) ?>"><?= e((string) $f['nome']) ?></a></td>
              <td><?= e((string) $f['grupo']) ?></td>
              <td class="num dado"><?= (int) $f['tipos'] ?><?= (int) $f['sem_definicao'] ? ' <span class="nulo" title="' . (int) $f['sem_definicao'] . ' sem definição">*</span>' : '' ?></td>
              <td><?= $f['eventcity'] ? e(TIPOS_EVENTCITY[$f['eventcity']] ?? $f['eventcity']) : '<span class="nulo">não vai</span>' ?></td>
              <td><?= $f['ativo'] ? '<span class="pilula ok">Ativa</span>' : '<span class="pilula ruim">Inativa</span>' ?></td>
              <td class="acao"><div class="acoes-linha"><a href="<?= e($url) ?>" data-ficha="<?= e((string) $f['nome']) ?>" title="Abrir a ficha" aria-label="Abrir <?= e((string) $f['nome']) ?>"><?= icone('lapis') ?></a></div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php
};

if ($parcial) {
    $lista();
    exit;
}

paginaInicio('Fichas e tipos', 'fichas');
?>
<div class="cab-pag">
  <div>
    <h1>Fichas e tipos</h1>
    <p>As 54 fichas do Inventário da Oferta Turística do Ministério do Turismo (2011), na ordem da capitulação, com os tipos e subtipos de cada uma. Valem para todos os destinos.</p>
  </div>
</div>

<form class="filtros" method="get" action="/fichas">
  <div class="entrada">
    <?= icone('busca') ?>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Ficha, código ou tipo (pousada, cachoeira...)" aria-label="Buscar fichas">
  </div>
  <div class="pilulas-filtro a-direita">
    <a class="pf<?= $categoria === '' ? ' ativa' : '' ?>" href="/fichas<?= $busca !== '' ? '?' . http_build_query(['q' => $busca]) : '' ?>">Todas<span><?= array_sum(array_map('intval', $contas)) ?></span></a>
    <?php foreach (CATEGORIAS_DO_INVENTARIO as $chave => $rotulo): ?>
      <a class="pf<?= $categoria === $chave ? ' ativa' : '' ?>" title="<?= e($rotulo) ?>" href="/fichas?<?= e(http_build_query(array_filter(['q' => $busca, 'categoria' => $chave]))) ?>"><?= $chave ?><span><?= (int) ($contas[$chave] ?? 0) ?></span></a>
    <?php endforeach; ?>
  </div>
</form>

<div id="lista-viva">
  <?php $lista(); ?>
</div>
<?php paginaFim();
