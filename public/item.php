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
 * A mudança de situação pela esteira (envio à conferência, homologação) é da
 * etapa 3; aqui quem coordena pode ajustar a situação à mão.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Inventario.php';
require_once __DIR__ . '/../app/Cnpj.php';

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
    Auth::exigirPermissao('inventario.criar');
    $ficha = Inventario::ficha((int) ($_GET['ficha'] ?? $_POST['ficha_id'] ?? 0));
}
$podeEditar = $novo ? Auth::pode('inventario.criar') : Auth::pode('inventario.editar');
// Quem coordena ajusta a situação à mão; o pesquisador só envia à conferência.
$coordena = Auth::ehSoftsul() || Auth::temPapel('gestor') || Auth::temPapel('consultor') || Auth::temPapel('coordenador');
$situacoesPermitidas = $coordena ? array_keys(Inventario::SITUACOES) : ['pre_cadastro', 'em_campo', 'em_conferencia'];

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

$texto = fn (string $c, int $max = 255): ?string => ($v = mb_substr(trim((string) ($_POST[$c] ?? '')), 0, $max)) !== '' ? $v : null;
$numero = function (string $c): ?float {
    $v = str_replace(',', '.', trim((string) ($_POST[$c] ?? '')));
    return is_numeric($v) ? (float) $v : null;
};
$doCatalogo = fn (string $c, array $validos): ?string => array_key_exists((string) ($_POST[$c] ?? ''), $validos) ? (string) $_POST[$c] : null;

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
    fichaSalva(endereco('item', $id, (string) $item['nome']) . '#fotos');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acaoFoto === '') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!$podeEditar) {
        $erro = 'Você não pode alterar o inventário.';
    } else {
        $situacao = in_array($_POST['situacao'] ?? '', $situacoesPermitidas, true) ? (string) $_POST['situacao'] : (string) $d['situacao'];
        $tipoId = (int) ($_POST['tipo_id'] ?? 0);
        $horarioNovo = ['24h' => !empty($_POST['h24']), 'feriados' => $texto('h_feriados', 200), 'temporada' => $texto('h_temporada', 200)];
        foreach (Inventario::DIAS as $dia => $_) {
            $abre = (string) ($_POST['h_abre'][$dia] ?? '');
            $fecha = (string) ($_POST['h_fecha'][$dia] ?? '');
            if (preg_match('/^\d{2}:\d{2}$/', $abre) && preg_match('/^\d{2}:\d{2}$/', $fecha)) {
                $horarioNovo[$dia] = [[$abre, $fecha]];
            }
        }
        $nucleo = [
            'tipo_id'               => in_array($tipoId, array_map('intval', array_column($tipos, 'id')), true) ? $tipoId : null,
            'situacao'              => $situacao,
            'nome'                  => (string) $texto('nome', 180),
            'nome_popular'          => $texto('nome_popular', 180),
            'razao_social'          => $texto('razao_social', 180),
            'cnpj'                  => Cnpj::normalizar((string) ($_POST['cnpj'] ?? '')) ?: null,
            'inscricao_municipal'   => $texto('inscricao_municipal', 30),
            'cadastur'              => $texto('cadastur', 30),
            'cadastur_validade'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['cadastur_validade'] ?? '')) ? (string) $_POST['cadastur_validade'] : null,
            'natureza'              => $doCatalogo('natureza', Inventario::NATUREZAS),
            'jurisdicao'            => $doCatalogo('jurisdicao', Inventario::JURISDICOES),
            'rede'                  => $texto('rede', 120),
            'inicio_atividade'      => ($a = (int) ($_POST['inicio_atividade'] ?? 0)) >= 1500 && $a <= (int) date('Y') ? $a : null,
            'funcionarios'          => ($n = (int) ($_POST['funcionarios'] ?? 0)) > 0 ? min($n, 65000) : null,
            'zona'                  => $doCatalogo('zona', Inventario::ZONAS),
            'cep'                   => strlen($cep = soDigitos($_POST['cep'] ?? '')) === 8 ? $cep : null,
            'logradouro'            => $texto('logradouro', 200),
            'numero'                => $texto('numero', 20),
            'complemento'           => $texto('complemento', 80),
            'bairro'                => $texto('bairro', 120),
            'latitude'              => ($la = $numero('latitude')) !== null && abs($la) <= 90 ? $la : null,
            'longitude'             => ($lo = $numero('longitude')) !== null && abs($lo) <= 180 ? $lo : null,
            'maps_url'              => $texto('maps_url', 500),
            'referencia'            => $texto('referencia', 255),
            'sinalizacao_acesso'    => ($_POST['sinalizacao_acesso'] ?? '') === '' ? null : (int) ($_POST['sinalizacao_acesso'] === '1'),
            'sinalizacao_turistica' => ($_POST['sinalizacao_turistica'] ?? '') === '' ? null : (int) ($_POST['sinalizacao_turistica'] === '1'),
            'extensao_km'           => $ficha['forma'] === 'linear' ? $numero('extensao_km') : null,
            'telefone'              => telefoneParaBanco($_POST['telefone'] ?? null),
            'whatsapp'              => telefoneParaBanco($_POST['whatsapp'] ?? null),
            'email'                 => ($em = mb_strtolower((string) $texto('email', 150))) !== '' ? $em : null,
            'site'                  => $texto('site', 255),
            'instagram'             => ($ig = ltrim((string) $texto('instagram', 100), '@')) !== '' ? $ig : null,
            'horario'               => json_encode($horarioNovo, JSON_UNESCAPED_UNICODE),
            'formas_pagamento'      => json_encode(array_values(array_intersect(Inventario::PAGAMENTOS, (array) ($_POST['pagamentos'] ?? []))), JSON_UNESCAPED_UNICODE),
            'idiomas'               => json_encode(array_values(array_intersect(Inventario::IDIOMAS, (array) ($_POST['idiomas'] ?? []))), JSON_UNESCAPED_UNICODE),
            'capacidade_publico'    => ($cp = (int) ($_POST['capacidade_publico'] ?? 0)) > 0 ? $cp : null,
            'conservacao'           => $doCatalogo('conservacao', Inventario::CONSERVACAO),
            'descricao_tecnica'     => $texto('descricao_tecnica', 20000),
            'descricao_promocional' => $texto('descricao_promocional', 5000),
            'observacoes'           => $texto('observacoes', 10000),
            'referencias'           => $texto('referencias', 10000),
            'possui_experiencia'    => empty($_POST['possui_experiencia']) ? 0 : 1,
        ];
        if ($novo) {
            $nucleo['origem'] = 'painel';
            $nucleo['pesquisador_id'] = (int) Auth::usuarioOuNulo()['id'];
        }
        // Campos específicos da ficha, com a ausência quando não há resposta.
        $especificos = [];
        foreach ($atributos as $grupo => $lista) {
            foreach ($lista as $at) {
                $aid = (int) $at['id'];
                $bruto = $_POST['at'][$aid] ?? null;
                $aus = array_key_exists((string) ($_POST['aus'][$aid] ?? ''), Inventario::AUSENCIAS) ? (string) $_POST['aus'][$aid] : null;
                $v = ['texto' => null, 'numero' => null, 'ausencia' => $aus];
                switch ($at['tipo']) {
                    case 'numero':
                        $n = str_replace(',', '.', trim((string) $bruto));
                        $v['numero'] = is_numeric($n) ? (float) $n : null;
                        break;
                    case 'sim_nao':
                        $v['numero'] = $bruto === '1' ? 1.0 : ($bruto === '0' ? 0.0 : null);
                        break;
                    case 'multipla':
                        $marcadas = array_values(array_intersect($at['opcoes'], (array) $bruto));
                        $v['texto'] = $marcadas ? json_encode($marcadas, JSON_UNESCAPED_UNICODE) : null;
                        break;
                    case 'escolha':
                        $v['texto'] = in_array((string) $bruto, $at['opcoes'], true) ? (string) $bruto : null;
                        break;
                    case 'tabela':
                        $linhas = [];
                        foreach ((array) $bruto as $linha) {
                            $linha = array_map(fn ($c) => mb_substr(trim((string) $c), 0, 200), array_slice(array_values((array) $linha), 0, count($at['opcoes'])));
                            if (implode('', $linha) !== '') {
                                $linhas[] = $linha;
                            }
                        }
                        $v['texto'] = $linhas ? json_encode($linhas, JSON_UNESCAPED_UNICODE) : null;
                        break;
                    default:
                        $v['texto'] = ($t = mb_substr(trim((string) $bruto), 0, $at['tipo'] === 'texto_longo' ? 10000 : 500)) !== '' ? $t : null;
                }
                if ($v['texto'] !== null || $v['numero'] !== null) {
                    $v['ausencia'] = null;
                }
                $especificos[$aid] = $v;
            }
        }
        $acessNovo = [];
        foreach ($subitensAcess as $s) {
            $acessNovo[$s['chave']] = array_values(array_intersect((array) $s['opcoes'], (array) ($_POST['acess'][$s['chave']] ?? [])));
        }

        if ($nucleo['nome'] === '') {
            $erro = 'Dê o nome do item.';
        } elseif ($tipos && $nucleo['tipo_id'] === null) {
            $erro = 'Escolha o tipo na ficha ' . $ficha['codigo'] . '.';
        } elseif ($nucleo['cnpj'] !== null && !Cnpj::valido($nucleo['cnpj'])) {
            $erro = 'CNPJ inválido. Confira os dígitos.';
        } elseif ($nucleo['email'] !== null && !filter_var($nucleo['email'], FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif (trim((string) ($_POST['telefone'] ?? '')) !== '' && $nucleo['telefone'] === null) {
            $erro = 'Telefone incompleto. Use DDD e número.';
        } elseif (trim((string) ($_POST['whatsapp'] ?? '')) !== '' && $nucleo['whatsapp'] === null) {
            $erro = 'WhatsApp incompleto. Use DDD e número.';
        } elseif ($nucleo['cnpj'] !== null) {
            // O mesmo CNPJ pode ter mais de uma ficha (hotel e o restaurante
            // aberto ao público), mas não na mesma ficha.
            $dup = $pdo->prepare("SELECT nome FROM inventario_itens WHERE empresa_id = ? AND cnpj = ? AND ficha_id = ? AND id <> ? AND situacao <> 'arquivada'");
            $dup->execute([Empresa::id(), $nucleo['cnpj'], $fichaId, $id]);
            if ($outro = $dup->fetchColumn()) {
                $erro = 'O CNPJ já está nesta ficha: ' . $outro . '.';
            }
        }
        if ($erro === '') {
            $id = Inventario::salvar($novo ? null : $id, $fichaId, $nucleo, $especificos, $acessNovo);
            if ($ficha['forma'] !== 'municipio') {
                Inventario::salvarFacilidades($id, (array) ($_POST['facilidades'] ?? []));
            }
            avisar(($novo ? $nucleo['nome'] . ' entrou no inventário.' : $nucleo['nome'] . ' salvo.'));
            fichaSalva(endereco('item', $id, $nucleo['nome']));
        }
        // O que foi digitado volta para a tela.
        $d = array_merge($d, $nucleo, ['cnpj' => $_POST['cnpj'] ?? '', 'telefone' => $_POST['telefone'] ?? '', 'whatsapp' => $_POST['whatsapp'] ?? '']);
        $horario = $horarioNovo;
        $pagamentos = json_decode((string) $nucleo['formas_pagamento'], true);
        $idiomas = json_decode((string) $nucleo['idiomas'], true);
        foreach ($especificos as $aid => $v) {
            $valores[$aid] = ['valor_texto' => $v['texto'], 'valor_numero' => $v['numero'], 'ausencia' => $v['ausencia']];
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
    <?php if ($podeEditar): ?>
      <div class="acoes"><button type="submit" class="botao"><?= icone('check') ?>Salvar</button></div>
    <?php endif; ?>
  </div>
  <?php if ($ficha['ajuda']): ?>
    <p class="aviso info"><?= icone('manual') ?><span><?= e((string) $ficha['ajuda']) ?></span></p>
  <?php endif; ?>

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
        <?php if ($ficha['forma'] !== 'municipio'): ?>
        <div class="campo c-6">
          <label for="cnpj">CNPJ</label>
          <div class="entrada"><?= icone('predio') ?><input type="text" id="cnpj" name="cnpj" class="cnpj" maxlength="18" autocomplete="off"
                 value="<?= e(!empty($d['cnpj']) && strlen((string) $d['cnpj']) === 14 ? formatarCnpj((string) $d['cnpj']) : (string) ($d['cnpj'] ?? '')) ?>"<?= $ro ?>></div>
          <span class="ajuda">Inventarie também quem não tem CNPJ: o manual manda incluir todo estabelecimento aberto ao público.</span>
        </div>
        <div class="campo c-6">
          <label for="razao_social">Razão social</label>
          <div class="entrada"><input type="text" id="razao_social" name="razao_social" maxlength="180" value="<?= e((string) ($d['razao_social'] ?? '')) ?>"<?= $ro ?>></div>
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
        </div>
        <div class="campo c-4">
          <label for="jurisdicao">Jurisdição</label>
          <div class="entrada"><select id="jurisdicao" name="jurisdicao"<?= $dis ?>><option value="">Não informada</option>
            <?php foreach (Inventario::JURISDICOES as $k => $r): ?><option value="<?= $k ?>"<?= ($d['jurisdicao'] ?? '') === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
          </select></div>
        </div>
        <div class="campo c-4">
          <label for="inicio_atividade">Início da atividade (ano)</label>
          <div class="entrada"><input type="number" id="inicio_atividade" name="inicio_atividade" min="1500" max="<?= date('Y') ?>" value="<?= e((string) ($d['inicio_atividade'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-8">
          <label for="rede">Rede, holding ou franquia</label>
          <div class="entrada"><input type="text" id="rede" name="rede" maxlength="120" value="<?= e((string) ($d['rede'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="funcionarios">Funcionários</label>
          <div class="entrada"><input type="number" id="funcionarios" name="funcionarios" min="0" value="<?= e((string) ($d['funcionarios'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($ficha['forma'] !== 'municipio'): ?>
    <div class="cartao">
      <h2 class="titulo-secao">Localização</h2>
      <div class="grade">
        <div class="campo c-4">
          <label for="zona">Zona</label>
          <div class="entrada"><select id="zona" name="zona"<?= $dis ?>><option value="">Não informada</option>
            <?php foreach (Inventario::ZONAS as $k => $r): ?><option value="<?= $k ?>"<?= ($d['zona'] ?? '') === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
          </select></div>
        </div>
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
          <label for="bairro">Bairro ou distrito</label>
          <div class="entrada"><input type="text" id="bairro" name="bairro" maxlength="120" value="<?= e((string) ($d['bairro'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="complemento">Complemento</label>
          <div class="entrada"><input type="text" id="complemento" name="complemento" maxlength="80" value="<?= e((string) ($d['complemento'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="latitude">Latitude</label>
          <div class="entrada"><input type="text" id="latitude" name="latitude" inputmode="decimal" placeholder="-26.229512" value="<?= e((string) ($d['latitude'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="longitude">Longitude</label>
          <div class="entrada"><input type="text" id="longitude" name="longitude" inputmode="decimal" placeholder="-52.671604" value="<?= e((string) ($d['longitude'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-12">
          <label for="maps_url">Link do mapa</label>
          <div class="entrada"><?= icone('mapa') ?><input type="url" id="maps_url" name="maps_url" maxlength="500" value="<?= e((string) ($d['maps_url'] ?? '')) ?>"<?= $ro ?>></div>
          <span class="ajuda">O "Como chegar" do portal. Em campo, a coordenada vem do GPS do celular.</span>
        </div>
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
      </div>
    </div>

    <div class="cartao">
      <h2 class="titulo-secao">Contato público</h2>
      <div class="grade">
        <div class="campo c-6">
          <label for="telefone">Telefone</label>
          <div class="entrada"><input type="text" id="telefone" name="telefone" class="telefone" value="<?= e(!empty($d['telefone']) && ctype_digit((string) $d['telefone']) ? formatarTelefone((string) $d['telefone']) : (string) ($d['telefone'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
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

    <div class="cartao">
      <h2 class="titulo-secao">Funcionamento</h2>
      <div class="rolagem">
        <table class="dados horario">
          <thead><tr><th>Dia</th><th>Abre</th><th>Fecha</th></tr></thead>
          <tbody>
          <?php foreach (Inventario::DIAS as $dia => $rotuloDia): $faixa = $horario[$dia][0] ?? ['', '']; ?>
            <tr><td><?= e($rotuloDia) ?></td>
              <td><input type="time" name="h_abre[<?= $dia ?>]" value="<?= e((string) $faixa[0]) ?>" aria-label="<?= e($rotuloDia) ?>, abre"<?= $ro ?>></td>
              <td><input type="time" name="h_fecha[<?= $dia ?>]" value="<?= e((string) $faixa[1]) ?>" aria-label="<?= e($rotuloDia) ?>, fecha"<?= $ro ?>></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <label class="check"><input type="checkbox" name="h24" value="1"<?= !empty($horario['24h']) ? ' checked' : '' ?><?= $dis ?>><i></i>Funciona 24 horas</label>
      <div class="grade">
        <div class="campo c-6">
          <label for="h_feriados">Feriados</label>
          <div class="entrada"><input type="text" id="h_feriados" name="h_feriados" maxlength="200" value="<?= e((string) ($horario['feriados'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="h_temporada">Temporada ou agendamento</label>
          <div class="entrada"><input type="text" id="h_temporada" name="h_temporada" maxlength="200" value="<?= e((string) ($horario['temporada'] ?? '')) ?>"<?= $ro ?>></div>
        </div>
      </div>
      <h3 class="sub-cartao">Formas de pagamento</h3>
      <?= $caixas('pagamentos', Inventario::PAGAMENTOS, $pagamentos) ?>
      <h3 class="sub-cartao">Atendimento em</h3>
      <?= $caixas('idiomas', Inventario::IDIOMAS, $idiomas) ?>
    </div>
    <?php endif; ?>
  </div>

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

  <?php foreach ($atributos as $grupo => $lista): ?>
  <div class="cartao">
    <h2 class="titulo-secao"><?= e((string) $grupo) ?></h2>
    <div class="grade">
      <?php foreach ($lista as $at): $aid = (int) $at['id']; $v = $valores[$aid] ?? null;
        $txt = $v['valor_texto'] ?? null; $num = $v['valor_numero'] ?? null; $aus = $v['ausencia'] ?? null;
        $largo = in_array($at['tipo'], ['texto_longo', 'multipla', 'tabela'], true); ?>
        <div class="campo <?= $largo ? 'c-12' : 'c-6' ?>">
          <label for="at-<?= $aid ?>"><?= e((string) $at['rotulo']) ?><?= $at['obrigatorio'] ? ' <span class="obrig">núcleo do MTur</span>' : '' ?></label>
          <?php switch ($at['tipo']):
            case 'numero': ?>
              <div class="entrada"><input type="text" id="at-<?= $aid ?>" name="at[<?= $aid ?>]" inputmode="decimal" value="<?= $num !== null ? e(rtrim(rtrim(number_format((float) $num, 2, ',', ''), '0'), ',')) : '' ?>"<?= $ro ?>><?= $ausencia($aid, $aus) ?></div>
            <?php break; case 'sim_nao': ?>
              <div class="entrada"><select id="at-<?= $aid ?>" name="at[<?= $aid ?>]"<?= $dis ?>><option value="">Não respondido</option>
                <option value="1"<?= $num !== null && (float) $num === 1.0 ? ' selected' : '' ?>>Sim</option>
                <option value="0"<?= $num !== null && (float) $num === 0.0 ? ' selected' : '' ?>>Não</option></select><?= $ausencia($aid, $aus) ?></div>
            <?php break; case 'escolha': ?>
              <div class="entrada"><select id="at-<?= $aid ?>" name="at[<?= $aid ?>]"<?= $dis ?>><option value="">Não respondido</option>
                <?php foreach ($at['opcoes'] as $op): ?><option<?= $txt === $op ? ' selected' : '' ?>><?= e((string) $op) ?></option><?php endforeach; ?>
              </select><?= $ausencia($aid, $aus) ?></div>
            <?php break; case 'multipla': ?>
              <?= $caixas('at[' . $aid . ']', $at['opcoes'], json_decode((string) $txt, true) ?: []) ?>
              <div class="entrada estreita"><?= $ausencia($aid, $aus) ?></div>
            <?php break; case 'tabela': $linhas = json_decode((string) $txt, true) ?: []; $linhas = array_merge($linhas, array_fill(0, 2, [])); ?>
              <div class="rolagem"><table class="dados tabela-campo">
                <thead><tr><?php foreach ($at['opcoes'] as $col): ?><th><?= e((string) $col) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($linhas as $n => $linha): ?>
                  <tr><?php foreach (array_values($at['opcoes']) as $k => $col): ?>
                    <td><input type="text" name="at[<?= $aid ?>][<?= $n ?>][<?= $k ?>]" maxlength="200" aria-label="<?= e((string) $col) ?>, linha <?= $n + 1 ?>" value="<?= e((string) ($linha[$k] ?? '')) ?>"<?= $ro ?>></td>
                  <?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody></table></div>
              <div class="entrada estreita"><?= $ausencia($aid, $aus) ?></div>
              <span class="ajuda">Duas linhas em branco a cada salvamento; salve para ganhar mais.</span>
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

  <?php if ($ficha['forma'] !== 'municipio' && $subitensAcess): ?>
  <div class="cartao">
    <h2 class="titulo-secao">Acessibilidade</h2>
    <p class="ajuda">Os subitens da seção 6 da ficha oficial. O portal resume em cinco selos simples para o turista.</p>
    <?php foreach ($subitensAcess as $s): ?>
      <h3 class="sub-cartao"><?= e((string) $s['rotulo']) ?></h3>
      <?= $caixas('acess[' . $s['chave'] . ']', (array) $s['opcoes'], (array) ($acess[$s['chave']] ?? [])) ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($ficha['forma'] !== 'municipio'): $tribos = Inventario::tribos();
    $marcadas = $_SERVER['REQUEST_METHOD'] === 'POST' ? array_map('intval', (array) ($_POST['facilidades'] ?? [])) : ($novo ? [] : Inventario::facilidades($id)); ?>
  <div class="cartao">
    <h2 class="titulo-secao">Tribos de consumo</h2>
    <p class="ajuda">Marque só o que o lugar comprovadamente oferece. Com duas facilidades ou mais, ganha o selo da tribo no portal (Bike Friendly, Pet Friendly...).</p>
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
  <?php endif; ?>

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
</form>

<?php if (!$novo): $fotos = Inventario::fotos($id); $eid = (int) $empresa['id']; $csrf = Auth::tokenCsrf(); ?>
<div class="cartao" id="fotos">
  <h2 class="titulo-secao">Fotos (<?= count($fotos) ?>)</h2>
  <?php if ($fotos): ?>
    <div class="galeria-item">
      <?php foreach ($fotos as $f): ?>
        <figure class="<?= $f['capa'] ? 'capa' : '' ?>">
          <a href="<?= e(Inventario::urlFoto($eid, (string) $f['arquivo'])) ?>" target="_blank" rel="noopener"><img src="<?= e(Inventario::urlFoto($eid, (string) $f['arquivo'])) ?>" alt="<?= e((string) ($f['legenda'] ?: $d['nome'])) ?>" loading="lazy"></a>
          <figcaption>
            <?php if ($f['capa']): ?><span class="pilula ok">Capa</span><?php endif; ?>
            <?php if ($f['latitude']): ?><span class="pilula nu" title="A foto trouxe a coordenada da câmera"><?= icone('mapa') ?>GPS</span><?php endif; ?>
            <?php if ($podeEditar): ?>
              <form method="post" action="<?= e($acao) ?>" class="legenda-foto">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao_foto" value="legenda"><input type="hidden" name="foto_id" value="<?= (int) $f['id'] ?>">
                <input type="text" name="legenda" maxlength="200" placeholder="Legenda" aria-label="Legenda" value="<?= e((string) $f['legenda']) ?>">
                <input type="text" name="credito" maxlength="120" placeholder="Crédito (autor)" aria-label="Crédito" value="<?= e((string) $f['credito']) ?>">
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
<?php endif; ?>

<?php if (!$novo && $ficha['forma'] !== 'municipio'):
  $qx = $pdo->prepare('SELECT id, titulo, situacao FROM inventario_experiencias WHERE item_id = ? AND empresa_id = ? ORDER BY id');
  $qx->execute([$id, (int) $empresa['id']]);
  $experiencias = $qx->fetchAll();
  $situacoesX = ['rascunho' => ['Rascunho', 'nu'], 'aguardando' => ['Aguardando curadoria', 'at'], 'aprovada' => ['Aprovada', 'ok'], 'ajuste' => ['Ajuste pedido', 'at'], 'reprovada' => ['Reprovada', 'ruim']]; ?>
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
<?php endif; ?>
<?php paginaFichaFim();
