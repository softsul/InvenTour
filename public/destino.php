<?php
/**
 * Ficha de um destino.
 * Softsul Software
 *
 * O endereço é um só, /configuracoes/destino/12-pato-branco (endereco()
 * põe as telas de configuração nesse prefixo), e quem muda é o modo:
 * - equipe Softsul: visão da plataforma, cadastra e altera tudo, inclusive
 *   slug, domínio, tipo, situação e a licença (modo de operação e
 *   consultor), e cria destino novo em /configuracoes/destino; projeto de
 *   consultoria sem subdomínio ganha o provisório proj-<id>;
 * - gestor ou consultor, dentro de um destino: edita os dados do próprio
 *   destino (identidade, governança do turismo, endereço, contato, cor, logo
 *   e segundo fator), sem mexer em slug, domínio, tipo, licença e ativo, que
 *   são da plataforma porque mudam o endereço do portal, o acesso e o
 *   contrato. Outro id responde 404.
 *
 * No molde da ficha de cliente do Orbit: o CNPJ busca na Receita e preenche o
 * que estiver vazio, o CEP preenche o endereço, e o Salvar fica no cabeçalho
 * que acompanha a rolagem. Destino não é apagado, é inativado: as fichas
 * do inventário e os usuários apontam para ele.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Fila.php';
require_once __DIR__ . '/../app/Cnpj.php';
require_once __DIR__ . '/../app/Foto.php';
require_once __DIR__ . '/../app/Imagem.php';
require_once __DIR__ . '/../app/Tenant.php';

const TIPOS_DE_DESTINO = ['municipio' => 'Município', 'regiao' => 'Região turística', 'estado' => 'Estado'];
// O ciclo comercial do destino (docs/PLANEJAMENTO.md, 2.2).
const MODOS_DE_OPERACAO = [
    'projeto'   => 'Projeto de consultoria',
    'avaliacao' => 'Avaliação (30 dias)',
    'ativo'     => 'Licenciado',
    'suspenso'  => 'Suspenso',
];
const CUSTODIAS = [
    'consultoria'   => 'Consultoria (fase de projeto)',
    'prefeitura'    => 'Prefeitura',
    'comtur'        => 'COMTUR',
    'compartilhada' => 'Compartilhada (prefeitura e COMTUR)',
];
// Subdomínios que já têm dono (o painel, o site, o e-mail) ou que confundiriam.
const SLUGS_RESERVADOS = ['app', 'www', 'api', 'portal', 'mail', 'smtp', 'imap', 'cdn', 'static', 'admin', 'inventour', 'softsul', 'zapping'];

Auth::exigirLogin();
// Modo "meu destino": quem não é da Softsul só alcança o destino em que está.
// A equipe Softsul dentro de um destino, abrindo a ficha dele (Configurações,
// Dados do destino), também: vale a permissão de Configurações do destino, e
// não a de Destinos, que é da plataforma.
$proprio = !Auth::ehSoftsul() || (Empresa::id() && (int) ($_GET['id'] ?? 0) === Empresa::id());

if ($proprio) {
    $atualDestino = Empresa::exigir();
    Auth::exigirPermissao('configuracoes.ver');
    $id = (int) ($_GET['id'] ?? 0);
    // Outro id responde 404 sem contar se existe: o número de um destino
    // alheio não vira informação.
    if ($id && $id !== (int) $atualDestino['id']) {
        negarRegistro();
    }
    $id = (int) $atualDestino['id'];
    $novo = false;
    $podeEditar = Auth::pode('configuracoes.editar');
} else {
    $id = (int) ($_GET['id'] ?? 0);
    $novo = $id === 0;
    // Destinos é da visão da plataforma: de dentro de um destino, a tela
    // pergunta antes de trocar.
    exigirVisaoPlataforma();
    Auth::exigirPermissao($novo ? 'destinos.criar' : 'destinos.ver');
    $podeEditar = Auth::pode($novo ? 'destinos.criar' : 'destinos.editar');
}
$pdo = Database::conexao();
$cfg = require __DIR__ . '/../app/config.php';
$dominioPortal = (string) ($cfg['app']['dominio_portal'] ?? 'inventour.com.br');

$d = ['id' => 0, 'nome' => '', 'nome_fantasia' => '', 'tipo' => 'municipio', 'cnpj' => '', 'slug' => '', 'dominio_proprio' => '',
      'email' => '', 'telefone' => '', 'whatsapp' => '', 'cep' => '', 'logradouro' => '', 'numero' => '', 'complemento' => '',
      'bairro' => '', 'cidade' => '', 'uf' => '', 'municipio_id' => null, 'cor_acento' => '', 'logo' => '', 'logo_no_portal' => 1, 'foto' => '',
      'exigir_2fa_equipe' => 1, 'ativo' => 1, 'modo_operacao' => 'projeto', 'avaliacao_ate' => null, 'consultor_id' => null];
if (!$novo) {
    $q = $pdo->prepare('SELECT * FROM empresas WHERE id = ?');
    $q->execute([$id]);
    $d = $q->fetch() ?: negarRegistro();
}
$atual = $d;
$erro = '';

// Governança (1:1 com o destino): órgão de turismo, COMTUR, FUMTUR, custódia.
$g = ['orgao_turismo_nome' => '', 'orgao_turismo_lei' => '', 'comtur_nome' => '', 'comtur_lei' => '', 'comtur_ativo' => 0,
      'fumtur_lei' => '', 'plano_turismo_lei' => '', 'custodia' => 'consultoria', 'presidente_comtur_id' => null];
if (!$novo) {
    $q = $pdo->prepare('SELECT * FROM destino_governanca WHERE empresa_id = ?');
    $q->execute([$id]);
    $g = ($q->fetch() ?: []) + $g;
}
$governancaAtual = $g;
// As pessoas do destino, para escolher o consultor e o presidente do COMTUR.
$pessoas = [];
$consultores = [];
if (!$novo) {
    $q = $pdo->prepare("SELECT u.id, u.nome, MAX(p.chave = 'consultor') AS consultor
                        FROM usuario_empresa ue JOIN usuarios u ON u.id = ue.usuario_id AND u.ativo = 1
                        LEFT JOIN usuario_empresa_papel uep ON uep.usuario_id = ue.usuario_id AND uep.empresa_id = ue.empresa_id
                        LEFT JOIN papeis p ON p.id = uep.papel_id
                        WHERE ue.empresa_id = ? AND ue.ativo = 1 GROUP BY u.id, u.nome ORDER BY u.nome");
    $q->execute([$id]);
    foreach ($q->fetchAll() as $p) {
        $pessoas[(int) $p['id']] = (string) $p['nome'];
        if ((int) $p['consultor']) {
            $consultores[(int) $p['id']] = (string) $p['nome'];
        }
    }
}

/**
 * Guarda o logo enviado em storage/midias/marcas/, padronizado pela Imagem
 * (nunca o arquivo original: a imagem é redesenhada, sem metadados). Devolve
 * o nome do arquivo gravado em empresas.logo (só o nome: é assim que
 * Marca::doDestino monta o endereço /midia/marcas/...), ou null com o erro
 * em $erro.
 */
