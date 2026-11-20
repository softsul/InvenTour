<?php
/**
 * Tela de entrada do painel do InvenTour.
 * Softsul Software
 *
 * Senha e, depois, o segundo fator (código do autenticador), ou a digital do
 * aparelho, ou o certificado digital A1. A mensagem de erro é uma só para
 * e-mail e senha: dizer qual dos dois está errado entregaria a lista de quem
 * tem acesso.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Certificado.php';

$destino = destinoSeguro($_GET['destino'] ?? $_POST['destino'] ?? null);
if (Auth::usuarioOuNulo()) {
    redirecionar($destino);
}

$erro  = '';
$aviso = '';
$email = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        // Normalmente é uma aba aberta há horas, e não ataque.
        $erro = 'A página ficou aberta tempo demais. Tente entrar de novo.';
    } else {
        $_SESSION['lembrar'] = !empty($_POST['lembrar']);
        switch (Auth::tentarLogin($email, (string) ($_POST['senha'] ?? ''))) {
            case 'sucesso':
                $u = Auth::usuarioOuNulo();
                redirecionar($u && $u['precisa_trocar_senha'] ? '/primeiro-acesso' : $destino);
            case 'precisa_2fa':
                redirecionar('/codigo?destino=' . urlencode($destino));
            case 'precisa_cadastrar_2fa':
                redirecionar('/primeiro-acesso');
            case 'bloqueado':
                $erro = 'Muitas tentativas seguidas. Aguarde 15 minutos antes de tentar de novo.';
                break;
            default:
                $erro = 'E-mail ou senha incorretos.';
        }
    }
} elseif (!empty($_GET['bloqueado'])) {
    $erro = 'Muitas tentativas seguidas. Aguarde 15 minutos antes de tentar de novo.';
} elseif (!empty($_GET['expirado'])) {
    $erro = 'O login não foi concluído a tempo. Entre de novo.';
} elseif (($_GET['certificado'] ?? '') === 'falhou') {
    $erro = 'Não deu para entrar pelo certificado: o pedido vale por poucos minutos e uma vez só. Tente de novo.';
} elseif (($_GET['certificado'] ?? '') === 'sem_vinculo') {
    $erro = 'Este certificado não está vinculado a nenhuma conta. Entre com a senha e vincule em Meus dados.';
} elseif (($_GET['senha'] ?? '') === 'ok') {
    $aviso = 'Senha definida. Entre com ela.';
} elseif (isset($_GET['conta_excluida'])) {
    $aviso = 'Sua conta foi excluída, com os seus dados pessoais e acessos.';
}

acessoInicio('Entrar', 'O inventário turístico do seu destino.',
    'Atrativos, serviços e infraestrutura coletados em campo, homologados pelo município e publicados no portal do destino.');
?>
    <form class="interno" method="post" action="/login" autocomplete="on" novalidate>
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <input type="hidden" name="destino" value="<?= e($destino) ?>">
      <div class="titulo">
        <h2>Entrar</h2>
        <p>Use o e-mail cadastrado no seu destino.</p>
      </div>
      <?= avisoAcesso($erro, $aviso) ?>
      <div class="campo">
        <label for="email">E-mail</label>
        <div class="entrada"><?= icone('emails') ?>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username webauthn"
                 required autofocus spellcheck="false" inputmode="email">
        </div>
      </div>
      <div class="campo">
        <label for="senha">Senha</label>
        <div class="entrada"><?= icone('cadeado') ?>
          <input type="password" id="senha" name="senha" autocomplete="current-password" required>
          <button type="button" class="revelar" data-alvo="senha" aria-label="Mostrar a senha"><?= icone('olho') ?></button>
        </div>
      </div>
      <div class="linha-op">
        <label class="check"><input type="checkbox" name="lembrar" value="1" checked><i></i>Manter conectado</label>
        <a href="/esqueci-a-senha">Esqueci a senha</a>
      </div>
      <button type="submit" class="botao">Entrar</button>
      <?php /* Só aparece onde o aparelho tem digital, rosto ou PIN (inventour.js). */ ?>
      <div class="ou-biometria" id="area-biometria" hidden>
        <button type="button" class="botao claro" id="entrar-biometria"><?= icone('digital') ?>Entrar com a digital</button>
      </div>
      <p class="aviso ruim" id="erro-biometria" role="alert" hidden></p>
      <?php if (Certificado::ligado()): ?>
        <div class="ou-certificado"><a class="botao claro" href="/certificado?acao=entrar<?= $destino !== '/painel' ? '&amp;destino=' . e(urlencode($destino)) : '' ?>"><?= icone('certificado') ?>Entrar com certificado digital</a></div>
      <?php endif; ?>
    </form>
<?php acessoFim();
