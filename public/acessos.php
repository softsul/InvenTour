<?php
/**
 * Acessos do servidor: os logs do Apache resumidos (app/Acessos.php), com
 * pessoas e robôs separados, as páginas mais vistas, o que os robôs de
 * busca e de IA leem e os erros.
 * Softsul Software
 *
 * Tela da equipe Softsul, no grupo Monitoramento do menu da plataforma (o
 * mesmo do EventCity). O filtro de destino só vale para o portal: o painel
 * não é de destino.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Acessos.php';
require_once __DIR__ . '/../app/Graficos.php';
require_once __DIR__ . '/../app/Lista.php';

Auth::exigirPermissao('acessos.ver');
if (!Auth::ehSoftsul()) {
    paginaErro(403, 'Esta tela é da equipe Softsul.', 'Os acessos do servidor valem para todos os destinos.');
}

$pdo = Database::conexao();
$dias = in_array((int) ($_GET['dias'] ?? 7), [1, 7, 30, 90], true) ? (int) ($_GET['dias'] ?? 7) : 7;
$sites = Lista::escolhas('site', array_keys(Acessos::SITES));
$destinos = array_column($pdo->query('SELECT id, nome_fantasia FROM empresas WHERE ativo = 1 ORDER BY nome_fantasia')->fetchAll(), 'nome_fantasia', 'id');
$empresas = array_map('intval', Lista::escolhas('destino', array_map('strval', array_keys($destinos))));
// Destino escolhido quer dizer portal: os outros sites não têm destino.
if ($empresas) {
    $sites = ['portal'];
}

$r = Acessos::resumo($dias, $sites, $empresas);
$quem = Acessos::quemPediu($dias, $sites, $empresas);
$cfg = require __DIR__ . '/../app/config.php';
$linkCom = function (array $mudar) use ($dias, $sites, $empresas): string {
    $q = array_filter(['dias' => $dias === 7 ? null : $dias, 'site' => $sites ?: null, 'destino' => $empresas ?: null]);
    foreach ($mudar as $k => $v) {
        $q[$k] = $v;
    }
    return '/acessos' . (($q = array_filter($q)) ? '?' . http_build_query($q) : '');
};
$n = fn (int $v): string => number_format($v, 0, ',', '.');
$tamanho = function (int $b): string {
    foreach (['GB' => 1 << 30, 'MB' => 1 << 20, 'kB' => 1 << 10] as $u => $f) {
        if ($b >= $f) {
            return number_format($b / $f, $b >= 10 * $f ? 0 : 1, ',', '.') . ' ' . $u;
        }
    }
    return $b . ' B';
};
$endereco = function (array $c) use ($cfg): string {
    $host = match ($c['site']) {
        'painel' => (string) parse_url((string) $cfg['app']['url'], PHP_URL_HOST),
        default => $c['dominio_proprio'] ?: ($c['slug'] ? $c['slug'] . '.' . ($cfg['app']['dominio_portal'] ?? 'inventour.com.br') : 'portal'),
    };
    return $host . $c['caminho'];
};
$tabela = function (string $tipo, string $vazio) use ($dias, $sites, $empresas, $endereco, $n): string {
    $linhas = Acessos::caminhos($tipo, $dias, $sites, $empresas);
    if (!$linhas) {
        return '<p class="nota-cartao">' . e($vazio) . '</p>';
    }
    $html = '<div class="rolagem"><table class="dados"><thead><tr><th>Endereço</th><th class="num">Pedidos</th></tr></thead><tbody>';
    foreach ($linhas as $l) {
        $txt = $endereco($l);
        $html .= '<tr><td class="elastica">' . e($txt) . '</td><td class="num dado">' . $n((int) $l['n']) . '</td></tr>';
    }
    return $html . '</tbody></table></div>';
};
$periodo = $dias === 1 ? 'hoje' : 'últimos ' . $dias . ' dias';

paginaInicio('Acessos do servidor', 'acessos');
?>
<div class="trilha"><span>Monitoramento</span><?= icone('seta') ?><span>Acessos do servidor</span></div>
<div class="cab-pag">
  <div><h1>Acessos do servidor</h1><p>O que o Apache registrou nos portais e no painel, lido a cada 10 minutos<?= $r['ultimo'] ? ' (última leitura ' . e(dataBr((string) $r['ultimo'], 'd/m H:i')) . ')' : '' ?>. Robô é contado pelo nome que ele diz ser.</p></div>
</div>

<form class="filtros filtros-relatorio" method="get" action="/acessos" data-fixos="dias">
  <input type="hidden" name="dias" value="<?= $dias ?>">
  <?= multiEscolhaFiltro(['campo' => 'site', 'icone' => 'rede', 'rotulo' => 'Site', 'vazio' => 'Todos os sites', 'plural' => 'sites',
      'opcoes' => Acessos::SITES, 'escolhidos' => $empresas ? [] : $sites, 'enviar' => true]) ?>
  <?= multiEscolhaFiltro(['campo' => 'destino', 'icone' => 'destino', 'rotulo' => 'Destino', 'vazio' => 'Todos os destinos', 'plural' => 'destinos',
      'opcoes' => $destinos, 'escolhidos' => array_map('strval', $empresas), 'enviar' => true]) ?>
  <div class="pilulas-filtro">
    <?php foreach ([1 => 'Hoje', 7 => '7 dias', 30 => '30 dias', 90 => '90 dias'] as $d => $rot): ?>
      <a class="pf<?= $dias === $d ? ' ativa' : '' ?>" href="<?= e($linkCom(['dias' => $d === 7 ? null : $d])) ?>"><?= $rot ?></a>
    <?php endforeach; ?>
  </div>
</form>

<div class="metricas">
  <div class="metrica destaque"><span class="rot">Visitantes</span><span class="val"><?= $n($r['visitantes']) ?></span><span class="nota">pessoas diferentes por dia, somadas</span></div>
  <div class="metrica"><span class="rot">Páginas vistas</span><span class="val"><?= $n($r['paginas']) ?></span><span class="nota">por pessoas, sem arquivo nem API</span></div>
  <div class="metrica"><span class="rot">Robôs</span><span class="val"><?= $r['parte_robos'] ?>%</span><span class="nota"><?= $n($r['robos']) ?> de <?= $n($r['pedidos']) ?> pedidos</span></div>
  <div class="metrica"><span class="rot">Erros do servidor</span><span class="val"><?= $n($r['e5']) ?></span><span class="nota">respostas 5xx</span></div>
  <div class="metrica"><span class="rot">Barrados</span><span class="val"><?= $n($r['e429']) ?></span><span class="nota">pelo limite por IP (429)</span></div>
  <div class="metrica"><span class="rot">Tráfego</span><span class="val"><?= e($tamanho($r['bytes'])) ?></span><span class="nota">enviado, <?= $n($r['e404']) ?> não encontrados</span></div>
</div>

<div class="painel-colunas painel-graficos">
  <div class="cartao">
    <div class="cab-cartao"><h2>Páginas por dia</h2><span class="nota"><?= e($dias === 1 ? 'últimos 7 dias' : $periodo) ?></span></div>
    <?= Graficos::colunas(Acessos::porDia(max(7, $dias), $sites, $empresas), [
        ['chave' => 'pessoas', 'nome' => 'Pessoas', 'cor' => Acessos::GRUPOS['pessoa']['cor']],
        ['chave' => 'robos', 'nome' => 'Robôs', 'cor' => Acessos::GRUPOS['outro']['cor']],
    ], 'Páginas vistas por dia, pessoas e robôs') ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Hora do dia</h2><span class="nota">páginas vistas por pessoas, <?= e($periodo) ?></span></div>
    <?= Graficos::colunas(Acessos::porHora($dias, $sites, $empresas), [['chave' => 'pessoas', 'nome' => 'Páginas vistas', 'cor' => Acessos::GRUPOS['pessoa']['cor']]],
        'Páginas vistas por pessoas em cada hora do dia') ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Quem pediu</h2><span class="nota">todos os pedidos, <?= e($periodo) ?></span></div>
    <?= Graficos::faixa($quem['faixa']) ?>
    <?php if ($quem['robos']): ?>
      <h3 class="sub-cartao">Robôs mais ativos</h3>
      <?= Graficos::barras($quem['robos']) ?>
    <?php endif; ?>
  </div>

  <?php if (!$sites || in_array('portal', $sites, true)): ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Portais por destino</h2><span class="nota">páginas vistas por pessoas</span></div>
    <?= ($b = Acessos::porDestino($dias, $empresas)) ? Graficos::barras($b) : '<p class="nota-cartao">Nenhuma página vista no período.</p>' ?>
    <p class="nota-cartao">Endereço que não é de nenhum destino (o portal genérico, varredura por IP) aparece sem destino.</p>
  </div>
  <?php endif; ?>

  <div class="cartao">
    <div class="cab-cartao"><h2>Mais vistas</h2><span class="nota">por pessoas</span></div>
    <?= $tabela('pessoas', 'Nenhuma página vista no período.') ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>O que os robôs leem</h2><span class="nota">busca e IA</span></div>
    <?= $tabela('robos', 'Nenhum robô de busca ou de IA no período.') ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Erros do servidor</h2><span class="nota">respostas 5xx</span></div>
    <?= $tabela('5xx', 'Nenhum erro do servidor no período.') ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Não encontrados</h2><span class="nota">404, quase sempre varredura</span></div>
    <?= $tabela('404', 'Nenhum endereço inexistente pedido no período.') ?>
  </div>
</div>
<?php paginaFim();
