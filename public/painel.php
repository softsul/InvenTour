<?php
/**
 * Início: o painel do destino, ou a visão da plataforma para a equipe Softsul.
 * Softsul Software
 *
 * Resumos e gráficos do que já está implantado (Painel.php e Graficos.php).
 * Na fundação do InvenTour: destinos, equipe, atividade e a saúde da fila e
 * do e-mail. Cada módulo do inventário (fichas, coleta, homologação, caderno,
 * phygital) entra aqui com o resumo dele quando ficar pronto. A auditoria
 * mora em Configurações.
 *
 * Regra do Luiz (23/10/2026): cada funcionalidade nova ganha o seu resumo
 * neste Início. Reforçada em 21/11/2026: a visão geral cresce a cada
 * módulo (Experiências, O destino e a coleta por dia entraram assim).
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Painel.php';
require_once __DIR__ . '/../app/Graficos.php';
require_once __DIR__ . '/../app/SesAvisos.php';
require_once __DIR__ . '/../app/Email.php';
require_once __DIR__ . '/../app/Bing.php';
require_once __DIR__ . '/../app/Analytics.php';
require_once __DIR__ . '/../app/Acessos.php';

/**
 * Os quadros da busca orgânica e das visitas, como no Início do EventCity:
 * Google (Search Console), Bing (Webmaster) e Google Analytics. Com
 * $empresaId, o portal do destino; com null, todos os portais.
 */
function quadrosDeBusca(?int $empresaId): string
{
    ob_start();
    if (SearchConsole::configurado()): $g = SearchConsole::resumo($empresaId); ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>No Google</h2><span class="nota"><?= $g['ultimo'] ? 'últimos 30 dias, até ' . e(dataBr((string) $g['ultimo'], 'd/m')) : 'aguardando a primeira leitura' ?></span></div>
    <div class="metricas">
      <div class="metrica"><span class="rot">Cliques</span><span class="val"><?= milhar($g['cliques']) ?></span><span class="nota">vindos da busca</span></div>
      <div class="metrica"><span class="rot">Impressões</span><span class="val"><?= milhar($g['impressoes']) ?></span><span class="nota">vezes que apareceu</span></div>
      <div class="metrica"><span class="rot">Posição média</span><span class="val"><?= $g['posicao'] !== null ? number_format((float) $g['posicao'], 1, ',', '') : '-' ?></span><span class="nota">no resultado</span></div>
    </div>
    <?php if ($g['termos']): ?>
      <h3 class="sub-cartao">O que buscaram</h3>
      <?= Graficos::barras(array_map(fn ($t) => ['rotulo' => $t['chave'], 'valor' => (int) $t['c'] ?: (int) $t['i'], 'cor' => '#1C3E73'], $g['termos'])) ?>
      <p class="nota-cartao">Cliques por termo; sem clique ainda, as impressões.</p>
    <?php else: ?>
      <p class="nota-cartao">Os termos aparecem quando o Google começar a mostrar o portal. Leva alguns dias depois do sitemap, e o portal só mostra lugar homologado.</p>
    <?php endif; ?>
  </div>
    <?php endif;
    if (Bing::configurado()): $b = Bing::resumo($empresaId); ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>No Bing</h2><span class="nota"><?= $b['ultimo'] ? 'últimos 30 dias, até ' . e(dataBr((string) $b['ultimo'], 'd/m')) : ($b['sites'] && $b['verificados'] < $b['sites'] ? 'aguardando a verificação do portal' : 'aguardando a primeira leitura') ?></span></div>
    <div class="metricas">
      <div class="metrica"><span class="rot">Cliques</span><span class="val"><?= milhar($b['cliques']) ?></span><span class="nota">vindos da busca</span></div>
      <div class="metrica"><span class="rot">Impressões</span><span class="val"><?= milhar($b['impressoes']) ?></span><span class="nota">vezes que apareceu</span></div>
      <div class="metrica"><span class="rot">Posição média</span><span class="val"><?= $b['posicao'] !== null ? number_format((float) $b['posicao'], 1, ',', '') : '-' ?></span><span class="nota">no resultado</span></div>
    </div>
    <?php if ($b['termos']): ?>
      <h3 class="sub-cartao">O que buscaram</h3>
      <?= Graficos::barras(array_map(fn ($t) => ['rotulo' => $t['chave'], 'valor' => (int) $t['c'] ?: (int) $t['i'], 'cor' => '#00809D'], $b['termos'])) ?>
      <p class="nota-cartao">Cliques por termo; sem clique ainda, as impressões. Vale também para Yahoo, DuckDuckGo e Ecosia.</p>
    <?php else: ?>
      <p class="nota-cartao">Os termos aparecem quando o Bing começar a mostrar o portal. O Bing também abastece Yahoo, DuckDuckGo, Ecosia e Copilot.</p>
    <?php endif; ?>
  </div>
    <?php endif;
    if (Analytics::configurado()): $ga = Analytics::resumo($empresaId); ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Visitas no Analytics</h2><?= Auth::pode('visitas.ver') ? '<a href="/visitas">Ver as visitas</a>' : '<span class="nota">28 dias</span>' ?></div>
    <div class="metricas">
      <div class="metrica"><span class="rot">Visitantes</span><span class="val"><?= milhar($ga['usuarios']) ?></span><span class="nota">que aceitaram os cookies</span></div>
      <div class="metrica"><span class="rot">Sessões</span><span class="val"><?= milhar($ga['sessoes']) ?></span><span class="nota"><?= $ga['engajamento'] ?>% engajadas</span></div>
      <div class="metrica"><span class="rot">Tempo</span><span class="val"><?= $ga['tempo'] >= 60 ? intdiv($ga['tempo'], 60) . ' min' : $ga['tempo'] . ' s' ?></span><span class="nota">por visitante</span></div>
    </div>
    <?php if ($ga['canais']): ?>
      <h3 class="sub-cartao">De onde vêm</h3>
      <?= Graficos::barras(array_map(fn ($c) => ['rotulo' => Analytics::canal((string) $c['chave']), 'valor' => (int) $c['s'], 'cor' => '#E37400'], $ga['canais'])) ?>
    <?php endif; ?>
    <?php if ($ga['cidades']): ?>
      <h3 class="sub-cartao">De que cidade</h3>
      <?= Graficos::barras(array_map(fn ($c) => ['rotulo' => (string) $c['chave'], 'valor' => (int) $c['s'], 'cor' => '#E37400'], $ga['cidades'])) ?>
    <?php endif; ?>
    <p class="nota-cartao"><?= $ga['ultimo'] ? 'Últimos 28 dias, até ' . e(dataBr((string) $ga['ultimo'], 'd/m')) . '.' : 'Aguardando a primeira leitura (todo dia às 7h30).' ?> Só conta quem aceitou os cookies.</p>
  </div>
    <?php endif;
    return (string) ob_get_clean();
}

