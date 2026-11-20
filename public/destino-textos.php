<?php
/**
 * O destino: as narrativas (discurso oficial) e a linha do tempo do turismo.
 * Softsul Software
 *
 * Narrativas são os capítulos de abertura do caderno e as abas
 * institucionais do portal: apresentação, histórico e origens, economia do
 * turismo (com os indicadores e a fonte de cada um), aspectos geográficos e
 * infraestrutura básica. O texto costuma vir colado do Word: o editor aceita
 * e o servidor limpa (HtmlLimpo) antes de gravar.
 *
 * A linha do tempo guarda os marcos do turismo no destino (lei do COMTUR,
 * primeiro voo, grande evento, reconhecimento), com precisão de dia, mês ou
 * ano.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/HtmlLimpo.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('destino-textos.ver');
$pdo = Database::conexao();
$eid = (int) $empresa['id'];
$podeEditar = Auth::pode('destino-textos.editar');

const SECOES_NARRATIVA = [
    'apresentacao'   => 'Apresentação',
    'historico'      => 'Histórico e origens',
    'economia'       => 'Economia do turismo',
    'geografia'      => 'Aspectos geográficos',
    'infraestrutura' => 'Infraestrutura básica',
];
const INDICADORES_ECONOMIA = [
    'acts'      => 'Atividades características do turismo (ACTs)',
    'cadastur'  => 'Prestadores ativos no Cadastur',
    'empregos'  => 'Empregos formais no turismo',
    'vaf'       => 'VAF de alojamento e alimentação (R$)',
    'idat'      => 'Índice estadual (IDAT ou similar)',
    'dti'       => 'Nível de maturidade DTI',
];
const CATEGORIAS_MARCO = [
    'legislacao'     => 'Legislação e governança',
    'acesso'         => 'Infraestrutura e acesso',
    'evento'         => 'Grande evento',
    'reconhecimento' => 'Indicador ou reconhecimento',
    'mercado'        => 'Mercado e trade',
];
const PRECISOES = ['dia' => 'Dia', 'mes' => 'Mês', 'ano' => 'Ano'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeEditar) {
        avisar('A página ficou aberta tempo demais, ou você não pode alterar os textos do destino.', 'ruim');
    } elseif ($acao === 'narrativa') {
        $secao = array_key_exists((string) ($_POST['secao'] ?? ''), SECOES_NARRATIVA) ? (string) $_POST['secao'] : null;
        if ($secao) {
            $titulo = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 150) ?: SECOES_NARRATIVA[$secao];
            $html = HtmlLimpo::limpar(mb_substr((string) ($_POST['conteudo'] ?? ''), 0, 2000000));
            $indicadores = null;
            if ($secao === 'economia') {
                $ind = [];
                foreach (INDICADORES_ECONOMIA as $k => $_) {
                    $v = mb_substr(trim((string) ($_POST['ind_valor'][$k] ?? '')), 0, 40);
                    if ($v !== '') {
                        $ind[$k] = ['valor' => $v, 'fonte' => mb_substr(trim((string) ($_POST['ind_fonte'][$k] ?? '')), 0, 120),
                                    'ano' => mb_substr(trim((string) ($_POST['ind_ano'][$k] ?? '')), 0, 10)];
                    }
                }
                $indicadores = $ind ? json_encode($ind, JSON_UNESCAPED_UNICODE) : null;
            }
            $ordem = array_search($secao, array_keys(SECOES_NARRATIVA), true) + 1;
            $q = $pdo->prepare('SELECT id FROM destino_narrativas WHERE empresa_id = ? AND secao = ?');
            $q->execute([$eid, $secao]);
            $uid = (int) Auth::usuarioOuNulo()['id'];
            $noPortal = empty($_POST['no_portal']) ? 0 : 1;
            if ($nid = (int) $q->fetchColumn()) {
                $pdo->prepare('UPDATE destino_narrativas SET titulo = ?, conteudo_html = ?, indicadores = ?, no_portal = ?, atualizado_em = NOW(), atualizado_por = ?
                               WHERE id = ? AND empresa_id = ?')->execute([$titulo, $html, $indicadores, $noPortal, $uid, $nid, $eid]);
            } else {
                $pdo->prepare('INSERT INTO destino_narrativas (empresa_id, secao, titulo, conteudo_html, indicadores, no_portal, ordem, atualizado_em, atualizado_por)
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)')->execute([$eid, $secao, $titulo, $html, $indicadores, $noPortal, $ordem, $uid]);
            }
            Auditoria::registrar('alterou', 'destino_narrativas', null, 'alterou a narrativa ' . SECOES_NARRATIVA[$secao], null, null, $eid);
            avisar(SECOES_NARRATIVA[$secao] . ' salvo.');
        }
        redirecionar('/destino-textos' . (($secao ?? 'apresentacao') !== 'apresentacao' ? '#' . $secao : ''));
    } elseif ($acao === 'marco') {
        $precisao = array_key_exists((string) ($_POST['precisao'] ?? ''), PRECISOES) ? (string) $_POST['precisao'] : 'dia';
        $categoria = array_key_exists((string) ($_POST['categoria'] ?? ''), CATEGORIAS_MARCO) ? (string) $_POST['categoria'] : null;
        $data = (string) ($_POST['data'] ?? '');
        $ano = (int) ($_POST['ano'] ?? 0);
        if ($precisao === 'ano' && $ano >= 1500 && $ano <= 2100) {
            $data = $ano . '-01-01';
        } elseif ($precisao === 'mes' && preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['mes'] ?? ''))) {
            $data = $_POST['mes'] . '-01';
        }
        $titulo = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 150);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !$categoria || $titulo === '') {
            avisar('Marco sem data, categoria ou título. Nada foi gravado.', 'ruim');
        } else {
            $pdo->prepare('INSERT INTO destino_linha_tempo (empresa_id, data_marco, precisao, categoria, titulo, descricao, no_portal, criado_em, criado_por)
                           VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)')
                ->execute([$eid, $data, $precisao, $categoria, $titulo, mb_substr(trim((string) ($_POST['descricao'] ?? '')), 0, 4000) ?: null,
                           empty($_POST['no_portal']) ? 0 : 1, (int) Auth::usuarioOuNulo()['id']]);
            Auditoria::registrar('criou', 'destino_linha_tempo', (int) $pdo->lastInsertId(), 'acrescentou o marco ' . $titulo, null, null, $eid);
            avisar('Marco acrescentado à linha do tempo.');
        }
        redirecionar('/destino-textos?aba=linha');
    } elseif ($acao === 'excluir_marco') {
        $mid = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM destino_linha_tempo WHERE id = ? AND empresa_id = ?')->execute([$mid, $eid]);
        Auditoria::registrar('excluiu', 'destino_linha_tempo', $mid, 'excluiu um marco da linha do tempo', null, null, $eid);
        avisar('Marco excluído.');
        redirecionar('/destino-textos?aba=linha');
    }
    redirecionar('/destino-textos');
}

$q = $pdo->prepare('SELECT * FROM destino_narrativas WHERE empresa_id = ?');
$q->execute([$eid]);
$narrativas = [];
foreach ($q->fetchAll() as $n) {
    $narrativas[$n['secao']] = $n;
}
$q = $pdo->prepare('SELECT * FROM destino_linha_tempo WHERE empresa_id = ? ORDER BY data_marco, id');
$q->execute([$eid]);
$marcos = $q->fetchAll();
$csrf = Auth::tokenCsrf();
$dataMarco = fn (array $m): string => match ($m['precisao']) {
    'ano' => substr((string) $m['data_marco'], 0, 4),
    'mes' => dataBr((string) $m['data_marco'], 'm/Y'),
    default => dataBr((string) $m['data_marco'], 'd/m/Y'),
};

paginaInicio('O destino', 'destino-textos');
?>
<div class="cab-pag">
  <div>
    <h1>O destino</h1>
    <p>O discurso oficial de <?= e($empresa['nome_fantasia']) ?>: os textos de abertura do caderno do inventário e das abas institucionais do portal, e a linha do tempo do turismo.</p>
  </div>
</div>
<?php /* Uma aba por seção e a linha do tempo: a tela inteira de uma vez ficava
         comprida demais. A aba vem do endereço (#economia, ?aba=linha), que é
         para onde o salvar de cada seção volta. */ ?>
