<?php
/**
 * Experiência turística de um item do inventário (os 4 Es da economia da
 * experiência), em modal, com a curadoria "anti porteira aberta".
 * Softsul Software
 *
 * Quem cadastra preenche o que o visitante vive (entretenimento, educação,
 * escapismo, estética), a narrativa, duração, grupo, agendamento e preço, e
 * manda fotos da vivência acontecendo (não da fachada). Enviada à curadoria,
 * só aparece no portal depois que o coordenador, o gestor ou o consultor
 * aprova, com parecer. Devolvida para ajuste, volta a ser editável.
 *
 *   /experiencia?item=12   nova experiência do item 12
 *   /experiencia/5         experiência existente
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Inventario.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('inventario.ver');
$pdo = Database::conexao();
$eid = (int) $empresa['id'];

const SITUACOES_EXPERIENCIA = [
    'rascunho'   => ['Rascunho', 'nu'],
    'aguardando' => ['Aguardando curadoria', 'at'],
    'aprovada'   => ['Aprovada', 'ok'],
    'ajuste'     => ['Ajuste pedido', 'at'],
    'reprovada'  => ['Reprovada', 'ruim'],
];
const DIMENSOES_4E = [
    'entretenimento' => ['Entretenimento', 'O visitante assiste e se diverte (show, apresentação, contação de história).'],
    'educacional'    => ['Educacional', 'O visitante aprende fazendo (oficina, colheita, preparo).'],
    'escapismo'      => ['Escapismo', 'O visitante mergulha e participa ativamente (trilha guiada, imersão, vivência).'],
    'estetica'       => ['Estética', 'O visitante contempla o lugar (paisagem, arquitetura, ambiente).'],
];
const FAIXAS_VALOR = ['gratuita' => 'Gratuita', 'ate_80' => 'Até R$ 80', 'de_81_a_200' => 'De R$ 81 a R$ 200',
                      'de_201_a_500' => 'De R$ 201 a R$ 500', 'acima_500' => 'Acima de R$ 500'];

$id = (int) ($_GET['id'] ?? 0);
if ($id) {
    $q = $pdo->prepare('SELECT * FROM inventario_experiencias WHERE id = ? AND empresa_id = ?');
    $q->execute([$id, $eid]);
    $x = $q->fetch() ?: negarRegistro();
    $item = Inventario::buscar((int) $x['item_id']) ?: negarRegistro();
} else {
    $item = Inventario::buscar((int) ($_GET['item'] ?? $_POST['item'] ?? 0)) ?: negarRegistro();
    $x = ['id' => 0, 'situacao' => 'rascunho', 'titulo' => '', 'dimensoes' => '{}', 'narrativa' => '', 'duracao_minutos' => '',
          'pessoas_min' => 1, 'pessoas_max' => '', 'agendamento' => 1, 'antecedencia_horas' => '', 'faixa_valor' => '', 'valor_texto' => '', 'parecer' => ''];
}
$curador = Auth::ehSoftsul() || Auth::temPapel('gestor') || Auth::temPapel('consultor') || Auth::temPapel('coordenador');
$podeEditar = Auth::pode('inventario.editar') && in_array($x['situacao'], ['rascunho', 'ajuste'], true);
$dimensoes = json_decode((string) $x['dimensoes'], true) ?: [];
$erro = '';
$aqui = $id ? endereco('experiencia', $id, (string) $x['titulo']) : '/experiencia?item=' . (int) $item['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? 'salvar');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (in_array($acao, ['aprovar', 'ajuste', 'reprovar'], true)) {
        $parecer = mb_substr(trim((string) ($_POST['parecer'] ?? '')), 0, 4000);
        if (!$curador || $x['situacao'] !== 'aguardando') {
            $erro = 'Esta experiência não está aguardando a sua curadoria.';
        } elseif ($acao !== 'aprovar' && $parecer === '') {
            $erro = 'Escreva o parecer: quem cadastrou precisa saber o que ajustar.';
        } else {
            $nova = ['aprovar' => 'aprovada', 'ajuste' => 'ajuste', 'reprovar' => 'reprovada'][$acao];
            $pdo->prepare('UPDATE inventario_experiencias SET situacao = ?, parecer = ?, curado_por = ?, curado_em = NOW() WHERE id = ? AND empresa_id = ?')
                ->execute([$nova, $parecer ?: null, (int) Auth::usuarioOuNulo()['id'], $id, $eid]);
            Inventario::registrarHistorico((int) $item['id'], 'curadoria', 'aguardando', $nova, 'Experiência ' . $x['titulo'] . ($parecer ? ': ' . $parecer : ''));
            Auditoria::registrar('alterou', 'inventario_experiencias', $id, 'curadoria da experiência ' . $x['titulo'] . ': ' . $nova, null, null, $eid);
            avisar('Experiência ' . SITUACOES_EXPERIENCIA[$nova][0] . '.');
            fichaSalva($aqui);
        }
    } elseif ($acao === 'evidencia' && $id && $podeEditar) {
        $arquivos = $_FILES['fotos'] ?? null;
        $n = 0;
        foreach ((array) ($arquivos['name'] ?? []) as $k => $nome) {
            $um = ['name' => $nome, 'tmp_name' => $arquivos['tmp_name'][$k] ?? '', 'error' => $arquivos['error'][$k] ?? UPLOAD_ERR_NO_FILE, 'size' => $arquivos['size'][$k] ?? 0];
            if ($um['error'] !== UPLOAD_ERR_NO_FILE && Inventario::guardarFoto((int) $item['id'], $um, null, $id) === null) {
                $n++;
            }
        }
        avisar($n ? plural($n, 'foto guardada', 'fotos guardadas') . '.' : 'Nenhuma foto guardada.', $n ? 'bom' : 'atencao');
        fichaSalva($aqui . '#evidencias');
    } elseif (!$podeEditar) {
        $erro = 'Esta experiência não pode ser alterada agora.';
    } else {
        $dimNovas = [];
        foreach (DIMENSOES_4E as $k => $_) {
            $t = mb_substr(trim((string) ($_POST['dim'][$k] ?? '')), 0, 1000);
            if ($t !== '') {
                $dimNovas[$k] = $t;
            }
        }
        $campos = [
            'titulo'             => mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 160),
            'dimensoes'          => json_encode($dimNovas, JSON_UNESCAPED_UNICODE),
            'narrativa'          => mb_substr(trim((string) ($_POST['narrativa'] ?? '')), 0, 8000),
            'duracao_minutos'    => max(0, min(10000, (int) ($_POST['duracao_minutos'] ?? 0))),
            'pessoas_min'        => max(1, (int) ($_POST['pessoas_min'] ?? 1)),
            'pessoas_max'        => max(0, (int) ($_POST['pessoas_max'] ?? 0)),
            'agendamento'        => empty($_POST['agendamento']) ? 0 : 1,
            'antecedencia_horas' => ($h = (int) ($_POST['antecedencia_horas'] ?? 0)) > 0 ? $h : null,
            'faixa_valor'        => array_key_exists((string) ($_POST['faixa_valor'] ?? ''), FAIXAS_VALOR) ? (string) $_POST['faixa_valor'] : '',
            'valor_texto'        => mb_substr(trim((string) ($_POST['valor_texto'] ?? '')), 0, 150) ?: null,
        ];
        $enviar = $acao === 'enviar';
        $q = $pdo->prepare('SELECT COUNT(*) FROM inventario_midias WHERE experiencia_id = ? AND empresa_id = ?');
        $q->execute([$id, $eid]);
        $temEvidencia = $id && (int) $q->fetchColumn() > 0;
        if ($campos['titulo'] === '') {
            $erro = 'Dê um título à experiência.';
        } elseif ($campos['faixa_valor'] === '') {
            $erro = 'Escolha a faixa de preço (pode ser gratuita).';
        } elseif ($campos['pessoas_max'] < $campos['pessoas_min']) {
            $erro = 'O grupo máximo não pode ser menor que o mínimo.';
        } elseif ($enviar && (count($dimNovas) < 1 || mb_strlen($campos['narrativa']) < 80 || !$campos['duracao_minutos'])) {
            $erro = 'Para enviar à curadoria: descreva pelo menos um dos 4 Es, a narrativa (80 caracteres ou mais) e a duração.';
        } elseif ($enviar && !$temEvidencia) {
            $erro = 'Para enviar à curadoria, mande antes as fotos da vivência acontecendo.';
        } else {
            $campos['situacao'] = $enviar ? 'aguardando' : $x['situacao'];
            if ($id) {
                $pdo->prepare('UPDATE inventario_experiencias SET ' . implode(' = ?, ', array_keys($campos)) . ' = ?, atualizado_em = NOW() WHERE id = ? AND empresa_id = ?')
                    ->execute([...array_values($campos), $id, $eid]);
            } else {
                $pdo->prepare('INSERT INTO inventario_experiencias (empresa_id, item_id, ' . implode(', ', array_keys($campos)) . ', criado_em, criado_por)
                               VALUES (?, ?' . str_repeat(', ?', count($campos)) . ', NOW(), ?)')
                    ->execute([$eid, (int) $item['id'], ...array_values($campos), (int) Auth::usuarioOuNulo()['id']]);
                $id = (int) $pdo->lastInsertId();
                $pdo->prepare('UPDATE inventario_itens SET possui_experiencia = 1 WHERE id = ? AND empresa_id = ?')->execute([(int) $item['id'], $eid]);
            }
            Inventario::registrarHistorico((int) $item['id'], $enviar ? 'enviou' : 'alterou', null, $enviar ? 'aguardando' : null, 'Experiência ' . $campos['titulo']);
            avisar($enviar ? 'Experiência enviada à curadoria.' : 'Experiência salva.' . ($temEvidencia ? '' : ' Mande as fotos da vivência para poder enviar à curadoria.'));
            fichaSalva(endereco('experiencia', $id, $campos['titulo']));
        }
        $x = array_merge($x, $campos);
        $dimensoes = $dimNovas;
    }
}

$evidencias = [];
if ($id) {
    $q = $pdo->prepare('SELECT * FROM inventario_midias WHERE experiencia_id = ? AND empresa_id = ? ORDER BY ordem, id');
    $q->execute([$id, $eid]);
    $evidencias = $q->fetchAll();
}
$ro = $podeEditar ? '' : ' readonly';
$dis = $podeEditar ? '' : ' disabled';
[$rotuloSit, $tomSit] = SITUACOES_EXPERIENCIA[$x['situacao']] ?? [$x['situacao'], 'nu'];
$csrf = Auth::tokenCsrf();
paginaFicha($x['titulo'] ?: 'Nova experiência', 'inventario');
?>
<div class="trilha"><a href="/inventario">Inventário</a><?= icone('seta') ?><a href="<?= e(endereco('item', (int) $item['id'], (string) $item['nome'])) ?>"><?= e((string) $item['nome']) ?></a><?= icone('seta') ?><span>Experiência</span></div>
<?php if ($erro !== ''): ?><p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p><?php endif; ?>
<?php if ($x['situacao'] === 'ajuste' && $x['parecer']): ?><p class="aviso atencao"><?= icone('alerta') ?><span><strong>Ajuste pedido pela curadoria:</strong> <?= e((string) $x['parecer']) ?></span></p><?php endif; ?>

<form method="post" action="<?= e($aqui) ?>" id="form-experiencia">
  <button type="submit" class="oculto-visual" tabindex="-1" aria-hidden="true" name="acao" value="salvar">Salvar</button>
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="item" value="<?= (int) $item['id'] ?>">
  <div class="cab-pag">
    <div><h1 data-titulo><?= e($x['titulo'] ?: 'Nova experiência') ?></h1>
      <p><?= e((string) $item['nome']) ?> · <span class="pilula <?= $tomSit ?>"><?= e($rotuloSit) ?></span></p></div>
    <?php if ($podeEditar): ?>
      <div class="acoes"><button type="submit" class="botao claro" name="acao" value="salvar"><?= icone('check') ?>Salvar</button>
        <button type="submit" class="botao" name="acao" value="enviar"><?= icone('enviar') ?>Enviar à curadoria</button></div>
    <?php endif; ?>
  </div>
  <div class="cartao">
    <div class="grade">
      <div class="campo c-12">
        <label for="titulo">Título <span class="obrig">obrigatório</span></label>
        <div class="entrada"><input type="text" id="titulo" name="titulo" maxlength="160" placeholder="Colheita noturna e degustação na vinícola" value="<?= e((string) $x['titulo']) ?>"<?= $ro ?>></div>
      </div>
      <div class="campo c-12">
        <label for="narrativa">Narrativa da vivência</label>
        <div class="entrada"><textarea id="narrativa" name="narrativa" rows="5" maxlength="8000"<?= $ro ?>><?= e((string) $x['narrativa']) ?></textarea></div>
        <span class="ajuda">O que o visitante vê, ouve, prova, toca e aprende, do começo ao fim.</span>
      </div>
    </div>
  </div>
  <div class="cartao">
    <h2 class="titulo-secao">Os 4 Es da experiência</h2>
    <p class="ajuda">Descreva como cada dimensão aparece. Pelo menos uma; as boas experiências costumam ter três ou quatro.</p>
    <div class="grade">
      <?php foreach (DIMENSOES_4E as $k => [$rotulo, $explica]): ?>
        <div class="campo c-6">
          <label for="dim-<?= $k ?>"><?= e($rotulo) ?></label>
          <div class="entrada"><textarea id="dim-<?= $k ?>" name="dim[<?= $k ?>]" rows="3" maxlength="1000"<?= $ro ?>><?= e((string) ($dimensoes[$k] ?? '')) ?></textarea></div>
          <span class="ajuda"><?= e($explica) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="cartao">
    <h2 class="titulo-secao">Operação</h2>
    <div class="grade">
      <div class="campo c-4"><label for="duracao_minutos">Duração (minutos)</label>
        <div class="entrada"><input type="number" id="duracao_minutos" name="duracao_minutos" min="0" value="<?= e((string) $x['duracao_minutos']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-4"><label for="pessoas_min">Grupo mínimo</label>
        <div class="entrada"><input type="number" id="pessoas_min" name="pessoas_min" min="1" value="<?= e((string) $x['pessoas_min']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-4"><label for="pessoas_max">Grupo máximo</label>
        <div class="entrada"><input type="number" id="pessoas_max" name="pessoas_max" min="1" value="<?= e((string) $x['pessoas_max']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-6"><label for="faixa_valor">Faixa de preço por pessoa <span class="obrig">obrigatório</span></label>
        <div class="entrada"><select id="faixa_valor" name="faixa_valor"<?= $dis ?>><option value="">Escolha</option>
          <?php foreach (FAIXAS_VALOR as $k => $r): ?><option value="<?= $k ?>"<?= $x['faixa_valor'] === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
        </select></div></div>
      <div class="campo c-6"><label for="valor_texto">Valor de referência</label>
        <div class="entrada"><input type="text" id="valor_texto" name="valor_texto" maxlength="150" placeholder="R$ 140 por pessoa, com taça e 4 rótulos" value="<?= e((string) $x['valor_texto']) ?>"<?= $ro ?>></div></div>
      <div class="campo c-6"><label class="check"><input type="checkbox" name="agendamento" value="1"<?= $x['agendamento'] ? ' checked' : '' ?><?= $dis ?>><i></i>Só com agendamento</label></div>
      <div class="campo c-6"><label for="antecedencia_horas">Antecedência mínima (horas)</label>
        <div class="entrada"><input type="number" id="antecedencia_horas" name="antecedencia_horas" min="0" value="<?= e((string) $x['antecedencia_horas']) ?>"<?= $ro ?>></div></div>
    </div>
  </div>
</form>

<?php if ($id): ?>
<div class="cartao" id="evidencias">
  <h2 class="titulo-secao">Evidências (<?= count($evidencias) ?>)</h2>
  <p class="ajuda">Fotos da vivência acontecendo, com visitantes, e não só da fachada: é o que a curadoria confere.</p>
  <?php if ($evidencias): ?><div class="galeria-item"><?php foreach ($evidencias as $f): ?>
    <figure><a href="<?= e(Inventario::urlFoto($eid, (string) $f['arquivo'])) ?>" data-visualizar="foto" data-galeria="experiencia"><img src="<?= e(Inventario::urlFoto($eid, (string) $f['arquivo'])) ?>" alt="" loading="lazy"></a></figure>
  <?php endforeach; ?></div><?php endif; ?>
  <?php if ($podeEditar): ?>
    <form method="post" action="<?= e($aqui) ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="evidencia">
      <?= zonaDeUpload('fotos', 'image/jpeg,image/png,image/webp', 'Arraste as fotos da vivência aqui', 'ou clique para escolher', true, true) ?>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($id && $curador && $x['situacao'] === 'aguardando'): ?>
<form method="post" action="<?= e($aqui) ?>" class="cartao">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <h2 class="titulo-secao">Curadoria</h2>
  <div class="campo"><label for="parecer">Parecer</label>
    <div class="entrada"><textarea id="parecer" name="parecer" rows="3" maxlength="4000"></textarea></div>
    <span class="ajuda">Obrigatório para devolver ou reprovar. Quem cadastrou lê este texto.</span></div>
  <div class="acoes">
    <button type="submit" class="botao" name="acao" value="aprovar"><?= icone('check') ?>Aprovar</button>
    <button type="submit" class="botao claro" name="acao" value="ajuste"><?= icone('lapis') ?>Pedir ajuste</button>
    <button type="submit" class="botao perigo" name="acao" value="reprovar" data-confirmar="Reprovar esta experiência?"><?= icone('fechar') ?>Reprovar</button>
  </div>
</form>
<?php elseif ($x['parecer'] && $x['situacao'] !== 'ajuste'): ?>
<div class="cartao"><h2 class="titulo-secao">Parecer da curadoria</h2><p><?= e((string) $x['parecer']) ?></p></div>
<?php endif; ?>
<?php paginaFichaFim();
