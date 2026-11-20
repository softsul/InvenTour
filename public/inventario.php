<?php
/**
 * Inventário do destino: atrativos, equipamentos e serviços, por ficha do MTur.
 * Softsul Software
 *
 * No molde das listas da casa: busca que filtra enquanto se digita (a mesma
 * função desenha a página e a resposta parcial), filtros de múltipla escolha
 * para situação e categoria, ficha em combo, ordem pelo cabeçalho,
 * paginação e o item em modal. O item novo começa pela escolha da ficha
 * (item.php?ficha=), porque é ela que decide os campos.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Inventario.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('inventario.ver');
$pdo = Database::conexao();

$busca = trim((string) ($_GET['q'] ?? ''));
$situacoes = Lista::escolhas('situacao', array_keys(Inventario::SITUACOES));
$categorias = Lista::escolhas('categoria', array_keys(Inventario::CATEGORIAS));
$fichaId = (int) ($_GET['ficha'] ?? 0);
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = Auth::porPagina();
$parcial = !empty($_GET['parcial']);

$ordem = Lista::ordem([
    'nome'      => 'i.nome',
    'ficha'     => ['f.ordem', 'i.nome'],
    'bairro'    => ['i.bairro', 'i.nome'],
    'situacao'  => ['i.situacao', 'i.nome'],
    'auditoria' => ['i.ultima_auditoria_em', 'i.nome'],
], 'nome');

[$filtroEmpresa, $argsEmpresa] = Empresa::filtro('i');
$onde = ['1 = 1' . $filtroEmpresa];
$args = $argsEmpresa;
if ($busca !== '') {
    [$sqlBusca, $argsBusca] = Lista::porPalavras($busca, ['i.nome', 'i.nome_popular', 'i.razao_social', 'i.bairro', 'i.logradouro', 't.nome']);
    $onde[] = $sqlBusca;
    array_push($args, ...$argsBusca);
    $digitos = preg_replace('/[^0-9A-Z]/', '', strtoupper($busca));
    if (strlen($digitos) >= 8) {
        $onde[count($onde) - 1] = '(' . $sqlBusca . ' OR i.cnpj LIKE ?)';
        $args[] = $digitos . '%';
    }
}
// Arquivados só aparecem quando pedidos.
if ($situacoes) {
    $onde[] = 'i.situacao IN (' . implode(',', array_fill(0, count($situacoes), '?')) . ')';
    array_push($args, ...$situacoes);
} else {
    $onde[] = "i.situacao <> 'arquivada'";
}
if ($categorias) {
    $onde[] = 'f.categoria IN (' . implode(',', array_fill(0, count($categorias), '?')) . ')';
    array_push($args, ...$categorias);
}
if ($fichaId) {
    $onde[] = 'i.ficha_id = ?';
    $args[] = $fichaId;
}
$where = 'WHERE ' . implode(' AND ', $onde);
$de = 'FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id';
$t = $pdo->prepare("SELECT COUNT(*) {$de} {$where}");
$t->execute($args);
$total = (int) $t->fetchColumn();
$q = $pdo->prepare("SELECT i.id, i.nome, i.bairro, i.zona, i.situacao, i.ultima_auditoria_em, i.cadastur, f.codigo, f.nome AS ficha_nome, f.categoria,
                           t.nome AS tipo_nome,
                           (SELECT m.arquivo FROM inventario_midias m WHERE m.item_id = i.id AND m.experiencia_id IS NULL ORDER BY m.capa DESC, m.ordem LIMIT 1) AS foto
                    {$de} {$where} ORDER BY {$ordem['sql']} LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina));
$q->execute($args);
$itens = $q->fetchAll();
$fichas = $pdo->query('SELECT id, codigo, nome FROM inventario_fichas WHERE ativo = 1 ORDER BY ordem')->fetchAll();
$podeCriar = Auth::pode('inventario.criar');
$eid = (int) $empresa['id'];

$parametros = fn (array $troca = []): array => array_filter([
    'q'         => $troca['q'] ?? $busca,
    'situacao'  => $troca['situacao'] ?? implode('|', $situacoes),
    'categoria' => $troca['categoria'] ?? implode('|', $categorias),
    'ficha'     => $troca['ficha'] ?? ($fichaId ?: ''),
    'ord'       => $troca['ord'] ?? ($ordem['chave'] !== 'nome' ? $ordem['chave'] : ''),
    'dir'       => $troca['dir'] ?? ($ordem['chave'] !== 'nome' || $ordem['dir'] !== 'asc' ? $ordem['dir'] : ''),
    'p'         => $troca['p'] ?? null,
], fn ($v) => $v !== null && $v !== '');
$comFiltro = fn (array $troca): string => '/inventario' . (($p = $parametros($troca)) ? '?' . http_build_query($p) : '');
$comFiltroAtivo = $busca !== '' || $situacoes || $categorias || $fichaId;

$lista = function () use ($itens, $busca, $total, $pagina, $ordem, $comFiltro, $parametros, $comFiltroAtivo, $eid) {
    if (!$itens): ?>
      <div class="vazio">
        <?= icone('mapa') ?>
        <p><strong>Nenhum item<?= $busca !== '' ? ' para "' . e($busca) . '"' : '' ?>.</strong></p>
        <p><?= $comFiltroAtivo ? 'Ajuste os filtros ou <a href="/inventario">volte para a lista completa</a>.'
            : 'Comece pelo pré-cadastro do Cadastur ou cadastre o primeiro atrativo, equipamento ou serviço.' ?></p>
      </div>
    <?php return; endif; ?>
      <div class="rolagem">
        <table class="dados">
          <thead><tr>
            <?= Lista::th('Item', 'nome', $ordem, $comFiltro) ?>
            <?= Lista::th('Ficha', 'ficha', $ordem, $comFiltro) ?>
            <?= Lista::th('Bairro', 'bairro', $ordem, $comFiltro) ?>
            <?= Lista::th('Situação', 'situacao', $ordem, $comFiltro) ?>
            <?= Lista::th('Última conferência', 'auditoria', $ordem, $comFiltro) ?>
            <th class="acao"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($itens as $i): $url = endereco('item', (int) $i['id'], $i['nome']);
            [$rotulo, $tom] = Inventario::SITUACOES[$i['situacao']] ?? [$i['situacao'], 'nu']; ?>
            <tr class="<?= $i['situacao'] === 'arquivada' ? 'inativa' : '' ?>">
              <td class="elastica"><span class="cli">
                <?php if ($i['foto']): ?><img class="avatar com-foto" src="<?= e(Inventario::urlFoto($eid, (string) $i['foto'], 'p')) ?>" alt="" loading="lazy"><?php else: ?><?= avatarIniciais((string) $i['nome'], 'avatar') ?><?php endif; ?>
                <span class="txt"><b><a href="<?= e($url) ?>" data-ficha="<?= e($i['nome']) ?>"><?= e($i['nome']) ?></a></b>
                <span><?= e((string) ($i['tipo_nome'] ?: $i['ficha_nome'])) ?></span></span></span></td>
              <td class="dado"><?= e((string) $i['codigo']) ?></td>
              <td><?= $i['bairro'] ? e((string) $i['bairro']) : '<span class="nulo">-</span>' ?><?= $i['zona'] === 'rural' ? ' <span class="pilula at">Rural</span>' : '' ?></td>
              <td><span class="pilula <?= $tom ?>"><?= e($rotulo) ?></span></td>
              <td class="dado"><?= $i['ultima_auditoria_em'] ? e(dataBr((string) $i['ultima_auditoria_em'], 'd/m/Y')) : '<span class="nulo">nunca</span>' ?></td>
              <td class="acao"><div class="acoes-linha"><a href="<?= e($url) ?>" data-ficha="<?= e($i['nome']) ?>" title="Abrir" aria-label="Abrir <?= e($i['nome']) ?>"><?= icone('lapis') ?></a></div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php
    $p = $parametros();
    unset($p['p']);
    echo paginacao($total, $pagina, '/inventario', $p);
};

if ($parcial) {
    $lista();
    exit;
}

paginaInicio('Inventário', 'inventario');
?>
<div class="cab-pag">
  <div>
    <h1>Inventário</h1>
    <p>A oferta turística de <?= e($empresa['nome_fantasia']) ?> nas fichas do Ministério do Turismo: infraestrutura de apoio, serviços e equipamentos e atrativos.</p>
  </div>
  <?php if ($podeCriar): ?>
    <div class="acoes"><a class="botao claro" href="/pre-cadastro"><?= icone('certificado') ?>Pré-cadastro pelo Cadastur</a>
      <a class="botao" href="<?= e(endereco('item', null)) ?>" data-ficha="Novo item do inventário" data-abre-novo><?= icone('mapa') ?>Novo item</a></div>
  <?php endif; ?>
</div>

<form class="filtros" method="get" action="/inventario">
  <div class="entrada">
    <?= icone('busca') ?>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome, tipo, bairro, rua ou CNPJ" aria-label="Buscar no inventário">
  </div>
  <?= multiEscolhaFiltro(['campo' => 'categoria', 'icone' => 'etiqueta', 'rotulo' => 'Categoria', 'vazio' => 'Todas as categorias',
      'plural' => 'categorias', 'escolhidos' => $categorias,
      'opcoes' => ['A' => 'A. Infraestrutura de apoio', 'B' => 'B. Serviços e equipamentos', 'C' => 'C. Atrativos']]) ?>
  <?= multiEscolhaFiltro(['campo' => 'situacao', 'icone' => 'check', 'rotulo' => 'Situação', 'vazio' => 'Todas menos arquivadas',
      'plural' => 'situações', 'escolhidos' => $situacoes, 'opcoes' => array_map(fn ($s) => $s[0], Inventario::SITUACOES)]) ?>
  <div class="entrada"><?= icone('manual') ?>
    <select name="ficha" aria-label="Ficha">
      <option value="">Todas as fichas</option>
      <?php foreach ($fichas as $f): ?>
        <option value="<?= (int) $f['id'] ?>"<?= $fichaId === (int) $f['id'] ? ' selected' : '' ?>><?= e($f['codigo'] . ' ' . $f['nome']) ?></option>
      <?php endforeach; ?>
    </select></div>
</form>

<div id="lista-viva">
  <?php $lista(); ?>
</div>
<?php paginaFim();
