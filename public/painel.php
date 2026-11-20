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
 * neste Início.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Painel.php';
require_once __DIR__ . '/../app/Graficos.php';
require_once __DIR__ . '/../app/SesAvisos.php';
require_once __DIR__ . '/../app/Email.php';

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
    <div class="acoes"><a class="botao claro" href="<?= e($portalDe($empresa)) ?>" target="_blank" rel="noopener"><?= icone('rede') ?>Ver o portal</a></div>
  <?php endif; ?>
</div>

<?php if ($metricas): ?>
<div class="metricas">
  <?php foreach ($metricas as $i => [$rotulo, $valor, $nota, $link]): ?>
    <?php if ($link): ?><a class="metrica metrica-link<?= $i === 0 ? ' destaque' : '' ?>" href="<?= e($link) ?>"><?php else: ?><div class="metrica<?= $i === 0 ? ' destaque' : '' ?>"><?php endif; ?>
      <span class="rot"><?= e($rotulo) ?></span><span class="val"><?= (int) $valor ?></span><span class="nota"><?= e($nota) ?></span>
    <?= $link ? '</a>' : '</div>' ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($empresa): ?>
<?php if (isset($inv) && $inv['total'] === 0): ?>
<p class="aviso info"><?= icone('mapa') ?><span><strong>Para começar o inventário de <?= e($empresa['nome_fantasia']) ?>:</strong>
faça o <a href="/pre-cadastro">pré-cadastro pelo Cadastur</a>, que traz hotéis, restaurantes e agências que a base oficial já conhece, e escreva os textos em <a href="/destino-textos">O destino</a>. Os atrativos e a infraestrutura de apoio entram pelo Novo item do Inventário.</span></p>
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
    <div class="cab-cartao"><h2>Atividade no painel</h2><a href="/configuracoes/auditoria">Auditoria</a></div>
    <?= Graficos::colunas(Painel::atividade(0), [['chave' => 'acoes', 'nome' => 'Ações registradas', 'cor' => '#2E8B3E']], 'Ações registradas na auditoria por dia') ?>
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
    </ul>
  </div>
</div>
<?php endif; ?>
<?php paginaFim();
