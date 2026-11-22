<?php
/**
 * Um requisito do diagnóstico DTI, em modal: o texto do Referencial Técnico,
 * o nível de 1 a 5 (Anexo 1), a situação atual com fonte e data (a
 * evidência) e as ações do Plano de Transformação (Anexo 2).
 * Softsul Software
 *
 * O que o InvenTour já sabe sobre o requisito aparece pronto, com o botão
 * para trazer o texto à evidência; o nível é sempre de quem avalia.
 * Diagnóstico fechado, ou sem permissão de editar: só leitura.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Diagnostico.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('diagnostico.ver');
Auth::exigirModuloLigado('diagnostico');
$eid = (int) $empresa['id'];

$ciclo = Diagnostico::ciclo((int) ($_GET['c'] ?? $_POST['c'] ?? 0), $eid);
if (!$ciclo) {
    paginaErro(404, 'Diagnóstico não encontrado.');
}
$met = Diagnostico::metodologia((int) $ciclo['metodologia_id']);
$requisitos = Diagnostico::requisitos((int) $ciclo['metodologia_id']);
$rid = (int) ($_GET['r'] ?? $_POST['r'] ?? 0);
$req = null;
foreach ($requisitos as $i => $r) {
    if ((int) $r['id'] === $rid) {
        $req = $r;
        $anterior = $requisitos[$i - 1] ?? null;
        $proximo = $requisitos[$i + 1] ?? null;
        break;
    }
}
if (!$req) {
    paginaErro(404, 'Requisito não encontrado.');
}
$podeEditar = Auth::pode('diagnostico.editar') && $ciclo['situacao'] === 'aberto';
$resposta = Diagnostico::respostas((int) $ciclo['id'])[$rid] ?? [];
$aqui = noPopup('/diagnostico-requisito?c=' . (int) $ciclo['id'] . '&r=' . $rid);
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Salve de novo.';
    } elseif (!$podeEditar) {
        $erro = 'Este diagnóstico está fechado, ou você não pode alterá-lo.';
    } else {
        $texto = fn (string $c, int $max) => mb_substr(trim((string) ($_POST[$c] ?? '')), 0, $max) ?: null;
        $nivel = (int) ($_POST['nivel'] ?? 0);
        $dados = [
            'nivel' => isset($met['escala'][(string) $nivel]) ? $nivel : null,
            'situacao_atual' => $texto('situacao_atual', 6000), 'fonte' => $texto('fonte', 255),
            'evidencia_em' => Diagnostico::data((string) ($_POST['evidencia_em'] ?? '')),
            'acoes' => $texto('acoes', 6000), 'responsavel' => $texto('responsavel', 120),
            'prazo' => Diagnostico::data((string) ($_POST['prazo'] ?? '')),
        ];
        if (trim((string) ($_POST['evidencia_em'] ?? '')) !== '' && !$dados['evidencia_em']) {
            $erro = 'A data da evidência não é uma data válida (dd/mm/aaaa).';
        } elseif (trim((string) ($_POST['prazo'] ?? '')) !== '' && !$dados['prazo']) {
            $erro = 'O prazo não é uma data válida (dd/mm/aaaa).';
        } elseif ($dados['nivel'] !== null && $dados['nivel'] >= 4 && !$dados['situacao_atual']) {
            $erro = 'Conformidade pede evidência: descreva a situação atual que a comprova.';
        } else {
            Diagnostico::responder($ciclo, $rid, $dados, (int) Auth::usuarioOuNulo()['id']);
            avisar('Requisito ' . (int) $req['numero'] . ' salvo.');
            fichaSalva('/diagnostico?c=' . (int) $ciclo['id'] . '&aba=' . $req['grupo']);
        }
        $resposta = array_merge($resposta, $dados, [
            'evidencia_em' => $dados['evidencia_em'] ?? (string) ($_POST['evidencia_em'] ?? ''),
            'prazo' => $dados['prazo'] ?? (string) ($_POST['prazo'] ?? ''),
        ]);
    }
}

$auto = $ciclo['metodologia_chave'] === 'mtur2022' ? (Diagnostico::evidenciasAutomaticas($empresa)[(int) $req['numero']] ?? null) : null;
$dataTela = fn ($v) => $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? dataBr((string) $v, 'd/m/Y') : (string) ($v ?? '');
$ro = $podeEditar ? '' : ' readonly';
$linhas = preg_split('/\n/', (string) $req['texto']);
$grupo = Diagnostico::GRUPOS[$req['grupo']][0];
$irPara = fn (?array $r) => $r ? noPopup('/diagnostico-requisito?c=' . (int) $ciclo['id'] . '&r=' . (int) $r['id']) : null;

paginaFicha('Requisito ' . (int) $req['numero'], 'diagnostico');
?>
<?php if ($erro !== ''): ?><p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p><?php endif; ?>
<form method="post" action="<?= e($aqui) ?>" id="form-requisito">
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <div class="cab-pag">
    <div>
      <h1 data-titulo>Requisito <?= (int) $req['numero'] ?></h1>
      <p><?= e($grupo . ($req['subeixo'] ? ' · ' . $req['subeixo'] : '') . ' · ' . $req['subcapitulo']) ?></p>
    </div>
    <div class="acoes">
      <?php if ($anterior ?? null): ?><a class="botao claro" href="<?= e($irPara($anterior)) ?>" data-balao-titulo="Anterior" data-balao="Requisito <?= (int) $anterior['numero'] ?>, sem salvar este." aria-label="Requisito anterior"><?= icone('seta', null, 'gira-180') ?></a><?php endif; ?>
      <?php if ($proximo ?? null): ?><a class="botao claro" href="<?= e($irPara($proximo)) ?>" data-balao-titulo="Próximo" data-balao="Requisito <?= (int) $proximo['numero'] ?>, sem salvar este." aria-label="Próximo requisito"><?= icone('seta') ?></a><?php endif; ?>
      <?php if ($podeEditar): ?><button type="submit" class="botao"><?= icone('check') ?>Salvar</button><?php endif; ?>
    </div>
  </div>

  <?php if ($ciclo['situacao'] !== 'aberto'): ?>
    <p class="aviso info"><?= icone('cadeado') ?><span>Diagnóstico fechado em <?= e(dataBr((string) $ciclo['fechado_em'], 'd/m/Y')) ?>: as respostas ficam como estão.</span></p>
  <?php endif; ?>

  <div class="cartao requisito-dti">
    <p class="texto-requisito"><?= e(array_shift($linhas)) ?></p>
    <?php if ($linhas): ?><ul class="itens-requisito"><?php foreach ($linhas as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($req['observacao']): ?><p class="ajuda"><b>Observação do manual:</b> <?= e((string) $req['observacao']) ?></p><?php endif; ?>
  </div>

  <div class="cartao">
    <h2 class="titulo-secao">Situação atual</h2>
    <div class="grade">
      <div class="campo c-12"><label for="nivel">Nível de conformidade</label>
        <div class="entrada"><select id="nivel" name="nivel"<?= $podeEditar ? '' : ' disabled' ?>>
          <option value="">Sem resposta</option>
          <?php foreach ($met['escala'] as $n => $rot): ?><option value="<?= (int) $n ?>"<?= isset($resposta['nivel']) && (int) $resposta['nivel'] === (int) $n ? ' selected' : '' ?>><?= (int) $n ?> · <?= e((string) $rot) ?></option><?php endforeach; ?>
        </select></div>
        <span class="ajuda">1 não conceituado (desconhece o requisito), 2 não conformidade (não cumpre), 3 observação (cumpre em parte), 4 conformidade estrita (cumpre), 5 conformidade plena (cumpre com excelência).</span></div>
      <?php if ($auto): ?>
        <div class="campo c-12">
          <div class="aviso info evidencia-auto"><?= icone('olho') ?><span><b>O InvenTour já mostra:</b> <span id="texto-auto"><?= e($auto) ?></span></span>
            <?php if ($podeEditar): ?><button type="button" class="botao claro" data-trazer-evidencia="texto-auto" data-para="situacao_atual"><?= icone('copiar') ?>Trazer</button><?php endif; ?></div>
        </div>
      <?php endif; ?>
      <div class="campo c-12"><label for="situacao_atual">Evidência</label>
        <div class="entrada"><textarea id="situacao_atual" name="situacao_atual" rows="5" maxlength="6000" placeholder="O que existe hoje e comprova o nível: lei, decreto, ata, plano, sistema, ação feita"<?= $ro ?>><?= e((string) ($resposta['situacao_atual'] ?? '')) ?></textarea></div></div>
      <div class="campo c-8"><label for="fonte">Fonte</label>
        <div class="entrada"><input type="text" id="fonte" name="fonte" maxlength="255" placeholder="Lei 1.234/2019, ata do COMTUR de 10/03, endereço do documento" value="<?= e((string) ($resposta['fonte'] ?? '')) ?>"<?= $ro ?>></div></div>
      <div class="campo c-4"><label for="evidencia_em">Data da evidência</label>
        <div class="entrada"><?= icone('calendario') ?><input type="text" id="evidencia_em" name="evidencia_em" maxlength="10" inputmode="numeric" placeholder="dd/mm/aaaa" data-data-ou-ano value="<?= e($dataTela($resposta['evidencia_em'] ?? null)) ?>"<?= $ro ?>></div></div>
    </div>
  </div>

  <div class="cartao">
    <h2 class="titulo-secao">Plano de Transformação</h2>
    <p class="ajuda">As ações para alcançar ou melhorar a conformidade, como na Matriz de Avaliação do MTur.</p>
    <div class="grade">
      <div class="campo c-12"><label for="acoes">Ações</label>
        <div class="entrada"><textarea id="acoes" name="acoes" rows="4" maxlength="6000" placeholder="O que fazer, como e com que recursos"<?= $ro ?>><?= e((string) ($resposta['acoes'] ?? '')) ?></textarea></div></div>
      <div class="campo c-8"><label for="responsavel">Responsável</label>
        <div class="entrada"><input type="text" id="responsavel" name="responsavel" maxlength="120" placeholder="Pessoa ou área" value="<?= e((string) ($resposta['responsavel'] ?? '')) ?>"<?= $ro ?>></div></div>
      <div class="campo c-4"><label for="prazo">Prazo</label>
        <div class="entrada"><?= icone('calendario') ?><input type="text" id="prazo" name="prazo" maxlength="10" inputmode="numeric" placeholder="dd/mm/aaaa" data-data-ou-ano value="<?= e($dataTela($resposta['prazo'] ?? null)) ?>"<?= $ro ?>></div></div>
    </div>
    <?php if ($podeEditar): ?><div class="cartao-rodape"><button type="submit" class="botao"><?= icone('check') ?>Salvar</button></div><?php endif; ?>
  </div>
  <?php if (!empty($resposta['atualizado_em'])): ?><p class="nota">Última alteração em <?= e(dataBr((string) $resposta['atualizado_em'], 'd/m/Y H:i')) ?>.</p><?php endif; ?>
</form>
<?php paginaFichaFim();
