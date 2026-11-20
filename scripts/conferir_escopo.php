<?php
/**
 * Teste automático de isolamento entre destinos.
 * Softsul Software
 *
 *   php scripts/conferir_escopo.php            (de preferência como www-data,
 *   sudo -u www-data php scripts/conferir_escopo.php   para testar também /foto)
 *
 * Por que existe: o filtro por destino vive na consulta (Empresa::filtro), e
 * basta UMA consulta nova sem ele para um destino ver o dado de outro
 * trocando o número na URL. Revisão de código não pega isso com segurança;
 * este script pega, e falha de escopo derruba a entrega.
 *
 * O que faz:
 * 1. Cria dois destinos de teste (slugs teste-escopo-a e teste-escopo-b) e,
 *    em cada um, um gestor e um prestador. Os cadastros do inventário entram
 *    em ce_criarDados quando as tabelas deles existirem.
 * 2. Chama as telas de verdade pelo PHP CLI, uma por processo, com $_SERVER,
 *    $_GET, $_POST e $_SESSION montados como numa sessão real (o mesmo
 *    formato que Auth grava no login). Processo separado porque as telas
 *    terminam com exit, e porque cada requisição real começa do zero.
 * 3. Descobre sozinho as telas que leem $_GET['id'] (public/*.php), as APIs
 *    de busca (public/api) e as listas com busca, para tela nova entrar no
 *    teste sem ninguém lembrar de acrescentar.
 * 4. Com a sessão do gestor de A, tenta abrir, alterar e achar na busca os
 *    registros de B, e o mesmo com o prestador de A. Esperado: 404 ou 403 e
 *    nenhum nome alheio na resposta. Também confere, no fim, que nenhum
 *    registro de B mudou no banco.
 * 5. Controles: o gestor de A abre os próprios registros e recebe 200 com o
 *    nome deles. Sem isso, um teste quebrado (sessão inválida, por exemplo)
 *    passaria com 404 em tudo e ninguém perceberia.
 * 6. Apaga tudo o que criou (try/finally), mesmo se falhar no meio, e
 *    confere que nada ficou. Antes de começar apaga sobra de execução
 *    interrompida, então pode rodar em produção quantas vezes for preciso.
 *
 * Saída: uma linha por tentativa (OK, FALHA ou AVISO), resumo no fim e
 * código de saída 1 se houver qualquer FALHA ou sobra no banco.
 *
 * Os registros de teste usam e-mail no domínio .invalid (reservado, nunca
 * existe de verdade) e nomes com uma marca aleatória por execução; é por
 * essa marca que o script reconhece dado alheio na resposta.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const CE_SLUG_A = 'teste-escopo-a';
const CE_SLUG_B = 'teste-escopo-b';
const CE_DOMINIO_EMAIL = '@inventour.invalid';
const CE_IP = '192.0.2.77'; // TEST-NET-1: nunca é um cliente de verdade
const CE_CSRF = 'conferirescopo0000000000000000000000000000000000000000000000000000';

// ------------------------------------------------- MODO TELA (processo filho)
// O processo filho recebe a requisição em JSON pela entrada padrão, monta o
// ambiente e inclui a tela no escopo global, como o Apache faria.
if (($argv[1] ?? '') === '--executar-tela') {
    $ceReq = json_decode((string) stream_get_contents(STDIN), true);
    ce_prepararTela($ceReq);
    unset($argv, $argc);
    require $ceReq['arquivo'];
    exit;
}

// ------------------------------------------------------------ MODO TESTE
require_once __DIR__ . '/../app/Database.php';

$ceResultado = ['ok' => 0, 'falha' => 0, 'aviso' => 0];
$pdo = Database::conexao();

// Duas execuções ao mesmo tempo apagariam os dados uma da outra.
if (!(int) $pdo->query("SELECT GET_LOCK('inventour_conferir_escopo', 0)")->fetchColumn()) {
    fwrite(STDERR, "Outra execução do conferir_escopo está em andamento.\n");
    exit(2);
}

$cfg = require __DIR__ . '/../app/config.php';
$raiz = dirname(__DIR__);
$fotosCriadas = [];
$sobras = [];

echo "Conferência de escopo entre destinos\n";
$antes = ce_limpar($pdo);
if ($antes['apagadas'] > 0) {
    echo "Sobra de execução anterior apagada: {$antes['apagadas']} linhas.\n";
}

try {
    $d = ce_criarDados($pdo, $cfg, $fotosCriadas);
    echo "Dados de teste criados: destinos {$d['A']['empresa']} e {$d['B']['empresa']}.\n\n";

    $retratoB = ce_retrato($pdo, $d, 'B');

    $gestorA = ce_sessao($d['A']['gestor'], $d['A']['empresa']);
    $propA = ce_sessao($d['A']['prestador'], $d['A']['empresa']);
    $tokB = $d['B']['tok'];
    $tokA = $d['A']['tok'];
    $tokA2 = $d['A']['tok2'];

    // ----------------------------------------------- TELAS COM ID NA URL
    echo "== Telas com id na URL\n";
    foreach (ce_telasComId($raiz) as $tela) {
        $arq = "{$raiz}/public/{$tela}.php";
        foreach (ce_alvosDaTela($tela, $d) as $alvo) {
            [$rotulo, $get, $recurso, $id, $nome, $dono, $controle] = $alvo;
            $req = ['arquivo' => $arq, 'get' => $get + ['id' => $id], 'endereco' => [$recurso, $id, $nome]];

            if ($controle !== null) {
                // Controle: o gestor de A abre o próprio registro.
                $r = ce_tela($req, $gestorA);
                ce_esperarAberto("controle: gestor A abre {$rotulo} de A", $r, $controle, false);
                continue;
            }
            $r = ce_tela($req, $gestorA);
            ce_esperarNegado("gestor A abre {$rotulo} de B ({$tela}/{$id})", $r, [$tokB]);
            $r = ce_tela($req + ['metodo' => 'POST', 'post' => ['csrf' => CE_CSRF, 'acao' => 'salvar', 'nome' => 'Alterado', 'nome_fantasia' => 'Alterado', 'titulo' => 'Alterado']], $gestorA);
            ce_esperarNegado("gestor A grava em {$rotulo} de B ({$tela}/{$id}, POST)", $r, [$tokB]);
            $r = ce_tela($req, $propA);
            ce_esperarNegado("prestador A abre {$rotulo} de B ({$tela}/{$id})", $r, [$tokB]);
        }
        // Prestador de A contra o outro organizador do mesmo destino.
        foreach (ce_alvosDoPrestador($tela, $d) as [$rotulo, $id, $recurso, $nome, $soGravar]) {
            $req = ['arquivo' => $arq, 'get' => ['id' => $id], 'endereco' => [$recurso, $id, $nome]];
            if (!$soGravar) {
                $r = ce_tela($req, $propA);
                ce_esperarNegado("prestador A abre {$rotulo} do outro organizador de A ({$tela}/{$id})", $r, [$tokA2]);
            }
            $r = ce_tela($req + ['metodo' => 'POST', 'post' => ['csrf' => CE_CSRF, 'acao' => 'salvar', 'nome' => 'Alterado', 'nome_fantasia' => 'Alterado', 'titulo' => 'Alterado']], $propA);
            ce_registrar($r['erro'] === null, "prestador A grava em {$rotulo} do outro organizador de A ({$tela}/{$id}, POST): HTTP {$r['codigo']}, conferido no banco no fim"
                . ($r['erro'] ? ' (erro: ' . $r['erro'] . ')' : ''));
        }
    }

    // ----------------------------------------- AÇÕES POST NAS LISTAS
    echo "\n== Ações de linha nas listas (POST com id de B)\n";
    $acoesLista = [
        'usuarios'      => ['desativar', $d['B']['gestor'], 'usuário'],
    ];
    foreach ($acoesLista as $lista => [$acao, $id, $rotulo]) {
        if (!is_file("{$raiz}/public/{$lista}.php")) {
            continue;
        }
        $r = ce_tela(['arquivo' => "{$raiz}/public/{$lista}.php", 'metodo' => 'POST', 'uri' => '/' . $lista,
                      'post' => ['csrf' => CE_CSRF, 'acao' => $acao, 'id' => $id, 'volta' => '/' . $lista]], $gestorA);
        ce_registrar($r['erro'] === null && !str_contains($r['corpo'], $tokB),
            "gestor A {$acao} {$rotulo} de B pela lista /{$lista}: HTTP {$r['codigo']}, efeito conferido no banco no fim"
            . ($r['erro'] ? ' (erro: ' . $r['erro'] . ')' : ''));
    }
    // Troca de destino para um destino sem vínculo.
    $r = ce_tela(['arquivo' => "{$raiz}/public/trocar-empresa.php", 'metodo' => 'POST', 'uri' => '/trocar-empresa',
                  'post' => ['csrf' => CE_CSRF, 'empresa_id' => $d['B']['empresa']]], $gestorA);
    ce_registrar((int) ($r['sessao']['empresa_id'] ?? 0) === $d['A']['empresa'],
        'gestor A tenta trocar para o destino B: sessão continua em A (empresa_id ' . (int) ($r['sessao']['empresa_id'] ?? 0) . ')');

    // ------------------------------------------------------ BUSCAS (APIs)
    echo "\n== APIs de busca\n";
    foreach (ce_apisDeBusca($raiz) as $api) {
        $arq = "{$raiz}/public/api/{$api}.php";
        // A busca vai com o começo da marca, e o vazamento é a marca inteira
        // na resposta: assim o texto digitado, que a tela pode repetir no
        // campo de busca, não conta como dado alheio.
        foreach (['', 'Escopo', ce_prefixo($tokB), ce_prefixo($tokA2)] as $q) {
            $req = ['arquivo' => $arq, 'uri' => '/api/' . $api, 'get' => ['q' => $q]];
            $r = ce_tela($req, $gestorA);
            ce_esperarSemVazamento("gestor A em /api/{$api}?q=" . ($q === '' ? '(vazio)' : $q), $r, [$tokB]);
            $r = ce_tela($req, $propA);
            ce_esperarSemVazamento("prestador A em /api/{$api}?q=" . ($q === '' ? '(vazio)' : $q), $r, [$tokB, $tokA2]);
        }
        $r = ce_tela(['arquivo' => $arq, 'uri' => '/api/' . $api, 'get' => ['q' => ce_prefixo($tokA)]], $gestorA);
        ce_controleDeBusca("controle: gestor A acha o que é de A em /api/{$api}", $r, $tokA);
    }

    // ------------------------------------------------ BUSCAS (LISTAS)
    echo "\n== Listas com busca\n";
    foreach (ce_listasComBusca($raiz) as [$lista, $param]) {
        $arq = "{$raiz}/public/{$lista}.php";
        $uri = in_array($lista, ['usuarios', 'auditoria'], true) ? '/configuracoes/' . $lista : '/' . $lista;
        foreach (['Escopo', ce_prefixo($tokB), ce_prefixo($tokA2)] as $q) {
            $req = ['arquivo' => $arq, 'uri' => $uri, 'get' => [$param => $q, 'situacao' => 'todos']];
            $r = ce_tela($req, $gestorA);
            ce_esperarSemVazamento("gestor A em /{$lista}?{$param}={$q}", $r, [$tokB]);
            $r = ce_tela($req, $propA);
            ce_esperarSemVazamento("prestador A em /{$lista}?{$param}={$q}", $r, [$tokB, $tokA2]);
        }
        $r = ce_tela(['arquivo' => $arq, 'uri' => $uri, 'get' => [$param => ce_prefixo($tokA), 'situacao' => 'todos']], $gestorA);
        ce_controleDeBusca("controle: gestor A acha o que é de A em /{$lista}", $r, $tokA);
    }

    // ---------------------------------------- NADA DE B MUDOU NO BANCO
    echo "\n== Integridade no banco\n";
    ce_compararRetrato('registros de B intactos depois de todas as tentativas', $retratoB, ce_retrato($pdo, $d, 'B'));
} catch (Throwable $e) {
    ce_registrar(false, 'o teste parou no meio: ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    echo "\n== Limpeza\n";
    foreach ($fotosCriadas as $f) {
        @unlink($f);
    }
    $limpeza = ce_limpar($pdo);
    $sobras = $limpeza['sobras'];
    foreach ($fotosCriadas as $f) {
        if (is_file($f)) {
            $sobras[] = 'arquivo ' . $f;
        }
    }
    echo "Apagadas {$limpeza['apagadas']} linhas de teste.\n";
    if ($sobras) {
        foreach ($sobras as $s) {
            ce_registrar(false, 'sobrou depois da limpeza: ' . $s);
        }
    } else {
        ce_registrar(true, 'nada de teste ficou no banco');
    }
    $pdo->query("SELECT RELEASE_LOCK('inventour_conferir_escopo')");
}

echo "\nResumo: " . ($ceResultado['ok'] + $ceResultado['falha']) . " tentativas, {$ceResultado['ok']} OK, "
   . "{$ceResultado['falha']} FALHA, {$ceResultado['aviso']} AVISO.\n";
exit($ceResultado['falha'] > 0 ? 1 : 0);

// =================================================================== FUNÇÕES

/** Monta o ambiente de uma requisição no processo filho. */
function ce_prepararTela(array $req): void
{
    $get = $req['get'] ?? [];
    $post = $req['post'] ?? [];
    $metodo = $req['metodo'] ?? 'GET';
    $uri = (string) ($req['uri'] ?? '/');
    if (!empty($req['endereco'])) {
        // O endereço canônico, para a tela não responder 301 ao endereço
        // "errado": num ataque, o canônico é justamente o melhor caso para o
        // atacante, e um 301 esconderia o que a tela faria.
        require_once __DIR__ . '/../app/funcoes.php';
        [$recurso, $id, $nome] = $req['endereco'];
        $uri = endereco((string) $recurso, (int) $id, (string) $nome);
        unset($get['id']);
        $get = ['id' => (string) $id] + $get;
    }
    $query = array_diff_key($get, ['id' => 1]);
    $_SERVER['REQUEST_METHOD'] = $metodo;
    $_SERVER['REQUEST_URI'] = $uri . ($query ? '?' . http_build_query($query) : '');
    $_SERVER['HTTP_HOST'] = 'app.inventour.com.br';
    $_SERVER['SERVER_NAME'] = 'app.inventour.com.br';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REMOTE_ADDR'] = CE_IP;
    $_SERVER['HTTP_USER_AGENT'] = 'conferir_escopo';
    $_SERVER['HTTP_ACCEPT'] = 'text/html';
    $_SERVER['SCRIPT_FILENAME'] = $req['arquivo'];
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/' . basename($req['arquivo']);
    $_GET = array_map('strval', $get);
    $_POST = $post;
    $_REQUEST = $_POST + $_GET;
    $_COOKIE = [];
    $_FILES = [];
    $_SESSION = $req['sessao'] ?? [];
    chdir(dirname($req['arquivo']));

    register_shutdown_function(function (): void {
        $corpo = '';
        while (ob_get_level() > 0) {
            $corpo = (string) ob_get_clean() . $corpo;
        }
        $codigo = http_response_code();
        $e = error_get_last();
        $fatal = $e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
        fwrite(STDOUT, "\n@@RESULTADO@@" . json_encode([
            'codigo' => $codigo === false ? 200 : (int) $codigo,
            'corpo'  => $corpo,
            'sessao' => $_SESSION ?? [],
            'erro'   => $fatal ? $e['message'] . ' em ' . $e['file'] . ':' . $e['line'] : null,
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    });
    ob_start();
}

/** Chama uma tela num processo próprio e devolve código HTTP, corpo e sessão final. */
function ce_tela(array $req, array $sessao): array
{
    $req['sessao'] = $sessao;
    $req['metodo'] = $req['metodo'] ?? 'GET';
    $proc = proc_open([PHP_BINARY, '-d', 'display_errors=stdout', __FILE__, '--executar-tela'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('não deu para abrir o processo da tela');
    }
    fwrite($pipes[0], (string) json_encode($req));
    fclose($pipes[0]);
    $saida = (string) stream_get_contents($pipes[1]);
    $erros = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    $pos = strrpos($saida, "\n@@RESULTADO@@");
    if ($pos === false) {
        return ['codigo' => 0, 'corpo' => $saida, 'sessao' => [], 'erro' => 'a tela não devolveu resultado: ' . trim(mb_substr($erros . $saida, 0, 300))];
    }
    $r = json_decode(substr($saida, $pos + 14), true);
    if (!is_array($r)) {
        return ['codigo' => 0, 'corpo' => $saida, 'sessao' => [], 'erro' => 'resultado ilegível'];
    }
    // O que a tela escreveu fora do buffer (antes do ob_start) também conta.
    $r['corpo'] = substr($saida, 0, $pos) . $r['corpo'];
    return $r;
}

/** O começo de uma marca: acha o registro na busca sem ser a marca inteira. */
function ce_prefixo(string $marca): string
{
    return substr($marca, 0, strlen($marca) - 3);
}

/** Sessão no mesmo formato que Auth::concluirLogin grava. */
function ce_sessao(int $usuarioId, int $empresaId): array
{
    return [
        'usuario_id' => $usuarioId, 'entrou_por' => 'senha', 'logado_em' => time(), 'ultimo_uso' => time(),
        'versao_sessao' => 1, 'empresa_id' => $empresaId, 'csrf' => CE_CSRF,
    ];
}

function ce_registrar(bool $ok, string $texto, bool $aviso = false): void
{
    global $ceResultado;
    if ($aviso) {
        $ceResultado['aviso']++;
        echo 'AVISO  ' . $texto . "\n";
        return;
    }
    $ceResultado[$ok ? 'ok' : 'falha']++;
    echo ($ok ? 'OK     ' : 'FALHA  ') . $texto . "\n";
}

/** Tentativa de alcançar dado alheio: só 403 ou 404, e sem o nome alheio no corpo. */
function ce_esperarNegado(string $rotulo, array $r, array $alheios): void
{
    $vazou = array_values(array_filter($alheios, fn ($t) => str_contains($r['corpo'], $t)));
    $ok = in_array($r['codigo'], [403, 404], true) && !$vazou && $r['erro'] === null;
    ce_registrar($ok, $rotulo . ': HTTP ' . $r['codigo']
        . ($vazou ? ', com dado alheio na resposta' : '')
        . ($r['erro'] ? ' (erro: ' . $r['erro'] . ')' : ''));
}

/** Busca: pode responder, mas sem nome alheio e sem erro. */
function ce_esperarSemVazamento(string $rotulo, array $r, array $alheios): void
{
    $vazou = array_values(array_filter($alheios, fn ($t) => str_contains($r['corpo'], $t)));
    $ok = !$vazou && $r['erro'] === null && $r['codigo'] < 500 && $r['codigo'] > 0;
    ce_registrar($ok, $rotulo . ': HTTP ' . $r['codigo']
        . ($vazou ? ', DEVOLVEU registro alheio' : ', nada alheio')
        . ($r['erro'] ? ' (erro: ' . $r['erro'] . ')' : ''));
}

/** Controle: o próprio registro abre com 200 e o nome dele na tela. */
function ce_esperarAberto(string $rotulo, array $r, string $marca, bool $soAviso): void
{
    $ok = $r['codigo'] === 200 && str_contains($r['corpo'], $marca) && $r['erro'] === null;
    if (!$ok && $soAviso) {
        ce_registrar(false, $rotulo . ': HTTP ' . $r['codigo'] . ' (módulo inativo: as tentativas desta tela só provam o bloqueio do módulo)', true);
        return;
    }
    ce_registrar($ok, $rotulo . ': HTTP ' . $r['codigo'] . ($ok ? '' : ', sem o próprio registro: o teste desta tela não vale')
        . ($r['erro'] ? ' (erro: ' . $r['erro'] . ')' : ''));
}

/** Controle de busca: aviso (e não falha) quando a busca não acha o próprio; nem toda busca procura por nome. */
function ce_controleDeBusca(string $rotulo, array $r, string $marca): void
{
    if (str_contains($r['corpo'], $marca)) {
        ce_registrar(true, $rotulo);
    } else {
        ce_registrar(false, $rotulo . ': não achou (HTTP ' . $r['codigo'] . '); a busca acima pode não procurar por nome', true);
    }
}

/** Telas do painel que leem $_GET['id'], pelo nome sem .php. */
function ce_telasComId(string $raiz): array
{
    $telas = [];
    foreach (glob("{$raiz}/public/*.php") ?: [] as $arq) {
        if (preg_match('/\$_GET\[\s*[\'"]id[\'"]\s*\]/', (string) file_get_contents($arq))) {
            $telas[] = basename($arq, '.php');
        }
    }
    sort($telas);
    return $telas;
}

/**
 * Os alvos de uma tela: [rótulo, get extra, recurso do endereço, id, nome,
 * dono, marca do controle ou null]. Tela sem mapa recebe todos os ids de B:
 * ela entra no teste sem ninguém precisar lembrar dela.
 */
function ce_alvosDaTela(string $tela, array $d): array
{
    $A = $d['A'];
    $B = $d['B'];
    $n = $d['nomes'];
    $alvos = [];
    $add = function (string $rotulo, string $recurso, int $id, array $get = [], ?string $controle = null) use (&$alvos, $n, $tela): void {
        $alvos[] = [$rotulo, $get, $recurso, $id, $n["{$recurso}:{$id}"] ?? ($n["*:{$id}"] ?? ''), null, $controle];
    };
    switch ($tela) {
        case 'usuario':
            $add('usuário', 'usuario', $A['prestador'], [], $A['tok']);
            $add('usuário (gestor)', 'usuario', $B['gestor']);
            $add('usuário (prestador)', 'usuario', $B['prestador']);
            break;
        case 'destino':
            $add('destino', 'destino', $A['empresa'], [], $A['tok']);
            $add('destino', 'destino', $B['empresa']);
            break;
        case 'ficha':
            // Ficha do catálogo do MTur: global, igual para todos os destinos,
            // só da visão da plataforma.
            break;
        case 'prestador':
            // Ficha do Cadastur: base pública do Ministério do Turismo, igual
            // para todos os destinos (o id é do Cadastur, não de destino).
            // Não há dado de B a vazar; o acesso é pela permissão cadastur.ver.
            break;
        case 'foto':
            // Sem arquivo de foto, a tela responde 404 de qualquer jeito; com
            // arquivo (rodando como www-data), o controle prova que ela serve.
            if ($d['fotos']) {
                $alvos[] = ['foto de usuário', ['tipo' => 'usuario'], 'foto/usuario', $A['prestador'], '', null, 'RIFF'];
            }
            $alvos[] = ['foto de usuário', ['tipo' => 'usuario'], 'foto/usuario', $B['gestor'], '', null, null];
            $alvos[] = ['foto do destino', ['tipo' => 'empresa'], 'foto/empresa', $B['empresa'], '', null, null];
            break;
        default:
            // Tela nova sem mapa: tenta cada id de B.
            foreach ($d['idsB'] as $rot => $id) {
                $alvos[] = ["{$rot} (tela sem mapa no teste)", [], $tela, $id, '', null, null];
            }
    }
    return $alvos;
}

/** Alvos do prestador de A dentro do próprio destino (chegam com as fichas do inventário). */
function ce_alvosDoPrestador(string $tela, array $d): array
{
    return [];
}

/** APIs que buscam dado do destino. CEP e CNPJ consultam fora; sessão, tema e passkey não devolvem registro. */
function ce_apisDeBusca(string $raiz): array
{
    $fora = ['cep', 'cnpj', 'sessao', 'tema', 'passkey-entrar', 'passkey-registrar'];
    $apis = [];
    foreach (glob("{$raiz}/public/api/*.php") ?: [] as $arq) {
        $nome = basename($arq, '.php');
        if (!in_array($nome, $fora, true)) {
            $apis[] = $nome;
        }
    }
    sort($apis);
    return $apis;
}

/** Listas do painel com busca por texto: [tela, parâmetro]. */
function ce_listasComBusca(string $raiz): array
{
    $listas = [];
    foreach (glob("{$raiz}/public/*.php") ?: [] as $arq) {
        $nome = basename($arq, '.php');
        if (preg_match('/\$_GET\[\s*[\'"](q|busca)[\'"]\s*\]/', (string) file_get_contents($arq), $m)) {
            $listas[] = [$nome, $m[1]];
        }
    }
    sort($listas);
    return $listas;
}

/** Insere uma linha e devolve o id. */
function ce_inserir(PDO $pdo, string $tabela, array $dados): int
{
    $cols = array_keys($dados);
    $pdo->prepare('INSERT INTO ' . $tabela . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
        ->execute(array_values($dados));
    return (int) $pdo->lastInsertId();
}

function ce_tabelaExiste(PDO $pdo, string $tabela): bool
{
    $q = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $q->execute([$tabela]);
    return (bool) $q->fetchColumn();
}

/** Cria os dois destinos e tudo o que há dentro deles. */
function ce_criarDados(PDO $pdo, array $cfg, array &$fotosCriadas): array
{
    $papel = fn (string $chave): int => (int) $pdo->query('SELECT id FROM papeis WHERE chave = ' . $pdo->quote($chave))->fetchColumn();
    $gestor = $papel('gestor');
    $prestador = $papel('prestador');
    $d = [ 'nomes' => [], 'fotos' => false, 'idsB' => []];

    foreach (['A' => CE_SLUG_A, 'B' => CE_SLUG_B] as $letra => $slug) {
        $tok = 'zq' . strtolower($letra) . bin2hex(random_bytes(4));
        $tok2 = 'zq' . strtolower($letra) . 'dois' . bin2hex(random_bytes(4));
        $x = ['tok' => $tok, 'tok2' => $tok2];
        $x['empresa'] = ce_inserir($pdo, 'empresas', [
            'nome' => "Destino Teste Escopo {$letra} {$tok}", 'nome_fantasia' => "Escopo {$letra} {$tok}",
            'tipo' => 'municipio', 'slug' => $slug, 'exigir_2fa_equipe' => 0, 'ativo' => 1, 'criado_em' => date('Y-m-d H:i:s'),
        ]);
        $d['nomes']["destino:{$x['empresa']}"] = "Escopo {$letra} {$tok}";
        foreach (['gestor' => $gestor, 'prestador' => $prestador] as $tipo => $papelId) {
            $nome = ucfirst($tipo) . " Escopo {$tok}";
            $uid = ce_inserir($pdo, 'usuarios', [
                'nome' => $nome, 'email' => 'teste-escopo-' . strtolower($letra) . '-' . $tipo . CE_DOMINIO_EMAIL,
                'softsul' => 0, 'ativo' => 1, 'duplo_fator_confirmado' => 1, 'versao_sessao' => 1,
                'empresa_padrao_id' => $x['empresa'], 'criado_em' => date('Y-m-d H:i:s'),
            ]);
            ce_inserir($pdo, 'usuario_empresa', ['usuario_id' => $uid, 'empresa_id' => $x['empresa'], 'papel_id' => $papelId, 'ativo' => 1, 'criado_em' => date('Y-m-d H:i:s')]);
            $pdo->prepare('INSERT INTO usuario_empresa_papel (usuario_id, empresa_id, papel_id) VALUES (?, ?, ?)')->execute([$uid, $x['empresa'], $papelId]);
            $x[$tipo] = $uid;
            $d['nomes']["usuario:{$uid}"] = $nome;
        }
        // Uma linha de auditoria do destino, para a busca da auditoria ter o
        // que (não) achar.
        $pdo->prepare('INSERT INTO auditoria (usuario_id, usuario_nome, empresa_id, acao, entidade, resumo, ip, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$x['gestor'], "Gestor Escopo {$tok}", $x['empresa'], 'alterou', 'empresas', "teste de escopo {$tok}", CE_IP]);

        $d[$letra] = $x;
    }

    // Fotos de verdade quando a pasta aceita escrita (rodando como www-data):
    // aí o controle prova que /foto serve, e o 404 de B passa a valer.
    $pasta = (string) $cfg['caminhos']['fotos'];
    if (is_dir($pasta) && is_writable($pasta) && function_exists('imagewebp')) {
        $img = imagecreatetruecolor(8, 8);
        foreach ([['usuarios', $d['A']['prestador']], ['usuarios', $d['B']['gestor']], ['empresas', $d['B']['empresa']]] as [$tabela, $id]) {
            $rel = 'teste-escopo-' . $tabela . '-' . $id . '.png';
            if (@imagepng($img, $pasta . '/' . $rel)) {
                $fotosCriadas[] = $pasta . '/' . $rel;
                $pdo->prepare("UPDATE {$tabela} SET foto = ? WHERE id = ?")->execute([$rel, $id]);
                foreach ([32, 64, 128, 256] as $tam) {
                    $fotosCriadas[] = $cfg['caminhos']['tmp'] . '/miniaturas/' . md5($rel . '|' . $tam . '|' . filemtime($pasta . '/' . $rel)) . '.webp';
                }
            }
        }
        $d['fotos'] = count($fotosCriadas) > 0;
    }

    $B = $d['B'];
    $d['idsB'] = ['usuário' => $B['gestor'], 'destino' => $B['empresa']];
    return $d;
}

/** Retrato das linhas de um conjunto, para conferir no fim que nada mudou. */
function ce_retrato(PDO $pdo, array $d, string $qual): string
{
    $linhas = [];
    $pega = function (string $sql, array $args) use ($pdo, &$linhas): void {
        $q = $pdo->prepare($sql);
        $q->execute($args);
        $linhas[] = $q->fetchAll();
    };
    if ($qual === 'B') {
        $B = $d['B'];
        $pega('SELECT * FROM empresas WHERE id = ?', [$B['empresa']]);
        $pega('SELECT * FROM usuario_empresa WHERE empresa_id = ? ORDER BY id', [$B['empresa']]);
        $pega('SELECT id, nome, email, ativo, versao_sessao, duplo_fator_confirmado, duplo_fator_segredo FROM usuarios WHERE id IN (?, ?) ORDER BY id', [$B['gestor'], $B['prestador']]);
    }
    return (string) json_encode($linhas);
}

function ce_compararRetrato(string $rotulo, string $antes, string $depois): void
{
    ce_registrar($antes === $depois, $rotulo . ($antes === $depois ? '' : ': ALGO MUDOU no banco'));
}

/**
 * Apaga tudo o que for de teste e devolve quantas linhas saíram e o que
 * sobrou. Reconhece o teste pelo slug dos destinos e pelo e-mail .invalid,
 * e segue as chaves estrangeiras do banco a partir deles, então tabela nova
 * (das fichas do inventário, por exemplo) entra sem mudar este script. Linha de pessoa ou
 * destino de verdade que só aponta para o teste (empresa_padrao_id de quem
 * entrou no destino de teste) perde a referência, nunca é apagada.
 */
function ce_limpar(PDO $pdo): array
{
    $ids = ['empresas' => [], 'usuarios' => []];
    $q = $pdo->prepare("SELECT id FROM empresas WHERE slug IN (?, ?) AND nome LIKE 'Destino Teste Escopo %'");
    $q->execute([CE_SLUG_A, CE_SLUG_B]);
    $ids['empresas'] = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    $q = $pdo->prepare('SELECT id FROM usuarios WHERE email LIKE ?');
    $q->execute(['teste-escopo-%' . CE_DOMINIO_EMAIL]);
    $ids['usuarios'] = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    $raizes = ['empresas', 'usuarios'];

    // Quem aponta para quem: as chaves estrangeiras, mais as colunas
    // empresa_id e usuario_id sem chave (auditoria, jobs, ajustes).
    $ligacoes = [];
    foreach ($pdo->query("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
                          WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND REFERENCED_COLUMN_NAME = 'id'") as $l) {
        $ligacoes[$l['TABLE_NAME'] . '.' . $l['COLUMN_NAME']] = [$l['TABLE_NAME'], $l['COLUMN_NAME'], $l['REFERENCED_TABLE_NAME']];
    }
    foreach ($pdo->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN ('empresa_id', 'usuario_id')") as $l) {
        $ref = $l['COLUMN_NAME'] === 'empresa_id' ? 'empresas' : 'usuarios';
        $ligacoes[$l['TABLE_NAME'] . '.' . $l['COLUMN_NAME']] ??= [$l['TABLE_NAME'], $l['COLUMN_NAME'], $ref];
    }
    $temId = [];
    foreach ($pdo->query("SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'id'") as $l) {
        $temId[$l['TABLE_NAME']] = true;
    }

    // Fecha o conjunto: filho de linha de teste é de teste. Pessoa e destino
    // só entram pelo e-mail e pelo slug, nunca por referência.
    $condicoes = [];
    $anular = [];
    do {
        $mudou = false;
        foreach ($ligacoes as [$tabela, $coluna, $ref]) {
            if (empty($ids[$ref])) {
                continue;
            }
            if (in_array($tabela, $raizes, true)) {
                $anular["{$tabela}.{$coluna}"] = [$tabela, $coluna, $ref];
                continue;
            }
            $condicoes[$tabela]["{$coluna}>{$ref}"] = [$coluna, $ref];
            if (isset($temId[$tabela])) {
                $marcas = implode(',', array_fill(0, count($ids[$ref]), '?'));
                $q = $pdo->prepare("SELECT id FROM `{$tabela}` WHERE `{$coluna}` IN ({$marcas})");
                $q->execute($ids[$ref]);
                $novos = array_diff(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)), $ids[$tabela] ?? []);
                if ($novos) {
                    $ids[$tabela] = array_merge($ids[$tabela] ?? [], $novos);
                    $mudou = true;
                }
            }
        }
    } while ($mudou);

    $onde = function (string $tabela) use (&$condicoes, &$ids, $raizes): array {
        $partes = [];
        $args = [];
        if (in_array($tabela, $raizes, true) || isset($ids[$tabela])) {
            if (!empty($ids[$tabela])) {
                $partes[] = 'id IN (' . implode(',', array_fill(0, count($ids[$tabela]), '?')) . ')';
                array_push($args, ...$ids[$tabela]);
            }
        }
        foreach ($condicoes[$tabela] ?? [] as [$coluna, $ref]) {
            if (!empty($ids[$ref])) {
                $partes[] = "`{$coluna}` IN (" . implode(',', array_fill(0, count($ids[$ref]), '?')) . ')';
                array_push($args, ...$ids[$ref]);
            }
        }
        return [$partes ? '(' . implode(' OR ', $partes) . ')' : '1 = 0', $args];
    };

    $apagadas = 0;
    // Referência de dado de verdade para o teste vira nula (só onde aceita nulo).
    foreach ($anular as [$tabela, $coluna, $ref]) {
        $marcas = implode(',', array_fill(0, count($ids[$ref]), '?'));
        $fora = !empty($ids[$tabela]) ? ' AND id NOT IN (' . implode(',', array_fill(0, count($ids[$tabela]), '?')) . ')' : '';
        try {
            $pdo->prepare("UPDATE `{$tabela}` SET `{$coluna}` = NULL WHERE `{$coluna}` IN ({$marcas}){$fora}")
                ->execute([...$ids[$ref], ...($ids[$tabela] ?? [])]);
        } catch (Throwable) {
            // Coluna obrigatória: fica como sobra e aparece no relatório.
        }
    }
    // Apaga em várias passadas: a ordem das chaves estrangeiras se resolve
    // sozinha (o que falhou por ter filho sai na passada seguinte).
    $tabelas = array_unique(array_merge(array_keys($condicoes), array_keys(array_filter($ids)), $raizes));
    $pendentes = $tabelas;
    for ($passada = 0; $passada < 12 && $pendentes; $passada++) {
        $falhou = [];
        foreach ($pendentes as $tabela) {
            [$w, $args] = $onde($tabela);
            if ($w === '1 = 0') {
                continue;
            }
            try {
                $s = $pdo->prepare("DELETE FROM `{$tabela}` WHERE {$w}");
                $s->execute($args);
                $apagadas += $s->rowCount();
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $falhou[] = $tabela;
            }
        }
        $pendentes = $falhou;
    }
    // O que não tem chave com o teste: tentativas de entrada pelos e-mails
    // de teste e contadores de limite das pessoas e do IP de teste.
    $s = $pdo->prepare('DELETE FROM tentativas_login WHERE email LIKE ? OR ip = ?');
    $s->execute(['teste-escopo-%' . CE_DOMINIO_EMAIL, CE_IP]);
    $apagadas += $s->rowCount();
    $chavesLimite = ['%:ip:' . CE_IP];
    foreach ($ids['usuarios'] as $uid) {
        $chavesLimite[] = '%:u:' . $uid;
    }
    foreach ($chavesLimite as $padrao) {
        $s = $pdo->prepare('DELETE FROM limites WHERE chave LIKE ?');
        $s->execute([$padrao]);
        $apagadas += $s->rowCount();
    }

    // Conferência: nada pode ter ficado.
    $sobras = [];
    foreach ($tabelas as $tabela) {
        [$w, $args] = $onde($tabela);
        $q = $pdo->prepare("SELECT COUNT(*) FROM `{$tabela}` WHERE {$w}");
        $q->execute($args);
        if ($n = (int) $q->fetchColumn()) {
            $sobras[] = "{$n} linha(s) em {$tabela}";
        }
    }
    foreach ($chavesLimite as $padrao) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM limites WHERE chave LIKE ?');
        $q->execute([$padrao]);
        if ($n = (int) $q->fetchColumn()) {
            $sobras[] = "{$n} linha(s) em limites ({$padrao})";
        }
    }
    $q = $pdo->prepare('SELECT (SELECT COUNT(*) FROM empresas WHERE slug IN (?, ?)) + (SELECT COUNT(*) FROM usuarios WHERE email LIKE ?)');
    $q->execute([CE_SLUG_A, CE_SLUG_B, 'teste-escopo-%' . CE_DOMINIO_EMAIL]);
    if ($n = (int) $q->fetchColumn()) {
        $sobras[] = "{$n} destino(s) ou pessoa(s) de teste";
    }
    return ['apagadas' => $apagadas, 'sobras' => $sobras];
}
