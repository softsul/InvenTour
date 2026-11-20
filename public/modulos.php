<?php
/**
 * Módulos que a plataforma liga e desliga para todos os destinos.
 * Softsul Software
 *
 * Só na visão da plataforma, para a equipe Softsul. Desligado, o módulo fica no
 * menu dos destinos, sem abrir; os dados dele continuam guardados.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

Auth::exigirLogin();
Auth::exigirPermissao('configuracoes.ver');
if (!Auth::ehSoftsul() || Empresa::id()) {
    paginaErro(403, 'Esta configuração é da visão da plataforma.', 'Troque para Plataforma InvenTour no seletor de destino.');
}
$podeEditar = Auth::pode('configuracoes.editar');
$pdo = Database::conexao();
$modulos = $pdo->query('SELECT id, chave, nome, icone, desligado FROM modulos WHERE pode_desligar = 1 ORDER BY grupo_ordem, ordem')->fetchAll();
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!$podeEditar) {
        $erro = 'Você não pode alterar os módulos.';
    } else {
        foreach ($modulos as $m) {
            $ligado = !empty($_POST['ligado'][$m['chave']]);
            if ($ligado === (bool) $m['desligado']) {
                $pdo->prepare('UPDATE modulos SET desligado = ? WHERE id = ? AND pode_desligar = 1')->execute([$ligado ? 0 : 1, (int) $m['id']]);
                Auditoria::registrar('alterou', 'modulos', (int) $m['id'], ($ligado ? 'ligou' : 'desligou') . ' o módulo ' . $m['nome']);
            }
        }
        avisar('Módulos salvos.');
        redirecionar('/configuracoes/modulos');
    }
}
paginaInicio('Módulos', 'configuracoes');
?>
<div class="trilha"><a href="/configuracoes">Configurações</a><?= icone('seta') ?><span>Módulos</span></div>
<?php if ($erro !== ''): ?><p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p><?php endif; ?>
<form method="post" action="/configuracoes/modulos">
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <div class="cab-pag">
    <div><h1>Módulos</h1><p>Valem para todos os destinos. Desligado, o módulo fica no menu sem abrir, e os dados dele continuam guardados.</p></div>
    <?php if ($podeEditar): ?><div class="acoes"><button type="submit" class="botao"><?= icone('check') ?>Salvar</button></div><?php endif; ?>
  </div>
  <div class="cartao">
    <?php foreach ($modulos as $m): ?>
      <?= interruptor('ligado[' . $m['chave'] . ']', (string) $m['nome'], !$m['desligado'], 'Ligado para todos os destinos', 'Desligado: no menu, sem abrir', !$podeEditar) ?>
    <?php endforeach; ?>
  </div>
</form>
<?php paginaFim();
