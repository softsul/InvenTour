<?php
/**
 * A tela de um relatório: filtros, tabela, PDF e CSV.
 * Softsul Software
 *
 * No molde do Orbit: uma tela para todos os relatórios. O que muda de um
 * para outro está em app/Relatorio.php (colunas e consulta); aqui ficam o
 * período, os filtros, a exportação e a folha de impressão.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Relatorio.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('relatorios.ver');
Auth::exigirModuloLigado('relatorios');
$pdo = Database::conexao();

$tipo = (string) ($_GET['tipo'] ?? '');
$def = Relatorio::achar($tipo);
if (!$def) {
    paginaErro(404, 'Relatório não encontrado, ou fora do seu acesso.');
}

// ------------------------------------------------------------------ período
// A agenda e os conflitos olham para a frente; o resto, para trás. O padrão
// é o mês atual, como no Orbit: "de 23/10/2025 a 23/10/2026" se lê como o
// dia de hoje, e quem abre acha que o filtro não funcionou.
$hoje = date('Y-m-d');
$frente = !empty($def['frente']);
$atalhos = ['mes' => ['nome' => 'Mês atual', 'de' => date('Y-m-01'), 'ate' => date('Y-m-t')]];
foreach ([3, 6, 12] as $n) {
    $atalhos[(string) $n] = $frente
        ? ['nome' => plural($n, 'mês', 'meses'), 'de' => $hoje, 'ate' => date('Y-m-d', strtotime("+{$n} months"))]
        : ['nome' => plural($n, 'mês', 'meses'), 'de' => date('Y-m-d', strtotime("-{$n} months")), 'ate' => $hoje];
}
$data = fn (string $v): bool => (bool) preg_match('/^(199\d|20\d\d|2100)-\d{2}-\d{2}$/', $v);
$de = (string) ($_GET['de'] ?? '');
$ate = (string) ($_GET['ate'] ?? '');
if (!$data($de)) {
    $de = $atalhos['mes']['de'];
}
if (!$data($ate)) {
    $ate = $atalhos['mes']['ate'];
}
if ($de > $ate) {
    [$de, $ate] = [$ate, $de];
}

// ------------------------------------------------ categoria do MTur
// A, B e C (infraestrutura de apoio, serviços e equipamentos, atrativos),
// de múltipla escolha, nos relatórios do inventário.
$categorias = !empty($def['categoria']) ? array_map(fn ($k, $v) => $k . '. ' . $v, array_keys(Inventario::CATEGORIAS), Inventario::CATEGORIAS) : [];
$categorias = $categorias ? array_combine(array_keys(Inventario::CATEGORIAS), $categorias) : [];
$catId = $categorias ? Lista::escolhas('categoria', array_keys($categorias)) : [];
$catNome = implode(', ', array_map(fn ($c) => $categorias[$c], $catId));
$semPeriodo = !empty($def['sem_periodo']);

$situacoes = (array) ($def['situacoes'] ?? []);
$situacao = (string) ($_GET['situacao'] ?? '');
if (!array_key_exists($situacao, $situacoes)) {
    $situacao = '';
}

$filtro = ['de' => $de, 'ate' => $ate, 'categoria' => $catId, 'situacao' => $situacao];
$dados = Relatorio::rodar((string) $def['chave'], $filtro);

/** O total de uma coluna: dinheiro como dinheiro, contagem como número inteiro. */
$totalDe = fn (array $c, float $v): string => $c['tipo'] === 'moeda' ? moedaBr($v) : number_format($v, 0, ',', '.');

/** Como cada tipo de coluna vira texto. Vale para a tela, o PDF e o CSV. */
$mostrar = function (array $coluna, array $linha, bool $paraCsv = false): string {
    $v = $linha[$coluna['chave']] ?? null;
    if ($v === null || $v === '') {
        return $paraCsv ? '' : '-';
    }
    switch ($coluna['tipo'] ?? 'texto') {
        case 'moeda':
            return $paraCsv ? number_format((float) $v, 2, ',', '') : moedaBr((float) $v);
        case 'data':
            return dataBr((string) $v, 'd/m/Y');
        case 'datahora':
            return dataBr((string) $v, 'd/m/Y H:i');
        case 'numero':
            return $paraCsv ? (string) (int) $v : number_format((float) $v, 0, ',', '.');
        case 'percentual':
            return $paraCsv ? number_format((float) $v, 1, ',', '') : number_format((float) $v, 0, ',', '.') . '%';
        default:
            return (string) $v;
    }
};

/** A segunda linha da célula, quando a coluna tem uma ("sub"): o local embaixo do evento. */
$sub = function (array $coluna, array $linha): string {
    $v = trim((string) ($linha[$coluna['sub'] ?? ''] ?? ''));
    if (empty($coluna['sub']) || $v === '') {
        return '';
    }
    return '<span class="sub-celula">' . e($v) . '</span>';
};

