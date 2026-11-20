<?php
/**
 * Cadastur: consulta da cópia local dos dados abertos do Ministério do
 * Turismo (app/Cadastur.php, importada todo trimestre). A equipe Softsul vê
 * na visão da plataforma; os destinos, só para consulta (sem exportar).
 * Softsul Software
 *
 * No molde da lista de destinos: busca que filtra enquanto se digita, ordem
 * pelo cabeçalho e paginação da casa, com município, região turística (do
 * Mapa do Turismo), UF, atividade e situação em combos na mesma linha. Acima da tabela vai a soma de leitos
 * do recorte, que é o número que a tela de conflitos usa quando o destino
 * não informa os leitos à mão.
 *
 * A base tem CPF de guia de turismo: o CPF aparece mascarado na tela e no
 * CSV, e a exportação fica na auditoria.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Cadastur.php';

// Vale na plataforma e dentro do destino (só consulta): nunca troca de visão.
Auth::exigirPermissao('cadastur.ver');
$pdo = Database::conexao();

// Acima disto o CSV não sai: o país inteiro é filtro de menos.
const LIMITE_CSV_CADASTUR = 20000;

$atividades = $pdo->query('SELECT DISTINCT atividade FROM cadastur_prestadores ORDER BY atividade')->fetchAll(PDO::FETCH_COLUMN);
$ufs = $pdo->query('SELECT uf FROM estados ORDER BY uf')->fetchAll(PDO::FETCH_COLUMN);

$busca = trim((string) ($_GET['q'] ?? ''));
// UF, atividade e situação são de múltipla escolha; o mapa manda as
// atividades das etiquetas como "a|b" e cai no mesmo lugar.
$uf = Lista::escolhas('uf', $ufs);
$atividade = Lista::escolhas('atividade', $atividades);
$situacao = Lista::escolhas('situacao', array_keys(Cadastur::SITUACOES));
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = Auth::porPagina();
$parcial = !empty($_GET['parcial']);
$exportar = ($_GET['exportar'] ?? '') === 'csv';

// Município também é de múltipla escolha (cidades vizinhas): vale o que
// existe no IBGE, com o nome para a caixa do filtro.
$pedidos = $_GET['municipio'] ?? [];
$pedidos = array_values(array_unique(array_filter(array_map('intval', is_array($pedidos) ? $pedidos : explode('|', (string) $pedidos)))));
$pedidos = array_slice($pedidos, 0, 50);
$municipios = [];
if ($pedidos) {
    $m = $pdo->prepare('SELECT m.id, m.nome, e.uf FROM municipios m JOIN estados e ON e.id = m.estado_id WHERE m.id IN ('
        . implode(',', array_fill(0, count($pedidos), '?')) . ') ORDER BY m.nome');
    $m->execute($pedidos);
    foreach ($m->fetchAll() as $mu) {
        $municipios[(string) $mu['id']] = $mu['nome'] . '/' . $mu['uf'];
    }
}
$municipio = array_keys($municipios);

// Região turística do Mapa do Turismo, também de múltipla escolha.
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
    'nome'        => ['c.nome_exibicao', 'c.atividade'],
    'documento'   => 'c.documento',
    'atividade'   => ['c.atividade', 'c.nome_exibicao'],
    'municipio'   => ['c.uf', 'c.municipio', 'c.nome_exibicao'],
    'certificado' => 'c.certificado',
    'validade'    => ['c.validade IS NULL', 'c.validade'],
    'leitos'      => ['c.leitos IS NULL', 'c.leitos'],
    'situacao'    => [Cadastur::SITUACAO_SQL, 'c.nome_exibicao'],
], 'nome');

[$onde, $args] = Cadastur::recorte(['q' => $busca, 'municipio' => $municipio, 'regiao' => $regiao, 'uf' => $uf, 'atividade' => $atividade, 'situacao' => $situacao]);
$where = $onde ? 'WHERE ' . implode(' AND ', $onde) : '';

// Sem filtro, os totais do país vêm guardados (só mudam na importação).
if ($where === '') {
    [$total, $leitos, $unidades] = Cadastur::totais();
} else {
    $t = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(c.leitos), 0), COALESCE(SUM(c.unidades), 0) FROM cadastur_prestadores c {$where}");
    $t->execute($args);
    [$total, $leitos, $unidades] = array_map('intval', $t->fetch(PDO::FETCH_NUM));
}

/** CPF de pessoa física aparece só pelo meio: 123.456.789-01 vira ***.456.789-**. */
$documento = static function (array $c): string {
    $d = (string) $c['documento'];
    if ($c['tipo_documento'] === 'cpf' && strlen($d) === 11) {
        return '***.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-**';
    }
    return formatarCnpj($d);
};
$validade = static fn (array $c): bool => $c['validade'] !== null && (string) $c['validade'] < date('Y-m-d');
$situacaoDe = [Cadastur::class, 'situacao'];