<nav class="abas-chamado abas-destino" role="tablist" aria-label="Seções">
  <?php foreach (SECOES_NARRATIVA as $secao => $rotulo): $temTexto = trim(strip_tags((string) ($narrativas[$secao]['conteudo_html'] ?? ''))) !== ''; ?>
    <button type="button" role="tab" data-aba="<?= $secao ?>" aria-selected="<?= $secao === 'apresentacao' ? 'true' : 'false' ?>"><?= e($rotulo) ?><?= $temTexto ? '' : '<span class="conta-aba" title="Ainda sem texto">vazio</span>' ?></button>
  <?php endforeach; ?>
  <button type="button" role="tab" data-aba="linha" aria-selected="false"><?= icone('calendario') ?>Linha do tempo<span class="conta-aba"><?= count($marcos) ?></span></button>
</nav>

  <?php foreach (SECOES_NARRATIVA as $secao => $rotulo): $n = $narrativas[$secao] ?? null; $ind = json_decode((string) ($n['indicadores'] ?? ''), true) ?: []; ?>
  <div class="painel-aba" data-painel="<?= $secao ?>"<?= $secao === 'apresentacao' ? '' : ' hidden' ?>>
    <form method="post" action="/destino-textos" class="cartao" id="form-<?= $secao ?>" data-com-editor>
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="narrativa"><input type="hidden" name="secao" value="<?= $secao ?>">
      <div class="cab-cartao"><h2><?= e($rotulo) ?></h2>
        <?php if ($n && $n['atualizado_em']): ?><span class="nota">atualizado em <?= e(dataBr((string) $n['atualizado_em'], 'd/m/Y')) ?></span><?php endif; ?></div>
      <div class="grade">
        <div class="campo c-12">
          <label for="titulo-<?= $secao ?>">Título</label>
          <div class="entrada"><input type="text" id="titulo-<?= $secao ?>" name="titulo" maxlength="150" value="<?= e((string) ($n['titulo'] ?? $rotulo)) ?>"<?= $podeEditar ? '' : ' readonly' ?>></div>
        </div>
      </div>
      <div class="editor-texto" data-editor>
        <?php if ($podeEditar): ?>
        <div class="editor-barra" role="toolbar" aria-label="Formatação">
          <button type="button" data-cmd="bold" title="Negrito" aria-label="Negrito"><b>N</b></button>
          <button type="button" data-cmd="italic" title="Itálico" aria-label="Itálico"><i>I</i></button>
          <button type="button" data-cmd="formatBlock" data-valor="h3" title="Título" aria-label="Título">T</button>
          <button type="button" data-cmd="insertUnorderedList" title="Lista" aria-label="Lista">&bull;</button>
          <button type="button" data-cmd="insertOrderedList" title="Lista numerada" aria-label="Lista numerada">1.</button>
          <button type="button" data-cmd="removeFormat" title="Limpar a formatação" aria-label="Limpar a formatação">&times;</button>
        </div>
        <?php endif; ?>
        <div class="editor-area texto-legal" <?= $podeEditar ? 'contenteditable="true"' : '' ?> role="textbox" aria-multiline="true" aria-label="Texto de <?= e($rotulo) ?>"><?= (string) ($n['conteudo_html'] ?? '') ?></div>
        <textarea name="conteudo" hidden><?= e((string) ($n['conteudo_html'] ?? '')) ?></textarea>
      </div>
      <?php if ($podeEditar): ?><p class="ajuda">Pode colar do Word ou do Google Docs: negrito, itálico, listas, títulos e tabelas ficam; cores, fontes e imagens coladas saem.</p><?php endif; ?>
      <?php if ($secao === 'economia'): ?>
        <h3 class="sub-cartao">Indicadores (com a fonte e o ano)</h3>
        <div class="indicadores">
          <div class="indicador cab" aria-hidden="true"><span>Indicador</span><span>Valor</span><span>Fonte</span><span>Ano</span></div>
          <?php foreach (INDICADORES_ECONOMIA as $k => $r): $ro = $podeEditar ? '' : ' readonly'; ?>
            <div class="indicador">
              <span class="indicador-nome"><?= e($r) ?></span>
              <div class="entrada"><input type="text" name="ind_valor[<?= $k ?>]" maxlength="40" inputmode="decimal" aria-label="<?= e($r) ?>, valor" placeholder="Valor" value="<?= e((string) ($ind[$k]['valor'] ?? '')) ?>"<?= $ro ?>></div>
              <div class="entrada"><input type="text" name="ind_fonte[<?= $k ?>]" maxlength="120" aria-label="<?= e($r) ?>, fonte" placeholder="RAIS, Cadastur, IPARDES..." value="<?= e((string) ($ind[$k]['fonte'] ?? '')) ?>"<?= $ro ?>></div>
              <div class="entrada"><input type="text" name="ind_ano[<?= $k ?>]" maxlength="10" inputmode="numeric" aria-label="<?= e($r) ?>, ano" placeholder="<?= date('Y') - 1 ?>" value="<?= e((string) ($ind[$k]['ano'] ?? '')) ?>"<?= $ro ?>></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <label class="check"><input type="checkbox" name="no_portal" value="1"<?= !$n || $n['no_portal'] ? ' checked' : '' ?><?= $podeEditar ? '' : ' disabled' ?>><i></i>Publicar no portal (sem a marca, vai só para o caderno)</label>
      <?php if ($podeEditar): ?><div class="acoes"><button type="submit" class="botao"><?= icone('check') ?>Salvar <?= e(mb_strtolower($rotulo)) ?></button></div><?php endif; ?>
    </form>
  </div>
  <?php endforeach; ?>

