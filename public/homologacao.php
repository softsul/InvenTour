<?php
/**
 * Homologação: a fila de conferência do destino.
 * Softsul Software
 *
 * O que os pesquisadores enviaram, do mais antigo para o mais novo, com o
 * que ainda falta do núcleo mínimo. Abre o item em modal, onde ficam os
 * botões de homologar e de devolver para o campo. Embaixo, os devolvidos
 * que ainda não voltaram.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Esteira.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('homologacao.ver');
Auth::exigirModuloLigado('homologacao');
$pdo = Database::conexao();
$eid = (int) $empresa['id'];

$sel = "SELECT i.*, f.codigo AS ficha_codigo, f.nome AS ficha_nome, f.forma, t.nome AS tipo_nome, u.nome AS pesquisador_nome,
               (SELECT m.arquivo FROM inventario_midias m WHERE m.item_id = i.id AND m.experiencia_id IS NULL ORDER BY m.capa DESC, m.ordem LIMIT 1) AS foto
        FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
        LEFT JOIN inventario_tipos t ON t.id = i.tipo_id LEFT JOIN usuarios u ON u.id = i.pesquisador_id
        WHERE i.empresa_id = ? AND ";
$q = $pdo->prepare($sel . "i.situacao = 'em_conferencia' ORDER BY i.enviado_em IS NULL, i.enviado_em, i.id");
$q->execute([$eid]);
$fila = $q->fetchAll();
$q = $pdo->prepare($sel . "i.situacao = 'em_campo' AND i.devolucao_nota IS NOT NULL ORDER BY i.atualizado_em DESC LIMIT 50");
$q->execute([$eid]);
$devolvidos = $q->fetchAll();

paginaInicio('Homologação', 'homologacao');
?>
<div class="cab-pag">
  <div>
    <h1>Homologação</h1>
    <p>As fichas que a equipe de campo enviou para conferência. Confira o núcleo mínimo e as fotos, e homologue ou devolva com uma nota do que acertar.</p>
  </div>
</div>

<div class="cartao">
  <div class="cab-cartao"><h2>Na fila (<?= count($fila) ?>)</h2></div>
  <?php if (!$fila): ?>
    <div class="vazio"><?= icone('certificado') ?><p><strong>Nada esperando conferência.</strong></p>
      <p>Quando um pesquisador enviar uma ficha, ela aparece aqui e no número do menu.</p></div>
  <?php else: ?>
  <div class="rolagem">
    <table class="dados">
      <thead><tr><th>Item</th><th>Ficha</th><th>Pesquisador</th><th>Enviado</th><th>Núcleo mínimo</th><th class="acao"></th></tr></thead>
      <tbody>
      <?php foreach ($fila as $i): $url = endereco('item', (int) $i['id'], $i['nome']); $falta = Esteira::pendencias($i); ?>
        <tr>
          <td class="elastica"><span class="cli">
            <?php if ($i['foto']): ?><img class="avatar com-foto" src="/midia/inventario/<?= $eid ?>/<?= e(rawurlencode(basename((string) $i['foto']))) ?>" alt="" loading="lazy"><?php else: ?><?= avatarIniciais((string) $i['nome'], 'avatar') ?><?php endif; ?>
            <span class="txt"><b><a href="<?= e($url) ?>" data-ficha="<?= e($i['nome']) ?>"><?= e($i['nome']) ?></a></b>
            <span><?= e((string) ($i['tipo_nome'] ?: $i['ficha_nome'])) ?></span></span></span></td>
          <td class="dado"><?= e((string) $i['ficha_codigo']) ?></td>
          <td><?= $i['pesquisador_nome'] ? e((string) $i['pesquisador_nome']) : '<span class="nulo">-</span>' ?></td>
          <td class="dado"><?= $i['enviado_em'] ? e(dataBr((string) $i['enviado_em'], 'd/m/Y H:i')) : '<span class="nulo">-</span>' ?></td>
          <td><?= $falta ? '<span class="pilula at" title="' . e(implode(', ', $falta)) . '">falta ' . count($falta) . '</span>' : '<span class="pilula ok">completo</span>' ?></td>
          <td class="acao"><div class="acoes-linha"><a href="<?= e($url) ?>" data-ficha="<?= e($i['nome']) ?>" title="Conferir" aria-label="Conferir <?= e($i['nome']) ?>"><?= icone('olho') ?></a></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($devolvidos): ?>
<div class="cartao">
  <div class="cab-cartao"><h2>Devolvidos para o campo (<?= count($devolvidos) ?>)</h2></div>
  <ul class="lista-simples">
    <?php foreach ($devolvidos as $i): ?>
      <li><a href="<?= e(endereco('item', (int) $i['id'], $i['nome'])) ?>" data-ficha="<?= e($i['nome']) ?>"><?= e($i['nome']) ?></a>
        <span class="dado"><?= e((string) $i['ficha_codigo']) ?></span> <span class="nulo"><?= e((string) $i['devolucao_nota']) ?></span></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<?php paginaFim();
