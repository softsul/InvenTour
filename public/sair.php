<?php
/**
 * Encerra a sessão.
 * Softsul Software
 *
 * Só por POST com CSRF, ou por GET quando não há sessão: um <img src="/sair">
 * em outro site não derruba ninguém.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::conferirCsrf($_POST['csrf'] ?? null)) {
    Auth::sair();
    redirecionar('/login');
}
if (!Auth::usuarioOuNulo()) {
    Auth::sair();
    redirecionar('/login');
}
acessoInicio('Sair', 'Até a próxima.', 'Confirme para encerrar a sessão neste aparelho.');
?>
    <form class="interno" method="post" action="/sair">
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <div class="titulo"><h2>Sair do InvenTour</h2></div>
      <button type="submit" class="botao">Sair agora</button>
      <a class="botao claro" href="/painel">Continuar no sistema</a>
    </form>
<?php acessoFim();
