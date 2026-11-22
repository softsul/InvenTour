<?php
/**
 * Diagnóstico DTI do destino (etapa 7, app/Diagnostico.php).
 * Softsul Software
 *
 * Sem diagnóstico, a tela explica e abre o primeiro. Com diagnóstico: a
 * média geral, o radar dos eixos e, em abas, o sistema de gestão, os 9 eixos
 * e o Plano de Transformação. Cada requisito abre em modal
 * (diagnostico-requisito.php) com o nível, a evidência e as ações. Fechado,
 * o ciclo vira registro; o próximo pode partir dele.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Diagnostico.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('diagnostico.ver');
Auth::exigirModuloLigado('diagnostico');
$eid = (int) $empresa['id'];
$podeCriar = Auth::pode('diagnostico.criar');
$podeEditar = Auth::pode('diagnostico.editar');
$usuario = (int) Auth::usuarioOuNulo()['id'];

$ciclos = Diagnostico::ciclos($eid);
$metodologias = Diagnostico::metodologias();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        avisar('A página ficou aberta tempo demais. Tente de novo.', 'ruim');
        redirecionar('/diagnostico');
    }
    $acao = (string) ($_POST['acao'] ?? '');
    if ($acao === 'abrir' && $podeCriar) {
        $met = (int) ($_POST['metodologia'] ?? 0);
        $m = Diagnostico::metodologia($met);
        $origem = (int) ($_POST['origem'] ?? 0);
        $doDestino = array_column($ciclos, null, 'id');
        if (!$m || !$m['ativo']) {
            avisar('Escolha a metodologia.', 'atencao');
        } elseif (array_filter($ciclos, fn ($c) => $c['situacao'] === 'aberto' && (int) $c['metodologia_id'] === $met)) {
            avisar('Já existe um diagnóstico aberto nesta metodologia. Feche-o antes de abrir outro.', 'atencao');
        } else {
            $origem = $origem && isset($doDestino[$origem]) && (int) $doDestino[$origem]['metodologia_id'] === $met ? $origem : null;
            $titulo = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 120) ?: $m['nome'] . ' · ' . date('Y');
            $id = Diagnostico::abrir($eid, $met, $titulo, $origem, $usuario);
            avisar($origem ? 'Diagnóstico aberto com as respostas do anterior, para revisar.' : 'Diagnóstico aberto. Comece pelo requisito que quiser.');
            redirecionar('/diagnostico?c=' . $id);
        }
        redirecionar('/diagnostico');
    }
    if ($acao === 'fechar' && $podeEditar) {
        $c = Diagnostico::ciclo((int) ($_POST['c'] ?? 0), $eid);
        if ($c) {
            $erro = Diagnostico::fechar($c, $usuario);
            avisar($erro ?: 'Diagnóstico fechado. A nota e as médias ficam guardadas como estão.', $erro ? 'atencao' : 'bom');
            redirecionar('/diagnostico?c=' . (int) $c['id']);
        }
    }
    redirecionar('/diagnostico');
}

$ciclo = null;
if ($ciclos) {
    $pedido = (int) ($_GET['c'] ?? 0);
    $ciclo = $pedido ? Diagnostico::ciclo($pedido, $eid) : null;
    $ciclo ??= Diagnostico::ciclo((int) $ciclos[0]['id'], $eid);
}

if ($ciclo) {
    $met = Diagnostico::metodologia((int) $ciclo['metodologia_id']);
    $requisitos = Diagnostico::requisitos((int) $ciclo['metodologia_id']);
    $respostas = Diagnostico::respostas((int) $ciclo['id']);
    $res = $ciclo['situacao'] === 'fechado' && $ciclo['resultado'] ? json_decode((string) $ciclo['resultado'], true) : Diagnostico::resultado($requisitos, $respostas);
    $geral = $res['geral'];
    $distincao = Diagnostico::distincao($met, $geral);
    $aberto = $ciclo['situacao'] === 'aberto';
    $porGrupo = [];
    foreach ($requisitos as $r) {
        $porGrupo[$r['grupo']][] = $r;
    }
    $plano = array_values(array_filter($requisitos, function ($r) use ($respostas) {
        $s = $respostas[(int) $r['id']] ?? null;
        return $s && (trim((string) $s['acoes']) !== '' || ($s['nivel'] !== null && (int) $s['nivel'] < 4));
    }));
    $aba = (string) ($_GET['aba'] ?? 'sistema');
    if (!isset(Diagnostico::GRUPOS[$aba]) && $aba !== 'plano') {
        $aba = 'sistema';
    }
}
$csrf = Auth::tokenCsrf();
$classeNivel = fn ($n) => $n === null ? 'nu' : ((int) $n >= 4 ? 'ok' : ((int) $n === 3 ? 'at' : 'ruim'));
$primeiraLinha = fn (string $t) => preg_split('/\n/', $t)[0];

paginaInicio('Diagnóstico DTI', 'diagnostico');
?>
<div class="cab-pag">
  <div>
    <h1>Diagnóstico DTI</h1>
    <p>A maturidade de <?= e((string) $empresa['nome_fantasia']) ?> como destino turístico inteligente, requisito a requisito, com a evidência de cada resposta e as ações do Plano de Transformação.</p>
  </div>
  <?php if ($ciclo && $podeCriar && !$aberto): ?>
    <div class="acoes"><button type="button" class="botao claro" data-abre="modal-novo-dti"><?= icone('mais') ?>Novo diagnóstico</button></div>
  <?php endif; ?>
</div>

<?php if (!$ciclo): ?>
  <div class="cartao">
    <h2 class="titulo-secao">Comece o primeiro diagnóstico</h2>
    <p class="ajuda">A metodologia oficial do Ministério do Turismo, DTI Brasil 2022, tem 105 requisitos: o sistema de gestão (capítulos 4 a 10) e os 9 eixos (governança, inovação, tecnologia, acessibilidade, sustentabilidade, criatividade, mobilidade e transporte, promoção e marketing, segurança). Cada um recebe um nível de 1 a 5, com a evidência. A distinção pede média 3 ou mais.</p>
    <p class="ajuda">Parte das evidências o InvenTour já tem (inventário, portal, governança, acessibilidade, trade) e aparece pronta em cada requisito.</p>
    <?php if ($podeCriar): ?>
      <form method="post" action="/diagnostico">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="abrir">
        <div class="grade">
          <div class="campo c-6"><label for="metodologia">Metodologia</label>
            <div class="entrada"><select id="metodologia" name="metodologia">
              <?php foreach ($metodologias as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['nome'] . ' (' . $m['orgao'] . ')') ?></option><?php endforeach; ?>
            </select></div></div>
          <div class="campo c-6"><label for="titulo">Nome do diagnóstico</label>
            <div class="entrada"><input type="text" id="titulo" name="titulo" maxlength="120" placeholder="DTI Brasil 2022 · <?= date('Y') ?>"></div></div>
        </div>
        <div class="cartao-rodape"><button type="submit" class="botao"><?= icone('check') ?>Começar o diagnóstico</button></div>
      </form>
    <?php else: ?>
      <p class="aviso info"><?= icone('alerta') ?><span>Quem abre o diagnóstico é o gestor ou o consultor do destino.</span></p>
    <?php endif; ?>
  </div>
<?php else: ?>

  <?php if (count($ciclos) > 1): ?>
    <div class="pilulas-filtro" aria-label="Diagnósticos do destino">
      <?php foreach ($ciclos as $c): ?>
        <a class="pf<?= (int) $c['id'] === (int) $ciclo['id'] ? ' ativa' : '' ?>" href="/diagnostico?c=<?= (int) $c['id'] ?>"><?= e((string) $c['titulo']) ?><?= $c['situacao'] === 'aberto' ? ' · aberto' : ' · ' . e(Diagnostico::nota($c['nota'] !== null ? (float) $c['nota'] : null)) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="metricas">
    <div class="metrica destaque"><span class="rot">Média geral</span><span class="val"><?= e(Diagnostico::nota($geral['media'])) ?></span>
      <span class="nota"><?= $distincao === null ? 'de 1 a 5, sem respostas ainda' : ($distincao ? 'atinge a média mínima de ' . e(Diagnostico::nota((float) $met['nota_minima'])) : 'abaixo da média mínima de ' . e(Diagnostico::nota((float) $met['nota_minima']))) ?></span></div>
    <div class="metrica"><span class="rot">Respondidos</span><span class="val"><?= (int) $geral['respondidos'] ?> de <?= (int) $geral['total'] ?></span>
      <span class="nota"><?= $aberto ? 'diagnóstico aberto' : 'fechado em ' . e(dataBr((string) $ciclo['fechado_em'], 'd/m/Y')) ?></span></div>
    <div class="metrica"><span class="rot">Em conformidade</span><span class="val"><?= (int) ($geral['niveis'][4] + $geral['niveis'][5]) ?></span><span class="nota">níveis 4 e 5</span></div>
    <div class="metrica"><span class="rot">Não conformes</span><span class="val"><?= (int) ($geral['niveis'][1] + $geral['niveis'][2]) ?></span><span class="nota">níveis 1 e 2 · <?= (int) $geral['niveis'][3] ?> em observação</span></div>
    <div class="metrica"><span class="rot">Plano de Transformação</span><span class="val"><?= (int) $geral['acoes'] ?></span><span class="nota">requisitos com ações</span></div>
  </div>

  <div class="painel-colunas painel-graficos">
    <div class="cartao">
      <div class="cab-cartao"><h2>Maturidade por eixo</h2><span class="nota">média de 0 a 5; o tracejado é a média mínima</span></div>
      <div class="radar-dti-caixa"><?= Diagnostico::radar($res['grupos'], $met['nota_minima'] !== null ? (float) $met['nota_minima'] : null) ?></div>
    </div>
    <div class="cartao">
      <div class="cab-cartao"><h2><?= e((string) $ciclo['titulo']) ?></h2><span class="nota"><?= e($met['nome'] . ', ' . $met['orgao']) ?></span></div>
      <div class="rolagem sem-borda eixos-dti">
        <table class="dados" data-sem-cartoes>
          <thead><tr><th>Eixo</th><th class="num">Média</th><th class="num">Feitos</th></tr></thead>
          <tbody>
          <?php foreach (Diagnostico::GRUPOS as $k => [$nome, $ic]): $g = $res['grupos'][$k]; ?>
            <tr><td class="elastica"><a href="/diagnostico?c=<?= (int) $ciclo['id'] ?>&amp;aba=<?= $k ?>"><?= e($nome) ?></a></td>
              <td class="num dado"><span class="pilula <?= $g['media'] === null ? 'nu' : ($g['media'] >= 4 ? 'ok' : ($g['media'] >= 3 ? 'at' : 'ruim')) ?>"><?= e(Diagnostico::nota($g['media'])) ?></span></td>
              <td class="num dado"><?= (int) $g['respondidos'] ?>/<?= (int) $g['total'] ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($aberto && $podeEditar): ?>
        <form method="post" action="/diagnostico" class="cartao-rodape">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="fechar"><input type="hidden" name="c" value="<?= (int) $ciclo['id'] ?>">
          <button type="submit" class="botao claro" data-confirmar="Fechar o diagnóstico? A nota e as respostas ficam guardadas como estão e não mudam mais. O próximo diagnóstico pode partir deste."><?= icone('cadeado') ?>Fechar o diagnóstico</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <nav class="abas-chamado" role="tablist" aria-label="Partes do diagnóstico">
    <?php foreach (Diagnostico::GRUPOS as $k => [$nome, $ic]): $g = $res['grupos'][$k]; ?>
      <button type="button" role="tab" data-aba="<?= $k ?>" aria-selected="<?= $aba === $k ? 'true' : 'false' ?>"><?= icone($ic) ?><?= e($nome) ?><span class="conta-aba"><?= (int) $g['respondidos'] ?>/<?= (int) $g['total'] ?></span></button>
    <?php endforeach; ?>
    <button type="button" role="tab" data-aba="plano" aria-selected="<?= $aba === 'plano' ? 'true' : 'false' ?>"><?= icone('lista') ?>Plano de Transformação<?= $plano ? '<span class="conta-aba">' . count($plano) . '</span>' : '' ?></button>
  </nav>

  <?php foreach (Diagnostico::GRUPOS as $k => [$nome, $ic, $explica]): ?>
    <div class="painel-aba" data-painel="<?= $k ?>"<?= $aba === $k ? '' : ' hidden' ?>>
      <div class="cartao">
        <p class="ajuda"><?= e($explica) ?></p>
        <div class="rolagem sem-borda">
          <table class="dados tabela-dti">
            <thead><tr><th>Nº</th><th>Requisito</th><th>Nível</th><th>Evidência</th><th class="acao"></th></tr></thead>
            <tbody>
            <?php foreach ($porGrupo[$k] ?? [] as $r): $s = $respostas[(int) $r['id']] ?? null; $nivel = $s['nivel'] ?? null;
              $url = '/diagnostico-requisito?c=' . (int) $ciclo['id'] . '&r=' . (int) $r['id']; ?>
              <tr>
                <td class="dado nao-quebra"><?= (int) $r['numero'] ?></td>
                <td class="elastica"><a href="<?= e($url) ?>" data-ficha="Requisito <?= (int) $r['numero'] ?>"><?= e($primeiraLinha((string) $r['texto'])) ?></a>
                  <span class="sub-celula"><?= e((string) ($r['subeixo'] ?: $r['subcapitulo'])) ?></span></td>
                <td class="nao-quebra"><span class="pilula <?= $classeNivel($nivel) ?>"><?= $nivel === null ? 'Sem resposta' : (int) $nivel . ' · ' . e(Diagnostico::CURTO[(int) $nivel]) ?></span></td>
                <td><?= $s && trim((string) $s['situacao_atual']) !== '' ? e(mb_strimwidth((string) $s['situacao_atual'], 0, 90, '...')) : '<span class="nota">-</span>' ?></td>
                <td class="acao"><div class="acoes-linha"><a href="<?= e($url) ?>" data-ficha="Requisito <?= (int) $r['numero'] ?>" data-balao-titulo="<?= $aberto && $podeEditar ? 'Responder' : 'Ver' ?>" data-balao="O nível, a evidência e as ações do requisito <?= (int) $r['numero'] ?>." aria-label="Abrir o requisito <?= (int) $r['numero'] ?>"><?= icone($aberto && $podeEditar ? 'lapis' : 'olho') ?></a></div></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="painel-aba" data-painel="plano"<?= $aba === 'plano' ? '' : ' hidden' ?>>
    <?php if (!$plano): ?>
      <div class="vazio"><?= icone('lista') ?><p><strong>O Plano de Transformação ainda está vazio.</strong></p>
        <p>Entram aqui os requisitos abaixo da conformidade (níveis 1 a 3) e os que têm ações anotadas, como na Matriz de Avaliação do MTur.</p></div>
    <?php else: ?>
      <div class="cartao">
        <p class="ajuda">Os requisitos abaixo da conformidade (níveis 1 a 3) e os que têm ações anotadas. O PDF e a planilha estão em Relatórios, Plano de Transformação.</p>
        <div class="rolagem sem-borda">
          <table class="dados tabela-dti">
            <thead><tr><th>Nº</th><th>Requisito</th><th>Nível</th><th>Ações</th><th>Responsável</th><th>Prazo</th></tr></thead>
            <tbody>
            <?php foreach ($plano as $r): $s = $respostas[(int) $r['id']]; $url = '/diagnostico-requisito?c=' . (int) $ciclo['id'] . '&r=' . (int) $r['id']; ?>
              <tr>
                <td class="dado nao-quebra"><?= (int) $r['numero'] ?></td>
                <td class="elastica"><a href="<?= e($url) ?>" data-ficha="Requisito <?= (int) $r['numero'] ?>"><?= e($primeiraLinha((string) $r['texto'])) ?></a>
                  <span class="sub-celula"><?= e(Diagnostico::GRUPOS[$r['grupo']][0]) ?></span></td>
                <td class="nao-quebra"><span class="pilula <?= $classeNivel($s['nivel']) ?>"><?= $s['nivel'] === null ? '-' : (int) $s['nivel'] ?></span></td>
                <td><?= trim((string) $s['acoes']) !== '' ? nl2br(e((string) $s['acoes'])) : '<span class="nota">sem ações anotadas</span>' ?></td>
                <td><?= e((string) ($s['responsavel'] ?? '')) ?: '-' ?></td>
                <td class="dado nao-quebra"><?= $s['prazo'] ? e(dataBr((string) $s['prazo'], 'd/m/Y')) : '-' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($podeCriar && !$aberto): ?>
    <dialog class="confirmador janela conteudo janela-dti" id="modal-novo-dti" aria-labelledby="titulo-novo-dti">
      <header class="cabeca-janela"><b id="titulo-novo-dti">Novo diagnóstico</b><button type="button" data-fecha aria-label="Fechar">&times;</button></header>
      <form method="post" action="/diagnostico" class="corpo-janela">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="abrir">
          <div class="grade">
            <div class="campo c-12"><label for="metodologia">Metodologia</label>
              <div class="entrada"><select id="metodologia" name="metodologia">
                <?php foreach ($metodologias as $m): ?><option value="<?= (int) $m['id'] ?>"<?= (int) $m['id'] === (int) $ciclo['metodologia_id'] ? ' selected' : '' ?>><?= e($m['nome'] . ' (' . $m['orgao'] . ')') ?></option><?php endforeach; ?>
              </select></div></div>
            <div class="campo c-12"><label for="titulo">Nome do diagnóstico</label>
              <div class="entrada"><input type="text" id="titulo" name="titulo" maxlength="120" placeholder="DTI Brasil 2022 · <?= date('Y') ?>"></div></div>
            <div class="campo c-12"><label for="origem">Respostas</label>
              <div class="entrada"><select id="origem" name="origem">
                <option value="<?= (int) $ciclo['id'] ?>">Partir de <?= e((string) $ciclo['titulo']) ?> e revisar</option>
                <option value="0">Começar em branco</option>
              </select></div></div>
          </div>
        <div class="cartao-rodape"><button type="button" class="botao claro" data-fecha>Cancelar</button><button type="submit" class="botao"><?= icone('check') ?>Abrir</button></div>
      </form>
    </dialog>
  <?php endif; ?>
<?php endif; ?>
<?php paginaFim();
