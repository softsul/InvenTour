<?php
/**
 * Mapa do Turismo: os municípios do Mapa do Turismo Brasileiro, com região
 * turística, categoria e os números que o Ministério do Turismo usa para
 * categorizar (app/MapaTurismo.php, importado todo mês da API pública).
 * Só a equipe Softsul vê esta tela, na visão da plataforma.
 * Softsul Software
 *
 * No molde da tela Cadastur: busca pelo nome do município, região, UF e
 * categoria em combos de múltipla escolha na mesma linha, ordem pelo
 * cabeçalho e paginação da casa. A coluna Cadastur conta os prestadores do
 * município e leva à lista dele; o botão do topo leva o mesmo recorte para
 * a tela Cadastur.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/MapaTurismo.php';

// Vale na plataforma e dentro do destino (só consulta): nunca troca de visão.
Auth::exigirPermissao('mapa-turismo.ver');
$pdo = Database::conexao();
$ano = MapaTurismo::ano();

$ufs = $pdo->query('SELECT uf FROM estados ORDER BY uf')->fetchAll(PDO::FETCH_COLUMN);
$busca = trim((string) ($_GET['q'] ?? ''));
$uf = Lista::escolhas('uf', $ufs);
$categoria = Lista::escolhas('categoria', array_map('strval', array_keys(MapaTurismo::CATEGORIAS)));
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = Auth::porPagina();
$parcial = !empty($_GET['parcial']);
$exportar = ($_GET['exportar'] ?? '') === 'csv';

// Região: ids que existem, com o nome para a caixa do filtro.
$pedidas = $_GET['regiao'] ?? [];
$pedidas = array_slice(array_values(array_unique(array_filter(array_map('intval', is_array($pedidas) ? $pedidas : explode('|', (string) $pedidas))))), 0, 50);
$regioes = [];
if ($pedidas) {
    $r = $pdo->prepare('SELECT r.id, r.nome, e.uf FROM regioes_turisticas r JOIN estados e ON e.id = r.estado_id WHERE r.id IN ('
        . implode(',', array_fill(0, count($pedidas), '?')) . ') ORDER BY r.nome');
    $r->execute($pedidas);
    foreach ($r->fetchAll() as $re) {
        $regioes[(string) $re['id']] = $re['nome'] . '/' . $re['uf'];
    }
}
$regiao = array_keys($regioes);

$ordem = Lista::ordem([
    'municipio'        => ['m.nome', 'e.uf'],
    'regiao'           => ['e.uf', 'r.nome', 't.categoria', 'm.nome'],
    'categoria'        => ['t.categoria', 'm.nome'],
    'empregos'         => ['t.empregos', 'm.nome'],
    'estabelecimentos' => ['t.estabelecimentos', 'm.nome'],
    'nacionais'        => ['t.visitas_nacionais', 'm.nome'],
    'internacionais'   => ['t.visitas_internacionais', 'm.nome'],
    'arrecadacao'      => ['t.arrecadacao', 'm.nome'],
], 'regiao');

$onde = ['t.ano = ?'];
$args = [(int) $ano];
if ($busca !== '') {
    $onde[] = '(m.slug LIKE ? OR m.slug LIKE ? OR r.nome LIKE ?)';
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(semAcento($busca))), '-');
    array_push($args, $slug . '%', '%-' . $slug . '%', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busca) . '%');
}
if ($regiao) {
    $onde[] = 't.regiao_id IN (' . implode(',', array_map('intval', $regiao)) . ')';
}
if ($uf) {
    $onde[] = 'e.uf IN (' . implode(',', array_fill(0, count($uf), '?')) . ')';
    array_push($args, ...$uf);
}
if ($categoria) {
    $onde[] = 't.categoria IN (' . implode(',', array_map('intval', $categoria)) . ')';
}
$from = 'FROM mapa_turismo t JOIN municipios m ON m.id = t.municipio_id JOIN estados e ON e.id = m.estado_id
         JOIN regioes_turisticas r ON r.id = t.regiao_id WHERE ' . implode(' AND ', $onde);

$t = $pdo->prepare("SELECT COUNT(*), COUNT(DISTINCT t.regiao_id), SUM(t.categoria = 1), SUM(t.categoria = 2), SUM(t.categoria = 3) {$from}");
$t->execute($args);
[$total, $nRegioes, $c1, $c2, $c3] = array_map('intval', $t->fetch(PDO::FETCH_NUM));

$selecao = "SELECT t.*, m.nome AS municipio, e.uf, r.nome AS regiao {$from} ORDER BY {$ordem['sql']}";
$numero = static fn ($v): string => $v !== null ? number_format((float) $v, 0, ',', '.') : '';

if ($exportar) {
    Auth::exigirPermissao('mapa-turismo.exportar');
    $q = $pdo->prepare($selecao);
    $q->execute($args);
    $linhas = $q->fetchAll();
    Auditoria::registrar('exportou', 'mapa_turismo', null, 'exportou ' . count($linhas) . ' municípios do Mapa do Turismo em CSV',
        null, ['q' => $busca, 'regiao' => $regiao, 'uf' => $uf, 'categoria' => $categoria]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mapa-do-turismo-' . $ano . '.csv"');
    header('Cache-Control: no-store');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, ['Município', 'UF', 'Código IBGE', 'Região turística', 'Categoria', 'Empregos', 'Estabelecimentos', 'Visitas nacionais',
        'Visitas internacionais', 'Arrecadação', 'Instância de governança', 'CNPJ da instância', 'Ano'], ';', '"', '');
    foreach ($linhas as $l) {
        fputcsv($saida, [
            $l['municipio'], $l['uf'], $l['municipio_id'], $l['regiao'], MapaTurismo::CATEGORIAS[(int) $l['categoria']] ?? '',
            $l['empregos'], $l['estabelecimentos'], $l['visitas_nacionais'], $l['visitas_internacionais'],
            $l['arrecadacao'] !== null ? number_format((float) $l['arrecadacao'], 2, ',', '') : '', $l['instancia'],
            $l['instancia_cnpj'] ? formatarCnpj((string) $l['instancia_cnpj']) : '', $l['ano'],
        ], ';', '"', '');
    }
    fclose($saida);
    exit;
}

$q = $pdo->prepare("{$selecao} LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina));
$q->execute($args);
$municipios = $q->fetchAll();

// Prestadores do Cadastur só dos municípios da página (ix_cadastur_municipio).
$cadastur = [];
if ($municipios && Auth::pode('cadastur.ver')) {
    $ids = array_map(fn ($m) => (int) $m['municipio_id'], $municipios);
    $c = $pdo->query('SELECT municipio_id, COUNT(*) FROM cadastur_prestadores WHERE municipio_id IN (' . implode(',', $ids) . ') GROUP BY municipio_id');
    $cadastur = array_map('intval', $c->fetchAll(PDO::FETCH_KEY_PAIR));
}

$comFiltro = function (array $troca) use ($busca, $regiao, $uf, $categoria, $ordem): string {
    $q = array_filter([
        'q'         => $troca['q'] ?? $busca,
        'regiao'    => $troca['regiao'] ?? $regiao,
        'uf'        => $troca['uf'] ?? $uf,
        'categoria' => $troca['categoria'] ?? $categoria,
        'ord'       => $troca['ord'] ?? ($ordem['chave'] !== 'regiao' ? $ordem['chave'] : ''),
        'dir'       => $troca['dir'] ?? ($ordem['chave'] !== 'regiao' || $ordem['dir'] !== 'asc' ? $ordem['dir'] : ''),
        'p'         => $troca['p'] ?? null,
        'exportar'  => $troca['exportar'] ?? null,
    ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    return '/mapa-turismo' . ($q ? '?' . http_build_query($q) : '');
};

$lista = function () use ($municipios, $cadastur, $busca, $total, $nRegioes, $c1, $c2, $c3, $pagina, $ordem, $comFiltro, $numero,
                          $regiao, $uf, $categoria, $ano): void {
    $n = static fn (int $v, string $um, string $varios): string => number_format($v, 0, ',', '.') . ' ' . ($v === 1 ? $um : $varios);
    $resumo = $n($total, 'município', 'municípios') . ' em ' . $n($nRegioes, 'região turística', 'regiões turísticas');
    $partes = array_filter([$c1 ? $n($c1, 'turístico', 'turísticos') : '', $c2 ? $n($c2, 'de oferta complementar', 'de oferta complementar') : '',
        $c3 ? $n($c3, 'de apoio', 'de apoio') : '']); ?>
    <p class="resumo-lista"><?= e($resumo . ($partes ? ' · ' . implode(', ', $partes) : '')) ?></p>
    <?php if (!$ano): ?>
      <div class="vazio">
        <?= icone('mapa') ?>
        <p><strong>O Mapa do Turismo ainda não foi importado.</strong></p>
        <p>A fila traz o Mapa todo mês; para trazer agora, rode scripts/importar_mapa_turismo.php no servidor.</p>
      </div>
    <?php elseif (!$municipios): ?>
      <div class="vazio">
        <?= icone('mapa') ?>
        <p><strong>Nenhum município<?= $busca !== '' ? ' para "' . e($busca) . '"' : ' neste recorte' ?>.</strong></p>
        <p>Ajuste a busca ou <a href="/mapa-turismo">volte para a lista completa</a>.</p>
      </div>
    <?php else: ?>
      <div class="rolagem">
        <table class="dados">
          <thead><tr>
            <?= Lista::th('Município', 'municipio', $ordem, $comFiltro) ?>
            <?= Lista::th('Região turística', 'regiao', $ordem, $comFiltro) ?>
            <?= Lista::th('Categoria', 'categoria', $ordem, $comFiltro) ?>
            <?= Lista::th('Empregos', 'empregos', $ordem, $comFiltro, 'num') ?>
            <?= Lista::th('Estab.', 'estabelecimentos', $ordem, $comFiltro, 'num') ?>
            <?= Lista::th('Visitas nac.', 'nacionais', $ordem, $comFiltro, 'num') ?>
            <?= Lista::th('Visitas int.', 'internacionais', $ordem, $comFiltro, 'num') ?>
            <?= Lista::th('Arrecadação', 'arrecadacao', $ordem, $comFiltro, 'num') ?>
            <?php if (Auth::pode('cadastur.ver')): ?><th class="num">Cadastur</th><?php endif; ?>
          </tr></thead>
          <tbody>
          <?php foreach ($municipios as $m): $cat = (int) $m['categoria']; ?>
            <tr>
              <td title="<?= e($m['municipio'] . '/' . $m['uf'] . ($m['instancia'] ? "\n" . $m['instancia'] . ($m['instancia_cnpj'] ? ' · ' . formatarCnpj((string) $m['instancia_cnpj']) : '') : '')) ?>"><b><?= e((string) $m['municipio']) ?></b> <span class="dado"><?= e((string) $m['uf']) ?></span></td>
              <td class="elastica" title="<?= e((string) $m['regiao']) ?>"><a href="<?= e($comFiltro(['regiao' => [(string) $m['regiao_id']], 'p' => null])) ?>"><?= e((string) $m['regiao']) ?></a></td>
              <td><span class="pilula <?= MapaTurismo::TONS[$cat] ?? 'nu' ?>" title="<?= e(MapaTurismo::CATEGORIAS[$cat] ?? '') ?>"><?= e(MapaTurismo::CATEGORIAS_CURTAS[$cat] ?? '') ?></span></td>
              <td class="num dado"><?= $numero($m['empregos']) ?></td>
              <td class="num dado"><?= $numero($m['estabelecimentos']) ?></td>
              <td class="num dado"><?= $numero($m['visitas_nacionais']) ?></td>
              <td class="num dado"><?= $numero($m['visitas_internacionais']) ?></td>
              <td class="num dado"><?= $m['arrecadacao'] !== null ? e(dinheiro((float) $m['arrecadacao'])) : '<span class="nulo">-</span>' ?></td>
              <?php if (Auth::pode('cadastur.ver')): $n = $cadastur[(int) $m['municipio_id']] ?? 0; ?>
                <td class="num dado"><?= $n ? '<a href="/cadastur?municipio=' . (int) $m['municipio_id'] . '">' . $numero($n) . '</a>' : '<span class="nulo">0</span>' ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
      echo paginacao($total, $pagina, '/mapa-turismo', array_filter([
          'q' => $busca, 'regiao' => implode('|', $regiao), 'uf' => implode('|', $uf), 'categoria' => implode('|', $categoria),
          'ord' => $ordem['chave'] !== 'regiao' ? $ordem['chave'] : '', 'dir' => $ordem['dir'] !== 'asc' ? $ordem['dir'] : '']));
    endif;
    // A exportação mora na parte que a busca ao vivo redesenha: leva sempre
    // o filtro que está na tela.
    if ($total && Auth::pode('mapa-turismo.exportar')): ?>
      <div class="acoes">
        <a class="botao claro" href="<?= e($comFiltro(['exportar' => 'csv', 'p' => null])) ?>" download><?= icone('baixar') ?>Exportar CSV (<?= number_format($total, 0, ',', '.') ?>)</a>
      </div>
    <?php endif;
};

if ($parcial) {
    $lista();
    exit;
}

$importado = $ano ? (string) $pdo->query('SELECT MAX(importado_em) FROM mapa_turismo')->fetchColumn() : '';
paginaInicio('Mapa do Turismo', 'mapa-turismo');
?>
<div class="cab-pag">
  <div>
    <h1>Mapa do Turismo<?= $ano ? ' ' . (int) $ano : '' ?></h1>
    <p>Regiões turísticas e categoria dos municípios no Mapa do Turismo Brasileiro, do Ministério do Turismo<?=
      $importado ? ' (importado em ' . e(dataBr($importado, 'd/m/Y')) . ')' : '' ?>.
      Município turístico tem fluxo de turistas; o de oferta complementar soma atrativos à região; o de apoio dá infraestrutura.</p>
  </div>
  <?php if ($ano): ?>
    <?php /* O Cadastur entende região e UF: o link sai na hora do clique,
             com o que estiver marcado no filtro (data-leva-filtros). */ ?>
    <div class="acoes">
      <button type="button" class="botao claro" data-abre="modal-mapa-turismo"><?= icone('mapa') ?>Ver no mapa</button>
      <?php if (Auth::pode('cadastur.ver')): ?>
        <a class="botao claro" href="/cadastur<?= $regiao || $uf ? '?' . e(http_build_query(array_filter(['regiao' => implode('|', $regiao), 'uf' => implode('|', $uf)]))) : '' ?>"
           data-leva-filtros="regiao uf"><?= icone('certificado') ?>Prestadores no Cadastur</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php /* Cada região turística numa cor, na mesma malha do mapa do Cadastur
         (inventourMalha no inventour.js); o tom é a categoria. */ ?>