$guardarLogo = function (int $empresaId, array $arquivo, string &$erro) use ($cfg): ?string {
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
        $erro = 'O logo não chegou inteiro. Tente de novo.';
        return null;
    }
    if ($arquivo['size'] > 10 * 1048576) {
        $erro = 'O logo passa de 10 MB. Escolha um arquivo menor.';
        return null;
    }
    $dados = (string) file_get_contents($arquivo['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($dados);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $erro = 'Use um logo em PNG, JPG ou WebP.';
        return null;
    }
    // Recortado na margem vazia, no formato do desenho e em PNG, para a
    // transparência do brasão sobreviver no portal escuro (Imagem::logo).
    $png = Imagem::logo(Imagem::recortar($dados, Imagem::recorteDoFormulario('recortes_logo')));
    if ($png === null) {
        $erro = 'O arquivo não é uma imagem válida.';
        return null;
    }
    $pasta = rtrim((string) $cfg['caminhos']['midias'], '/') . '/marcas';
    if (!is_dir($pasta)) {
        @mkdir($pasta, 0770, true);
    }
    foreach (glob($pasta . '/' . $empresaId . '-*') ?: [] as $velho) {
        @unlink($velho);
    }
    // O nome leva a hora para o portal não mostrar o logo antigo do cache.
    $nome = $empresaId . '-' . time() . '.png';
    if (@file_put_contents($pasta . '/' . $nome, $png) === false) {
        $erro = 'Não consegui guardar o logo agora.';
        return null;
    }
    return $nome;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? 'salvar');
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null)) {
        $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!$podeEditar) {
        $erro = 'Você não pode alterar este destino.';
    } elseif (!$proprio && !$novo && in_array($acao, ['inativar', 'ativar'], true)) {
        if (!Auth::pode('destinos.excluir')) {
            $erro = 'Você não pode inativar ou reativar destinos.';
        } else {
            $ativo = $acao === 'ativar' ? 1 : 0;
            // Inativar sobe a versao_sessao de todo mundo vinculado: derruba
            // na hora quem está dentro do destino.
            $ativo ? Empresa::ativar($id) : Empresa::inativar($id);
            Auditoria::registrar('alterou', 'empresas', $id, ($ativo ? 'reativou' : 'inativou') . ' o destino ' . $d['nome_fantasia'],
                ['ativo' => (int) $d['ativo']], ['ativo' => $ativo], $id);
            avisar($ativo ? $d['nome_fantasia'] . ' reativado.' : $d['nome_fantasia'] . ' inativado. A equipe dele perdeu o acesso na hora e o portal saiu do ar.', $ativo ? 'bom' : 'atencao');
            fichaSalva(endereco('destino', $id, $d['nome_fantasia']));
        }
    } elseif ($acao === 'remover_logo' && !$novo) {
        foreach (glob(rtrim((string) $cfg['caminhos']['midias'], '/') . '/marcas/' . $id . '-*') ?: [] as $velho) {
            @unlink($velho);
        }
        $pdo->prepare('UPDATE empresas SET logo = NULL, atualizado_em = NOW() WHERE id = ?')->execute([$id]);
        Auditoria::registrar('alterou', 'empresas', $id, 'removeu o logo do destino', null, null, $id);
        Tenant::esquecerHosts($d);
        avisar('Logo excluído. O portal volta a usar a marca do InvenTour.');
        fichaSalva(endereco('destino', $id, $d['nome_fantasia']));
    } else {
        $campos = [
            'nome'              => trim((string) ($_POST['razao_social'] ?? '')),
            'nome_fantasia'     => trim((string) ($_POST['nome_fantasia'] ?? '')),
            'cnpj'              => Cnpj::normalizar((string) ($_POST['cnpj'] ?? '')) ?: null,
            'email'             => mb_strtolower(trim((string) ($_POST['email'] ?? ''))) ?: null,
            'telefone'          => telefoneParaBanco($_POST['telefone'] ?? null),
            'whatsapp'          => telefoneParaBanco($_POST['whatsapp'] ?? null),
            'cep'               => soDigitos($_POST['cep'] ?? '') ?: null,
            'logradouro'        => trim((string) ($_POST['logradouro'] ?? '')) ?: null,
            'numero'            => trim((string) ($_POST['numero'] ?? '')) ?: null,
            'complemento'       => trim((string) ($_POST['complemento'] ?? '')) ?: null,
            'bairro'            => trim((string) ($_POST['bairro'] ?? '')) ?: null,
            'logo_no_portal'    => empty($_POST['ocultar_logo']) ? 1 : 0,
            'cor_acento'        => empty($_POST['sem_cor']) ? strtoupper(trim((string) ($_POST['cor_acento'] ?? ''))) : null,
            'exigir_2fa_equipe' => empty($_POST['exigir_2fa_equipe']) ? 0 : 1,
        ];
        if ($campos['cor_acento'] === '') {
            $campos['cor_acento'] = null;
        }
        // Slug, domínio e tipo são da plataforma: dentro do destino o gestor
        // não os altera, e o que vier no POST é ignorado.
        if (!$proprio) {
            $campos['tipo'] = array_key_exists((string) ($_POST['tipo'] ?? ''), TIPOS_DE_DESTINO) ? (string) $_POST['tipo'] : 'municipio';
            $campos['slug'] = mb_strtolower(trim((string) ($_POST['slug'] ?? ''))) ?: apelido((string) ($_POST['nome_fantasia'] ?? ''));
            $campos['dominio_proprio'] = mb_strtolower(trim((string) ($_POST['dominio_proprio'] ?? ''))) ?: null;
            // O modo de operação e o consultor são da plataforma: é a licença.
            $campos['modo_operacao'] = array_key_exists((string) ($_POST['modo_operacao'] ?? ''), MODOS_DE_OPERACAO) ? (string) $_POST['modo_operacao'] : 'projeto';
            $ate = (string) ($_POST['avaliacao_ate'] ?? '');
            $campos['avaliacao_ate'] = $campos['modo_operacao'] === 'avaliacao'
                ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate) ? $ate : date('Y-m-d', strtotime('+30 days'))) : null;
            $consultor = (int) ($_POST['consultor_id'] ?? 0);
            $campos['consultor_id'] = isset($consultores[$consultor]) ? $consultor : null;
            // Projeto de consultoria novo sem subdomínio ganha um provisório
            // (proj-<id>), acertado depois de gravar.
            if ($novo && $campos['modo_operacao'] === 'projeto' && trim((string) ($_POST['slug'] ?? '')) === '') {
                $campos['slug'] = 'proj-novo-' . bin2hex(random_bytes(3));
            }
        }
        $slugConferir = $proprio ? (string) $d['slug'] : (string) $campos['slug'];
        $dominioConferir = $proprio ? $d['dominio_proprio'] : $campos['dominio_proprio'];

        // O município sede é escolhido na lista do IBGE e gravado pelo
        // código. Se a pessoa não escolheu mas consultou o CNPJ, vale o
        // código que a Receita devolveu (campo oculto codigo_ibge). Cidade e
        // UF continuam gravadas, copiadas do município, porque a troca de
        // destino, a busca e a lista de destinos ainda as leem.
        $municipioEscolhido = (int) soDigitos((string) ($_POST['municipio_id'] ?? '')) ?: (int) soDigitos((string) ($_POST['codigo_ibge'] ?? ''));
        $m = $pdo->prepare('SELECT m.id, m.nome, e.uf FROM municipios m JOIN estados e ON e.id = m.estado_id WHERE m.id = ?');
        $m->execute([$municipioEscolhido]);
        $municipio = $m->fetch() ?: null;
        $campos['municipio_id'] = $municipio ? (int) $municipio['id'] : null;
        $campos['cidade'] = $municipio['nome'] ?? null;
        $campos['uf'] = $municipio['uf'] ?? null;

        $lei = fn (string $c): ?string => mb_substr(trim((string) ($_POST[$c] ?? '')), 0, 120) ?: null;
        $gov = [
            'orgao_turismo_nome'   => mb_substr(trim((string) ($_POST['orgao_turismo_nome'] ?? '')), 0, 150) ?: null,
            'orgao_turismo_lei'    => $lei('orgao_turismo_lei'),
            'comtur_nome'          => mb_substr(trim((string) ($_POST['comtur_nome'] ?? '')), 0, 150) ?: null,
            'comtur_lei'           => $lei('comtur_lei'),
            'comtur_ativo'         => empty($_POST['comtur_ativo']) ? 0 : 1,
            'fumtur_lei'           => $lei('fumtur_lei'),
            'plano_turismo_lei'    => $lei('plano_turismo_lei'),
            'custodia'             => array_key_exists((string) ($_POST['custodia'] ?? ''), CUSTODIAS) ? (string) $_POST['custodia'] : (string) $g['custodia'],
            'presidente_comtur_id' => isset($pessoas[(int) ($_POST['presidente_comtur_id'] ?? 0)]) ? (int) $_POST['presidente_comtur_id'] : null,
        ];
        $modoNovo = $campos['modo_operacao'] ?? (string) $d['modo_operacao'];

        if ($campos['nome'] === '' || $campos['nome_fantasia'] === '') {
            $erro = 'Nome institucional e nome no portal são obrigatórios.';
        } elseif ($campos['cnpj'] !== null && !Cnpj::valido($campos['cnpj'])) {
            $erro = 'CNPJ inválido. Confira os dígitos.';
        } elseif (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?$/', $slugConferir)) {
            $erro = 'O subdomínio só aceita letras minúsculas, números e hífen, sem começar nem terminar com hífen.';
        } elseif (in_array($slugConferir, SLUGS_RESERVADOS, true)) {
            $erro = 'Este subdomínio é reservado. Escolha outro.';
        } elseif ($dominioConferir !== null && !preg_match('/^(?=.{4,150}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $dominioConferir)) {
            $erro = 'Domínio próprio inválido. Use só o nome, por exemplo turismo.cidade.pr.gov.br.';
        } elseif ($campos['email'] !== null && (!filter_var($campos['email'], FILTER_VALIDATE_EMAIL) || !emailDominioRecebe($campos['email']))) {
            $erro = 'Este e-mail não recebe mensagens. Confira o domínio depois do @.';
        } elseif (trim((string) ($_POST['telefone'] ?? '')) !== '' && $campos['telefone'] === null) {
            $erro = 'Telefone incompleto. Use DDD e número.';
        } elseif (trim((string) ($_POST['whatsapp'] ?? '')) !== '' && $campos['whatsapp'] === null) {
            $erro = 'WhatsApp incompleto. Use DDD e número.';
        } elseif ($campos['municipio_id'] === null) {
            $erro = 'Escolha o município sede na lista.';
        } elseif ($campos['cep'] !== null && strlen($campos['cep']) !== 8) {
            $erro = 'CEP com oito dígitos.';
        } elseif ($campos['cor_acento'] !== null && !preg_match('/^#[0-9A-F]{6}$/', $campos['cor_acento'])) {
            $erro = 'A cor de acento precisa estar no formato #RRGGBB.';
        } elseif ($gov['custodia'] !== 'consultoria' && $modoNovo === 'projeto') {
            $erro = 'Na fase de projeto a custódia é da consultoria. Ela passa à prefeitura ou ao COMTUR com a licença.';
        } else {
            // Um município, um destino licenciado: dois projetos de
            // consultoria convivem, mas só um vira o portal oficial.
            if ($modoNovo === 'ativo' && ($campos['tipo'] ?? $d['tipo']) === 'municipio') {
                $outroAtivo = $pdo->prepare("SELECT nome_fantasia FROM empresas WHERE municipio_id = ? AND tipo = 'municipio'
                                             AND modo_operacao = 'ativo' AND ativo = 1 AND id <> ?");
                $outroAtivo->execute([$campos['municipio_id'], $id]);
                if ($nomeOutro = $outroAtivo->fetchColumn()) {
                    $erro = 'O município já tem um destino licenciado: ' . $nomeOutro . '. Suspenda aquele antes de licenciar este.';
                }
            }
        }
        if ($erro === '') {
            $dup = $pdo->prepare('SELECT nome_fantasia FROM empresas WHERE (slug = ? OR (cnpj IS NOT NULL AND cnpj = ?)
                                  OR (dominio_proprio IS NOT NULL AND dominio_proprio = ?)) AND id <> ?');
            $dup->execute([$slugConferir, $campos['cnpj'], $dominioConferir, $id]);
            if ($outro = $dup->fetchColumn()) {
                $erro = 'O destino ' . $outro . ' já usa este CNPJ, este subdomínio ou este domínio.';
            }
        }
        if ($erro === '') {
            $nomes = array_keys($campos);
            if ($novo) {
                $pdo->prepare('INSERT INTO empresas (' . implode(', ', $nomes) . ', ativo, criado_em) VALUES ('
                    . str_repeat('?, ', count($nomes)) . '1, NOW())')->execute(array_values($campos));
                $id = (int) $pdo->lastInsertId();
                if (str_starts_with((string) $campos['slug'], 'proj-novo-')) {
                    $campos['slug'] = $slugConferir = 'proj-' . $id;
                    $pdo->prepare('UPDATE empresas SET slug = ? WHERE id = ?')->execute([$campos['slug'], $id]);
                }
                Auditoria::registrar('criou', 'empresas', $id, 'criou o destino ' . $campos['nome_fantasia'], null, $campos, $id);
            } else {
                $pdo->prepare('UPDATE empresas SET ' . implode(' = ?, ', $nomes) . ' = ?, atualizado_em = NOW() WHERE id = ?')
                    ->execute([...array_values($campos), $id]);
                Auditoria::registrar('alterou', 'empresas', $id, 'alterou o destino ' . $campos['nome_fantasia'],
                    array_intersect_key($atual, $campos), $campos, $id);
            }
            // A governança vai junto, e a custódia é auditada à parte: é ela que
            // decide quem destrava o destino numa troca de gestão.
            $pdo->prepare('INSERT INTO destino_governanca (empresa_id, ' . implode(', ', array_keys($gov)) . ', atualizado_em, atualizado_por)
                           VALUES (?' . str_repeat(', ?', count($gov)) . ', NOW(), ?)
                           ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn ($c) => "$c = VALUES($c)", array_keys($gov))) . ',
                           atualizado_em = NOW(), atualizado_por = VALUES(atualizado_por)')
                ->execute([$id, ...array_values($gov), (int) Auth::usuarioOuNulo()['id']]);
            if (!$novo && array_diff_assoc(array_map('strval', $gov), array_map('strval', array_intersect_key($governancaAtual, $gov)))) {
                Auditoria::registrar('alterou', 'destino_governanca', $id, 'alterou a governança de ' . $campos['nome_fantasia'],
                    array_intersect_key($governancaAtual, $gov), $gov, $id);
            }
            $recado = '';
            if (($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $falha = '';
                $logo = $guardarLogo($id, $_FILES['logo'], $falha);
                if ($logo !== null) {
                    $pdo->prepare('UPDATE empresas SET logo = ? WHERE id = ?')->execute([$logo, $id]);
                    $recado = ' Logo atualizado.';
                } else {
                    $recado = ' ' . $falha;
                }
            }
            avisar(($novo ? 'Destino ' . $campos['nome_fantasia'] . ' cadastrado.' : 'Cadastro de ' . $campos['nome_fantasia'] . ' salvo.') . $recado,
                str_contains($recado, 'atualizado') || $recado === '' ? 'bom' : 'atencao');
            Tenant::esquecerHosts(['slug' => $slugConferir, 'dominio_proprio' => $dominioConferir]);
            fichaSalva(endereco('destino', $id, $campos['nome_fantasia']));
        }
        // O que foi digitado volta para a tela.
        $g = array_merge($g, $gov);
        $d = array_merge($d, $campos, ['telefone' => $_POST['telefone'] ?? '', 'whatsapp' => $_POST['whatsapp'] ?? '',
            'cep' => $_POST['cep'] ?? '', 'cnpj' => $_POST['cnpj'] ?? '']);
    }
}

