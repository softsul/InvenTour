<?php
/**
 * Segundo passo do login: o código do aplicativo autenticador.
 * Softsul Software
 *
 * Só chega aqui quem já acertou a senha (sessão pre_2fa). Os erros contam no
 * banco junto com os da senha: voltar para a tela de senha não zera a conta.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

$destino = destinoSeguro($_GET['destino'] ?? $_POST['destino'] ?? null);
if (Auth::usuarioOuNulo()) {
    redirecionar($destino);
}
if (empty($_SESSION['pre_2fa'])) {
    redirecionar('/login');
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Entre de novo.';
    } else {
        switch (Auth::confirmarSegundoFator((string) ($_POST['codigo'] ?? ''))) {
            case 'sucesso':
                $u = Auth::usuarioOuNulo();
                redirecionar($u && $u['precisa_trocar_senha'] ? '/primeiro-acesso' : $destino);
            case 'bloqueado':
                redirecionar('/login?bloqueado=1');
            case 'expirado':
                redirecionar('/login?expirado=1');
            default:
                $erro = 'Código incorreto. O mesmo código não vale duas vezes: se acabou de usar, espere o próximo.';
        }
    }
}

acessoInicio('Código de acesso', 'Falta só o código.', 'Abra o aplicativo autenticador no celular e digite os seis dígitos que ele mostra agora.');
?>
    <form class="interno" method="post" action="/codigo" novalidate>
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <input type="hidden" name="destino" value="<?= e($destino) ?>">
      <div class="titulo"><h2>Confirmar acesso</h2><p>O código muda a cada 30 segundos.</p></div>
      <?= avisoAcesso($erro) ?>
      <div class="campo">
        <label for="codigo">Código de 6 dígitos</label>
        <div class="entrada"><?= icone('escudo') ?>
          <input type="text" id="codigo" name="codigo" class="codigo dado" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                 autocomplete="one-time-code" required autofocus spellcheck="false" data-enviar-com-6>
        </div>
      </div>
      <button type="submit" class="botao">Confirmar</button>
      <a class="botao claro" href="/login">Voltar</a>
    </form>
<?php acessoFim();