<dialog class="confirmador janela mapa-janela" id="modal-mapa-turismo" aria-labelledby="titulo-mapa-turismo"
        data-fonte="/api/mapa-turismo-mapa" data-versao="<?= (int) @filemtime(__DIR__ . '/assets/mapa/municipios.svg') ?>">
  <header class="cabeca-janela"><b id="titulo-mapa-turismo">Regiões turísticas no mapa</b>
    <button type="button" data-fecha aria-label="Fechar">&times;</button></header>
  <div class="mapa-corpo">
    <div class="mapa-etiquetas mapa-regioes" role="group" aria-label="Regiões turísticas no mapa"></div>
    <div class="mapa-principal">
    <div class="mapa-legenda">
      <span class="mapa-escala"><span class="rotulo-escala">Tom</span>
        <span class="faixa"><i class="mapa-cor neutra"></i>Turístico</span>
        <span class="faixa"><i class="mapa-cor neutra t2"></i>Oferta complementar</span>
        <span class="faixa"><i class="mapa-cor neutra t3"></i>Apoio</span></span>
      <div class="entrada estreita mapa-uf"><?= icone('mapa') ?><select aria-label="Ir para a região ou o estado">
        <option value="">Brasil inteiro</option>
        <optgroup label="Regiões"><option value="R-N">Região Norte</option><option value="R-NE">Região Nordeste</option><option value="R-CO">Região Centro-Oeste</option><option value="R-SE">Região Sudeste</option><option value="R-S">Região Sul</option></optgroup>
        <optgroup label="Estados"><?php foreach ($ufs as $sigla): ?><option><?= e($sigla) ?></option><?php endforeach; ?></optgroup>
      </select></div>
      <span class="mapa-dica">Cada região turística numa cor; município sem cor está fora do Mapa ou do filtro. Roda do mouse aproxima, arraste para mover, duplo clique volta ao Brasil inteiro.</span>
    </div>
    <div class="mapa-area"><span class="mapa-carregando">Carregando o mapa...</span></div>
    </div>
    <div class="mapa-cartao" hidden></div>
  </div>