$titulo = (string) $def['nome'];
$periodo = $semPeriodo ? 'Situação em ' . date('d/m/Y') : 'de ' . dataBr($de, 'd/m/Y') . ' a ' . dataBr($ate, 'd/m/Y') . ' · por ' . $def['data'];
$recorte = implode(' · ', array_filter([$catNome, $situacao !== '' ? mb_strtolower((string) $situacoes[$situacao]) : '']));
$parteNome = $recorte;
$assinado = 'Gerado em ' . date('d/m/Y H:i') . ' por ' . (Auth::usuarioOuNulo()['nome'] ?? 'alguém') . ' · InvenTour';

// --------------------------------------------------------------- exportação
$saida = (string) ($_GET['saida'] ?? '');
if ($saida === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio-' . $def['chave'] . '-' . $de . '-a-' . $ate . '.csv"');
    $f = fopen('php://output', 'w');
    fputs($f, "\xEF\xBB\xBF");   // BOM, senão o Excel come os acentos
    // Coluna com segunda linha vira duas colunas: na planilha, evento e local
    // juntos na mesma célula não dão para filtrar.
    $cabecalho = [];
    foreach ($dados['colunas'] as $c) {
        $cabecalho[] = $c['titulo'];
        if (!empty($c['sub'])) {
            $cabecalho[] = $c['sub_titulo'] ?? ucfirst(str_replace('_', ' ', (string) $c['sub']));
        }
    }
    fputcsv($f, $cabecalho, ';');
    foreach ($dados['linhas'] as $linha) {
        $celulas = [];
        foreach ($dados['colunas'] as $c) {
            $celulas[] = $mostrar($c, $linha, true);
            if (!empty($c['sub'])) {
                $celulas[] = (string) ($linha[$c['sub']] ?? '');
            }
        }
        fputcsv($f, $celulas, ';');
    }
    if ($dados['totais']) {
        $rodape = [];
        foreach ($dados['colunas'] as $i => $c) {
            $rodape[] = isset($dados['totais'][$c['chave']])
                ? number_format($dados['totais'][$c['chave']], $c['tipo'] === 'moeda' ? 2 : 0, ',', '')
                : ($i === 0 ? 'TOTAL' : '');
            if (!empty($c['sub'])) {
                $rodape[] = '';
            }
        }
        fputcsv($f, $rodape, ';');
    }
    fclose($f);
    Auditoria::registrar('exportou', 'relatorios', null, $def['chave'] . ' em CSV');
    exit;
}

if ($saida === 'pdf') {
    require_once __DIR__ . '/../app/Pdf.php';
    ob_start();
    include __DIR__ . '/../app/vistas/relatorio_pdf.php';
    $html = (string) ob_get_clean();
    try {
        $pdf = Pdf::deHtml($html, ['rodape_texto' => $titulo . ' · ' . $periodo, 'deitado' => !empty($def['deitado']),
            'cabecalho_html' => $cabecalhoHtml, 'topo' => '34']);
    } catch (Throwable $e) {
        error_log('relatorio-pdf: ' . $e->getMessage());
        paginaErro(500, 'Não consegui gerar o PDF. O erro foi registrado.');
    }
    Auditoria::registrar('exportou', 'relatorios', null, $def['chave'] . ' em PDF');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="relatorio-' . $def['chave'] . '-' . $de . '.pdf"');
    echo $pdf;
    exit;
}

// Todo link carrega o filtro inteiro: trocar o período não desfaz a situação.
$linkCom = function (array $troca) use ($tipo, $de, $ate, $catId, $situacao, $semPeriodo): string {
    return '/relatorio?' . http_build_query(array_filter(array_merge(
        ['tipo' => $tipo, 'de' => $semPeriodo ? null : $de, 'ate' => $semPeriodo ? null : $ate, 'categoria' => implode('|', $catId), 'situacao' => $situacao], $troca
    ), fn ($v) => $v !== null && $v !== ''));
};

paginaInicio($titulo, 'relatorios');
?>
<div class="trilha"><a href="/relatorios">Relatórios</a><?= icone('seta') ?><span><?= e($titulo) ?></span></div>

<div class="cab-pag">
  <div>
    <h1><?= e($titulo) ?></h1>
    <p><?= e($periodo) ?><?= $recorte !== '' ? ' · ' . e($recorte) : '' ?></p>
  </div>
  <div class="acoes">
    <a class="botao claro" href="<?= e($linkCom(['saida' => 'csv'])) ?>"><?= icone('baixar') ?>CSV</a>
    <a class="botao ver-pdf" data-nome="<?= e($titulo) ?>" href="<?= e($linkCom(['saida' => 'pdf'])) ?>"><?= icone('arquivo') ?>PDF</a>
  </div>
</div>

