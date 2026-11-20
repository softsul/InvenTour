<?php
/**
 * E-mail da plataforma: o envio pelo SES e as devoluções e reclamações.
 * Softsul Software
 *
 * Mesmo lugar e molde do WhatsApp (Configurações da plataforma,
 * Integrações): a situação do envio com um teste, o resumo de 30 dias e a
 * lista do que a Amazon avisou pelo SNS (SesAvisos), com o desbloqueio de
 * um endereço quando a pessoa corrige o cadastro ou pede para voltar a
 * receber.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Email.php';
require_once __DIR__ . '/../app/SesAvisos.php';
require_once __DIR__ . '/../app/Auditoria.php';

Auth::exigirLogin();
if (!Auth::ehSoftsul()) {
    paginaErro(403, 'Esta tela é da equipe Softsul.', 'O envio de e-mail vale para todos os destinos.');
}
$pdo = Database::conexao();
$u = Auth::usuarioOuNulo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        avisar('A página ficou aberta tempo demais. Tente de novo.', 'ruim');
    } elseif (($_POST['acao'] ?? '') === 'teste') {
        $para = trim((string) ($_POST['para'] ?? ''));
        try {
            Email::enviar($para, 'Teste de envio do InvenTour',
                Email::molde('Teste de envio', '<p>Se esta mensagem chegou, o envio de e-mail do InvenTour está funcionando.</p>'));
            Auditoria::registrar('alterou', 'ajustes', null, 'enviou e-mail de teste para ' . $para);
            avisar('Teste enviado para ' . $para . '. Confira a caixa de entrada.');
        } catch (Throwable $e) {
            avisar('O teste não saiu: ' . $e->getMessage(), 'ruim');
        }
    } elseif (($_POST['acao'] ?? '') === 'liberar') {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        SesAvisos::liberar($email, (int) $u['id']);
        Auditoria::registrar('alterou', 'ajustes', null, 'desbloqueou o e-mail ' . $email);
        avisar($email . ' voltou a receber e-mails do InvenTour.');
    }
    redirecionar('/configuracoes/email' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$busca = trim((string) ($_GET['q'] ?? ''));
$tipo = in_array($_GET['tipo'] ?? '', ['devolucao', 'reclamacao'], true) ? (string) $_GET['tipo'] : '';
$situacao = in_array($_GET['situacao'] ?? '', ['bloqueados', 'liberados', 'temporarias'], true) ? (string) $_GET['situacao'] : '';
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = Auth::porPagina();
$parcial = !empty($_GET['parcial']);

$baseOnde = ['1 = 1'];
$baseArgs = [];
if ($busca !== '') {
    [$sqlBusca, $argsBusca] = Lista::porPalavras($busca, ['o.email', 'o.detalhe']);
    $baseOnde[] = $sqlBusca;
    array_push($baseArgs, ...$argsBusca);
}
if ($tipo !== '') {
    $baseOnde[] = 'o.tipo = ?';
    $baseArgs[] = $tipo;
}
$regraSituacao = [
    'bloqueados'  => "o.liberado_em IS NULL AND (o.tipo = 'reclamacao' OR o.permanente = 1)",
    'liberados'   => 'o.liberado_em IS NOT NULL',
    'temporarias' => "o.tipo = 'devolucao' AND o.permanente = 0",
];
$onde = $baseOnde;
if ($situacao !== '') {
    $onde[] = $regraSituacao[$situacao];
}
$where = 'WHERE ' . implode(' AND ', $onde);
$t = $pdo->prepare("SELECT COUNT(*) FROM email_ocorrencias o {$where}");
$t->execute($baseArgs);
$total = (int) $t->fetchColumn();
$q = $pdo->prepare("SELECT o.*, u.nome AS liberado_nome FROM email_ocorrencias o LEFT JOIN usuarios u ON u.id = o.liberado_por
                    {$where} ORDER BY o.criado_em DESC, o.id DESC LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina));
$q->execute($baseArgs);
$linhas = $q->fetchAll();
$c = $pdo->prepare('SELECT COUNT(*), SUM(' . $regraSituacao['bloqueados'] . '), SUM(' . $regraSituacao['liberados'] . '), SUM(' . $regraSituacao['temporarias'] . ')
                    FROM email_ocorrencias o WHERE ' . implode(' AND ', $baseOnde));
$c->execute($baseArgs);
[$nTodas, $nBloq, $nLib, $nTemp] = array_map('intval', $c->fetch(PDO::FETCH_NUM));

$comFiltro = function (array $troca) use ($busca, $tipo, $situacao): string {
    $q = array_filter(array_merge(['q' => $busca, 'tipo' => $tipo, 'situacao' => $situacao], $troca), fn ($v) => $v !== null && $v !== '');
    return '/configuracoes/email' . ($q ? '?' . http_build_query($q) : '');
};

$lista = function () use ($linhas, $total, $pagina, $busca, $tipo, $situacao) {
    if (!$linhas): ?>
      <div class="vazio">
        <?= icone('emails') ?>
        <p><strong>Nenhuma devolução ou reclamação<?= $busca !== '' ? ' para "' . e($busca) . '"' : '' ?>.</strong></p>
        <p>Quando um e-mail do InvenTour voltar ou for marcado como spam, a Amazon avisa e o endereço aparece aqui.</p>
      </div>
    <?php else: ?>
      <div class="rolagem">
        <table class="dados">
          <thead><tr><th>E-mail</th><th>Tipo</th><th>Motivo</th><th>Quando</th><th>Situação</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($linhas as $o):
              $bloq = $o['liberado_em'] === null && ($o['tipo'] === 'reclamacao' || (int) $o['permanente'] === 1); ?>
            <tr>
              <td class="elastica"><span class="txt"><b><?= e($o['email']) ?></b><?php if ($o['detalhe']): ?><span title="<?= e($o['detalhe']) ?>"><?= e($o['detalhe']) ?></span><?php endif; ?></span></td>
              <td><?= $o['tipo'] === 'reclamacao' ? 'Reclamação' : ((int) $o['permanente'] ? 'Devolução definitiva' : 'Devolução temporária') ?></td>
              <td><?= $o['subtipo'] ? e($o['subtipo']) : '<span class="nulo">-</span>' ?></td>
              <td class="dado"><?= e(dataBr((string) $o['criado_em'], 'd/m/Y H:i')) ?></td>
              <td><?php if ($bloq): ?><span class="pilula ruim">Bloqueado</span>
                  <?php elseif ($o['liberado_em']): ?><span class="pilula ok" title="<?= e('Por ' . ($o['liberado_nome'] ?? '?') . ' em ' . dataBr((string) $o['liberado_em'], 'd/m/Y H:i')) ?>">Liberado</span>
                  <?php else: ?><span class="pilula nu">Só registro</span><?php endif; ?></td>
              <td class="acao"><?php if ($bloq): ?>
                <form class="form-linha" method="post" action="<?= e('/configuracoes/email' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . preg_replace('/(^|&)parcial=1/', '', (string) $_SERVER['QUERY_STRING']) : '')) ?>">
                  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
                  <input type="hidden" name="acao" value="liberar"><input type="hidden" name="email" value="<?= e($o['email']) ?>">
                  <button type="submit" class="botao claro mini" data-titulo="Desbloquear e-mail" data-botao="Desbloquear"
                          data-confirmar="<?= e($o['email'] . ' volta a receber e-mails do InvenTour. Faça isso só se o endereço foi corrigido ou a pessoa pediu: outra devolução ou reclamação conta contra a conta inteira na Amazon.') ?>">Desbloquear</button>
                </form><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
      echo paginacao($total, $pagina, '/configuracoes/email', array_filter(['q' => $busca, 'tipo' => $tipo, 'situacao' => $situacao]));
    endif;
};

if ($parcial) {
    $lista();
    exit;
}

$cfg = (require __DIR__ . '/../app/config.php')['mail'] ?? [];
[$devolvidos, $reclamacoes, $bloqueados] = SesAvisos::resumo();
$remetente = (string) (($cfg['remetente_email'] ?? '') ?: ($cfg['smtp_usuario'] ?? ''));
paginaInicio('E-mail', 'configuracoes');
?>
<div class="trilha"><a href="/configuracoes">Configurações</a><?= icone('seta') ?><span>E-mail</span></div>
<div class="cab-pag">
  <div><h1>E-mail</h1><p>O envio pelo Amazon SES e o que voltou: devoluções e reclamações avisadas pela Amazon, com o bloqueio de cada endereço.</p></div>
</div>

<div class="metricas metricas-email">
  <div class="metrica"><span class="rot">Envio</span><span class="val"><?= Email::configurado() ? 'Ligado' : 'Desligado' ?></span><span class="nota"><?= e((string) ($cfg['smtp_host'] ?? '')) ?></span></div>
  <div class="metrica"><span class="rot">Devoluções</span><span class="val"><?= $devolvidos ?></span><span class="nota">em 30 dias</span></div>
  <div class="metrica"><span class="rot">Reclamações</span><span class="val"><?= $reclamacoes ?></span><span class="nota">marcado como spam, em 30 dias</span></div>
  <div class="metrica"><span class="rot">Bloqueados</span><span class="val"><?= $bloqueados ?></span><span class="nota">endereços que não recebem agora</span></div>
</div>

<div class="cartao cartao-teste-email">
  <h2 class="titulo-secao">Mensagem de teste</h2>
  <form method="post" action="/configuracoes/email" class="linha-teste">
    <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="teste">
    <div class="entrada"><?= icone('emails') ?><input type="email" name="para" required value="<?= e((string) $u['email']) ?>" aria-label="Enviar o teste para"></div>
    <button type="submit" class="botao claro"><?= icone('enviar') ?>Enviar teste</button>
  </form>
  <p class="ajuda">Sai de <b><?= e($remetente ?: 'remetente não configurado') ?></b>. Os avisos de devolução e reclamação chegam pelo tópico <span class="dado"><?= e(SesAvisos::topico() ?: 'não configurado') ?></span>.</p>
</div>

<form class="filtros ev-filtros" method="get" action="/configuracoes/email">
  <?php if ($situacao !== ''): ?><input type="hidden" name="situacao" value="<?= e($situacao) ?>"><?php endif; ?>
  <div class="ev-filtros-linha">
    <div class="entrada ev-busca"><?= icone('busca') ?><input type="search" name="q" value="<?= e($busca) ?>" placeholder="E-mail ou motivo" aria-label="Buscar e-mails"></div>
    <div class="entrada estreita"><?= icone('etiqueta') ?><select name="tipo" aria-label="Tipo" data-enviar-ao-mudar>
      <option value="">Tipos</option>
      <option value="devolucao"<?= $tipo === 'devolucao' ? ' selected' : '' ?>>Devoluções</option>
      <option value="reclamacao"<?= $tipo === 'reclamacao' ? ' selected' : '' ?>>Reclamações</option>
    </select></div>
  </div>
  <div class="pilulas-filtro a-direita">
    <?php foreach (['' => [$nTodas, 'Todas'], 'bloqueados' => [$nBloq, 'Bloqueados'], 'temporarias' => [$nTemp, 'Temporárias'], 'liberados' => [$nLib, 'Liberados']] as $chave => [$num, $rotulo]): ?>
      <a class="pf<?= $situacao === $chave ? ' ativa' : '' ?>" href="<?= e($comFiltro(['situacao' => $chave, 'p' => null])) ?>"><?= $rotulo ?><span><?= $num ?></span></a>
    <?php endforeach; ?>
  </div>
</form>

<div id="lista-viva">
  <?php $lista(); ?>
</div>
<?php paginaFim();