</dialog>

<form class="filtros ev-filtros" method="get" action="/mapa-turismo">
  <div class="ev-filtros-linha">
    <div class="entrada ev-busca">
      <?= icone('busca') ?>
      <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Município ou região" aria-label="Buscar no Mapa do Turismo">
    </div>
    <?= multiEscolhaFiltro(['campo' => 'regiao', 'icone' => 'mapa', 'rotulo' => 'Região turística', 'vazio' => 'Qualquer região',
        'plural' => 'regiões', 'opcoes' => $regioes, 'escolhidos' => $regiao, 'fonte' => '/api/regioes-turisticas',
        'buscar' => 'Digite o nome da região']) ?>
    <?= multiEscolhaFiltro(['campo' => 'uf', 'icone' => 'rede', 'rotulo' => 'UF', 'vazio' => 'Todas as UFs', 'plural' => 'UFs',
        'opcoes' => array_combine($ufs, $ufs), 'escolhidos' => $uf]) ?>
    <?= multiEscolhaFiltro(['campo' => 'categoria', 'icone' => 'etiqueta', 'rotulo' => 'Categoria', 'vazio' => 'Todas as categorias',
        'plural' => 'categorias', 'opcoes' => MapaTurismo::CATEGORIAS, 'escolhidos' => $categoria]) ?>
  </div>
</form>

<div id="lista-viva">
  <?php $lista(); ?>
</div>
<?php paginaFim();
