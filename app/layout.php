<?php
/**
 * Moldura das telas do painel: menu lateral, barra do topo, conteúdo,
 * modais compartilhados, telas de erro e de acesso.
 * Softsul Software
 *
 * É a moldura do Centurion (que é a do Orbit), com o mesmo HTML e as mesmas
 * classes do tema.css, para o InvenTour ser da família dos outros produtos.
 * O que difere:
 * - a troca de empresa é a troca de destino (município, região ou
 *   corporação), e a equipe Softsul tem a visão da plataforma;
 * - não há nada do domínio do Centurion (aulas, check-in, WhatsApp);
 * - nada de script no HTML (CSP): o tema escuro e o menu recolhido já saem
 *   do servidor, pela conta e por um cookie.
 */
declare(strict_types=1);
require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Empresa.php';
require_once __DIR__ . '/Marca.php';
require_once __DIR__ . '/Lista.php';
require_once __DIR__ . '/Foto.php';

Auth::iniciarSessao();

// Linhas por página escolhidas em qualquer lista (?pp=N): grava na pessoa e
// volta para o endereço limpo.
if (isset($_GET['pp']) && Auth::usuarioOuNulo()) {
    Lista::ouvirTamanho(strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/');
}

/**
 * Os números do menu: fichas esperando conferência (Homologação), experiências
 * esperando curadoria (Experiências) e fichas devolvidas para o campo no
 * escopo da pessoa (Coleta de campo). O mesmo cálculo serve a tela e o
 * /api/contadores.
 */
function contadoresDoMenu(): array
{
    $eid = Empresa::id();
    if (!$eid) {
        return [];
    }
    require_once __DIR__ . '/Esteira.php';
    $n = [];
    if (Auth::pode('homologacao.ver')) {
        $n['homologacao'] = Esteira::naFila($eid);
    }
    if (Auth::pode('experiencias.ver') && (Auth::ehSoftsul() || Auth::temPapel('gestor') || Auth::temPapel('consultor') || Auth::temPapel('coordenador'))) {
        // Para quem faz a curadoria: as experiências que esperam por ela.
        $q = Database::conexao()->prepare("SELECT COUNT(*) FROM inventario_experiencias WHERE empresa_id = ? AND situacao = 'aguardando'");
        $q->execute([$eid]);
        $n['experiencias'] = (int) $q->fetchColumn();
    }
    if (Auth::pode('trade.ver')) {
        // Pedidos de responsável e revisões do trade que esperam a equipe.
        require_once __DIR__ . '/Trade.php';
        $n['trade'] = Trade::esperando($eid);
    }
    if (Auth::pode('coleta.ver')) {
        // Na coleta, só o que pede ação: o que a conferência devolveu.
        $n['coleta'] = Esteira::devolvidos($eid);
    }
    return $n;
}

function paginaInicio(string $titulo, string $moduloAtivo = ''): void
{
    $quem = Auth::usuarioOuNulo();
    require_once __DIR__ . '/Passkey.php';
    $usuario = Auth::usuarioOuNulo();
    // Quem usa o painel é marcado como equipe nos portais: com o cookie
    // it_painel, o Google Analytics manda a visita como tráfego interno e o
    // filtro da propriedade a tira da conta (portal/assets/consentimento.js).
    if ($usuario && empty($_COOKIE['it_painel']) && !headers_sent()) {
        $dominio = (string) ((require __DIR__ . '/config.php')['app']['dominio_portal'] ?? 'inventour.com.br');
        setcookie('it_painel', '1', ['expires' => time() + 31536000, 'path' => '/', 'domain' => '.' . $dominio, 'secure' => true, 'samesite' => 'Lax']);
    }
    $modulos = Auth::modulosVisiveis();
    // Números ao lado de um item do menu (chave => quantidade), atualizados
    // pelo inventour.js em /api/contadores. Cada módulo novo que tenha fila
    // (fichas esperando homologação, por exemplo) entra aqui.
    $contadoresDoMenu = contadoresDoMenu();
    $escuro    = Auth::temaEscuro();
    $recolhido = ($_COOKIE['inventour_menu'] ?? '') === 'recolhido';
    ?>
<!doctype html>
<html lang="pt-BR"<?= $recolhido ? ' class="menu-recolhido"' : '' ?><?= $escuro ? ' data-tema="escuro"' : '' ?>>
<head>
<?php cabecaComum($titulo, $escuro); ?>
</head>
<body>
<?php svgSprite(); ?>
<div class="shell">

  <nav class="rail" aria-label="Módulos" id="rail">
    <div class="marca">
      <button type="button" class="abrir-menu" id="abrir-menu"
              aria-controls="itens-rail" aria-expanded="false" aria-label="Abrir o menu">
        <span class="tracos"><span></span><span></span><span></span></span>
      </button>
      <?php /* O logo da marca inteiro; com o menu recolhido, só o emblema. */ ?>
      <a class="ir-inicio" href="/painel" title="Ir para o início" aria-label="Ir para o início"><?=
        logoInvenTour('logo') ?><?= logoInvenTour('simbolo', 'logo-mini') ?></a>
      <span class="onde-estou" id="onde-estou"></span>
    </div>
    <div class="itens-rail" id="itens-rail">
    <?php
    // O título do grupo só aparece quando muda: um cabeçalho seguido de nada
    // é ruído.
    $grupoAtual = false;
    foreach ($modulos as $m):
        if ($m['grupo'] !== $grupoAtual):
            $grupoAtual = $m['grupo']; ?>
          <div class="grupo"><?= e($grupoAtual) ?></div>
        <?php endif; ?>
      <?php if (!empty($m['desligado'])): ?>
      <span class="item desligado" aria-disabled="true" title="<?= e(trim(explicacaoDoMenu((string) $m['chave']) . "\nDesabilitado por enquanto.")) ?>" data-nome="<?= e($m['nome']) ?>"><?= icone((string) $m['icone']) ?><span class="nome-item"><?= e($m['nome']) ?></span></span>
      <?php continue; endif; ?>
      <a class="item<?= $m['chave'] === $moduloAtivo ? ' ativo' : '' ?>" href="/<?= e($m['chave']) ?>"
         data-nome="<?= e($m['nome']) ?>" title="<?= e(explicacaoDoMenu((string) $m['chave']) ?: $m['nome']) ?>"<?= $m['chave'] === $moduloAtivo ? ' aria-current="page"' : '' ?>><?=
        icone((string) $m['icone']) ?><span class="nome-item"><?= e($m['nome']) ?></span><?php if (isset($contadoresDoMenu[$m['chave']])): ?><span class="conta-item" data-conta="<?= e((string) $m['chave']) ?>"<?=
        $contadoresDoMenu[$m['chave']] ? '' : ' hidden' ?>><?= (int) $contadoresDoMenu[$m['chave']] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    </div>
    <script src="/assets/menu-posicao.js?v=<?= (int) @filemtime(__DIR__ . '/../public/assets/menu-posicao.js') ?>"></script>
    <?php /* Pé do menu: a versão do InvenTour e, ao clicar, o menu com todos
             os produtos da Softsul (padrão da família, Luiz, 14/11/2026). A
             lista vem de /api/produtos, que repassa a central do Orbit, e só
             é buscada quando o menu abre. */
    require_once __DIR__ . '/ProdutosSoftsul.php';
    $versaoPropria = ProdutosSoftsul::versaoPropria();
    // No app nativo (InvenTour Gestão e InvenTour) o pé não aparece.
    if (!preg_match('#InvenTour(App|Gestao)/#', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))): ?>
    <div class="pe-rail">
      <button type="button" class="versao-produto" id="btn-produtos"
              aria-haspopup="true" aria-expanded="false" aria-controls="menu-produtos"
              data-versao="<?= $versaoPropria ? e($versaoPropria['versao']) : '' ?>"
              title="InvenTour<?= $versaoPropria ? ', versão ' . e($versaoPropria['versao']) : '' ?>. Clique para ver os outros produtos da Softsul.">
        <img class="icone-produto" src="/assets/icone.svg" alt="" width="28" height="28">
        <span class="quem">
          <b>InvenTour</b>
          <span><?= $versaoPropria ? 'versão ' . e($versaoPropria['versao']) : 'versão não gerada' ?></span>
        </span>
        <?= icone('seta', null, 'chevron') ?>
      </button>
      <div class="suspenso" id="menu-produtos" role="menu" hidden>
        <div class="titulo-suspenso">Produtos da Softsul</div>
        <div class="lista-produtos" id="lista-produtos"><span class="carregando-produtos">Carregando os produtos…</span></div>
      </div>
    </div>
    <?php endif; ?>
  </nav>
  <button type="button" class="recolher-menu" id="recolher-menu" aria-pressed="<?= $recolhido ? 'true' : 'false' ?>"
          title="<?= $recolhido ? 'Abrir o menu: volta a mostrar o nome dos módulos' : 'Recolher o menu: fica só com os ícones, para sobrar tela' ?>" aria-label="<?= $recolhido ? 'Abrir o menu' : 'Recolher o menu' ?>"><span class="puxador"><?= icone('seta') ?></span></button>

  <div class="painel">
    <header class="topo">
      <?php /* A busca do topo é o botão da paleta, e não um segundo campo:
               dois lugares para procurar a mesma coisa é a pessoa aprendendo
               qual dos dois funciona. */ ?>
      <button type="button" class="busca" id="abrir-paleta" aria-haspopup="dialog" aria-controls="paleta">
        <?= icone('busca') ?>
        <span class="dizer-busca">Buscar ou executar um comando</span>
        <kbd class="atalho-busca" id="atalho-paleta">/</kbd>
      </button>
      <div class="dir">
        <?php /* O contador da atualização ao vivo (molde do Orbit): aparece só
                 onde a tela se atualiza sozinha, e é o JavaScript que o revela.
                 Clicar atualiza na hora. */ ?>
        <button type="button" class="pulso-vivo" id="pulso-vivo" hidden title="Esta tela se atualiza sozinha">
          <span class="conta-pulso" id="conta-pulso">10</span>
          <span class="oculto-visual">Atualizar agora</span>
        </button>
        <?php
        // O seletor de destino só aparece para quem alcança mais de um, ou
        // para a equipe Softsul, que também tem a visão da plataforma.
        $destinos = $usuario ? Empresa::disponiveis((int) $usuario['id']) : [];
        $ativa = Empresa::atual();
        if (count($destinos) > 1 || ($usuario['softsul'] ?? false)):
        ?>
          <form id="form-trocar-destino" method="post" action="/trocar-empresa" hidden>
            <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
            <?php /* Trocar de destino recomeça pelo início: a tela em que a
                     pessoa estava é de outro destino (uma ficha, um filtro)
                     e quase nunca faz sentido no novo. */ ?>
            <input type="hidden" name="destino" value="/painel">
          </form>
          <div class="menu-empresa">
            <button type="button" class="troca-empresa" id="btn-empresa" title="Trocar o destino em que você está trabalhando"
                    aria-haspopup="true" aria-expanded="false" aria-controls="menu-empresas">
              <span class="ponto"></span>
              <span class="quem"><span>Destino</span><b><?= e($ativa['nome_fantasia'] ?? 'Plataforma InvenTour') ?></b></span>
              <?= icone('seta', null, 'chevron') ?>
            </button>
            <div class="suspenso" id="menu-empresas" role="menu" hidden>
              <div class="titulo-suspenso">Trabalhar em</div>
              <?php foreach ($destinos as $d): ?>
                <button type="submit" role="menuitem" form="form-trocar-destino" name="empresa_id" value="<?= (int) $d['id'] ?>"
                        class="<?= $ativa && (int) $ativa['id'] === (int) $d['id'] ? 'atual' : '' ?>">
                  <?= Foto::avatarDestino($d, 'avatar logo-menu') ?>
                  <span class="txt"><b><?= e($d['nome_fantasia']) ?></b>
                    <span><?= e(trim(($d['cidade'] ?? '') . (!empty($d['uf']) ? '/' . $d['uf'] : '') . (!empty($d['papel_nome']) ? ' · ' . $d['papel_nome'] : ''), ' /·') ?: 'destino') ?></span></span>
                </button>
              <?php endforeach; ?>
              <?php if ($usuario['softsul'] ?? false): ?>
                <div class="risco"></div>
                <button type="submit" role="menuitem" form="form-trocar-destino" name="empresa_id" value="0" class="<?= $ativa ? '' : 'atual' ?>">
                  <?= icone('painel') ?><span class="txt"><b>Plataforma InvenTour</b><span>todos os destinos, sem entrar em nenhum</span></span>
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="menu-usuario">
          <button type="button" class="perfil" id="btn-perfil" title="Seus dados, senha, segundo fator, digital, certificado, tema e sair"
                  aria-haspopup="true" aria-expanded="false" aria-controls="menu-perfil">
            <?= Foto::avatar('usuario', (int) $usuario['id'], $usuario['foto'] ?? null, (string) $usuario['nome']) ?>
            <span class="quem"><b><?= e(comoChamar($usuario)) ?></b><span><?= e((string) ($usuario['papel_nome'] ?? '')) ?></span></span>
            <?= icone('seta', null, 'chevron') ?>
          </button>
          <div class="suspenso" id="menu-perfil" role="menu" hidden>
            <div class="cabeca">
              <?= Foto::avatar('usuario', (int) $usuario['id'], $usuario['foto'] ?? null, (string) $usuario['nome'], 'avatar grande', 128) ?>
              <div>
                <b><?= e(comoChamar($usuario)) ?></b>
                <?php if (comoChamar($usuario) !== $usuario['nome']): ?><span><?= e((string) $usuario['nome']) ?></span><?php endif; ?>
                <span><?= e((string) $usuario['email']) ?></span>
                <span class="cracha"><?= e((string) ($usuario['papel_nome'] ?? '')) ?><?= $ativa ? ' · ' . e($ativa['nome_fantasia']) : '' ?></span>
              </div>
            </div>
            <a role="menuitem" href="/perfil" data-ficha="Meus dados" title="Nome, telefone, foto e preferências como linhas por página. Também é onde se exclui a conta."><?= icone('contatos') ?>Meus dados</a>
            <a role="menuitem" href="/perfil#senha" data-ficha="Trocar a senha" title="Troca a senha de entrada. A senha atual é pedida para confirmar que é você."><?= icone('cadeado') ?>Trocar a senha</a>
            <a role="menuitem" href="/perfil#autenticador" data-ficha="Segundo fator" title="O código do aplicativo autenticador, pedido depois da senha: sem ele, ninguém entra só com a senha."><?= icone('escudo') ?>Segundo fator<?php
              if (empty($usuario['duplo_fator_confirmado'])): ?><span class="marca-atencao">desligado</span><?php endif; ?></a>
            <?php $quantos = count(Passkey::doUsuario((int) $usuario['id'])); ?>
            <a role="menuitem" href="/perfil#aparelhos" data-ficha="Entrar por digital" title="Cadastra este aparelho para entrar com a digital, o rosto ou o PIN, sem digitar a senha."><?= icone('digital') ?>Entrar por digital<?php
              if ($quantos): ?><span class="marca-atencao bom"><?= $quantos ?></span><?php
              else: ?><span class="marca-atencao">cadastrar</span><?php endif; ?></a>
            <a role="menuitem" href="/perfil#certificados" data-ficha="Certificado digital" title="Vincula o certificado digital A1 à sua conta, para entrar com ele no lugar da senha."><?= icone('certificado') ?>Certificado digital</a>
            <button type="button" class="menu-tema" id="menu-tema" role="menuitemcheckbox" title="Alterna entre o tema claro e o escuro. A escolha fica guardada na sua conta."
                    aria-checked="<?= $escuro ? 'true' : 'false' ?>" data-csrf="<?= e(Auth::tokenCsrf()) ?>">
              <span class="txt"><b>Tema escuro</b><span id="menu-tema-estado"><?= $escuro
                ? 'Ativado · alternar para claro' : 'Desativado · alternar para escuro' ?></span></span>
              <span class="chave" aria-hidden="true"></span>
            </button>
            <div class="risco"></div>
            <?php /* Sair pergunta antes, no modal da casa: um toque sem querer
                     no celular derrubava a sessão e obrigava a entrar de novo
                     com senha e código. */ ?>
            <button type="submit" role="menuitem" class="sair" form="form-sair" title="Encerra a sessão neste aparelho. Para voltar, é preciso entrar de novo."
                    data-titulo="Sair do InvenTour?" data-confirmar="A sessão neste aparelho será encerrada. Para voltar, você entra de novo.">
              <?= icone('sair') ?>Sair</button>
          </div>
        </div>
      </div>
    </header>

    <form id="form-sair" method="post" action="/sair" hidden>
      <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
    </form>
    <main class="conteudo">
    <?= avisoGuardado() ?>
<?php
}

