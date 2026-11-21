<?php
/**
 * "Sou o responsável por este lugar": o pedido do trade, aberto ao público.
 * Softsul Software
 *
 * Chega pela ficha pública do portal (/reivindicar?i=ID). Só para lugar
 * homologado de destino ativo (o licenciado). A pessoa diz quem é, o
 * vínculo com o lugar, o CNPJ ou o Cadastur, anexa o comprovante e aceita o
 * termo; a equipe do destino confere em Trade turístico e, aprovando, manda
 * o link para ela criar a senha. Nome, e-mail e WhatsApp ficam no pedido e
 * na conta, nunca na ficha pública.
 *
 * Limite por IP (a tela é pública e aceita arquivo) e um pedido aberto por
 * e-mail e lugar.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Limite.php';
require_once __DIR__ . '/../app/Cnpj.php';
require_once __DIR__ . '/../app/Trade.php';

Limite::porIp('reivindicar', 30, 600);

$lugar = Trade::lugarParaPedir((int) ($_GET['i'] ?? $_POST['i'] ?? 0));
if (!$lugar) {
    paginaErro(404, 'Este lugar não aceita pedido de responsável.', 'O pedido vale para os lugares publicados no portal de um destino com o InvenTour ativo.');
}

$p = ['nome' => '', 'email' => '', 'whatsapp' => '', 'vinculo' => '', 'cnpj' => '', 'cadastur' => ''];
$erro = '';
$feito = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($p as $k => $_) {
        $p[$k] = trim((string) (is_scalar($_POST[$k] ?? null) ? $_POST[$k] : ''));
    }
    $p['email'] = mb_strtolower($p['email']);
    $cnpj = Cnpj::apenasDigitos($p['cnpj']);
    $cadastur = preg_replace('/[^0-9A-Za-z.\-\/]/', '', $p['cadastur']);
    $whats = telefoneParaBanco($p['whatsapp']);
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (mb_strlen($p['nome']) < 5 || !str_contains($p['nome'], ' ')) {
        $erro = 'Escreva o seu nome completo.';
    } elseif (!filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
        $erro = 'Confira o e-mail: é por ele que você vai entrar.';
    } elseif ($whats === null || strlen($whats) < 12) {
        $erro = 'Escreva o WhatsApp com o DDD: a equipe fala com você por ele.';
    } elseif (!isset(Trade::VINCULOS[$p['vinculo']])) {
        $erro = 'Diga o seu vínculo com o lugar.';
    } elseif ($cnpj === '' && $cadastur === '') {
        $erro = 'Informe o CNPJ ou o número do Cadastur do lugar.';
    } elseif ($cnpj !== '' && !Cnpj::valido($cnpj)) {
        $erro = 'CNPJ inválido. Confira os dígitos.';
    } elseif (empty($_POST['termo'])) {
        $erro = 'Para pedir, leia e aceite o termo.';
    } elseif (Trade::pedidoAberto((int) $lugar['id'], $p['email'])) {
        $erro = 'Já existe um pedido seu para este lugar. A equipe do destino responde pelo WhatsApp ou pelo e-mail.';
    } else {
        Limite::exigir('reivindicar-envio:' . Limite::ipAgrupado(), 5, 3600, 'pedidos de responsável');
        $comp = Trade::guardarComprovante((int) $lugar['empresa_id'], $_FILES['comprovante'] ?? []);
        if (is_string($comp)) {
            $erro = $comp;
        } else {
            Trade::pedir($lugar, ['nome' => (string) nomeProprio($p['nome']), 'email' => $p['email'], 'whatsapp' => $whats, 'vinculo' => $p['vinculo'],
                                  'cnpj' => $cnpj ?: null, 'cadastur' => $cadastur ?: null], $comp, $_SERVER['REMOTE_ADDR'] ?? null);
            $feito = true;
        }
    }
}

acessoInicio('Responsável por ' . $lugar['nome'], 'Você responde por este lugar?',
    'Peça o acesso e mantenha horário, contato, fotos e capacidade de ' . $lugar['nome'] . ' em dia no portal de turismo de ' . $lugar['destino'] . '.');
if ($feito): ?>
    <div class="interno">
      <div class="titulo"><h2>Pedido enviado</h2><p><?= e((string) $lugar['nome']) ?></p></div>
      <?= avisoAcesso('', 'A equipe de turismo de ' . $lugar['destino'] . ' confere o comprovante e responde pelo WhatsApp ou pelo e-mail com o link para você criar a sua senha.') ?>
      <p class="ajuda">Enquanto isso, a ficha do portal continua como está.</p>
    </div>
<?php else: ?>
    <form class="interno largo form-reivindicar" method="post" action="/reivindicar?i=<?= (int) $lugar['id'] ?>" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
      <div class="titulo"><h2><?= e((string) $lugar['nome']) ?></h2>
        <p><?= e(trim(($lugar['tipo_nome'] ?: $lugar['ficha_nome']) . ' · ' . trim((string) $lugar['logradouro'] . ($lugar['numero'] ? ', ' . $lugar['numero'] : '')), ' ·')) ?></p></div>
      <?= avisoAcesso($erro) ?>
      <div class="campo"><label for="nome">Seu nome completo</label>
        <div class="entrada"><?= icone('usuarios') ?><input type="text" id="nome" name="nome" maxlength="120" value="<?= e($p['nome']) ?>" autocomplete="name" required></div></div>
      <div class="campo"><label for="email">Seu e-mail</label>
        <div class="entrada"><?= icone('emails') ?><input type="email" id="email" name="email" maxlength="150" value="<?= e($p['email']) ?>" autocomplete="email" inputmode="email" spellcheck="false" required></div>
        <span class="ajuda">É com ele que você vai entrar no InvenTour.</span></div>
      <div class="campo"><label for="whatsapp">Seu WhatsApp</label>
        <div class="entrada"><?= icone('whatsapp') ?><input type="text" id="whatsapp" name="whatsapp" class="telefone" value="<?= e($p['whatsapp']) ?>" autocomplete="tel" required></div></div>
      <div class="campo"><label for="vinculo">Você é</label>
        <div class="entrada"><select id="vinculo" name="vinculo" required><option value="">Escolha</option>
          <?php foreach (Trade::VINCULOS as $k => $r): ?><option value="<?= $k ?>"<?= $p['vinculo'] === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
        </select></div></div>
      <div class="campo"><label for="cnpj">CNPJ do lugar</label>
        <div class="entrada"><?= icone('predio') ?><input type="text" id="cnpj" name="cnpj" class="cnpj" maxlength="18" autocomplete="off" value="<?= e($p['cnpj']) ?>"></div></div>
      <div class="campo"><label for="cadastur">Número do Cadastur</label>
        <div class="entrada"><?= icone('certificado') ?><input type="text" id="cadastur" name="cadastur" maxlength="30" autocomplete="off" value="<?= e($p['cadastur']) ?>"></div>
        <span class="ajuda">Basta um dos dois. Com o Cadastur, a conferência é mais rápida.</span></div>
      <div class="campo"><span class="rotulo">Comprovante</span>
        <?= zonaDeUpload('comprovante', 'application/pdf,image/jpeg,image/png,image/webp', 'Cartão do CNPJ, certificado do Cadastur ou procuração', 'PDF ou foto, até 10 MB', false) ?></div>
      <div class="termo-trade">
        <b>Termo de responsabilidade</b>
        <ul><?php foreach (Trade::TERMO as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
        <label class="check"><input type="checkbox" name="termo" value="1" required><i></i>Li e aceito o termo</label>
      </div>
      <button type="submit" class="botao"><?= icone('enviar') ?>Enviar o pedido</button>
      <p class="ajuda">Já tem acesso? <a href="/login">Entre no InvenTour</a>.</p>
    </form>
<?php endif;
acessoFim();
