<?php
/**
 * Importa os quadros de um caderno de inventário já publicado como
 * pré-cadastro no InvenTour.
 * Softsul Software
 *
 *   php scripts/importar_caderno.php <empresa_id> <quadros.json> [--gravar]
 *
 * O JSON vem de scripts/ler_quadros_caderno.py (lê os quadros NOME,
 * ENDEREÇO, TELEFONE, PLUS CODE das páginas do PDF). Sem --gravar, só mostra
 * o que faria. Cada linha vira um item em pré-cadastro na ficha do MTur da
 * seção do caderno; se o item já existe no destino (pré-cadastro do
 * Cadastur), pelo telefone ou pelo nome, completa o que falta em vez de
 * duplicar. A coordenada sai do plus code. É a "pesquisa de laboratório" da
 * metodologia: o pesquisador confere tudo em campo.
 *
 * Feito para o caderno de Pato Branco 2025 (20/11/2026); o mapa de seções
 * abaixo segue a capitulação daquele caderno.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/PlusCode.php';
require_once __DIR__ . '/../app/svg.php';

[$empresaId, $arquivo] = [(int) ($argv[1] ?? 0), (string) ($argv[2] ?? '')];
$gravar = in_array('--gravar', $argv, true);
if (!$empresaId || !is_file($arquivo)) {
    fwrite(STDERR, "Uso: php scripts/importar_caderno.php <empresa_id> <quadros.json> [--gravar]\n");
    exit(1);
}
$pdo = Database::conexao();
$e = $pdo->prepare('SELECT e.id, e.nome_fantasia, m.latitude, m.longitude FROM empresas e JOIN municipios m ON m.id = e.municipio_id WHERE e.id = ?');
$e->execute([$empresaId]);
$destino = $e->fetch() ?: exit("Destino {$empresaId} não encontrado.\n");

/** prefixo da seção do caderno => [ordem da ficha, tipo] (null: não importa) */
$mapa = [
    '2.1.1.1 ' => [20, 'Hotel'], '2.1.1.10' => [20, 'Pensão'], '2.1.1.11' => [20, 'Motel'], '2.1.1.19' => [20, 'Flat/apart-hotel'],
    '2.1.1.' => [20, 'Outros'], '2.1.2.1' => [21, 'Acampamento turístico/camping'], '2.1.2.2' => [20, 'Hospedagem familiar'],
    '2.2.2.1' => [22, 'Bar'], '2.2.2.2' => [22, 'Cafeteria'], '2.2.3' => [22, 'Confeitaria/padaria'], '2.2.4' => [22, 'Bar'],
    '2.2.5' => [22, 'Quiosque'], '2.2.6' => [22, 'Sorveteria'], '2.2.7' => [22, 'Lanchonete'], '2.2' => [22, 'Restaurante'],
    '2.3' => [23, 'Agência de viagem'], '2.4.3' => [24, 'Locadoras de veículos'], '2.4.4' => null, '2.4' => [24, 'Transportadora turística e similares'],
    '2.5.1' => [25, 'Centro de convenções e feiras'], '2.5.2' => [25, 'Parque/pavilhão/centro de exposições'], '2.5.3' => [25, 'Auditório/salão para reuniões'],
    '2.5.4' => [26, 'Organizadora'], '2.5.5' => [26, 'Outros serviços especializados'],
    '2.6.2' => [28, 'Parque'], '2.6.3' => [31, 'Clube social'], '2.6.4' => [29, 'Pista de patinação'], '2.6.5.2' => [29, 'Estádio'],
    '2.6.5.3' => [29, 'Campo de futebol'], '2.6.5.4' => [29, 'Campo de futebol'], '2.6.5.8' => [29, 'Ginásio'], '2.6.5.9' => [29, 'Quadra'],
    '2.6.5.1' => [29, 'Quadra'], '2.6.5' => [29, 'Outras'], '2.6.6' => [29, 'Autódromo'], '2.6.8' => [28, 'Mirante'], '2.6.9' => [32, 'Outros'],
    '2.6.10' => [31, 'Boate/discoteca'], '2.6.12' => [31, 'Casa de dança'], '2.6.13' => [31, 'Cinema'], '2.6.14' => [29, 'Pista de boliche'],
    '2.6.15' => [25, 'Parque/pavilhão/centro de exposições'], '2.6.16' => [32, 'Outros'], '2.7.2' => [34, null],
    '3.1.5' => [39, 'Lago/lagoa/laguna'], '3.1.8' => [40, 'Parque'], '3.1 ' => [39, 'Outros'],
    '3.2.2.3' => [45, 'Arquitetura religiosa'], '3.2.3' => [47, 'Bens integrados à paisagem ou ao espaço urbano'],
    '3.2.4.1' => [48, 'Museu/memorial'], '3.2.4.2' => [48, 'Biblioteca'], '3.2.4.5' => [48, 'Centros culturais/casas de cultura/galerias'],
    '3.2.6' => [49, 'Gastronomia típica e preparação de alimentos'], '3.2 ' => [47, 'Bens integrados à paisagem ou ao espaço urbano'],
    '3.3.3' => [52, 'Industrial'], '3.4.11' => [53, 'Outras'], '3.4.8' => [53, 'Viveiro'], '3.5' => [54, null],
];
// o prefixo mais longo vence
uksort($mapa, fn ($a, $b) => strlen($b) <=> strlen($a));
$fichaPorOrdem = $pdo->query('SELECT ordem, id FROM inventario_fichas')->fetchAll(PDO::FETCH_KEY_PAIR);
$tipoId = function (int $fichaId, ?string $nome) use ($pdo): ?int {
    if ($nome === null) {
        return null;
    }
    $q = $pdo->prepare('SELECT id FROM inventario_tipos WHERE ficha_id = ? AND nome = ? ORDER BY pai_id IS NULL LIMIT 1');
    $q->execute([$fichaId, $nome]);
    return ((int) $q->fetchColumn()) ?: null;
};
$norm = function (string $t): string {
    $t = iconv('UTF-8', 'ASCII//TRANSLIT', $t) ?: $t;
    $t = strtolower((string) preg_replace('/[^a-z0-9]+/i', ' ', $t));
    $t = (string) preg_replace('/\b(hotel|h|restaurante|bar|ltda|me|eireli|cafeteria|lanchonete|e|de|da|do|das|dos|turismo|viagens|pato branco)\b/', ' ', $t);
    return trim((string) preg_replace('/\s+/', ' ', $t));
};