/**
 * Explicação de cada item do menu, que vai no title e aparece no balão da
 * casa (inventour.js). Escrita a partir do que a tela faz, e não repetindo
 * o nome: com o menu recolhido o balão já põe o nome em cima. Alguns itens
 * mudam entre o destino e a visão da plataforma.
 */
function explicacaoDoMenu(string $chave): string
{
    $plataforma = !Empresa::id();
    return match ($chave) {
        'painel' => $plataforma
            ? 'Visão da plataforma: destinos ativos, andamento dos inventários de cada destino, saúde das integrações e atividade da equipe.'
            : 'Resumo do destino: fichas do inventário por categoria e por situação, coleta em andamento e homologação pendente.',
        'inventario' => 'Fichas da oferta turística do destino: infraestrutura de apoio, serviços e equipamentos turísticos e atrativos, como no Inventário do Ministério do Turismo.',
        'experiencias' => 'Vivências que os lugares do inventário oferecem (colheita, oficina, degustação), nos 4 Es, com a curadoria antes de irem para o portal.',
        'coleta' => 'Coleta de campo: as fichas que pesquisadores e o trade preencheram, com a localização e as fotos, a caminho da homologação.',
        'homologacao' => 'Fila das fichas que esperam a homologação do município: abra cada uma para homologar, devolver para ajuste ou recusar.',
        'caderno' => 'Caderno oficial do inventário do destino, gerado em PDF por versão, a partir das fichas homologadas.',
        'roteiros' => 'Roteiros prontos para o visitante, com os lugares do inventário em ordem e a rota no mapa do portal.',
        'trade' => 'Quem responde pelos lugares do inventário: os pedidos feitos pelo portal, as atualizações que os responsáveis mandam e a transferência de titularidade.',
        'meus-lugares' => 'Os lugares por que você responde no portal: mande horário, contato, capacidade e fotos para a conferência da equipe.',
        'acessos' => 'O que o servidor registrou nos portais e no painel: pessoas e robôs separados, páginas mais vistas, o que os robôs de busca e de IA leem e os erros.',
        'visitas' => $plataforma
            ? 'Visitas aos portais pelo Google Analytics, com o filtro de destino e o comparativo entre eles.'
            : 'Visitas ao portal do destino pelo Google Analytics: sessões por dia, cidades, canais e o que os visitantes clicaram.',
        'phygital' => 'Placas e cartazes com QR Code nos atrativos e equipamentos: para onde cada leitura leva e quantas leituras teve.',
        'relatorios' => 'Relatórios gerenciais do destino: o inventário (lugares, capacidade, Cadastur, acessibilidade, conservação), o público do portal e a evolução mês a mês, em PDF e CSV.',
        'diagnostico' => 'Diagnóstico de destino turístico inteligente (DTI Brasil, do Ministério do Turismo): os 105 requisitos com nível, evidência e o Plano de Transformação.',
        'mapa-turismo' => 'Municípios do Mapa do Turismo Brasileiro, com região turística, categoria e os números que o Ministério do Turismo usa para categorizar.',
        'cadastur' => 'Prestadores do Cadastur, do Ministério do Turismo, por município, região, UF, atividade e situação, com a soma de leitos.',
        'destinos' => 'Municípios, regiões turísticas e corporações que usam o InvenTour: o cadastro e a situação de cada um.',
        'fichas' => 'As 54 fichas do Inventário da Oferta Turística do Ministério do Turismo, com os tipos e subtipos de cada uma e as orientações de preenchimento. Valem para todos os destinos.',
        'usuarios' => 'Quem tem acesso ao painel e com que papel.',
        'auditoria' => 'Registro de quem fez o quê no painel, e quando.',
        'configuracoes' => $plataforma
            ? 'Ajustes da plataforma: módulos, e-mail, WhatsApp, usuários e auditoria.'
            : 'Ajustes do destino: dados do destino, usuários e papéis e a auditoria.',
        default => '',
    };
}

