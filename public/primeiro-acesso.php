<?php
/**
 * Primeiro acesso: troca de senha obrigatória e cadastro do autenticador.
 * Softsul Software
 *
 * Serve a quem está no meio do login (senha certa, segundo fator ainda não
 * cadastrado) e a quem já tem sessão mas passou a ser obrigado a usar o
 * segundo fator, porque o destino ligou a exigência para a equipe.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

$logado = Auth::usuarioOuNulo();
$uid = $logado ? (int) $logado['id'] : (int) ($_SESSION['pre_2fa'] ?? 0);
// Quem está no meio do login tem 15 minutos para concluir o cadastro.
if (!$logado && $uid && time() - (int) ($_SESSION['pre_2fa_em'] ?? 0) > 900) {
    unset($_SESSION['pre_2fa'], $_SESSION['pre_2fa_em'], $_SESSION['novo_segredo']);
    redirecionar('/login?expirado=1');
}
if (!$uid) {
    redirecionar('/login');
}

$pdo = Database::conexao();
$q = $pdo->prepare('SELECT id, nome, email, precisa_trocar_senha, duplo_fator_confirmado FROM usuarios WHERE id = ? AND ativo = 1');
$q->execute([$uid]);
$u = $q->fetch();
if (!$u) {
    Auth::sair();
    redirecionar('/login');
}
$precisaSenha = $logado && $u['precisa_trocar_senha'];
$precisa2fa   = !$u['duplo_fator_confirmado'] && Auth::segundoFatorObrigatorio($uid);

if (!$precisaSenha && !$precisa2fa) {
    if (!$logado) {
        Auth::concluirDepoisDoCadastro($uid);
    }
    redirecionar('/painel');
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif ($precisaSenha) {
        $nova = (string) ($_POST['senha_nova'] ?? '');
        $erro = Auth::validarSenhaNova($nova, (string) ($_POST['senha_confirma'] ?? ''), $u['email'], $u['nome']) ?? '';
        if ($erro === '') {
            Auth::definirSenha($uid, $nova, true);
            Auditoria::registrar('alterou', 'usuarios', $uid, 'definiu a senha no primeiro acesso');
            redirecionar('/primeiro-acesso');
        }
    } else {
        $segredo = (string) ($_SESSION['novo_segredo'] ?? '');
        if ($segredo !== '' && Auth::ligarSegundoFator($uid, $segredo, (string) ($_POST['codigo'] ?? ''))) {
            unset($_SESSION['novo_segredo']);
            Auditoria::registrar('alterou', 'usuarios', $uid, 'cadastrou o aplicativo autenticador');
            if ($logado) {
                Auth::invalidarSessoes($uid, true);
                avisar('Autenticador cadastrado. A partir de agora, o código será pedido a cada entrada.');
            } else {
                Auth::concluirDepoisDoCadastro($uid);
            }
            redirecionar('/painel');
        }
        $erro = 'Código incorreto. Confira se o relógio do celular está certo e digite o código que aparece agora.';
    }
}

if ($precisaSenha) {
    acessoInicio('Primeiro acesso', 'Escolha a sua senha.', 'Pelo menos 12 caracteres. Uma frase que só você saiba é mais forte e mais fácil de lembrar.');
    ?>
    <form class="interno" method="post" action="/primeiro-acesso" novalidate>
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <div class="titulo"><h2>Definir senha</h2><p><?= e($u['email']) ?></p></div>
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
    <?php
    acessoFim();
    exit;
}

if (empty($_SESSION['novo_segredo'])) {
    $_SESSION['novo_segredo'] = TOTP::gerarSegredo();
}
acessoInicio('Segundo fator', 'Proteja a sua conta.',
    'Quem cuida do inventário de um destino precisa do segundo fator: além da senha, um código que muda a cada 30 segundos no seu celular.');
?>
    <form class="interno" method="post" action="/primeiro-acesso" novalidate>
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <div class="titulo"><h2>Cadastrar o autenticador</h2><p><?= e($u['email']) ?></p></div>
      <?= avisoAcesso($erro) ?>
      <?= blocoCadastro2fa((string) $_SESSION['novo_segredo'], (string) $u['email']) ?>
      <div class="campo"><label for="codigo">Código de 6 dígitos</label>
        <div class="entrada"><?= icone('escudo') ?><input type="text" id="codigo" name="codigo" class="codigo dado" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required spellcheck="false"></div></div>
      <button type="submit" class="botao">Confirmar</button>
      <a class="botao claro" href="/sair">Cancelar</a>
    </form>
<?php acessoFim();
