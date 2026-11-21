<?php
/**
 * Um roteiro do destino, em modal: título, tipo, perfil de viajante,
 * descrição, duração, publicar e as paradas, tudo na mesma tela e salvo de
 * uma vez. A parada entra pela busca do lugar e vai para o fim; a ordem se
 * ajusta arrastando (ou pelas setas do teclado na alça). Cada parada tem a
 * hora de chegada, o tempo no lugar e uma dica. Só lugares não arquivados.
 *   /roteiro       roteiro novo
 *   /roteiro/5     roteiro existente
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/UmDia.php';

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
$tipos = ['permanente' => 'Permanente', 'evento' => 'De evento', 'tribo' => 'Por perfil de viajante', 'um_dia' => 'Um dia aqui'];
$tribos = $pdo->query('SELECT id, nome FROM tribos WHERE ativo = 1 ORDER BY ordem')->fetchAll(PDO::FETCH_KEY_PAIR);
$erro = '';
$aqui = $id ? endereco('roteiro', $id, (string) $r['titulo']) : '/roteiro';

/** As paradas como estão no banco, com o que a tela mostra de cada lugar. */
$lerParadas = function (int $rid) use ($pdo): array {
    $q = $pdo->prepare('SELECT ri.item_id, ri.nota, ri.hora, ri.duracao_min, ri.deslocamento_min, i.nome, i.situacao, i.latitude, i.longitude, f.codigo FROM roteiro_itens ri
                        JOIN inventario_itens i ON i.id = ri.item_id JOIN inventario_fichas f ON f.id = i.ficha_id WHERE ri.roteiro_id = ? ORDER BY ri.ordem');
    $q->execute([$rid]);
    return $q->fetchAll();
};
$paradas = $id ? $lerParadas($id) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? 'salvar');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeEditar) {
        $erro = 'A página ficou aberta tempo demais, ou você não pode alterar roteiros.';
    } elseif ($acao === 'excluir' && $id && Auth::pode('roteiros.excluir')) {
        $pdo->prepare('DELETE FROM roteiros WHERE id = ? AND empresa_id = ?')->execute([$id, $eid]);
        Auditoria::registrar('excluiu', 'roteiros', $id, 'excluiu o roteiro ' . $r['titulo'], null, null, $eid);
        avisar('Roteiro excluído.');
        fichaSalva('/roteiros');
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
        // As paradas vêm na ordem da tela: item, hora, tempo e dica alinhados.
        $itens = array_map('intval', (array) ($_POST['parada_item'] ?? []));
        $novas = [];
        $ok = $pdo->prepare("SELECT i.id, i.nome, i.situacao, i.latitude, i.longitude, f.codigo FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                             WHERE i.id = ? AND i.empresa_id = ? AND i.situacao <> 'arquivada'");
        foreach ($itens as $k => $iid) {
            if (!$iid || isset($novas[$iid])) {
                continue;
            }
            $ok->execute([$iid, $eid]);
            $lugar = $ok->fetch();
            if (!$lugar) {
                continue;
            }
            $min = (int) ($_POST['parada_tempo'][$k] ?? 0);
            $desl = trim((string) ($_POST['parada_desloc'][$k] ?? ''));
            $novas[$iid] = ['item_id' => $iid, 'nome' => $lugar['nome'], 'situacao' => $lugar['situacao'], 'codigo' => $lugar['codigo'],
                            'latitude' => $lugar['latitude'], 'longitude' => $lugar['longitude'],
                            'hora' => UmDia::hora($_POST['parada_hora'][$k] ?? null), 'duracao_min' => $min > 0 ? min($min, 1440) : null,
                            'deslocamento_min' => ctype_digit($desl) ? min((int) $desl, 600) : null,
                            'nota' => mb_substr(trim((string) ($_POST['parada_nota'][$k] ?? '')), 0, 300) ?: null];
        }
        // A hora de chegada é calculada: a primeira parada dá o início; cada
        // seguinte chega na anterior + o tempo nela + o deslocamento até aqui
        // (estimado pela distância quando o redator não ajustou).
        $novas = array_values($novas);
        foreach ($novas as $k => &$p) {
            if ($k === 0) {
                $p['deslocamento_min'] = null;
                continue;
            }
            // Deslocamento vazio é o estimado (fica null no banco e se refaz se a ordem mudar).
            $ant = $novas[$k - 1];
            $desloc = $p['deslocamento_min'] ?? UmDia::deslocamento(UmDia::metros($ant, $p));
            $p['hora'] = $ant['hora'] !== null ? UmDia::somar($ant['hora'], (int) $ant['duracao_min'] + $desloc) : null;
        }
        unset($p);
        if ($campos['titulo'] === '') {
            $erro = 'Dê um título ao roteiro.';
        } else {
            $pdo->beginTransaction();
            try {
                if ($id) {
                    $pdo->prepare('UPDATE roteiros SET ' . implode(' = ?, ', array_keys($campos)) . ' = ?, atualizado_em = NOW() WHERE id = ? AND empresa_id = ?')
                        ->execute([...array_values($campos), $id, $eid]);
                } else {
                    $pdo->prepare('INSERT INTO roteiros (empresa_id, ' . implode(', ', array_keys($campos)) . ', criado_em, criado_por) VALUES (?' . str_repeat(', ?', count($campos)) . ', NOW(), ?)')
                        ->execute([$eid, ...array_values($campos), (int) Auth::usuarioOuNulo()['id']]);
                }
                $novoId = $id ?: (int) $pdo->lastInsertId();
                $pdo->prepare('DELETE FROM roteiro_itens WHERE roteiro_id = ?')->execute([$novoId]);
                $ins = $pdo->prepare('INSERT INTO roteiro_itens (roteiro_id, item_id, empresa_id, ordem, hora, duracao_min, deslocamento_min, nota) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $n = 0;
                foreach ($novas as $p) {
                    $ins->execute([$novoId, $p['item_id'], $eid, ++$n, $p['hora'], $p['duracao_min'], $p['deslocamento_min'], $p['nota']]);
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            $resumo = $campos + ['paradas' => array_map(fn ($p) => trim(($p['hora'] ?? '') . ' ' . $p['nome']), $novas)];
            Auditoria::registrar($id ? 'alterou' : 'criou', 'roteiros', $novoId, ($id ? 'alterou' : 'criou') . ' o roteiro ' . $campos['titulo'], null, $resumo, $eid);
            avisar($id ? 'Roteiro salvo.' : 'Roteiro criado.');
            fichaSalva(endereco('roteiro', $novoId, $campos['titulo']));
        }
        $r = array_merge($r, $campos);
        $paradas = $novas;
    }
}

$csrf = Auth::tokenCsrf();
$ro = $podeEditar ? '' : ' readonly';
$homologado = fn (string $s) => in_array($s, ['homologada', 'desatualizada'], true);
/** Uma linha de parada (também o molde que o inventour.js copia ao acrescentar). */
$linha = function (array $p) use ($ro, $podeEditar, $homologado): string {
    ob_start(); ?>
    <li class="parada-linha" data-parada>
      <?php if ($podeEditar): ?><button type="button" class="parada-alca" data-alca aria-label="Arrastar para mudar a ordem (ou use as setas)" data-balao-titulo="Mudar a ordem" data-balao="Arraste para cima ou para baixo. Pelo teclado: setas para cima e para baixo."><?= icone('arrastar') ?></button><?php endif; ?>
      <span class="parada-num" data-parada-num></span>
      <div class="parada-lugar"><b data-parada-nome><?= e((string) ($p['nome'] ?? '')) ?></b> <span class="dado" data-parada-codigo><?= e((string) ($p['codigo'] ?? '')) ?></span>
        <span class="pilula at" data-parada-pendente<?= $homologado((string) ($p['situacao'] ?? 'homologada')) ? ' hidden' : '' ?>>Ainda não homologado</span>
        <input type="hidden" name="parada_item[]" value="<?= (int) ($p['item_id'] ?? 0) ?>" data-parada-item></div>
      <input type="hidden" data-parada-lat value="<?= e((string) ($p['latitude'] ?? '')) ?>"><input type="hidden" data-parada-lng value="<?= e((string) ($p['longitude'] ?? '')) ?>">
      <div class="parada-campos">
        <label class="parada-campo parada-desloc"><span>Deslocamento até aqui</span>
          <span class="entrada com-sufixo"><input type="text" name="parada_desloc[]" data-parada-desloc inputmode="numeric" maxlength="3" autocomplete="off" value="<?= e((string) ($p['deslocamento_min'] ?? '')) ?>"<?= $ro ?>><span class="sufixo">min</span></span></label>
        <label class="parada-campo"><span data-parada-rotulo-hora>Chegada</span>
          <span class="entrada"><input type="text" name="parada_hora[]" data-hora data-parada-hora inputmode="numeric" maxlength="5" placeholder="00:00" autocomplete="off" value="<?= e((string) ($p['hora'] ?? '')) ?>"<?= $ro ?>></span></label>
        <label class="parada-campo"><span>Tempo no lugar</span>
          <span class="entrada com-sufixo"><input type="text" name="parada_tempo[]" data-parada-tempo inputmode="numeric" maxlength="4" autocomplete="off" value="<?= e((string) ($p['duracao_min'] ?? '')) ?>"<?= $ro ?>><span class="sufixo">min</span></span></label>
        <label class="parada-campo parada-dica"><span>Dica (opcional)</span>
          <span class="entrada"><input type="text" name="parada_nota[]" maxlength="300" value="<?= e((string) ($p['nota'] ?? '')) ?>"<?= $ro ?>></span></label>
      </div>
      <?php if ($podeEditar): ?><button type="button" class="parada-tirar" data-tirar-parada aria-label="Tirar do roteiro" data-balao-titulo="Tirar do roteiro" data-balao="A parada sai do roteiro quando você salvar."><?= icone('lixeira') ?></button><?php endif; ?>
    </li>
    <?php return (string) ob_get_clean();
};
paginaFicha($r['titulo'] ?: 'Novo roteiro', 'roteiros');
?>
<?php if ($erro !== ''): ?><p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p><?php endif; ?>
<?php // O portal público só mostra lugar homologado: quem publica precisa saber o que o visitante vê.
$visiveis = count(array_filter($paradas, fn ($p) => $homologado((string) ($p['situacao'] ?? ''))));
if ($r['publicado'] && $paradas && $visiveis < count($paradas)): ?>
  <p class="aviso atencao" role="status"><?= icone('alerta') ?><span><?= $visiveis === 0
    ? 'Publicado, mas ainda não aparece no portal: nenhuma das ' . plural(count($paradas), 'parada', 'paradas') . ' foi homologada, e o portal só mostra lugar homologado. Na prévia do portal (Início do painel) ele aparece inteiro.'
    : 'No portal aparecem ' . $visiveis . ' de ' . plural(count($paradas), 'parada', 'paradas') . ': as outras ainda não foram homologadas. Na prévia do portal aparece inteiro.' ?></span></p>
<?php endif; ?>
<?php if ($r['publicado'] && $r['tipo'] !== 'um_dia'): ?><p class="ajuda"><?= icone('calendario') ?> Este roteiro aparece em Roteiros no portal. Para entrar em Um dia aqui, o tipo é Um dia aqui.</p><?php endif; ?>
<form method="post" action="<?= e($aqui) ?>" id="form-roteiro">
  <button type="submit" class="oculto-visual" tabindex="-1" aria-hidden="true">Salvar</button>
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="salvar">
  <div class="cab-pag">
    <div><h1 data-titulo><?= e($r['titulo'] ?: 'Novo roteiro') ?></h1>
      <p><?= !$r['publicado'] ? '<span class="pilula nu">Rascunho</span>' : ($paradas && $visiveis === 0 ? '<span class="pilula at">Publicado, sem parada homologada</span>'
            : ($visiveis < count($paradas) ? '<span class="pilula at">No portal: ' . $visiveis . ' de ' . count($paradas) . '</span>' : '<span class="pilula ok">No portal</span>')) ?> · <span data-paradas-conta><?= plural(count($paradas), 'parada', 'paradas') ?></span></p></div>
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
          <?php foreach ($tipos as $k => $t): ?><option value="<?= $k ?>"<?= $r['tipo'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <span class="ajuda">Um dia aqui: a página do portal que sorteia um dos roteiros deste tipo. Você decide onde o dia começa, a hora de cada parada, o tempo em cada lugar e onde termina.</span></div>
      <div class="campo c-6"><label for="tribo_id">Perfil de viajante</label>
        <div class="entrada"><select id="tribo_id" name="tribo_id"<?= $podeEditar ? '' : ' disabled' ?>><option value="">Nenhum</option>
          <?php foreach ($tribos as $tid => $t): ?><option value="<?= (int) $tid ?>"<?= (int) $r['tribo_id'] === (int) $tid ? ' selected' : '' ?>><?= e((string) $t) ?></option><?php endforeach; ?></select></div>
        <span class="ajuda">Vale para o tipo Por perfil de viajante.</span></div>
      <div class="campo c-12"><label for="descricao">Descrição</label>
        <div class="entrada"><textarea id="descricao" name="descricao" rows="4" maxlength="4000"<?= $ro ?>><?= e((string) $r['descricao']) ?></textarea></div></div>
      <div class="campo c-12"><?= interruptor('publicado', 'Publicar no portal', (bool) $r['publicado'], 'No portal, com a rota no mapa', 'Rascunho: só no painel', !$podeEditar) ?></div>
    </div>
  </div>

  <div class="cartao" id="paradas" data-paradas>
    <h2 class="titulo-secao">Paradas</h2>
    <p class="ajuda">Na ordem da visita: a primeira é onde o roteiro começa e a última onde termina. Arraste pela alça para mudar a ordem. Na primeira, ponha a hora de início; as outras chegadas se calculam pelo tempo no lugar e pelo deslocamento, que vem estimado pela distância e você ajusta.</p>
    <p class="paradas-vazio" data-paradas-vazio<?= $paradas ? ' hidden' : '' ?>>Nenhuma parada ainda. Busque o lugar abaixo: ele entra no fim do roteiro.</p>
    <ol class="paradas-editor" data-paradas-lista>
      <?php foreach ($paradas as $p) { echo $linha($p); } ?>
    </ol>
    <?php if ($podeEditar): ?>
    <template data-parada-molde><?= $linha(['item_id' => 0, 'nome' => '', 'codigo' => '', 'situacao' => 'homologada']) ?></template>
    <div class="paradas-acrescentar">
      <label for="parada_nova_texto">Acrescentar parada</label>
      <?= seletorBuscavel(['campo' => 'parada_nova', 'fonte' => '/api/itens', 'oque' => 'o lugar', 'vazio' => 'Digite o nome do lugar', 'nada' => 'Nenhum lugar com esse nome.']) ?>
    </div>
    <?php endif; ?>
  </div>
</form>
<?php if ($id && $podeEditar && Auth::pode('roteiros.excluir')): ?>
<form method="post" action="<?= e($aqui) ?>" class="cartao cartao-perigo"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="excluir">
  <div class="cartao-perigo-linha"><p class="ajuda">Excluir tira o roteiro do painel e do portal. Os lugares continuam no inventário.</p>
    <button type="submit" class="botao perigo" data-confirmar="Excluir o roteiro <?= e((string) $r['titulo']) ?>?"><?= icone('lixeira') ?>Excluir o roteiro</button></div></form>
<?php endif; ?>
<?php paginaFichaFim();