function paginaFim(): void
{
    require_once __DIR__ . '/Passkey.php';
    $u = Auth::usuarioOuNulo();
    ?>
    </main>
  </div>
</div>

<?php if ($u):
    /* Convite para entrar pela digital NESTE aparelho. A pergunta não é "a
       pessoa já cadastrou algum aparelho", é "este aparelho já está
       cadastrado": o servidor não sabe de que aparelho se trata, então a
       marca fica no navegador, junto da assinatura da lista. O JavaScript
       só abre onde existe digital, rosto ou PIN, uma vez por sessão, e some
       por 30 dias depois de um "Agora não". */
    $meusAparelhos = Passkey::doUsuario((int) $u['id']); ?>
<dialog class="confirmador" id="modal-biometria" aria-labelledby="titulo-biometria" data-assinatura="<?= e(Passkey::assinatura((int) $u['id'])) ?>">
  <h2 id="titulo-biometria">Entrar com a digital neste aparelho?</h2>
  <div class="corpo">
    <p>Cadastrando este aparelho, você entra com a digital, o rosto ou o PIN dele, sem senha e sem o código do
    autenticador. A senha continua funcionando normalmente, em qualquer aparelho.</p>
    <?php if ($meusAparelhos): ?>
      <p class="rotulo">Já cadastrados</p>
      <div class="lista-aparelhos">
        <?php foreach ($meusAparelhos as $ap): ?>
          <div class="um-aparelho"><b><?= e($ap['apelido']) ?></b>
            <span class="dado"><?= $ap['sincronizavel'] ? 'sincronizado na sua conta' : 'só neste aparelho' ?> ·
            <?= $ap['ultimo_uso_em'] ? 'último acesso ' . e(dataBr($ap['ultimo_uso_em'], 'd/m/Y H:i')) : 'nunca usado' ?></span></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="campo">
      <label for="apelido-aparelho-modal">Nome deste aparelho</label>
      <div class="entrada"><input type="text" id="apelido-aparelho-modal" maxlength="60" placeholder="Este aparelho"></div>
      <span class="ajuda">Serve para você reconhecer na lista e poder tirar o acesso dele depois, se perder o aparelho.</span>
    </div>
  </div>
  <div class="acoes-modal">
    <button type="button" class="botao claro" id="adiar-biometria">Agora não</button>
    <button type="button" class="botao" data-cadastrar-aparelho data-csrf="<?= e(Auth::tokenCsrf()) ?>"><?= icone('digital') ?>Cadastrar este aparelho</button>
  </div>
</dialog>
<?php endif; ?>

<?php /* O modal de ficha: inclusão e alteração abrem aqui, com a própria
         tela de cadastro em modo popup. Não fecha com clique fora: cadastro
         longo perdido por um clique ao lado é o que mais irrita. */ ?>
<dialog class="confirmador janela ficha-modal" id="modal-ficha" aria-labelledby="titulo-ficha">
  <header class="cabeca-janela"><b id="titulo-ficha">Cadastro</b>
    <button type="button" data-fecha aria-label="Fechar">&times;</button></header>
  <iframe name="quadro-ficha" id="quadro-ficha" title="Cadastro"></iframe>
</dialog>

<?php /* Paleta de comandos. Uma por tela, sempre a mesma, porque a memória
         de teclado só se forma quando o gesto vale em todo lugar. */ ?>
<dialog class="paleta" id="paleta" aria-label="Buscar ou executar um comando">
  <div class="pegar-paleta">
    <?= icone('busca') ?>
    <input type="text" id="campo-paleta" autocomplete="off" spellcheck="false"
           placeholder="Buscar destino, pessoa ou escrever um comando"
           aria-label="Buscar ou executar um comando"
           role="combobox" aria-expanded="true" aria-controls="achados-paleta">
    <kbd>esc</kbd>
  </div>
  <div class="achados-paleta" id="achados-paleta" role="listbox"></div>
  <footer class="pe-paleta">
    <span><kbd>&uarr;</kbd><kbd>&darr;</kbd> andar</span>
    <span><kbd>enter</kbd> abrir</span>
    <span><kbd>esc</kbd> fechar</span>
  </footer>
</dialog>
</body>
</html>
<?php
}

