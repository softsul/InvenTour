<?php
/**
 * Um lugar do responsável, em modal: o que ele pode atualizar, em abas.
 * Softsul Software
 *
 * Contato e texto para o turista, funcionamento (a mesma semana do
 * painel), capacidade (só nas fichas que a têm), tribos e fotos novas. O
 * formulário mostra a ficha com a proposta aberta por cima; ao mandar, só
 * o que mudou vira a revisão (Trade::propor) e espera a equipe. CNPJ, nome,
 * endereço, tipo e conservação ficam com a equipe: o responsável pede pelo
 * campo de recado, que segue com a revisão.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Trade.php';
require_once __DIR__ . '/../app/FichaFormulario.php';
require_once __DIR__ . '/../app/ColetaTela.php';

$eu = Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('meus-lugares.ver');
$uid = (int) $eu['id'];
$id = (int) ($_GET['id'] ?? 0);
if (!Trade::responde($uid, $id)) {
    negarRegistro();
}
$item = Inventario::buscar($id) ?: negarRegistro();
if ($item['situacao'] === 'arquivada') {
    negarRegistro();
}
$ficha = Inventario::ficha((int) $item['ficha_id']) ?: negarRegistro();
$campos = Trade::camposDaFicha($item);
$podeEditar = Auth::pode('meus-lugares.editar');
$aqui = '/meu-lugar?id=' . $id;
$eid = (int) $empresa['id'];

$aberta = Trade::revisaoAberta($id);
if ($aberta && (int) $aberta['usuario_id'] !== $uid) {
    $aberta = null;
}
$ultima = Trade::ultimaRevisao($id, $uid);
$erro = '';

// Excluir uma foto que ainda espera a equipe (formulário próprio).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tirar_foto'])) {
    if (Auth::conferirCsrf($_POST['csrf'] ?? null) && $podeEditar && $aberta) {
        $novos = array_map(fn ($p) => $p['depois'], $aberta['alteracoes']);
        Trade::propor($item, $uid, $novos, [], [(string) $_POST['tirar_foto']]);
        avisar('Foto tirada da atualização.');
    }
    redirecionar(noPopup($aqui) . '#fotos');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['tirar_foto'])) {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!$podeEditar) {
        $erro = 'Você não pode atualizar este lugar.';
    } else {
        $lido = FichaFormulario::ler($_POST, $ficha, true, $item['horario'] ?? null);
        $novos = array_intersect_key($lido['nucleo'], $campos);
        foreach (['capacidade_uh', 'capacidade_leitos', 'capacidade_assentos'] as $c) {
            if (isset($campos[$c]) && array_key_exists($c, $_POST)) {
                $n = (int) preg_replace('/\D/', '', (string) $_POST[$c]);
                $novos[$c] = $n > 0 ? $n : null;
            }
        }
        if (isset($campos['facilidades'])) {
            $novos['facilidades'] = array_values(array_filter(array_map('intval', (array) ($_POST['facilidades'] ?? []))));
        }
        $erros = array_intersect_key($lido['erros'], $campos);
        $fotos = [];
        $falhas = [];
        $arquivos = $_FILES['fotos'] ?? null;
        foreach ((array) ($arquivos['name'] ?? []) as $k => $nomeArquivo) {
            $um = ['name' => $nomeArquivo, 'tmp_name' => $arquivos['tmp_name'][$k] ?? '', 'error' => $arquivos['error'][$k] ?? UPLOAD_ERR_NO_FILE, 'size' => $arquivos['size'][$k] ?? 0];
            if ($um['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $r = Trade::guardarFotoProposta($um);
            is_string($r) ? $falhas[] = $nomeArquivo . ': ' . $r : $fotos[] = $r;
        }
        if ($erros) {
            $erro = (string) reset($erros);
        } else {
            $n = Trade::propor($item, $uid, $novos, $fotos);
            avisar($n ? 'Atualização enviada. A equipe de turismo confere e o portal muda depois da aprovação.' : 'Nada mudou em relação à ficha do portal.', $n ? 'bom' : 'atencao');
            foreach ($falhas as $f) {
                avisar('Uma foto não entrou: ' . $f, 'atencao');
            }
            fichaSalva('/meus-lugares');
        }
    }
}

// O que o formulário mostra: a ficha, com a proposta aberta por cima.
$d = $item;
$facilidades = Inventario::facilidades($id);
foreach (($aberta['alteracoes'] ?? []) as $c => $par) {
    $c === 'facilidades' ? $facilidades = array_map('intval', (array) $par['depois']) : $d[$c] = $par['depois'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erro !== '') {
    $d = array_merge($d, $lido['nucleo'] ?? []);
}
$horario = json_decode((string) ($d['horario'] ?? ''), true) ?: [];
$pagamentos = json_decode((string) ($d['formas_pagamento'] ?? ''), true) ?: [];
$idiomas = json_decode((string) ($d['idiomas'] ?? ''), true) ?: [];
$fotosItem = Inventario::fotos($id);
$fotosEspera = $aberta['fotos'] ?? [];
$ro = $podeEditar ? '' : ' readonly';
$dis = $podeEditar ? '' : ' disabled';
$tel = fn ($v) => !empty($v) && ctype_digit((string) $v) ? formatarTelefone((string) $v) : (string) ($v ?? '');
$caixas = function (string $nome, array $opcoes, array $marcadas) use ($dis): string {
    $h = '<input type="hidden" name="' . e($nome) . '[]" value=""><div class="caixas">';
    foreach ($opcoes as $op) {
        $h .= '<label class="check"><input type="checkbox" name="' . e($nome) . '[]" value="' . e((string) $op) . '"' . (in_array($op, $marcadas, true) ? ' checked' : '') . $dis . '><i></i>' . e((string) $op) . '</label>';
    }
    return $h . '</div>';
};
$temCapacidade = (bool) array_filter(array_keys($campos), fn ($c) => str_starts_with($c, 'capacidade_'));
$abas = ['contato' => ['Contato e texto', 'contatos'], 'funcionamento' => ['Funcionamento', 'relogio']];
if ($temCapacidade) { $abas['capacidade'] = ['Capacidade', 'usuarios']; }
$abas['tribos'] = ['Tribos', 'pessoas'];
$abas['fotos'] = ['Fotos', 'camera'];
$abas['experiencias'] = ['Experiências', 'natureza'];
$q = Database::conexao()->prepare('SELECT id, titulo, situacao FROM inventario_experiencias WHERE item_id = ? AND empresa_id = ? ORDER BY id DESC');
$q->execute([$id, $eid]);
$experiencias = $q->fetchAll();
$situacoesX = ['rascunho' => ['Rascunho', 'nu'], 'aguardando' => ['Aguardando curadoria', 'at'], 'aprovada' => ['No portal', 'ok'], 'ajuste' => ['Ajuste pedido', 'at'], 'reprovada' => ['Reprovada', 'ruim']];

paginaFicha($item['nome'], 'meus-lugares');
?>
<div class="trilha"><a href="/meus-lugares">Meus lugares</a><?= icone('seta') ?><span><?= e((string) $item['nome']) ?></span></div>
<?php if ($erro !== ''): ?><p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p><?php endif; ?>

<form method="post" action="<?= e($aqui) ?>" enctype="multipart/form-data" id="form-lugar">
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <div class="cab-pag">
    <div>
      <h1 data-titulo><?= e((string) $item['nome']) ?></h1>
      <p><?= e((string) ($item['tipo_nome'] ?: $ficha['nome'])) ?></p>
    </div>
    <?php if ($podeEditar): ?><div class="acoes"><button type="submit" class="botao"><?= icone('enviar') ?>Enviar para conferência</button></div><?php endif; ?>
  </div>

  <?php if ($aberta && $aberta['situacao'] === 'devolvida'): ?>
    <p class="aviso atencao"><?= icone('alerta') ?><span>A equipe devolveu a sua atualização: <?= e((string) $aberta['nota']) ?> Ajuste e mande de novo.</span></p>
  <?php elseif ($aberta): ?>
    <p class="aviso info"><?= icone('relogio') ?><span>Você mandou uma atualização em <?= e(dataBr((string) ($aberta['atualizado_em'] ?: $aberta['criado_em']), 'd/m/Y')) ?>. Ela espera a conferência da equipe; o formulário já mostra o que você mandou e você ainda pode mudar.</span></p>
  <?php elseif ($ultima && $ultima['situacao'] === 'rejeitada'): ?>
    <p class="aviso ruim"><?= icone('alerta') ?><span>A última atualização não foi aceita: <?= e((string) $ultima['nota']) ?></span></p>
  <?php endif; ?>

  <nav class="abas-chamado abas-item" role="tablist" aria-label="Partes do lugar">
    <?php $primeira = true; foreach ($abas as $k => [$rot, $ic]): ?>
      <button type="button" role="tab" data-aba="<?= $k ?>" aria-selected="<?= $primeira ? 'true' : 'false' ?>"><?= icone($ic) ?><?= e($rot) ?><?= $k === 'fotos' && $fotosEspera ? '<span class="conta-aba">' . count($fotosEspera) . '</span>' : '' ?></button>
    <?php $primeira = false; endforeach; ?>
  </nav>

  <div class="painel-aba" data-painel="contato">
    <div class="cartao">
      <h2 class="titulo-secao">Contato que o turista vê</h2>
      <div class="grade">
        <div class="campo c-4"><label for="telefone">Telefone</label>
          <div class="entrada"><input type="text" id="telefone" name="telefone" class="telefone" value="<?= e($tel($d['telefone'] ?? null)) ?>"<?= $ro ?>></div></div>
        <div class="campo c-3"><label for="ramal">Ramal</label>
          <div class="entrada"><input type="text" id="ramal" name="ramal" maxlength="10" inputmode="numeric" value="<?= e((string) ($d['ramal'] ?? '')) ?>"<?= $ro ?>></div></div>
        <div class="campo c-5"><label for="whatsapp">WhatsApp</label>
          <div class="entrada"><?= icone('whatsapp') ?><input type="text" id="whatsapp" name="whatsapp" class="telefone" value="<?= e($tel($d['whatsapp'] ?? null)) ?>"<?= $ro ?>></div></div>
        <div class="campo c-12"><label for="email">E-mail</label>
          <div class="entrada"><?= icone('emails') ?><input type="email" id="email" name="email" maxlength="150" value="<?= e((string) ($d['email'] ?? '')) ?>"<?= $ro ?>></div></div>
        <div class="campo c-6"><label for="site">Site</label>
          <div class="entrada"><input type="url" id="site" name="site" maxlength="255" value="<?= e((string) ($d['site'] ?? '')) ?>"<?= $ro ?>></div></div>
        <div class="campo c-6"><label for="instagram">Instagram</label>
          <div class="entrada"><input type="text" id="instagram" name="instagram" maxlength="100" placeholder="@perfil" value="<?= e(!empty($d['instagram']) ? '@' . $d['instagram'] : '') ?>"<?= $ro ?>></div></div>
      </div>
    </div>
    <div class="cartao">
      <h2 class="titulo-secao">Texto para o turista</h2>
      <div class="campo"><label for="descricao_promocional">Como você apresenta o lugar</label>
        <div class="entrada"><textarea id="descricao_promocional" name="descricao_promocional" rows="6" maxlength="5000"<?= $ro ?>><?= e((string) ($d['descricao_promocional'] ?? '')) ?></textarea></div>
        <span class="ajuda">Aparece na ficha do portal. Conte o que o visitante encontra; preço e promoção mudam rápido e ficam de fora.</span></div>
    </div>
  </div>

  <div class="painel-aba" data-painel="funcionamento" hidden>
    <div class="cartao">
      <h2 class="titulo-secao">Funcionamento</h2>
      <?= semanaDeHorario($horario, 'data-semana', !$podeEditar) ?>
      <div class="grade">
        <div class="campo c-3"><label for="h_feriados_abre">Funciona em feriados</label>
          <div class="entrada"><select id="h_feriados_abre" name="h_feriados_abre"<?= $dis ?>><option value="">Não informado</option>
            <option value="1"<?= ($horario['feriados_abre'] ?? null) === true ? ' selected' : '' ?>>Sim</option>
            <option value="0"<?= ($horario['feriados_abre'] ?? null) === false ? ' selected' : '' ?>>Não</option></select></div></div>
        <div class="campo c-3"><label for="h_feriados">Feriados (detalhe)</label>
          <div class="entrada"><input type="text" id="h_feriados" name="h_feriados" maxlength="200" placeholder="Só nacionais, fecha no Natal..." value="<?= e((string) ($horario['feriados'] ?? '')) ?>"<?= $ro ?>></div></div>
        <div class="campo c-6"><label for="h_temporada">Temporada ou agendamento</label>
          <div class="entrada"><input type="text" id="h_temporada" name="h_temporada" maxlength="200" value="<?= e((string) ($horario['temporada'] ?? '')) ?>"<?= $ro ?>></div></div>
      </div>
      <h3 class="sub-cartao">Formas de pagamento</h3>
      <?= $caixas('pagamentos', Inventario::PAGAMENTOS, $pagamentos) ?>
      <div class="campo">
        <h3 class="sub-cartao">Atendimento em</h3>
        <?= $caixas('idiomas', Inventario::IDIOMAS, $idiomas) ?>
        <div class="entrada outro-texto" data-outro-at="idiomas"><input type="text" name="idiomas_outro" maxlength="120" placeholder="Qual idioma?" aria-label="Outro idioma: qual" value="<?= e((string) ($d['idiomas_outro'] ?? '')) ?>"<?= $ro ?>></div>
      </div>
    </div>
  </div>

  <?php if ($temCapacidade): ?>
  <div class="painel-aba" data-painel="capacidade" hidden>
    <div class="cartao">
      <h2 class="titulo-secao">Capacidade</h2>
      <div class="grade">
        <?php foreach (['capacidade_uh' => 'Unidades habitacionais (quartos, chalés)', 'capacidade_leitos' => 'Leitos', 'capacidade_assentos' => 'Lugares sentados ao mesmo tempo', 'capacidade_publico' => 'Público simultâneo (pessoas)'] as $c => $rot):
          if (!isset($campos[$c])) { continue; } ?>
          <div class="campo c-6"><label for="<?= $c ?>"><?= e($rot) ?></label>
            <div class="entrada"><input type="text" inputmode="numeric" id="<?= $c ?>" name="<?= $c ?>" maxlength="7" value="<?= e((string) ($d[$c] ?? '')) ?>"<?= $ro ?>></div></div>
        <?php endforeach; ?>
      </div>
      <p class="ajuda">Leitos e capacidade vão também para o EventCity, que compara com o público esperado dos eventos da cidade.</p>
    </div>
  </div>
  <?php endif; ?>

  <div class="painel-aba" data-painel="tribos" hidden>
    <div class="cartao">
      <h2 class="titulo-secao">Tribos de consumo</h2>
      <p class="ajuda">Marque só o que o lugar oferece de fato. Com duas facilidades ou mais, ganha o selo da tribo no portal (Bike Friendly, Pet Friendly...).</p>
      <input type="hidden" name="facilidades[]" value="">
      <div class="tribos-colunas">
      <?php $macroAtual = null; foreach (Inventario::tribos() as $t):
        $nMarcadas = count(array_intersect(array_keys($t['facilidades']), $facilidades)); ?>
        <?php if ($t['macro'] !== $macroAtual): $macroAtual = $t['macro']; ?><h3 class="sub-cartao"><?= e((string) $macroAtual) ?></h3><?php endif; ?>
        <details class="tribo"<?= $nMarcadas ? ' open' : '' ?>>
          <summary><?= icone((string) $t['icone']) ?><b><?= e((string) $t['nome']) ?></b><?= $nMarcadas >= 2 ? ' <span class="pilula ok">selo</span>' : ($nMarcadas ? ' <span class="pilula nu">' . $nMarcadas . '</span>' : '') ?></summary>
          <div class="caixas">
            <?php foreach ($t['facilidades'] as $fid => $nomeFac): ?>
              <label class="check"><input type="checkbox" name="facilidades[]" value="<?= $fid ?>"<?= in_array($fid, $facilidades, true) ? ' checked' : '' ?><?= $dis ?>><i></i><?= e((string) $nomeFac) ?></label>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="painel-aba" data-painel="fotos" hidden>
    <div class="cartao" id="fotos">
      <h2 class="titulo-secao">Fotos novas</h2>
      <?php if ($podeEditar): ?>
        <?= zonaDeUpload('fotos', 'image/jpeg,image/png,image/webp', 'Arraste as fotos do lugar aqui', 'ou clique para escolher (JPG, PNG ou WebP)', false, true) ?>
        <p class="ajuda">Fotos boas, na horizontal, sem texto por cima. Elas entram na ficha depois da conferência, com o seu nome no crédito.</p>
      <?php endif; ?>
      <?php if ($fotosEspera): ?>
        <h3 class="sub-cartao">Esperando a conferência</h3>
        <div class="galeria-item">
          <?php foreach ($fotosEspera as $f): $u = '/trade-arquivo?revisao=' . (int) $aberta['id'] . '&f=' . rawurlencode((string) $f['arquivo']); ?>
            <figure><a href="<?= e($u) ?>" data-visualizar="foto" data-galeria="espera"><img src="<?= e($u . '&mini=1') ?>" alt="Foto nova" loading="lazy"></a>
              <?php if ($podeEditar): ?><figcaption><button type="submit" class="como-link" form="form-tirar" name="tirar_foto" value="<?= e((string) $f['arquivo']) ?>"><?= icone('lixeira') ?>Tirar</button></figcaption><?php endif; ?></figure>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($fotosItem): ?>
    <div class="cartao">
      <h2 class="titulo-secao">Fotos que já estão no portal</h2>
      <div class="galeria-item">
        <?php foreach ($fotosItem as $f): ?>
          <figure class="<?= $f['capa'] ? 'capa' : '' ?>"><a href="<?= e(Inventario::urlFoto($eid, (string) $f['arquivo'])) ?>" data-visualizar="foto" data-galeria="portal"><img src="<?= e(Inventario::urlFoto($eid, (string) $f['arquivo'], 'm')) ?>" alt="" loading="lazy"></a>
            <?php if ($f['capa']): ?><figcaption><span class="pilula ok">Capa</span></figcaption><?php endif; ?></figure>
        <?php endforeach; ?>
      </div>
      <p class="ajuda">Para tirar ou trocar uma foto do portal, fale com a equipe de turismo do destino.</p>
    </div>
    <?php endif; ?>
  </div>

  <div class="painel-aba" data-painel="experiencias" hidden>
    <div class="cartao">
      <div class="cab-cartao"><h2>Experiências (<?= count($experiencias) ?>)</h2>
        <?php if ($podeEditar): ?><a href="<?= e(noPopup('/experiencia?item=' . $id)) ?>"><?= icone('mais') ?> Nova experiência</a><?php endif; ?></div>
      <?php if (!$experiencias): ?>
        <p class="ajuda">Vivências que o lugar oferece (colheita, oficina, degustação, visita técnica). Passam pela curadoria da equipe antes de ir para o portal.</p>
      <?php else: ?>
        <ul class="lista-simples">
          <?php foreach ($experiencias as $ex): [$rx, $tx] = $situacoesX[$ex['situacao']] ?? [$ex['situacao'], 'nu']; ?>
            <li><a href="<?= e(noPopup(endereco('experiencia', (int) $ex['id'], (string) $ex['titulo']))) ?>"><?= e((string) $ex['titulo']) ?></a> <span class="pilula <?= $tx ?>"><?= e($rx) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($podeEditar): ?>
    <div class="cartao-rodape"><button type="submit" class="botao"><?= icone('enviar') ?>Enviar para conferência</button></div>
  <?php endif; ?>
</form>
<form method="post" action="<?= e($aqui) ?>" id="form-tirar"><input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"></form>
<?php paginaFichaFim();
