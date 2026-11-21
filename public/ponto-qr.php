<?php
/**
 * Um ponto de QR, em modal: título, onde o cartaz fica, para onde leva e o
 * apelido (só na criação; depois de impresso, não muda). Salvar põe o
 * cartaz na fila. Softsul Software
 *   /ponto-qr      ponto novo
 *   /ponto-qr/5    ponto existente
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/PontosQr.php';
require_once __DIR__ . '/../app/Portal.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('phygital.ver');
$pdo = Database::conexao();
$eid = (int) $empresa['id'];
$id = (int) ($_GET['id'] ?? 0);
$podeEditar = Auth::pode($id ? 'phygital.editar' : 'phygital.criar');
$p = ['id' => 0, 'apelido' => '', 'titulo' => '', 'destino_tipo' => 'inicio', 'destino_ref' => '', 'local_texto' => '', 'ativo' => 1];
if ($id) {
    $q = $pdo->prepare('SELECT * FROM qr_pontos WHERE id = ? AND empresa_id = ?');
    $q->execute([$id, $eid]);
    $p = $q->fetch() ?: negarRegistro();
}
$q = $pdo->prepare('SELECT id, titulo FROM roteiros WHERE empresa_id = ? ORDER BY titulo');
$q->execute([$eid]);
$roteiros = $q->fetchAll(PDO::FETCH_KEY_PAIR);
$erro = '';
$aqui = $id ? endereco('ponto-qr', $id, (string) $p['titulo']) : '/ponto-qr';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeEditar) {
        $erro = 'A página ficou aberta tempo demais, ou você não pode alterar os pontos de QR.';
    } else {
        $tipo = array_key_exists((string) ($_POST['destino_tipo'] ?? ''), PontosQr::DESTINOS) ? (string) $_POST['destino_tipo'] : 'inicio';
        $ref = match ($tipo) {
            'ficha'   => (string) (int) ($_POST['item'] ?? $p['destino_ref']),
            'roteiro' => isset($roteiros[(int) ($_POST['roteiro'] ?? 0)]) ? (string) (int) $_POST['roteiro'] : '',
            'pilar'   => isset(Portal::PILARES[(string) ($_POST['pilar'] ?? '')]) ? (string) $_POST['pilar'] : '',
            default   => '',
        };
        $campos = ['titulo' => mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 150), 'destino_tipo' => $tipo, 'destino_ref' => $ref ?: null,
                   'local_texto' => mb_substr(trim((string) ($_POST['local_texto'] ?? '')), 0, 200) ?: null, 'ativo' => empty($_POST['ativo']) ? 0 : 1];
        $apelido = $id ? (string) $p['apelido'] : apelido((string) ($_POST['apelido'] ?? '') ?: $campos['titulo'], 40);
        $ja = $pdo->prepare('SELECT 1 FROM qr_pontos WHERE empresa_id = ? AND apelido = ? AND id <> ?');
        $ja->execute([$eid, $apelido, $id]);
        if ($campos['titulo'] === '') {
            $erro = 'Dê um título ao ponto: é o que sai em letras grandes no cartaz.';
        } elseif (in_array($tipo, ['ficha', 'roteiro', 'pilar'], true) && !$ref) {
            $erro = 'Escolha para onde o QR leva.';
        } elseif (!$id && (strlen($apelido) < 2 || $ja->fetchColumn())) {
            $erro = 'Esse apelido já existe ou é curto demais. Escolha outro.';
        } else {
            if ($id) {
                $pdo->prepare('UPDATE qr_pontos SET ' . implode(' = ?, ', array_keys($campos)) . ' = ? WHERE id = ? AND empresa_id = ?')->execute([...array_values($campos), $id, $eid]);
                Auditoria::registrar('alterou', 'qr_pontos', $id, 'alterou o ponto de QR ' . $apelido, null, $campos, $eid);
            } else {
                $pdo->prepare('INSERT INTO qr_pontos (empresa_id, apelido, ' . implode(', ', array_keys($campos)) . ', criado_em, criado_por) VALUES (?, ?' . str_repeat(', ?', count($campos)) . ', NOW(), ?)')
                    ->execute([$eid, $apelido, ...array_values($campos), (int) Auth::usuarioOuNulo()['id']]);
                $id = (int) $pdo->lastInsertId();
                Auditoria::registrar('criou', 'qr_pontos', $id, 'criou o ponto de QR ' . $apelido, null, $campos, $eid);
            }
            require_once __DIR__ . '/../app/Fila.php';
            Fila::enfileirar('qr_cartaz', ['id' => $id], null, $eid);
            avisar('Ponto salvo. O cartaz fica pronto em um minuto.');
            fichaSalva('/phygital');
        }
        $p = array_merge($p, $campos, ['apelido' => $apelido]);
    }
}
$itemAtual = null;
if ($p['destino_tipo'] === 'ficha' && $p['destino_ref']) {
    $q = $pdo->prepare('SELECT nome FROM inventario_itens WHERE id = ? AND empresa_id = ?');
    $q->execute([(int) $p['destino_ref'], $eid]);
    $itemAtual = $q->fetchColumn() ?: null;
}
$csrf = Auth::tokenCsrf();
$ro = $podeEditar ? '' : ' readonly';
paginaFicha($p['titulo'] ?: 'Novo ponto de QR', 'phygital');
?>
<?php if ($erro !== ''): ?><p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p><?php endif; ?>
<form method="post" action="<?= e($aqui) ?>">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <div class="cab-pag">
    <div><h1 data-titulo><?= e($p['titulo'] ?: 'Novo ponto de QR') ?></h1>
      <?php if ($id): ?><p class="dado"><?= e(preg_replace('#^https://#', '', PontosQr::url($empresa, (string) $p['apelido']))) ?></p><?php endif; ?></div>
    <?php if ($podeEditar): ?><div class="acoes"><button type="submit" class="botao"><?= icone('check') ?>Salvar</button></div><?php endif; ?>
  </div>
  <div class="cartao">
    <div class="grade">
      <div class="campo c-8"><label for="titulo">Título no cartaz <span class="obrig">obrigatório</span></label>
        <div class="entrada"><input type="text" id="titulo" name="titulo" maxlength="150" placeholder="Bem-vindo a Pato Branco" value="<?= e((string) $p['titulo']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-4"><label for="apelido">Apelido no endereço</label>
        <div class="entrada"><input type="text" id="apelido" name="apelido" maxlength="40" placeholder="rodoviaria" value="<?= e((string) $p['apelido']) ?>"<?= $id ? ' readonly' : $ro ?>></div>
        <span class="ajuda"><?= $id ? 'Não muda: é o que está impresso.' : 'Letras e hífen. Em branco, sai do título.' ?></span></div>
      <div class="campo c-12"><label for="local_texto">Onde o cartaz fica</label>
        <div class="entrada"><input type="text" id="local_texto" name="local_texto" maxlength="200" placeholder="Saguão da rodoviária, ao lado do guichê" value="<?= e((string) $p['local_texto']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-6"><label for="destino_tipo">O QR leva para</label>
        <div class="entrada"><select id="destino_tipo" name="destino_tipo"<?= $podeEditar ? '' : ' disabled' ?>>
          <?php foreach (PontosQr::DESTINOS as $k => $t): ?><option value="<?= $k ?>"<?= $p['destino_tipo'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div></div>
      <div class="campo c-6"><label for="pilar">Pilar</label>
        <div class="entrada"><select id="pilar" name="pilar"<?= $podeEditar ? '' : ' disabled' ?>><option value="">Escolha, se for um pilar</option>
          <?php foreach (Portal::PILARES as $k => $pl): ?><option value="<?= $k ?>"<?= $p['destino_tipo'] === 'pilar' && $p['destino_ref'] === $k ? ' selected' : '' ?>><?= e($pl[0]) ?></option><?php endforeach; ?></select></div></div>
      <div class="campo c-6"><label for="roteiro">Roteiro</label>
        <div class="entrada"><select id="roteiro" name="roteiro"<?= $podeEditar ? '' : ' disabled' ?>><option value="">Escolha, se for um roteiro</option>
          <?php foreach ($roteiros as $rid => $rt): ?><option value="<?= (int) $rid ?>"<?= $p['destino_tipo'] === 'roteiro' && (int) $p['destino_ref'] === (int) $rid ? ' selected' : '' ?>><?= e((string) $rt) ?></option><?php endforeach; ?></select></div></div>
      <div class="campo c-6"><label for="item_texto">Lugar</label>
        <?= seletorBuscavel(['campo' => 'item', 'fonte' => '/api/itens', 'valor' => $p['destino_tipo'] === 'ficha' ? (string) $p['destino_ref'] : '',
            'texto' => (string) $itemAtual, 'oque' => 'o lugar', 'vazio' => 'Se for a ficha de um lugar', 'travado' => !$podeEditar]) ?></div>
      <div class="campo c-12"><?= interruptor('ativo', 'Ponto ativo', (bool) $p['ativo'], 'Lendo o QR, a pessoa vai para a página escolhida', 'Desativado: o QR leva ao início do portal', !$podeEditar) ?></div>
    </div>
  </div>
</form>
<?php paginaFichaFim();