/**
 * Telas que só existem na visão da plataforma (Destinos, Catálogos).
 * Quem é da Softsul e chega a elas de dentro de um destino não muda de
 * visão sozinho: a tela pergunta, e a troca só acontece no botão. Antes a
 * troca era calada, e a pessoa via "Plataforma" sem ter pedido.
 */
function exigirVisaoPlataforma(): void
{
    if (!Auth::ehSoftsul() || !Empresa::id()) {
        return;
    }
    $destino = Empresa::atual();
    $voltar = destinoSeguro((string) ($_SERVER['REQUEST_URI'] ?? '/painel'));
    if (!headers_sent()) {
        header('Cache-Control: no-store');
    }
    paginaInicio('Visão da plataforma');
    ?>
<div class="cab-pag"><div><h1>Esta tela é da visão da plataforma</h1>
  <p>Você está em <?= e((string) ($destino['nome_fantasia'] ?? 'um destino')) ?>. Para abrir esta tela, é preciso passar para a plataforma; o destino guardado como padrão não muda.</p></div></div>
<form method="post" action="/trocar-empresa" class="acoes">
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <input type="hidden" name="destino" value="<?= e($voltar) ?>">
  <input type="hidden" name="manter_padrao" value="1">
  <button type="submit" class="botao" name="empresa_id" value="0"><?= icone('painel') ?>Ir para a plataforma</button>
  <a class="botao claro" href="/painel">Ficar em <?= e((string) ($destino['nome_fantasia'] ?? 'no destino')) ?></a>
</form>
<?php
    paginaFim();
    exit;
}

