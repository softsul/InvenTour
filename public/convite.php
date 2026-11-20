<?php
/**
 * Definir a senha pelo link de uso único (convite, redefinição ou esqueci a senha).
 * Softsul Software
 *
 * O link troca a senha e não abre a conta sozinho: o segundo fator continua
 * valendo na entrada. Quem cria o usuário nunca escolhe a senha dele.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Convite.php';
require_once __DIR__ . '/../app/Limite.php';

// Por IP, antes de procurar o token: tela pública, e o limite tira a graça
// de tentar token por tentativa e erro (números em app/Limite.php).
Limite::porIp('convite', 60, 600);

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$convite = Convite::achar($token);
$erro = '';

if ($convite && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } else {
        $nova = (string) ($_POST['senha_nova'] ?? '');
        $erro = Auth::validarSenhaNova($nova, (string) ($_POST['senha_confirma'] ?? ''), $convite['email'], $convite['nome']) ?? '';
        if ($erro === '' && Convite::usar((int) $convite['id'])) {
            Auth::definirSenha((int) $convite['usuario_id'], $nova);
            Auditoria::registrar('alterou', 'usuarios', (int) $convite['usuario_id'],
                'definiu a senha pelo link (' . $convite['origem'] . ')');
            // Quem estava logado com outra conta neste navegador sai, para a
            // entrada seguinte ser com a conta que acabou de ganhar senha.
            Auth::sair();
            redirecionar('/login?senha=ok');
        } elseif ($erro === '') {
            $convite = null;
        }
    }
}

acessoInicio('Definir senha', 'Escolha a sua senha.', 'Pelo menos 12 caracteres. Uma frase que só você saiba é mais forte e mais fácil de lembrar.');
if (!$convite): ?>
    <div class="interno">
      <div class="titulo"><h2>Link inválido</h2></div>
      <?= avisoAcesso('Este link já foi usado, expirou ou não existe. Peça um novo ao gestor do seu destino, ou use "Esqueci a senha".') ?>
      <a class="botao" href="/esqueci-a-senha">Esqueci a senha</a>
      <a class="botao claro" href="/login">Ir para a entrada</a>
    </div>
<?php else: ?>
    <form class="interno" method="post" action="/convite" novalidate>
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <input type="hidden" name="t" value="<?= e($token) ?>">
      <div class="titulo"><h2>Olá, <?= e(explode(' ', $convite['nome'])[0]) ?></h2><p><?= e($convite['email']) ?></p></div>
      <?= avisoAcesso($erro) ?>
      <div class="campo"><label for="senha_nova">Senha nova</label>
        <div class="entrada"><?= icone('cadeado') ?><input type="password" id="senha_nova" name="senha_nova" autocomplete="new-password" minlength="12" required autofocus>
          <button type="button" class="revelar" data-alvo="senha_nova" aria-label="Mostrar a senha"><?= icone('olho') ?></button></div>
        <span class="ajuda">Pelo menos 12 caracteres. Uma frase que só você saiba é mais forte e mais fácil de lembrar.</span></div>
      <div class="campo"><label for="senha_confirma">Repita a senha</label>
        <div class="entrada"><?= icone('cadeado') ?><input type="password" id="senha_confirma" name="senha_confirma" autocomplete="new-password" required>
          <button type="button" class="revelar" data-alvo="senha_confirma" aria-label="Mostrar a senha"><?= icone('olho') ?></button></div></div>
      <button type="submit" class="botao">Salvar a senha</button>
    </form>
<?php endif;
acessoFim();