if (!$novo && !emPopup()) {
    canonizar('destino', $id, $d['nome_fantasia']);
}

$titulo = $novo ? 'Novo destino' : ($proprio ? 'Dados do destino' : $d['nome_fantasia']);
// O rótulo do município escolhido, para o campo de busca abrir preenchido.
$municipioTexto = '';
if (!empty($d['municipio_id'])) {
    $m = $pdo->prepare('SELECT m.nome, e.uf FROM municipios m JOIN estados e ON e.id = m.estado_id WHERE m.id = ?');
    $m->execute([(int) $d['municipio_id']]);
    $municipioTexto = ($mm = $m->fetch()) ? $mm['nome'] . '/' . $mm['uf'] : '';
}
paginaFicha($titulo, $proprio ? 'configuracoes' : 'destinos');
$ro = $podeEditar ? '' : ' readonly';
$travado = $proprio || !$podeEditar ? ' readonly' : '';
$acaoForm = endereco('destino', $novo ? null : $id, $novo ? null : $d['nome_fantasia']);
?>
<?php if ($proprio): ?>
  <div class="trilha"><a href="/configuracoes">Configurações</a><?= icone('seta') ?><span>Dados do destino</span></div>
<?php else: ?>
  <div class="trilha"><a href="/destinos">Destinos</a><?= icone('seta') ?><span><?= $novo ? 'Novo' : e($d['nome_fantasia']) ?></span></div>
