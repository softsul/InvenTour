<?php
/**
 * Pontos de QR do destino (phygital, etapa 5): os cartazes espalhados pela
 * cidade, para onde cada um leva e quantas leituras teve (30 dias), com o
 * cartaz A4 em PDF (no visualizador) e o arquivo para baixar. O ponto abre
 * em modal (ponto-qr.php). Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/PontosQr.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('phygital.ver');
Auth::exigirModuloLigado('phygital');
$pdo = Database::conexao();
$eid = (int) $empresa['id'];

if (($_GET['cartaz'] ?? '') !== '') {
    $f = PontosQr::arquivoCartaz($eid, (int) $_GET['cartaz']);
    $q = $pdo->prepare('SELECT apelido FROM qr_pontos WHERE id = ? AND empresa_id = ?');
    $q->execute([(int) $_GET['cartaz'], $eid]);
    $apelido = $q->fetchColumn();
    if ($apelido === false || !is_file($f)) {
        negarRegistro();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="cartaz-qr-' . $apelido . '.pdf"');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

$q = $pdo->prepare('SELECT p.*, (SELECT COALESCE(SUM(l.leituras), 0) FROM qr_leituras l WHERE l.ponto_id = p.id AND l.dia >= CURDATE() - INTERVAL 30 DAY) AS leituras30,
                           (SELECT COALESCE(SUM(l.leituras), 0) FROM qr_leituras l WHERE l.ponto_id = p.id) AS leituras
                    FROM qr_pontos p WHERE p.empresa_id = ? ORDER BY p.titulo');
$q->execute([$eid]);
$pontos = $q->fetchAll();
paginaInicio('Pontos de QR', 'phygital');
?>
<div class="cab-pag">
  <div><h1>Pontos de QR</h1>
    <p>Cartazes com QR nos atrativos, hotéis e pontos da cidade. Cada um leva a uma página do portal, que pode mudar sem reimprimir o cartaz. As leituras são contadas por dia, sem guardar quem leu.</p></div>
  <?php if (Auth::pode('phygital.criar')): ?>
    <div class="acoes"><a class="botao" href="/ponto-qr" data-ficha="Novo ponto de QR" data-abre-novo><?= icone('qrcode') ?>Novo ponto</a></div>
  <?php endif; ?>
</div>
<div class="cartao">
  <?php if (!$pontos): ?>
    <div class="vazio"><?= icone('qrcode') ?><p><strong>Nenhum ponto de QR ainda.</strong></p><p>Crie o primeiro para a rodoviária, o aeroporto ou a praça central.</p></div>
  <?php else: ?>
  <div class="rolagem">
    <table class="dados">
      <thead><tr><th>Ponto</th><th>Leva para</th><th>Endereço do QR</th><th class="num">Leituras em 30 dias</th><th class="num">Total</th><th>Cartaz</th></tr></thead>
      <tbody>
      <?php foreach ($pontos as $p): $url = endereco('ponto-qr', (int) $p['id'], (string) $p['titulo']); $temCartaz = is_file(PontosQr::arquivoCartaz($eid, (int) $p['id'])); ?>
        <tr class="<?= $p['ativo'] ? '' : 'inativa' ?>">
          <td class="elastica"><b><a href="<?= e($url) ?>" data-ficha="<?= e($p['titulo']) ?>"><?= e((string) $p['titulo']) ?></a></b>
            <?php if ($p['local_texto']): ?><br><span class="nulo"><?= e((string) $p['local_texto']) ?></span><?php endif; ?></td>
          <td><?= e(PontosQr::DESTINOS[$p['destino_tipo']] ?? $p['destino_tipo']) ?></td>
          <td class="dado"><?= e(preg_replace('#^https://#', '', PontosQr::url($empresa, (string) $p['apelido']))) ?></td>
          <td class="num dado"><?= milhar((int) $p['leituras30']) ?></td>
          <td class="num dado"><?= milhar((int) $p['leituras']) ?></td>
          <td class="arquivos-caderno"><?= $temCartaz
              ? '<a class="botao claro mini" href="/phygital?cartaz=' . (int) $p['id'] . '" data-visualizar="pdf" title="Abrir o cartaz">' . icone('olho') . 'Cartaz</a>'
              : '<span class="nulo">gerando</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php paginaFim();