/**
 * Tela de erro com a cara do sistema. A folha branca do Apache parece o
 * sistema ter caído, e ainda conta a versão do servidor a quem sonda.
 */
function paginaErro(int $codigo, string $recado, string $detalhe = ''): never
{
    if (!headers_sent()) {
        http_response_code($codigo);
        header('Cache-Control: no-store');
    }
    $rotulos = [400 => 'Pedido inválido', 403 => 'Sem permissão', 404 => 'Página não encontrada',
                409 => 'Não dá para fazer isso agora', 500 => 'Alguma coisa quebrou'];
    $titulo = $rotulos[$codigo] ?? 'Erro';

    // Volta para onde a pessoa estava, quando ela veio de uma tela do
    // próprio painel: o Referer de outro site nunca vira link aqui.
    $voltar = '';
    $de = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($de !== '' && $host !== '' && parse_url($de, PHP_URL_HOST) === $host) {
        $caminho = (string) (parse_url($de, PHP_URL_PATH) ?? '');
        $consulta = (string) (parse_url($de, PHP_URL_QUERY) ?? '');
        $voltar = destinoSeguro($caminho . ($consulta !== '' ? '?' . $consulta : ''), '');
        if ($voltar === ($_SERVER['REQUEST_URI'] ?? '')) {
            $voltar = '';
        }
    }

    if (!Auth::usuarioOuNulo()) {
        ?><!doctype html>
<html lang="pt-BR" data-tema="escuro"><head><?php cabecaComum($titulo, true); ?></head><body>
<?php svgSprite(); ?>
  <main class="tela-erro palco-escuro">
    <div class="topo-erro"><a href="/login" aria-label="InvenTour, ir para a entrada"><?= logoInvenTour('logo') ?></a></div>
    <?= explosaoDaMarca() ?>
    <div class="erro-texto">
      <h1><?= e($recado) ?></h1>
      <?php if ($detalhe !== ''): ?><p><?= e($detalhe) ?></p><?php endif; ?>
      <p>Se você chegou por um link do sistema, entre primeiro.</p>
      <div class="erro-acoes">
        <a class="botao" href="/login"><?= icone('cadeado') ?>Entrar no painel</a>
        <a class="botao claro" href="https://inventour.com.br"><?= icone('seta') ?>Conhecer o InvenTour</a>
      </div>
    </div>
    <p class="rodape-erro">Produto <a href="https://softsul.com" target="_blank" rel="noopener">Softsul Software</a> e <a href="https://azapping.com.br" target="_blank" rel="noopener">Zapping Consultoria</a></p>
  </main>
</body></html>
        <?php
        exit;
    }
    paginaInicio($titulo);
    ?>
    <section class="erro-cartao palco-escuro">
      <?= explosaoDaMarca() ?>
      <div class="erro-texto">
        <h1><?= e($recado) ?></h1>
        <?php if ($detalhe !== ''): ?><p><?= e($detalhe) ?></p><?php endif; ?>
        <p>Use a busca do topo (atalho Ctrl K) para achar destino, prestador ou pessoa pelo nome.</p>
        <div class="erro-acoes">
          <a class="botao" href="/painel"><?= icone('painel') ?>Ir para o início</a>
          <?php if ($voltar !== ''): ?><a class="botao claro" href="<?= e($voltar) ?>"><?= icone('seta') ?>Voltar para onde eu estava</a><?php endif; ?>
        </div>
      </div>
    </section>
    <?php
    paginaFim();
    exit;
}

