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
require_once __DIR__ . '/../app/EventCity.php';

/**
 * Os quadros da busca orgânica e das visitas, como no Início do EventCity:
 * Google (Search Console), Bing (Webmaster) e Google Analytics. Com
 * $empresaId, o portal do destino; com null, todos os portais.
 */
function quadrosDeBusca(?int $empresaId): string
{
    ob_start();
    // Google e Bing num quadro só, lado a lado (Luiz, 22/11/2026). Motor sem
    // leitura ainda ocupa uma linha com a explicação, não três caixas de zero.
    $motores = [];
    if (SearchConsole::configurado()) {
        $motores[] = ['Google', SearchConsole::resumo($empresaId), '#1C3E73',
            'Os números aparecem quando o Google começar a mostrar o portal. Leva alguns dias depois do sitemap, e o portal só mostra lugar homologado.'];
    }
    if (Bing::configurado()) {
        $bR = Bing::resumo($empresaId);
        $motores[] = ['Bing', $bR, '#00809D', ($bR['sites'] && $bR['verificados'] < $bR['sites'] ? 'Aguardando a verificação do portal. ' : '')
            . 'Os números aparecem quando o Bing começar a mostrar o portal. Ele também abastece Yahoo, DuckDuckGo, Ecosia e Copilot.'];
    }
    if ($motores): ?>
  <div class="cartao" id="busca">
    <div class="cab-cartao"><h2>Na busca</h2><span class="nota">últimos 30 dias</span></div>
    <div class="busca-motores">
      <?php foreach ($motores as [$nomeM, $r, $corM, $explica]): $vazio = !$r['ultimo'] || (!$r['impressoes'] && !$r['termos']); ?>
      <div class="motor">
        <h3 class="sub-cartao"><?= e($nomeM) ?><?php if ($r['ultimo']): ?> <span class="nota">até <?= e(dataBr((string) $r['ultimo'], 'd/m')) ?></span><?php endif; ?></h3>
        <?php if ($vazio): ?>
          <p class="nota-cartao motor-vazio"><?= e($explica) ?></p>
        <?php else: ?>
          <ul class="numeros-motor">
            <li><b><?= milhar($r['cliques']) ?></b><span>cliques</span></li>
            <li><b><?= milhar($r['impressoes']) ?></b><span>impressões</span></li>
            <li><b><?= $r['posicao'] !== null ? number_format((float) $r['posicao'], 1, ',', '') : '-' ?></b><span>posição média</span></li>
          </ul>
          <?php if ($r['termos']): ?>
            <?= Graficos::barras(array_map(fn ($t) => ['rotulo' => $t['chave'], 'valor' => (int) $t['c'] ?: (int) $t['i'], 'cor' => $corM], array_slice($r['termos'], 0, 6))) ?>
            <p class="nota-cartao">Cliques por termo; sem clique ainda, as impressões.</p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
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
            . plural($inv['situacao']['em_conferencia'], 'em conferência', 'em conferência'), '/inventario', 'inv'];
        $metricas[] = ['Leitos homologados', $inv['leitos'], plural($inv['uh'], 'unidade habitacional', 'unidades habitacionais'), '/inventario?ficha=' . (int) $pdo->query('SELECT id FROM inventario_fichas WHERE ordem = 20')->fetchColumn(), 'inv'];
    }
    // Etapa 3: a esteira. Quem coordena vê a fila de conferência; quem
    // coleta, o que tem para levantar e o que voltou da conferência.
    require_once __DIR__ . '/../app/Esteira.php';
    if (Auth::pode('homologacao.ver')) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM inventario_itens WHERE empresa_id = ? AND situacao = 'homologada' AND homologado_em >= NOW() - INTERVAL 30 DAY");
        $q->execute([$eid]);
        $metricas[] = ['Fila de conferência', Esteira::naFila($eid), plural((int) $q->fetchColumn(), 'homologado', 'homologados') . ' nos últimos 30 dias', '/homologacao', 'inv'];
    }
    if (Auth::pode('coleta.ver')) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM campo_aparelhos WHERE empresa_id = ? AND token_hash IS NOT NULL AND revogado_em IS NULL');
        $q->execute([$eid]);
        $metricas[] = ['Para coletar', Esteira::paraColetar($eid), plural(Esteira::devolvidos($eid), 'devolvido', 'devolvidos') . ' pela conferência · '
            . plural((int) $q->fetchColumn(), 'aparelho com o app', 'aparelhos com o app') . (Esteira::escopo() !== null ? ' · no seu escopo' : ''), '/coleta', 'inv'];
    }
    if (Auth::pode('experiencias.ver')) {
        $q = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(situacao = 'aguardando'), 0), COALESCE(SUM(situacao = 'aprovada'), 0)
                            FROM inventario_experiencias WHERE empresa_id = ?");
        $q->execute([$eid]);
        [$totalX, $aguardandoX, $aprovadasX] = array_map('intval', $q->fetch(PDO::FETCH_NUM));
        $metricas[] = ['Experiências', $totalX, plural($aprovadasX, 'aprovada', 'aprovadas') . ' · ' . milhar($aguardandoX) . ' aguardando curadoria', '/experiencias', 'inv'];
    }
    // Etapa 6: o trade. A equipe vê quem responde pelos lugares e o que
    // espera por ela; o responsável, os lugares dele.
    if (Auth::pode('trade.ver')) {
        $q = $pdo->prepare("SELECT (SELECT COUNT(*) FROM inventario_reivindicacoes WHERE empresa_id = ? AND situacao = 'aprovada'),
                                   (SELECT COUNT(*) FROM inventario_reivindicacoes WHERE empresa_id = ? AND situacao = 'pendente'),
                                   (SELECT COUNT(*) FROM inventario_revisoes WHERE empresa_id = ? AND situacao = 'aguardando')");
        $q->execute([$eid, $eid, $eid]);
        [$respT, $pedT, $revT] = array_map('intval', $q->fetch(PDO::FETCH_NUM));
        $metricas[] = ['Trade turístico', $respT, plural($pedT, 'pedido', 'pedidos') . ' e ' . plural($revT, 'revisão', 'revisões') . ' esperando', '/trade', 'portal'];
    }
    if (Auth::pode('meus-lugares.ver')) {
        require_once __DIR__ . '/../app/Trade.php';
        $meus = Trade::meusLugares((int) $u['id']);
        $esperaM = count(array_filter($meus, fn ($l) => in_array($l['revisao'], ['aguardando', 'devolvida'], true)));
        $metricas[] = ['Meus lugares', count($meus), $esperaM ? plural($esperaM, 'atualização em conferência', 'atualizações em conferência') : 'tudo em dia no portal', '/meus-lugares', 'portal'];
    }
    if (Auth::pode('destino-textos.ver')) {
        $q = $pdo->prepare("SELECT (SELECT COUNT(*) FROM destino_narrativas WHERE empresa_id = ? AND conteudo_html <> ''),
                                   (SELECT COUNT(*) FROM destino_linha_tempo WHERE empresa_id = ?)");
        $q->execute([$eid, $eid]);
        [$textosD, $marcosD] = array_map('intval', $q->fetch(PDO::FETCH_NUM));
        $metricas[] = ['O destino', $textosD, 'de 5 textos · ' . plural($marcosD, 'marco', 'marcos') . ' na linha do tempo', '/destino-textos', 'destino'];
    }
    if (Auth::pode('caderno.ver')) {
        // Etapa 4: o caderno oficial, a última versão pronta e as leituras dos QR.
        $q = $pdo->prepare("SELECT versao, paginas, previa, (SELECT COALESCE(SUM(leituras), 0) FROM caderno_leituras WHERE empresa_id = ?)
                            FROM cadernos WHERE empresa_id = ? AND situacao = 'pronto' ORDER BY id DESC LIMIT 1");
        $q->execute([$eid, $eid]);
        $ultimo = $q->fetch(PDO::FETCH_NUM);
        $metricas[] = ['Caderno oficial', $ultimo ? (int) $ultimo[1] : 0, $ultimo
            ? 'páginas na versão ' . (int) $ultimo[0] . ($ultimo[2] ? ' (prévia)' : '') . ' · ' . plural((int) $ultimo[3], 'leitura do QR', 'leituras do QR')
            : 'nenhuma versão gerada ainda', '/caderno', 'portal'];
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
                . mb_strtolower(MapaTurismo::CATEGORIAS[(int) $noMapa['categoria']] ?? ''), Auth::pode('mapa-turismo.ver') ? '/mapa-turismo' : null, 'destino'];
        } elseif ($foraDoMapa = MapaTurismo::ano()) {
            if ($regiaoVizinha = MapaTurismo::regiaoDosVizinhos((int) $empresa['municipio_id'])) {
                $metricas[] = ['Região turística', (int) $regiaoVizinha['na_regiao'], 'municípios em ' . $regiaoVizinha['nome']
                    . ' · a dos vizinhos', Auth::pode('mapa-turismo.ver') ? '/mapa-turismo' : null, 'destino'];
            }
        }
    }
    // Etapa 7: a saúde do banco (radar de obsolescência): a parte dos lugares
    // homologados que segue em dia. Sem lugar no portal, não há o que medir.
    if (Auth::pode('inventario.ver')) {
        require_once __DIR__ . '/../app/Radar.php';
        $rs = Radar::saude($eid);
        if ($rs['saude'] !== null) {
            $metricas[] = ['Lugares em dia', $rs['saude'] . '%', 'dos homologados · ' . plural($rs['desatualizados'], 'desatualizado', 'desatualizados')
                . ' · ' . plural($rs['enviados'], 'aviso', 'avisos') . ' em 90 dias', Auth::pode('relatorios.ver') ? '/relatorio?tipo=radar' : null, 'inv'];
        }
    }
    // Etapa 7: leitos x eventos do EventCity nos próximos 90 dias.
    if (Auth::pode('inventario.ver') && EventCity::temAgenda($eid)) {
        $le = EventCity::noitesDeEventos($eid);
        $falta = count(array_filter($le['noites'], fn ($n) => $n['nivel'] === 'falta'));
        $atencao = count(array_filter($le['noites'], fn ($n) => $n['nivel'] === 'atencao'));
        $pico = $le['noites'] ? max(array_column($le['noites'], 'turistas')) : 0;
        // Sem hospedagem homologada não há conta a fazer: o cartão só diz o
        // que falta e leva à homologação. O pico segue no relatório.
        $metricas[] = ['Noites sem leito', $le['leitos'] ? $falta : '-', !$le['leitos']
            ? 'sem leitos homologados: homologue as hospedagens'
            : ($le['noites'] ? plural($atencao, 'noite', 'noites') . ' em atenção · pico de ' . plural($pico, 'turista', 'turistas') . ' para ' . plural($le['leitos'], 'leito', 'leitos')
            : 'nos eventos dos próximos 90 dias · nenhum com turistas informados' . ($le['sem_divisao'] ? ' (' . count($le['sem_divisao']) . ' sem a divisão do público)' : '')),
            !$le['leitos'] && Auth::pode('homologacao.ver') ? '/homologacao' : (Auth::pode('relatorios.ver') ? '/relatorio?tipo=leitos_eventos' : null), 'inv'];
    }
    // Busca orgânica (Search Console e Bing), como no EventCity: 30 dias.
    if (Auth::pode('inventario.ver')) {
        require_once __DIR__ . '/../app/Bing.php';
        // Google e Bing num cartão só, que leva ao quadro "Na busca".
        $g = SearchConsole::configurado() ? SearchConsole::resumo($eid) : null;
        $b = Bing::configurado() ? Bing::resumo($eid) : null;
        if ($g || $b) {
            $partes = [];
            if ($g) { $partes[] = milhar($g['impressoes']) . ' no Google'; }
            if ($b) { $partes[] = milhar($b['impressoes']) . ' no Bing'; }
            $metricas[] = ['Cliques da busca', ($g['cliques'] ?? 0) + ($b['cliques'] ?? 0), 'impressões em 30 dias: ' . implode(' e ', $partes), '#busca', 'portal'];
        }
    }
    if (Auth::pode('visitas.ver')) {
        require_once __DIR__ . '/../app/Analytics.php';
        if (Analytics::configurado()) {
            $ga = Analytics::resumo($eid);
            $metricas[] = ['Visitas no portal', $ga['usuarios'], 'visitantes em 28 dias · ' . plural($ga['sessoes'], 'sessão', 'sessões') . ', ' . $ga['engajamento'] . '% engajadas', '/visitas', 'portal'];
        }
    }
    // Módulos que ainda não tinham resumo aqui (Luiz, 22/11/2026): pontos
    // de QR, roteiros, a agenda do EventCity e o diagnóstico DTI.
    if (Auth::pode('phygital.ver')) {
        $q = $pdo->prepare('SELECT (SELECT COUNT(*) FROM qr_pontos WHERE empresa_id = ? AND ativo = 1),
                                   (SELECT COALESCE(SUM(leituras), 0) FROM qr_leituras WHERE empresa_id = ? AND dia >= CURDATE() - INTERVAL 30 DAY)');
        $q->execute([$eid, $eid]);
        [$pontosQ, $leiturasQ] = array_map('intval', $q->fetch(PDO::FETCH_NUM));
        $metricas[] = ['Pontos de QR', $pontosQ, $pontosQ ? plural($leiturasQ, 'leitura', 'leituras') . ' em 30 dias' : 'nenhum ponto criado ainda', '/phygital', 'portal'];
    }
    if (Auth::pode('roteiros.ver')) {
        $q = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(publicado = 1), 0), COALESCE(SUM(tipo = 'um_dia'), 0) FROM roteiros WHERE empresa_id = ?");
        $q->execute([$eid]);
        [$totalR, $publicadosR, $umDiaR] = array_map('intval', $q->fetch(PDO::FETCH_NUM));
        $metricas[] = ['Roteiros', $totalR, $totalR ? plural($publicadosR, 'publicado', 'publicados') . ' no portal · ' . plural($umDiaR, 'de um dia', 'de um dia') : 'nenhum roteiro montado ainda', '/roteiros', 'portal'];
    }
    if (EventCity::temAgenda($eid)) {
        $agendaE = EventCity::agendaEmCache($eid) ?? [];
        $proxE = $agendaE[0] ?? null;
        $metricas[] = ['Agenda do EventCity', count($agendaE), $proxE
            ? 'eventos em 90 dias · o próximo em ' . date('d/m', strtotime((string) ($proxE['proximo_dia'] ?? $proxE['data_inicio'])))
            : 'eventos em 90 dias', Auth::pode('configuracoes.ver') ? '/eventcity' : null, 'portal'];
    }
    if (Auth::pode('diagnostico.ver') && !(int) $pdo->query("SELECT desligado FROM modulos WHERE chave = 'diagnostico'")->fetchColumn()) {
        require_once __DIR__ . '/../app/Diagnostico.php';
        $cicloD = Diagnostico::ciclos($eid)[0] ?? null;
        $resD = null;
        $temDiagnostico = true;
        if (!$cicloD) {
            $metricas[] = ['Diagnóstico DTI', '-', 'nenhum diagnóstico aberto: comece pelos 105 requisitos do MTur', '/diagnostico', 'destino'];
        } elseif ($cicloD['situacao'] === 'fechado') {
            $metricas[] = ['Diagnóstico DTI', Diagnostico::nota((float) $cicloD['nota']), 'média do ciclo fechado em ' . dataBr((string) $cicloD['fechado_em'], 'd/m/Y'), '/diagnostico?c=' . (int) $cicloD['id'], 'destino'];
        } else {
            $resD = Diagnostico::resultado(Diagnostico::requisitos((int) $cicloD['metodologia_id']), Diagnostico::respostas((int) $cicloD['id']));
            $metricas[] = ['Diagnóstico DTI', Diagnostico::nota($resD['geral']['media']), 'média parcial · ' . (int) $cicloD['respondidos'] . ' de ' . (int) $cicloD['total'] . ' requisitos respondidos', '/diagnostico?c=' . (int) $cicloD['id'], 'destino'];
        }
    }
    $metricas[] = ['Equipe', Painel::equipe($eid), 'pessoas com acesso ao destino', Auth::pode('usuarios.ver') ? '/configuracoes/usuarios' : null, 'destino'];
} elseif ($u['softsul']) {
    $destinos = Painel::destinosPlataforma();
    $fila = Painel::filaPlataforma();
    [$devolvidos, $reclamacoes, $bloqueados] = SesAvisos::resumo();
    $licenciados = count(array_filter($destinos, fn ($d) => $d['modo_operacao'] === 'ativo'));
    $metricas[] = ['Destinos', count($destinos), plural($licenciados, 'licenciado', 'licenciados') . ', ' . plural(count($destinos) - $licenciados, 'em projeto ou avaliação', 'em projeto ou avaliação'), '/destinos'];
    // O catálogo do MTur que os formulários do inventário usam.
    [$totalFichas, $totalTipos] = Database::conexao()->query('SELECT (SELECT COUNT(*) FROM inventario_fichas WHERE ativo = 1), (SELECT COUNT(*) FROM inventario_tipos WHERE ativo = 1)')->fetch(PDO::FETCH_NUM);
    $metricas[] = ['Fichas do MTur', (int) $totalFichas, plural((int) $totalTipos, 'tipo e subtipo', 'tipos e subtipos') . ' no catálogo', Auth::pode('fichas.ver') ? '/fichas' : null];
    // Google e Bing num cartão só, que leva ao quadro "Na busca".
    $gP = SearchConsole::configurado() ? SearchConsole::resumo(null) : null;
    $bP = Bing::configurado() ? Bing::resumo(null) : null;
    if ($gP || $bP) {
        $partes = [];
        if ($gP) { $partes[] = milhar($gP['impressoes']) . ' no Google'; }
        if ($bP) { $partes[] = milhar($bP['impressoes']) . ' no Bing (' . plural($bP['verificados'], 'portal verificado', 'portais verificados') . ')'; }
        $metricas[] = ['Cliques da busca', ($gP['cliques'] ?? 0) + ($bP['cliques'] ?? 0), 'impressões em 30 dias, todos os portais: ' . implode(' e ', $partes), '#busca'];
    }
    if (Analytics::configurado()) {
        $ga = Analytics::resumo(null);
        $metricas[] = ['Visitas aos portais', $ga['usuarios'], 'visitantes em 28 dias · ' . plural($ga['sessoes'], 'sessão', 'sessões'), '/visitas'];
    }
    $metricas[] = ['E-mails devolvidos', $devolvidos, plural($reclamacoes, 'reclamação', 'reclamações') . ' em 30 dias · ' . plural($bloqueados, 'bloqueado', 'bloqueados'), '/configuracoes/email'];
}
// Cadastur e Mapa do Turismo são números do país: na visão da plataforma
// viram cartões; no destino, o Mapa entra no aviso de quem está fora dele.
if (!$empresa && Auth::pode('cadastur.ver')) {
    require_once __DIR__ . '/../app/Cadastur.php';
    $baseCadastur = Cadastur::base();
    $metricas[] = ['Cadastur', Cadastur::totais()[0],
        'prestadores no país' . ($baseCadastur ? ', ' . mb_strtolower((string) $baseCadastur['recurso_nome']) : ''), '/cadastur'];
}
if (Auth::pode('mapa-turismo.ver') || !empty($foraDoMapa)) {
    require_once __DIR__ . '/../app/MapaTurismo.php';
    $mapaTurismo = MapaTurismo::totais();
}
if (!$empresa && Auth::pode('mapa-turismo.ver')) {
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
           data-balao-titulo="Prévia do portal" data-balao="Abre o portal com o que ainda não foi homologado, por 2 horas. É só para a equipe: não compartilhe o link."><?= icone('olho') ?>Prévia do portal</a>
      <?php endif; ?>
      <a class="botao claro" href="<?= e($portalDe($empresa)) ?>" target="_blank" rel="noopener"><?= icone('rede') ?>Ver o portal</a></div>
  <?php endif; ?>
</div>

<?php
// Os cartões do destino em faixas por assunto (Luiz, 22/11/2026): eram 17
// soltos, e no celular nove telas antes da esteira. No celular só a faixa do
// Inventário começa aberta; as outras abrem pelo título.
$cartao = function (array $m, bool $destaque): void {
    [$rotulo, $valor, $nota, $link] = $m;
    $cls = 'metrica' . ($link ? ' metrica-link' : '') . ($destaque ? ' destaque' : '');
    // A nota fica em duas linhas no cartão compacto; a inteira, no balão.
    $balao = mb_strlen($nota) > 60 ? ' data-balao-titulo="' . e($rotulo) . '" data-balao="' . e($nota) . '"' : '';
    echo $link ? '<a class="' . $cls . '" href="' . e($link) . '"' . $balao . '>' : '<div class="' . $cls . '"' . $balao . '>';
    echo '<span class="rot">' . e($rotulo) . '</span><span class="val">' . (is_string($valor) ? e($valor) : milhar((int) $valor)) . '</span><span class="nota">' . e($nota) . '</span>';
    echo $link ? '</a>' : '</div>';
};
$faixas = ['inv' => 'Inventário', 'portal' => 'Portal e divulgação', 'destino' => 'Destino'];
?>
<?php if ($metricas && $empresa): ?>
  <?php foreach ($faixas as $chave => $nomeFaixa): $daFaixa = array_values(array_filter($metricas, fn ($m) => ($m[4] ?? 'destino') === $chave)); if (!$daFaixa) { continue; } ?>
  <section class="faixa-metricas" id="faixa-<?= $chave ?>"<?= $chave !== 'inv' ? ' data-recolhe-celular' : '' ?>>
    <button type="button" class="faixa-cab" aria-expanded="true" aria-controls="cartoes-<?= $chave ?>"><span><?= e($nomeFaixa) ?></span><em><?= count($daFaixa) ?></em><?= icone('seta', null, 'faixa-seta') ?></button>
    <div class="metricas compactas" id="cartoes-<?= $chave ?>">
      <?php foreach ($daFaixa as $i => $m) { $cartao($m, $chave === 'inv' && $i === 0); } ?>
    </div>
  </section>
  <?php endforeach; ?>
<?php elseif ($metricas): ?>
<div class="metricas compactas metricas-plataforma">
  <?php foreach ($metricas as $i => $m) { $cartao($m, $i === 0); } ?>
</div>
<?php endif; ?>

<?php if ($empresa): ?>
<?php if (isset($inv) && $inv['total'] === 0): ?>
<p class="aviso info"><?= icone('mapa') ?><span><strong>Para começar o inventário de <?= e($empresa['nome_fantasia']) ?>:</strong>
faça o <a href="/pre-cadastro">pré-cadastro pelo Cadastur</a>, que traz hotéis, restaurantes e agências que a base oficial já conhece, e escreva os textos em <a href="/destino-textos">O destino</a>. Os atrativos e a infraestrutura de apoio entram pelo Novo item do Inventário.</span></p>
<?php elseif (!empty($foraDoMapa)): ?>
<p class="aviso atencao"><?= icone('mapa') ?><span><strong><?= e($empresa['cidade'] ?? '' ?: $empresa['nome_fantasia']) ?> não está no Mapa do Turismo Brasileiro <?= (int) $foraDoMapa ?>.</strong>
O Mapa é a referência do Ministério do Turismo para regiões turísticas e para os programas dele<?php if (!empty($mapaTurismo['municipios'])): ?>, e a edição <?= (int) $foraDoMapa ?> reúne <?= milhar((int) $mapaTurismo['municipios']) ?> municípios em <?= plural((int) $mapaTurismo['regioes'], 'região turística', 'regiões turísticas') ?><?php endif; ?>. A inclusão é pedida pela prefeitura ao órgão estadual de turismo, no período de atualização do Mapa.<?php if ($regiaoVizinha): ?>
 Os municípios vizinhos estão na região turística <strong><?= e($regiaoVizinha['nome']) ?></strong>.<?php endif; ?><?php if (Auth::pode('mapa-turismo.ver')): ?> <a href="/mapa-turismo">Ver o Mapa</a><?php endif; ?></span></p>
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
    <?php $coletaDias = Painel::coletaPorDia($eid); $somaItens = array_sum(array_column($coletaDias, 'itens')); $somaFotos = array_sum(array_column($coletaDias, 'fotos')); ?>
    <div class="cab-cartao"><h2>Coleta de campo <span class="total-titulo"><?= $somaItens || $somaFotos ? plural($somaItens, 'ficha', 'fichas') . ' e ' . plural($somaFotos, 'foto', 'fotos') . ' em 14 dias' : 'nada em 14 dias' ?></span></h2><a href="/coleta">Ver a coleta</a></div>
    <div class="grafico-baixo"><?= Graficos::colunas($coletaDias, [
        ['chave' => 'itens', 'nome' => 'Fichas gravadas', 'cor' => '#20B295'],
        ['chave' => 'fotos', 'nome' => 'Fotos', 'cor' => '#63B6E5'],
    ], 'O que chegou da coleta de campo por dia') ?></div>
  </div>
  <?php endif; ?>
  <?php if (isset($inv) && $inv['total'] > 0): $porFicha = Painel::lugaresPorFicha($eid); ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Lugares por tipo <span class="total-titulo">os <?= count($porFicha) ?> tipos com mais lugares no inventário</span></h2><a href="/inventario">Ver o inventário</a></div>
    <?= Graficos::barras($porFicha) ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($agendaE)): $porSegmento = [];
      foreach ($agendaE as $ev) { $sg = (string) ($ev['segmento'] ?? '') ?: 'Sem segmento'; $porSegmento[$sg] = ($porSegmento[$sg] ?? 0) + 1; }
      arsort($porSegmento); ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Agenda dos próximos 90 dias <span class="total-titulo"><?= plural(count($agendaE), 'evento', 'eventos') ?> no EventCity, por semana</span></h2><?= Auth::pode('configuracoes.ver') ? '<a href="/eventcity">EventCity</a>' : '' ?></div>
    <div class="grafico-baixo"><?= Graficos::colunas(Painel::agendaPorSemana($agendaE), [['chave' => 'eventos', 'nome' => 'Eventos', 'cor' => '#E2620B']], 'Eventos por semana nos próximos 90 dias') ?></div>
    <h3 class="sub-cartao">Por segmento</h3>
    <?= Graficos::barras(array_map(fn ($n, $v) => ['rotulo' => $n, 'valor' => $v, 'cor' => '#E2620B'], array_keys(array_slice($porSegmento, 0, 5, true)), array_slice($porSegmento, 0, 5, true))) ?>
  </div>
  <?php endif; ?>
  <?php if (Auth::pode('inventario.ver')): $portalM = Painel::portal($eid); $t = $portalM['totais']; ?>
  <div class="cartao" id="portal-visitas">
    <div class="cab-cartao"><h2>O portal nos últimos dias <span class="total-titulo"><?= plural((int) array_sum(array_column($portalM['serie'], 'paginas')), 'página vista', 'páginas vistas') ?> em 14 dias</span></h2><span class="nota">sem cookie</span></div>
    <div class="grafico-baixo"><?= Graficos::colunas($portalM['serie'], [['chave' => 'paginas', 'nome' => 'Páginas vistas', 'cor' => '#1C3E73']], 'Páginas vistas por dia nos últimos 14 dias') ?></div>
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
    <?php $atividadeDias = Painel::atividade($eid); ?>
    <div class="cab-cartao"><h2>Atividade no painel <span class="total-titulo"><?= plural((int) array_sum(array_column($atividadeDias, 'acoes')), 'ação', 'ações') ?> em 14 dias</span></h2><?= Auth::pode('auditoria.ver') ? '<a href="/configuracoes/auditoria">Auditoria</a>' : '' ?></div>
    <div class="grafico-baixo"><?= Graficos::colunas($atividadeDias, [['chave' => 'acoes', 'nome' => 'Ações registradas', 'cor' => '#2E8B3E']], 'Ações registradas na auditoria por dia') ?></div>
  </div>
  <?php if (Auth::pode('phygital.ver')): $qrDias = Painel::qrPorDia($eid); $qrTop = Painel::qrMaisLidos($eid); ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Leituras dos QR <span class="total-titulo"><?= plural((int) array_sum(array_column($qrDias, 'leituras')), 'leitura', 'leituras') ?> em 14 dias</span></h2><a href="/phygital">Pontos de QR</a></div>
    <?php if (empty($pontosQ)): ?>
      <p class="nota-cartao">Nenhum ponto de QR criado ainda. Cada ponto leva quem lê a uma página do portal, e as leituras aparecem aqui por dia.</p>
    <?php else: ?>
      <div class="grafico-baixo"><?= Graficos::colunas($qrDias, [['chave' => 'leituras', 'nome' => 'Leituras', 'cor' => '#7A5BC7']], 'Leituras dos QR por dia') ?></div>
      <?php if ($qrTop): ?><h3 class="sub-cartao">Mais lidos em 30 dias</h3><?= Graficos::barras($qrTop) ?><?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($temDiagnostico)): ?>
  <div class="cartao">
    <?php $resR = $cicloD ? ($resD ?? (json_decode((string) ($cicloD['resultado'] ?? ''), true) ?: null)) : null; ?>
    <div class="cab-cartao"><h2>Diagnóstico DTI <span class="total-titulo"><?= $cicloD ? e((string) $cicloD['titulo']) . ' · ' . ($cicloD['situacao'] === 'fechado' ? 'fechado' : (int) $cicloD['respondidos'] . ' de ' . (int) $cicloD['total'] . ' respondidos') : 'metodologia do MTur, 105 requisitos' ?></span></h2><a href="<?= e($cicloD ? '/diagnostico?c=' . (int) $cicloD['id'] : '/diagnostico') ?>">Ver o diagnóstico</a></div>
    <?php if ($resR && (int) ($resR['geral']['respondidos'] ?? 0) > 0): ?>
      <div class="radar-dti-caixa radar-inicio"><?= Diagnostico::radar($resR['grupos'], 3.0) ?></div>
      <p class="nota-cartao">Média de cada eixo, de 1 a 5. A linha tracejada é a média mínima 3 da distinção DTI.</p>
    <?php else: ?>
      <p class="nota-cartao">O radar dos 10 eixos aparece aqui assim que os primeiros requisitos tiverem nível.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
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
  <?php $invDest = Painel::inventarioPorDestino(); $somaInv = array_sum(array_column($invDest, 'total')); $somaHom = array_sum(array_column($invDest, 'homologados')); ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Inventário por destino <span class="total-titulo"><?= plural((int) $somaInv, 'lugar', 'lugares') ?>, <?= plural((int) $somaHom, 'homologado', 'homologados') ?></span></h2><a href="/destinos">Destinos</a></div>
    <?php if ($somaInv): ?>
      <?= Graficos::faixa([
          ['rotulo' => 'Homologados', 'valor' => (int) $somaHom, 'cor' => '#20B295'],
          ['rotulo' => 'Ainda na esteira', 'valor' => (int) ($somaInv - $somaHom), 'cor' => '#9AA3B2'],
      ]) ?>
      <h3 class="sub-cartao">Lugares em cada destino</h3>
      <?= Graficos::barras(array_map(fn ($d) => ['rotulo' => $d['nome_fantasia'], 'valor' => (int) $d['total'], 'cor' => '#1E6EA7'], array_slice($invDest, 0, 8))) ?>
    <?php else: ?>
      <p class="nota-cartao">Nenhum destino tem lugar no inventário ainda.</p>
    <?php endif; ?>
  </div>
  <?php $coletaTodos = Painel::coletaPorDia(0); ?>
  <div class="cartao">
    <div class="cab-cartao"><h2>Coleta de campo <span class="total-titulo"><?= plural((int) array_sum(array_column($coletaTodos, 'itens')), 'ficha', 'fichas') ?> e <?= plural((int) array_sum(array_column($coletaTodos, 'fotos')), 'foto', 'fotos') ?> em 14 dias, todos os destinos</span></h2></div>
    <div class="grafico-baixo"><?= Graficos::colunas($coletaTodos, [
        ['chave' => 'itens', 'nome' => 'Fichas gravadas', 'cor' => '#20B295'],
        ['chave' => 'fotos', 'nome' => 'Fotos', 'cor' => '#63B6E5'],
    ], 'O que chegou da coleta de campo por dia, em todos os destinos') ?></div>
  </div>
  <div class="cartao">
    <?php $visitasTodos = Painel::visitasPlataforma(); ?>
    <div class="cab-cartao"><h2>Visitas aos portais <span class="total-titulo"><?= plural((int) array_sum(array_column($visitasTodos, 'paginas')), 'página vista', 'páginas vistas') ?> em 14 dias</span></h2><span class="nota">sem cookie</span></div>
    <div class="grafico-baixo"><?= Graficos::colunas($visitasTodos, [['chave' => 'paginas', 'nome' => 'Páginas vistas', 'cor' => '#1C3E73']], 'Páginas vistas por dia nos portais') ?></div>
  </div>
  <?= quadrosDeBusca(null) ?>
  <div class="cartao">
    <?php $atividadeTodos = Painel::atividade(0); ?>
    <div class="cab-cartao"><h2>Atividade no painel <span class="total-titulo"><?= plural((int) array_sum(array_column($atividadeTodos, 'acoes')), 'ação', 'ações') ?> em 14 dias, todos os destinos</span></h2><a href="/configuracoes/auditoria">Auditoria</a></div>
    <div class="grafico-baixo"><?= Graficos::colunas($atividadeTodos, [['chave' => 'acoes', 'nome' => 'Ações registradas', 'cor' => '#2E8B3E']], 'Ações registradas na auditoria por dia') ?></div>
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
