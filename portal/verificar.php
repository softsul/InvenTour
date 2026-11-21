<?php
/**
 * Verificação de um caderno oficial: /verificar/<código> (o do rodapé e o
 * do QR da capa). Softsul Software
 *
 * Diz se o código é de um caderno deste destino, de que versão, quando foi
 * gerado e quantas páginas tem, e mostra o SHA-256 do PDF: quem tem o
 * arquivo confere que ele é o mesmo que o InvenTour gerou.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Limite.php';
require_once __DIR__ . '/../app/Tenant.php';
require_once __DIR__ . '/../app/PortalLayout.php';

$destino = Tenant::exigir();
$codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['c'] ?? '')));
$caderno = null;
if (strlen($codigo) === 10) {
    $q = Database::conexaoPortal()->prepare("SELECT id, codigo, titulo, ano, versao, previa, paginas, itens, sha256, gerado_em
                                             FROM cadernos WHERE codigo = ? AND empresa_id = ? AND situacao = 'pronto'");
    $q->execute([$codigo, (int) $destino['id']]);
    $caderno = $q->fetch() ?: null;
    if ($caderno) {
        Database::conexaoPortal()->prepare('INSERT INTO caderno_leituras (empresa_id, caderno_id, item_id, dia, leituras) VALUES (?, ?, 0, CURDATE(), 1)
                                            ON DUPLICATE KEY UPDATE leituras = leituras + 1')->execute([(int) $destino['id'], (int) $caderno['id']]);
    }
}
header('Cache-Control: no-store');
PortalLayout::inicio($destino, 'Verificar o caderno', false);
?>
  <p class="sobre">Caderno oficial · <?= e((string) $destino['nome_fantasia']) ?></p>
<?php if ($caderno): ?>
  <h1>Documento autêntico</h1>
  <p class="lead"><?= e((string) $caderno['titulo']) ?>, versão <?= (int) $caderno['versao'] ?> (<?= (int) $caderno['ano'] ?>)<?= $caderno['previa'] ? ', versão preliminar' : '' ?>.</p>
  <dl class="ficha-dados">
    <dt>Código</dt><dd><?= e(substr($codigo, 0, 4) . '-' . substr($codigo, 4, 4) . '-' . substr($codigo, 8)) ?></dd>
    <dt>Gerado em</dt><dd><?= e(date('d/m/Y H:i', strtotime((string) $caderno['gerado_em']))) ?></dd>
    <dt>Páginas</dt><dd><?= number_format((int) $caderno['paginas'], 0, ',', '.') ?></dd>
    <dt>Itens</dt><dd><?= number_format((int) $caderno['itens'], 0, ',', '.') ?></dd>
    <dt>SHA-256 do PDF</dt><dd class="hash"><?= e((string) $caderno['sha256']) ?></dd>
  </dl>
  <p class="lead">Para conferir um arquivo, calcule o SHA-256 dele: tem de ser igual ao de cima.</p>
<?php else: ?>
  <h1>Código não encontrado</h1>
  <p class="lead">Este código não é de um caderno oficial de <?= e((string) $destino['nome_fantasia']) ?>. Confira as letras e os números do rodapé do documento.</p>
<?php endif; ?>
<?php PortalLayout::fim();