$existentes = $pdo->prepare('SELECT id, ficha_id, nome, telefone, latitude, logradouro, capacidade_uh, capacidade_leitos, observacoes FROM inventario_itens WHERE empresa_id = ?');
$existentes->execute([$empresaId]);
$porFone = $porNome = [];
foreach ($existentes->fetchAll() as $x) {
    if ($x['telefone']) {
        $porFone[substr((string) $x['telefone'], -8)][] = $x;
    }
    $porNome[$norm((string) $x['nome'])][] = $x;
}

$linhas = json_decode((string) file_get_contents($arquivo), true) ?: [];
$conta = ['novos' => 0, 'completados' => 0, 'pulados' => 0];
$ins = $pdo->prepare("INSERT INTO inventario_itens (empresa_id, ficha_id, tipo_id, situacao, origem, nome, logradouro, telefone, plus_code, latitude, longitude,
                             capacidade_uh, capacidade_leitos, descricao_tecnica, observacoes, criado_em)
                      VALUES (?, ?, ?, 'pre_cadastro', 'importacao', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$hist = $pdo->prepare("INSERT INTO inventario_item_historico (item_id, empresa_id, acao, de, para, nota, criado_em) VALUES (?, ?, 'importou', NULL, ?, ?, NOW())");
$vistosNovos = $usados = [];
/**
 * Conserta o corte das colunas do PDF: número de ordem colado no nome ("1
 * Clube Grêmio"), começo do endereço no fim do nome ("... R. Arg" +
 * "gentina, 200") e telefone vazando no endereço ("Av. Tupi, 2648 (46").
 */
$limpar = function (string $nome, string $endereco): array {
    $ligaduras = ['ﬁ' => 'fi', 'ﬂ' => 'fl', 'ﬀ' => 'ff'];
    $nome = trim((string) preg_replace('/\s+/', ' ', strtr($nome, $ligaduras)), ' *');
    $endereco = trim((string) preg_replace('/\s+/', ' ', strtr($endereco, $ligaduras)));
    $nome = (string) preg_replace('/^\d{1,2}¹? (?=\p{Lu})/u', '', $nome);
    if (preg_match('/^(.*\S)\s+((?:R|Av|Rod|Est)\.\s*\S*)$/u', $nome, $m) && mb_strlen($m[1]) > 3) {
        $nome = $m[1];
        // o corte às vezes repete a letra da emenda ("R. Arg" + "gentina")
        if (preg_match('/^\p{Ll}/u', $endereco)) {
            $endereco = mb_substr($m[2], -1) === mb_substr($endereco, 0, 1) ? $m[2] . mb_substr($endereco, 1) : $m[2] . $endereco;
        } else {
            $endereco = $m[2] . ' ' . $endereco;
        }
    }
    $endereco = trim((string) preg_replace('/\s*\(\d*\)?\s*[\d\- ]*$/', '', $endereco), ' -.');
    $endereco = (string) preg_replace('/^\.\s*/', 'Av. ', $endereco);
    return [$nome, $endereco !== '' ? $endereco : null];
};
if ($gravar) {
    $pdo->beginTransaction();
}
foreach ($linhas as $l) {
    $alvo = null;
    foreach ($mapa as $prefixo => $destinoFicha) {
        if (str_starts_with($l['secao'] . ' ', $prefixo)) {
            $alvo = $destinoFicha;
            break;
        }
    }
    if ($alvo === null || !($fichaId = (int) ($fichaPorOrdem[$alvo[0]] ?? 0))) {
        $conta['pulados']++;
        continue;
    }
    [$nome, $endereco] = $limpar((string) $l['nome'], (string) ($l['endereco'] ?? $l['local'] ?? ''));
    if ($nome === '' || preg_match('/^(R|Av|Rua)\.? /u', $nome) || preg_match('/^\p{Ll}/u', $nome)) {
        $conta['pulados']++; // resto de endereço, ou nome cortado ("ube Grêmio") que repete a linha de cima
        continue;
    }
    $tel = telefoneParaBanco(strlen((string) $l['telefone']) >= 10 ? (string) $l['telefone'] : null);
    $coord = PlusCode::coordenada((string) $l['plus'], (float) $destino['latitude'], (float) $destino['longitude']);
    // Hospedagem traz C, UH e leitos depois do plus code ("S 58 116").
    $uh = $leitos = null;
    if ($alvo[0] === 20 && preg_match('/^([SN])\s+(\d+)\s+(\d+)/', (string) $l['resto'], $m)) {
        [$uh, $leitos] = [(int) $m[2], (int) $m[3]];
    }
    $origem = 'Importado do Inventário da Oferta Turística ' . $destino['nome_fantasia'] . ' 2025, seção ' . $l['secao'] . ' (folha ' . $l['folha'] . ' do PDF).';
    $tecnica = null;
    if (!empty($l['artista']) || !empty($l['ano'])) {
        $tecnica = trim(($l['ano'] ? 'Ano: ' . $l['ano'] . '. ' : '') . ($l['artista'] ? 'Artista: ' . $l['artista'] . '. ' : '') . ($l['local'] ? 'Local: ' . $l['local'] . '.' : ''));
    }

    // Já existe (do Cadastur)? Pelo telefone, ou pelo nome sem as palavras genéricas.
    // Só casa dentro da mesma ficha (hospedagem e camping contam juntas): o
    // telefone do hotel também atende a sala de eventos e o restaurante dele.
    $par = null;
    $grupo = fn (int $f) => in_array($f, [$fichaPorOrdem[20], $fichaPorOrdem[21]], true) ? 'hosp' : (string) $f;
    foreach (array_merge($tel ? ($porFone[substr($tel, -8)] ?? []) : [], $porNome[$norm($nome)] ?? []) as $x) {
        if ($grupo((int) $x['ficha_id']) === $grupo($fichaId) && !isset($usados[$x['id']])) {
            $par = $x;
            $usados[$x['id']] = true;
            break;
        }
    }
    if ($par) {
        $sets = ['plus_code = COALESCE(plus_code, ?)'];
        $args = [$l['plus']];
        if (!$par['latitude'] && $coord) {
            $sets[] = 'latitude = ?, longitude = ?';
            array_push($args, $coord[0], $coord[1]);
        }
        if (!$par['logradouro'] && $endereco) {
            $sets[] = 'logradouro = ?';
            $args[] = $endereco;
        }
        if (!$par['telefone'] && $tel) {
            $sets[] = 'telefone = ?';
            $args[] = $tel;
        }
        $nota = $origem;
        if ($uh !== null) {
            $nota .= " Inventário 2025: {$uh} UH e {$leitos} leitos; Cadastur: " . ($par['capacidade_uh'] ?? '?') . ' UH e ' . ($par['capacidade_leitos'] ?? '?') . ' leitos.';
            $sets[] = 'capacidade_uh = ?, capacidade_leitos = ?';
            array_push($args, $uh, $leitos);
        }
        $sets[] = "observacoes = CONCAT_WS('\n', observacoes, ?)";
        $args[] = $nota;
        echo "COMPLETA #{$par['id']} {$par['nome']} <= {$nome} [{$l['secao']}]\n";
        if ($gravar) {
            $pdo->prepare('UPDATE inventario_itens SET ' . implode(', ', $sets) . ', atualizado_em = NOW() WHERE id = ? AND empresa_id = ?')
                ->execute([...$args, $par['id'], $empresaId]);
            $hist->execute([$par['id'], $empresaId, null, 'Completado com a importação do Inventário 2025: ' . $l['secao']]);
        }
        $conta['completados']++;
        continue;
    }
    $chave = $fichaId . '|' . $norm($nome) . '|' . $l['plus'];
    if (isset($vistosNovos[$chave])) {
        $conta['pulados']++;
        continue;
    }
    $vistosNovos[$chave] = true;
    echo "NOVO  [{$alvo[0]}] {$nome} | " . ($endereco ?? '-') . " | {$l['plus']}\n";
    if ($gravar) {
        $ins->execute([$empresaId, $fichaId, $tipoId($fichaId, $alvo[1]), mb_substr($nome, 0, 180), $endereco ? mb_substr($endereco, 0, 200) : null,
                       $tel, $l['plus'], $coord[0] ?? null, $coord[1] ?? null, $uh, $leitos, $tecnica, $origem]);
        $hist->execute([(int) $pdo->lastInsertId(), $empresaId, 'pre_cadastro', 'Importado do Inventário 2025: ' . $l['secao']]);
    }
    $conta['novos']++;
}
if ($gravar) {
    $pdo->commit();
}
echo "\n{$conta['novos']} novos, {$conta['completados']} completados, {$conta['pulados']} pulados" . ($gravar ? ' (gravado).' : ' (simulação; use --gravar).') . "\n";
