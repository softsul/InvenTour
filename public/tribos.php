<?php
/**
 * Tribos de consumo e nichos comportamentais (catálogo global, plataforma).
 * Softsul Software
 *
 * O documento 07 do pacote de especificação trouxe 5 macrocategorias, 21
 * tribos e as facilidades verificáveis de cada uma, e avisa que a lista não
 * é fechada: precisa de curadoria. Aqui a plataforma acrescenta tribo e
 * facilidade, e liga ou desliga o que não serve. Nada se apaga, porque os
 * itens do inventário apontam para as facilidades.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

exigirVisaoPlataforma();
Auth::exigirPermissao('tribos.ver');
$pdo = Database::conexao();
$podeEditar = Auth::pode('tribos.editar');

$chave = function (string $t): string {
    $t = iconv('UTF-8', 'ASCII//TRANSLIT', $t) ?: $t;
    return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($t)), '_');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeEditar) {
        avisar('A página ficou aberta tempo demais, ou você não pode alterar as tribos.', 'ruim');
    } elseif ($acao === 'facilidade') {
        $tid = (int) ($_POST['tribo_id'] ?? 0);
        $nome = mb_substr(trim((string) ($_POST['nome'] ?? '')), 0, 160);
        if ($nome !== '' && $tid) {
            $ordem = (int) $pdo->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM tribo_facilidades WHERE tribo_id = ' . $tid)->fetchColumn();
            $pdo->prepare('INSERT IGNORE INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES (?, ?, ?, ?)')
                ->execute([$tid, mb_substr($chave($nome), 0, 80), $nome, $ordem]);
            Auditoria::registrar('criou', 'tribo_facilidades', (int) $pdo->lastInsertId(), 'acrescentou a facilidade ' . $nome);
            avisar('Facilidade acrescentada.');
        }
    } elseif ($acao === 'tribo') {
        $nome = mb_substr(trim((string) ($_POST['nome'] ?? '')), 0, 100);
        $macro = mb_substr(trim((string) ($_POST['macro'] ?? '')), 0, 80);
        if ($nome !== '' && $macro !== '') {
            $ordem = (int) $pdo->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM tribos')->fetchColumn();
            $pdo->prepare('INSERT IGNORE INTO tribos (macro, chave, nome, descricao, ordem) VALUES (?, ?, ?, ?, ?)')
                ->execute([$macro, mb_substr($chave($nome), 0, 60), $nome, mb_substr(trim((string) ($_POST['descricao'] ?? '')), 0, 300) ?: null, $ordem]);
            Auditoria::registrar('criou', 'tribos', (int) $pdo->lastInsertId(), 'acrescentou a tribo ' . $nome);
            avisar('Tribo acrescentada. Agora cadastre as facilidades dela.');
        }
    } elseif (in_array($acao, ['chave_tribo', 'chave_facilidade'], true)) {
        // A chave liga e desliga (interruptor): grava sem recarregar a tela.
        $tabela = $acao === 'chave_tribo' ? 'tribos' : 'tribo_facilidades';
        $ativo = empty($_POST['ativo']) ? 0 : 1;
        $pdo->prepare("UPDATE {$tabela} SET ativo = ? WHERE id = ?")->execute([$ativo, (int) ($_POST['id'] ?? 0)]);
        Auditoria::registrar('alterou', $tabela, (int) ($_POST['id'] ?? 0), ($ativo ? 'ligou' : 'desligou') . ($acao === 'chave_tribo' ? ' a tribo' : ' a facilidade'));
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
    } elseif (in_array($acao, ['ligar_tribo', 'desligar_tribo', 'ligar_facilidade', 'desligar_facilidade'], true)) {
        $tabela = str_ends_with($acao, 'tribo') ? 'tribos' : 'tribo_facilidades';
        $pdo->prepare("UPDATE {$tabela} SET ativo = ? WHERE id = ?")->execute([str_starts_with($acao, 'ligar') ? 1 : 0, (int) ($_POST['id'] ?? 0)]);
        Auditoria::registrar('alterou', $tabela, (int) ($_POST['id'] ?? 0), str_replace('_', ' ', $acao));
    }
    redirecionar('/tribos');
}

$tribos = $pdo->query('SELECT t.*, (SELECT COUNT(DISTINCT i.item_id) FROM inventario_item_facilidades i JOIN tribo_facilidades f ON f.id = i.facilidade_id
                                     WHERE f.tribo_id = t.id) AS itens FROM tribos t ORDER BY t.ordem')->fetchAll();
$facilidades = [];
foreach ($pdo->query('SELECT * FROM tribo_facilidades ORDER BY ordem, id') as $f) {
    $facilidades[(int) $f['tribo_id']][] = $f;
}
$macros = array_values(array_unique(array_column($tribos, 'macro')));
$csrf = Auth::tokenCsrf();
paginaInicio('Tribos de consumo', 'tribos');
?>
<div class="cab-pag"><div><h1>Tribos de consumo</h1>
  <p>Perfis de viajantes e as facilidades que comprovam que um lugar os atende. O item ganha o selo da tribo no portal com duas facilidades ou mais. Lista aberta: acrescente e desligue com curadoria.</p></div></div>

<?php $macroAtual = null; foreach ($tribos as $t): ?>
  <?php if ($t['macro'] !== $macroAtual): $macroAtual = $t['macro']; ?><h2 class="titulo-secao"><?= e((string) $macroAtual) ?></h2><?php endif; ?>
  <details class="cartao tribo<?= $t['ativo'] ? '' : ' inativa' ?>">
    <summary><?= icone((string) $t['icone']) ?><b><?= e((string) $t['nome']) ?></b>
      <span class="nulo"><?= plural(count($facilidades[(int) $t['id']] ?? []), 'facilidade', 'facilidades') ?> · <?= plural((int) $t['itens'], 'item', 'itens') ?></span>
      <?= $t['ativo'] ? '' : '<span class="pilula ruim">Desligada</span>' ?></summary>
    <?php if ($t['descricao']): ?><p class="ajuda"><?= e((string) $t['descricao']) ?></p><?php endif; ?>
    <?php /* Liga e desliga em chave, como o tema escuro (Luiz, 21/11/2026). */ ?>
    <form method="post" action="/tribos" class="chave-tribo" data-chave-grava>
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="chave_tribo"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
      <?= interruptor('ativo', 'A tribo', (bool) $t['ativo'], 'Ligada: aparece nas fichas e no portal', 'Desligada: fora das fichas e do portal', !$podeEditar) ?>
    </form>
    <ul class="chaves-facilidades">
      <?php foreach ($facilidades[(int) $t['id']] ?? [] as $f): ?>
        <li><form method="post" action="/tribos" data-chave-grava>
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="chave_facilidade"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
          <?= interruptor('ativo', (string) $f['nome'], (bool) $f['ativo'], 'Ligada', 'Desligada', !$podeEditar) ?>
        </form></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($podeEditar): ?>
      <form method="post" action="/tribos" class="filtros">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="facilidade"><input type="hidden" name="tribo_id" value="<?= (int) $t['id'] ?>">
        <div class="entrada"><?= icone('mais') ?><input type="text" name="nome" maxlength="160" placeholder="Nova facilidade" aria-label="Nova facilidade de <?= e((string) $t['nome']) ?>"></div>
        <button type="submit" class="botao claro mini">Acrescentar</button>
      </form>
    <?php endif; ?>
  </details>
<?php endforeach; ?>

<?php if ($podeEditar): ?>
<form method="post" action="/tribos" class="cartao" id="form-tribo">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="tribo">
  <h2 class="titulo-secao">Nova tribo</h2>
  <div class="grade">
    <div class="campo c-6"><label for="tribo-nome">Nome</label><div class="entrada"><input type="text" id="tribo-nome" name="nome" maxlength="100"></div></div>
    <div class="campo c-6"><label for="tribo-macro">Macrocategoria</label><div class="entrada"><select id="tribo-macro" name="macro">
      <?php foreach ($macros as $m): ?><option><?= e((string) $m) ?></option><?php endforeach; ?></select></div></div>
    <div class="campo c-12"><label for="tribo-desc">Perfil</label><div class="entrada"><input type="text" id="tribo-desc" name="descricao" maxlength="300"></div></div>
  </div>
  <div class="acoes"><button type="submit" class="botao"><?= icone('mais') ?>Acrescentar tribo</button></div>
</form>
<?php endif; ?>
<?php paginaFim();
