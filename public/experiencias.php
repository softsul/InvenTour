<?php
/**
 * Experiências turísticas do destino: a manutenção de todas num lugar só.
 * Softsul Software
 *
 * Antes a experiência só abria pela aba Experiências de cada item. Aqui
 * ficam todas, com a busca viva (título, narrativa ou item), o filtro por
 * situação, a ordem pelo cabeçalho e a paginação, no molde do Inventário.
 * Quem faz a curadoria vê primeiro as que esperam por ela (o número do
 * menu). A experiência abre em modal (experiencia.php); a nova escolhe o
 * item no próprio formulário.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Inventario.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('experiencias.ver');
Auth::exigirModuloLigado('experiencias');
$pdo = Database::conexao();
$eid = (int) $empresa['id'];

$situacoesX = [
    'aguardando' => ['Aguardando curadoria', 'at'],
    'ajuste'     => ['Ajuste pedido', 'at'],
    'rascunho'   => ['Rascunho', 'nu'],
    'aprovada'   => ['Aprovada', 'ok'],
    'reprovada'  => ['Reprovada', 'ruim'],
];
$dimensoes4e = ['entretenimento' => 'Entretenimento', 'educacional' => 'Educacional', 'escapismo' => 'Escapismo', 'estetica' => 'Estética'];
$faixas = ['gratuita' => 'Gratuita', 'ate_80' => 'Até R$ 80', 'de_81_a_200' => 'R$ 81 a 200', 'de_201_a_500' => 'R$ 201 a 500', 'acima_500' => 'Acima de R$ 500'];

$busca = trim((string) ($_GET['q'] ?? ''));
$situacoes = Lista::escolhas('situacao', array_keys($situacoesX));
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = Auth::porPagina();
$parcial = !empty($_GET['parcial']);

// Sem coluna escolhida: o que pede ação primeiro (aguardando curadoria,
// ajuste pedido), e dentro de cada situação a mais recente.
$ordem = Lista::ordemOuPadrao([
    'situacao'   => ["FIELD(x.situacao, 'aguardando', 'ajuste', 'rascunho', 'aprovada', 'reprovada')", 'x.titulo'],
    'titulo'     => 'x.titulo',
    'item'       => ['i.nome', 'x.titulo'],
    'atualizada' => ['COALESCE(x.atualizado_em, x.criado_em)', 'x.titulo'],
], "FIELD(x.situacao, 'aguardando', 'ajuste', 'rascunho', 'aprovada', 'reprovada'), COALESCE(x.atualizado_em, x.criado_em) DESC");

$onde = ['x.empresa_id = ?'];
$args = [$eid];
if ($busca !== '') {
    [$sqlBusca, $argsBusca] = Lista::porPalavras($busca, ['x.titulo', 'x.narrativa', 'i.nome', 'i.nome_popular']);
    $onde[] = $sqlBusca;
    array_push($args, ...$argsBusca);
}
if ($situacoes) {
    $onde[] = 'x.situacao IN (' . implode(',', array_fill(0, count($situacoes), '?')) . ')';
    array_push($args, ...$situacoes);
}
$where = 'WHERE ' . implode(' AND ', $onde);
$de = 'FROM inventario_experiencias x JOIN inventario_itens i ON i.id = x.item_id JOIN inventario_fichas f ON f.id = i.ficha_id';
$t = $pdo->prepare("SELECT COUNT(*) {$de} {$where}");
$t->execute($args);
$total = (int) $t->fetchColumn();
$q = $pdo->prepare("SELECT x.id, x.titulo, x.situacao, x.dimensoes, x.faixa_valor, x.duracao_minutos, x.criado_em, x.atualizado_em,
                           i.id AS item_id, i.nome AS item_nome, f.codigo,
                           (SELECT m.arquivo FROM inventario_midias m WHERE m.experiencia_id = x.id ORDER BY m.ordem, m.id LIMIT 1) AS foto
                    {$de} {$where} ORDER BY {$ordem['sql']} LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina));
$q->execute($args);
$linhas = $q->fetchAll();
$podeCriar = Auth::pode('inventario.editar');

$parametros = fn (array $troca = []): array => array_filter([
    'q'        => $troca['q'] ?? $busca,
    'situacao' => $troca['situacao'] ?? implode('|', $situacoes),
    'ord'      => $troca['ord'] ?? $ordem['chave'],
    'dir'      => $troca['dir'] ?? ($ordem['chave'] !== '' ? $ordem['dir'] : ''),
    'p'        => $troca['p'] ?? null,
], fn ($v) => $v !== null && $v !== '');
$comFiltro = fn (array $troca): string => '/experiencias' . (($p = $parametros($troca)) ? '?' . http_build_query($p) : '');
$comFiltroAtivo = $busca !== '' || $situacoes;

$lista = function () use ($linhas, $busca, $total, $pagina, $ordem, $comFiltro, $parametros, $comFiltroAtivo, $eid, $situacoesX, $dimensoes4e, $faixas, $podeCriar) {
    if (!$linhas): ?>
      <div class="vazio">
        <?= icone('natureza') ?>
        <p><strong>Nenhuma experiência<?= $busca !== '' ? ' para "' . e($busca) . '"' : '' ?>.</strong></p>
        <p><?= $comFiltroAtivo ? 'Ajuste os filtros ou <a href="/experiencias">volte para a lista completa</a>.'
            : 'Vivências que os lugares do inventário oferecem: colheita, oficina, degustação, visita técnica.'
              . ($podeCriar ? ' Cadastre a primeira em Nova experiência.' : '') ?></p>
      </div>
    <?php return; endif; ?>
      <div class="rolagem">
        <table class="dados">
          <thead><tr>
            <?= Lista::th('Experiência', 'titulo', $ordem, $comFiltro) ?>
            <?= Lista::th('Item', 'item', $ordem, $comFiltro) ?>
            <th>4 Es</th>
            <th>Preço</th>
            <?= Lista::th('Situação', 'situacao', $ordem, $comFiltro) ?>
            <?= Lista::th('Atualizada', 'atualizada', $ordem, $comFiltro) ?>
            <th class="acao"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($linhas as $x): $url = endereco('experiencia', (int) $x['id'], (string) $x['titulo']);
            [$rotulo, $tom] = $situacoesX[$x['situacao']] ?? [$x['situacao'], 'nu'];
            $dims = array_intersect_key($dimensoes4e, json_decode((string) $x['dimensoes'], true) ?: []); ?>
            <tr>
              <td class="elastica"><span class="cli">
                <?php if ($x['foto']): ?><img class="avatar com-foto" src="<?= e(Inventario::urlFoto($eid, (string) $x['foto'], 'p')) ?>" alt="" loading="lazy"><?php else: ?><?= avatarIniciais((string) $x['titulo'], 'avatar') ?><?php endif; ?>
                <span class="txt"><b><a href="<?= e($url) ?>" data-ficha="<?= e($x['titulo']) ?>"><?= e((string) $x['titulo']) ?></a></b>
                <span><?= $x['duracao_minutos'] ? e(duracaoExperiencia((int) $x['duracao_minutos'])) : 'Duração a definir' ?></span></span></span></td>
              <td><a href="<?= e(endereco('item', (int) $x['item_id'], (string) $x['item_nome'])) ?>" data-ficha="<?= e($x['item_nome']) ?>"><?= e((string) $x['item_nome']) ?></a>
                <span class="dado"><?= e((string) $x['codigo']) ?></span></td>
              <td><?= $dims ? implode(' ', array_map(fn ($d) => '<span class="pilula nu">' . e($d) . '</span>', $dims)) : '<span class="nulo">-</span>' ?></td>
              <td><?= isset($faixas[$x['faixa_valor']]) ? e($faixas[$x['faixa_valor']]) : '<span class="nulo">-</span>' ?></td>
              <td><span class="pilula <?= $tom ?>"><?= e($rotulo) ?></span></td>
              <td class="dado"><?= e(dataBr((string) ($x['atualizado_em'] ?: $x['criado_em']), 'd/m/Y')) ?></td>
              <td class="acao"><div class="acoes-linha"><a href="<?= e($url) ?>" data-ficha="<?= e($x['titulo']) ?>" title="Abrir" aria-label="Abrir <?= e($x['titulo']) ?>"><?= icone('lapis') ?></a></div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php
    $p = $parametros();
    unset($p['p']);
    echo paginacao($total, $pagina, '/experiencias', $p);
};

/** "1 h 30 min", "45 min". */
function duracaoExperiencia(int $min): string
{
    $h = intdiv($min, 60);
    $m = $min % 60;
    return trim(($h ? $h . ' h ' : '') . ($m ? $m . ' min' : ''));
}

if ($parcial) {
    $lista();
    exit;
}

paginaInicio('Experiências', 'experiencias');
?>
<div class="cab-pag">
  <div>
    <h1>Experiências</h1>
    <p>As vivências que os lugares de <?= e($empresa['nome_fantasia']) ?> oferecem, nos 4 Es da economia da experiência. Só vão para o portal depois da curadoria.</p>
  </div>
  <?php if ($podeCriar): ?>
    <div class="acoes"><a class="botao" href="/experiencia" data-ficha="Nova experiência" data-abre-novo><?= icone('mais') ?>Nova experiência</a></div>
  <?php endif; ?>
</div>

<form class="filtros" method="get" action="/experiencias">
  <div class="entrada">
    <?= icone('busca') ?>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Título, narrativa ou item" aria-label="Buscar nas experiências">
  </div>
  <?= multiEscolhaFiltro(['campo' => 'situacao', 'icone' => 'check', 'rotulo' => 'Situação', 'vazio' => 'Todas as situações',
      'plural' => 'situações', 'escolhidos' => $situacoes, 'opcoes' => array_map(fn ($s) => $s[0], $situacoesX)]) ?>
</form>

<div id="lista-viva">
  <?php $lista(); ?>
</div>
<?php paginaFim();
