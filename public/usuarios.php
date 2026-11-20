<?php
/**
 * Usuários: as pessoas com acesso ao destino atual, ou a equipe Softsul na
 * visão da plataforma.
 * Softsul Software
 *
 * No molde das listas do Orbit: busca ao vivo, ordem pelo cabeçalho, pílulas
 * de filtro e paginação da casa. O escopo do destino vai na consulta
 * (ue.empresa_id = ?), nunca na tela.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Usuarios.php';
require_once __DIR__ . '/../app/Foto.php';
require_once __DIR__ . '/../app/Passkey.php';
require_once __DIR__ . '/../app/Certificado.php';

$eu = Auth::exigirPermissao('usuarios.ver');
$pdo = Database::conexao();
$empresa = Empresa::id();

// Ações da linha: reiniciar o segundo fator e desativar ou reativar o
// acesso, sem sair da lista. O CSRF é conferido antes de qualquer coisa.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alvoId = (int) ($_POST['id'] ?? 0);
    $alvo = $alvoId ? Usuarios::noContexto($alvoId) : null;
    $acao = (string) ($_POST['acao'] ?? '');
    $volta = destinoSeguro($_POST['volta'] ?? null, '/configuracoes/usuarios');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$alvo || !Auth::pode('usuarios.editar') || !Usuarios::podeMexer($alvo)) {
        avisar('Você não pode alterar este usuário.', 'ruim');
    } elseif ($acao === 'reiniciar_2fa') {
        $pdo->prepare('UPDATE usuarios SET duplo_fator_segredo = NULL, duplo_fator_confirmado = 0, duplo_fator_ultimo_periodo = NULL WHERE id = ?')->execute([$alvoId]);
        Passkey::removerTodos($alvoId);
        Certificado::removerTodos($alvoId);
        Auth::invalidarSessoes($alvoId);
        Auditoria::registrar('alterou', 'usuarios', $alvoId, 'reiniciou o segundo fator de ' . $alvo['nome']);
        avisar('Segundo fator de ' . $alvo['nome'] . ' reiniciado. Na próxima entrada ele cadastra de novo.');
    } elseif (in_array($acao, ['desativar', 'ativar'], true)) {
        $ativo = $acao === 'ativar' ? 1 : 0;
        if ($empresa) {
            // Dentro de um destino, o que se desativa é o vínculo: a conta
            // continua valendo nos outros destinos da pessoa.
            $pdo->prepare('UPDATE usuario_empresa SET ativo = ?, atualizado_em = NOW() WHERE usuario_id = ? AND empresa_id = ?')->execute([$ativo, $alvoId, $empresa]);
        } else {
            $pdo->prepare('UPDATE usuarios SET ativo = ?, atualizado_em = NOW() WHERE id = ?')->execute([$ativo, $alvoId]);
        }
        if (!$ativo) {
            Auth::invalidarSessoes($alvoId);
        }
        Auditoria::registrar('alterou', 'usuarios', $alvoId, ($ativo ? 'reativou' : 'desativou') . ' o acesso de ' . $alvo['nome']);
        avisar($ativo ? 'Acesso de ' . $alvo['nome'] . ' reativado.' : 'Acesso de ' . $alvo['nome'] . ' desativado. O histórico dele fica preservado.');
    }
    redirecionar($volta);
}
$busca = trim((string) ($_GET['q'] ?? ''));
$tipos = $empresa ? ['' => 'Todos', 'equipe' => 'Equipe do destino', 'prestadores' => 'Prestadores'] : ['' => 'Equipe Softsul', 'todos' => 'Todas as contas'];
$tipo = array_key_exists((string) ($_GET['tipo'] ?? ''), $tipos) ? (string) ($_GET['tipo'] ?? '') : '';
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = Auth::porPagina();
$parcial = !empty($_GET['parcial']);
$ordem = Lista::ordem(['nome' => 'u.nome', 'papel' => 'p.nome', 'fator' => ['u.duplo_fator_confirmado', 'u.nome'],
    'acesso' => ['u.ultimo_login IS NULL', 'u.ultimo_login'], 'situacao' => ['ativo', 'u.nome']], 'nome');

$args = [];
if ($empresa) {
    // Os papéis da pessoa no destino, todos juntos: o nome lista cada um, e
    // "prestador" marca quem só mantém a ficha do próprio negócio.
    $de = "FROM usuarios u JOIN usuario_empresa ue ON ue.usuario_id = u.id AND ue.empresa_id = ?
           JOIN (SELECT uep.usuario_id, GROUP_CONCAT(pp.nome ORDER BY pp.ordem SEPARATOR ', ') AS nome,
                        SUM(pp.chave <> 'prestador') > 0 AS equipe, SUM(pp.chave = 'prestador') > 0 AS prestador
                 FROM usuario_empresa_papel uep JOIN papeis pp ON pp.id = uep.papel_id
                 WHERE uep.empresa_id = ? GROUP BY uep.usuario_id) p ON p.usuario_id = u.id";
    array_push($args, $empresa, $empresa);
    $onde = $tipo === 'equipe' ? ['p.equipe = 1'] : ($tipo === 'prestadores' ? ['p.prestador = 1'] : []);
    $ativoSql = '(ue.ativo AND u.ativo)';
} else {
    $de = 'FROM usuarios u LEFT JOIN papeis p ON p.id = u.papel_softsul_id';
    $onde = $tipo === 'todos' ? [] : ['u.softsul = 1'];
    $ativoSql = 'u.ativo';
}
if ($busca !== '') {
    $digitos = soDigitos($busca);
    $onde[] = '(u.nome LIKE ? OR u.email LIKE ?' . ($digitos !== '' ? ' OR u.telefone LIKE ?' : '') . ')';
    array_push($args, '%' . $busca . '%', '%' . $busca . '%');
    if ($digitos !== '') {
        $args[] = '%' . $digitos . '%';
    }
}
$where = $onde ? 'WHERE ' . implode(' AND ', $onde) : '';
$t = $pdo->prepare("SELECT COUNT(*) {$de} {$where}");
$t->execute($args);
$total = (int) $t->fetchColumn();
$q = $pdo->prepare(
    "SELECT u.id, u.nome, u.email, u.telefone, u.foto, u.softsul, u.senha_hash IS NOT NULL AS tem_senha, u.duplo_fator_confirmado,
            u.ultimo_login, p.nome AS papel_nome, {$ativoSql} AS ativo,
            (SELECT COUNT(*) FROM credenciais_webauthn cw WHERE cw.usuario_id = u.id) AS aparelhos,
            (SELECT COUNT(*) FROM usuario_certificados uc WHERE uc.usuario_id = u.id) AS certificados
     {$de} {$where} ORDER BY ativo DESC, {$ordem['sql']} LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina)
);
$q->execute($args);
$pessoas = $q->fetchAll();

$comFiltro = function (array $troca) use ($busca, $tipo, $ordem): string {
    $q = array_filter([
        'q'    => $troca['q'] ?? $busca,
        'tipo' => $troca['tipo'] ?? $tipo,
        'ord'  => $troca['ord'] ?? ($ordem['chave'] !== 'nome' ? $ordem['chave'] : ''),
        'dir'  => $troca['dir'] ?? ($ordem['chave'] !== 'nome' || $ordem['dir'] !== 'asc' ? $ordem['dir'] : ''),
        'p'    => $troca['p'] ?? null,
    ], fn ($v) => $v !== null && $v !== '');
    return '/configuracoes/usuarios' . ($q ? '?' . http_build_query($q) : '');
};

$lista = function () use ($pessoas, $busca, $total, $pagina, $tipo, $ordem, $comFiltro, $eu, $empresa) {
    if (!$pessoas): ?>
      <div class="vazio">
        <?= icone('pessoas') ?>
        <p><strong>Ninguém encontrado<?= $busca !== '' ? ' para "' . e($busca) . '"' : '' ?>.</strong></p>
        <p>Ajuste a busca ou <a href="/configuracoes/usuarios">volte para a lista completa</a>.</p>
      </div>
    <?php else:
      $csrf = Auth::tokenCsrf();
      $volta = $_SERVER['REQUEST_URI'] ?? '/configuracoes/usuarios';
      $volta = preg_replace('/([?&])parcial=1&?/', '$1', $volta); ?>
      <div class="rolagem">
        <table class="dados">
          <thead><tr>
            <?= Lista::th('Pessoa', 'nome', $ordem, $comFiltro) ?>
            <?= Lista::th('Papel', 'papel', $ordem, $comFiltro) ?>
            <th>WhatsApp</th>
            <?= Lista::th('Segundo fator', 'fator', $ordem, $comFiltro) ?>
            <?= Lista::th('Último acesso', 'acesso', $ordem, $comFiltro) ?>
            <?= Lista::th('Situação', 'situacao', $ordem, $comFiltro) ?>
            <th class="acao"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($pessoas as $p):
            $url = endereco('usuario', (int) $p['id'], $p['nome']);
            $ctx = Usuarios::noContexto((int) $p['id']);
            $mexe = $ctx && Auth::pode('usuarios.editar') && Usuarios::podeMexer($ctx); ?>
            <tr class="<?= $p['ativo'] ? '' : 'inativa' ?>">
              <td class="elastica"><span class="cli"><?= Foto::avatar('usuario', (int) $p['id'], $p['foto'], $p['nome'], 'avatar') ?>
                <span class="txt"><b><a href="<?= e($url) ?>" data-ficha="<?= e($p['nome']) ?>"><?= e($p['nome']) ?></a></b><span><?= e($p['email']) ?></span></span></span></td>
              <td><?= e((string) $p['papel_nome']) ?></td>
              <td class="dado"><?= $p['telefone'] ? e(formatarTelefone($p['telefone'])) : '<span class="nulo">-</span>' ?></td>
              <td>
                <?php if (!$p['tem_senha']): ?><span class="pilula at">Convite pendente</span>
                <?php elseif ($p['duplo_fator_confirmado']): ?><span class="pilula ok">Ligado</span>
                <?php else: ?><span class="pilula at">Pendente</span><?php endif; ?>
                <?php if ((int) $p['aparelhos']): ?><span class="pilula ok"><?= (int) $p['aparelhos'] ?> digital<?= (int) $p['aparelhos'] > 1 ? 'is' : '' ?></span><?php endif; ?>
                <?php if ((int) $p['certificados']): ?><span class="pilula ok">certificado</span><?php endif; ?>
              </td>
              <td class="dado"><?= $p['ultimo_login'] ? e(dataBr($p['ultimo_login'])) : '<span class="nulo">nunca entrou</span>' ?></td>
              <td><?= $p['ativo'] ? '<span class="pilula ok">Ativo</span>' : '<span class="pilula ruim">Desativado</span>' ?></td>
              <td class="acao">
                <div class="acoes-linha">
                  <a href="<?= e($url) ?>" data-ficha="<?= e($p['nome']) ?>" title="Alterar" aria-label="Alterar <?= e($p['nome']) ?>"><?= icone('lapis') ?></a>
                  <?php if ($mexe && (int) $p['id'] !== (int) $eu['id']): ?>
                    <form method="post" action="<?= e($url) ?>" data-ficha="<?= e($p['nome']) ?>">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="acao" value="link">
                      <button type="submit" value="link" title="<?= $p['tem_senha'] ? 'Gerar link para trocar a senha' : 'Gerar convite novo' ?>"
                              aria-label="Senha de <?= e($p['nome']) ?>"><?= icone('cadeado') ?></button>
                    </form>
                    <form method="post" action="/configuracoes/usuarios">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <input type="hidden" name="volta" value="<?= e($volta) ?>">
                      <?php if ($p['duplo_fator_confirmado'] || (int) $p['aparelhos'] || (int) $p['certificados']): ?>
                        <button type="submit" name="acao" value="reiniciar_2fa" title="Reiniciar o segundo fator"
                                data-confirmar="Reiniciar o segundo fator de <?= e($p['nome']) ?>? O autenticador, as digitais e os certificados deixam de valer, e ele cadastra de novo na próxima entrada."><?= icone('escudo') ?></button>
                      <?php endif; ?>
                      <button type="submit" name="acao" value="<?= $p['ativo'] ? 'desativar' : 'ativar' ?>"
                              title="<?= $p['ativo'] ? 'Desativar o acesso' : 'Reativar o acesso' ?>"
                              <?= $p['ativo'] ? 'data-confirmar="Desativar o acesso de ' . e($p['nome']) . ($empresa ? ' a este destino' : '') . '? Ele deixa de entrar, e o histórico fica preservado."' : '' ?>><?= icone($p['ativo'] ? 'sair' : 'check') ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php echo paginacao($total, $pagina, '/configuracoes/usuarios', array_filter(['q' => $busca, 'tipo' => $tipo]));
    endif;
};

if ($parcial) {
    $lista();
    exit;
}

paginaInicio('Usuários', 'configuracoes');
?>
<div class="trilha"><a href="/configuracoes">Configurações</a><?= icone('seta') ?><span>Usuários</span></div>
<div class="cab-pag">
  <div>
    <h1>Usuários</h1>
    <p><?= $empresa ? 'Quem tem acesso a ' . e(Empresa::atual()['nome_fantasia']) . ': gestores, técnicos, pesquisadores, leitores e prestadores.' : 'A equipe Softsul, que alcança todos os destinos.' ?></p>
  </div>
  <?php if (Auth::pode('usuarios.criar') && Usuarios::papeisAtribuiveis()): ?>
    <div class="acoes"><a class="botao" href="/configuracoes/usuario" data-ficha="Novo usuário" data-abre-novo><?= icone('pessoas') ?>Novo usuário</a></div>
  <?php endif; ?>
</div>

<form class="filtros" method="get" action="/configuracoes/usuarios">
  <div class="entrada">
    <?= icone('busca') ?>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome, e-mail ou WhatsApp" aria-label="Buscar usuários">
  </div>
  <div class="pilulas-filtro a-direita">
    <?php foreach ($tipos as $chave => $rotulo): ?>
      <a class="pf<?= $tipo === $chave ? ' ativa' : '' ?>" href="<?= e($comFiltro(['tipo' => $chave, 'p' => null])) ?>"><?= e($rotulo) ?></a>
    <?php endforeach; ?>
  </div>
</form>

<div id="lista-viva">
  <?php $lista(); ?>
</div>
<?php paginaFim();
