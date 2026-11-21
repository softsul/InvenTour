<?php
/**
 * Caderno oficial do inventário: as versões geradas e a encadernação.
 * Softsul Software
 *
 * Aba Versões: pedir uma versão nova (completa ou por recorte, homologada
 * ou prévia) e as já geradas, com o PDF (abre no visualizador), o XLSX, o
 * CSV, o código de verificação e as leituras dos QR. Enquanto há versão na
 * fila, a tela se atualiza sozinha. Aba Encadernação: título, subtítulo,
 * ano, equipe técnica e fontes que saem na capa e na ficha técnica. A
 * geração roda na fila (app/Caderno.php).
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Caderno.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('caderno.ver');
Auth::exigirModuloLigado('caderno');
$pdo = Database::conexao();
$eid = (int) $empresa['id'];
$podeGerar = Auth::pode('caderno.criar');
$podeEditar = Auth::pode('caderno.editar');
$csrf = Auth::tokenCsrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        avisar('A página ficou aberta tempo demais. Tente de novo.', 'ruim');
    } elseif ($acao === 'gerar' && $podeGerar) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM cadernos WHERE empresa_id = ? AND situacao IN ('pendente', 'gerando')");
        $q->execute([$eid]);
        if ((int) $q->fetchColumn() > 0) {
            avisar('Já há uma versão sendo gerada. Espere ela terminar para pedir outra.', 'atencao');
        } else {
            $id = Caderno::pedir($eid, (array) ($_POST['categorias'] ?? []), (array) ($_POST['fichas'] ?? []), !empty($_POST['previa']),
                (int) Auth::usuarioOuNulo()['id']);
            Auditoria::registrar('criou', 'cadernos', $id, 'pediu uma versão do caderno oficial', null, null, $eid);
            avisar('Versão na fila. Em um ou dois minutos ela aparece pronta aqui.');
        }
    } elseif ($acao === 'encadernacao' && $podeEditar) {
        $ano = (int) ($_POST['ano'] ?? 0);
        $valores = [
            'titulo'    => mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 160),
            'subtitulo' => mb_substr(trim((string) ($_POST['subtitulo'] ?? '')), 0, 200),
            'ano'       => $ano >= 2000 && $ano <= 2100 ? (string) $ano : '',
            'equipe'    => mb_substr(trim((string) ($_POST['equipe'] ?? '')), 0, 4000),
            'fontes'    => mb_substr(trim((string) ($_POST['fontes'] ?? '')), 0, 4000),
            'credito_capa' => mb_substr(trim((string) ($_POST['credito_capa'] ?? '')), 0, 160),
        ];
        Ajustes::salvar('caderno', $valores, $eid, (int) Auth::usuarioOuNulo()['id']);
        Auditoria::registrar('alterou', 'ajustes', null, 'alterou a encadernação do caderno oficial', null, $valores, $eid);
        avisar('Encadernação salva. Vale para a próxima versão gerada.');
        redirecionar('/caderno?aba=encadernacao');
    }
    elseif ($acao === 'foto_capa' && $podeEditar) {
        $falha = Caderno::guardarFotoDaCapa($eid, $_FILES['foto_capa'] ?? []);
        if ($falha === null) {
            Auditoria::registrar('alterou', 'ajustes', null, 'trocou a foto da capa do caderno oficial', null, null, $eid);
        }
        avisar($falha ?? 'Foto da capa guardada. Vale para a próxima versão gerada.', $falha ? 'ruim' : 'bom');
        redirecionar('/caderno?aba=encadernacao');
    } elseif ($acao === 'tirar_foto_capa' && $podeEditar) {
        @unlink(Caderno::pasta($eid) . '/capa.jpg');
        Auditoria::registrar('alterou', 'ajustes', null, 'tirou a foto da capa do caderno oficial', null, null, $eid);
        avisar('Foto da capa retirada: a capa volta a ser a do emblema.');
        redirecionar('/caderno?aba=encadernacao');
    }
    redirecionar('/caderno');
}

$q = $pdo->prepare('SELECT c.*, u.nome AS quem, (SELECT COALESCE(SUM(l.leituras), 0) FROM caderno_leituras l WHERE l.caderno_id = c.id) AS leituras
                    FROM cadernos c LEFT JOIN usuarios u ON u.id = c.pedido_por WHERE c.empresa_id = ? ORDER BY c.id DESC LIMIT 50');
$q->execute([$eid]);
$versoes = $q->fetchAll();
$naFila = (bool) array_filter($versoes, fn ($v) => in_array($v['situacao'], ['pendente', 'gerando'], true));
$cfg = Caderno::config($eid);
$q = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(situacao IN ('homologada', 'desatualizada')), 0) AS homologados
                    FROM inventario_itens WHERE empresa_id = ? AND situacao <> 'arquivada'");
$q->execute([$eid]);
$contas = $q->fetch();
$fichas = $pdo->query('SELECT id, codigo, nome FROM inventario_fichas WHERE ativo = 1 ORDER BY ordem')->fetchAll();
$aba = ($_GET['aba'] ?? '') === 'encadernacao' ? 'encadernacao' : 'versoes';

paginaInicio('Caderno oficial', 'caderno');
?>
<div class="cab-pag">
  <div><h1>Caderno oficial</h1>
    <p>O inventário de <?= e($empresa['nome_fantasia']) ?> em PDF, na ordem das fichas do Ministério do Turismo, com planilhas em XLSX e CSV.</p></div>
</div>

<nav class="abas-chamado" role="tablist" aria-label="Seções">
  <button type="button" role="tab" data-aba="versoes" aria-selected="<?= $aba === 'versoes' ? 'true' : 'false' ?>"><?= icone('arquivo') ?>Versões<span class="conta-aba"><?= count($versoes) ?></span></button>
  <button type="button" role="tab" data-aba="encadernacao" aria-selected="<?= $aba === 'encadernacao' ? 'true' : 'false' ?>"><?= icone('lapis') ?>Encadernação</button>
</nav>

<div class="painel-aba" data-painel="versoes"<?= $aba === 'versoes' ? '' : ' hidden' ?>>
  <?php if ($podeGerar): ?>
  <form method="post" action="/caderno" class="cartao">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="gerar">
    <div class="cab-cartao"><h2>Gerar uma versão</h2>
      <span class="nota"><?= milhar((int) $contas['homologados']) ?> de <?= milhar((int) $contas['total']) ?> itens homologados</span></div>
    <p class="ajuda gerar-explica">A versão oficial leva só os itens homologados. A prévia leva todos, com a marca de versão preliminar em todas as páginas, para conferir antes da homologação.</p>
    <div class="grade">
      <div class="campo c-6">
        <span class="rotulo-campo">Categorias</span>
        <div class="caixas-coluna">
        <?php foreach (Caderno::CATEGORIAS as $k => $nome): ?>
          <label class="check"><input type="checkbox" name="categorias[]" value="<?= $k ?>" checked><i></i><?= e($nome) ?></label>
        <?php endforeach; ?>
        </div>
        <span class="ajuda">As três marcadas é o caderno completo.</span>
      </div>
      <div class="campo c-6">
        <span class="rotulo-campo">Só algumas fichas</span>
        <?= multiEscolhaFiltro(['campo' => 'fichas', 'icone' => 'manual', 'rotulo' => 'Fichas', 'vazio' => 'Todas as fichas das categorias marcadas',
            'plural' => 'fichas', 'escolhidos' => [], 'filtrar' => true, 'buscar' => 'Buscar ficha',
            'opcoes' => array_column(array_map(fn ($f) => ['id' => $f['id'], 'nome' => $f['codigo'] . ' ' . $f['nome']], $fichas), 'nome', 'id')]) ?>
        <span class="ajuda">Escolhendo fichas, o caderno sai só com elas.</span>
      </div>
      <div class="campo c-12">
        <?= interruptor('previa', 'Prévia', (int) $contas['homologados'] === 0, 'Inclui o que ainda não foi homologado, com a marca de versão preliminar',
            'Só os itens homologados: a versão oficial') ?>
      </div>
    </div>
    <div class="acoes acoes-gerar"><button type="submit" class="botao"<?= $naFila ? ' disabled title="Espere a versão da fila terminar"' : '' ?>><?= icone('arquivo') ?>Gerar o caderno</button>
      <?php if ($naFila): ?><span class="nota">Há uma versão sendo gerada. A tela se atualiza sozinha.</span><?php endif; ?></div>
  </form>
  <?php endif; ?>

  <div class="cartao"<?= $naFila ? ' data-recarregar-em="6"' : '' ?>>
    <div class="cab-cartao"><h2>Versões geradas</h2></div>
    <?php if (!$versoes): ?>
      <div class="vazio"><?= icone('arquivo') ?><p><strong>Nenhuma versão ainda.</strong></p><p>Gere a primeira acima. A prévia serve para conferir antes da homologação.</p></div>
    <?php else: ?>
    <div class="rolagem">
      <table class="dados">
        <thead><tr><th>Versão</th><th>Recorte</th><th>Situação</th><th class="num">Páginas</th><th class="num">Itens</th><th>Código</th><th class="num">Leituras do QR</th><th>Arquivos</th></tr></thead>
        <tbody>
        <?php foreach ($versoes as $v): [$rot, $tom] = Caderno::SITUACOES[$v['situacao']]; $pronto = $v['situacao'] === 'pronto'; ?>
          <tr>
            <td class="elastica"><b>Versão <?= (int) $v['versao'] ?></b> <?= $v['previa'] ? '<span class="pilula at">Prévia</span>' : '<span class="pilula ok">Oficial</span>' ?>
              <br><span class="nulo"><?= e(dataBr((string) ($v['gerado_em'] ?: $v['criado_em']), 'd/m/Y H:i')) ?><?= $v['quem'] ? ' · ' . e((string) $v['quem']) : '' ?></span></td>
            <td><?= e(Caderno::descreverRecorte($v['recorte'])) ?></td>
            <td><span class="pilula <?= $tom ?>"><?= e($rot) ?></span>
              <?php if ($v['situacao'] === 'gerando' && $v['progresso']): ?><br><span class="nulo"><?= e((string) $v['progresso']) ?></span><?php endif; ?>
              <?php if ($v['situacao'] === 'falhou' && $v['erro']): ?><br><span class="nulo" title="<?= e((string) $v['erro']) ?>">Não deu certo. Tente de novo.</span><?php endif; ?></td>
            <td class="num dado"><?= $v['paginas'] ? milhar((int) $v['paginas']) : '<span class="nulo">-</span>' ?></td>
            <td class="num dado"><?= $v['itens'] !== null ? milhar((int) $v['itens']) : '<span class="nulo">-</span>' ?></td>
            <td class="dado"><?= e(Caderno::codigoLegivel((string) $v['codigo'])) ?></td>
            <td class="num dado"><?= milhar((int) $v['leituras']) ?></td>
            <td class="arquivos-caderno">
              <?php if ($pronto): ?>
                <?php /* Os três arquivos com nome escrito: ícone sozinho não dizia qual era qual. */ ?>
                <a class="botao claro mini" href="/caderno-arquivo?id=<?= (int) $v['id'] ?>&amp;tipo=pdf" data-visualizar="pdf" title="Abrir o PDF" aria-label="Abrir o PDF da versão <?= (int) $v['versao'] ?>"><?= icone('olho') ?>PDF</a>
                <?php if (Auth::pode('caderno.exportar')): ?>
                  <a class="botao claro mini" href="/caderno-arquivo?id=<?= (int) $v['id'] ?>&amp;tipo=xlsx" title="Baixar a planilha do Excel" aria-label="Baixar a planilha da versão <?= (int) $v['versao'] ?>"><?= icone('baixar') ?>XLSX</a>
                  <a class="botao claro mini" href="/caderno-arquivo?id=<?= (int) $v['id'] ?>&amp;tipo=csv" title="Baixar em CSV" aria-label="Baixar o CSV da versão <?= (int) $v['versao'] ?>"><?= icone('baixar') ?>CSV</a>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="painel-aba" data-painel="encadernacao"<?= $aba === 'encadernacao' ? '' : ' hidden' ?>>
  <form method="post" action="/caderno" class="cartao">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="encadernacao">
    <div class="cab-cartao"><h2>Capa e ficha técnica</h2></div>
    <?php $ro = $podeEditar ? '' : ' readonly'; ?>
    <div class="grade">
      <div class="campo c-8"><label for="titulo">Título</label>
        <div class="entrada"><input type="text" id="titulo" name="titulo" maxlength="160" value="<?= e($cfg['titulo']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-4"><label for="ano">Ano de referência</label>
        <div class="entrada"><input type="number" id="ano" name="ano" min="2000" max="2100" value="<?= (int) $cfg['ano'] ?>"<?= $ro ?>></div></div>
      <div class="campo c-12"><label for="subtitulo">Subtítulo</label>
        <div class="entrada"><input type="text" id="subtitulo" name="subtitulo" maxlength="200" placeholder="Por exemplo, Edição do Conselho Municipal de Turismo" value="<?= e($cfg['subtitulo']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-12"><label for="credito_capa">Legenda da foto da capa</label>
        <div class="entrada"><input type="text" id="credito_capa" name="credito_capa" maxlength="160" placeholder="Parque Estadual Vitório Piassa. Foto: Prefeitura" value="<?= e($cfg['credito_capa']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-6"><label for="equipe">Equipe técnica</label>
        <div class="entrada"><textarea id="equipe" name="equipe" rows="6" maxlength="4000" placeholder="Nome: função, uma pessoa por linha"<?= $ro ?>><?= e($cfg['equipe']) ?></textarea></div>
        <span class="ajuda">Uma pessoa por linha, no formato Nome: função. Sai na ficha técnica.</span></div>
      <div class="campo c-6"><label for="fontes">Fontes</label>
        <div class="entrada"><textarea id="fontes" name="fontes" rows="6" maxlength="4000"<?= $ro ?>><?= e($cfg['fontes']) ?></textarea></div>
        <span class="ajuda">IBGE, IPARDES, Cadastur, o caderno anterior. Sai na ficha técnica.</span></div>
    </div>
    <?php if ($podeEditar): ?><div class="acoes acoes-gerar"><button type="submit" class="botao"><?= icone('check') ?>Salvar</button></div><?php endif; ?>
  </form>
  <div class="cartao">
    <div class="cab-cartao"><h2>Foto da capa</h2></div>
    <p class="ajuda">Uma foto marcante do destino, de preferência deitada. Sem foto, a capa sai com o emblema na cor do destino.</p>
    <?php if (Caderno::fotoDaCapa($eid)): ?>
      <div class="foto-capa-atual">
        <a href="/caderno-arquivo?tipo=capa" data-visualizar="foto" data-legenda="Foto da capa"><img src="/caderno-arquivo?tipo=capa&amp;mini=1" alt="Foto da capa atual"></a>
        <?php if ($podeEditar): ?>
          <form method="post" action="/caderno?aba=encadernacao"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="tirar_foto_capa">
            <button type="submit" class="botao claro mini" data-confirmar="Tirar a foto da capa?"><?= icone('lixeira') ?>Tirar a foto</button></form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($podeEditar): ?>
      <form method="post" action="/caderno?aba=encadernacao" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="foto_capa">
        <?= zonaDeUpload('foto_capa', 'image/jpeg,image/png,image/webp', 'Arraste a foto da capa aqui', 'ou clique para escolher') ?>
      </form>
    <?php endif; ?>
  </div>
  <p class="ajuda">Os textos de abertura e a linha do tempo vêm de <a href="/destino-textos">O destino</a>; as experiências aprovadas entram como anexo.</p>
</div>
<?php paginaFim();
