<?php
/**
 * Cadastro de um usuário, no contexto atual (destino ou plataforma).
 * Softsul Software
 *
 * A pessoa recebe um link para definir a própria senha, por e-mail ou pelo
 * WhatsApp: quem cadastra nunca escolhe a senha alheia. Se o e-mail já tem
 * conta (de outro destino, por exemplo), a pessoa só ganha o vínculo com este
 * destino, sem conta duplicada. Dentro de um destino a pessoa pode ter
 * vários papéis, e as permissões somam; a grade de exceções grava só o que
 * difere do papel.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Usuarios.php';
require_once __DIR__ . '/../app/Convite.php';
require_once __DIR__ . '/../app/Passkey.php';
require_once __DIR__ . '/../app/Foto.php';
require_once __DIR__ . '/../app/Certificado.php';

$id = (int) ($_GET['id'] ?? 0);
$eu = Auth::exigirPermissao($id ? 'usuarios.ver' : 'usuarios.criar');
$pdo = Database::conexao();
$empresa = Empresa::id();
$papeis = Usuarios::papeisAtribuiveis();

$alvo = null;
if ($id) {
    // Usuarios::noContexto devolve null para quem está fora do alcance de
    // quem pergunta (outro destino): o 404 não conta se a conta existe.
    $alvo = Usuarios::noContexto($id);
    if (!$alvo) {
        negarRegistro();
    }
    $certo = endereco('usuario', $id, $alvo['nome']);
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !emPopup() && strtok($_SERVER['REQUEST_URI'], '?') !== $certo) {
        header('Location: ' . $certo, true, 301);
        exit;
    }
}
$podeMexer = $id ? Usuarios::podeMexer($alvo) && Auth::pode('usuarios.editar') : (bool) $papeis;
$podeConta = $id ? $podeMexer && Usuarios::podeMexerNaConta($alvo) : true;
$erro = '';
$linkGerado = (string) ($_SESSION['link_convite'] ?? '');
unset($_SESSION['link_convite']);

/** Gera o link, tenta o e-mail e guarda o link para mostrar na tela seguinte. */
$mandarLink = function (int $uid, string $origem) use ($pdo, $eu): void {
    $q = $pdo->prepare('SELECT nome, email FROM usuarios WHERE id = ?');
    $q->execute([$uid]);
    $p = $q->fetch();
    $link = Convite::criar($uid, $origem, (int) $eu['id']);
    $foi = Convite::enviar($p['email'], $p['nome'], $link, $origem);
    $_SESSION['link_convite'] = $link;
    Auditoria::registrar('alterou', 'usuarios', $uid, ($origem === 'convite' ? 'enviou convite' : 'gerou link de redefinição de senha')
        . ($foi ? ' por e-mail' : ', e-mail não configurado ou falhou'));
    avisar($foi ? 'Link enviado por e-mail. Se preferir, mande também pelo WhatsApp.' : 'O e-mail não saiu. Copie o link abaixo e mande pelo WhatsApp.', $foi ? 'bom' : 'ruim');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? 'salvar');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!$podeMexer) {
        $erro = 'Você não pode alterar este usuário.';
    } elseif ($acao === 'salvar') {
        $nome  = trim((string) ($_POST['nome'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $telefoneDigitado = trim((string) ($_POST['telefone'] ?? ''));
        $telefone = $telefoneDigitado === '' ? null : telefoneParaBanco($telefoneDigitado);
        $departamento = mb_substr(trim((string) ($_POST['departamento'] ?? '')), 0, 120) ?: null;
        $funcao = mb_substr(trim((string) ($_POST['funcao'] ?? '')), 0, 80) ?: null;
        // No destino a pessoa pode ter vários papéis (curador e imprensa, por
        // exemplo); na plataforma, a equipe Softsul tem um só.
        $pedidos = $empresa ? array_map('intval', (array) ($_POST['papeis'] ?? [])) : [(int) ($_POST['papel_id'] ?? 0)];
        $escolhidos = [];
        foreach ($pedidos as $pid) {
            if ($pp = Usuarios::papelAtribuivel($pid)) {
                $escolhidos[(int) $pp['ordem'] * 1000 + (int) $pp['id']] = $pp;
            }
        }
        ksort($escolhidos);
        $escolhidos = array_values($escolhidos);
        $papelIds = array_map(fn ($pp) => (int) $pp['id'], $escolhidos);
        $nomesPapeis = implode(', ', array_column($escolhidos, 'nome'));
        $ativo = empty($_POST['ativo']) ? 0 : 1;
        $papel = $escolhidos[0] ?? null;

        if ($podeConta && mb_strlen($nome) < 3) {
            $erro = 'Digite o nome completo.';
        } elseif ($podeConta && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif ($podeConta && $telefoneDigitado !== '' && $telefone === null) {
            $erro = 'Telefone inválido. Use DDD e número.';
        } elseif (!$papel) {
            $erro = $empresa ? 'Escolha ao menos um papel.' : 'Escolha um papel.';
        } else {
            $erro = Database::transacao(function (PDO $pdo) use (&$id, $alvo, $nome, $email, $telefone, $departamento, $funcao, $papel, $papelIds, $nomesPapeis, $ativo, $empresa, $podeConta, $mandarLink): string {
                $gravarPapeis = function (int $uid) use ($pdo, $empresa, $papelIds): void {
                    $pdo->prepare('DELETE FROM usuario_empresa_papel WHERE usuario_id = ? AND empresa_id = ?')->execute([$uid, $empresa]);
                    $ins = $pdo->prepare('INSERT INTO usuario_empresa_papel (usuario_id, empresa_id, papel_id) VALUES (?, ?, ?)');
                    foreach ($papelIds as $pid) {
                        $ins->execute([$uid, $empresa, $pid]);
                    }
                };
                $existe = $pdo->prepare('SELECT id, softsul FROM usuarios WHERE email = ? AND id <> ?');
                $existe->execute([$email, $id]);
                $outro = $existe->fetch();
                $softsul = $papel['escopo'] === 'softsul' ? 1 : 0;

                if (!$id) {
                    if ($outro) {
                        // Conta que já existe: só ganha o vínculo com este destino.
                        if (!$empresa || $outro['softsul']) {
                            return 'Já existe uma conta com este e-mail.';
                        }
                        $v = $pdo->prepare('SELECT 1 FROM usuario_empresa WHERE usuario_id = ? AND empresa_id = ?');
                        $v->execute([$outro['id'], $empresa]);
                        if ($v->fetchColumn()) {
                            return 'Esta pessoa já está cadastrada neste destino.';
                        }
                        $id = (int) $outro['id'];
                        $pdo->prepare('INSERT INTO usuario_empresa (usuario_id, empresa_id, papel_id, ativo, criado_em) VALUES (?, ?, ?, 1, NOW())')
                            ->execute([$id, $empresa, $papel['id']]);
                        $gravarPapeis($id);
                        Auditoria::registrar('criou', 'usuario_empresa', $id, 'vinculou conta existente ao destino como ' . $nomesPapeis);
                        avisar('Esta pessoa já tinha conta no InvenTour e foi vinculada ao destino. Ela entra com a senha que já usa.');
                        return '';
                    }
                    $pdo->prepare(
                        'INSERT INTO usuarios (nome, email, telefone, departamento, funcao, softsul, papel_softsul_id, ativo, empresa_padrao_id, criado_em)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())'
                    )->execute([$nome, $email, $telefone, $departamento, $funcao, $softsul, $softsul ? $papel['id'] : null, $empresa ?: null]);
                    $id = (int) $pdo->lastInsertId();
                    if ($empresa) {
                        $pdo->prepare('INSERT INTO usuario_empresa (usuario_id, empresa_id, papel_id, ativo, criado_em) VALUES (?, ?, ?, 1, NOW())')
                            ->execute([$id, $empresa, $papel['id']]);
                        $gravarPapeis($id);
                    }
                    Auditoria::registrar('criou', 'usuarios', $id, 'cadastrou ' . $nome . ' como ' . $nomesPapeis, null,
                        ['nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'departamento' => $departamento, 'funcao' => $funcao, 'papeis' => $nomesPapeis]);
                    $mandarLink($id, 'convite');
                    return '';
                }

                if ($podeConta) {
                    if ($outro) {
                        return 'Já existe outra conta com este e-mail.';
                    }
                    $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, telefone = ?, departamento = ?, funcao = ?, atualizado_em = NOW() WHERE id = ?')
                        ->execute([$nome, $email, $telefone, $departamento, $funcao, $id]);
                }
                $antes = $alvo['papeis'] ?? [];
                sort($antes);
                $depois = $papelIds;
                sort($depois);
                $mudouAcesso = $antes !== $depois || (int) $alvo['vinculo_ativo'] !== $ativo
                            || ($podeConta && $email !== $alvo['email']);
                if ($empresa) {
                    $pdo->prepare('UPDATE usuario_empresa SET papel_id = ?, ativo = ?, atualizado_em = NOW() WHERE usuario_id = ? AND empresa_id = ?')
                        ->execute([$papel['id'], $ativo, $id, $empresa]);
                    $gravarPapeis($id);
                } else {
                    $pdo->prepare('UPDATE usuarios SET papel_softsul_id = ?, ativo = ?, atualizado_em = NOW() WHERE id = ?')
                        ->execute([$papel['id'], $ativo, $id]);
                }
                // Mudou papel, e-mail ou acesso: as sessões abertas caem.
                if ($mudouAcesso) {
                    Auth::invalidarSessoes($id);
                }
                Auditoria::registrar('alterou', 'usuarios', $id, 'alterou ' . $nome,
                    ['nome' => $alvo['nome'], 'email' => $alvo['email'], 'telefone' => $alvo['telefone'], 'departamento' => $alvo['departamento'], 'funcao' => $alvo['funcao'], 'papeis' => $alvo['papel_nome'], 'ativo' => (int) $alvo['vinculo_ativo']],
                    ['nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'departamento' => $departamento, 'funcao' => $funcao, 'papeis' => $nomesPapeis, 'ativo' => $ativo]);
                avisar('Usuário salvo.');
                return '';
            });
            if ($erro === '') {
                // A foto enviada no editor só é gravada com a ficha salva.
                if ($podeConta && Foto::mexeuNaFicha()) {
                    $falhaFoto = Foto::daFicha('usuario', $id);
                    if ($falhaFoto !== null && !empty($_SESSION['aviso'])) {
                        $_SESSION['aviso']['texto'] .= ' ' . $falhaFoto;
                    }
                }
                // Com WhatsApp e sem foto, o rosto vem do perfil do WhatsApp,
                // como no Orbit. Foto que já existe não é trocada: quem
                // enviou uma escolheu aquela.
                $q = $pdo->prepare('SELECT nome, telefone, foto FROM usuarios WHERE id = ?');
                $q->execute([$id]);
                $salvo = $q->fetch();
                if ($salvo && $salvo['telefone'] && !$salvo['foto'] && !Foto::mexeuNaFicha()) {
                    $recado = Foto::recado(Foto::doWhatsapp('usuario', $id, (string) $salvo['telefone']));
                    if ($recado !== '' && !empty($_SESSION['aviso'])) {
                        $_SESSION['aviso']['texto'] .= $recado;
                    }
                }
                fichaSalva(endereco('usuario', $id, (string) $salvo['nome']));
            }
        }
    } elseif ($id && $acao === 'link') {
        $mandarLink($id, $alvo['senha_hash'] ? 'redefinir' : 'convite');
        redirecionar(noPopup(endereco('usuario', $id, $alvo['nome'])));
    } elseif ($id && $acao === 'reiniciar_2fa') {
        $pdo->prepare('UPDATE usuarios SET duplo_fator_segredo = NULL, duplo_fator_confirmado = 0, duplo_fator_ultimo_periodo = NULL WHERE id = ?')
            ->execute([$id]);
        Passkey::removerTodos($id);
        Certificado::removerTodos($id);
        Auth::invalidarSessoes($id);
        Auditoria::registrar('alterou', 'usuarios', $id, 'reiniciou o segundo fator e removeu aparelhos e certificados');
        avisar('Segundo fator reiniciado. Na próxima entrada a pessoa cadastra o autenticador de novo.');
        redirecionar(noPopup(endereco('usuario', $id, $alvo['nome'])));
    } elseif ($id && $acao === 'encerrar_sessoes') {
        Auth::invalidarSessoes($id);
        Auditoria::registrar('alterou', 'usuarios', $id, 'encerrou as sessões abertas');
        avisar('Sessões encerradas.');
        redirecionar(noPopup(endereco('usuario', $id, $alvo['nome'])));
    } elseif ($id && $acao === 'permissoes') {
        // Marcado é o que a pessoa pode. Grava só o que difere do papel, como
        // exceção, para mudar o papel depois continuar valendo.
        $base = $empresa ?: 0;
        $marcadas = array_map('intval', (array) ($_POST['permissao'] ?? []));
        $todas = $pdo->query('SELECT pe.id FROM permissoes pe JOIN modulos m ON m.id = pe.modulo_id WHERE m.ativo = 1'
            . ($alvo['softsul'] ? '' : ' AND m.apenas_softsul = 0'))->fetchAll(PDO::FETCH_COLUMN);
        $idsPapeis = $alvo['papeis'] ?: [0];
        $dp = $pdo->prepare('SELECT DISTINCT permissao_id FROM papel_permissao WHERE papel_id IN (' . implode(',', array_fill(0, count($idsPapeis), '?')) . ')');
        $dp->execute($idsPapeis);
        $doPapel = array_map('intval', $dp->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare('DELETE FROM usuario_permissao WHERE usuario_id = ? AND empresa_id = ?')->execute([$id, $base]);
        $ins = $pdo->prepare('INSERT INTO usuario_permissao (usuario_id, empresa_id, permissao_id, concedida) VALUES (?, ?, ?, ?)');
        $excecoes = [];
        foreach (array_map('intval', $todas) as $permId) {
            $tem = in_array($permId, $marcadas, true);
            if ($tem !== in_array($permId, $doPapel, true)) {
                $ins->execute([$id, $base, $permId, $tem ? 1 : 0]);
                $excecoes[$permId] = $tem ? 1 : 0;
            }
        }
        Auth::invalidarSessoes($id);
        Auditoria::registrar('alterou', 'usuario_permissao', $id, 'ajustou as permissões individuais', null, $excecoes);
        avisar('Permissões salvas.');
        redirecionar(noPopup(endereco('usuario', $id, $alvo['nome'])) . '#permissoes');
    }
}

$dados = $alvo ?? ['nome' => '', 'email' => '', 'telefone' => '', 'departamento' => '', 'funcao' => '', 'papel_id' => 0, 'papeis' => [], 'vinculo_ativo' => 1, 'softsul' => 0];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erro !== '') {
    $dados = array_merge($dados, ['nome' => $_POST['nome'] ?? $dados['nome'], 'email' => $_POST['email'] ?? $dados['email'],
        'telefone' => $_POST['telefone'] ?? $dados['telefone'],
        'departamento' => $_POST['departamento'] ?? $dados['departamento'], 'funcao' => $_POST['funcao'] ?? $dados['funcao'], 'papel_id' => (int) ($_POST['papel_id'] ?? 0),
        'papeis' => $empresa ? array_map('intval', (array) ($_POST['papeis'] ?? [])) : [(int) ($_POST['papel_id'] ?? 0)],
        'vinculo_ativo' => empty($_POST['ativo']) ? 0 : 1]);
}

// Permissões: o padrão do papel e as exceções gravadas, por módulo. Só os
// módulos que valem no contexto atual: dentro de um destino some o que é da
// plataforma, e na plataforma some o que só existe num destino.
$modulosPerm = [];
if ($id && $dados['papeis']) {
    $marcasPapeis = implode(',', array_map('intval', $dados['papeis']));
    $q = $pdo->prepare(
        'SELECT m.id AS modulo_id, m.nome AS modulo, m.icone, m.grupo, pe.id, pe.verbo,
                EXISTS (SELECT 1 FROM papel_permissao pp WHERE pp.papel_id IN (' . $marcasPapeis . ') AND pp.permissao_id = pe.id) AS do_papel,
                (SELECT up.concedida FROM usuario_permissao up WHERE up.usuario_id = ? AND up.empresa_id = ? AND up.permissao_id = pe.id) AS excecao
         FROM permissoes pe JOIN modulos m ON m.id = pe.modulo_id
         WHERE m.ativo = 1 AND m.contexto <> ?' . ($alvo['softsul'] ? '' : ' AND m.apenas_softsul = 0') . '
         ORDER BY m.grupo_ordem, m.ordem, FIELD(pe.verbo, "ver", "criar", "editar", "excluir", "exportar")'
    );
    $q->execute([$id, $empresa ?: 0, $empresa ? 'plataforma' : 'destino']);
    foreach ($q->fetchAll() as $l) {
        $modulosPerm[$l['modulo_id']] ??= ['nome' => $l['modulo'], 'icone' => $l['icone'], 'grupo' => $l['grupo'], 'verbos' => []];
        $modulosPerm[$l['modulo_id']]['verbos'][] = $l;
    }
}
$csrf = Auth::tokenCsrf();
if ($id && !emPopup()) {
    canonizar('usuario', $id, $dados['nome']);
}

paginaFicha($id ? $dados['nome'] : 'Novo usuário', 'usuarios');
$ro = $podeConta ? '' : ' readonly';
?>
<div class="trilha"><a href="/configuracoes/usuarios">Usuários</a><?= icone('seta') ?><span><?= $id ? e($dados['nome']) : 'Novo' ?></span></div>
<?php if ($erro !== ''): ?><p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p><?php endif; ?>

<?php if ($linkGerado !== ''): ?>
<div class="cartao">
  <div class="cab-cartao"><h2>Link para definir a senha</h2><span class="dado">vale uma vez só</span></div>
  <p class="ajuda">Mande para a pessoa pelo WhatsApp, nunca em grupo.</p>
  <div class="copiavel">
    <div class="entrada"><input readonly value="<?= e($linkGerado) ?>" id="link-convite" aria-label="Link para definir a senha" class="dado"></div>
    <button type="button" class="botao claro" data-copiar="link-convite"><?= icone('copiar') ?>Copiar</button>
    <?php if (!empty($alvo['telefone'])): ?>
      <a class="botao claro" target="_blank" rel="noopener" href="https://wa.me/<?= e($alvo['telefone']) ?>?text=<?= rawurlencode('Olá! Este é o seu link para definir a senha do InvenTour: ' . $linkGerado) ?>"><?= icone('whatsapp') ?>WhatsApp</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<form method="post" action="<?= e($id ? endereco('usuario', $id, $dados['nome']) : '/configuracoes/usuario') ?>" id="form-usuario" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="salvar">
  <div class="cab-pag">
    <div class="titulo-cliente">
      <?php if ($id && $podeConta): ?>
        <?= Foto::editavel('usuario', $id, $alvo['foto'] ?? null, $dados['nome'], null, $podeMexer && !empty($alvo['telefone'])) ?>
      <?php else: ?>
        <?= $id ? Foto::avatar('usuario', $id, $alvo['foto'] ?? null, $dados['nome'], 'brasao', 128) : avatarIniciais('Novo usuário', 'brasao') ?>
      <?php endif; ?>
      <div>
        <h1><?= $id ? e($dados['nome']) : 'Novo usuário' ?></h1>
        <p><?= $id ? e(($dados['papel_nome'] ?? '') . ' · ' . ($alvo['ultimo_login'] ? 'último acesso ' . quandoFoi($alvo['ultimo_login']) : 'nunca entrou'))
                   : 'A pessoa recebe um link para escolher a própria senha.' ?></p>
      </div>
    </div>
    <?php if ($podeMexer): ?>
      <div class="acoes"><button type="submit" class="botao"><?= icone('check') ?><?= $id ? 'Salvar' : 'Cadastrar e enviar o convite' ?></button></div>
    <?php endif; ?>
  </div>

  <div class="colunas">
    <div class="cartao">
      <h2 class="titulo-secao">Pessoa</h2>
      <?php if ($id && !$podeConta && $podeMexer): ?>
        <p class="aviso info"><?= icone('alerta') ?><span>Esta pessoa também tem acesso a outro destino. Nome, e-mail, WhatsApp, departamento e função ela mesma altera, em Meus dados.</span></p>
      <?php endif; ?>
      <div class="grade">
        <div class="campo c-12">
          <label for="nome">Nome completo <span class="obrig">obrigatório</span></label>
          <div class="entrada"><input id="nome" name="nome" value="<?= e($dados['nome']) ?>" required maxlength="120"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="departamento">Departamento ou secretaria</label>
          <div class="entrada"><input id="departamento" name="departamento" value="<?= e((string) $dados['departamento']) ?>" maxlength="120" placeholder="Secretaria de Turismo"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="funcao">Função</label>
          <div class="entrada"><input id="funcao" name="funcao" value="<?= e((string) $dados['funcao']) ?>" maxlength="80" placeholder="Diretor de Eventos"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="email">E-mail <span class="obrig">obrigatório</span></label>
          <div class="entrada"><?= icone('emails') ?><input id="email" name="email" type="email" value="<?= e($dados['email']) ?>" required spellcheck="false"<?= $ro ?>></div>
          <?php require_once __DIR__ . '/../app/SesAvisos.php'; if ($dados['email'] && ($bloq = SesAvisos::motivo($dados['email']))): ?><span class="ajuda erro" role="alert">Este endereço não recebe mais e-mails do InvenTour: <?= e($bloq) ?>. Corrija o endereço; ao salvar outro, o aviso some.</span><?php endif; ?>
          <span class="ajuda">É o login. Se já tiver conta em outro destino, a pessoa só ganha o acesso a este.</span>
        </div>
        <div class="campo c-6">
          <label for="telefone">WhatsApp</label>
          <div class="entrada"><?= icone('whatsapp') ?><input id="telefone" name="telefone" class="telefone"
               value="<?= e($dados['telefone'] && ctype_digit((string) $dados['telefone']) ? formatarTelefone($dados['telefone']) : (string) $dados['telefone']) ?>"<?= $ro ?>></div>
          <span class="ajuda">Para mandar o convite e os avisos pelo WhatsApp, quando o e-mail não basta.</span>
        </div>
      </div>
    </div>

    <div class="cartao">
      <h2 class="titulo-secao">Acesso<?= $empresa ? ' a ' . e(Empresa::atual()['nome_fantasia']) : '' ?></h2>
      <div class="grade">
        <div class="campo c-12">
          <?php if ($empresa): ?>
            <label id="rotulo-papeis">Papéis <span class="obrig">obrigatório</span></label>
            <?php /* Combobox de múltipla escolha: as caixas são as de verdade,
                     e o que vai no formulário. Papel que quem cadastra não
                     pode dar aparece travado, para não sumir da ficha. */
            $atribuiveis = array_column($papeis, null, 'id');
            $nomesTodos = $pdo->query("SELECT id, nome, descricao, escopo FROM papeis WHERE escopo = 'destino' ORDER BY ordem")->fetchAll(); ?>
            <div class="multi-escolha" data-multi-escolha>
              <button type="button" class="entrada multi-alvo" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="rotulo-papeis"<?= $podeMexer ? '' : ' disabled' ?>>
                <span class="multi-fichas" data-vazio="Escolha um ou mais papéis"></span><?= icone('seta', null, 'chevron') ?>
              </button>
              <div class="multi-opcoes" role="listbox" aria-multiselectable="true" hidden>
                <?php foreach ($nomesTodos as $pp):
                  $marcado = in_array((int) $pp['id'], $dados['papeis'] ?? [], true);
                  $pode = $podeMexer && isset($atribuiveis[$pp['id']]); ?>
                  <label class="check multi-opcao<?= $pode ? '' : ' travada' ?>">
                    <input type="checkbox" name="papeis[]" value="<?= (int) $pp['id'] ?>" data-nome="<?= e($pp['nome']) ?>"<?= $marcado ? ' checked' : '' ?><?= $pode ? '' : ' disabled' ?>><i></i>
                    <span><b><?= e($pp['nome']) ?></b><small><?= e((string) $pp['descricao']) ?></small></span>
                  </label>
                  <?php if (!$pode && $marcado): ?><input type="hidden" name="papeis[]" value="<?= (int) $pp['id'] ?>"><?php endif; ?>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <label for="papel_id">Papel <span class="obrig">obrigatório</span></label>
            <div class="entrada"><select id="papel_id" name="papel_id" required<?= $podeMexer ? '' : ' disabled' ?>>
              <option value="">Escolha</option>
              <?php foreach ($papeis as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $dados['papel_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option><?php endforeach; ?>
            </select></div>
          <?php endif; ?>
          <span class="ajuda"><?= $empresa ? 'As permissões somam as dos papéis escolhidos. ' : '' ?>Ninguém dá um papel acima do próprio.</span>
        </div>
      </div>
      <?php if ($id): ?>
        <label class="check"><input type="checkbox" name="ativo" value="1" <?= $dados['vinculo_ativo'] ? 'checked' : '' ?><?= $podeMexer ? '' : ' disabled' ?>><i></i>Acesso ativo<?= $empresa ? ' neste destino' : '' ?></label>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if ($id): ?>
<div class="cartao">
  <div class="cab-cartao"><h2>Entrada e segurança</h2></div>
  <div class="metricas">
    <div class="metrica"><span class="rot">Senha</span><span class="val"><?= $alvo['senha_hash'] ? 'definida' : 'pendente' ?></span></div>
    <div class="metrica"><span class="rot">Segundo fator</span><span class="val"><?= $alvo['duplo_fator_confirmado'] ? 'ligado' : 'desligado' ?></span></div>
    <div class="metrica"><span class="rot">Aparelhos com digital</span><span class="val"><?= count(Passkey::doUsuario($id)) ?></span></div>
    <div class="metrica"><span class="rot">Certificados</span><span class="val"><?= count(Certificado::doUsuario($id)) ?></span></div>
    <div class="metrica"><span class="rot">WhatsApp</span><span class="val"><?= $alvo['whatsapp_existe'] === null ? 'não conferido' : ($alvo['whatsapp_existe'] ? 'confirmado' : 'sem WhatsApp') ?></span>
      <?php if ($alvo['whatsapp_conferido_em']): ?><span class="nota">conferido em <?= e(dataBr($alvo['whatsapp_conferido_em'], 'd/m/Y')) ?></span><?php endif; ?></div>
  </div>
  <?php if ($podeMexer): ?>
  <div class="acoes">
    <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="link">
      <button type="submit" class="botao claro"><?= icone('chave') ?><?= $alvo['senha_hash'] ? 'Gerar link para trocar a senha' : 'Gerar convite novo' ?></button></form>
    <?php if ($alvo['duplo_fator_confirmado'] || Passkey::doUsuario($id) || Certificado::doUsuario($id)): ?>
    <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="reiniciar_2fa">
      <button type="submit" class="botao claro" data-confirmar="A pessoa perdeu o celular ou o certificado? O autenticador, os aparelhos com digital e os certificados deixam de valer, e ela cadastra de novo na próxima entrada."><?= icone('escudo') ?>Reiniciar o segundo fator</button></form>
    <?php endif; ?>
    <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="encerrar_sessoes">
      <button type="submit" class="botao claro" data-confirmar="Todas as sessões abertas desta pessoa serão encerradas."><?= icone('sair') ?>Encerrar as sessões abertas</button></form>
  </div>
  <?php endif; ?>
</div>

<?php if ($modulosPerm): ?>
<form method="post" class="cartao" id="permissoes">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="permissoes">
  <div class="cab-cartao"><h2>Permissões</h2><span class="dado">o que difere do papel fica marcado</span></div>
  <p class="ajuda">Marcado é o que a pessoa pode fazer hoje. O padrão vem do papel; marcar ou desmarcar aqui cria uma
  exceção só para ela, que continua valendo se o papel mudar depois.</p>
  <div class="permissoes">
    <?php $grupo = false; foreach ($modulosPerm as $m):
      if ($m['grupo'] !== $grupo): $grupo = $m['grupo']; ?><h3 class="grupo-perm"><?= e((string) $grupo) ?></h3><?php endif; ?>
      <div class="linha-perm">
        <span class="nome-modulo"><?= icone((string) $m['icone']) ?><?= e($m['nome']) ?></span>
        <div class="verbos">
          <?php foreach ($m['verbos'] as $v):
            $tem = $v['excecao'] === null ? (bool) $v['do_papel'] : (bool) $v['excecao'];
            $excecao = $tem !== (bool) $v['do_papel']; ?>
            <label class="check verbo<?= $excecao ? ' excecao' : '' ?>" title="<?= $excecao ? ($tem ? 'concedido fora do papel' : 'retirado do papel') : 'padrão do papel' ?>">
              <input type="checkbox" name="permissao[]" value="<?= (int) $v['id'] ?>" <?= $tem ? 'checked' : '' ?><?= $podeMexer ? '' : ' disabled' ?>><i></i><?= e($v['verbo']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($podeMexer): ?><div class="acoes"><button type="submit" class="botao"><?= icone('check') ?>Salvar permissões</button></div><?php endif; ?>
</form>
<?php endif; ?>
<?php endif; ?>
<?php paginaFichaFim();
