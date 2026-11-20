<?php
/**
 * Uma ficha do Inventário da Oferta Turística, aberta em modal a partir de
 * Fichas e tipos.
 * Softsul Software
 *
 * Mostra a identificação oficial (códigos, categoria, grupo, forma, tipo no
 * EventCity, página no PDF de 2011) e deixa a plataforma ajustar o que é
 * texto de apoio: a orientação de preenchimento da ficha e a definição de
 * cada tipo, que aparecem como ajuda ao pesquisador. Também liga e desliga
 * tipos e acrescenta tipo novo ("Outros" que a prática pediu). Código,
 * categoria e ordem não mudam aqui: são da capitulação oficial.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';

exigirVisaoPlataforma();
Auth::exigirPermissao('fichas.ver');
$pdo = Database::conexao();
$id = (int) ($_GET['id'] ?? 0);
$q = $pdo->prepare('SELECT * FROM inventario_fichas WHERE id = ?');
$q->execute([$id]);
$f = $q->fetch() ?: negarRegistro();
$podeEditar = Auth::pode('fichas.editar');
$erro = '';

$tiposDaFicha = function () use ($pdo, $id): array {
    // Tipo e logo abaixo os subtipos dele (pai_id), na ordem da ficha.
    $q = $pdo->prepare('SELECT t.*, p.nome AS pai_nome FROM inventario_tipos t LEFT JOIN inventario_tipos p ON p.id = t.pai_id
                        WHERE t.ficha_id = ? ORDER BY COALESCE(p.ordem, t.ordem), t.pai_id IS NOT NULL, t.ordem');
    $q->execute([$id]);
    return $q->fetchAll();
};
$tipos = $tiposDaFicha();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!$podeEditar) {
        $erro = 'Você não pode alterar o catálogo de fichas.';
    } else {
        $novoNome = mb_substr(trim((string) ($_POST['novo_nome'] ?? '')), 0, 160);
        $nomesExistentes = array_map(fn ($t) => mb_strtolower((string) $t['nome']), array_filter($tipos, fn ($t) => !$t['pai_id']));
        if ($novoNome !== '' && in_array(mb_strtolower($novoNome), $nomesExistentes, true)) {
            $erro = 'Esta ficha já tem o tipo ' . $novoNome . '.';
        }
    }
    if ($erro === '') {
        $pdo->beginTransaction();
        $ajuda = trim((string) ($_POST['ajuda'] ?? '')) ?: null;
        $ativa = empty($_POST['ativo']) ? 0 : 1;
        $pdo->prepare('UPDATE inventario_fichas SET ajuda = ?, ativo = ? WHERE id = ?')->execute([$ajuda, $ativa, $id]);
        $mudou = $ajuda !== $f['ajuda'] || $ativa !== (int) $f['ativo'];
        $up = $pdo->prepare('UPDATE inventario_tipos SET definicao = ?, ativo = ? WHERE id = ? AND ficha_id = ?');
        foreach ($tipos as $t) {
            $def = trim((string) ($_POST['definicao'][$t['id']] ?? '')) ?: null;
            $at = empty($_POST['tipo_ativo'][$t['id']]) ? 0 : 1;
            if ($def !== $t['definicao'] || $at !== (int) $t['ativo']) {
                $up->execute([$def, $at, $t['id'], $id]);
                $mudou = true;
            }
        }
        if ($novoNome !== '') {
            $ordem = (int) $pdo->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM inventario_tipos WHERE ficha_id = ' . $id)->fetchColumn();
            $pdo->prepare('INSERT INTO inventario_tipos (ficha_id, nome, definicao, ordem, ativo) VALUES (?, ?, ?, ?, 1)')
                ->execute([$id, $novoNome, trim((string) ($_POST['novo_definicao'] ?? '')) ?: null, $ordem]);
            $mudou = true;
        }
        $pdo->commit();
        if ($mudou) {
            Auditoria::registrar('alterou', 'inventario_fichas', $id, 'alterou a ficha ' . $f['codigo'] . ' ' . $f['nome']
                . ($novoNome !== '' ? ' e acrescentou o tipo ' . $novoNome : ''));
        }
        avisar($mudou ? 'Ficha ' . $f['nome'] . ' salva.' : 'Nada mudou.');
        fichaSalva(endereco('ficha', $id, (string) $f['nome']));
    }
}

if (!emPopup()) {
    canonizar('ficha', $id, (string) $f['nome']);
}
$ro = $podeEditar ? '' : ' readonly';
$categorias = ['A' => 'Infraestrutura de apoio ao turismo', 'B' => 'Serviços e equipamentos turísticos', 'C' => 'Atrativos turísticos'];
$formas = ['municipio' => 'Do município (uma por destino)', 'pontual' => 'Pontual (endereço e coordenada)', 'linear' => 'Linear (extensão, sem endereço)'];
$eventcity = ['atrativo' => 'Atrativo', 'hospedagem' => 'Hospedagem', 'gastronomia' => 'Gastronomia',
              'espaco_eventos' => 'Espaço de eventos', 'servico_apoio' => 'Serviço de apoio'];
paginaFicha($f['nome'], 'fichas');
?>
<div class="trilha"><a href="/fichas">Fichas e tipos</a><?= icone('seta') ?><span><?= e((string) $f['codigo']) ?></span></div>
<?php if ($erro !== ''): ?>
  <p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p>
<?php endif; ?>

<form method="post" action="<?= e(endereco('ficha', $id, (string) $f['nome'])) ?>" id="form-ficha">
  <button type="submit" class="oculto-visual" tabindex="-1" aria-hidden="true">Salvar</button>
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <div class="cab-pag">
    <div>
      <h1 data-titulo><?= e((string) $f['nome']) ?></h1>
      <p><?= e('Ficha ' . $f['ordem'] . ' de 54 · ' . $f['codigo'] . ' · ' . $f['grupo']) ?></p>
    </div>
    <?php if ($podeEditar): ?>
      <div class="acoes"><button type="submit" class="botao"><?= icone('check') ?>Salvar</button></div>
    <?php endif; ?>
  </div>

  <div class="colunas">
    <div class="cartao">
      <h2 class="titulo-secao">Identificação oficial</h2>
      <dl class="dados-ficha">
        <dt>Categoria</dt><dd><?= e($f['categoria'] . '. ' . ($categorias[$f['categoria']] ?? '')) ?></dd>
        <dt>Códigos do MTur</dt><dd class="dado"><?= e((string) $f['codigos_mtur']) ?></dd>
        <dt>Forma</dt><dd><?= e($formas[$f['forma']] ?? (string) $f['forma']) ?></dd>
        <dt>No EventCity</dt><dd><?= $f['eventcity'] ? e($eventcity[$f['eventcity']] ?? (string) $f['eventcity']) : 'Não é enviada' ?></dd>
        <dt>Página no PDF de 2011</dt><dd class="dado"><?= $f['pagina_2011'] ? (int) $f['pagina_2011'] : '-' ?></dd>
      </dl>
      <label class="check"><input type="checkbox" name="ativo" value="1"<?= $f['ativo'] ? ' checked' : '' ?><?= $podeEditar ? '' : ' disabled' ?>><i></i>Ficha em uso nos formulários</label>
    </div>

    <div class="cartao">
      <h2 class="titulo-secao">Orientação de preenchimento</h2>
      <div class="campo">
        <label for="ajuda">O que o pesquisador lê ao abrir a ficha</label>
        <div class="entrada"><textarea id="ajuda" name="ajuda" rows="5" maxlength="4000"<?= $ro ?>><?= e((string) $f['ajuda']) ?></textarea></div>
        <span class="ajuda">Base no manual do MTur de 2006: o que inventariar nesta ficha e o que vai em outra.</span>
      </div>
    </div>
  </div>

  <div class="cartao">
    <h2 class="titulo-secao">Tipos e subtipos (<?= count($tipos) ?>)</h2>
    <?php if (!$tipos): ?>
      <p class="ajuda">Nenhum tipo cadastrado nesta ficha.</p>
    <?php else: ?>
      <div class="rolagem">
        <table class="dados">
          <thead><tr><th>Código</th><th>Tipo</th><th class="elastica">Definição</th><th>Em uso</th></tr></thead>
          <tbody>
          <?php foreach ($tipos as $t): ?>
            <tr class="<?= $t['ativo'] ? '' : 'inativa' ?>">
              <td class="dado"><?= $t['codigo_mtur'] ? e((string) $t['codigo_mtur']) : '<span class="nulo">-</span>' ?></td>
              <td><?= $t['pai_id'] ? '<span class="nulo">' . e((string) $t['pai_nome']) . ':</span> ' . e((string) $t['nome']) : '<b>' . e((string) $t['nome']) . '</b>' ?></td>
              <td class="elastica"><input type="text" name="definicao[<?= (int) $t['id'] ?>]" maxlength="1000" aria-label="Definição de <?= e((string) $t['nome']) ?>"
                     value="<?= e((string) $t['definicao']) ?>"<?= $ro ?>></td>
              <td><label class="check"><input type="checkbox" name="tipo_ativo[<?= (int) $t['id'] ?>]" value="1"<?= $t['ativo'] ? ' checked' : '' ?><?= $podeEditar ? '' : ' disabled' ?> aria-label="<?= e((string) $t['nome']) ?> em uso"><i></i></label></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <?php if ($podeEditar): ?>
      <div class="grade">
        <div class="campo c-4">
          <label for="novo_nome">Novo tipo</label>
          <div class="entrada"><input type="text" id="novo_nome" name="novo_nome" maxlength="160"></div>
        </div>
        <div class="campo c-8">
          <label for="novo_definicao">Definição do novo tipo</label>
          <div class="entrada"><input type="text" id="novo_definicao" name="novo_definicao" maxlength="1000"></div>
          <span class="ajuda">Para o que a prática pedir e a ficha oficial não traz. Os tipos oficiais não se apagam: desligue o que não usar.</span>
        </div>
      </div>
    <?php endif; ?>
  </div>
</form>
<?php paginaFichaFim();
