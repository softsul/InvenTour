<?php
/**
 * Busca geral da paleta: acha o registro sem saber em que módulo ele está,
 * respeitando permissão e o destino em que a pessoa está.
 * Softsul Software
 *
 * Acha destinos e Cadastur (só a plataforma), Mapa do Turismo, pessoas e
 * os cartões de Configurações. Toda rotina nova entra aqui (e em comandos.php), para o
 * Ctrl+K achar o que ela cadastra: é a regra da casa. A paleta mostra tudo
 * o que vier, com rolagem; o teto de cada grupo (MAX) só protege a tecla.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): Busca ao vivo: uma chamada por tecla.
Limite::porUsuario('busca', 120, 60);
header('Cache-Control: no-store');
if (!Auth::usuarioOuNulo()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$busca = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($busca) < 2) {
    echo json_encode(['ok' => true, 'grupos' => []]);
    exit;
}
$pdo = Database::conexao();
$como = '%' . $busca . '%';
$digitos = soDigitos($busca);
$grupos = [];
const MAX = 30;
$juntar = function (string $titulo, string $icone, array $itens) use (&$grupos): void {
    if ($itens) {
        $grupos[] = ['titulo' => $titulo, 'icone' => $icone, 'itens' => $itens];
    }
};

// Destinos: só a equipe Softsul (a permissão só existe no papel da plataforma).
if (Auth::pode('destinos.ver') && Auth::ehSoftsul()) {
    $cnpj = preg_replace('/[^0-9A-Z]/', '', strtoupper($busca));
    $s = $pdo->prepare('SELECT id, nome_fantasia, cidade, uf, slug, dominio_proprio, ativo FROM empresas
                        WHERE nome LIKE ? OR nome_fantasia LIKE ? OR cidade LIKE ? OR slug LIKE ?' . ($cnpj !== '' ? ' OR cnpj LIKE ?' : '') . '
                        ORDER BY ativo DESC, nome_fantasia LIMIT ' . MAX);
    $s->execute($cnpj !== '' ? [$como, $como, $como, $como, $cnpj . '%'] : [$como, $como, $como, $como]);
    $itens = [];
    foreach ($s->fetchAll() as $d) {
        $itens[] = ['nome' => $d['nome_fantasia'] . ($d['ativo'] ? '' : ' (inativo)'),
                    'onde' => trim(($d['cidade'] ? $d['cidade'] . '/' . $d['uf'] : '') . ' · ' . ($d['dominio_proprio'] ?: $d['slug']), ' ·'),
                    'url'  => endereco('destino', (int) $d['id'], $d['nome_fantasia'])];
    }
    $juntar('Destinos', 'predio', $itens);
}

// O que é do destino atual: cada cadastro pelo nome, com a ficha dele.
$eid = (int) Empresa::id();
$doDestino = function (string $permissao, string $titulo, string $icone, string $sql, array $par, callable $item) use ($pdo, $eid, $juntar): void {
    if (!$eid || !Auth::pode($permissao)) {
        return;
    }
    $s = $pdo->prepare($sql . ' LIMIT ' . MAX);
    $s->execute($par);
    $juntar($titulo, $icone, array_map($item, $s->fetchAll()));
};

// Os cadastros do inventário entram aqui quando as telas deles existirem,
// cada um com $doDestino (permissão, título, ícone, consulta com empresa_id).

// Mapa do Turismo: os municípios e as regiões turísticas com esse nome.
if (Auth::ehSoftsul() && Auth::pode('mapa-turismo.ver')) {
    $itens = [];
    $s = $pdo->prepare('SELECT r.id, r.nome, e.uf FROM regioes_turisticas r JOIN estados e ON e.id = r.estado_id
                        WHERE r.ativo = 1 AND r.nome LIKE ? ORDER BY r.nome LIMIT ' . MAX);
    $s->execute([$como]);
    foreach ($s->fetchAll() as $r) {
        $itens[] = ['nome' => 'Região ' . $r['nome'] . '/' . $r['uf'], 'onde' => 'região turística', 'url' => '/mapa-turismo?regiao=' . (int) $r['id']];
    }
    $s = $pdo->prepare('SELECT DISTINCT m.nome, e.uf, t.categoria FROM mapa_turismo t JOIN municipios m ON m.id = t.municipio_id
                        JOIN estados e ON e.id = m.estado_id WHERE m.nome LIKE ? ORDER BY m.nome LIMIT ' . MAX);
    $s->execute([$como]);
    foreach ($s->fetchAll() as $m) {
        $itens[] = ['nome' => $m['nome'] . '/' . $m['uf'], 'onde' => 'categoria ' . $m['categoria'],
                    'url' => '/mapa-turismo?' . http_build_query(['q' => $m['nome']])];
    }
    if ($itens) {
        $itens[] = ['nome' => 'Buscar "' . $busca . '" no Mapa do Turismo', 'onde' => 'município', 'url' => '/mapa-turismo?' . http_build_query(['q' => $busca])];
    }
    $juntar('Mapa do Turismo', 'mapa', $itens);
}

// Pessoas: as do destino atual, ou a equipe Softsul na visão da plataforma.
if (Auth::pode('usuarios.ver')) {
    $filtroTel = $digitos !== '' ? ' OR u.telefone LIKE ?' : '';
    $par = [$como, $como];
    if ($digitos !== '') {
        $par[] = '%' . $digitos . '%';
    }
    if (Empresa::id()) {
        $s = $pdo->prepare("SELECT u.id, u.nome, u.email, p.nome AS papel FROM usuarios u
                            JOIN usuario_empresa ue ON ue.usuario_id = u.id AND ue.empresa_id = ?
                            JOIN papeis p ON p.id = ue.papel_id
                            WHERE (u.nome LIKE ? OR u.email LIKE ?{$filtroTel}) ORDER BY u.nome LIMIT " . MAX);
        $s->execute([Empresa::id(), ...$par]);
    } else {
        $s = $pdo->prepare("SELECT u.id, u.nome, u.email, p.nome AS papel FROM usuarios u
                            LEFT JOIN papeis p ON p.id = u.papel_softsul_id
                            WHERE u.softsul = 1 AND (u.nome LIKE ? OR u.email LIKE ?{$filtroTel}) ORDER BY u.nome LIMIT " . MAX);
        $s->execute($par);
    }
    $itens = [];
    foreach ($s->fetchAll() as $p) {
        $itens[] = ['nome' => $p['nome'], 'onde' => trim(($p['papel'] ?? '') . ' · ' . $p['email'], ' ·'),
                    'url' => endereco('usuario', (int) $p['id'], $p['nome'])];
    }
    $juntar('Pessoas', 'pessoas', $itens);
}

// Cadastur: só a equipe Softsul. Até 20 prestadores pelo nome (o LIMIT
// para cedo; contar tudo levaria mais de um segundo por tecla) e o atalho
// para a lista, que procura também em atividade, categoria e certificado.
if (Auth::ehSoftsul() && Auth::pode('cadastur.ver') && mb_strlen($busca) >= 3) {
    $doc = preg_replace('/[^0-9A-Z]/', '', strtoupper($busca));
    // Pelo nome que aparece na tela (o fantasia, ou o jurídico quando não
    // há fantasia): achar pelo jurídico e mostrar o fantasia confundia.
    $s = $pdo->prepare('SELECT id, nome_fantasia, nome_juridico, atividade, municipio, uf FROM cadastur_prestadores
                        WHERE COALESCE(nome_fantasia, nome_juridico) LIKE ?' . (strlen((string) $doc) >= 5 ? ' OR documento LIKE ?' : '') . '
                        LIMIT 20');
    $s->execute(strlen((string) $doc) >= 5 ? [$como, $doc . '%'] : [$como]);
    $itens = [];
    // Primeiro os municípios com esse nome: "foz do" leva à lista de Foz do
    // Iguaçu inteira, e não só a quem tem "Foz do" no nome. Pelo slug
    // indexado, como o campo de município.
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(semAcento($busca))), '-');
    if (strlen($slug) >= 3) {
        $m = $pdo->prepare('SELECT m.id, m.nome, e.uf, (SELECT COUNT(*) FROM cadastur_prestadores c WHERE c.municipio_id = m.id) AS n
                            FROM municipios m JOIN estados e ON e.id = m.estado_id
                            WHERE m.slug LIKE ? ORDER BY m.slug = ? DESC, n DESC LIMIT 3');
        $m->execute([$slug . '%', $slug]);
        foreach ($m->fetchAll() as $mu) {
            if ((int) $mu['n']) {
                $itens[] = ['nome' => 'Cadastur de ' . $mu['nome'] . '/' . $mu['uf'], 'onde' => number_format((int) $mu['n'], 0, ',', '.') . ' prestadores',
                            'url' => '/cadastur?municipio=' . (int) $mu['id']];
            }
        }
    }
    $itens[] = ['nome' => 'Buscar "' . $busca . '" no Cadastur', 'onde' => 'nome, CNPJ, município, atividade, categoria ou certificado',
                'url' => '/cadastur?' . http_build_query(['q' => $busca])];
    foreach ($s->fetchAll() as $c) {
        $itens[] = ['nome' => (string) ($c['nome_fantasia'] ?: $c['nome_juridico']),
                    'onde' => trim($c['atividade'] . ' · ' . ($c['municipio'] ? $c['municipio'] . '/' . $c['uf'] : ''), ' ·'),
                    'url'  => '/prestador/' . (int) $c['id']];
    }
    $juntar('Cadastur', 'certificado', $itens);
}

// Os cartões de Configurações do destino, pelo nome ou pela descrição.
if ($eid && Auth::pode('configuracoes.ver')) {
    require_once __DIR__ . '/../../app/ConfiguracoesCatalogo.php';
    $semAcento = fn (string $t) => mb_strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $t));
    $alvo = $semAcento($busca);
    $itens = [];
    foreach (ConfiguracoesCatalogo::cartoes($eid) as $c) {
        if (!empty($c['pronto']) && str_contains($semAcento($c['nome'] . ' ' . $c['descricao']), $alvo)) {
            $itens[] = ['nome' => $c['nome'], 'onde' => 'Configurações · ' . $c['grupo'], 'url' => $c['endereco']];
        }
    }
    $juntar('Configurações', 'engrenagem', $itens);
}

echo json_encode(['ok' => true, 'grupos' => $grupos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
