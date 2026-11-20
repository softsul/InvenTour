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
$hora = (int) date('G');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
$dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$dominioPortal = (string) ((require __DIR__ . '/../app/config.php')['app']['dominio_portal'] ?? 'inventour.com.br');
$portalDe = fn (array $d): string => 'https://' . ($d['dominio_proprio'] ?: $d['slug'] . '.' . $dominioPortal);

$metricas = [];
if ($empresa) {
    $metricas[] = ['Equipe', Painel::equipe($eid), 'pessoas com acesso ao destino', Auth::pode('usuarios.ver') ? '/configuracoes/usuarios' : null];
} elseif ($u['softsul']) {
    $destinos = Painel::destinosPlataforma();
    $fila = Painel::filaPlataforma();
    [$devolvidos, $reclamacoes, $bloqueados] = SesAvisos::resumo();
    $metricas[] = ['Destinos', count($destinos), 'ativos no InvenTour', '/destinos'];
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
<p class="aviso info"><?= icone('mapa') ?><span><strong>O inventário de <?= e($empresa['nome_fantasia']) ?> está a caminho.</strong>
As fichas da oferta turística, a coleta de campo, a homologação e o caderno oficial entram aqui assim que cada módulo ficar pronto.</span></p>
<div class="painel-colunas painel-graficos">
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