/**
 * Barra o acesso a um registro sem contar se ele existe: "sem permissão"
 * confirmaria que o registro existe, e o número dele viraria informação.
 */
function negarRegistro(): never
{
    if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/')) {
        http_response_code(404);
        exit('Registro não encontrado.');
    }
    paginaErro(404, 'Registro não encontrado.',
        'Ele pode ter sido apagado, ou pertencer a um destino que não está no seu acesso.');
}

/* ------------------------------------------------------------ FICHA EM MODAL
   Toda inclusão e alteração de dados abre num modal, por cima da lista. Como
   no Orbit (contato.php), a ficha é a MESMA tela, em modo popup (?popup=1),
   dentro de um iframe do modal: um formulário só, que funciona dentro e fora
   do modal, e não dois que um dia divergem. */

/** A tela está aberta dentro do modal de ficha? */
function emPopup(): bool
{
    return !empty($_GET['popup']) || !empty($_POST['popup']);
}

/** O endereço, mantendo o modo popup quando a tela está no modal. */
function noPopup(string $url): string
{
    return emPopup() ? $url . (str_contains($url, '?') ? '&' : '?') . 'popup=1' : $url;
}

/** Abre a tela de ficha: com moldura completa, ou só o conteúdo, no modal. */
function paginaFicha(string $titulo, string $moduloAtivo = ''): void
{
    if (!emPopup()) {
        paginaInicio($titulo, $moduloAtivo);
        return;
    }
    $escuro = Auth::temaEscuro();
    ?>
<!doctype html>
<html lang="pt-BR"<?= $escuro ? ' data-tema="escuro"' : '' ?>>
<head>
<?php cabecaComum($titulo, $escuro); ?>
</head>
<body class="em-popup">
<?php svgSprite(); ?>
<main class="conteudo">
<?= avisoGuardado() ?>
<?php
}

