<?php
/**
 * Monitoramento, Visitas: o Google Analytics dos portais, lido na hora pela Data
 * API (app/Analytics.php, cache de uma hora), como no EventCity.
 * Softsul Software
 *
 * Dentro de um destino, só o portal dele: o filtro é o host do portal do
 * destino aberto, sem escolha. Na visão da plataforma, todos, com o filtro
 * de destino e o comparativo entre eles. Só conta quem aceitou os cookies;
 * cada portal pede o consentimento do visitante.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Analytics.php';
require_once __DIR__ . '/../app/Graficos.php';
require_once __DIR__ . '/../app/Lista.php';

Auth::exigirPermissao('visitas.ver');
$pdo = Database::conexao();
$empresa = Empresa::id();
$dias = in_array((int) ($_GET['dias'] ?? 28), [7, 28, 90], true) ? (int) ($_GET['dias'] ?? 28) : 28;
$destinos = [];
if ($empresa) {
    $empresas = [(int) $empresa];
} else {
    if (!Auth::ehSoftsul()) {
        redirecionar('/painel');
    }
    $destinos = array_column($pdo->query('SELECT id, nome_fantasia FROM empresas WHERE ativo = 1 ORDER BY nome_fantasia')->fetchAll(), 'nome_fantasia', 'id');
    $empresas = array_map('intval', Lista::escolhas('destino', array_map('strval', array_keys($destinos))));
}

$erro = '';
$v = null;
$vivo = null;
if (Analytics::configurado()) {
    try {
        $v = Analytics::visitas($dias, $empresas);
        if (!$empresa && !$empresas) {
            $vivo = Analytics::aoVivo();
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$cor = '#E37400';
$barras = fn (array $itens) => Graficos::barras(array_map(fn ($i) => $i + ['cor' => $cor], $itens));
$deMapa = fn (array $mapa) => array_map(fn ($k, $n) => ['rotulo' => (string) $k, 'valor' => (int) $n, 'cor' => $cor], array_keys($mapa), $mapa);
$n = fn (int $x): string => number_format($x, 0, ',', '.');
$linkCom = function (array $mudar) use ($dias, $empresas, $empresa): string {
    $q = array_filter(['dias' => $dias === 28 ? null : $dias, 'destino' => !$empresa && $empresas ? $empresas : null]);
    foreach ($mudar as $k => $val) {
        $q[$k] = $val;
    }
    return '/visitas' . (($q = array_filter($q)) ? '?' . http_build_query($q) : '');
};

paginaInicio('Visitas', 'visitas');
?>
<div class="trilha"><span>Monitoramento</span><?= icone('seta') ?><span>Visitas</span></div>
<div class="cab-pag">
  <div><h1>Visitas<?= $empresa ? ' ao portal' : ' aos portais' ?></h1><p>Pelo Google Analytics: de onde vêm, de que cidade, o que procuram e o que fazem na ficha de cada lugar. Conta só quem aceitou os cookies; números do dia chegam com algumas horas de atraso.</p></div>
</div>

<form class="filtros filtros-relatorio" method="get" action="/visitas" data-fixos="dias">
  <input type="hidden" name="dias" value="<?= $dias ?>">
  <?php if (!$empresa): ?>
    <?= multiEscolhaFiltro(['campo' => 'destino', 'icone' => 'destino', 'rotulo' => 'Destino', 'vazio' => 'Todos os destinos', 'plural' => 'destinos',
        'opcoes' => $destinos, 'escolhidos' => array_map('strval', $empresas), 'enviar' => true]) ?>
  <?php endif; ?>
  <div class="pilulas-filtro">
    <?php foreach ([7 => '7 dias', 28 => '28 dias', 90 => '90 dias'] as $d => $rot): ?>
      <a class="pf<?= $dias === $d ? ' ativa' : '' ?>" href="<?= e($linkCom(['dias' => $d === 28 ? null : $d])) ?>"><?= $rot ?></a>
    <?php endforeach; ?>
  </div>
</form>

<?php if (!Analytics::configurado()): ?>
  <div class="vazio"><?= icone('olho') ?><p><strong>O Google Analytics ainda não está ligado.</strong></p><p>A equipe Softsul liga em Configurações, Google Analytics.</p></div>
<?php elseif ($erro !== ''): ?>
  <div class="vazio"><?= icone('alerta') ?><p><strong>O Google não respondeu agora.</strong></p><p><?= e($erro) ?></p></div>
<?php else: ?>

<div class="metricas">
  <div class="metrica destaque"><span class="rot">Visitantes</span><span class="val"><?= $n($v['usuarios']) ?></span><span class="nota"><?= $v['novos'] ?>% pela primeira vez</span></div>
  <div class="metrica"><span class="rot">De fora da cidade</span><span class="val"><?= $v['de_fora'] !== null ? $v['de_fora'] . '%' : '-' ?></span><span class="nota">pela cidade do acesso</span></div>
  <div class="metrica"><span class="rot">Sessões</span><span class="val"><?= $n($v['sessoes']) ?></span><span class="nota"><?= $v['engajamento'] ?>% engajadas</span></div>
  <div class="metrica"><span class="rot">Tempo</span><span class="val"><?= $v['tempo'] >= 60 ? intdiv($v['tempo'], 60) . ' min' : $v['tempo'] . ' s' ?></span><span class="nota">de atenção por visitante</span></div>
  <div class="metrica"><span class="rot">Páginas</span><span class="val"><?= number_format((float) $v['paginas_sessao'], 1, ',', '') ?></span><span class="nota">por sessão</span></div>
  <?php if ($vivo !== null): ?>
  <div class="metrica"><span class="rot">Agora</span><span class="val"><?= $n($vivo['total']) ?></span><span class="nota">nos últimos 30 minutos</span></div>
  <?php endif; ?>
</div>

<div class="painel-colunas painel-graficos">
  <div class="cartao">
    <div class="cab-cartao"><h2>De onde é o visitante</h2><span class="nota">visitantes por cidade</span></div>
    <?= $v['cidades'] ? Graficos::barras($deMapa($v['cidades'])) : '<p class="nota-cartao">Sem visitas no período.</p>' ?>
    <?php if ($v['estados']): ?>
      <h3 class="sub-cartao">Por estado</h3>
      <?= Graficos::barras($deMapa($v['estados'])) ?>
    <?php endif; ?>
    <p class="nota-cartao">A cidade vem da conexão de internet: é aproximada, e o celular às vezes aparece na cidade da operadora.</p>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Sessões por dia</h2><span class="nota">últimos <?= $dias ?> dias</span></div>
    <?= Graficos::colunas($v['serie'], [['chave' => 'sessoes', 'nome' => 'Sessões', 'cor' => $cor]], 'Sessões por dia') ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>De onde vêm</h2><span class="nota">sessões por canal</span></div>
    <?= $v['canais'] ? $barras($v['canais']) : '<p class="nota-cartao">Sem visitas no período.</p>' ?>
    <?php if ($v['origens']): ?>
      <h3 class="sub-cartao">Sites e aplicativos</h3>
      <?= $barras($v['origens']) ?>
    <?php endif; ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>O que fazem na ficha</h2><span class="nota">cliques na ficha do lugar e nos roteiros</span></div>
    <?= $barras($v['acoes']) ?>
    <?php if ($v['acao_por_canal']): ?>
      <h3 class="sub-cartao">Cliques por origem da visita</h3>
      <?= Graficos::barras($deMapa($v['acao_por_canal'])) ?>
    <?php endif; ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>O que procuram</h2><span class="nota">páginas mais vistas</span></div>
    <?= $v['paginas'] ? $barras(array_map(fn ($p) => ['rotulo' => preg_replace('/ · .*$/u', '', $p['rotulo']), 'valor' => $p['valor']], $v['paginas'])) : '<p class="nota-cartao">Sem visitas no período.</p>' ?>
    <?php if ($v['pilares']): ?>
      <h3 class="sub-cartao">Por parte do portal</h3>
      <?= $barras($v['pilares']) ?>
    <?php endif; ?>
    <?php if ($v['buscas']): ?>
      <h3 class="sub-cartao">Buscas no portal</h3>
      <?= $barras($v['buscas']) ?>
    <?php endif; ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Campanhas</h2><span class="nota">QR dos cartazes e links compartilhados</span></div>
    <?= $v['campanhas'] ? $barras($v['campanhas']) : '<p class="nota-cartao">Nenhuma visita por campanha no período. Leitura de QR dos pontos e do caderno e link com campanha aparecem aqui.</p>' ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Quando</h2><span class="nota">sessões por dia da semana e hora</span></div>
    <?= Graficos::colunas(array_map(fn ($i, $s) => ['rotulo' => ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][$i], 'sessoes' => $s], array_keys($v['dias_semana']), $v['dias_semana']),
        [['chave' => 'sessoes', 'nome' => 'Sessões', 'cor' => $cor]], 'Sessões por dia da semana') ?>
    <?= Graficos::colunas(array_map(fn ($h, $s) => ['rotulo' => $h % 3 === 0 ? (string) $h : '', 'titulo' => sprintf('%02dh', $h), 'sessoes' => $s], array_keys($v['horas']), $v['horas']),
        [['chave' => 'sessoes', 'nome' => 'Sessões', 'cor' => $cor]], 'Sessões por hora do dia') ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Aparelho</h2><span class="nota">visitantes</span></div>
    <?= Graficos::faixa(array_map(fn ($a, $c) => $a + ['cor' => $c], $v['aparelhos'], array_slice(['#E37400', '#1C3E73', '#8A94A6', '#2E8B3E', '#7B4FC9'], 0, count($v['aparelhos'])))) ?>
  </div>

  <?php if (!$empresa && count($empresas) !== 1 && $v['por_destino']): ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Por destino</h2><span class="nota">sessões</span></div>
    <?= $barras($v['por_destino']) ?>
  </div>
  <?php endif; ?>

  <?php if ($vivo && $vivo['paginas']): ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Agora</h2><span class="nota">visitantes nos últimos 30 minutos, todos os portais</span></div>
    <?= $barras(array_map(fn ($p) => ['rotulo' => preg_replace('/ · .*$/u', '', $p['rotulo']), 'valor' => $p['valor']], $vivo['paginas'])) ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php paginaFim();
