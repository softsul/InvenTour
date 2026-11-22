<?php
/**
 * O link do radar de obsolescência: o estabelecimento confirma, num clique,
 * que os dados da ficha continuam certos, ou pede para atualizar.
 * Softsul Software
 *
 * Público, sem login: o link de uso único (app/Radar.php) é a credencial.
 * Mostra só o que já é público no portal (contato, horário, capacidade).
 * "Está tudo igual" renova a conferência; "Preciso atualizar" leva a Meus
 * lugares (quem já responde pelo lugar) ou ao pedido de acesso.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Limite.php';
require_once __DIR__ . '/../app/Radar.php';
require_once __DIR__ . '/../app/Trade.php';

Limite::porIp('confirmar', 60, 600);
$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$a = Radar::achar($token);
$erro = '';
$feito = false;
$vencido = $a && ($a['expira_em'] < date('Y-m-d H:i:s'));

if ($a && !$vencido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (($_POST['resposta'] ?? '') === 'igual') {
        $feito = Radar::confirmarIgual($a);
        if (!$feito) {
            $erro = 'Este link já foi usado. Obrigado!';
        }
    } elseif (($_POST['resposta'] ?? '') === 'atualizar') {
        redirecionar(Radar::pedirAtualizacao($a));
    }
}

$tel = fn ($v) => $v ? formatarTelefone((string) $v) : '';
$dados = array_filter([
    'Telefone'  => $tel($a['telefone'] ?? null),
    'WhatsApp'  => $tel($a['whatsapp'] ?? null),
    'E-mail'    => (string) ($a['email'] ?? ''),
    'Site'      => (string) ($a['site'] ?? ''),
    'Instagram' => !empty($a['instagram']) ? '@' . $a['instagram'] : '',
    'Horário'   => $a ? Trade::horarioEmTexto(json_decode((string) $a['horario'], true) ?: []) : '',
    'Quartos'   => !empty($a['capacidade_uh']) ? milhar((int) $a['capacidade_uh']) : '',
    'Leitos'    => !empty($a['capacidade_leitos']) ? milhar((int) $a['capacidade_leitos']) : '',
    'Lugares'   => !empty($a['capacidade_assentos']) ? milhar((int) $a['capacidade_assentos']) : '',
    'Público'   => !empty($a['capacidade_publico']) ? milhar((int) $a['capacidade_publico']) . ' pessoas' : '',
]);

acessoInicio('Confirmar os dados', $a ? (string) $a['nome'] : 'Guia de turismo', $a
    ? 'Os dados do seu negócio no guia oficial de turismo de ' . $a['destino'] . ' continuam certos?'
    : 'Este link não é mais válido.');
?>
    <div class="interno largo">
<?php if (!$a): ?>
      <div class="titulo"><h2>Link inválido</h2></div>
      <?= avisoAcesso('Este link não existe ou foi copiado pela metade. Use o link completo da mensagem.') ?>
<?php elseif ($vencido && !$feito): ?>
      <div class="titulo"><h2><?= e((string) $a['nome']) ?></h2></div>
      <?= avisoAcesso('Este link venceu (vale ' . Radar::VALIDADE_DIAS . ' dias). Um novo chega na próxima conferência, ou fale com a equipe de turismo de ' . $a['destino'] . '.') ?>
<?php elseif ($feito || $a['resposta'] === 'igual'): ?>
      <div class="titulo"><h2>Obrigado!</h2><p><?= e((string) $a['nome']) ?></p></div>
      <?= avisoAcesso('', 'Pronto: os dados ficam como estão e o seu negócio continua em destaque no guia de ' . $a['destino'] . '.') ?>
<?php else: ?>
      <div class="titulo"><h2><?= e((string) $a['nome']) ?></h2><p>Assim o turista vê no guia hoje:</p></div>
      <?= avisoAcesso($erro) ?>
      <dl class="dados-ficha">
        <?php foreach ($dados as $rotulo => $valor): ?><dt><?= e($rotulo) ?></dt><dd><?= e($valor) ?></dd><?php endforeach; ?>
      </dl>
      <form method="post" action="/confirmar" class="botoes-radar">
        <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="t" value="<?= e($token) ?>">
        <button type="submit" class="botao" name="resposta" value="igual"><?= icone('check') ?>Está tudo igual</button>
        <button type="submit" class="botao claro" name="resposta" value="atualizar"><?= icone('lapis') ?>Preciso atualizar</button>
      </form>
      <p class="ajuda">Para atualizar, você entra como responsável pelo lugar (ou pede o acesso, se ainda não tem). A equipe de turismo confere antes de mudar no guia.</p>
<?php endif; ?>
    </div>
<?php acessoFim();