function paginaFichaFim(): void
{
    if (!emPopup()) {
        paginaFim();
        return;
    }
    ?>
</main>
</body>
</html>
<?php
}

/**
 * Depois de salvar: fora do modal, volta para a tela; dentro dele, devolve
 * uma página mínima que avisa a lista (pelo inventour.js, porque a CSP não
 * aceita script escrito na página) para fechar o modal e se atualizar. O
 * aviso fica guardado na sessão e aparece na lista.
 */
function fichaSalva(string $voltaSemModal): never
{
    if (!emPopup()) {
        redirecionar($voltaSemModal);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
       . '<meta name="inventour-ficha-salva" content="1">'
       . '<script src="/assets/inventour.js?v=' . VERSAO_ASSETS . '" defer></script></head><body></body></html>';
    exit;
}

/**
 * Início das telas de entrada (login, código, convite), sem menu. Sempre no
 * tema escuro, o padrão do painel, qualquer que seja o tema do último
 * usuário naquele navegador.
 */
function acessoInicio(string $titulo, string $frase, string $explica): void
{
    $escuro = true;
    ?>
<!doctype html>
<html lang="pt-BR"<?= $escuro ? ' data-tema="escuro"' : '' ?>>
<head>
<?php cabecaComum($titulo, $escuro); ?>
</head>
<body>
<?php svgSprite(); ?>
<main class="tela-acesso">
  <?php /* No app público (InvenTour), que abre esta tela por "Excluir minha
           conta": o iPhone não tem botão de voltar. O inventour.js mostra. */ ?>
  <a class="voltar-acesso" href="https://portal.inventour.com.br/" data-voltar-app hidden><?= icone('seta') ?><span>Voltar</span></a>
  <section class="acesso-marca palco-escuro">
    <?= explosaoDaMarca() ?>
    <?php /* Só o nome: o emblema já está no centro dos raios. */ ?>
    <?= logoInvenTour('palavra') ?>
    <div class="dizer">
      <h1><?= e($frase) ?></h1>
      <p><?= e($explica) ?></p>
    </div>
    <p class="assinatura">Produto <a href="https://softsul.com" target="_blank" rel="noopener">Softsul Software</a> e <a href="https://azapping.com.br" target="_blank" rel="noopener">Zapping Consultoria</a> · <a href="/privacidade">Privacidade</a></p>
  </section>
  <section class="acesso-form">
<?php
}

function acessoFim(): void
{
    ?>
  </section>
</main>
</body>
</html>
<?php
}

/** Mensagem de erro ou sucesso nas telas de entrada. */
function avisoAcesso(string $erro, string $ok = ''): string
{
    if ($erro !== '') {
        return '<p class="aviso ruim" role="alert">' . icone('alerta') . '<span>' . e($erro) . '</span></p>';
    }
    if ($ok !== '') {
        return '<p class="aviso bom" role="status">' . icone('check') . '<span>' . e($ok) . '</span></p>';
    }
    return '';
}

/**
 * O QR Code e o código para digitar à mão, no cadastro do autenticador. O QR
 * é gerado aqui no servidor: mandar a semente para uma API pública de QR a
 * entregaria a um terceiro.
 */
function blocoCadastro2fa(string $segredo, string $email): string
{
    require_once __DIR__ . '/QrCode.php';
    return '<div class="cadastro-2fa">'
         . '<div class="qr">' . QrCode::svg(TOTP::uri($segredo, $email, 'InvenTour')) . '</div>'
         . '<ol class="passos-2fa">'
         . '<li>Instale um aplicativo autenticador no celular (Google Authenticator, Microsoft Authenticator ou Authy).</li>'
         . '<li>No aplicativo, toque em adicionar e aponte a câmera para o QR Code.</li>'
         . '<li>Sem câmera? Digite este código no aplicativo:</li>'
         . '</ol>'
         . '<div class="segredo">' . e(TOTP::segredoLegivel($segredo)) . '</div>'
         . '</div>';
}