$selecao = "SELECT c.* FROM cadastur_prestadores c {$where} ORDER BY {$ordem['sql']}";

if ($exportar) {
    Auth::exigirPermissao('cadastur.exportar');
    if ($total > LIMITE_CSV_CADASTUR) {
        avisar('São ' . number_format($total, 0, ',', '.') . ' prestadores: filtre por UF, município ou atividade para exportar até '
            . number_format(LIMITE_CSV_CADASTUR, 0, ',', '.') . '.', 'ruim');
        redirecionar('/cadastur');
    }
    $q = $pdo->prepare($selecao);
    $q->execute($args);
    $linhas = $q->fetchAll();
    Auditoria::registrar('exportou', 'cadastur_prestadores', null, 'exportou ' . count($linhas) . ' prestadores do Cadastur em CSV',
        null, ['q' => $busca, 'municipio' => $municipio, 'regiao' => $regiao, 'uf' => $uf, 'atividade' => $atividade, 'situacao' => $situacao]);
    $celula = static function (?string $v): string {
        $v = (string) $v;
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cadastur-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF"); // o Excel só lê acento em UTF-8 com a marca no começo
    fputcsv($saida, ['Nome fantasia', 'Nome jurídico', 'Documento', 'Atividade', 'Natureza jurídica', 'Município', 'UF',
        'Certificado', 'Validade', 'Leitos', 'Unidades', 'Categorias', 'Situação', 'Situação da atividade'], ';', '"', '');
    foreach ($linhas as $c) {
        fputcsv($saida, array_map($celula, [
            $c['nome_fantasia'], $c['nome_juridico'], $documento($c), $c['atividade'], $c['natureza_juridica'], $c['municipio'], $c['uf'],
            $c['certificado'], $c['validade'] ? dataBr($c['validade'], 'd/m/Y') : '', $c['leitos'] !== null ? (string) $c['leitos'] : '',
            $c['unidades'] !== null ? (string) $c['unidades'] : '', $c['categorias'], $situacaoDe($c), $c['situacao_atividade'],
        ]), ';', '"', '');
    }
    fclose($saida);
    exit;
}

// A página sai em dois passos: as chaves pelo índice (ix_cadastur_nome
// cobre a ordem padrão) e só depois as linhas inteiras. Com SELECT c.* e
// OFFSET grande o MySQL ordenava a tabela toda (5 s na página 100).
$q = $pdo->prepare("SELECT c.* FROM cadastur_prestadores c JOIN (SELECT c.documento, c.atividade FROM cadastur_prestadores c {$where}
                    ORDER BY {$ordem['sql']} LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina) . ") p USING (documento, atividade)
                    ORDER BY {$ordem['sql']}");
$q->execute($args);
$prestadores = $q->fetchAll();

$comFiltro = function (array $troca) use ($busca, $municipio, $regiao, $uf, $atividade, $situacao, $ordem): string {
    $q = array_filter([
        'q'         => $troca['q'] ?? $busca,
        'municipio' => $troca['municipio'] ?? $municipio,
        'regiao'    => $troca['regiao'] ?? $regiao,
        'uf'        => $troca['uf'] ?? $uf,
        'atividade' => $troca['atividade'] ?? $atividade,
        'situacao'  => $troca['situacao'] ?? $situacao,
        'ord'       => $troca['ord'] ?? ($ordem['chave'] !== 'nome' ? $ordem['chave'] : ''),
        'dir'       => $troca['dir'] ?? ($ordem['chave'] !== 'nome' || $ordem['dir'] !== 'asc' ? $ordem['dir'] : ''),
        'p'         => $troca['p'] ?? null,
        'exportar'  => $troca['exportar'] ?? null,
    ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    return '/cadastur' . ($q ? '?' . http_build_query($q) : '');
};

$lista = function () use ($prestadores, $busca, $total, $leitos, $unidades, $pagina, $ordem, $comFiltro, $documento, $validade, $situacaoDe,
                          $municipio, $regiao, $uf, $atividade, $situacao): void {
    $recorte = number_format($total, 0, ',', '.') . ' ' . ($total === 1 ? 'prestador' : 'prestadores');
    if ($leitos) {
        $recorte .= ' · ' . number_format($leitos, 0, ',', '.') . ' leitos';
    }
    if ($unidades) {
        $recorte .= ' · ' . number_format($unidades, 0, ',', '.') . ' unidades habitacionais';
    } ?>
    <p class="resumo-lista"><?= e($recorte) ?></p>
    <?php if (!$prestadores): ?>
      <div class="vazio">
        <?= icone('certificado') ?>
        <p><strong>Nenhum prestador<?= $busca !== '' ? ' para "' . e($busca) . '"' : ' neste recorte' ?>.</strong></p>
        <p>Ajuste a busca ou <a href="/cadastur">volte para a lista completa</a>.</p>
      </div>
    <?php else: ?>
      <div class="rolagem">
        <table class="dados">
          <thead><tr>
            <?= Lista::th('Prestador', 'nome', $ordem, $comFiltro) ?>
            <?= Lista::th('Documento', 'documento', $ordem, $comFiltro) ?>
            <?= Lista::th('Atividade', 'atividade', $ordem, $comFiltro) ?>
            <?= Lista::th('Município', 'municipio', $ordem, $comFiltro) ?>
            <?= Lista::th('Certificado', 'certificado', $ordem, $comFiltro) ?>
            <?= Lista::th('Validade', 'validade', $ordem, $comFiltro) ?>
            <?= Lista::th('Leitos', 'leitos', $ordem, $comFiltro, 'num') ?>
            <?= Lista::th('Situação', 'situacao', $ordem, $comFiltro) ?>
          </tr></thead>
          <tbody>
          <?php foreach ($prestadores as $c):
            $nome = trim((string) ($c['nome_fantasia'] ?: $c['nome_juridico']));
            $juridico = trim((string) $c['nome_juridico']);
            $categorias = trim((string) $c['categorias']); ?>
            <tr>
              <td class="elastica" title="<?= e($nome . ($juridico !== '' && $juridico !== $nome ? "\n" . $juridico : '') . ($categorias !== '' ? "\n" . $categorias : '')) ?>"><b><a href="/prestador/<?= (int) $c['id'] ?>" data-ficha="<?= e($nome !== '' ? $nome : 'Prestador') ?>"><?= e($nome !== '' ? $nome : '-') ?></a></b></td>
              <td class="dado"><?= e($documento($c)) ?></td>
              <td title="<?= e((string) $c['atividade']) ?>"><?= e((string) $c['atividade']) ?></td>
              <td><?= $c['municipio'] ? e((string) $c['municipio']) . ' <span class="dado">' . e((string) $c['uf']) . '</span>' : '<span class="nulo">-</span>' ?></td>
              <td class="dado"><?= $c['certificado'] ? e((string) $c['certificado']) : '<span class="nulo">-</span>' ?></td>
              <td class="dado"><?= $c['validade'] ? ($validade($c) ? '<span class="pilula ruim">' . e(dataBr($c['validade'], 'd/m/Y')) . '</span>' : e(dataBr($c['validade'], 'd/m/Y'))) : '<span class="nulo">-</span>' ?></td>
              <td class="num dado"><?= $c['leitos'] !== null ? number_format((int) $c['leitos'], 0, ',', '.') : '<span class="nulo">-</span>' ?></td>
              <td><?= match ($situacaoDe($c)) {
                  'Regular' => '<span class="pilula ok">Regular</span>',
                  'Vencido' => '<span class="pilula ruim">Vencido</span>',
                  ''        => '<span class="nulo">-</span>',
                  default   => '<span class="pilula at">' . e((string) $c['situacao_cadastral']) . '</span>',
              } ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
      echo paginacao($total, $pagina, '/cadastur', array_filter([
          'q' => $busca, 'municipio' => implode('|', $municipio), 'regiao' => implode('|', $regiao), 'uf' => implode('|', $uf), 'atividade' => implode('|', $atividade), 'situacao' => implode('|', $situacao),
          'ord' => $ordem['chave'] !== 'nome' ? $ordem['chave'] : '', 'dir' => $ordem['dir'] !== 'asc' ? $ordem['dir'] : '']));
    endif;
    // O link de exportação mora na parte que a busca ao vivo redesenha:
    // assim ele leva sempre o filtro que está na tela.
    if ($total && $total <= LIMITE_CSV_CADASTUR && Auth::pode('cadastur.exportar')): ?>
      <div class="acoes"><a class="botao claro" href="<?= e($comFiltro(['exportar' => 'csv', 'p' => null])) ?>" download><?= icone('baixar') ?>Exportar CSV (<?= number_format($total, 0, ',', '.') ?>)</a></div>
    <?php endif;
};

if ($parcial) {
    $lista();
    exit;
}

$base = Cadastur::base();
paginaInicio('Cadastur', 'cadastur');
?>
<div class="cab-pag">
  <div>
    <h1>Cadastur</h1>
    <p>Prestadores de serviços turísticos cadastrados no Ministério do Turismo, dos dados abertos<?=
      $base ? ' (' . e((string) $base['recurso_nome']) . ', importado em ' . e(dataBr((string) $base['concluida_em'], 'd/m/Y')) . ')' : '' ?>.
      A soma de leitos do município é a que os conflitos usam quando o destino não informa os leitos.</p>
  </div>
  <div class="acoes"><button type="button" class="botao claro" data-abre="modal-mapa-cadastur"><?= icone('mapa') ?>Ver no mapa</button></div>
</div>

<?php /* Mapa do Brasil por município, no molde do mapa de cobertura do
         FrameFlow: a malha do IBGE em SVG, sem biblioteca. As etiquetas de
         atividade escolhem o que entra na contagem; a cor é a quantidade. */ ?>
<dialog class="confirmador janela mapa-janela" id="modal-mapa-cadastur" aria-labelledby="titulo-mapa-cadastur"
        data-fonte="/api/cadastur-mapa" data-versao="<?= (int) @filemtime(__DIR__ . '/assets/mapa/municipios.svg') ?>">
  <header class="cabeca-janela"><b id="titulo-mapa-cadastur">Cadastur no mapa</b>
    <button type="button" data-fecha aria-label="Fechar">&times;</button></header>
  <div class="mapa-corpo">
    <div class="mapa-etiquetas" role="group" aria-label="Atividades no mapa">
      <button type="button" class="mapa-tag todas ativa" data-tag="" aria-pressed="true">Todas as atividades<span></span></button>
    </div>
    <div class="mapa-principal">
    <div class="mapa-legenda">
      <span class="mapa-escala" aria-label="Prestadores por município"></span>
      <div class="entrada estreita mapa-uf"><?= icone('mapa') ?><select aria-label="Ir para a região ou o estado">
        <option value="">Brasil inteiro</option>
        <optgroup label="Regiões"><option value="R-N">Região Norte</option><option value="R-NE">Região Nordeste</option><option value="R-CO">Região Centro-Oeste</option><option value="R-SE">Região Sudeste</option><option value="R-S">Região Sul</option></optgroup>
        <optgroup label="Estados"><?php foreach ($ufs as $sigla): ?><option><?= e($sigla) ?></option><?php endforeach; ?></optgroup>
      </select></div>
      <span class="mapa-dica">Roda do mouse aproxima, arraste para mover, duplo clique volta ao Brasil inteiro.</span>
    </div>
    <div class="mapa-area"><span class="mapa-carregando">Carregando o mapa...</span></div>
    </div>
    <div class="mapa-cartao" hidden></div>
  </div>
</dialog>

<form class="filtros ev-filtros" method="get" action="/cadastur">
  <div class="ev-filtros-linha">
    <div class="entrada ev-busca">
      <?= icone('busca') ?>
      <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome, CNPJ ou certificado" aria-label="Buscar no Cadastur">
    </div>
    <?= multiEscolhaFiltro(['campo' => 'municipio', 'icone' => 'mapa', 'rotulo' => 'Município', 'vazio' => 'Qualquer município',
        'plural' => 'municípios', 'opcoes' => $municipios, 'escolhidos' => $municipio, 'fonte' => '/api/municipios',
        'buscar' => 'Digite o nome da cidade']) ?>
    <?= multiEscolhaFiltro(['campo' => 'regiao', 'icone' => 'rede', 'rotulo' => 'Região turística', 'vazio' => 'Qualquer região',
        'plural' => 'regiões', 'opcoes' => $regioes, 'escolhidos' => $regiao, 'fonte' => '/api/regioes-turisticas',
        'buscar' => 'Digite o nome da região']) ?>
    <?= multiEscolhaFiltro(['campo' => 'uf', 'icone' => 'rede', 'rotulo' => 'UF', 'vazio' => 'Todas as UFs', 'plural' => 'UFs',
        'opcoes' => array_combine($ufs, $ufs), 'escolhidos' => $uf]) ?>
    <?= multiEscolhaFiltro(['campo' => 'atividade', 'icone' => 'etiqueta', 'rotulo' => 'Atividade', 'vazio' => 'Todas as atividades',
        'plural' => 'atividades', 'opcoes' => array_combine($atividades, $atividades), 'escolhidos' => $atividade]) ?>
    <?= multiEscolhaFiltro(['campo' => 'situacao', 'icone' => 'check', 'rotulo' => 'Situação', 'vazio' => 'Qualquer situação',
        'plural' => 'situações', 'opcoes' => array_map(fn ($s) => $s[0], Cadastur::SITUACOES), 'escolhidos' => $situacao]) ?>
  </div>
</form>

<div id="lista-viva">
  <?php $lista(); ?>
</div>
<?php paginaFim();
