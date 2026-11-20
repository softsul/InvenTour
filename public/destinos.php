<?php
/**
 * Destinos da plataforma (municípios, regiões turísticas e corporações que
 * usam o InvenTour). Só a equipe Softsul vê esta tela.
 * Softsul Software
 *
 * No molde da lista de clientes do Orbit: busca que filtra enquanto se digita
 * (a mesma função desenha a página e a resposta parcial), ordem pelo
 * cabeçalho, pílulas de situação com contagem e paginação da casa.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Foto.php';

// Destinos é da visão da plataforma: de dentro de um destino, a tela
// pergunta antes de trocar (exigirVisaoPlataforma).
exigirVisaoPlataforma();
Auth::exigirPermissao('destinos.ver');
if (!Auth::ehSoftsul()) {
    negarRegistro();
}
$pdo = Database::conexao();

const TIPOS_DE_DESTINO = ['municipio' => 'Município', 'regiao' => 'Região turística', 'corporativo' => 'Corporativo'];

// Ações da linha: inativar e reativar, sem sair da lista.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alvoId = (int) ($_POST['id'] ?? 0);
    $acao = (string) ($_POST['acao'] ?? '');
    $volta = destinoSeguro($_POST['volta'] ?? null, '/destinos');
    $q = $pdo->prepare('SELECT * FROM empresas WHERE id = ?');
    $q->execute([$alvoId]);
    $alvo = $q->fetch();
    if (Auth::conferirCsrf($_POST['csrf'] ?? null) && $alvo && Auth::pode('destinos.excluir') && in_array($acao, ['inativar', 'ativar'], true)) {
        $ativo = $acao === 'ativar' ? 1 : 0;
        // Inativar sobe a versao_sessao de todo mundo vinculado: derruba na
        // hora quem está dentro do destino.
        $ativo ? Empresa::ativar($alvoId) : Empresa::inativar($alvoId);
        Auditoria::registrar('alterou', 'empresas', $alvoId, ($ativo ? 'reativou' : 'inativou') . ' o destino ' . $alvo['nome_fantasia'], null, null, $alvoId);
        avisar($ativo ? $alvo['nome_fantasia'] . ' reativado.' : $alvo['nome_fantasia'] . ' inativado. A equipe dele perdeu o acesso e o portal sai do ar.');
    }
    redirecionar($volta);
}

$busca = trim((string) ($_GET['q'] ?? ''));
$situacao = in_array($_GET['situacao'] ?? '', ['inativos', 'todos'], true) ? $_GET['situacao'] : 'ativos';
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = Auth::porPagina();
$parcial = !empty($_GET['parcial']);

$ordem = Lista::ordem([
    'nome'     => 'e.nome_fantasia',
    'tipo'     => ['e.tipo', 'e.nome_fantasia'],
    'cidade'   => ['e.cidade', 'e.uf'],
    'slug'     => 'e.slug',
    'pessoas'  => 'pessoas',
    'situacao' => ['e.ativo', 'e.nome_fantasia'],
], 'nome');

$onde = [];
$args = [];
if ($situacao === 'ativos') {
    $onde[] = 'e.ativo = 1';
} elseif ($situacao === 'inativos') {
    $onde[] = 'e.ativo = 0';
}
if ($busca !== '') {
    $como = '%' . $busca . '%';
    $cnpj = preg_replace('/[^0-9A-Z]/', '', strtoupper($busca));
    $onde[] = '(e.nome LIKE ? OR e.nome_fantasia LIKE ? OR e.cidade LIKE ? OR e.slug LIKE ? OR e.dominio_proprio LIKE ?' . ($cnpj !== '' ? ' OR e.cnpj LIKE ?' : '') . ')';
    array_push($args, $como, $como, $como, $como, $como);
    if ($cnpj !== '') {
        $args[] = $cnpj . '%';
    }
}
$where = $onde ? 'WHERE ' . implode(' AND ', $onde) : '';
$t = $pdo->prepare("SELECT COUNT(*) FROM empresas e {$where}");
$t->execute($args);
$total = (int) $t->fetchColumn();
$q = $pdo->prepare(
    "SELECT e.*, (SELECT COUNT(*) FROM usuario_empresa ue WHERE ue.empresa_id = e.id AND ue.ativo = 1) AS pessoas
     FROM empresas e {$where} ORDER BY {$ordem['sql']} LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina)
);
$q->execute($args);
$destinos = $q->fetchAll();
$contas = $pdo->query('SELECT SUM(ativo = 1) AS ativos, SUM(ativo = 0) AS inativos FROM empresas')->fetch();
$cfg = require __DIR__ . '/../app/config.php';
$dominioPortal = (string) ($cfg['app']['dominio_portal'] ?? 'inventour.com.br');

$comFiltro = function (array $troca) use ($busca, $situacao, $ordem): string {
    $q = array_filter([
        'q'        => $troca['q'] ?? $busca,
        'situacao' => $troca['situacao'] ?? ($situacao !== 'ativos' ? $situacao : ''),
        'ord'      => $troca['ord'] ?? ($ordem['chave'] !== 'nome' ? $ordem['chave'] : ''),
        'dir'      => $troca['dir'] ?? ($ordem['chave'] !== 'nome' || $ordem['dir'] !== 'asc' ? $ordem['dir'] : ''),
        'p'        => $troca['p'] ?? null,
    ], fn ($v) => $v !== null && $v !== '');
    return '/destinos' . ($q ? '?' . http_build_query($q) : '');
};

$lista = function () use ($destinos, $busca, $total, $pagina, $situacao, $ordem, $comFiltro, $dominioPortal) {
    if (!$destinos): ?>
      <div class="vazio">
        <?= icone('predio') ?>
        <p><strong>Nada encontrado<?= $busca !== '' ? ' para "' . e($busca) . '"' : '' ?>.</strong></p>
        <p>Ajuste a busca ou <a href="/destinos">volte para a lista completa</a>.</p>
      </div>
    <?php else:
      $csrf = Auth::tokenCsrf();
      $volta = preg_replace('/([?&])parcial=1&?/', '$1', $_SERVER['REQUEST_URI'] ?? '/destinos'); ?>
      <div class="rolagem">
        <table class="dados">
          <thead><tr>
            <?= Lista::th('Destino', 'nome', $ordem, $comFiltro) ?>
            <?= Lista::th('Tipo', 'tipo', $ordem, $comFiltro) ?>
            <?= Lista::th('Município', 'cidade', $ordem, $comFiltro) ?>
            <?= Lista::th('Portal', 'slug', $ordem, $comFiltro) ?>
            <?= Lista::th('Pessoas', 'pessoas', $ordem, $comFiltro, 'num') ?>
            <?= Lista::th('Situação', 'situacao', $ordem, $comFiltro) ?>
            <th class="acao"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($destinos as $d): $url = endereco('destino', (int) $d['id'], $d['nome_fantasia']);
            $portal = $d['dominio_proprio'] ?: $d['slug'] . '.' . $dominioPortal; ?>
            <tr class="<?= $d['ativo'] ? '' : 'inativa' ?>">
              <td class="elastica"><span class="cli"><?= Foto::avatarDestino($d, 'avatar') ?>
                <span class="txt"><b><a href="<?= e($url) ?>" data-ficha="<?= e($d['nome_fantasia']) ?>"><?= e($d['nome_fantasia']) ?></a></b><span><?= e($d['nome']) ?></span></span></span></td>
              <td><?= e(TIPOS_DE_DESTINO[$d['tipo']] ?? $d['tipo']) ?></td>
              <td><?= $d['cidade'] ? e($d['cidade']) . ' <span class="dado">' . e((string) $d['uf']) . '</span>' : '<span class="nulo">-</span>' ?></td>
              <td class="dado"><a href="https://<?= e($portal) ?>" target="_blank" rel="noopener"><?= e($portal) ?></a></td>
              <td class="num dado"><?= (int) $d['pessoas'] ?></td>
              <td><?= $d['ativo'] ? '<span class="pilula ok">Ativo</span>' : '<span class="pilula ruim">Inativo</span>' ?></td>
              <td class="acao">
                <div class="acoes-linha">
                  <a href="<?= e($url) ?>" data-ficha="<?= e($d['nome_fantasia']) ?>" title="Alterar" aria-label="Alterar <?= e($d['nome_fantasia']) ?>"><?= icone('lapis') ?></a>
                  <?php if (Auth::pode('usuarios.ver') && $d['ativo']): ?>
                    <form method="post" action="/trocar-empresa">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="destino" value="/configuracoes/usuarios">
                      <button type="submit" name="empresa_id" value="<?= (int) $d['id'] ?>" title="Usuários do destino" aria-label="Usuários de <?= e($d['nome_fantasia']) ?>"><?= icone('pessoas') ?></button>
                    </form>
                  <?php endif; ?>
                  <?php if (Auth::pode('destinos.excluir')): ?>
                    <form method="post" action="/destinos">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><input type="hidden" name="volta" value="<?= e($volta) ?>">
                      <button type="submit" name="acao" value="<?= $d['ativo'] ? 'inativar' : 'ativar' ?>" title="<?= $d['ativo'] ? 'Inativar o destino' : 'Reativar o destino' ?>"
                              <?= $d['ativo'] ? 'data-confirmar="Inativar ' . e($d['nome_fantasia']) . '? A equipe dele perde o acesso na hora e o portal sai do ar. O histórico fica preservado."' : '' ?>><?= icone($d['ativo'] ? 'sair' : 'check') ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
      echo paginacao($total, $pagina, '/destinos', array_filter(['q' => $busca, 'situacao' => $situacao !== 'ativos' ? $situacao : '']));
    endif;
};

if ($parcial) {
    $lista();
    exit;
}

paginaInicio('Destinos', 'destinos');
?>
<div class="cab-pag">
  <div>
    <h1>Destinos</h1>
    <p>Os destinos que usam o InvenTour. Cada um tem equipe, inventário, portal público e configurações próprios.</p>
  </div>
  <?php if (Auth::ehSoftsul() && Auth::pode('destinos.criar')): ?>
    <div class="acoes"><a class="botao" href="<?= e(endereco('destino', null)) ?>" data-ficha="Novo destino" data-abre-novo><?= icone('predio') ?>Novo destino</a></div>
  <?php endif; ?>
</div>

<form class="filtros" method="get" action="/destinos">
  <div class="entrada">
    <?= icone('busca') ?>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome, CNPJ, cidade ou endereço do portal" aria-label="Buscar destinos">
  </div>
  <div class="pilulas-filtro a-direita">
    <?php foreach (['ativos' => 'Ativos', 'inativos' => 'Inativos', 'todos' => 'Todos'] as $chave => $rotulo):
      $conta = ['ativos' => (int) $contas['ativos'], 'inativos' => (int) $contas['inativos'],
                'todos' => (int) $contas['ativos'] + (int) $contas['inativos']][$chave]; ?>
      <a class="pf<?= $situacao === $chave ? ' ativa' : '' ?>" href="<?= e($comFiltro(['situacao' => $chave === 'ativos' ? '' : $chave, 'p' => null])) ?>"><?= $rotulo ?><span><?= $conta ?></span></a>
    <?php endforeach; ?>
  </div>
</form>

<div id="lista-viva">
  <?php $lista(); ?>
</div>
<?php paginaFim();
