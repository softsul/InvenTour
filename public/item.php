<?php
/**
 * Um item do inventário (atrativo, equipamento ou serviço), em modal.
 * Softsul Software
 *
 * Sem id e sem ficha, mostra as 54 fichas para escolher (é a ficha que decide
 * os campos). Com a ficha, o formulário junta o núcleo comum das fichas do
 * MTur (identificação, localização, contato, funcionamento, conservação,
 * textos), os campos específicos daquela ficha, gerados pelo catálogo
 * (inventario_atributos), e a acessibilidade da seção 6. Todo campo
 * específico aceita NA, NI ou NE, como manda a metodologia. O histórico do
 * item fica no fim.
 *
 * A esteira (app/Esteira.php) fica no topo: o pesquisador envia para a
 * conferência, quem coordena homologa ou devolve com nota. Quem coordena
 * ainda pode ajustar a situação à mão no campo Situação.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Inventario.php';
require_once __DIR__ . '/../app/Cnpj.php';
require_once __DIR__ . '/../app/PlusCode.php';
require_once __DIR__ . '/../app/Esteira.php';
require_once __DIR__ . '/../app/FichaFormulario.php';
require_once __DIR__ . '/../app/ColetaTela.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('inventario.ver');
$pdo = Database::conexao();

$id = (int) ($_GET['id'] ?? 0);
$novo = $id === 0;
$item = null;
if (!$novo) {
    $item = Inventario::buscar($id) ?: negarRegistro();
    $ficha = Inventario::ficha((int) $item['ficha_id']) ?: negarRegistro();
} else {
    if (!Auth::pode('inventario.criar') && !Auth::pode('coleta.criar')) {
        Auth::exigirPermissao('inventario.criar');
    }
    $ficha = Inventario::ficha((int) ($_GET['ficha'] ?? $_POST['ficha_id'] ?? 0));
    if ($ficha && !Esteira::dentroDoEscopo((string) $ficha['codigo'])) {
        negarRegistro();
    }
}
// O pesquisador (coleta) altera o que está no escopo dele e ainda em coleta;
// depois de enviado, só quem coordena mexe.
$podeEditar = $novo ? (Auth::pode('inventario.criar') || Auth::pode('coleta.criar'))
    : (Auth::pode('inventario.editar') || (Auth::pode('coleta.editar') && Esteira::dentroDoEscopo((string) $item['ficha_codigo'])
        && in_array($item['situacao'], Esteira::EM_COLETA, true)));
// Quem coordena ajusta a situação à mão; o pesquisador só envia à conferência.
$coordena = Auth::ehSoftsul() || Auth::temPapel('gestor') || Auth::temPapel('consultor') || Auth::temPapel('coordenador');
$situacoesPermitidas = $coordena ? array_keys(Inventario::SITUACOES) : ['pre_cadastro', 'em_campo', 'em_conferencia'];
// Arquivar é pela chave no cabeçalho, não pela lista (a lista só mostra
// "Arquivada" quando o item está arquivado, para não desarquivar sem querer).
if (($item['situacao'] ?? '') !== 'arquivada') {
    $situacoesPermitidas = array_values(array_diff($situacoesPermitidas, ['arquivada']));
}

// ------------------------------------------------------------ ESCOLHER A FICHA
if ($novo && !$ficha) {
    paginaFicha('Novo item do inventário', 'inventario');
    ?>
    <div class="trilha"><a href="/inventario">Inventário</a><?= icone('seta') ?><span>Novo</span></div>
    <div class="cab-pag"><div><h1 data-titulo>Novo item do inventário</h1>
      <p>Escolha a ficha do Ministério do Turismo. É ela que decide os campos: hospedagem pede UH e leitos, cachoeira pede altura e acesso.</p></div></div>
    <?php foreach (Inventario::fichasPorCategoria() as $cat => $lista): ?>
      <div class="cartao">
        <h2 class="titulo-secao"><?= e($cat . '. ' . (Inventario::CATEGORIAS[$cat] ?? '')) ?></h2>
        <ul class="lista-simples">
          <?php foreach ($lista as $f): ?>
            <li><a href="<?= e(endereco('item', null) . '?ficha=' . (int) $f['id'] . (emPopup() ? '&popup=1' : '')) ?>"><b class="dado"><?= e((string) $f['codigo']) ?></b> <?= e((string) $f['nome']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach;
    paginaFichaFim();
    exit;
}

$fichaId = (int) $ficha['id'];
$tipos = Inventario::tipos($fichaId);
$atributos = Inventario::atributos($fichaId);
$valores = $novo ? [] : Inventario::valores($id);
$acess = $novo ? [] : Inventario::acessibilidade($id);
$blocos = Inventario::blocosComuns();
$subitensAcess = (array) ($blocos['acessibilidade'] ?? []);
$d = $item ?? ['nome' => '', 'situacao' => 'em_campo', 'zona' => null, 'conservacao' => null, 'natureza' => null, 'jurisdicao' => null,
               'tipo_id' => null, 'horario' => null, 'formas_pagamento' => null, 'idiomas' => null];
$horario = json_decode((string) ($d['horario'] ?? ''), true) ?: [];
$pagamentos = json_decode((string) ($d['formas_pagamento'] ?? ''), true) ?: [];
$idiomas = json_decode((string) ($d['idiomas'] ?? ''), true) ?: [];
$erro = '';


// Esteira: formulário próprio (form-esteira), só com o item salvo.
$acaoEsteira = (string) ($_POST['acao_esteira'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acaoEsteira !== '' && !$novo) {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        avisar('A página ficou aberta tempo demais. Tente de novo.', 'ruim');
    } elseif ($acaoEsteira === 'enviar' && $podeEditar) {
        Esteira::enviar($id) ? avisar($item['nome'] . ' foi para a conferência.') : avisar('Este item não está em coleta.', 'atencao');
    } elseif ($acaoEsteira === 'homologar' && Esteira::coordena()) {
        $falta = Esteira::homologar($id);
        $falta ? avisar('Ainda falta: ' . implode(', ', $falta) . '.', 'atencao') : avisar($item['nome'] . ' homologado: pronto para o portal e para o EventCity.');
    } elseif ($acaoEsteira === 'arquivar' && Esteira::arquiva()) {
        Esteira::arquivar($id) ? avisar($item['nome'] . ' arquivado: saiu das listas e do portal, sem apagar nada.') : avisar('Este item já estava arquivado.', 'atencao');
    } elseif ($acaoEsteira === 'desarquivar' && Esteira::arquiva()) {
        Esteira::desarquivar($id) ? avisar($item['nome'] . ' voltou para o inventário.') : avisar('Este item não estava arquivado.', 'atencao');
    } elseif ($acaoEsteira === 'devolver' && Esteira::coordena()) {
        Esteira::devolver($id, (string) ($_POST['devolucao_nota'] ?? ''))
            ? avisar($item['nome'] . ' voltou para o campo com a sua nota.')
            : avisar('Escreva o que acertar em campo para devolver.', 'atencao');
    } else {
        avisar('Você não pode fazer isso neste item.', 'ruim');
    }
    fichaSalva(endereco('item', $id, (string) $item['nome']));
}

// Fotos: formulário próprio (fora do formulário da ficha), só com o item salvo.
$acaoFoto = (string) ($_POST['acao_foto'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acaoFoto !== '' && !$novo) {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeEditar) {
        avisar('A página ficou aberta tempo demais, ou você não pode alterar este item.', 'ruim');
    } elseif ($acaoFoto === 'enviar') {
        $arquivos = $_FILES['fotos'] ?? null;
        $credito = mb_substr(trim((string) ($_POST['credito'] ?? '')), 0, 120) ?: null;
        $enviadas = 0;
        $falhas = [];
        foreach ((array) ($arquivos['name'] ?? []) as $k => $nomeArquivo) {
            $um = ['name' => $nomeArquivo, 'tmp_name' => $arquivos['tmp_name'][$k] ?? '', 'error' => $arquivos['error'][$k] ?? UPLOAD_ERR_NO_FILE,
                   'size' => $arquivos['size'][$k] ?? 0];
            if ($um['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $falha = Inventario::guardarFoto($id, $um, $credito);
            $falha === null ? $enviadas++ : $falhas[] = $nomeArquivo . ': ' . $falha;
        }
        if ($enviadas) {
            Inventario::registrarHistorico($id, 'alterou', null, null, plural($enviadas, 'foto enviada', 'fotos enviadas'));
        }
        avisar(($enviadas ? plural($enviadas, 'foto guardada', 'fotos guardadas') . '. ' : '') . implode(' ', $falhas), $falhas ? 'atencao' : 'bom');
    } elseif ($acaoFoto === 'capa') {
        Inventario::definirCapa($id, (int) ($_POST['foto_id'] ?? 0));
        avisar('Capa trocada.');
    } elseif ($acaoFoto === 'legenda') {
        Inventario::legendarFoto($id, (int) ($_POST['foto_id'] ?? 0), mb_substr(trim((string) ($_POST['legenda'] ?? '')), 0, 200) ?: null,
            mb_substr(trim((string) ($_POST['credito'] ?? '')), 0, 120) ?: null);
        avisar('Legenda salva.');
    } elseif ($acaoFoto === 'excluir') {
        Inventario::excluirFoto($id, (int) ($_POST['foto_id'] ?? 0));
        Inventario::registrarHistorico($id, 'alterou', null, null, 'excluiu uma foto');
        avisar('Foto excluída.');
    }
    // Foto (legenda, capa, excluir, enviar) não fecha o modal: volta para a
    // própria ficha, na aba Fotos, com o aviso do que foi feito.
    redirecionar(noPopup(endereco('item', $id, (string) $item['nome'])) . '#fotos');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acaoFoto === '' && $acaoEsteira === '') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!$podeEditar) {
        $erro = 'Você não pode alterar o inventário.';
    } else {
        $situacao = in_array($_POST['situacao'] ?? '', $situacoesPermitidas, true) ? (string) $_POST['situacao'] : (string) $d['situacao'];
        // A mesma leitura da coleta de campo (app/FichaFormulario.php).
        $lido = FichaFormulario::ler($_POST, $ficha);
        $nucleo = ['situacao' => $situacao] + $lido['nucleo'];
        $especificos = $lido['especificos'];
        $acessNovo = $lido['acess'];
        $horarioNovo = json_decode((string) ($nucleo['horario'] ?? ''), true) ?: [];
        // Sem coordenada, mas com plus code: o código vira latitude e longitude
        // (o curto se recupera pela posição do município do destino).
        if (!empty($nucleo['plus_code']) && ($nucleo['latitude'] ?? null) === null) {
            $ref = $pdo->prepare('SELECT m.latitude, m.longitude FROM empresas e JOIN municipios m ON m.id = e.municipio_id WHERE e.id = ?');
            $ref->execute([Empresa::id()]);
            $r = $ref->fetch() ?: [];
            if ($c = PlusCode::coordenada($nucleo['plus_code'], isset($r['latitude']) ? (float) $r['latitude'] : null, isset($r['longitude']) ? (float) $r['longitude'] : null)) {
                [$nucleo['latitude'], $nucleo['longitude']] = $c;
            }
        }
        if ($novo) {
            $nucleo['origem'] = 'painel';
            $nucleo['pesquisador_id'] = (int) Auth::usuarioOuNulo()['id'];
        }
        if ($nucleo['nome'] === '') {
            $erro = 'Dê o nome do item.';
        } elseif ($tipos && $nucleo['tipo_id'] === null) {
            $erro = 'Escolha o tipo na ficha ' . $ficha['codigo'] . '.';
        } elseif ($lido['erros']) {
            $erro = (string) reset($lido['erros']);
        } elseif (!empty($nucleo['cnpj'])) {
            // O mesmo CNPJ pode ter mais de uma ficha (hotel e o restaurante
            // aberto ao público), mas não na mesma ficha.
            $dup = $pdo->prepare("SELECT nome FROM inventario_itens WHERE empresa_id = ? AND cnpj = ? AND ficha_id = ? AND id <> ? AND situacao <> 'arquivada'");
            $dup->execute([Empresa::id(), $nucleo['cnpj'], $fichaId, $id]);
            if ($outro = $dup->fetchColumn()) {
                $erro = 'O CNPJ já está nesta ficha: ' . $outro . '.';
            }
        }
        if ($erro === '') {
            $id = Inventario::salvar($novo ? null : $id, $fichaId, $nucleo, $especificos, $acessNovo, $lido['acess_outro']);
            if ($ficha['forma'] !== 'municipio') {
                Inventario::salvarFacilidades($id, (array) ($_POST['facilidades'] ?? []));
            }
            avisar(($novo ? $nucleo['nome'] . ' entrou no inventário.' : $nucleo['nome'] . ' salvo.'));
            fichaSalva(endereco('item', $id, $nucleo['nome']));
        }
        // O que foi digitado volta para a tela.
        $d = array_merge($d, $nucleo, ['cnpj' => $_POST['cnpj'] ?? '', 'telefone' => $_POST['telefone'] ?? '', 'whatsapp' => $_POST['whatsapp'] ?? '']);
        $horario = $horarioNovo;
        $pagamentos = json_decode((string) ($nucleo['formas_pagamento'] ?? ''), true) ?: [];
        $idiomas = json_decode((string) ($nucleo['idiomas'] ?? ''), true) ?: [];
        foreach ($especificos as $aid => $v) {
            $valores[$aid] = ['valor_texto' => $v['texto'], 'valor_numero' => $v['numero'], 'valor_outro' => $v['outro'] ?? null, 'ausencia' => $v['ausencia']];
        }
        $acess = $acessNovo;
    }
}

if (!$novo && !emPopup()) {
    canonizar('item', $id, (string) $d['nome']);
}
$ro = $podeEditar ? '' : ' readonly';
$dis = $podeEditar ? '' : ' disabled';
$titulo = $novo ? 'Novo item: ' . $ficha['nome'] : (string) $d['nome'];
$acao = $novo ? endereco('item', null) . '?ficha=' . $fichaId : endereco('item', $id, (string) $d['nome']);
[$rotuloSituacao, $tomSituacao] = Inventario::SITUACOES[$d['situacao']] ?? [$d['situacao'], 'nu'];

/** Campo de ausência (NA, NI, NE) ao lado de um campo específico. */
$ausencia = function (int $aid, ?string $atual) use ($dis): string {
    $h = '<select class="ausencia" name="aus[' . $aid . ']" aria-label="Sem resposta"' . $dis . '><option value="">Respondido</option>';
    foreach (Inventario::AUSENCIAS as $k => $r) {
        $h .= '<option value="' . $k . '"' . ($atual === $k ? ' selected' : '') . '>' . e(strtoupper($k)) . '</option>';
    }
    return $h . '</select>';
};
/** Lista de caixas para múltipla escolha dentro do formulário. */
$caixas = function (string $nome, array $opcoes, array $marcadas) use ($dis): string {
    $h = '<div class="caixas">';
    foreach ($opcoes as $op) {
        $h .= '<label class="check"><input type="checkbox" name="' . e($nome) . '[]" value="' . e((string) $op) . '"'
            . (in_array($op, $marcadas, true) ? ' checked' : '') . $dis . '><i></i>' . e((string) $op) . '</label>';
    }
    return $h . '</div>';
};