$u = Auth::exigirPermissao('painel.ver');
$empresa = Empresa::atual();
$eid = (int) Empresa::id();
$pdo = Database::conexao();
$hora = (int) date('G');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
$dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$dominioPortal = (string) ((require __DIR__ . '/../app/config.php')['app']['dominio_portal'] ?? 'inventour.com.br');
$portalDe = fn (array $d): string => 'https://' . ($d['dominio_proprio'] ?: $d['slug'] . '.' . $dominioPortal);

$metricas = [];
if ($empresa) {
    if (Auth::pode('inventario.ver')) {
        require_once __DIR__ . '/../app/Inventario.php';
        $inv = Inventario::resumo($eid);
        $homologados = $inv['situacao']['homologada'] + $inv['situacao']['desatualizada'];
        $metricas[] = ['Inventário', $inv['total'] - $inv['situacao']['arquivada'], plural($homologados, 'homologado', 'homologados') . ', '
            . plural($inv['situacao']['em_conferencia'], 'em conferência', 'em conferência'), '/inventario'];
        $metricas[] = ['Leitos homologados', $inv['leitos'], plural($inv['uh'], 'unidade habitacional', 'unidades habitacionais'), '/inventario?ficha=' . (int) $pdo->query('SELECT id FROM inventario_fichas WHERE ordem = 20')->fetchColumn()];
    }
    // Etapa 3: a esteira. Quem coordena vê a fila de conferência; quem
    // coleta, o que tem para levantar e o que voltou da conferência.
    require_once __DIR__ . '/../app/Esteira.php';
    if (Auth::pode('homologacao.ver')) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM inventario_itens WHERE empresa_id = ? AND situacao = 'homologada' AND homologado_em >= NOW() - INTERVAL 30 DAY");
        $q->execute([$eid]);
        $metricas[] = ['Fila de conferência', Esteira::naFila($eid), plural((int) $q->fetchColumn(), 'homologado', 'homologados') . ' nos últimos 30 dias', '/homologacao'];
    }
    if (Auth::pode('coleta.ver')) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM campo_aparelhos WHERE empresa_id = ? AND token_hash IS NOT NULL AND revogado_em IS NULL');
        $q->execute([$eid]);
        $metricas[] = ['Para coletar', Esteira::paraColetar($eid), plural(Esteira::devolvidos($eid), 'devolvido', 'devolvidos') . ' pela conferência · '
            . plural((int) $q->fetchColumn(), 'aparelho com o app', 'aparelhos com o app') . (Esteira::escopo() !== null ? ' · no seu escopo' : ''), '/coleta'];
    }
    if (Auth::pode('experiencias.ver')) {
        $q = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(situacao = 'aguardando'), 0), COALESCE(SUM(situacao = 'aprovada'), 0)
                            FROM inventario_experiencias WHERE empresa_id = ?");
        $q->execute([$eid]);
        [$totalX, $aguardandoX, $aprovadasX] = array_map('intval', $q->fetch(PDO::FETCH_NUM));
        $metricas[] = ['Experiências', $totalX, plural($aprovadasX, 'aprovada', 'aprovadas') . ' · ' . milhar($aguardandoX) . ' aguardando curadoria', '/experiencias'];
    }
    // Etapa 6: o trade. A equipe vê quem responde pelos lugares e o que
    // espera por ela; o responsável, os lugares dele.
    if (Auth::pode('trade.ver')) {
        $q = $pdo->prepare("SELECT (SELECT COUNT(*) FROM inventario_reivindicacoes WHERE empresa_id = ? AND situacao = 'aprovada'),
                                   (SELECT COUNT(*) FROM inventario_reivindicacoes WHERE empresa_id = ? AND situacao = 'pendente'),
                                   (SELECT COUNT(*) FROM inventario_revisoes WHERE empresa_id = ? AND situacao = 'aguardando')");
        $q->execute([$eid, $eid, $eid]);
        [$respT, $pedT, $revT] = array_map('intval', $q->fetch(PDO::FETCH_NUM));
        $metricas[] = ['Trade turístico', $respT, plural($pedT, 'pedido', 'pedidos') . ' e ' . plural($revT, 'revisão', 'revisões') . ' esperando', '/trade'];
    }
    if (Auth::pode('meus-lugares.ver')) {
        require_once __DIR__ . '/../app/Trade.php';
        $meus = Trade::meusLugares((int) $u['id']);
        $esperaM = count(array_filter($meus, fn ($l) => in_array($l['revisao'], ['aguardando', 'devolvida'], true)));
        $metricas[] = ['Meus lugares', count($meus), $esperaM ? plural($esperaM, 'atualização em conferência', 'atualizações em conferência') : 'tudo em dia no portal', '/meus-lugares'];
    }
    if (Auth::pode('destino-textos.ver')) {
        $q = $pdo->prepare("SELECT (SELECT COUNT(*) FROM destino_narrativas WHERE empresa_id = ? AND conteudo_html <> ''),
                                   (SELECT COUNT(*) FROM destino_linha_tempo WHERE empresa_id = ?)");
        $q->execute([$eid, $eid]);
        [$textosD, $marcosD] = array_map('intval', $q->fetch(PDO::FETCH_NUM));
        $metricas[] = ['O destino', $textosD, 'de 5 textos · ' . plural($marcosD, 'marco', 'marcos') . ' na linha do tempo', '/destino-textos'];
    }
    if (Auth::pode('caderno.ver')) {
        // Etapa 4: o caderno oficial, a última versão pronta e as leituras dos QR.
        $q = $pdo->prepare("SELECT versao, paginas, previa, (SELECT COALESCE(SUM(leituras), 0) FROM caderno_leituras WHERE empresa_id = ?)
                            FROM cadernos WHERE empresa_id = ? AND situacao = 'pronto' ORDER BY id DESC LIMIT 1");
        $q->execute([$eid, $eid]);
        $ultimo = $q->fetch(PDO::FETCH_NUM);
        $metricas[] = ['Caderno oficial', $ultimo ? (int) $ultimo[1] : 0, $ultimo
            ? 'páginas na versão ' . (int) $ultimo[0] . ($ultimo[2] ? ' (prévia)' : '') . ' · ' . plural((int) $ultimo[3], 'leitura do QR', 'leituras do QR')
            : 'nenhuma versão gerada ainda', '/caderno'];
    }
    // O município no Mapa do Turismo Brasileiro, como no EventCity: a região
    // turística e a categoria. Fora do Mapa, a região dos vizinhos e o aviso
    // abaixo das métricas.
    $foraDoMapa = null;
    $regiaoVizinha = null;
    if (!empty($empresa['municipio_id'])) {
        require_once __DIR__ . '/../app/MapaTurismo.php';
        if ($noMapa = MapaTurismo::doMunicipio((int) $empresa['municipio_id'])) {
            $metricas[] = ['Região turística', (int) $noMapa['na_regiao'], 'municípios em ' . $noMapa['regiao'] . ' · '
                . mb_strtolower(MapaTurismo::CATEGORIAS[(int) $noMapa['categoria']] ?? ''), Auth::pode('mapa-turismo.ver') ? '/mapa-turismo' : null];
        } elseif ($foraDoMapa = MapaTurismo::ano()) {
            if ($regiaoVizinha = MapaTurismo::regiaoDosVizinhos((int) $empresa['municipio_id'])) {
                $metricas[] = ['Região turística', (int) $regiaoVizinha['na_regiao'], 'municípios em ' . $regiaoVizinha['nome']
                    . ' · a dos vizinhos', Auth::pode('mapa-turismo.ver') ? '/mapa-turismo' : null];
            }
        }
    }
    // Busca orgânica (Search Console e Bing), como no EventCity: 30 dias.
    if (Auth::pode('inventario.ver')) {
        require_once __DIR__ . '/../app/Bing.php';
        if (SearchConsole::configurado()) {
            $g = SearchConsole::resumo($eid);
            $metricas[] = ['Busca no Google', $g['cliques'], plural($g['impressoes'], 'impressão', 'impressões') . ' em 30 dias'
                . ($g['posicao'] !== null ? ' · posição média ' . str_replace('.', ',', (string) $g['posicao']) : ''), Auth::ehSoftsul() ? '/configuracoes/google' : null];
        }
        if (Bing::configurado()) {
            $b = Bing::resumo($eid);
            $metricas[] = ['Busca no Bing', $b['cliques'], plural($b['impressoes'], 'impressão', 'impressões') . ' em 30 dias'
                . ($b['sites'] && !$b['verificados'] ? ' · portal aguardando a verificação' : ''), Auth::ehSoftsul() ? '/configuracoes/bing' : null];
        }
    }
    if (Auth::pode('visitas.ver')) {
        require_once __DIR__ . '/../app/Analytics.php';
        if (Analytics::configurado()) {
            $ga = Analytics::resumo($eid);
            $metricas[] = ['Visitas no portal', $ga['usuarios'], 'visitantes em 28 dias · ' . plural($ga['sessoes'], 'sessão', 'sessões') . ', ' . $ga['engajamento'] . '% engajadas', '/visitas'];
        }
    }
    $metricas[] = ['Equipe', Painel::equipe($eid), 'pessoas com acesso ao destino', Auth::pode('usuarios.ver') ? '/configuracoes/usuarios' : null];
} elseif ($u['softsul']) {
    $destinos = Painel::destinosPlataforma();
    $fila = Painel::filaPlataforma();
    [$devolvidos, $reclamacoes, $bloqueados] = SesAvisos::resumo();
    $licenciados = count(array_filter($destinos, fn ($d) => $d['modo_operacao'] === 'ativo'));
    $metricas[] = ['Destinos', count($destinos), plural($licenciados, 'licenciado', 'licenciados') . ', ' . plural(count($destinos) - $licenciados, 'em projeto ou avaliação', 'em projeto ou avaliação'), '/destinos'];
    // O catálogo do MTur que os formulários do inventário usam.
    [$totalFichas, $totalTipos] = Database::conexao()->query('SELECT (SELECT COUNT(*) FROM inventario_fichas WHERE ativo = 1), (SELECT COUNT(*) FROM inventario_tipos WHERE ativo = 1)')->fetch(PDO::FETCH_NUM);
    $metricas[] = ['Fichas do MTur', (int) $totalFichas, plural((int) $totalTipos, 'tipo e subtipo', 'tipos e subtipos') . ' no catálogo', Auth::pode('fichas.ver') ? '/fichas' : null];
    if (SearchConsole::configurado()) {
        $gP = SearchConsole::resumo(null);
        $metricas[] = ['Busca no Google', $gP['cliques'], plural($gP['impressoes'], 'impressão', 'impressões') . ' em 30 dias, todos os portais', '/configuracoes/google'];
    }
    if (Bing::configurado()) {
        $bP = Bing::resumo(null);
        $metricas[] = ['Busca no Bing', $bP['cliques'], plural($bP['impressoes'], 'impressão', 'impressões') . ' em 30 dias · ' . plural($bP['verificados'], 'portal verificado', 'portais verificados'), '/configuracoes/bing'];
    }
    if (Analytics::configurado()) {
        $ga = Analytics::resumo(null);
        $metricas[] = ['Visitas aos portais', $ga['usuarios'], 'visitantes em 28 dias · ' . plural($ga['sessoes'], 'sessão', 'sessões'), '/visitas'];
    }
    $metricas[] = ['E-mails devolvidos', $devolvidos, plural($reclamacoes, 'reclamação', 'reclamações') . ' em 30 dias · ' . plural($bloqueados, 'bloqueado', 'bloqueados'), '/configuracoes/email'];
}
if (Auth::pode('cadastur.ver')) {
    require_once __DIR__ . '/../app/Cadastur.php';
    $baseCadastur = Cadastur::base();
    $metricas[] = ['Cadastur', Cadastur::totais()[0],
        'prestadores no país' . ($baseCadastur ? ', ' . mb_strtolower((string) $baseCadastur['recurso_nome']) : ''), '/cadastur'];
}
if (Auth::pode('mapa-turismo.ver')) {
    require_once __DIR__ . '/../app/MapaTurismo.php';
    $mapaTurismo = MapaTurismo::totais();
    $metricas[] = ['Mapa do Turismo' . ($mapaTurismo['ano'] ? ' ' . $mapaTurismo['ano'] : ''), $mapaTurismo['municipios'],
        'municípios em ' . plural($mapaTurismo['regioes'], 'região turística', 'regiões turísticas'), '/mapa-turismo'];
}

paginaInicio('Início', 'painel');
?>
<div class="cab-pag">
  <div>
    <h1><?= e($saudacao) ?>, <?= e(comoChamar($u)) ?></h1>
    <p><?= e(($empresa ? $empresa['nome_fantasia'] : 'Visão da plataforma') . ' · ' . $dias[(int) date('w')] . ', ' . date('d/m/Y')) ?></p>
  </div>
  <?php if ($empresa && !empty($empresa['slug'])): ?>
    <div class="acoes">
      <?php if (Auth::pode('inventario.ver')): require_once __DIR__ . '/../app/Portal.php'; ?>
        <a class="botao claro" href="<?= e($portalDe($empresa) . '/?previa=' . Portal::tokenDePrevia($eid)) ?>" target="_blank" rel="noopener"
           title="Abre o portal com o que ainda não foi homologado, por 2 horas. É só para a equipe: não compartilhe o link."><?= icone('olho') ?>Prévia do portal</a>
      <?php endif; ?>
      <a class="botao claro" href="<?= e($portalDe($empresa)) ?>" target="_blank" rel="noopener"><?= icone('rede') ?>Ver o portal</a></div>
  <?php endif; ?>
</div>

<?php if ($metricas): ?>
<div class="metricas">
  <?php foreach ($metricas as $i => [$rotulo, $valor, $nota, $link]): ?>
    <?php if ($link): ?><a class="metrica metrica-link<?= $i === 0 ? ' destaque' : '' ?>" href="<?= e($link) ?>"><?php else: ?><div class="metrica<?= $i === 0 ? ' destaque' : '' ?>"><?php endif; ?>
      <span class="rot"><?= e($rotulo) ?></span><span class="val"><?= milhar((int) $valor) ?></span><span class="nota"><?= e($nota) ?></span>
    <?= $link ? '</a>' : '</div>' ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($empresa): ?>
<?php if (isset($inv) && $inv['total'] === 0): ?>
<p class="aviso info"><?= icone('mapa') ?><span><strong>Para começar o inventário de <?= e($empresa['nome_fantasia']) ?>:</strong>
faça o <a href="/pre-cadastro">pré-cadastro pelo Cadastur</a>, que traz hotéis, restaurantes e agências que a base oficial já conhece, e escreva os textos em <a href="/destino-textos">O destino</a>. Os atrativos e a infraestrutura de apoio entram pelo Novo item do Inventário.</span></p>
<?php elseif (!empty($foraDoMapa)): ?>
<p class="aviso atencao"><?= icone('mapa') ?><span><strong><?= e($empresa['cidade'] ?? '' ?: $empresa['nome_fantasia']) ?> não está no Mapa do Turismo Brasileiro <?= (int) $foraDoMapa ?>.</strong>
O Mapa é a referência do Ministério do Turismo para regiões turísticas e para os programas dele. A inclusão é pedida pela prefeitura ao órgão estadual de turismo, no período de atualização do Mapa.<?php if ($regiaoVizinha): ?>
Os municípios vizinhos estão na região turística <strong><?= e($regiaoVizinha['nome']) ?></strong>.<?php endif; ?></span></p>
<?php endif; ?>
<div class="painel-colunas painel-graficos">
  <?php if (isset($inv) && $inv['total'] > 0): ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Esteira do inventário</h2><a href="/inventario">Ver o inventário</a></div>
    <?= Graficos::faixa([
        ['rotulo' => 'Pré-cadastro', 'valor' => $inv['situacao']['pre_cadastro'], 'cor' => '#9AA3B2', 'link' => '/inventario?situacao=pre_cadastro'],
        ['rotulo' => 'Em campo', 'valor' => $inv['situacao']['em_campo'], 'cor' => '#63B6E5', 'link' => '/inventario?situacao=em_campo'],
        ['rotulo' => 'Em conferência', 'valor' => $inv['situacao']['em_conferencia'], 'cor' => '#F3B137', 'link' => '/inventario?situacao=em_conferencia'],
        ['rotulo' => 'Homologado', 'valor' => $inv['situacao']['homologada'], 'cor' => '#20B295', 'link' => '/inventario?situacao=homologada'],
        ['rotulo' => 'Desatualizado', 'valor' => $inv['situacao']['desatualizada'], 'cor' => '#E2620B', 'link' => '/inventario?situacao=desatualizada'],
    ]) ?>
    <h3 class="sub-cartao">Por categoria</h3>
    <?= Graficos::barras([
        ['rotulo' => 'A. Infraestrutura de apoio', 'valor' => $inv['categoria']['A'], 'cor' => '#1E6EA7', 'link' => '/inventario?categoria=A'],
        ['rotulo' => 'B. Serviços e equipamentos', 'valor' => $inv['categoria']['B'], 'cor' => '#20B295', 'link' => '/inventario?categoria=B'],
        ['rotulo' => 'C. Atrativos', 'valor' => $inv['categoria']['C'], 'cor' => '#F3B137', 'link' => '/inventario?categoria=C'],
    ]) ?>
    <h3 class="sub-cartao">Por origem</h3>
    <?= Graficos::barras(array_values(array_filter([
        ['rotulo' => 'Cadastur', 'valor' => $inv['origem']['cadastur'] ?? 0, 'cor' => '#1E6EA7'],
        ['rotulo' => 'Caderno importado', 'valor' => $inv['origem']['importacao'] ?? 0, 'cor' => '#7A5BC7'],
        ['rotulo' => 'Painel', 'valor' => $inv['origem']['painel'] ?? 0, 'cor' => '#20B295'],
        ['rotulo' => 'Coleta em campo', 'valor' => $inv['origem']['campo'] ?? 0, 'cor' => '#F3B137'],
        ['rotulo' => 'CNPJ', 'valor' => $inv['origem']['cnpj'] ?? 0, 'cor' => '#63B6E5'],
        ['rotulo' => 'EventCity', 'valor' => $inv['origem']['eventcity'] ?? 0, 'cor' => '#E2620B'],
    ], fn ($b) => $b['valor'] > 0))) ?>
  </div>
  <?php endif; ?>
  <?php if (Auth::pode('coleta.ver')): ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Coleta de campo</h2><a href="/coleta">Ver a coleta</a></div>
    <?= Graficos::colunas(Painel::coletaPorDia($eid), [
        ['chave' => 'itens', 'nome' => 'Fichas gravadas', 'cor' => '#20B295'],
        ['chave' => 'fotos', 'nome' => 'Fotos', 'cor' => '#63B6E5'],
    ], 'O que chegou da coleta de campo por dia') ?>
  </div>
  <?php endif; ?>
  <?php if (Auth::pode('inventario.ver')): $portalM = Painel::portal($eid); $t = $portalM['totais']; ?>
  <div class="cartao" id="portal-visitas">
    <div class="cab-cartao"><h2>O portal nos últimos dias</h2><span class="nota">sem cookie e sem identificar quem visita</span></div>
    <?= Graficos::colunas($portalM['serie'], [['chave' => 'paginas', 'nome' => 'Páginas vistas', 'cor' => '#1C3E73']], 'Páginas vistas por dia nos últimos 14 dias') ?>
    <h3 class="sub-cartao">Em 30 dias, nos lugares</h3>
    <ul class="legenda-faixa">
      <li>Visualizações <b><?= milhar($t['v']) ?></b></li><li>Como chegar <b><?= milhar($t['r']) ?></b></li><li>WhatsApp <b><?= milhar($t['w']) ?></b></li>
      <li>Ligou <b><?= milhar($t['l']) ?></b></li><li>Site ou Instagram <b><?= milhar($t['s']) ?></b></li><li>Guardou no roteiro <b><?= milhar($t['g']) ?></b></li>
    </ul>
    <?php if ($portalM['top']): ?>
      <h3 class="sub-cartao">Mais vistos</h3>
      <?= Graficos::barras($portalM['top']) ?>
    <?php else: ?>
      <p class="nota-cartao">Os lugares mais vistos aparecem quando o portal tiver lugar homologado e as primeiras visitas.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?= Auth::pode('inventario.ver') ? quadrosDeBusca($eid) : '' ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Atividade no painel</h2><?= Auth::pode('auditoria.ver') ? '<a href="/configuracoes/auditoria">Auditoria</a>' : '' ?></div>
    <?= Graficos::colunas(Painel::atividade($eid), [['chave' => 'acoes', 'nome' => 'Ações registradas', 'cor' => '#2E8B3E']], 'Ações registradas na auditoria por dia') ?>
  </div>
</div>
<?php elseif (!$u['softsul']): ?>
<p class="aviso info"><?= icone('alerta') ?><span>Você ainda não está em nenhum destino. <a href="/trocar-empresa">Escolha um</a> para começar.</span></p>
<?php else: ?>
<div class="cartao cartao-destinos">
  <div class="cab-cartao"><h2>Destinos</h2><a href="/destinos">Gerenciar</a></div>
  <?php if (!$destinos): ?><p class="ajuda">Nenhum destino ativo. Cadastre o primeiro em Destinos.</p><?php else: ?>
  <div class="rolagem">
    <table class="dados">
      <thead><tr><th>Destino</th><th>Licença</th><th class="num">Equipe</th><th>Última atividade</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($destinos as $d): ?>
        <tr>
          <td class="elastica"><span class="txt"><b><?= e($d['nome_fantasia']) ?></b><span><?= e(trim($d['cidade'] . ($d['uf'] ? '/' . $d['uf'] : ''), '/')) ?></span></span></td>
          <td><?= e(['projeto' => 'Projeto', 'avaliacao' => 'Avaliação', 'ativo' => 'Licenciado', 'suspenso' => 'Suspenso'][$d['modo_operacao']] ?? (string) $d['modo_operacao']) ?></td>
          <td class="num dado"><?= (int) $d['equipe'] ?></td>
          <td class="dado"><?= $d['ultima_atividade'] ? e(dataBr((string) $d['ultima_atividade'], 'd/m/Y')) : '<span class="nulo">nenhuma</span>' ?></td>
          <td class="acao"><a class="botao claro mini" href="<?= e($portalDe($d)) ?>" target="_blank" rel="noopener"><?= icone('rede') ?>Portal</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="painel-colunas painel-graficos">
  <div class="cartao">
    <div class="cab-cartao"><h2>Visitas aos portais</h2><span class="nota">páginas vistas por dia, 14 dias, sem cookie</span></div>
    <?= Graficos::colunas(Painel::visitasPlataforma(), [['chave' => 'paginas', 'nome' => 'Páginas vistas', 'cor' => '#1C3E73']], 'Páginas vistas por dia nos portais') ?>
  </div>
  <?= quadrosDeBusca(null) ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Atividade no painel</h2><a href="/configuracoes/auditoria">Auditoria</a></div>
    <?= Graficos::colunas(Painel::atividade(0), [['chave' => 'acoes', 'nome' => 'Ações registradas', 'cor' => '#2E8B3E']], 'Ações registradas na auditoria por dia') ?>
  </div>

  <?php $ac = Acessos::resumo(7); ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Acessos ao servidor</h2><a href="/acessos">Detalhes</a></div>
    <div class="metricas">
      <div class="metrica"><span class="rot">Visitantes</span><span class="val"><?= milhar($ac['visitantes']) ?></span><span class="nota">por dia, somados em 7 dias</span></div>
      <div class="metrica"><span class="rot">Páginas vistas</span><span class="val"><?= milhar($ac['paginas']) ?></span><span class="nota">por pessoas</span></div>
      <div class="metrica"><span class="rot">Robôs</span><span class="val"><?= $ac['parte_robos'] ?>%</span><span class="nota">dos pedidos</span></div>
    </div>
    <p class="nota-cartao"><?= plural($ac['e5'], 'erro do servidor', 'erros do servidor') ?> e <?= milhar($ac['e429']) ?> pedidos barrados pelo limite por IP em 7 dias.</p>
    <?= Graficos::colunas(Acessos::porDia(14), [
        ['chave' => 'pessoas', 'nome' => 'Pessoas', 'cor' => Acessos::GRUPOS['pessoa']['cor']],
        ['chave' => 'robos', 'nome' => 'Robôs', 'cor' => Acessos::GRUPOS['outro']['cor']],
    ], 'Páginas vistas por dia nos últimos 14 dias, pessoas e robôs') ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Robôs</h2><span class="nota">pedidos em 7 dias</span></div>
    <?php $qp = Acessos::quemPediu(7); ?>
    <?= Graficos::faixa($qp['faixa']) ?>
    <?php if ($qp['robos']): ?>
      <h3 class="sub-cartao">Mais ativos</h3>
      <?= Graficos::barras(array_slice($qp['robos'], 0, 8)) ?>
    <?php endif; ?>
  </div>

  <div class="cartao">
    <div class="cab-cartao"><h2>Integrações e saúde</h2><a href="/configuracoes">Configurações</a></div>
    <ul class="lista-saude">
      <li><a href="/configuracoes/email"><?= icone('emails') ?><span class="txt-saude"><b>E-mail (Amazon SES)</b>
        <span><?= plural($devolvidos, 'devolução', 'devoluções') ?> e <?= plural($reclamacoes, 'reclamação', 'reclamações') ?> em 30 dias</span></span>
        <span class="pilula <?= !Email::configurado() ? 'ruim' : ($bloqueados ? 'at' : 'ok') ?>"><?= !Email::configurado() ? 'Desligado' : ($bloqueados ? plural($bloqueados, 'bloqueado', 'bloqueados') : 'Em dia') ?></span></a></li>
      <li><span class="item-saude"><?= icone('lista') ?><span class="txt-saude"><b>Fila de tarefas</b>
        <span><?= plural($fila['agendados'], 'agendado', 'agendados') ?> · <?= plural($fila['rodando'], 'rodando', 'rodando') ?><?= $fila['ultimo'] ? ' · última concluída ' . e(dataBr((string) $fila['ultimo'], 'd/m H:i')) : '' ?></span></span>
        <span class="pilula <?= $fila['falhos7'] || $fila['atrasados'] ? 'ruim' : 'ok' ?>"><?= $fila['atrasados'] ? plural($fila['atrasados'], 'atrasado', 'atrasados')
            : ($fila['falhos7'] ? plural($fila['falhos7'], 'falha', 'falhas') . ' em 7 dias' : 'Em dia') ?></span></span></li>
      <?php $bSaude = Bing::configurado() ? Bing::resumo(null) : null; ?>
      <li><a href="/configuracoes/google"><?= icone('busca') ?><span class="txt-saude"><b>Google Search Console</b>
        <span>inventour.com.br, todos os portais</span></span><span class="pilula <?= SearchConsole::configurado() ? 'ok' : 'at' ?>"><?= SearchConsole::configurado() ? 'Conectado' : 'Falta a chave' ?></span></a></li>
      <li><a href="/configuracoes/bing"><?= icone('busca') ?><span class="txt-saude"><b>Bing Webmaster</b>
        <span><?= $bSaude ? plural($bSaude['verificados'], 'portal verificado', 'portais verificados') . ' de ' . $bSaude['sites'] : 'Um site por portal, cadastrado sozinho' ?></span></span><span class="pilula <?= $bSaude ? 'ok' : 'at' ?>"><?= $bSaude ? 'Conectado' : 'Falta a chave' ?></span></a></li>
      <li><a href="/configuracoes/analytics"><?= icone('painel') ?><span class="txt-saude"><b>Google Analytics</b>
        <span><?= Analytics::lembrete() === 'marcar' ? 'Os cliques chegaram: marque clique_whatsapp e clique_rota como eventos principais' : (Analytics::medicao() !== '' ? 'Nos portais, com aviso de cookies' : 'Sem código de medição') ?></span></span><span class="pilula <?= Analytics::lembrete() === 'marcar' ? 'ruim' : (Analytics::configurado() ? 'ok' : 'at') ?>"><?= Analytics::lembrete() === 'marcar' ? 'Marcar eventos' : (Analytics::configurado() ? 'Conectado' : (Analytics::medicao() !== '' ? 'Falta a leitura' : 'Desligado')) ?></span></a></li>
    </ul>
  </div>
</div>
<?php endif; ?>
<?php paginaFim();
