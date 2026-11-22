<?php
/**
 * Relatórios: a lista do que dá para tirar (etapa 7, o mesmo do EventCity).
 * Softsul Software
 *
 * No molde do Orbit: cartões e não uma tela cheia de números. Cada
 * relatório responde a uma pergunta diferente, e quem chega aqui está
 * procurando uma coisa, não navegando.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Relatorio.php';

Auth::exigirLogin();
Empresa::exigir();
Auth::exigirPermissao('relatorios.ver');
Auth::exigirModuloLigado('relatorios');

$porGrupo = [];
foreach (Relatorio::todos() as $chave => $r) {
    if (!Auth::pode((string) $r['permissao'])) {
        continue;   // relatório que a pessoa não poderia abrir não se anuncia
    }
    $porGrupo[$r['grupo']][$chave] = $r;
}

paginaInicio('Relatórios', 'relatorios');
?>
<div class="cab-pag">
  <div>
    <h1>Relatórios gerenciais</h1>
    <p>Escolha o assunto. Todos saem em PDF para imprimir ou CSV para a planilha; os do inventário mostram a situação de hoje, os outros aceitam período.</p>
  </div>
</div>

<?php foreach (Relatorio::GRUPOS as $chaveGrupo => $nomeGrupo): ?>
  <?php if (empty($porGrupo[$chaveGrupo])) { continue; } ?>
  <h2 class="titulo-grupo-config"><?= e($nomeGrupo) ?></h2>
  <div class="cartoes-config">
    <?php foreach ($porGrupo[$chaveGrupo] as $chave => $r): ?>
      <a class="config" href="/relatorio?tipo=<?= e($chave) ?>">
        <span class="marca-config"><?= icone((string) $r['icone']) ?></span>
        <div class="corpo-config">
          <b><?= e((string) $r['nome']) ?></b>
          <p><?= e((string) $r['descricao']) ?></p>
          <span class="nota-config"><?= !empty($r['sem_periodo']) ? 'situação de hoje' : 'período por ' . e((string) $r['data']) ?><?= !empty($r['categoria']) ? ' · filtra por categoria' : '' ?></span>
        </div>
        <span class="conta-config"><?= icone('seta') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if (!$porGrupo): ?>
  <div class="vazio"><?= icone('lista') ?>
    <p><strong>Nenhum relatório disponível para o seu acesso.</strong></p>
    <p>Cada relatório pede a mesma permissão da tela que ele resume.</p>
  </div>
<?php endif; ?>
<?php paginaFim();