paginaFicha($titulo, 'inventario');
?>
<div class="trilha"><a href="/inventario">Inventário</a><?= icone('seta') ?><span><?= $novo ? 'Novo' : e((string) $d['nome']) ?></span></div>
<?php if ($erro !== ''): ?>
  <p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p>
<?php endif; ?>

<form method="post" action="<?= e($acao) ?>" id="form-item">
  <button type="submit" class="oculto-visual" tabindex="-1" aria-hidden="true">Salvar</button>
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <input type="hidden" name="ficha_id" value="<?= $fichaId ?>">
  <div class="cab-pag">
    <div>
      <h1 data-titulo><?= e($titulo) ?></h1>
      <p><span class="dado"><?= e((string) $ficha['codigo']) ?></span> <?= e((string) $ficha['nome']) ?> · <span class="pilula <?= $tomSituacao ?>"><?= e($rotuloSituacao) ?></span></p>
    </div>
    <?php if ($podeEditar || (!$novo && Esteira::arquiva())): ?>
      <div class="acoes">
        <?php if (!$novo && Esteira::arquiva()): $arquivada = ($d['situacao'] ?? '') === 'arquivada'; ?>
          <label class="liga liga-cab" title="Arquivado sai das listas, do portal e do EventCity, sem apagar nada">
            <input type="checkbox" data-alterna-esteira="<?= $arquivada ? 'desarquivar' : 'arquivar' ?>"<?= $arquivada ? ' checked' : '' ?>
              data-pergunta="<?= $arquivada ? 'Tirar ' . e((string) $d['nome']) . ' do arquivo e voltar ao inventário?' : 'Arquivar ' . e((string) $d['nome']) . '? Sai das listas, do portal e do EventCity, sem apagar nada.' ?>">
            <i></i><em class="liga-sim">Arquivada</em><em class="liga-nao">Arquivada</em></label>
        <?php endif; ?>
        <?php if ($podeEditar): ?><button type="submit" class="botao"><?= icone('check') ?>Salvar</button><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php if ($ficha['ajuda']): ?>
    <p class="aviso info"><?= icone('manual') ?><span><?= e((string) $ficha['ajuda']) ?></span></p>
  <?php endif; ?>

  <?php if (!$novo):
    $pendencias = Esteira::pendencias($item);
    $coordenaAqui = Esteira::coordena();
    $emColeta = in_array($d['situacao'], Esteira::EM_COLETA, true); ?>
    <?php if ($emColeta && !empty($item['devolucao_nota'])): ?>
      <p class="aviso atencao"><?= icone('alerta') ?><span><strong>Devolvido pela conferência:</strong> <?= e((string) $item['devolucao_nota']) ?></span></p>
    <?php endif; ?>
    <?php if (($emColeta && $podeEditar) || ($d['situacao'] === 'em_conferencia' && $coordenaAqui)): ?>
    <div class="esteira-item <?= $pendencias ? 'com-pendencia' : 'pronto' ?>">
      <div class="esteira-texto">
        <?php if ($pendencias): ?>
          <b><?= icone('alerta') ?>Para homologar falta</b>
          <span><?= e(implode(' · ', $pendencias)) ?></span>
        <?php else: ?>
          <b><?= icone('check') ?>Núcleo mínimo completo</b>
          <span><?= $d['situacao'] === 'em_conferencia' ? 'Confira os dados e as fotos e homologue.' : 'Pronto para a conferência.' ?></span>
        <?php endif; ?>
      </div>
      <div class="esteira-acoes">
        <?php if ($emColeta && $podeEditar): ?>
          <button type="submit" form="form-esteira" name="acao_esteira" value="enviar" class="botao claro"><?= icone('enviar') ?>Enviar para conferência</button>
        <?php endif; ?>
        <?php if ($d['situacao'] === 'em_conferencia' && $coordenaAqui): ?>
          <details class="devolver">
            <summary class="botao claro"><?= icone('trocar') ?>Devolver para o campo</summary>
            <div class="devolver-caixa">
              <label for="devolucao_nota">O que acertar em campo</label>
              <div class="entrada"><textarea id="devolucao_nota" name="devolucao_nota" form="form-esteira" rows="3" maxlength="1000" placeholder="Ex.: falta foto da fachada; conferir o número de leitos."></textarea></div>
              <button type="submit" form="form-esteira" name="acao_esteira" value="devolver" class="botao claro">Devolver</button>
            </div>
          </details>
          <button type="submit" form="form-esteira" name="acao_esteira" value="homologar" class="botao"<?= $pendencias ? ' disabled title="Complete o núcleo mínimo antes"' : '' ?>><?= icone('certificado') ?>Homologar</button>
        <?php endif; ?>
      </div>
    </div>
    <?php elseif ($d['situacao'] === 'homologada' && !empty($item['homologado_em'])): ?>
      <p class="aviso bom"><?= icone('certificado') ?><span>Homologado em <?= e(dataBr((string) $item['homologado_em'], 'd/m/Y')) ?>: pronto para o portal e para o EventCity.</span></p>
    <?php endif; ?>
  <?php endif; ?>

  <?php
  // Abas: a ficha inteira numa página só ficava comprida demais.
  $fotos = $novo ? [] : Inventario::fotos($id);
  $temFicha = (bool) $atributos || in_array($ficha['eventcity'], ['espaco_eventos', 'atrativo'], true);
  $temAcess = $ficha['forma'] !== 'municipio' && $subitensAcess;
  $temTribos = $ficha['forma'] !== 'municipio';
  $abas = ['dados' => ['Dados', 'etiqueta', null]];
  if ($temFicha) { $abas['ficha'] = ['Ficha ' . $ficha['codigo'], 'lista', null]; }
  if ($temAcess) { $abas['acessibilidade'] = ['Acessibilidade', 'cadeirante', null]; }
  if ($temTribos) { $abas['tribos'] = ['Tribos', 'pessoas', null]; }
  $abas['textos'] = ['Textos e histórico', 'lapis', null];
  if (!$novo) { $abas['fotos'] = ['Fotos', 'camera', count($fotos)]; }
  if (!$novo && $ficha['forma'] !== 'municipio') { $abas['experiencias'] = ['Experiências', 'natureza', null]; }
  ?>
  <nav class="abas-chamado abas-item" role="tablist" aria-label="Partes da ficha">
    <?php foreach ($abas as $chaveAba => [$rotuloAba, $iconeAba, $contaAba]): ?>
      <button type="button" role="tab" data-aba="<?= $chaveAba ?>" aria-selected="<?= $chaveAba === 'dados' ? 'true' : 'false' ?>"><?= icone($iconeAba) ?><?= e($rotuloAba) ?><?= $contaAba !== null ? '<span class="conta-aba">' . $contaAba . '</span>' : '' ?></button>
    <?php endforeach; ?>
  </nav>

  <div class="painel-aba" data-painel="dados">

  <div class="colunas">
    <div class="cartao">
      <h2 class="titulo-secao">Identificação</h2>
      <div class="grade">
        <div class="campo c-12">
          <label for="nome">Nome <span class="obrig">obrigatório</span></label>
          <div class="entrada"><input type="text" id="nome" name="nome" required maxlength="180" value="<?= e((string) $d['nome']) ?>"<?= $ro ?>></div>
          <span class="ajuda">Nome fantasia ou denominação oficial.</span>
        </div>
        <?php if ($tipos): ?>
        <div class="campo c-12">
          <label for="tipo_id">Tipo <span class="obrig">obrigatório</span></label>
          <div class="entrada"><select id="tipo_id" name="tipo_id"<?= $dis ?>>
            <option value="">Escolha</option>
            <?php foreach ($tipos as $t): ?>
              <option value="<?= (int) $t['id'] ?>"<?= (int) $d['tipo_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e(($t['pai_nome'] ? $t['pai_nome'] . ': ' : '') . $t['nome']) ?></option>
            <?php endforeach; ?>
          </select></div>
        </div>
        <?php endif; ?>
        <div class="campo c-6">
          <label for="nome_popular">Nome popular</label>
          <div class="entrada"><input type="text" id="nome_popular" name="nome_popular" maxlength="180" value="<?= e((string) ($d['nome_popular'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="situacao">Situação</label>
          <div class="entrada"><select id="situacao" name="situacao"<?= $dis ?>>
            <?php foreach ($situacoesPermitidas as $s): ?>
              <option value="<?= $s ?>"<?= $d['situacao'] === $s ? ' selected' : '' ?>><?= e(Inventario::SITUACOES[$s][0]) ?></option>
            <?php endforeach; ?>
          </select></div>
        </div>
        <div class="campo c-6">
          <label for="cnpj">CNPJ<?= $ficha['forma'] === 'municipio' ? ' da prefeitura ou do órgão' : '' ?></label>
          <div class="entrada"><?= icone('predio') ?><input type="text" id="cnpj" name="cnpj" class="cnpj" maxlength="18" autocomplete="off"
                 value="<?= e(!empty($d['cnpj']) && strlen((string) $d['cnpj']) === 14 ? formatarCnpj((string) $d['cnpj']) : (string) ($d['cnpj'] ?? '')) ?>"<?= $ro ?>></div>
          <span class="ajuda">Inventarie também quem não tem CNPJ: o manual manda incluir todo estabelecimento aberto ao público.</span>
        </div>
        <?php if ($ficha['forma'] !== 'municipio'): ?>
        <div class="campo c-6">
          <label for="razao_social">Razão social</label>
          <div class="entrada"><input type="text" id="razao_social" name="razao_social" maxlength="180" value="<?= e((string) ($d['razao_social'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="cnae">Código CNAE</label>
          <div class="entrada"><input type="text" id="cnae" name="cnae" maxlength="10" inputmode="numeric" placeholder="5510801" value="<?= e((string) ($d['cnae'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-8">
          <label for="atividade_economica">Atividade econômica</label>
          <div class="entrada"><input type="text" id="atividade_economica" name="atividade_economica" maxlength="255" value="<?= e((string) ($d['atividade_economica'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="inscricao_municipal">Inscrição municipal</label>
          <div class="entrada"><input type="text" id="inscricao_municipal" name="inscricao_municipal" maxlength="30" value="<?= e((string) ($d['inscricao_municipal'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="cadastur">Cadastur</label>
          <div class="entrada"><input type="text" id="cadastur" name="cadastur" maxlength="30" value="<?= e((string) ($d['cadastur'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="cadastur_validade">Validade do Cadastur</label>
          <div class="entrada"><input type="date" id="cadastur_validade" name="cadastur_validade" value="<?= e((string) ($d['cadastur_validade'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="natureza">Natureza</label>
          <div class="entrada"><select id="natureza" name="natureza"<?= $dis ?>><option value="">Não informada</option>
            <?php foreach (Inventario::NATUREZAS as $k => $r): ?><option value="<?= $k ?>"<?= ($d['natureza'] ?? '') === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
          </select></div>
          <div class="entrada outro-texto" data-outro-de="natureza" data-outro-valor="outra"><input type="text" name="natureza_outra" maxlength="120" placeholder="Qual?" aria-label="Natureza: qual outra" value="<?= e((string) ($d['natureza_outra'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="tipo_organizacao">Tipo de organização</label>
          <div class="entrada"><select id="tipo_organizacao" name="tipo_organizacao"<?= $dis ?>><option value="">Não informado</option>
            <?php foreach (Inventario::TIPOS_ORGANIZACAO as $k => $r): ?><option value="<?= $k ?>"<?= ($d['tipo_organizacao'] ?? '') === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
          </select></div>
          <div class="entrada outro-texto" data-outro-de="tipo_organizacao" data-outro-valor="outros"><input type="text" name="tipo_organizacao_outro" maxlength="120" placeholder="Qual?" aria-label="Tipo de organização: qual outro" value="<?= e((string) ($d['tipo_organizacao_outro'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="jurisdicao">Jurisdição</label>
          <div class="entrada"><select id="jurisdicao" name="jurisdicao"<?= $dis ?>><option value="">Não informada</option>
            <?php foreach (Inventario::JURISDICOES as $k => $r): ?><option value="<?= $k ?>"<?= ($d['jurisdicao'] ?? '') === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
          </select></div>
        </div>
        <div class="campo c-4">
          <label for="inicio_atividade">Início da atividade</label>
          <div class="entrada"><input type="text" id="inicio_atividade" name="inicio_atividade" maxlength="10" inputmode="numeric" placeholder="dd/mm/aaaa ou o ano" data-data-ou-ano
            value="<?= e(!empty($d['inicio_atividade_data']) ? date('d/m/Y', strtotime((string) $d['inicio_atividade_data'])) : (string) ($d['inicio_atividade'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-8">
          <label for="rede">Rede, holding ou franquia</label>
          <div class="entrada"><input type="text" id="rede" name="rede" maxlength="120" value="<?= e((string) ($d['rede'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="funcionarios">Funcionários permanentes</label>
          <div class="entrada"><input type="number" id="funcionarios" name="funcionarios" min="0" value="<?= e((string) ($d['funcionarios'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="funcionarios_temporarios">Funcionários temporários</label>
          <div class="entrada"><input type="number" id="funcionarios_temporarios" name="funcionarios_temporarios" min="0" value="<?= e((string) ($d['funcionarios_temporarios'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="funcionarios_pcd">Pessoas com deficiência (%)</label>
          <div class="entrada"><input type="text" id="funcionarios_pcd" name="funcionarios_pcd" inputmode="decimal" maxlength="6" value="<?= e(($d['funcionarios_pcd'] ?? null) !== null ? rtrim(rtrim(number_format((float) $d['funcionarios_pcd'], 2, ',', ''), '0'), ',') : '') ?>"<?= $ro ?>></div>
          <span class="ajuda">Entre os funcionários.</span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php $soLugar = $ficha['forma'] !== 'municipio'; ?>
    <div class="cartao">
      <h2 class="titulo-secao"><?= $soLugar ? 'Localização' : ($ficha['ordem'] == 13 ? 'Endereço da Secretaria de Educação' : 'Endereço da prefeitura') ?></h2>
      <div class="grade">
        <?php if ($soLugar): ?>
        <div class="campo c-4">
          <label for="zona">Zona</label>
          <div class="entrada"><select id="zona" name="zona"<?= $dis ?>><option value="">Não informada</option>
            <?php foreach (Inventario::ZONAS as $k => $r): ?><option value="<?= $k ?>"<?= ($d['zona'] ?? '') === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
          </select></div>
        </div>
        <?php endif; ?>
        <?php if ($ficha['forma'] === 'linear'): ?>
        <div class="campo c-4">
          <label for="extensao_km">Extensão no município (km)</label>
          <div class="entrada"><input type="text" id="extensao_km" name="extensao_km" inputmode="decimal" value="<?= e((string) ($d['extensao_km'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <?php endif; ?>
        <div class="campo c-4">
          <label for="cep">CEP</label>
          <div class="entrada"><input type="text" id="cep" name="cep" inputmode="numeric" maxlength="9" placeholder="00000-000" value="<?= e(formatarCep((string) ($d['cep'] ?? ''))) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-8">
          <label for="logradouro">Logradouro</label>
          <div class="entrada"><input type="text" id="logradouro" name="logradouro" maxlength="200" value="<?= e((string) ($d['logradouro'] ?? '')) ?>"<?= $ro ?>></div>
          <span class="ajuda">Rua, rodovia, linha rural ou comunidade.</span>
        </div>
        <div class="campo c-4">
          <label for="numero">Número</label>
          <div class="entrada"><input type="text" id="numero" name="numero" maxlength="20" value="<?= e((string) ($d['numero'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="bairro">Bairro ou localidade</label>
          <div class="entrada"><input type="text" id="bairro" name="bairro" maxlength="120" value="<?= e((string) ($d['bairro'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="distrito">Distrito</label>
          <div class="entrada"><input type="text" id="distrito" name="distrito" maxlength="120" value="<?= e((string) ($d['distrito'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="complemento">Complemento</label>
          <div class="entrada"><input type="text" id="complemento" name="complemento" maxlength="80" value="<?= e((string) ($d['complemento'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="latitude">Latitude</label>
          <div class="entrada"><input type="text" id="latitude" name="latitude" inputmode="decimal" placeholder="-26.229512" value="<?= e((string) ($d['latitude'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="longitude">Longitude</label>
          <div class="entrada"><input type="text" id="longitude" name="longitude" inputmode="decimal" placeholder="-52.671604" value="<?= e((string) ($d['longitude'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="plus_code">Plus code</label>
          <div class="entrada"><input type="text" id="plus_code" name="plus_code" maxlength="16" placeholder="Q8FF+VM" autocapitalize="characters" value="<?= e((string) ($d['plus_code'] ?? '')) ?>"<?= $ro ?>></div>
          <span class="ajuda">O código do Google Maps. Sem latitude, ela sai daqui ao gravar.</span>
        </div>
        <?php if ($podeEditar): ?>
        <div class="campo c-12">
          <div class="endereco-coordenada" data-endereco-coordenada>
            <button type="button" class="botao claro" data-buscar-endereco><?= icone('busca') ?>Buscar endereço pela coordenada</button>
            <span class="endereco-achado" hidden><span data-endereco-texto></span>
              <button type="button" class="botao mini" data-usar-endereco hidden><?= icone('check') ?>Usar este endereço</button></span>
          </div>
        </div>
        <?php endif; ?>
        <div class="campo c-12">
          <label for="maps_url">Link do mapa</label>
          <div class="entrada"><?= icone('mapa') ?><input type="url" id="maps_url" name="maps_url" maxlength="500" value="<?= e((string) ($d['maps_url'] ?? '')) ?>"<?= $ro ?>></div>
          <span class="ajuda">O "Como chegar" do portal. Em campo, a coordenada vem do GPS do celular.</span>
        </div>
        <?php if ($soLugar): ?>
        <div class="campo c-12">
          <label for="referencia">Pontos de referência</label>
          <div class="entrada"><input type="text" id="referencia" name="referencia" maxlength="255" value="<?= e((string) ($d['referencia'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <?php foreach (['sinalizacao_acesso' => 'Sinalização de acesso', 'sinalizacao_turistica' => 'Sinalização turística'] as $c => $r): ?>
        <div class="campo c-6">
          <label for="<?= $c ?>"><?= $r ?></label>
          <div class="entrada"><select id="<?= $c ?>" name="<?= $c ?>"<?= $dis ?>><option value="">Não informada</option>
            <option value="1"<?= ($d[$c] ?? null) !== null && (int) $d[$c] === 1 ? ' selected' : '' ?>>Sim</option>
            <option value="0"<?= ($d[$c] ?? null) !== null && (int) $d[$c] === 0 ? ' selected' : '' ?>>Não</option>
          </select></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="cartao">
      <h2 class="titulo-secao">Contato público</h2>
      <div class="grade">
        <div class="campo c-4">
          <label for="telefone">Telefone</label>
          <div class="entrada"><input type="text" id="telefone" name="telefone" class="telefone" value="<?= e(!empty($d['telefone']) && ctype_digit((string) $d['telefone']) ? formatarTelefone((string) $d['telefone']) : (string) ($d['telefone'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-3">
          <label for="ramal">Ramal</label>
          <div class="entrada"><input type="text" id="ramal" name="ramal" maxlength="10" inputmode="numeric" value="<?= e((string) ($d['ramal'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-5">
          <label for="whatsapp">WhatsApp</label>
          <div class="entrada"><?= icone('whatsapp') ?><input type="text" id="whatsapp" name="whatsapp" class="telefone" value="<?= e(!empty($d['whatsapp']) && ctype_digit((string) $d['whatsapp']) ? formatarTelefone((string) $d['whatsapp']) : (string) ($d['whatsapp'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-12">
          <label for="email">E-mail</label>
          <div class="entrada"><?= icone('emails') ?><input type="email" id="email" name="email" maxlength="150" value="<?= e((string) ($d['email'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="site">Site</label>
          <div class="entrada"><input type="url" id="site" name="site" maxlength="255" value="<?= e((string) ($d['site'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="instagram">Instagram</label>
          <div class="entrada"><input type="text" id="instagram" name="instagram" maxlength="100" placeholder="@perfil" value="<?= e(!empty($d['instagram']) ? '@' . $d['instagram'] : '') ?>"<?= $ro ?>></div>
        </div>
      </div>
      <p class="ajuda">Contato que o turista vê. O dado pessoal de quem responde pelo negócio fica na conta dele, nunca aqui.</p>
    </div>

    <?php if ($soLugar): ?>
    <div class="cartao">
      <h2 class="titulo-secao">Funcionamento</h2>
      <?= semanaDeHorario($horario, 'data-semana', !$podeEditar) ?>
      <div class="grade">
        <div class="campo c-3">
          <label for="h_feriados_abre">Funciona em feriados</label>
          <div class="entrada"><select id="h_feriados_abre" name="h_feriados_abre"<?= $dis ?>><option value="">Não informado</option>
            <option value="1"<?= ($horario['feriados_abre'] ?? null) === true ? ' selected' : '' ?>>Sim</option>
            <option value="0"<?= ($horario['feriados_abre'] ?? null) === false ? ' selected' : '' ?>>Não</option></select></div>
        </div>
        <div class="campo c-3">
          <label for="h_feriados">Feriados (detalhe)</label>
          <div class="entrada"><input type="text" id="h_feriados" name="h_feriados" maxlength="200" placeholder="Só nacionais, fecha no Natal..." value="<?= e((string) ($horario['feriados'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="h_temporada">Temporada ou agendamento</label>
          <div class="entrada"><input type="text" id="h_temporada" name="h_temporada" maxlength="200" value="<?= e((string) ($horario['temporada'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
      </div>
      <h3 class="sub-cartao">Formas de pagamento</h3>
      <?= $caixas('pagamentos', Inventario::PAGAMENTOS, $pagamentos) ?>
      <div class="campo">
      <h3 class="sub-cartao">Atendimento em</h3>
      <?= $caixas('idiomas', Inventario::IDIOMAS, $idiomas) ?>
      <div class="entrada outro-texto" data-outro-at="idiomas"><input type="text" name="idiomas_outro" maxlength="120" placeholder="Qual idioma?" aria-label="Outro idioma: qual" value="<?= e((string) ($d['idiomas_outro'] ?? '')) ?>"<?= $ro ?>></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  </div>

  <?php if ($temFicha): ?><div class="painel-aba" data-painel="ficha" hidden><?php endif; ?>
  <?php if (in_array($ficha['eventcity'], ['espaco_eventos', 'atrativo'], true)): ?>
  <div class="cartao">
    <h2 class="titulo-secao">Capacidade</h2>
    <div class="grade">
      <div class="campo c-6">
        <label for="capacidade_publico">Público simultâneo (pessoas)</label>
        <div class="entrada"><input type="number" id="capacidade_publico" name="capacidade_publico" min="0" value="<?= e((string) ($d['capacidade_publico'] ?? '')) ?>"<?= $ro ?>></div>
        <span class="ajuda">Vai para o EventCity, que compara com o público esperado dos eventos.</span>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php
  /** Uma linha de tabela da ficha, com cada célula pelo tipo da coluna. */
  $linhaTabela = function (int $aid, int $n, array $colunas, array $linha) use ($ro, $dis): string {
      $h = '<tr>';
      foreach ($colunas as $k => $col) {
          $nome = 'at[' . $aid . '][' . $n . '][' . $k . ']';
          $v = $linha[$k] ?? '';
          $rot = e($col['rotulo']) . ', linha ' . ($n + 1);
          $h .= '<td>';
          if ($col['tipo'] === 'meses') {
              $marcados = array_map('strval', (array) $v);
              $h .= '<div class="meses-celula" role="group" aria-label="' . $rot . '">';
              foreach (Inventario::MESES as $mes) {
                  $h .= '<label class="check mes" title="' . e($mes) . '"><input type="checkbox" name="' . $nome . '[]" value="' . e($mes) . '"'
                      . (in_array($mes, $marcados, true) ? ' checked' : '') . $dis . '><i></i>' . e(mb_substr($mes, 0, 3)) . '</label>';
              }
              $h .= '</div>';
          } elseif ($col['tipo'] === 'data') {
              $h .= '<div class="entrada mini"><input type="date" name="' . $nome . '" aria-label="' . $rot . '" value="' . e(is_scalar($v) ? (string) $v : '') . '"' . $ro . '></div>';
          } elseif ($col['tipo'] === 'sim_nao' || $col['tipo'] === 'escolha') {
              $ops = $col['tipo'] === 'sim_nao' ? ['1' => 'Sim', '0' => 'Não'] : array_combine($col['opcoes'], $col['opcoes']);
              $h .= '<div class="entrada mini"><select name="' . $nome . '" aria-label="' . $rot . '"' . $dis . '><option value=""></option>';
              foreach ($ops as $ov => $or) {
                  $h .= '<option value="' . e((string) $ov) . '"' . ((string) $v === (string) $ov && $v !== '' ? ' selected' : '') . '>' . e((string) $or) . '</option>';
              }
              $h .= '</select></div>';
          } else {
              $h .= '<div class="entrada mini"><input type="text" name="' . $nome . '" maxlength="200"' . ($col['tipo'] === 'numero' ? ' inputmode="decimal"' : '')
                  . ' aria-label="' . $rot . '" value="' . e(is_scalar($v) ? (string) $v : '') . '"' . $ro . '></div>';
          }
          $h .= '</td>';
      }
      return $h . '</tr>';
  };
  /** O texto do "Outros ____", que aparece quando a opção Outros está marcada. */
  $outroTexto = function (int $aid, array $at, ?string $valor) use ($ro): string {
      if (empty($at['config']['outros'])) {
          return '';
      }
      return '<div class="entrada outro-texto" data-outro-at="' . $aid . '"><input type="text" name="outro[' . $aid . ']" maxlength="255" placeholder="Quais? Especifique"'
          . ' aria-label="' . e((string) $at['rotulo']) . ': especifique o outro" value="' . e((string) $valor) . '"' . $ro . '></div>';
  };
  ?>
  <?php foreach ($atributos as $grupo => $lista): ?>
  <div class="cartao">
    <h2 class="titulo-secao"><?= e((string) $grupo) ?></h2>
    <div class="grade">
      <?php foreach ($lista as $at): $aid = (int) $at['id']; $v = $valores[$aid] ?? null; $cfg = (array) ($at['config'] ?? []);
        $txt = $v['valor_texto'] ?? null; $num = $v['valor_numero'] ?? null; $aus = $v['ausencia'] ?? null; $outro = $v['valor_outro'] ?? null;
        $largo = in_array($at['tipo'], ['texto_longo', 'multipla', 'tabela'], true); ?>
        <div class="campo <?= $largo ? 'c-12' : 'c-6' ?>">
          <label for="at-<?= $aid ?>"><?= e((string) $at['rotulo']) ?><?= $at['obrigatorio'] ? ' <span class="obrig">núcleo do MTur</span>' : '' ?></label>
          <?php switch ($at['tipo']):
            case 'numero': ?>
              <div class="entrada"><input type="text" id="at-<?= $aid ?>" name="at[<?= $aid ?>]" inputmode="decimal" value="<?= $num !== null ? e(rtrim(rtrim(number_format((float) $num, 2, ',', ''), '0'), ',')) : '' ?>"<?= $ro ?>><?= $ausencia($aid, $aus) ?></div>
            <?php break; case 'data': ?>
              <div class="entrada"><input type="date" id="at-<?= $aid ?>" name="at[<?= $aid ?>]" value="<?= e((string) $txt) ?>"<?= $ro ?>><?= $ausencia($aid, $aus) ?></div>
            <?php break; case 'sim_nao': ?>
              <div class="entrada"><select id="at-<?= $aid ?>" name="at[<?= $aid ?>]"<?= $dis ?>><option value="">Não respondido</option>
                <option value="1"<?= $num !== null && (float) $num === 1.0 ? ' selected' : '' ?>>Sim</option>
                <option value="0"<?= $num !== null && (float) $num === 0.0 ? ' selected' : '' ?>>Não</option></select><?= $ausencia($aid, $aus) ?></div>
            <?php break; case 'escolha': ?>
              <div class="entrada"><select id="at-<?= $aid ?>" name="at[<?= $aid ?>]"<?= $dis ?>><option value="">Não respondido</option>
                <?php foreach ($at['opcoes'] as $op): ?><option<?= $txt === $op ? ' selected' : '' ?>><?= e((string) $op) ?></option><?php endforeach; ?>
              </select><?= $ausencia($aid, $aus) ?></div>
              <?= $outroTexto($aid, $at, $outro) ?>
            <?php break; case 'multipla': ?>
              <div data-maximo="<?= $podeEditar ? (int) ($cfg['maximo'] ?? 0) : 0 ?>"><?= $caixas('at[' . $aid . ']', $at['opcoes'], json_decode((string) $txt, true) ?: []) ?></div>
              <?= $outroTexto($aid, $at, $outro) ?>
              <div class="entrada estreita"><?= $ausencia($aid, $aus) ?></div>
              <?php if (!empty($cfg['maximo'])): ?><span class="ajuda">Marque até <?= (int) $cfg['maximo'] ?>.</span><?php endif; ?>
            <?php break; case 'tabela':
              $colunas = FichaFormulario::colunas($at);
              $linhas = json_decode((string) $txt, true) ?: [];
              // Linhas sugeridas pela ficha (Auditório, Salas modulares...): a primeira coluna já vem escrita.
              if (!$linhas && !empty($cfg['linhas'])) {
                  $linhas = array_map(fn ($l) => [(string) $l], (array) $cfg['linhas']);
              }
              $linhas[] = []; ?>
              <div class="rolagem"><table class="dados tabela-campo" data-tabela-at="<?= $aid ?>">
                <thead><tr><?php foreach ($colunas as $col): ?><th><?= e($col['rotulo']) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($linhas as $n => $linha): ?><?= $linhaTabela($aid, $n, $colunas, (array) $linha) ?><?php endforeach; ?>
                </tbody></table></div>
              <div class="tabela-acoes">
                <?php if ($podeEditar): ?><button type="button" class="como-link" data-mais-linha-at="<?= $aid ?>"><?= icone('mais') ?>Mais uma linha</button><?php endif; ?>
                <div class="entrada estreita"><?= $ausencia($aid, $aus) ?></div>
              </div>
            <?php break; case 'texto_longo': ?>
              <div class="entrada"><textarea id="at-<?= $aid ?>" name="at[<?= $aid ?>]" rows="3"<?= $ro ?>><?= e((string) $txt) ?></textarea></div>
              <div class="entrada estreita"><?= $ausencia($aid, $aus) ?></div>
            <?php break; default: ?>
              <div class="entrada"><input type="text" id="at-<?= $aid ?>" name="at[<?= $aid ?>]" maxlength="500" value="<?= e((string) $txt) ?>"<?= $ro ?>><?= $ausencia($aid, $aus) ?></div>
          <?php endswitch; ?>
          <?php if ($at['ajuda']): ?><span class="ajuda"><?= e((string) $at['ajuda']) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if ($temFicha): ?></div><?php endif; ?>

  <?php if ($temAcess): ?>
  <div class="painel-aba" data-painel="acessibilidade" hidden>
  <div class="cartao">
    <h2 class="titulo-secao">Acessibilidade</h2>
    <div class="grade">
      <div class="campo c-6">
        <label for="acessibilidade_possui">Possui alguma facilidade para pessoas com deficiência ou mobilidade reduzida?</label>
        <div class="entrada"><select id="acessibilidade_possui" name="acessibilidade_possui"<?= $dis ?>><option value="">Não informado</option>
          <option value="1"<?= ($d['acessibilidade_possui'] ?? null) !== null && (int) $d['acessibilidade_possui'] === 1 ? ' selected' : '' ?>>Sim</option>
          <option value="0"<?= ($d['acessibilidade_possui'] ?? null) !== null && (int) $d['acessibilidade_possui'] === 0 ? ' selected' : '' ?>>Não</option>
        </select></div>
      </div>
    </div>
    <p class="ajuda">Os subitens da seção 6 da ficha oficial. O portal resume em cinco selos simples para o turista.</p>
    <?php $acessOutros = $_SERVER['REQUEST_METHOD'] === 'POST' ? array_filter((array) ($_POST['acess_outro'] ?? []), 'is_scalar') : ($novo ? [] : Inventario::acessibilidadeOutros($id));
    foreach ($subitensAcess as $s): $marc = (array) ($acess[$s['chave']] ?? []); $txt = (string) ($acessOutros[$s['chave']] ?? ''); ?>
      <div class="campo">
      <h3 class="sub-cartao"><?= e((string) $s['rotulo']) ?></h3>
      <?php if (!empty($s['texto'])): ?>
        <div class="entrada"><input type="text" name="acess_outro[<?= e($s['chave']) ?>]" maxlength="255" placeholder="Quais? Especifique" aria-label="<?= e((string) $s['rotulo']) ?>" value="<?= e($txt) ?>"<?= $ro ?>></div>
      <?php elseif (array_key_exists('multipla', $s) && !$s['multipla']): ?>
        <div class="entrada"><select name="acess[<?= e($s['chave']) ?>]" aria-label="<?= e((string) $s['rotulo']) ?>"<?= $dis ?>><option value="">Não informado</option>
          <?php foreach ((array) $s['opcoes'] as $op): ?><option<?= in_array($op, $marc, true) ? ' selected' : '' ?>><?= e((string) $op) ?></option><?php endforeach; ?>
        </select></div>
      <?php else: ?>
        <?= $caixas('acess[' . $s['chave'] . ']', (array) $s['opcoes'], $marc) ?>
        <?php if (!empty($s['outros'])): ?>
          <div class="entrada outro-texto" data-outro-at="acess-<?= e($s['chave']) ?>"><input type="text" name="acess_outro[<?= e($s['chave']) ?>]" maxlength="255" placeholder="Quais? Especifique" aria-label="<?= e((string) $s['rotulo']) ?>: especifique" value="<?= e($txt) ?>"<?= $ro ?>></div>
        <?php endif; ?>
      <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  </div>
  <?php endif; ?>

  <?php if ($ficha['forma'] !== 'municipio'): $tribos = Inventario::tribos();
    $marcadas = $_SERVER['REQUEST_METHOD'] === 'POST' ? array_map('intval', (array) ($_POST['facilidades'] ?? [])) : ($novo ? [] : Inventario::facilidades($id)); ?>
  <div class="painel-aba" data-painel="tribos" hidden>
  <div class="cartao">
    <h2 class="titulo-secao">Tribos de consumo</h2>
    <p class="ajuda">Marque só o que o lugar comprovadamente oferece. Com duas facilidades ou mais, ganha o selo da tribo no portal (Bike Friendly, Pet Friendly...).</p>
    <div class="tribos-colunas">
    <?php $macroAtual = null; foreach ($tribos as $tid => $t):
      $nMarcadas = count(array_intersect(array_keys($t['facilidades']), $marcadas)); ?>
      <?php if ($t['macro'] !== $macroAtual): $macroAtual = $t['macro']; ?><h3 class="sub-cartao"><?= e((string) $macroAtual) ?></h3><?php endif; ?>
      <details class="tribo"<?= $nMarcadas ? ' open' : '' ?>>
        <summary><?= icone((string) $t['icone']) ?><b><?= e((string) $t['nome']) ?></b><?= $nMarcadas >= 2 ? ' <span class="pilula ok">selo</span>' : ($nMarcadas ? ' <span class="pilula nu">' . $nMarcadas . '</span>' : '') ?></summary>
        <div class="caixas">
          <?php foreach ($t['facilidades'] as $fid => $nomeFac): ?>
            <label class="check"><input type="checkbox" name="facilidades[]" value="<?= $fid ?>"<?= in_array($fid, $marcadas, true) ? ' checked' : '' ?><?= $dis ?>><i></i><?= e((string) $nomeFac) ?></label>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
    </div>
  </div>
  </div>
  <?php endif; ?>

  <div class="painel-aba" data-painel="textos" hidden>
  <div class="colunas">
    <div class="cartao">
      <h2 class="titulo-secao">Conservação e textos</h2>
      <div class="grade">
        <?php if ($ficha['forma'] !== 'municipio'): ?>
        <div class="campo c-6">
          <label for="conservacao">Estado geral de conservação</label>
          <div class="entrada"><select id="conservacao" name="conservacao"<?= $dis ?>><option value="">Não avaliado</option>
            <?php foreach (Inventario::CONSERVACAO as $k => $r): ?><option value="<?= $k ?>"<?= ($d['conservacao'] ?? '') === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
          </select></div>
          <span class="ajuda">Parecer de quem inventaria; o trade não altera.</span>
        </div>
        <div class="campo c-6">
          <label class="check"><input type="checkbox" name="possui_experiencia" value="1"<?= !empty($d['possui_experiencia']) ? ' checked' : '' ?><?= $dis ?>><i></i>Oferece experiência turística</label>
          <span class="ajuda">Abre o cadastro da experiência, que passa pela curadoria antes do portal.</span>
        </div>
        <?php endif; ?>
        <div class="campo c-12">
          <label for="descricao_promocional">Texto para o portal</label>
          <div class="entrada"><textarea id="descricao_promocional" name="descricao_promocional" rows="3" maxlength="5000"<?= $ro ?>><?= e((string) ($d['descricao_promocional'] ?? '')) ?></textarea></div>
          <span class="ajuda">Convidativo, para o turista.</span>
        </div>
        <div class="campo c-12">
          <label for="descricao_tecnica">Descrição técnica</label>
          <div class="entrada"><textarea id="descricao_tecnica" name="descricao_tecnica" rows="4"<?= $ro ?>><?= e((string) ($d['descricao_tecnica'] ?? '')) ?></textarea></div>
          <span class="ajuda">Caracterização para o caderno oficial.</span>
        </div>
        <div class="campo c-12">
          <label for="observacoes">Observações</label>
          <div class="entrada"><textarea id="observacoes" name="observacoes" rows="3"<?= $ro ?>><?= e((string) ($d['observacoes'] ?? '')) ?></textarea></div>
          <span class="ajuda">Só no painel e no caderno, nunca no portal.</span>
        </div>
        <div class="campo c-12">
          <label for="referencias">Referências</label>
          <div class="entrada"><textarea id="referencias" name="referencias" rows="2"<?= $ro ?>><?= e((string) ($d['referencias'] ?? '')) ?></textarea></div>
          <span class="ajuda">Fontes bibliográficas e documentais.</span>
        </div>
      </div>
    </div>

    <?php if (!$novo): $hist = Inventario::historico($id); ?>
    <div class="cartao">
      <h2 class="titulo-secao">Histórico</h2>
      <ul class="lista-simples">
        <?php foreach ($hist as $h): ?>
          <li><span class="dado"><?= e(dataBr((string) $h['criado_em'], 'd/m/Y H:i')) ?></span> <?= e((string) ($h['usuario_nome'] ?? 'Sistema')) ?>
            <?= e(match ($h['acao']) { 'criou' => 'cadastrou', 'alterou' => 'alterou', 'mudou_situacao' => 'mudou de ' . (Inventario::SITUACOES[$h['de']][0] ?? $h['de']) . ' para ' . (Inventario::SITUACOES[$h['para']][0] ?? $h['para']), 'importou' => 'importou', default => $h['acao'] }) ?>
            <?= $h['nota'] ? '<span class="nulo">' . e((string) $h['nota']) . '</span>' : '' ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
  </div>
</form>
<?php if (!$novo): ?>
<form method="post" action="<?= e($acao) ?>" id="form-esteira" hidden><input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"></form>
<?php endif; ?>

<?php if (!$novo): $eid = (int) $empresa['id']; $csrf = Auth::tokenCsrf(); ?>
<div class="painel-aba" data-painel="fotos" hidden>
<div class="cartao" id="fotos">
  <h2 class="titulo-secao">Fotos (<?= count($fotos) ?>)</h2>
  <?php if ($fotos): ?>
    <div class="galeria-item">
      <?php foreach ($fotos as $f): ?>
        <figure class="<?= $f['capa'] ? 'capa' : '' ?>">
          <a href="<?= e(Inventario::urlFoto($eid, (string) $f['arquivo'])) ?>" data-visualizar="foto" data-galeria="item-<?= $id ?>" data-legenda="<?= e(trim((string) $f['legenda'] . ($f['credito'] ? ' · Foto: ' . $f['credito'] : ''), ' ·')) ?>"><img src="<?= e(Inventario::urlFoto($eid, (string) $f['arquivo'], 'm')) ?>" alt="<?= e((string) ($f['legenda'] ?: $d['nome'])) ?>" loading="lazy"></a>
          <figcaption>
            <?php if ($f['capa']): ?><span class="pilula ok">Capa</span><?php endif; ?>
            <?php if ($f['latitude']): ?><span class="pilula nu" title="A foto trouxe a coordenada da câmera"><?= icone('mapa') ?>GPS</span><?php endif; ?>
            <?php if ($podeEditar): ?>
              <form method="post" action="<?= e($acao) ?>" class="legenda-foto">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao_foto" value="legenda"><input type="hidden" name="foto_id" value="<?= (int) $f['id'] ?>">
                <div class="entrada mini"><?= icone('etiqueta') ?><input type="text" name="legenda" maxlength="200" placeholder="Legenda" aria-label="Legenda" value="<?= e((string) $f['legenda']) ?>"></div>
                <div class="entrada mini"><?= icone('camera') ?><input type="text" name="credito" maxlength="120" placeholder="Crédito (autor)" aria-label="Crédito" value="<?= e((string) $f['credito']) ?>"></div>
                <div class="acoes-linha">
                  <button type="submit" title="Salvar a legenda" aria-label="Salvar a legenda"><?= icone('check') ?></button>
                  <?php if (!$f['capa']): ?><button type="submit" name="acao_foto" value="capa" title="Usar como capa" aria-label="Usar como capa"><?= icone('imagem') ?></button><?php endif; ?>
                  <button type="submit" name="acao_foto" value="excluir" title="Excluir a foto" aria-label="Excluir a foto" data-confirmar="Excluir esta foto? Não dá para desfazer."><?= icone('lixeira') ?></button>
                </div>
              </form>
            <?php else: ?>
              <span><?= e((string) $f['legenda']) ?><?= $f['credito'] ? ' · Foto: ' . e((string) $f['credito']) : '' ?></span>
            <?php endif; ?>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($podeEditar): ?>
    <form method="post" action="<?= e($acao) ?>" enctype="multipart/form-data" class="enviar-fotos">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao_foto" value="enviar">
      <div class="campo">
        <label for="credito-fotos">Crédito das fotos enviadas</label>
        <div class="entrada"><input type="text" id="credito-fotos" name="credito" maxlength="120" placeholder="Nome de quem fotografou"></div>
        <span class="ajuda">O crédito vai junto da foto no portal e no caderno.</span>
      </div>
      <?= zonaDeUpload('fotos', 'image/jpeg,image/png,image/webp', 'Arraste as fotos aqui', 'ou clique para escolher (JPG, PNG ou WebP; a do celular traz a coordenada)', true, true) ?>
    </form>
  <?php endif; ?>
</div>
</div>
<?php endif; ?>

<?php if (!$novo && $ficha['forma'] !== 'municipio'):
  $qx = $pdo->prepare('SELECT id, titulo, situacao FROM inventario_experiencias WHERE item_id = ? AND empresa_id = ? ORDER BY id');
  $qx->execute([$id, (int) $empresa['id']]);
  $experiencias = $qx->fetchAll();
  $situacoesX = ['rascunho' => ['Rascunho', 'nu'], 'aguardando' => ['Aguardando curadoria', 'at'], 'aprovada' => ['Aprovada', 'ok'], 'ajuste' => ['Ajuste pedido', 'at'], 'reprovada' => ['Reprovada', 'ruim']]; ?>
<div class="painel-aba" data-painel="experiencias" hidden>
<div class="cartao" id="experiencias">
  <div class="cab-cartao"><h2>Experiências turísticas (<?= count($experiencias) ?>)</h2>
    <?php if ($podeEditar): ?><a href="/experiencia?item=<?= $id ?>" data-ficha="Nova experiência"><?= icone('mais') ?> Nova experiência</a><?php endif; ?></div>
  <?php if (!$experiencias): ?>
    <p class="ajuda">Vivências que o lugar oferece (colheita, oficina, degustação, visita técnica). Passam pela curadoria antes de ir para a aba Experiências do portal.</p>
  <?php else: ?>
    <ul class="lista-simples">
      <?php foreach ($experiencias as $ex): [$r, $t] = $situacoesX[$ex['situacao']] ?? [$ex['situacao'], 'nu']; ?>
        <li><a href="<?= e(endereco('experiencia', (int) $ex['id'], (string) $ex['titulo'])) ?>" data-ficha="<?= e((string) $ex['titulo']) ?>"><?= e((string) $ex['titulo']) ?></a> <span class="pilula <?= $t ?>"><?= e($r) ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
</div>
<?php endif; ?>
<?php paginaFichaFim();
