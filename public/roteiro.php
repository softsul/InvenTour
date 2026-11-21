<?php
/**
 * Um roteiro do destino, em modal: título, tipo, perfil de viajante,
 * descrição, duração, publicar, e as paradas em ordem (subir, descer,
 * tirar e a dica de cada uma). Só lugares que não estão arquivados.
 *   /roteiro       roteiro novo
 *   /roteiro/5     roteiro existente
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('roteiros.ver');
$pdo = Database::conexao();
$eid = (int) $empresa['id'];
$id = (int) ($_GET['id'] ?? 0);
$podeEditar = Auth::pode($id ? 'roteiros.editar' : 'roteiros.criar');
$r = ['id' => 0, 'titulo' => '', 'tipo' => 'permanente', 'tribo_id' => null, 'descricao' => '', 'duracao' => '', 'publicado' => 0];
if ($id) {
    $q = $pdo->prepare('SELECT * FROM roteiros WHERE id = ? AND empresa_id = ?');
    $q->execute([$id, $eid]);
    $r = $q->fetch() ?: negarRegistro();
}
$tipos = ['permanente' => 'Permanente', 'evento' => 'De evento', 'tribo' => 'Por perfil de viajante'];
$tribos = $pdo->query('SELECT id, nome FROM tribos WHERE ativo = 1 ORDER BY ordem')->fetchAll(PDO::FETCH_KEY_PAIR);
$erro = '';
$aqui = $id ? endereco('roteiro', $id, (string) $r['titulo']) : '/roteiro';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? 'salvar');
    // Os botões de mover ficam dentro da lista e mandam o formulário #mover
    // com o próprio nome (subir, descer, tirar) e o lugar no valor.
    foreach (['subir', 'descer', 'tirar'] as $mover) {
        if (isset($_POST[$mover])) {
            $acao = $mover;
            $_POST['item'] = $_POST[$mover];
        }
    }
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeEditar) {
        $erro = 'A página ficou aberta tempo demais, ou você não pode alterar roteiros.';
    } elseif ($acao === 'excluir' && $id && Auth::pode('roteiros.excluir')) {
        $pdo->prepare('DELETE FROM roteiros WHERE id = ? AND empresa_id = ?')->execute([$id, $eid]);
        Auditoria::registrar('excluiu', 'roteiros', $id, 'excluiu o roteiro ' . $r['titulo'], null, null, $eid);
        avisar('Roteiro excluído.');
        fichaSalva('/roteiros');
    } elseif (in_array($acao, ['parada', 'subir', 'descer', 'tirar'], true) && $id) {
        $item = (int) ($_POST['item'] ?? 0);
        $q = $pdo->prepare('SELECT item_id FROM roteiro_itens WHERE roteiro_id = ? ORDER BY ordem');
        $q->execute([$id]);
        $ordem = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
        if ($acao === 'parada') {
            $ok = $pdo->prepare("SELECT 1 FROM inventario_itens WHERE id = ? AND empresa_id = ? AND situacao <> 'arquivada'");
            $ok->execute([$item, $eid]);
            if ($ok->fetchColumn() && !in_array($item, $ordem, true)) {
                $pdo->prepare('INSERT INTO roteiro_itens (roteiro_id, item_id, empresa_id, ordem) VALUES (?, ?, ?, ?)')->execute([$id, $item, $eid, count($ordem) + 1]);
                avisar('Parada acrescentada.');
            }
        } else {
            $k = array_search($item, $ordem, true);
            if ($k !== false) {
                if ($acao === 'tirar') {
                    array_splice($ordem, $k, 1);
                    $pdo->prepare('DELETE FROM roteiro_itens WHERE roteiro_id = ? AND item_id = ?')->execute([$id, $item]);
                } else {
                    $outro = $acao === 'subir' ? $k - 1 : $k + 1;
                    if (isset($ordem[$outro])) {
                        [$ordem[$k], $ordem[$outro]] = [$ordem[$outro], $ordem[$k]];
                    }
                }
                foreach ($ordem as $n => $iid) {
                    $pdo->prepare('UPDATE roteiro_itens SET ordem = ? WHERE roteiro_id = ? AND item_id = ?')->execute([$n + 1, $id, $iid]);
                }
            }
        }
        $pdo->prepare('UPDATE roteiros SET atualizado_em = NOW() WHERE id = ?')->execute([$id]);
        redirecionar($aqui . '#paradas');
    } else {
        $campos = [
            'titulo'    => mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 150),
            'tipo'      => array_key_exists((string) ($_POST['tipo'] ?? ''), $tipos) ? (string) $_POST['tipo'] : 'permanente',
            'tribo_id'  => isset($tribos[(int) ($_POST['tribo_id'] ?? 0)]) ? (int) $_POST['tribo_id'] : null,
            'descricao' => mb_substr(trim((string) ($_POST['descricao'] ?? '')), 0, 4000) ?: null,
            'duracao'   => mb_substr(trim((string) ($_POST['duracao'] ?? '')), 0, 60) ?: null,
            'publicado' => empty($_POST['publicado']) ? 0 : 1,
        ];
        if ($campos['tipo'] !== 'tribo') {
            $campos['tribo_id'] = null;
        }
        // Notas das paradas vêm no mesmo formulário.
        foreach ((array) ($_POST['nota'] ?? []) as $iid => $nota) {
            if ($id) {
                $pdo->prepare('UPDATE roteiro_itens SET nota = ? WHERE roteiro_id = ? AND item_id = ?')
                    ->execute([mb_substr(trim((string) $nota), 0, 300) ?: null, $id, (int) $iid]);
            }
        }
        if ($campos['titulo'] === '') {
            $erro = 'Dê um título ao roteiro.';
        } else {
            if ($id) {
                $pdo->prepare('UPDATE roteiros SET ' . implode(' = ?, ', array_keys($campos)) . ' = ?, atualizado_em = NOW() WHERE id = ? AND empresa_id = ?')
                    ->execute([...array_values($campos), $id, $eid]);
                Auditoria::registrar('alterou', 'roteiros', $id, 'alterou o roteiro ' . $campos['titulo'], null, $campos, $eid);
            } else {
                $pdo->prepare('INSERT INTO roteiros (empresa_id, ' . implode(', ', array_keys($campos)) . ', criado_em, criado_por) VALUES (?' . str_repeat(', ?', count($campos)) . ', NOW(), ?)')
                    ->execute([$eid, ...array_values($campos), (int) Auth::usuarioOuNulo()['id']]);
                $id = (int) $pdo->lastInsertId();
                Auditoria::registrar('criou', 'roteiros', $id, 'criou o roteiro ' . $campos['titulo'], null, $campos, $eid);
            }
            avisar($id && !empty($_POST['primeira']) ? 'Roteiro criado. Agora acrescente as paradas.' : 'Roteiro salvo.');
            fichaSalva(endereco('roteiro', $id, $campos['titulo']));
        }
        $r = array_merge($r, $campos);
    }
}

$paradas = [];
if ($id) {
    $q = $pdo->prepare('SELECT ri.item_id, ri.nota, i.nome, i.situacao, f.codigo FROM roteiro_itens ri JOIN inventario_itens i ON i.id = ri.item_id
                        JOIN inventario_fichas f ON f.id = i.ficha_id WHERE ri.roteiro_id = ? ORDER BY ri.ordem');
    $q->execute([$id]);
    $paradas = $q->fetchAll();
}
$csrf = Auth::tokenCsrf();
$ro = $podeEditar ? '' : ' readonly';
paginaFicha($r['titulo'] ?: 'Novo roteiro', 'roteiros');
?>
<?php if ($erro !== ''): ?><p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p><?php endif; ?>
<form method="post" action="<?= e($aqui) ?>" id="form-roteiro">
  <button type="submit" class="oculto-visual" tabindex="-1" aria-hidden="true">Salvar</button>
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="salvar">
  <?php if (!$id): ?><input type="hidden" name="primeira" value="1"><?php endif; ?>
  <div class="cab-pag">
    <div><h1 data-titulo><?= e($r['titulo'] ?: 'Novo roteiro') ?></h1>
      <p><?= $r['publicado'] ? '<span class="pilula ok">No portal</span>' : '<span class="pilula nu">Rascunho</span>' ?> · <?= plural(count($paradas), 'parada', 'paradas') ?></p></div>
    <?php if ($podeEditar): ?><div class="acoes"><button type="submit" class="botao"><?= icone('check') ?>Salvar</button></div><?php endif; ?>
  </div>
  <div class="cartao">
    <div class="grade">
      <div class="campo c-8"><label for="titulo">Título <span class="obrig">obrigatório</span></label>
        <div class="entrada"><input type="text" id="titulo" name="titulo" maxlength="150" placeholder="Um dia no centro histórico" value="<?= e((string) $r['titulo']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-4"><label for="duracao">Duração</label>
        <div class="entrada"><input type="text" id="duracao" name="duracao" maxlength="60" placeholder="Meio dia, 2 dias" value="<?= e((string) $r['duracao']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-6"><label for="tipo">Tipo</label>
        <div class="entrada"><select id="tipo" name="tipo" data-revela="tribo"<?= $podeEditar ? '' : ' disabled' ?>>
          <?php foreach ($tipos as $k => $t): ?><option value="<?= $k ?>"<?= $r['tipo'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div></div>
      <div class="campo c-6"><label for="tribo_id">Perfil de viajante</label>
        <div class="entrada"><select id="tribo_id" name="tribo_id"<?= $podeEditar ? '' : ' disabled' ?>><option value="">Nenhum</option>
          <?php foreach ($tribos as $tid => $t): ?><option value="<?= (int) $tid ?>"<?= (int) $r['tribo_id'] === (int) $tid ? ' selected' : '' ?>><?= e((string) $t) ?></option><?php endforeach; ?></select></div>
        <span class="ajuda">Vale para o tipo Por perfil de viajante.</span></div>
      <div class="campo c-12"><label for="descricao">Descrição</label>
        <div class="entrada"><textarea id="descricao" name="descricao" rows="4" maxlength="4000"<?= $ro ?>><?= e((string) $r['descricao']) ?></textarea></div></div>
      <div class="campo c-12"><?= interruptor('publicado', 'Publicar no portal', (bool) $r['publicado'], 'No portal, com a rota no mapa', 'Rascunho: só no painel', !$podeEditar) ?></div>
    </div>
  </div>
  <?php if ($id): ?>
  <div class="cartao" id="paradas">
    <h2 class="titulo-secao">Paradas</h2>
    <?php if (!$paradas): ?><p class="ajuda">Nenhuma parada ainda. Busque o lugar abaixo e acrescente na ordem da visita.</p><?php endif; ?>
    <ol class="paradas-painel">
      <?php foreach ($paradas as $n => $p): ?>
        <li><div class="parada-cab"><b><?= e((string) $p['nome']) ?></b> <span class="dado"><?= e((string) $p['codigo']) ?></span>
          <?= in_array($p['situacao'], ['homologada', 'desatualizada'], true) ? '' : '<span class="pilula at" title="No portal só aparece depois de homologado">Ainda não homologado</span>' ?>
          <?php if ($podeEditar): ?><span class="parada-acoes">
            <button type="submit" form="mover" name="subir" value="<?= (int) $p['item_id'] ?>" class="botao claro mini" title="Subir"<?= $n === 0 ? ' disabled' : '' ?>>↑</button>
            <button type="submit" form="mover" name="descer" value="<?= (int) $p['item_id'] ?>" class="botao claro mini" title="Descer"<?= $n === count($paradas) - 1 ? ' disabled' : '' ?>>↓</button>
            <button type="submit" form="mover" name="tirar" value="<?= (int) $p['item_id'] ?>" class="botao claro mini" title="Tirar do roteiro"><?= icone('lixeira') ?></button></span><?php endif; ?></div>
          <div class="entrada"><input type="text" name="nota[<?= (int) $p['item_id'] ?>]" maxlength="300" placeholder="Dica desta parada (opcional)" value="<?= e((string) $p['nota']) ?>"<?= $ro ?>></div></li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php endif; ?>
</form>
<?php if ($id && $podeEditar): ?>
<form method="post" action="<?= e($aqui) ?>" id="mover"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"></form>
<form method="post" action="<?= e($aqui) ?>" class="cartao">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="parada">
  <h2 class="titulo-secao">Acrescentar parada</h2>
  <div class="grade"><div class="campo c-12">
    <?= seletorBuscavel(['campo' => 'item', 'fonte' => '/api/itens', 'obrigatorio' => true, 'oque' => 'o lugar', 'enviarAoEscolher' => true,
        'vazio' => 'Digite o nome do lugar', 'nada' => 'Nenhum lugar com esse nome.']) ?>
    <span class="ajuda">Escolhendo o lugar, ele entra no fim do roteiro.</span></div></div>
</form>
<?php if (Auth::pode('roteiros.excluir')): ?>
<form method="post" action="<?= e($aqui) ?>"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="excluir">
  <button type="submit" class="botao perigo" data-confirmar="Excluir o roteiro <?= e((string) $r['titulo']) ?>?"><?= icone('lixeira') ?>Excluir o roteiro</button></form>
<?php endif; ?>
<?php endif; ?>
<?php paginaFichaFim();