<?php if (!empty($def['explicacao'])): ?>
  <details class="cartao explicacao-relatorio">
    <summary><strong><?= e((string) ($def['explicacao_titulo'] ?? 'Como é calculado')) ?></strong></summary>
    <p class="ajuda"><?= e((string) $def['explicacao']) ?></p>
  </details>
<?php endif; ?>

<form class="filtros filtros-relatorio" method="get" action="/relatorio" data-fixos="tipo">
  <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
  <?php if ($categorias): ?>
    <?= multiEscolhaFiltro(['campo' => 'categoria', 'icone' => 'etiqueta', 'rotulo' => 'Categoria', 'vazio' => 'Todas as categorias',
        'plural' => 'categorias', 'opcoes' => $categorias, 'escolhidos' => $catId, 'enviar' => true]) ?>
  <?php endif; ?>
  <?php if ($situacoes): ?>
    <div class="entrada filtro-curto"><?= icone('lista') ?><select name="situacao" aria-label="Situação" data-enviar-ao-mudar>
      <?php foreach ($situacoes as $chave => $rotulo): ?><option value="<?= e((string) $chave) ?>"<?= $situacao === (string) $chave ? ' selected' : '' ?>><?= e($rotulo) ?></option><?php endforeach; ?>
    </select></div>
  <?php endif; ?>
  <?php if (!$semPeriodo): ?>
  <div class="entrada filtro-data"><input type="date" name="de" value="<?= e($de) ?>" data-filtra-data aria-label="Início"></div>
  <div class="entrada filtro-data"><input type="date" name="ate" value="<?= e($ate) ?>" data-filtra-data aria-label="Fim"></div>
  <div class="pilulas-filtro">
    <?php foreach ($atalhos as $a): $ativa = $de === $a['de'] && $ate === $a['ate']; ?>
      <a class="pf<?= $ativa ? ' ativa' : '' ?>" href="<?= e($linkCom(['de' => $a['de'], 'ate' => $a['ate']])) ?>"><?= e($a['nome']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</form>

<?php if (!$dados['linhas']): ?>
  <div class="vazio"><?= icone((string) $def['icone']) ?>
    <p><strong><?= $semPeriodo ? 'Nada com esse recorte.' : 'Nada no período.' ?></strong></p>
    <p><?= $semPeriodo ? 'Experimente tirar os filtros. Os relatórios do inventário contam o que está homologado ou no portal.' : 'O período conta por ' . e((string) $def['data']) . '. Experimente alargar o intervalo ou tirar os filtros.' ?></p>
  </div>
<?php else: ?>
  <div class="cartao">
    <div class="cab-cartao">
      <h2><?= plural(count($dados['linhas']), 'linha', 'linhas') ?></h2>
      <?php if ($dados['totais']): ?>
        <span class="dado">
          <?php foreach ($dados['colunas'] as $c): if (!isset($dados['totais'][$c['chave']])) { continue; } ?>
            <?= e($c['titulo']) ?> <?= e($totalDe($c, (float) $dados['totais'][$c['chave']])) ?>&nbsp;&nbsp;
          <?php endforeach; ?>
        </span>
      <?php endif; ?>
    </div>
    <div class="rolagem sem-borda">
      <table class="dados">
        <thead><tr>
          <?php foreach ($dados['colunas'] as $c): ?>
            <th class="<?= in_array($c['tipo'], ['moeda', 'numero', 'percentual'], true) ? 'num' : '' ?>"><?= e($c['titulo']) ?></th>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
          <?php $elastica = array_search('texto', array_column($dados['colunas'], 'tipo'), true); ?>
          <?php foreach ($dados['linhas'] as $linha): ?>
            <tr>
              <?php foreach ($dados['colunas'] as $i => $c): $ehNumero = in_array($c['tipo'], ['moeda', 'numero', 'percentual'], true); ?>
                <td class="<?= $ehNumero ? 'num dado' : '' ?><?= in_array($c['tipo'], ['data', 'datahora'], true) ? ' dado nao-quebra' : '' ?><?= $i === $elastica ? ' elastica' : '' ?>"><?=
                  $c['chave'] === 'alerta' && $linha['alerta'] !== '' ? '<span class="pilula ruim">' . e((string) $linha['alerta']) . '</span>' : e($mostrar($c, $linha)) . $sub($c, $linha) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($dados['totais']): ?>
          <tfoot><tr>
            <?php foreach ($dados['colunas'] as $i => $c): ?>
              <td class="<?= isset($dados['totais'][$c['chave']]) ? 'num dado' : '' ?>"><?=
                isset($dados['totais'][$c['chave']]) ? '<b>' . e($totalDe($c, (float) $dados['totais'][$c['chave']])) . '</b>' : ($i === 0 ? '<b>Total</b>' : '') ?></td>
            <?php endforeach; ?>
          </tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php paginaFim();
