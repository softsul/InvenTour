<?php
/**
 * Política de privacidade no painel: /privacidade, sem login (o link fica
 * na tela de entrada). Mesmo texto do portal (app/vistas/privacidade.php).
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

$quem = 'cada destino da plataforma InvenTour';
$semContato = true;
?>
<!doctype html>
<html lang="pt-BR">
<head><?php cabecaComum('Política de privacidade', false); ?></head>
<body>
<?php svgSprite(); ?>
<main class="pagina-legal">
  <a class="voltar-legal" href="/login"><?= icone('seta', null, 'gira') ?>Voltar para a entrada</a>
  <?= logoInvenTour('logo') ?>
  <h1>Política de privacidade</h1>
  <?php require __DIR__ . '/../app/vistas/privacidade.php'; ?>
</main>
</body></html>
