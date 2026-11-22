<?php
/**
 * A folha de papel de um relatório.
 * Softsul Software
 *
 * No molde do Orbit: fundo branco (o cinza da tela, no papel, é tinta gasta
 * em nada), logotipo embutido e nenhuma folha de estilo externa, porque o
 * gerador roda sem rede.
 *
 * @var array    $def       definição do relatório
 * @var array    $dados     colunas, linhas e totais
 * @var string   $titulo
 * @var string   $periodo
 * @var string   $parteNome categoria e situação escolhidas
 * @var string   $assinado
 * @var array    $empresa
 * @var callable $mostrar
 * @var callable $sub
 */
$logo = Pdf::embutir(__DIR__ . '/../../public/assets/icone-192.png');
$tinta = '#141A2B';
$fraca = '#6E7687';
$traco = '#E3E8F4';
$numericos = ['moeda', 'numero', 'percentual'];

// O cabeçalho (marca, título, período, destino e quem gerou) vai à parte, em
// $cabecalhoHtml: o wkhtmltopdf o repete no alto de todas as páginas.
ob_start();
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<style>
  html, body{margin:0; padding:0; background:#FFFFFF}
  body{color:<?= $tinta ?>; font-family:-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; font-size:11px; line-height:1.5}
  .topo{display:flex; display:-webkit-box; -webkit-box-pack:justify; -webkit-box-align:end; justify-content:space-between; align-items:flex-end;
    border-bottom:2px solid <?= $tinta ?>; padding-bottom:8px}
  .topo > div:first-child{-webkit-box-flex:1}
  .marca{margin-bottom:4px}
  .marca img{height:22px; border-radius:6px; vertical-align:middle}
  .marca b{font-size:13px; color:#0E7C66; letter-spacing:-.01em; vertical-align:middle; margin-left:6px}
  h1{margin:0 0 1px; font-size:16px; font-weight:700}
  .periodo{color:<?= $fraca ?>; font-size:10.5px}
  .quem{text-align:right; font-size:9.5px; color:<?= $fraca ?>; line-height:1.6; white-space:nowrap; padding-left:16px}
</style></head><body>
  <div class="topo">
    <div>
      <div class="marca"><?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php endif; ?><b>inventour</b></div>
      <h1><?= e($titulo) ?></h1>
      <div class="periodo"><?= e($periodo) ?><?= $parteNome ? ' · ' . e($parteNome) : '' ?></div>
    </div>
    <div class="quem"><?= e((string) $empresa['nome_fantasia']) ?><br><?= e($assinado) ?></div>
  </div>
</body></html>
<?php
$cabecalhoHtml = (string) ob_get_clean();
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><title><?= e($titulo) ?></title>
<style>
  @page { margin: 0; }
  body{margin:0; background:#FFFFFF; color:<?= $tinta ?>; font-family:-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; font-size:11px; line-height:1.5}
  table{width:100%; border-collapse:collapse; font-size:10px}
  /* Os títulos das colunas também se repetem em cada página. */
  thead{display:table-header-group}
  tr{page-break-inside:avoid}
  thead th{text-align:left; font-size:8.5px; letter-spacing:.08em; text-transform:uppercase; color:<?= $fraca ?>; font-weight:600; border-bottom:1px solid <?= $traco ?>; padding:6px 6px 5px}
  tbody td{padding:5px 6px; border-bottom:1px solid #F1F4FA; vertical-align:top}
  tbody tr:nth-child(even) td{background:#FAFBFE}
  .num{text-align:right; white-space:nowrap}
  .nq{white-space:nowrap}
  tfoot td{padding:7px 6px; border-top:2px solid <?= $tinta ?>; font-weight:700}
  .rodape{margin-top:16px; padding-top:10px; border-top:1px solid <?= $traco ?>; font-size:9px; color:<?= $fraca ?>}
  .vazio{padding:26px 0; color:<?= $fraca ?>; text-align:center}
  .sub-celula{display:block; font-size:7.5pt; color:#5E6C8B; margin-top:1px}
</style></head>
<body>

  <?php if (!$dados['linhas']): ?>
    <p class="vazio">Nada no período.</p>
  <?php else: ?>
    <table>
      <thead><tr>
        <?php foreach ($dados['colunas'] as $c): ?><th class="<?= in_array($c['tipo'], $numericos, true) ? 'num' : '' ?>"><?= e($c['titulo']) ?></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
        <?php foreach ($dados['linhas'] as $linha): ?>
          <tr>
            <?php foreach ($dados['colunas'] as $c): ?>
              <td class="<?= in_array($c['tipo'], $numericos, true) ? 'num' : '' ?><?= in_array($c['tipo'], ['data', 'datahora'], true) ? ' nq' : '' ?>"><?= e($mostrar($c, $linha)) . $sub($c, $linha) ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($dados['totais']): ?>
        <tfoot><tr>
          <?php foreach ($dados['colunas'] as $i => $c): ?>
            <td class="<?= isset($dados['totais'][$c['chave']]) ? 'num' : '' ?>"><?=
              isset($dados['totais'][$c['chave']])
                ? e($c['tipo'] === 'moeda' ? moedaBr($dados['totais'][$c['chave']]) : number_format((float) $dados['totais'][$c['chave']], 0, ',', '.'))
                : ($i === 0 ? 'Total de ' . plural(count($dados['linhas']), 'linha', 'linhas') : '') ?></td>
          <?php endforeach; ?>
        </tr></tfoot>
      <?php endif; ?>
    </table>
  <?php endif; ?>

  <p class="rodape">Produto Softsul Software e Zapping Consultoria · Relatório gerado pelo InvenTour. Os números refletem o cadastro no momento da geração.</p>
</body></html>
