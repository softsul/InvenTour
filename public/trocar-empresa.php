<?php
/**
 * Troca do destino em que a pessoa está trabalhando. O seletor do topo envia
 * para cá; a tela própria serve a quem chega por um link ou precisa escolher.
 * Softsul Software
 *
 * O acesso ao destino é conferido no servidor (Empresa::trocar), nunca pela
 * lista que a tela mostra.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Foto.php';

$u = Auth::exigirLogin();
$destino = destinoSeguro($_GET['destino'] ?? $_POST['destino'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        avisar('A página ficou aberta tempo demais. Tente de novo.', 'ruim');
    } elseif (Empresa::trocar((int) $u['id'], (int) ($_POST['empresa_id'] ?? -1), empty($_POST['manter_padrao']))) {
        redirecionar($destino);
    } else {
        avisar('Você não tem acesso a este destino.', 'ruim');
    }
    redirecionar('/trocar-empresa');
}

$lista = Empresa::disponiveis((int) $u['id']);
$atual = Empresa::id();
paginaInicio('Trocar de destino');
?>
<div class="cab-pag"><div><h1>Em qual destino você vai trabalhar?</h1>
  <p>A escolha fica guardada para a próxima entrada.</p></div></div>

<form method="post" action="/trocar-empresa" class="lista-escolha">
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <input type="hidden" name="destino" value="<?= e($destino) ?>">
  <?php foreach ($lista as $d): ?>
    <button type="submit" name="empresa_id" value="<?= (int) $d['id'] ?>" class="escolha<?= (int) $d['id'] === $atual ? ' atual' : '' ?>">
      <?= Foto::avatarDestino($d, 'avatar') ?><span><b><?= e($d['nome_fantasia']) ?></b>
      <small><?= e(trim(($d['cidade'] ?? '') . (!empty($d['uf']) ? '/' . $d['uf'] : '') . (!empty($d['papel_nome']) ? ' · ' . $d['papel_nome'] : ''), ' /·')) ?></small></span>
    </button>
  <?php endforeach; ?>
  <?php if ($u['softsul']): ?>
    <button type="submit" name="empresa_id" value="0" class="escolha<?= $atual === 0 ? ' atual' : '' ?>">
      <?= icone('painel') ?><span><b>Plataforma InvenTour</b><small>Todos os destinos, sem entrar em nenhum</small></span>
    </button>
  <?php endif; ?>
</form>
<?php paginaFim();