<div class="painel-aba" data-painel="linha" hidden>
  <?php if ($podeEditar): ?>
  <form method="post" action="/destino-textos?aba=linha" class="cartao" id="form-marco">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="marco">
    <h2 class="titulo-secao">Novo marco</h2>
    <div class="grade">
      <div class="campo c-3">
        <label for="precisao">Precisão</label>
        <div class="entrada"><select id="precisao" name="precisao" data-precisao>
          <?php foreach (PRECISOES as $k => $r): ?><option value="<?= $k ?>"><?= e($r) ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <div class="campo c-3" data-quando="dia"><label for="data">Data</label><div class="entrada"><input type="date" id="data" name="data"></div></div>
      <div class="campo c-3" data-quando="mes" hidden><label for="mes">Mês</label><div class="entrada"><input type="month" id="mes" name="mes"></div></div>
      <div class="campo c-3" data-quando="ano" hidden><label for="ano">Ano</label><div class="entrada"><input type="number" id="ano" name="ano" min="1500" max="2100"></div></div>
      <div class="campo c-6">
        <label for="categoria">Categoria <span class="obrig">obrigatório</span></label>
        <div class="entrada"><select id="categoria" name="categoria"><option value="">Escolha</option>
          <?php foreach (CATEGORIAS_MARCO as $k => $r): ?><option value="<?= $k ?>"><?= e($r) ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <div class="campo c-12">
        <label for="titulo-marco">Título <span class="obrig">obrigatório</span></label>
        <div class="entrada"><input type="text" id="titulo-marco" name="titulo" maxlength="150" placeholder="Instituição do COMTUR, primeiro voo comercial..."></div>
      </div>
      <div class="campo c-12">
        <label for="descricao-marco">Descrição</label>
        <div class="entrada"><textarea id="descricao-marco" name="descricao" rows="2" maxlength="4000"></textarea></div>
      </div>
    </div>
    <label class="check"><input type="checkbox" name="no_portal" value="1" checked><i></i>Mostrar no portal</label>
    <div class="acoes"><button type="submit" class="botao"><?= icone('mais') ?>Acrescentar</button></div>
  </form>
  <?php endif; ?>
  <div class="cartao">
    <h2 class="titulo-secao">Linha do tempo</h2>
    <?php if (!$marcos): ?>
      <p class="ajuda">Nenhum marco ainda. Leis do turismo, conselhos, voos, grandes eventos e reconhecimentos contam a história do destino no caderno e no portal.</p>
    <?php else: ?>
      <ol class="linha-tempo">
        <?php foreach ($marcos as $m): ?>
          <li><span class="data dado"><?= e($dataMarco($m)) ?></span>
            <div><b><?= e((string) $m['titulo']) ?></b> <span class="pilula nu"><?= e(CATEGORIAS_MARCO[$m['categoria']] ?? $m['categoria']) ?></span><?= $m['no_portal'] ? '' : ' <span class="pilula at">só no caderno</span>' ?>
              <?php if ($m['descricao']): ?><p><?= e((string) $m['descricao']) ?></p><?php endif; ?></div>
            <?php if ($podeEditar): ?>
              <form method="post" action="/destino-textos?aba=linha" class="acoes-linha">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="excluir_marco"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <button type="submit" title="Excluir o marco" aria-label="Excluir <?= e((string) $m['titulo']) ?>" data-confirmar="Excluir o marco <?= e((string) $m['titulo']) ?>?"><?= icone('lixeira') ?></button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </div>
</div>
<?php paginaFim();
