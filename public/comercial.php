<?php
/**
 * Comercial: a tabela de preços por porte de município, a implantação, a
 * assinatura, o período de avaliação e o convite da prévia do QR.
 * Softsul Software
 *
 * Só na visão da plataforma, para a equipe Softsul. Vale para todos os
 * destinos (app/Comercial.php). Valor em branco quer dizer "a combinar".
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Comercial.php';

Auth::exigirLogin();
Auth::exigirPermissao('configuracoes.ver');
if (!Auth::ehSoftsul() || Empresa::id()) {
    paginaErro(403, 'Esta configuração é da visão da plataforma.', 'Troque para Plataforma InvenTour no seletor de destino.');
}
$podeEditar = Auth::pode('configuracoes.editar');
$dados = Comercial::dados();
$erro = '';
// "1.200" digitado sem centavos é mil e duzentos, não um real e vinte.
$valorDe = function ($v): ?string {
    $t = trim((string) $v);
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $t)) {
        $t = str_replace('.', '', $t);
    }
    $n = moedaParaBanco($t);
    return $n !== null && (float) $n > 0 ? number_format((float) $n, 2, '.', '') : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!$podeEditar) {
        $erro = 'Você não pode alterar a política comercial.';
    } else {
        $faixas = [];
        foreach ($dados['faixas'] as $i => $f) {
            $faixas[] = ['nome' => $f['nome'], 'ate' => $f['ate'],
                         'implantacao' => $valorDe($_POST['implantacao'][$i] ?? ''),
                         'mensal' => $valorDe($_POST['mensal'][$i] ?? ''),
                         'anual' => $valorDe($_POST['anual'][$i] ?? '')];
        }
        $dias = (int) ($_POST['dias_avaliacao'] ?? 30);
        $email = mb_strtolower(trim((string) ($_POST['contato_email'] ?? '')));
        if ($dias < 1 || $dias > 365) {
            $erro = 'O período de avaliação vai de 1 a 365 dias.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'O e-mail comercial não parece válido.';
        } else {
            $novo = [
                'faixas'           => json_encode($faixas, JSON_UNESCAPED_UNICODE),
                'dias_avaliacao'   => (string) $dias,
                'convite'          => mb_substr(trim((string) ($_POST['convite'] ?? '')), 0, 600),
                'contato_email'    => $email,
                'contato_whatsapp' => telefoneParaBanco($_POST['contato_whatsapp'] ?? null) ?? '',
            ];
            Ajustes::salvar('comercial', $novo, 0, (int) Auth::usuarioOuNulo()['id']);
            Auditoria::registrar('alterou', 'ajustes', null, 'alterou a política comercial (preços, avaliação e convite)', null, $novo);
            avisar('Política comercial salva.');
            redirecionar('/configuracoes/comercial');
        }
    }
}
$ro = $podeEditar ? '' : ' readonly';
$moeda = fn ($v): string => $v === null || $v === '' ? '' : number_format((float) $v, 2, ',', '.');

paginaInicio('Comercial', 'configuracoes');
?>
<div class="trilha"><a href="/configuracoes">Configurações</a><?= icone('seta') ?><span>Comercial</span></div>
<?php if ($erro !== ''): ?><p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p><?php endif; ?>
<form method="post" action="/configuracoes/comercial">
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <div class="cab-pag">
    <div><h1>Comercial</h1><p>Preços por porte de município, avaliação gratuita e o convite que aparece no QR do caderno. Valem para todos os destinos.</p></div>
    <?php if ($podeEditar): ?><div class="acoes"><button type="submit" class="botao"><?= icone('check') ?>Salvar</button></div><?php endif; ?>
  </div>

  <div class="cartao">
    <h2 class="titulo-secao">Tabela de preços</h2>
    <p class="ajuda">O porte sai da população do município sede (IBGE). Em branco, o valor fica a combinar.</p>
    <div class="rolagem">
      <table class="dados">
        <thead><tr><th>Porte do município</th><th class="num">Implantação (R$)</th><th class="num">Assinatura mensal (R$)</th><th class="num">Assinatura anual (R$)</th></tr></thead>
        <tbody>
        <?php foreach ($dados['faixas'] as $i => $f): ?>
          <tr>
            <td class="elastica"><b><?= e((string) $f['nome']) ?></b></td>
            <td><div class="entrada"><input type="text" class="moeda" name="implantacao[<?= $i ?>]" inputmode="decimal" aria-label="Implantação, <?= e((string) $f['nome']) ?>" placeholder="a combinar" value="<?= e($moeda($f['implantacao'] ?? null)) ?>"<?= $ro ?>></div></td>
            <td><div class="entrada"><input type="text" class="moeda" name="mensal[<?= $i ?>]" inputmode="decimal" aria-label="Assinatura mensal, <?= e((string) $f['nome']) ?>" placeholder="a combinar" value="<?= e($moeda($f['mensal'] ?? null)) ?>"<?= $ro ?>></div></td>
            <td><div class="entrada"><input type="text" class="moeda" name="anual[<?= $i ?>]" inputmode="decimal" aria-label="Assinatura anual, <?= e((string) $f['nome']) ?>" placeholder="a combinar" value="<?= e($moeda($f['anual'] ?? null)) ?>"<?= $ro ?>></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="cartao">
    <h2 class="titulo-secao">Avaliação gratuita</h2>
    <div class="grade">
      <div class="campo c-4">
        <label for="dias_avaliacao">Período de avaliação (dias)</label>
        <div class="entrada"><input type="number" id="dias_avaliacao" name="dias_avaliacao" min="1" max="365" value="<?= (int) $dados['dias_avaliacao'] ?>"<?= $ro ?>></div>
        <span class="ajuda">O teste do destino antes da licença: o portal abre e o QR do caderno mostra a ficha viva. É o prazo que o destino ganha ao passar para Avaliação.</span>
      </div>
    </div>
  </div>

  <div class="cartao">
    <h2 class="titulo-secao">Convite do QR</h2>
    <p class="ajuda">No modo projeto, quem lê o QR do caderno vê a prévia bloqueada da ficha (foto e título) com este convite e o contato comercial.</p>
    <div class="grade">
      <div class="campo c-12">
        <label for="convite">Texto do convite</label>
        <div class="entrada"><textarea id="convite" name="convite" rows="3" maxlength="600"<?= $ro ?>><?= e((string) $dados['convite']) ?></textarea></div>
      </div>
      <div class="campo c-6">
        <label for="contato_email">E-mail comercial</label>
        <div class="entrada"><?= icone('emails') ?><input type="email" id="contato_email" name="contato_email" maxlength="150" value="<?= e((string) $dados['contato_email']) ?>"<?= $ro ?>></div>
      </div>
      <div class="campo c-6">
        <label for="contato_whatsapp">WhatsApp comercial</label>
        <div class="entrada"><input type="text" id="contato_whatsapp" name="contato_whatsapp" class="telefone" inputmode="tel" maxlength="16" value="<?= e(formatarTelefone((string) $dados['contato_whatsapp'])) ?>"<?= $ro ?>></div>
      </div>
    </div>
  </div>
</form>
<?php paginaFim();