<?php endif; ?>

<?php if (!$novo && !$d['ativo']): ?>
  <p class="aviso atencao"><?= icone('alerta') ?><span><strong>Este destino está inativo.</strong> Ninguém dele entra no painel e o portal não responde.<?= $proprio ? '' : ' Use Reativar para voltar a operar.' ?></span></p>
<?php endif; ?>
<?php if ($erro !== ''): ?>
  <p class="aviso ruim" role="alert"><?= icone('alerta') ?><span><?= e($erro) ?></span></p>
<?php endif; ?>

<form method="post" action="<?= e($acaoForm) ?>" id="form-destino" enctype="multipart/form-data">
  <?php // Primeiro botão de envio do formulário: é ele que o Enter aciona. Sem ele,
      // o Enter num campo acionava o Inativar, que vem antes do Salvar na tela. ?>
  <button type="submit" class="oculto-visual" name="acao" value="salvar" tabindex="-1" aria-hidden="true">Salvar</button>
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
  <input type="hidden" name="dados_receita" id="dados_receita" value="">
  <input type="hidden" name="codigo_ibge" id="codigo_ibge" value="">
  <?php foreach (['situacao_cadastral', 'natureza_juridica', 'cnae_principal'] as $oculto): ?>
    <input type="hidden" id="<?= $oculto ?>" value="">
  <?php endforeach; ?>

  <div class="cab-pag">
    <div class="titulo-cliente">
      <?= $novo ? avatarIniciais('Novo destino', 'brasao') : Foto::avatarDestino($d + ['id' => $id], 'brasao', 128) ?>
      <div>
        <h1><?= e($titulo) ?></h1>
        <p><?= $novo ? 'Digite o CNPJ da prefeitura ou da entidade e o resto vem da Receita.'
              : e((TIPOS_DE_DESTINO[$d['tipo']] ?? $d['tipo']) . ' · ' . (MODOS_DE_OPERACAO[$d['modo_operacao']] ?? $d['modo_operacao']) . ($d['cidade'] ? ' · ' . $d['cidade'] . '/' . $d['uf'] : '')
                  . ' · ' . ($d['dominio_proprio'] ?: $d['slug'] . '.' . $dominioPortal)) ?></p>
      </div>
    </div>
    <div class="acoes">
      <?php if (!$proprio && !$novo && Auth::pode('usuarios.ver')): ?>
        <button type="submit" class="botao claro" form="form-entrar-destino" name="empresa_id" value="<?= $id ?>"><?= icone('pessoas') ?>Usuários do destino</button>
      <?php endif; ?>
      <?php if ($podeEditar): ?>
        <?php if (!$proprio && !$novo && Auth::pode('destinos.excluir')): ?>
          <?php if ($d['ativo']): ?>
            <button type="submit" class="botao perigo" name="acao" value="inativar" formnovalidate
                    data-confirmar="Inativar <?= e($d['nome_fantasia']) ?>? A equipe dele perde o acesso na hora e o portal sai do ar. O histórico fica preservado e dá para reativar depois."><?= icone('alerta') ?>Inativar</button>
          <?php else: ?>
            <button type="submit" class="botao claro" name="acao" value="ativar" formnovalidate><?= icone('check') ?>Reativar</button>
          <?php endif; ?>
        <?php endif; ?>
        <button type="submit" class="botao"><?= icone('check') ?>Salvar</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="colunas">
    <div class="cartao">
      <h2 class="titulo-secao">Identificação</h2>
      <div class="grade">
        <div class="campo c-6">
          <label for="cnpj">CNPJ</label>
          <div class="entrada">
            <?= icone('predio') ?>
            <input type="text" id="cnpj" name="cnpj" class="cnpj" inputmode="text" maxlength="18" autocomplete="off"
                   value="<?= e($d['cnpj'] ? formatarCnpj((string) $d['cnpj']) : '') ?>"<?= $ro ?>>
            <?php if ($podeEditar): ?>
              <button type="button" class="revelar" id="btn-cnpj" data-checar-duplicado="1" title="Buscar na Receita"><?= icone('busca') ?></button>
            <?php endif; ?>
          </div>
          <span class="ajuda" id="ajuda-cnpj">A busca preenche o nome, o endereço e o contato.</span>
        </div>
        <div class="campo c-6">
          <label for="tipo">Tipo <span class="obrig">obrigatório</span></label>
          <div class="entrada"><select id="tipo" name="tipo"<?= $proprio || !$podeEditar ? ' disabled' : '' ?>>
            <?php foreach (TIPOS_DE_DESTINO as $chave => $rotulo): ?>
              <option value="<?= $chave ?>"<?= $d['tipo'] === $chave ? ' selected' : '' ?>><?= e($rotulo) ?></option>
            <?php endforeach; ?>
          </select></div>
          <span class="ajuda">Município, região turística (IGR, consórcio) ou estado.<?= $proprio ? ' Quem altera é a Softsul.' : '' ?></span>
        </div>
        <div class="campo c-12">
          <label for="razao_social">Nome institucional <span class="obrig">obrigatório</span></label>
          <div class="entrada"><input type="text" id="razao_social" name="razao_social" required maxlength="150"
                 value="<?= e((string) $d['nome']) ?>"<?= $ro ?>></div>
          <span class="ajuda">Por exemplo, Prefeitura Municipal de Pato Branco.</span>
        </div>
        <div class="campo c-12">
          <label for="nome_fantasia">Nome no portal <span class="obrig">obrigatório</span></label>
          <div class="entrada"><input type="text" id="nome_fantasia" name="nome_fantasia" required maxlength="100"
                 value="<?= e((string) $d['nome_fantasia']) ?>"<?= $ro ?>></div>
          <span class="ajuda">É o nome que o público e a equipe veem: Pato Branco.</span>
        </div>
      </div>
    </div>

    <div class="cartao">
      <h2 class="titulo-secao">Licença</h2>
      <div class="grade">
        <div class="campo c-6">
          <label for="modo_operacao">Modo de operação <span class="obrig">obrigatório</span></label>
          <div class="entrada"><select id="modo_operacao" name="modo_operacao"<?= $proprio || !$podeEditar ? ' disabled' : '' ?>>
            <?php foreach (MODOS_DE_OPERACAO as $chave => $rotulo): ?>
              <option value="<?= $chave ?>"<?= $d['modo_operacao'] === $chave ? ' selected' : '' ?>><?= e($rotulo) ?></option>
            <?php endforeach; ?>
          </select></div>
          <span class="ajuda">No projeto de consultoria o portal fica fechado e o caderno sai com a chancela do InvenTour; licenciado, abre o portal, o trade e a ligação com o EventCity.<?= $proprio ? ' Quem altera é a Softsul.' : '' ?></span>
        </div>
        <div class="campo c-6">
          <label for="avaliacao_ate">Avaliação até</label>
          <div class="entrada"><input type="date" id="avaliacao_ate" name="avaliacao_ate" value="<?= e((string) $d['avaliacao_ate']) ?>"<?= $travado ?>></div>
          <span class="ajuda">Só no modo avaliação. Em branco, 30 dias a partir de hoje.</span>
        </div>
        <div class="campo c-12">
          <label for="consultor_id">Consultor responsável</label>
          <?php if ($consultores): ?>
            <div class="entrada"><select id="consultor_id" name="consultor_id"<?= $proprio || !$podeEditar ? ' disabled' : '' ?>>
              <option value="">Nenhum</option>
              <?php foreach ($consultores as $uid => $nomeConsultor): ?>
                <option value="<?= $uid ?>"<?= (int) $d['consultor_id'] === $uid ? ' selected' : '' ?>><?= e($nomeConsultor) ?></option>
              <?php endforeach; ?>
            </select></div>
          <?php else: ?>
            <p class="ajuda">Nenhuma pessoa com o papel Consultor neste destino. Convide o turismólogo em Usuários e escolha aqui.</p>
          <?php endif; ?>
          <span class="ajuda">O turismólogo ou a consultoria dona do projeto de inventário.</span>
        </div>
      </div>
    </div>

    <div class="cartao">
      <h2 class="titulo-secao">Governança do turismo</h2>
      <div class="grade">
        <div class="campo c-8">
          <label for="orgao_turismo_nome">Órgão oficial de turismo</label>
          <div class="entrada"><input type="text" id="orgao_turismo_nome" name="orgao_turismo_nome" maxlength="150" value="<?= e((string) $g['orgao_turismo_nome']) ?>"<?= $ro ?>></div>
          <span class="ajuda">Secretaria, departamento ou divisão que responde pelo turismo.</span>
        </div>
        <div class="campo c-4">
          <label for="orgao_turismo_lei">Lei de criação</label>
          <div class="entrada"><input type="text" id="orgao_turismo_lei" name="orgao_turismo_lei" maxlength="120" placeholder="Lei nº 0.000/0000" value="<?= e((string) $g['orgao_turismo_lei']) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-8">
          <label for="comtur_nome">Conselho Municipal de Turismo</label>
          <div class="entrada"><input type="text" id="comtur_nome" name="comtur_nome" maxlength="150" value="<?= e((string) $g['comtur_nome']) ?>"<?= $ro ?>></div>
          <label class="check"><input type="checkbox" name="comtur_ativo" value="1"<?= (int) $g['comtur_ativo'] ? ' checked' : '' ?><?= $podeEditar ? '' : ' disabled' ?>><i></i>Em funcionamento, com reuniões regulares</label>
        </div>
        <div class="campo c-4">
          <label for="comtur_lei">Lei do COMTUR</label>
          <div class="entrada"><input type="text" id="comtur_lei" name="comtur_lei" maxlength="120" placeholder="Lei nº 0.000/0000" value="<?= e((string) $g['comtur_lei']) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="fumtur_lei">Lei do Fundo Municipal de Turismo</label>
          <div class="entrada"><input type="text" id="fumtur_lei" name="fumtur_lei" maxlength="120" value="<?= e((string) $g['fumtur_lei']) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="plano_turismo_lei">Plano ou política municipal de turismo</label>
          <div class="entrada"><input type="text" id="plano_turismo_lei" name="plano_turismo_lei" maxlength="120" value="<?= e((string) $g['plano_turismo_lei']) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="custodia">Custódia dos dados</label>
          <div class="entrada"><select id="custodia" name="custodia"<?= $podeEditar ? '' : ' disabled' ?>>
            <?php foreach (CUSTODIAS as $chave => $rotulo): ?>
              <option value="<?= $chave ?>"<?= $g['custodia'] === $chave ? ' selected' : '' ?>><?= e($rotulo) ?></option>
            <?php endforeach; ?>
          </select></div>
          <span class="ajuda">Com o COMTUR na custódia, apagar fichas homologadas em lote pede a confirmação do gestor e do presidente do conselho: o inventário atravessa a troca de gestão.</span>
        </div>
        <div class="campo c-6">
          <label for="presidente_comtur_id">Presidente do COMTUR</label>
          <?php if ($pessoas): ?>
            <div class="entrada"><select id="presidente_comtur_id" name="presidente_comtur_id"<?= $podeEditar ? '' : ' disabled' ?>>
              <option value="">Não informado</option>
              <?php foreach ($pessoas as $uid => $nomePessoa): ?>
                <option value="<?= $uid ?>"<?= (int) $g['presidente_comtur_id'] === $uid ? ' selected' : '' ?>><?= e($nomePessoa) ?></option>
              <?php endforeach; ?>
            </select></div>
          <?php else: ?>
            <p class="ajuda">Convide o presidente em Usuários e escolha aqui.</p>
          <?php endif; ?>
          <span class="ajuda">Precisa ter acesso ao destino.</span>
        </div>
      </div>
    </div>

    <div class="cartao">
      <h2 class="titulo-secao">Portal público</h2>
      <div class="grade">
        <div class="campo c-6">
          <label for="slug">Subdomínio <span class="obrig">obrigatório</span></label>
          <div class="entrada"><input type="text" id="slug" name="slug" maxlength="60" pattern="[a-z0-9-]+" spellcheck="false"
                 value="<?= e((string) $d['slug']) ?>"<?= $travado ?>></div>
          <span class="ajuda">O portal responde em <b><?= e($d['slug'] ?: 'subdominio') ?>.<?= e($dominioPortal) ?></b>. Só letras minúsculas, números e hífen.<?= $proprio ? ' Quem altera é a Softsul.' : ' Em branco, sai do nome no portal.' ?></span>
        </div>
        <div class="campo c-6">
          <label for="dominio_proprio">Domínio próprio</label>
          <div class="entrada"><input type="text" id="dominio_proprio" name="dominio_proprio" maxlength="150" spellcheck="false" placeholder="turismo.cidade.pr.gov.br"
                 value="<?= e((string) $d['dominio_proprio']) ?>"<?= $travado ?>></div>
          <span class="ajuda">Opcional: um CNAME da prefeitura apontando para o InvenTour.<?= $proprio ? ' Quem altera é a Softsul.' : '' ?></span>
        </div>
        <div class="campo c-6">
          <label for="cor_acento">Cor de acento</label>
          <div class="entrada cor"><input type="color" id="cor_acento" name="cor_acento" value="<?= e($d['cor_acento'] ?: '#0C6C78') ?>" data-cor-inventour="#D4144A"<?= $podeEditar ? '' : ' disabled' ?>></div>
          <label class="check"><input type="checkbox" name="sem_cor" value="1" data-sem-cor<?= $d['cor_acento'] ? '' : ' checked' ?><?= $podeEditar ? '' : ' disabled' ?>><i></i>Usar a cor do InvenTour</label>
          <span class="ajuda">A cor dos botões e dos destaques do portal. Com a caixa marcada, vale a cor padrão.</span>
        </div>
        <div class="campo c-6">
          <label id="rotulo-logo">Logo do destino</label>
          <?php if (!empty($d['logo'])): ?>
            <div class="logo-atual">
              <img src="/midia/marcas/<?= rawurlencode(basename((string) $d['logo'])) ?>" alt="Logo de <?= e($d['nome_fantasia']) ?>" loading="lazy">
              <?php if ($podeEditar): ?>
                <?php /* O formulário do botão fica fora da ficha: form dentro de form é HTML inválido. */ ?>
                <button type="submit" class="botao claro" form="form-remover-logo" formnovalidate
                        data-confirmar="Excluir o logo de <?= e($d['nome_fantasia']) ?>? O portal volta a usar a marca do InvenTour."><?= icone('lixeira') ?>Excluir o logo</button>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($podeEditar): ?>
            <?= zonaDeUpload('logo', 'image/png,image/jpeg,image/webp', 'Arraste o logo aqui', 'ou clique para escolher (PNG, JPG ou WebP; você ajusta o enquadramento em seguida)', false, false, Imagem::RECORTE_LOGO, 'recortes_logo', true) ?>
          <?php endif; ?>
          <label class="check"><input type="checkbox" name="ocultar_logo" value="1"<?= (int) ($d['logo_no_portal'] ?? 1) ? '' : ' checked' ?><?= $podeEditar ? '' : ' disabled' ?>><i></i>Ocultar o logo em tudo</label>
          <?php if ($podeEditar): ?>
            <span class="ajuda">Aparece no cabeçalho do portal, no caderno do inventário, nos cartazes de QR e nas listas. Com a caixa marcada, o logo fica guardado aqui, mas some de tudo. É gravado quando você salvar a ficha.</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="cartao">
      <h2 class="titulo-secao">Endereço</h2>
      <div class="grade">
        <div class="campo c-4">
          <label for="cep">CEP</label>
          <div class="entrada"><input type="text" id="cep" name="cep" inputmode="numeric" maxlength="9" placeholder="00000-000"
                 value="<?= e(formatarCep((string) $d['cep'])) ?>"<?= $ro ?>></div>
          <span class="ajuda">O endereço vem sozinho ao completar os oito dígitos.</span>
        </div>
        <div class="campo c-8">
          <label for="logradouro">Logradouro</label>
          <div class="entrada"><input type="text" id="logradouro" name="logradouro" maxlength="150" value="<?= e((string) $d['logradouro']) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-3">
          <label for="numero">Número</label>
          <div class="entrada"><input type="text" id="numero" name="numero" maxlength="20" value="<?= e((string) $d['numero']) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-4">
          <label for="complemento">Complemento</label>
          <div class="entrada"><input type="text" id="complemento" name="complemento" maxlength="80" value="<?= e((string) $d['complemento']) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-5">
          <label for="bairro">Bairro</label>
          <div class="entrada"><input type="text" id="bairro" name="bairro" maxlength="80" value="<?= e((string) $d['bairro']) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-12">
          <label for="municipio_id_texto">Município sede <span class="obrig">obrigatório</span></label>
          <?php /* Escolha na lista do IBGE: grava o código, e não o nome
                   digitado, para o destino ficar ligado ao mapa do país. O
                   CEP e a consulta de CNPJ escolhem sozinhos (inventour.js). */ ?>
          <?= seletorBuscavel([
              'campo' => 'municipio_id', 'fonte' => '/api/municipios', 'valor' => $d['municipio_id'] ? (string) $d['municipio_id'] : '',
              'texto' => $municipioTexto, 'icone' => 'mapa', 'vazio' => 'Digite o nome ou o código IBGE',
              'nada' => 'Nenhum município com esse nome.', 'oque' => 'o município sede', 'obrigatorio' => true, 'travado' => !$podeEditar,
          ]) ?>
          <span class="ajuda">Da lista do IBGE. O CEP e a consulta do CNPJ já escolhem.</span>
        </div>
      </div>
    </div>

    <div class="cartao">
      <h2 class="titulo-secao">Contato do destino</h2>
      <div class="grade">
        <div class="campo c-12">
          <label for="email">E-mail</label>
          <div class="entrada"><?= icone('emails') ?><input type="email" id="email" name="email" maxlength="150" value="<?= e((string) $d['email']) ?>"<?= $ro ?>></div>
          <span class="ajuda">Aparece no portal como contato do turismo do destino.</span>
        </div>
        <div class="campo c-6">
          <label for="telefone">Telefone</label>
          <div class="entrada"><input type="text" id="telefone" name="telefone" class="telefone"
                 value="<?= e($d['telefone'] && ctype_digit((string) $d['telefone']) ? formatarTelefone($d['telefone']) : (string) $d['telefone']) ?>"<?= $ro ?>></div>
        </div>
        <div class="campo c-6">
          <label for="whatsapp">WhatsApp</label>
          <div class="entrada"><?= icone('whatsapp') ?><input type="text" id="whatsapp" name="whatsapp" class="telefone"
                 value="<?= e($d['whatsapp'] && ctype_digit((string) $d['whatsapp']) ? formatarTelefone($d['whatsapp']) : (string) $d['whatsapp']) ?>"<?= $ro ?>></div>
          <span class="ajuda">O número que o público vê no portal para falar com o destino.</span>
        </div>
      </div>
    </div>

    <div class="cartao">
      <h2 class="titulo-secao">Segurança</h2>
      <label class="check"><input type="checkbox" name="exigir_2fa_equipe" value="1" <?= $d['exigir_2fa_equipe'] ? 'checked' : '' ?><?= $podeEditar ? '' : ' disabled' ?>><i></i>Exigir o segundo fator da equipe do destino</label>
      <p class="ajuda">Gestor, consultor, coordenador, pesquisador e leitor passam a precisar do código do autenticador, da digital ou do certificado para entrar.</p>
    </div>
  </div>
</form>

<?php if (!$novo): ?>
  <?php /* Fora do formulário principal: form dentro de form é HTML inválido. */ ?>
  <?php if (!$proprio): ?>
  <form id="form-entrar-destino" method="post" action="/trocar-empresa" target="_top" hidden>
    <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>">
    <input type="hidden" name="destino" value="/configuracoes/usuarios">
  </form>
  <?php endif; ?>
  <?php if ($podeEditar && !empty($d['logo'])): ?>
  <form id="form-remover-logo" method="post" action="<?= e($acaoForm) ?>" hidden>
    <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="remover_logo">
  </form>
  <?php endif; ?>
<?php endif; ?>
<?php paginaFichaFim();
