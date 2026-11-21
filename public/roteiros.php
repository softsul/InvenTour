<?php
/**
 * Roteiros do destino (etapa 5): os caminhos prontos que o portal mostra,
 * permanentes, de evento ou de um perfil de viajante, com os lugares do
 * inventário em ordem. O roteiro abre em modal (roteiro.php).
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('roteiros.ver');
Auth::exigirModuloLigado('roteiros');
$pdo = Database::conexao();
$eid = (int) $empresa['id'];
$q = $pdo->prepare('SELECT r.*, t.nome AS tribo_nome, (SELECT COUNT(*) FROM roteiro_itens ri WHERE ri.roteiro_id = r.id) AS paradas
                    FROM roteiros r LEFT JOIN tribos t ON t.id = r.tribo_id WHERE r.empresa_id = ? ORDER BY r.ordem, r.titulo');
$q->execute([$eid]);
$roteiros = $q->fetchAll();
$tipos = ['permanente' => 'Permanente', 'evento' => 'De evento', 'tribo' => 'Por perfil de viajante'];
paginaInicio('Roteiros', 'roteiros');
?>
<div class="cab-pag">
  <div><h1>Roteiros</h1>
    <p>Caminhos prontos para o visitante, com os lugares do inventário em ordem e uma dica em cada parada. Publicados, aparecem no portal com a rota no mapa.</p></div>
  <?php if (Auth::pode('roteiros.criar')): ?>
    <div class="acoes"><a class="botao" href="/roteiro" data-ficha="Novo roteiro" data-abre-novo><?= icone('mais') ?>Novo roteiro</a></div>
  <?php endif; ?>
</div>
<div class="cartao">
  <?php if (!$roteiros): ?>
    <div class="vazio"><?= icone('mapa') ?><p><strong>Nenhum roteiro ainda.</strong></p><p>Monte o primeiro: um dia no centro, a rota do café colonial, o roteiro tecnológico.</p></div>
  <?php else: ?>
  <div class="rolagem">
    <table class="dados">
      <thead><tr><th>Roteiro</th><th>Tipo</th><th class="num">Paradas</th><th>Situação</th><th class="acao"></th></tr></thead>
      <tbody>
      <?php foreach ($roteiros as $r): $url = endereco('roteiro', (int) $r['id'], (string) $r['titulo']); ?>
        <tr>
          <td class="elastica"><b><a href="<?= e($url) ?>" data-ficha="<?= e($r['titulo']) ?>"><?= e((string) $r['titulo']) ?></a></b>
            <?php if ($r['duracao']): ?><br><span class="nulo"><?= e((string) $r['duracao']) ?></span><?php endif; ?></td>
          <td><?= e($tipos[$r['tipo']] ?? $r['tipo']) ?><?= $r['tribo_nome'] ? ': ' . e((string) $r['tribo_nome']) : '' ?></td>
          <td class="num dado"><?= milhar((int) $r['paradas']) ?></td>
          <td><?= $r['publicado'] ? '<span class="pilula ok">No portal</span>' : '<span class="pilula nu">Rascunho</span>' ?></td>
          <td class="acao"><div class="acoes-linha"><a href="<?= e($url) ?>" data-ficha="<?= e($r['titulo']) ?>" title="Abrir" aria-label="Abrir <?= e($r['titulo']) ?>"><?= icone('lapis') ?></a></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php paginaFim();
