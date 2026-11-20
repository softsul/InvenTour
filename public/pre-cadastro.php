<?php
/**
 * Pré-cadastro do inventário pelo Cadastur.
 * Softsul Software
 *
 * Mostra os prestadores do Cadastur no município do destino, por atividade,
 * com a ficha do MTur em que cada atividade entra e quantos já estão no
 * inventário. Marcadas as atividades, cria um item em pré-cadastro para cada
 * prestador que ainda não está lá (nome, razão social, CNPJ, certificado e
 * validade, endereço, contato, UH e leitos da hospedagem). O pesquisador
 * confere em campo: é a pesquisa de laboratório do manual do MTur.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Inventario.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('inventario.criar');
$pdo = Database::conexao();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        avisar('A página ficou aberta tempo demais. Tente de novo.', 'ruim');
        redirecionar('/pre-cadastro');
    }
    $n = Inventario::preCadastrarCadastur((array) ($_POST['atividades'] ?? []));
    avisar($n ? plural($n, 'prestador entrou', 'prestadores entraram') . ' no inventário em pré-cadastro, para a conferência em campo.'
              : 'Nenhum prestador novo: os das atividades escolhidas já estão no inventário.');
    redirecionar($n ? '/inventario?situacao=pre_cadastro' : '/pre-cadastro');
}

$resumo = Inventario::resumoCadastur((int) $empresa['id']);
$fichas = $pdo->query('SELECT ordem, codigo, nome FROM inventario_fichas')->fetchAll(PDO::FETCH_UNIQUE);
$faltam = array_sum(array_map(fn ($r) => (int) $r['total'] - (int) $r['ja'], $resumo));
paginaInicio('Pré-cadastro pelo Cadastur', 'inventario');
?>
<div class="trilha"><a href="/inventario">Inventário</a><?= icone('seta') ?><span>Pré-cadastro pelo Cadastur</span></div>
<div class="cab-pag">
  <div>
    <h1>Pré-cadastro pelo Cadastur</h1>
    <p>Os prestadores do Cadastur em <?= e((string) ($empresa['cidade'] ?: $empresa['nome_fantasia'])) ?> entram no inventário em pré-cadastro, cada um na ficha do Ministério do Turismo da sua atividade, com o que a base oficial já tem. O pesquisador confere e completa em campo.</p>
  </div>
</div>

<?php if (!$resumo): ?>
  <div class="vazio"><?= icone('certificado') ?><p><strong>O Cadastur não tem prestadores neste município.</strong></p>
    <p>Confira o município sede nos dados do destino.</p></div>
<?php else: ?>
<form method="post" action="/pre-cadastro" class="cartao">
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <div class="rolagem">
    <table class="dados">
      <thead><tr><th></th><th>Atividade no Cadastur</th><th>Ficha do MTur</th><th class="num">No Cadastur</th><th class="num">Já no inventário</th><th class="num">Novos</th></tr></thead>
      <tbody>
      <?php foreach ($resumo as $r): $ordem = Inventario::CADASTUR_FICHAS[$r['atividade']] ?? null; $f = $ordem ? ($fichas[$ordem] ?? null) : null;
        $novos = (int) $r['total'] - (int) $r['ja']; ?>
        <tr>
          <td><?php if ($f && $novos > 0): ?><label class="check"><input type="checkbox" name="atividades[]" value="<?= e((string) $r['atividade']) ?>" checked aria-label="<?= e((string) $r['atividade']) ?>"><i></i></label><?php endif; ?></td>
          <td class="elastica"><?= e((string) $r['atividade']) ?></td>
          <td><?= $f ? '<span class="dado">' . e((string) $f['codigo']) . '</span> ' . e((string) $f['nome']) : '<span class="nulo">sem ficha</span>' ?></td>
          <td class="num dado"><?= (int) $r['total'] ?></td>
          <td class="num dado"><?= (int) $r['ja'] ?></td>
          <td class="num dado"><?= $novos > 0 ? '<b>' . $novos . '</b>' : '0' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="ajuda">Guia de turismo pessoa física entra só com o nome: o CPF não vai para o inventário. Quem já está no inventário (pelo registro do Cadastur ou pelo CNPJ) não é duplicado.</p>
  <?php if ($faltam > 0): ?>
    <div class="acoes"><button type="submit" class="botao" data-confirmar="Criar os itens em pré-cadastro para as atividades marcadas?"><?= icone('mais') ?>Pré-cadastrar <?= plural($faltam, 'prestador', 'prestadores') ?></button></div>
  <?php else: ?>
    <p class="aviso info"><?= icone('check') ?><span>Todos os prestadores do Cadastur deste município já estão no inventário.</span></p>
  <?php endif; ?>
</form>
<?php endif; ?>
<?php paginaFim();
