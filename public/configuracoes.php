<?php
/**
 * Configurações do destino, em cartões agrupados, no molde do Orbit.
 * Softsul Software
 *
 * Cada cartão leva à página do ajuste. A lista mora em
 * app/ConfiguracoesCatalogo.php, que também abastece a busca do topo.
 * Dentro de um destino, tudo é dele. Na visão da plataforma, os cartões são
 * os da equipe Softsul (usuários, auditoria de todos os destinos, destinos e
 * catálogos). Usuários e Auditoria moram só aqui, fora do menu (pedido do
 * Luiz).
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/ConfiguracoesCatalogo.php';

Auth::exigirLogin();
$empresa = Empresa::atual();
Auth::exigirPermissao('configuracoes.ver');

$porGrupo = [];
foreach (ConfiguracoesCatalogo::cartoes($empresa ? (int) $empresa['id'] : 0) as $t) {
    $porGrupo[$t['grupo']][] = $t;
}
$ordem = ConfiguracoesCatalogo::GRUPOS;
uksort($porGrupo, fn ($a, $b) => array_search($a, $ordem, true) <=> array_search($b, $ordem, true));

paginaInicio('Configurações', 'configuracoes');
?>
<div class="cab-pag">
  <div><h1>Configurações</h1><p><?= $empresa ? 'Os ajustes de ' . e($empresa['nome_fantasia']) . '.' : 'A equipe Softsul, a auditoria de todos os destinos e os cadastros da plataforma.' ?></p></div>
</div>

<?php if ($empresa): ?>
<p class="aviso info"><?= icone('escudo') ?><span>Estes ajustes valem para o destino inteiro. Só quem gere o destino pode alterá-los.
O segundo fator da equipe está <b><?= $empresa['exigir_2fa_equipe'] ? 'obrigatório' : 'opcional' ?></b>; para mudar, use Dados do destino.</span></p>
<?php endif; ?>

<?php foreach ($porGrupo as $grupo => $cartoes): ?>
  <h2 class="titulo-grupo-config"><?= e((string) $grupo) ?></h2>
  <div class="cartoes-config">
    <?php foreach ($cartoes as $t): ?>
      <?php if ($t['pronto']): ?>
        <a class="config" href="<?= e($t['endereco']) ?>" data-titulo="<?= e($t['nome']) ?>">
      <?php else: ?>
        <div class="config futura">
      <?php endif; ?>
        <span class="marca-config"><?= icone((string) $t['icone']) ?></span>
        <div class="corpo-config">
          <b><?= e($t['nome']) ?></b>
          <p><?= e($t['descricao']) ?></p>
          <?php if (!empty($t['nota'])): ?><span class="nota-config"><?= e($t['nota']) ?></span><?php endif; ?>
        </div>
        <?php if ($t['pronto']): ?>
          <span class="conta-config"><b><?= (int) ($t['total'])() ?></b><span><?= e($t['unidade']) ?></span></span>
        <?php else: ?>
          <span class="conta-config"><span><?= e($t['unidade']) ?></span></span>
        <?php endif; ?>
      <?= $t['pronto'] ? '</a>' : '</div>' ?>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
<?php paginaFim();
