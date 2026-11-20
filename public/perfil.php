<?php
/**
 * Meus dados: nome, telefone, foto, senha, segundo fator, aparelhos da
 * biometria, certificado digital e preferências (tema e linhas por página).
 * Softsul Software
 *
 * Tudo aqui é da própria pessoa: a senha atual é pedida para trocar a senha
 * e para desligar o segundo fator, porque a sessão aberta num celular
 * desbloqueado não prova que é ela quem está mexendo.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Passkey.php';
require_once __DIR__ . '/../app/Certificado.php';
require_once __DIR__ . '/../app/Foto.php';
require_once __DIR__ . '/../app/Usuarios.php';

$u = Auth::exigirLogin();
$uid = (int) $u['id'];
$pdo = Database::conexao();
$eu = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
$eu->execute([$uid]);
$eu = $eu->fetch();
$obrigatorio = Auth::segundoFatorObrigatorio($uid);
$erro = '';
$secao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    $secao = $acao;
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif ($acao === 'dados') {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $telefone = trim((string) ($_POST['telefone'] ?? ''));
        $fone = $telefone === '' ? null : telefoneParaBanco($telefone);
        if (mb_strlen($nome) < 3) {
            $erro = 'Digite o nome completo.';
        } elseif ($telefone !== '' && $fone === null) {
            $erro = 'Telefone inválido. Use DDD e número, por exemplo (46) 99999-9999.';
        } else {
            $departamento = mb_substr(trim((string) ($_POST['departamento'] ?? '')), 0, 120) ?: null;
            $funcao = mb_substr(trim((string) ($_POST['funcao'] ?? '')), 0, 80) ?: null;
            $pdo->prepare('UPDATE usuarios SET nome = ?, apelido = ?, telefone = ?, departamento = ?, funcao = ?, atualizado_em = NOW() WHERE id = ?')
                ->execute([$nome, trim((string) ($_POST['apelido'] ?? '')) ?: null, $fone, $departamento, $funcao, $uid]);
            Auditoria::registrar('alterou', 'usuarios', $uid, 'atualizou os próprios dados',
                ['nome' => $eu['nome'], 'telefone' => $eu['telefone'], 'departamento' => $eu['departamento'], 'funcao' => $eu['funcao']],
                ['nome' => $nome, 'telefone' => $fone, 'departamento' => $departamento, 'funcao' => $funcao]);
            $recado = '';
            if (Foto::mexeuNaFicha()) {
                $falhaFoto = Foto::daFicha('usuario', $uid);
                $recado = $falhaFoto ? ' ' . $falhaFoto : ' Foto ' . (empty($_POST['foto_remover']) ? 'trocada.' : 'removida.');
            } elseif ($fone && empty($eu['foto'])) {
                // Sem foto, o rosto vem do perfil do WhatsApp (como no Orbit).
                $recado = Foto::recado(Foto::doWhatsapp('usuario', $uid, $fone));
            }
            avisar('Dados salvos.' . $recado);
            redirecionar(noPopup('/perfil'));
        }
    } elseif ($acao === 'senha') {
        $nova = (string) ($_POST['senha_nova'] ?? '');
        if (!password_verify((string) ($_POST['senha_atual'] ?? ''), (string) $eu['senha_hash'])) {
            $erro = 'A senha atual não confere.';
        } elseif (($erro = Auth::validarSenhaNova($nova, (string) ($_POST['senha_confirma'] ?? ''), $eu['email'], $eu['nome']) ?? '') === '') {
            Auth::definirSenha($uid, $nova, true);
            Auditoria::registrar('alterou', 'usuarios', $uid, 'trocou a própria senha');
            avisar('Senha trocada. As outras sessões abertas foram encerradas.');
            redirecionar(noPopup('/perfil'));
        }
    } elseif ($acao === 'ligar_2fa') {
        $segredo = (string) ($_SESSION['novo_segredo'] ?? '');
        if ($segredo !== '' && Auth::ligarSegundoFator($uid, $segredo, (string) ($_POST['codigo'] ?? ''))) {
            unset($_SESSION['novo_segredo']);
            Auth::invalidarSessoes($uid, true);
            Auditoria::registrar('alterou', 'usuarios', $uid, 'cadastrou o aplicativo autenticador');
            avisar('Autenticador cadastrado. As outras sessões abertas foram encerradas.');
            redirecionar(noPopup('/perfil'));
        }
        $erro = 'Código incorreto. Confira se o relógio do celular está certo e digite o código que aparece agora.';
    } elseif ($acao === 'desligar_2fa') {
        if ($obrigatorio) {
            $erro = 'Para a sua conta o segundo fator é obrigatório. Para trocar de celular, cadastre o autenticador de novo.';
        } elseif (!password_verify((string) ($_POST['senha_atual'] ?? ''), (string) $eu['senha_hash'])) {
            $erro = 'A senha atual não confere.';
        } else {
            $pdo->prepare('UPDATE usuarios SET duplo_fator_segredo = NULL, duplo_fator_confirmado = 0, duplo_fator_ultimo_periodo = NULL WHERE id = ?')
                ->execute([$uid]);
            Auth::invalidarSessoes($uid, true);
            Auditoria::registrar('alterou', 'usuarios', $uid, 'desligou o segundo fator');
            avisar('Segundo fator desligado.');
            redirecionar(noPopup('/perfil'));
        }
    } elseif ($acao === 'remover_certificado') {
        Certificado::remover($uid, (int) ($_POST['certificado'] ?? 0));
        avisar('Certificado desvinculado.');
        redirecionar(noPopup('/perfil') . '#certificados');
    } elseif ($acao === 'renomear_aparelho') {
        Passkey::renomear($uid, (int) ($_POST['aparelho'] ?? 0), (string) ($_POST['apelido'] ?? ''));
        avisar('Nome do aparelho salvo.');
        redirecionar(noPopup('/perfil') . '#aparelhos');
    } elseif ($acao === 'remover_aparelho') {
        Passkey::remover($uid, (int) ($_POST['aparelho'] ?? 0));
        avisar('Aparelho removido.');
        redirecionar(noPopup('/perfil') . '#aparelhos');
    } elseif ($acao === 'preferencias') {
        // Tema e tamanho de página ficam na conta, como no Orbit: valem em
        // qualquer computador ou celular em que a pessoa entrar.
        Auth::guardarTema(($_POST['tema'] ?? 'escuro') !== 'claro');
        Auth::guardarPorPagina((int) ($_POST['por_pagina'] ?? 0));
        Auditoria::registrar('alterou', 'usuarios', $uid, 'ajustou as preferências de tela');
        avisar('Preferências salvas.');
        redirecionar(noPopup('/perfil') . '#preferencias');
    } elseif ($acao === 'excluir_conta') {
        // A palavra digitada evita o toque sem querer; o modal do
        // data-confirmar pergunta mais uma vez antes de enviar.
        if (mb_strtoupper(trim((string) ($_POST['confirmacao'] ?? ''))) !== 'EXCLUIR') {
            $erro = 'Para excluir a conta, digite EXCLUIR no campo de confirmação.';
        } elseif (($falha = Usuarios::excluirPropriaConta($uid)) !== null) {
            $erro = $falha;
        } else {
            Auth::sair();
            redirecionar('/login?conta_excluida=1');
        }
    }
}

$trocandoAutenticador = isset($_GET['autenticador']) || $secao === 'ligar_2fa';
if ($trocandoAutenticador && empty($_SESSION['novo_segredo'])) {
    $_SESSION['novo_segredo'] = TOTP::gerarSegredo();
}
$aparelhos = Passkey::doUsuario($uid);
$certificados = Certificado::doUsuario($uid);
$csrf = Auth::tokenCsrf();

paginaFicha('Meus dados', 'perfil');
?>
<div class="cab-pag">
  <div class="titulo-cliente">
    <?= Foto::editavel('usuario', $uid, $eu['foto'], $eu['nome'], 'dados', !empty($eu['telefone'])) ?>
    <div><h1>Meus dados</h1><p><?= e($eu['email']) ?></p></div>
  </div>
</div>
<?php if ($erro !== ''): ?>
  <p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p>
<?php endif; ?>

<div class="colunas">
  <form method="post" action="/perfil" class="cartao" id="dados" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="dados">
    <h2 class="titulo-secao">Dados pessoais</h2>
    <div class="grade">
      <div class="campo c-12"><label for="nome">Nome completo <span class="obrig">obrigatório</span></label>
        <div class="entrada"><input id="nome" name="nome" value="<?= e($eu['nome']) ?>" required autocomplete="name"></div></div>
      <div class="campo c-6"><label for="apelido">Como prefere ser chamado</label>
        <div class="entrada"><input id="apelido" name="apelido" value="<?= e($eu['apelido']) ?>" maxlength="40"></div></div>
      <div class="campo c-6"><label for="telefone">WhatsApp</label>
        <div class="entrada"><?= icone('whatsapp') ?><input id="telefone" name="telefone" class="telefone" autocomplete="tel"
             value="<?= e($eu['telefone'] ? formatarTelefone($eu['telefone']) : '') ?>"></div>
        <span class="ajuda">É por ele que chegam os avisos e o link de acesso, quando o e-mail não basta.</span></div>
      <div class="campo c-6"><label for="departamento">Departamento ou secretaria</label>
        <div class="entrada"><input id="departamento" name="departamento" value="<?= e((string) $eu['departamento']) ?>" maxlength="120" placeholder="Secretaria de Turismo" autocomplete="organization"></div></div>
      <div class="campo c-6"><label for="funcao">Função</label>
        <div class="entrada"><input id="funcao" name="funcao" value="<?= e((string) $eu['funcao']) ?>" maxlength="80" placeholder="Diretor de Eventos" autocomplete="organization-title"></div></div>
    </div>
    <div class="acoes"><button class="botao" type="submit"><?= icone('check') ?>Salvar</button></div>
  </form>

  <form method="post" action="/perfil" class="cartao" id="senha">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="senha">
    <input type="text" name="username" value="<?= e($eu['email']) ?>" autocomplete="username" hidden>
    <h2 class="titulo-secao">Senha</h2>
    <div class="grade">
      <div class="campo c-12"><label for="senha_atual">Senha atual</label>
        <div class="entrada"><?= icone('cadeado') ?><input type="password" id="senha_atual" name="senha_atual" autocomplete="current-password" required>
          <button type="button" class="revelar" data-alvo="senha_atual" aria-label="Mostrar a senha"><?= icone('olho') ?></button></div></div>
      <div class="campo c-6"><label for="senha_nova">Senha nova</label>
        <div class="entrada"><?= icone('cadeado') ?><input type="password" id="senha_nova" name="senha_nova" autocomplete="new-password" minlength="12" required>
          <button type="button" class="revelar" data-alvo="senha_nova" aria-label="Mostrar a senha"><?= icone('olho') ?></button></div>
        <span class="ajuda">Pelo menos 12 caracteres. Uma frase que só você saiba é mais forte e mais fácil de lembrar.</span></div>
      <div class="campo c-6"><label for="senha_confirma">Repita a senha nova</label>
        <div class="entrada"><?= icone('cadeado') ?><input type="password" id="senha_confirma" name="senha_confirma" autocomplete="new-password" required>
          <button type="button" class="revelar" data-alvo="senha_confirma" aria-label="Mostrar a senha"><?= icone('olho') ?></button></div></div>
    </div>
    <div class="acoes"><button class="botao" type="submit"><?= icone('check') ?>Trocar a senha</button></div>
  </form>
</div>

<form method="post" action="/perfil#preferencias" class="cartao" id="preferencias">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="preferencias">
  <h2 class="titulo-secao">Preferências de tela</h2>
  <div class="grade">
    <div class="campo c-6">
      <label for="tema">Tema</label>
      <div class="entrada"><select id="tema" name="tema">
        <option value="escuro"<?= ($eu['tema'] ?? 'escuro') !== 'claro' ? ' selected' : '' ?>>Escuro</option>
        <option value="claro"<?= ($eu['tema'] ?? 'escuro') === 'claro' ? ' selected' : '' ?>>Claro</option>
      </select></div>
      <span class="ajuda">O mesmo interruptor está no menu do seu nome, no canto do topo.</span>
    </div>
    <div class="campo c-6">
      <label for="por_pagina">Linhas por página nas listas</label>
      <div class="entrada"><select id="por_pagina" name="por_pagina">
        <?php foreach (Auth::POR_PAGINA_OPCOES as $n): ?>
          <option value="<?= $n ?>"<?= Auth::porPagina() === $n ? ' selected' : '' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select></div>
      <span class="ajuda">Em tela grande cabem mais linhas; no celular, menos.</span>
    </div>
  </div>
  <div class="acoes"><button class="botao" type="submit"><?= icone('check') ?>Salvar preferências</button></div>
</form>

<div class="cartao" id="autenticador">
  <div class="cab-cartao"><h2>Segundo fator</h2>
    <span class="pilula <?= $eu['duplo_fator_confirmado'] ? 'ok' : 'nu' ?>"><?= $eu['duplo_fator_confirmado'] ? 'ligado' : 'desligado' ?></span></div>
  <?php if ($trocandoAutenticador): ?>
    <form method="post" action="/perfil#autenticador">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="ligar_2fa">
      <?= blocoCadastro2fa((string) $_SESSION['novo_segredo'], (string) $eu['email']) ?>
      <div class="grade"><div class="campo c-6"><label for="codigo">Código de 6 dígitos</label>
        <div class="entrada"><?= icone('escudo') ?><input id="codigo" name="codigo" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required class="dado"></div></div></div>
      <div class="acoes"><a class="botao claro" href="<?= e(noPopup('/perfil')) ?>">Cancelar</a><button class="botao" type="submit"><?= icone('check') ?>Confirmar</button></div>
    </form>
  <?php elseif ($eu['duplo_fator_confirmado']): ?>
    <p>O código do autenticador é pedido a cada entrada com senha.</p>
    <div class="acoes"><a class="botao claro" href="<?= e(noPopup('/perfil?autenticador=novo')) ?>#autenticador"><?= icone('escudo') ?>Trocar de celular</a></div>
    <?php if (!$obrigatorio): ?>
      <form method="post" action="/perfil#autenticador" class="formulario-linha">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="desligar_2fa">
        <input type="text" name="username" value="<?= e($eu['email']) ?>" autocomplete="username" hidden>
        <div class="grade"><div class="campo c-6"><label for="senha_desligar">Senha atual, para desligar</label>
          <div class="entrada"><?= icone('cadeado') ?><input type="password" id="senha_desligar" name="senha_atual" autocomplete="current-password" required>
          <button type="button" class="revelar" data-alvo="senha_desligar" aria-label="Mostrar a senha"><?= icone('olho') ?></button></div></div></div>
        <div class="acoes"><button class="botao perigo" type="submit" data-confirmar="Desligar o segundo fator deixa a sua conta protegida só pela senha.">Desligar</button></div>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <p>Com ele ligado, quem descobrir a sua senha ainda não consegue entrar.</p>
    <div class="acoes"><a class="botao" href="<?= e(noPopup('/perfil?autenticador=novo')) ?>#autenticador"><?= icone('escudo') ?>Ligar o segundo fator</a></div>
  <?php endif; ?>
</div>

<div class="cartao" id="aparelhos">
  <div class="cab-cartao"><h2>Entrar com a digital</h2><span class="conta-titulo"><?= count($aparelhos) ?></span></div>
  <p class="ajuda">Entre pela digital, pelo rosto ou pelo PIN do aparelho, sem senha e sem código. Chave <b>sincronizada na
  sua conta</b> (Google ou Apple) vale em todos os aparelhos ligados a ela: cadastrada no computador, entra também pelo
  celular. O último acesso mostra de qual aparelho veio.</p>
  <?php if ($aparelhos): ?>
    <div class="rolagem sem-borda"><table class="dados">
      <thead><tr><th>Aparelho</th><th>Tipo</th><th>Último acesso</th><th>Cadastrado</th><th class="acao"></th></tr></thead>
      <tbody>
      <?php foreach ($aparelhos as $a): ?>
        <tr>
          <td><form method="post" action="/perfil#aparelhos" class="renomear-aparelho">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="renomear_aparelho"><input type="hidden" name="aparelho" value="<?= (int) $a['id'] ?>">
            <div class="entrada"><input type="text" name="apelido" maxlength="60" value="<?= e($a['apelido']) ?>" aria-label="Nome do aparelho"></div>
            <button type="submit" class="como-link">Salvar</button></form></td>
          <td><span class="dado"><?= $a['sincronizavel'] === null ? 'não informado' : ($a['sincronizavel'] ? 'sincronizado na sua conta' : 'só neste aparelho') ?></span></td>
          <td class="dado"><?= $a['ultimo_uso_em'] ? e(dataBr($a['ultimo_uso_em'], 'd/m/Y H:i')) . ($a['ultimo_aparelho'] ? '<br><span class="nulo">' . e($a['ultimo_aparelho']) . '</span>' : '') : 'ainda não usado' ?></td>
          <td class="dado"><?= e(dataBr($a['criado_em'], 'd/m/Y')) ?></td>
          <td class="acao"><form method="post" action="/perfil#aparelhos"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="remover_aparelho"><input type="hidden" name="aparelho" value="<?= (int) $a['id'] ?>">
            <button type="submit" title="Remover" aria-label="Remover <?= e($a['apelido']) ?>" data-confirmar="Remover &quot;<?= e($a['apelido']) ?>&quot;? Este aparelho deixa de entrar pela digital."><?= icone('lixeira') ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  <div id="area-cadastrar-aparelho" hidden>
    <div class="grade"><div class="campo c-6"><label for="apelido-aparelho">Nome deste aparelho</label>
      <div class="entrada"><input id="apelido-aparelho" maxlength="60" placeholder="Meu celular"></div></div></div>
    <div class="acoes"><button type="button" class="botao" data-cadastrar-aparelho data-csrf="<?= e($csrf) ?>"><?= icone('digital') ?>Cadastrar este aparelho</button></div>
    <p class="ajuda" id="sem-leitor" hidden>Este aparelho não tem leitor de digital nem reconhecimento de rosto. Dá para usar uma chave de segurança física.</p>
  </div>
</div>

<div class="cartao" id="certificados">
  <div class="cab-cartao"><h2>Certificado digital</h2><span class="conta-titulo"><?= count($certificados) ?></span></div>
  <p class="ajuda">Com o certificado A1 instalado no computador, você entra sem senha e sem código. Quem usar este computador
  com o certificado instalado entra na sua conta: em computador compartilhado, não vincule. Certificado renovado tem impressão
  digital nova e precisa ser vinculado de novo.</p>
  <?php if ($certificados): ?>
    <div class="rolagem sem-borda"><table class="dados">
      <thead><tr><th>Titular</th><th>Emissor</th><th>Validade</th><th>Último acesso</th><th class="acao"></th></tr></thead>
      <tbody>
      <?php foreach ($certificados as $c): ?>
        <tr>
          <td class="elastica"><b><?= e($c['titular']) ?></b></td>
          <td><?= e((string) $c['emissor']) ?></td>
          <td class="dado"><?= e(dataBr($c['valido_ate'], 'd/m/Y')) ?></td>
          <td class="dado"><?= $c['ultimo_uso_em'] ? e(dataBr($c['ultimo_uso_em'], 'd/m/Y H:i')) : 'ainda não usado' ?></td>
          <td class="acao"><form method="post" action="/perfil#certificados"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="remover_certificado"><input type="hidden" name="certificado" value="<?= (int) $c['id'] ?>">
            <button type="submit" title="Desvincular" aria-label="Desvincular o certificado de <?= e($c['titular']) ?>" data-confirmar="Este certificado deixa de entrar na sua conta."><?= icone('lixeira') ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  <?php if (Certificado::ligado()): ?>
    <div class="acoes"><a class="botao claro" href="/certificado?acao=vincular" target="_top"><?= icone('certificado') ?>Vincular o certificado deste computador</a></div>
  <?php else: ?>
    <p class="ajuda">A entrada por certificado digital ainda está sendo ligada.</p>
  <?php endif; ?>
</div>

<?php if (!$eu['softsul']): ?>
<form method="post" action="/perfil#excluir-conta" class="cartao" id="excluir-conta" target="_top">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="excluir_conta">
  <h2 class="titulo-secao">Excluir minha conta</h2>
  <p>A exclusão é definitiva e vale para todo o InvenTour. Saem o seu nome, e-mail, telefone,
     foto, senha, aparelhos e todos os acessos aos destinos.</p>
  <p class="ajuda">Continuam com o destino, sem os seus dados pessoais, os registros do que já foi feito no
     inventário: fichas coletadas e homologações. A ficha de um negócio também continua, porque é da empresa;
     para removê-la, fale com o destino.</p>
  <div class="grade">
    <div class="campo c-6"><label for="confirmacao">Para confirmar, digite EXCLUIR</label>
      <div class="entrada"><?= icone('alerta') ?><input id="confirmacao" name="confirmacao" autocomplete="off" autocapitalize="characters"></div></div>
  </div>
  <div class="acoes"><button class="botao perigo" type="submit" data-confirmar="Excluir a sua conta do InvenTour? Isso não pode ser desfeito."><?= icone('lixeira') ?>Excluir minha conta</button></div>
</form>
<?php endif; ?>
<?php paginaFichaFim();
