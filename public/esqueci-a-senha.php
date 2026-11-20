<?php
/**
 * Esqueci a senha: a própria pessoa pede um link para escolher a senha nova.
 * Softsul Software
 *
 * A resposta é sempre a mesma, exista o e-mail ou não, para a tela não
 * servir de consulta de quem tem acesso. Limite de 3 pedidos por e-mail e
 * 10 por IP a cada hora, para não virar forma de encher a caixa de alguém.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Convite.php';
require_once __DIR__ . '/../app/Limite.php';

// Freio geral por IP antes de qualquer consulta (números e porquê em
// app/Limite.php). O limite de envio por e-mail e por IP, mais abaixo,
// continua: ele decide se o e-mail sai; este decide se a tela responde.
Limite::porIp('esqueci', 20, 600);

if (Auth::usuarioOuNulo()) {
    redirecionar('/perfil');
}
$pdo = Database::conexao();
$erro = '';
$enviado = false;
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Recarregue e tente de novo.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Digite o e-mail com que você entra no InvenTour.';
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $q = $pdo->prepare(
            "SELECT SUM(email = ?) AS por_email, SUM(ip = ?) AS por_ip FROM tentativas_login
             WHERE etapa = 'esqueci' AND criado_em > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND (email = ? OR ip = ?)"
        );
        $q->execute([$email, $ip, $email, $ip]);
        $c = $q->fetch();
        $excedeu = (int) ($c['por_email'] ?? 0) >= 3 || (int) ($c['por_ip'] ?? 0) >= 10;
        Auth::anotarTentativa($email, $ip, false, 'esqueci');

        $s = $pdo->prepare('SELECT id, nome, email, softsul FROM usuarios WHERE email = ? AND ativo = 1');
        $s->execute([$email]);
        $u = $s->fetch();
        if ($u && !$excedeu && Auth::temAlgumAcesso((int) $u['id'], (bool) $u['softsul'])) {
            $link = Convite::criar((int) $u['id'], 'esqueci', null);
            $ok = Convite::enviar($u['email'], $u['nome'], $link, 'esqueci');
            Auditoria::registrar('alterou', 'usuarios', (int) $u['id'],
                'pediu link de esqueci a senha (IP ' . $ip . ')' . ($ok ? '' : ', mas o e-mail não saiu'));
        } elseif ($excedeu) {
            Auditoria::falhaDeAcesso('esqueci_excedido');
        }
        $enviado = true;
    }
}

acessoInicio('Esqueci a senha', 'Esqueceu a senha?', 'Mandamos um link para o seu e-mail. Por ele você escolhe a senha nova.');
if ($enviado): ?>
    <div class="interno">
      <div class="titulo"><h2>Confira o seu e-mail</h2><p><?= e($email) ?></p></div>
      <?= avisoAcesso('', 'Se este e-mail tiver acesso ao InvenTour, o link chega em alguns minutos. Ele vale por 1 hora e serve uma vez só. Confira também a caixa de spam.') ?>
      <a class="botao claro" href="/login">Voltar para a entrada</a>
    </div>
<?php else: ?>
    <form class="interno" method="post" action="/esqueci-a-senha" novalidate>
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <div class="titulo"><h2>Esqueci a senha</h2><p>Digite o e-mail com que você entra.</p></div>
      <?= avisoAcesso($erro) ?>
      <div class="campo"><label for="email">E-mail</label>
        <div class="entrada"><?= icone('emails') ?><input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus spellcheck="false" inputmode="email"></div></div>
      <button type="submit" class="botao">Enviar o link</button>
      <a class="botao claro" href="/login">Voltar para a entrada</a>
    </form>
<?php endif;
acessoFim();
