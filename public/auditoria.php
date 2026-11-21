<?php
/**
 * Auditoria: quem fez o quê, quando e de onde.
 * Softsul Software
 *
 * Dentro de um destino, só o que aconteceu naquele destino (a.empresa_id na
 * consulta). Na visão da plataforma, a equipe Softsul vê tudo. A tabela é
 * particionada por ano, por isso toda consulta ordena por id e filtra por
 * empresa_id, que são as chaves indexadas.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

Auth::exigirPermissao('auditoria.ver');
$pdo = Database::conexao();
$empresa = Empresa::id();
$busca = trim((string) ($_GET['busca'] ?? ''));
// Filtros (Luiz, 21/11/2026): pessoa, período e, na visão da plataforma, destino.
$usuarioF = (int) ($_GET['usuario'] ?? 0);
$de = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['de'] ?? '')) ? (string) $_GET['de'] : '';
$ate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['ate'] ?? '')) ? (string) $_GET['ate'] : '';
$destinoF = (string) ($_GET['destino'] ?? '');
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = Auth::porPagina();

$onde = [];
$args = [];
if ($empresa) {
    $onde[] = 'a.empresa_id = ?';
    $args[] = $empresa;
} elseif (!Auth::ehSoftsul()) {
    redirecionar('/painel');
}
if ($busca !== '') {
    $onde[] = '(a.usuario_nome LIKE ? OR a.resumo LIKE ? OR a.acao LIKE ? OR a.ip LIKE ?)';
    array_push($args, ...array_fill(0, 4, '%' . $busca . '%'));
}
if ($usuarioF) {
    $onde[] = 'a.usuario_id = ?';
    $args[] = $usuarioF;
}
if ($de !== '') {
    $onde[] = 'a.criado_em >= ?';
    $args[] = $de . ' 00:00:00';
}
if ($ate !== '') {
    $onde[] = 'a.criado_em <= ?';
    $args[] = $ate . ' 23:59:59';
}
if (!$empresa && $destinoF !== '') {
    // "0" é o que aconteceu na visão da plataforma, fora de um destino.
    $onde[] = $destinoF === '0' ? '(a.empresa_id IS NULL OR a.empresa_id = 0)' : 'a.empresa_id = ?';
    if ($destinoF !== '0') {
        $args[] = (int) $destinoF;
    }
}
$where = $onde ? 'WHERE ' . implode(' AND ', $onde) : '';
// As pessoas que aparecem na auditoria (do destino, ou de todos na plataforma).
$q = $pdo->prepare('SELECT DISTINCT a.usuario_id, u.nome FROM auditoria a JOIN usuarios u ON u.id = a.usuario_id'
    . ($empresa ? ' WHERE a.empresa_id = ?' : '') . ' ORDER BY u.nome');
$q->execute($empresa ? [$empresa] : []);
$pessoas = $q->fetchAll(PDO::FETCH_KEY_PAIR);
$destinos = $empresa ? [] : $pdo->query('SELECT id, nome_fantasia FROM empresas ORDER BY nome_fantasia')->fetchAll(PDO::FETCH_KEY_PAIR);
$t = $pdo->prepare("SELECT COUNT(*) FROM auditoria a {$where}");
$t->execute($args);
$total = (int) $t->fetchColumn();
$q = $pdo->prepare(
    "SELECT a.*, e.nome_fantasia FROM auditoria a LEFT JOIN empresas e ON e.id = a.empresa_id
     {$where} ORDER BY a.id DESC LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina)
);
$q->execute($args);
$lista = $q->fetchAll();

$rotulos = ['entrou' => 'Entrou', 'saiu' => 'Saiu', 'criou' => 'Criou', 'alterou' => 'Alterou', 'excluiu' => 'Excluiu',
            'exportou' => 'Exportou', 'login_negado' => 'Login negado', 'acesso_negado' => 'Acesso negado',
            'trocou_empresa' => 'Trocou de destino'];

// A mesma lista serve a página inteira e a busca ao vivo (?parcial=1).
ob_start();
?>
<?php if (!$lista): ?>
  <div class="vazio"><?= icone('lista') ?><p><strong>Nada registrado.</strong></p></div>
<?php else: ?>
<div class="rolagem">
<table class="dados">
  <thead><tr><th>Quando</th><th>Quem</th><th>Ação</th><th>O quê</th><?php if (!$empresa): ?><th>Destino</th><?php endif; ?><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($lista as $a): $ruim = in_array($a['acao'], ['login_negado', 'acesso_negado'], true); ?>
    <tr>
      <td class="dado" title="<?= e(dataBr($a['criado_em'], 'd/m/Y H:i:s')) ?>"><?= e(dataBr($a['criado_em'], 'd/m/Y H:i')) ?></td>
      <td><?= $a['usuario_nome'] ? e($a['usuario_nome']) : '<span class="nulo">sem sessão</span>' ?></td>
      <td><span class="pilula <?= $ruim ? 'ruim' : ($a['acao'] === 'excluiu' ? 'at' : 'nu') ?>"><?= e($rotulos[$a['acao']] ?? $a['acao']) ?></span></td>
      <td class="elastica"><?= e($a['resumo'] ?? '') ?></td>
      <?php if (!$empresa): ?><td><?= e($a['nome_fantasia'] ?? '') ?></td><?php endif; ?>
      <td class="dado"><?= e($a['ip'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= paginacao($total, $pagina, '/configuracoes/auditoria', array_filter(['busca' => $busca, 'usuario' => $usuarioF ?: '', 'de' => $de, 'ate' => $ate, 'destino' => $destinoF], fn ($v) => $v !== '')) ?>
<?php endif; ?>
<?php
$htmlLista = ob_get_clean();
if (!empty($_GET['parcial'])) {
    echo $htmlLista;
    exit;
}
paginaInicio('Auditoria', 'configuracoes');
?>
<div class="trilha"><a href="/configuracoes">Configurações</a><?= icone('seta') ?><span>Auditoria</span></div>
<div class="cab-pag"><div><h1>Auditoria</h1>
  <p>Quem fez o quê, quando e de onde<?= $empresa ? ', em ' . e(Empresa::atual()['nome_fantasia']) : ', em todos os destinos' ?>.</p></div></div>
<form class="filtros" method="get" action="/configuracoes/auditoria">
  <div class="entrada"><?= icone('busca') ?><input type="search" name="busca" value="<?= e($busca) ?>" placeholder="Pessoa, ação, texto ou IP" aria-label="Buscar na auditoria" autocomplete="off"></div>
  <div class="entrada"><?= icone('pessoas') ?><select name="usuario" aria-label="Pessoa">
    <option value="">Todas as pessoas</option>
    <?php foreach ($pessoas as $uid => $nome): ?><option value="<?= (int) $uid ?>"<?= $usuarioF === (int) $uid ? ' selected' : '' ?>><?= e((string) $nome) ?></option><?php endforeach; ?>
  </select></div>
  <?php if (!$empresa): ?>
  <div class="entrada"><?= icone('predio') ?><select name="destino" aria-label="Destino">
    <option value="">Todos os destinos</option>
    <option value="0"<?= $destinoF === '0' ? ' selected' : '' ?>>Visão da plataforma</option>
    <?php foreach ($destinos as $did => $nome): ?><option value="<?= (int) $did ?>"<?= $destinoF === (string) $did ? ' selected' : '' ?>><?= e((string) $nome) ?></option><?php endforeach; ?>
  </select></div>
  <?php endif; ?>
  <div class="entrada"><?= icone('calendario') ?><input type="date" name="de" value="<?= e($de) ?>" aria-label="De" title="De" data-filtra-data></div>
  <div class="entrada"><?= icone('calendario') ?><input type="date" name="ate" value="<?= e($ate) ?>" aria-label="Até" title="Até" data-filtra-data></div>
</form>
<div id="lista-viva">
<?= $htmlLista ?>
</div>
<?php paginaFim();
