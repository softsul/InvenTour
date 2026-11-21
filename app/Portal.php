<?php
/**
 * Os dados do portal público do destino (etapa 5).
 * Softsul Software
 *
 * Tudo pela conexão do portal (usuário inventour_portal, só as colunas
 * públicas: docs/servidor/mysql-usuario-portal.sql). Nunca sai rascunho nem
 * dado técnico: só o que foi homologado (homologada ou desatualizada).
 *
 * Prévia interna: o painel gera um link assinado (válido por 2 horas) que
 * mostra também o que ainda não foi homologado, com a faixa de prévia e sem
 * indexação. Não há cookie: o parâmetro segue nos links do próprio portal.
 *
 * Ordem orgânica das listas (PLANEJAMENTO 2.8): quem tem experiência
 * aprovada, depois quem tem Cadastur válido, depois um sorteio do dia (a
 * mesma ordem o dia todo, diferente a cada dia, para ninguém morar no topo).
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Cripto.php';
require_once __DIR__ . '/Imagem.php';

final class Portal
{
    /** Pilares do portal: chave => [título, subtítulo, ícone, fichas (ordem do MTur)]. */
    public const PILARES = [
        'onde-dormir'  => ['Onde dormir', 'Hotéis, pousadas e outros meios de hospedagem', 'cama', [20, 21]],
        'onde-comer'   => ['Onde comer', 'Restaurantes, bares, cafés e sorveterias', 'garfo', [22]],
        'o-que-fazer'  => ['O que fazer', 'Atrativos naturais e culturais, parques, lazer e esporte',
                           'bussola', [27, 28, 29, 30, 31, 32, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53]],
        'servicos'     => ['Serviços', 'Agências, transporte, eventos, saúde, bancos e informações turísticas',
                           'info', [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 16, 17, 18, 19, 23, 24, 25, 26, 33, 34, 35]],
    ];

    /** Os cinco selos de acessibilidade, a partir das respostas da ficha (seção 6). */
    public const SELOS_ACESSIBILIDADE = [
        'cadeirante' => 'Cadeira de rodas',
        'visual'     => 'Deficiência visual',
        'auditiva'   => 'Deficiência auditiva',
        'capacitado' => 'Equipe preparada',
        'vaga'       => 'Vaga reservada',
    ];

    private static ?bool $previa = null;

    private static function pdo(): PDO
    {
        return Database::conexaoPortal();
    }

    // ------------------------------------------------------------ prévia

    /** Link de prévia interna (gerado no painel): o parâmetro ?previa=EXPIRA.ASSINATURA. */
    public static function tokenDePrevia(int $eid, int $horas = 2): string
    {
        $expira = time() + $horas * 3600;
        return $expira . '.' . substr(Cripto::assinar('previa-portal:' . $eid . ':' . $expira), 0, 32);
    }

    /** O pedido atual é uma prévia válida para este destino? */
    public static function previa(int $eid): bool
    {
        if (self::$previa !== null) {
            return self::$previa;
        }
        $t = (string) ($_GET['previa'] ?? '');
        if (!preg_match('/^(\d{10})\.([a-f0-9]{32})$/', $t, $m) || (int) $m[1] < time()) {
            return self::$previa = false;
        }
        return self::$previa = hash_equals(substr(Cripto::assinar('previa-portal:' . $eid . ':' . $m[1]), 0, 32), $m[2]);
    }

    /** Endereço interno do portal, levando a prévia junto quando houver. */
    public static function url(string $caminho, array $query = []): string
    {
        if (self::$previa) {
            $query['previa'] = (string) $_GET['previa'];
        }
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '' && $v !== []);
        return $caminho . ($query ? '?' . http_build_query($query) : '');
    }

    /** O filtro de situação: só homologado, ou tudo menos arquivado na prévia. */
    private static function situacoes(int $eid, string $alias = 'i'): string
    {
        return self::previa($eid) ? "{$alias}.situacao <> 'arquivada'" : "{$alias}.situacao IN ('homologada', 'desatualizada')";
    }

    // ------------------------------------------------------------ listas

    /** Quantos itens públicos há em cada pilar (para o início). */
    public static function contagens(int $eid): array
    {
        $q = self::pdo()->prepare('SELECT f.ordem, COUNT(*) FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                                   WHERE i.empresa_id = ? AND ' . self::situacoes($eid) . ' GROUP BY f.ordem');
        $q->execute([$eid]);
        $porFicha = array_map('intval', $q->fetchAll(PDO::FETCH_KEY_PAIR));
        $n = [];
        foreach (self::PILARES as $k => $p) {
            $n[$k] = array_sum(array_intersect_key($porFicha, array_flip($p[3])));
        }
        return $n;
    }

    /**
     * Itens de um pilar (ou de uma busca), na ordem orgânica, já com a foto
     * de capa, os selos de acessibilidade e os de tribo. Filtros: zona,
     * acessibilidade (selo), tribo (id), q (texto), ficha (ordem).
     */
    public static function itens(int $eid, ?string $pilar, array $filtros = [], int $limite = 400): array
    {
        $onde = ['i.empresa_id = ?', self::situacoes($eid)];
        $args = [$eid];
        $fichas = $pilar && isset(self::PILARES[$pilar]) ? self::PILARES[$pilar][3] : array_merge(...array_column(self::PILARES, 3));
        if (!empty($filtros['ficha']) && in_array((int) $filtros['ficha'], $fichas, true)) {
            $fichas = [(int) $filtros['ficha']];
        }
        $onde[] = 'f.ordem IN (' . implode(',', array_map('intval', $fichas)) . ')';
        if (in_array($filtros['zona'] ?? '', ['urbana', 'rural'], true)) {
            $onde[] = 'i.zona = ?';
            $args[] = $filtros['zona'];
        }
        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            foreach (array_slice(preg_split('/\s+/', $q) ?: [], 0, 5) as $p) {
                $onde[] = '(i.nome LIKE ? OR i.nome_popular LIKE ? OR i.bairro LIKE ? OR t.nome LIKE ?)';
                array_push($args, ...array_fill(0, 4, '%' . $p . '%'));
            }
        }
        $sql = "SELECT i.id, i.tipo_id, i.nome, i.nome_popular, i.bairro, i.zona, i.latitude, i.longitude, i.telefone, i.whatsapp, i.cadastur, i.horario, i.plus_code,
                       i.capacidade_uh, i.capacidade_leitos, i.descricao_promocional, i.situacao, f.codigo, f.ordem AS ficha_ordem, f.nome AS ficha_nome,
                       t.nome AS tipo_nome,
                       (SELECT COUNT(*) FROM inventario_experiencias x WHERE x.item_id = i.id AND x.empresa_id = i.empresa_id AND x.situacao = 'aprovada') AS experiencias,
                       (i.cadastur IS NOT NULL AND (i.cadastur_validade IS NULL OR i.cadastur_validade >= CURDATE())) AS cadastur_ok
                FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
                WHERE " . implode(' AND ', $onde) . '
                ORDER BY experiencias > 0 DESC, cadastur_ok DESC, CRC32(CONCAT(i.id, CURDATE())) LIMIT ' . (int) $limite;
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        $itens = [];
        foreach ($st->fetchAll() as $i) {
            $itens[(int) $i['id']] = $i + ['foto' => null, 'selos' => [], 'tribos' => [], 'pilar' => self::pilarDe((int) $i['ficha_ordem'])];
        }
        if (!$itens) {
            return [];
        }
        self::completar($eid, $itens);
        if (!empty($filtros['acess']) && isset(self::SELOS_ACESSIBILIDADE[$filtros['acess']])) {
            $itens = array_filter($itens, fn ($i) => in_array($filtros['acess'], $i['selos'], true));
        }
        if (!empty($filtros['tribo'])) {
            $itens = array_filter($itens, fn ($i) => isset($i['tribos'][(int) $filtros['tribo']]));
        }
        if (!empty($filtros['aberto'])) {
            $itens = array_filter($itens, fn ($i) => (self::agora($i['horario'] ?? null)['aberto'] ?? false) === true);
        }
        return $itens;
    }

    /** Foto de capa, selos de acessibilidade e de tribo de uma lista de itens, em lote. */
    private static function completar(int $eid, array &$itens): void
    {
        $ids = implode(',', array_map('intval', array_keys($itens)));
        foreach (self::pdo()->query("SELECT item_id, arquivo FROM inventario_midias WHERE empresa_id = {$eid} AND experiencia_id IS NULL
                                     AND item_id IN ({$ids}) ORDER BY capa DESC, ordem, id") as $m) {
            $itens[(int) $m['item_id']]['foto'] ??= $m['arquivo'];
        }
        $acess = [];
        foreach (self::pdo()->query("SELECT item_id, chave, opcoes FROM inventario_item_acessibilidade WHERE empresa_id = {$eid} AND item_id IN ({$ids})") as $a) {
            $acess[(int) $a['item_id']][$a['chave']] = json_decode((string) $a['opcoes'], true) ?: [];
        }
        foreach ($acess as $id => $respostas) {
            $itens[$id]['selos_det'] = self::selosDetalhados($respostas);
            $itens[$id]['selos'] = array_keys($itens[$id]['selos_det']);
        }
        foreach (self::pdo()->query("SELECT i.item_id, t.id, t.nome, t.icone, COUNT(*) n, GROUP_CONCAT(f.nome ORDER BY f.ordem SEPARATOR ', ') AS facilidades FROM inventario_item_facilidades i
                                     JOIN tribo_facilidades f ON f.id = i.facilidade_id AND f.ativo = 1 JOIN tribos t ON t.id = f.tribo_id AND t.ativo = 1
                                     WHERE i.empresa_id = {$eid} AND i.item_id IN ({$ids}) GROUP BY i.item_id, t.id HAVING n >= 2") as $t) {
            $itens[(int) $t['item_id']]['tribos'][(int) $t['id']] = ['nome' => $t['nome'], 'icone' => $t['icone'], 'facilidades' => (string) $t['facilidades']];
        }
    }

    /** Os selos a partir das respostas de acessibilidade (o "Não" sozinho não vale). */
    public static function selos(array $r): array
    {
        return array_keys(self::selosDetalhados($r));
    }

    /**
     * Os selos com o que os justifica, para o balão: ['cadeirante' => ['Rampa', 'Sanitário: barra de apoio'], ...].
     */
    public static function selosDetalhados(array $r): array
    {
        $achados = function (string $chave, string $rotulo, array $opcoes = []) use ($r): array {
            $marcadas = array_values(array_diff((array) ($r[$chave] ?? []), ['Não']));
            if ($opcoes) {
                $marcadas = array_values(array_intersect($marcadas, $opcoes));
            }
            return array_map(fn ($o) => $rotulo !== '' ? $rotulo . ': ' . mb_strtolower((string) $o) : (string) $o, $marcadas);
        };
        $det = [
            'cadeirante' => array_merge($achados('circulacao_interna', '', ['Rampa', 'Elevador', 'Plataforma elevatória', 'Porta larga']),
                                        $achados('rota_externa', 'Acesso externo', ['Rampa', 'Calçada rebaixada']),
                                        $achados('sanitario', 'Sanitário', ['Barra de apoio', 'Acesso para cadeira de rodas', 'Porta larga suficiente para entrada de cadeira de rodas'])),
            'visual'     => array_merge($achados('piso', 'Piso', ['Tátil']), $achados('sinalizacao_tatil', 'Sinalização tátil'),
                                        $achados('comunicacao', '', ['Texto informativo em Braille', 'Texto informativo em fonte ampliada'])),
            'auditiva'   => array_merge($achados('comunicacao', '', ['Intérprete em Libras']), $achados('sinalizacao_visual', 'Sinalização visual'),
                                        $achados('alarme_emergencia', 'Alarme', ['Visual', 'Vibratório'])),
            'capacitado' => $achados('pessoal_capacitado', 'Atendimento preparado para deficiência'),
            'vaga'       => $achados('vaga_estacionamento', 'Vaga'),
        ];
        return array_filter($det);
    }

    // ------------------------------------------------------------ ficha

    public static function item(int $eid, int $id): ?array
    {
        $q = self::pdo()->prepare('SELECT i.id, i.empresa_id, i.ficha_id, i.tipo_id, i.situacao, i.nome, i.nome_popular, i.logradouro, i.numero, i.complemento, i.bairro, i.distrito, i.zona, i.cep, i.telefone, i.whatsapp, i.email, i.site, i.instagram, i.latitude, i.longitude, i.plus_code, i.referencia, i.horario, i.formas_pagamento, i.idiomas, i.capacidade_uh, i.capacidade_leitos, i.capacidade_assentos, i.capacidade_publico, i.acessibilidade_possui, i.descricao_promocional, i.cadastur, i.cadastur_validade, i.possui_experiencia, f.codigo, f.nome AS ficha_nome, f.ordem AS ficha_ordem, f.categoria, t.nome AS tipo_nome
                                   FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
                                   WHERE i.id = ? AND i.empresa_id = ? AND ' . self::situacoes($eid));
        $q->execute([$id, $eid]);
        $i = $q->fetch();
        if (!$i) {
            return null;
        }
        $lista = [$id => $i + ['foto' => null, 'selos' => [], 'tribos' => []]];
        self::completar($eid, $lista);
        $i = $lista[$id];
        $q = self::pdo()->prepare('SELECT arquivo, legenda, credito FROM inventario_midias WHERE item_id = ? AND empresa_id = ? AND experiencia_id IS NULL ORDER BY capa DESC, ordem, id');
        $q->execute([$id, $eid]);
        $i['fotos'] = $q->fetchAll();
        $q = self::pdo()->prepare("SELECT id, empresa_id, item_id, situacao, titulo, dimensoes, narrativa, duracao_minutos, pessoas_min, pessoas_max, agendamento, antecedencia_horas, faixa_valor, valor_texto FROM inventario_experiencias WHERE item_id = ? AND empresa_id = ? AND situacao = 'aprovada' ORDER BY titulo");
        $q->execute([$id, $eid]);
        $i['experiencias'] = $q->fetchAll();
        return $i;
    }

    /** Endereço da foto, servido pelo /midia do portal; $tam p (lista), m (cartão) ou '' (grande). */
    public static function foto(int $eid, ?string $arquivo, string $tam = 'm'): ?string
    {
        if (!$arquivo) {
            return null;
        }
        $nome = $tam !== '' ? Imagem::nomeMiniatura((string) $arquivo, $tam) : basename((string) $arquivo);
        return '/midia/inventario/' . $eid . '/' . rawurlencode($nome);
    }

    /** "Seg a sex 08:00 às 18:00" a partir do horário da ficha. */
    public static function horario(?string $json): array
    {
        $h = json_decode((string) $json, true) ?: [];
        if (!empty($h['24h'])) {
            return ['Aberto 24 horas'];
        }
        $dias = ['seg' => 'Segunda', 'ter' => 'Terça', 'qua' => 'Quarta', 'qui' => 'Quinta', 'sex' => 'Sexta', 'sab' => 'Sábado', 'dom' => 'Domingo'];
        $linhas = [];
        foreach ($dias as $k => $nome) {
            if (!empty($h[$k]) && is_array($h[$k])) {
                $linhas[] = $nome . ': ' . implode(' e ', array_map(fn ($p) => ($p[0] ?? '') . ' às ' . ($p[1] ?? ''), $h[$k]));
            }
        }
        if ($linhas && isset($h['feriados_abre'])) {
            $linhas[] = $h['feriados_abre'] ? 'Abre em feriados' : 'Fecha em feriados';
        }
        if (!$linhas && ($p = self::periodos($json))) {
            $linhas[] = $p;
        }
        return $linhas;
    }

    /**
     * Sem a hora exata, o período de atendimento: "Atende no almoço e no
     * jantar". Null quando a ficha tem horário por dia ou não tem período.
     */
    public static function periodos(?string $json): ?string
    {
        $h = json_decode((string) $json, true) ?: [];
        $nomes = ['cafe' => 'no café da manhã', 'almoco' => 'no almoço', 'tarde' => 'à tarde', 'jantar' => 'no jantar'];
        $p = array_values(array_intersect_key($nomes, array_flip(array_filter((array) ($h['periodos'] ?? []), 'is_string'))));
        if (!$p || !empty($h['24h']) || array_filter(array_intersect_key($h, array_flip(['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'])))) {
            return null;
        }
        $ultimo = array_pop($p);
        return 'Atende ' . ($p ? implode(', ', $p) . ' e ' : '') . $ultimo;
    }

    /** O pilar de um item, pela ficha. */
    public static function pilarDe(int $fichaOrdem): ?string
    {
        foreach (self::PILARES as $k => $p) {
            if (in_array($fichaOrdem, $p[3], true)) {
                return $k;
            }
        }
        return null;
    }

    // ------------------------------------------------------------ outros

    public static function tribos(): array
    {
        return self::pdo()->query('SELECT id, nome, icone FROM tribos WHERE ativo = 1 ORDER BY ordem')->fetchAll();
    }

    public static function experiencias(int $eid): array
    {
        $q = self::pdo()->prepare("SELECT x.id, x.empresa_id, x.item_id, x.situacao, x.titulo, x.dimensoes, x.narrativa, x.duracao_minutos, x.pessoas_min, x.pessoas_max, x.agendamento, x.antecedencia_horas, x.faixa_valor, x.valor_texto, i.nome AS item_nome,
                                          (SELECT m.arquivo FROM inventario_midias m WHERE m.experiencia_id = x.id ORDER BY m.ordem, m.id LIMIT 1) AS foto,
                                          (SELECT m.arquivo FROM inventario_midias m WHERE m.item_id = x.item_id AND m.experiencia_id IS NULL ORDER BY m.capa DESC, m.ordem LIMIT 1) AS foto_item
                                   FROM inventario_experiencias x JOIN inventario_itens i ON i.id = x.item_id
                                   WHERE x.empresa_id = ? AND x.situacao = 'aprovada' AND " . self::situacoes($eid) . ' ORDER BY x.titulo');
        $q->execute([$eid]);
        return $q->fetchAll();
    }

    public static function narrativas(int $eid): array
    {
        $q = self::pdo()->prepare("SELECT secao, titulo, conteudo_html, indicadores FROM destino_narrativas
                                   WHERE empresa_id = ? AND conteudo_html <> ''" . (self::previa($eid) ? '' : ' AND no_portal = 1') . ' ORDER BY ordem');
        $q->execute([$eid]);
        return $q->fetchAll();
    }

    public static function marcos(int $eid): array
    {
        $q = self::pdo()->prepare('SELECT id, empresa_id, data_marco, precisao, categoria, titulo, descricao, no_portal FROM destino_linha_tempo WHERE empresa_id = ?' . (self::previa($eid) ? '' : ' AND no_portal = 1') . ' ORDER BY data_marco, id');
        $q->execute([$eid]);
        return $q->fetchAll();
    }

    public static function roteiros(int $eid): array
    {
        // Conta só as paradas que o visitante vê (no público, as homologadas);
        // roteiro sem nenhuma visível não aparece, em vez de abrir vazio.
        $q = self::pdo()->prepare('SELECT r.id, r.empresa_id, r.titulo, r.tipo, r.tribo_id, r.descricao, r.duracao, r.publicado, r.ordem,
                                          (SELECT COUNT(*) FROM roteiro_itens ri JOIN inventario_itens i ON i.id = ri.item_id
                                           WHERE ri.roteiro_id = r.id AND ' . self::situacoes($eid) . ') AS paradas
                                   FROM roteiros r WHERE r.empresa_id = ?' . (self::previa($eid) ? '' : ' AND r.publicado = 1') . ' ORDER BY r.ordem, r.titulo');
        $q->execute([$eid]);
        return array_values(array_filter($q->fetchAll(), fn ($r) => (int) $r['paradas'] > 0));
    }

    public static function roteiro(int $eid, int $id): ?array
    {
        $q = self::pdo()->prepare('SELECT id, empresa_id, titulo, tipo, tribo_id, descricao, duracao, publicado, ordem FROM roteiros WHERE id = ? AND empresa_id = ?' . (self::previa($eid) ? '' : ' AND publicado = 1'));
        $q->execute([$id, $eid]);
        $r = $q->fetch();
        if (!$r) {
            return null;
        }
        $q = self::pdo()->prepare('SELECT item_id, nota, hora, duracao_min FROM roteiro_itens WHERE roteiro_id = ? ORDER BY ordem');
        $q->execute([$id]);
        $paradas = [];
        foreach ($q->fetchAll() as $l) {
            $paradas[(int) $l['item_id']] = $l;
        }
        $r['paradas'] = self::porIds($eid, array_keys($paradas));
        if (!$r['paradas']) {
            return null;
        }
        foreach ($r['paradas'] as $iid => &$p) {
            $p['nota'] = $paradas[$iid]['nota'] ?? null;
            $p['hora'] = $paradas[$iid]['hora'] ?? null;
            $p['duracao_min'] = $paradas[$iid]['duracao_min'] ?? null;
        }
        return $r;
    }

    /** Itens públicos pelos ids, na ordem pedida (roteiro, Meu roteiro). */
    public static function porIds(int $eid, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $marcas = implode(',', $ids);
        $q = self::pdo()->prepare("SELECT i.id, i.nome, i.bairro, i.latitude, i.longitude, i.telefone, i.whatsapp, i.descricao_promocional, i.horario, i.plus_code,
                                          f.ordem AS ficha_ordem, f.nome AS ficha_nome, f.codigo, t.nome AS tipo_nome
                                   FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
                                   WHERE i.empresa_id = ? AND i.id IN ({$marcas}) AND " . self::situacoes($eid));
        $q->execute([$eid]);
        $achados = [];
        foreach ($q->fetchAll() as $i) {
            $achados[(int) $i['id']] = $i + ['foto' => null, 'selos' => [], 'tribos' => [], 'pilar' => self::pilarDe((int) $i['ficha_ordem'])];
        }
        if ($achados) {
            self::completar($eid, $achados);
        }
        $saida = [];
        foreach ($ids as $id) {
            if (isset($achados[$id])) {
                $saida[$id] = $achados[$id];
            }
        }
        return $saida;
    }

    /** Rota no Google Maps passando pelos lugares, na ordem (até 10 paradas). */
    public static function rotaNoMapa(array $itens): ?string
    {
        $pontos = array_values(array_filter(array_map(fn ($i) => $i['latitude'] !== null ? $i['latitude'] . ',' . $i['longitude'] : null, $itens)));
        if (count($pontos) < 2) {
            return null;
        }
        $pontos = array_slice($pontos, 0, 10);
        $fim = array_pop($pontos);
        $inicio = array_shift($pontos);
        return 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($inicio) . '&destination=' . rawurlencode($fim)
            . ($pontos ? '&waypoints=' . rawurlencode(implode('|', $pontos)) : '') . '&travelmode=driving';
    }

    // ------------------------------------------------------------ guia de campo

    /** Arquivo do contorno do município para o mapa do portal (gravado pela fila, job portal_mapa). */
    private static function arquivoMapa(int $eid): string
    {
        $cfg = require __DIR__ . '/config.php';
        return rtrim((string) $cfg['caminhos']['cache'], '/') . '/portal/mapa-' . $eid . '.json';
    }

    /** Grava o contorno simplificado da divisa (IBGE) para o mapa do portal. Roda na fila. */
    public static function gravarMapa(int $eid): bool
    {
        require_once __DIR__ . '/AreaDestino.php';
        $a = AreaDestino::doDestino($eid);
        if (!$a) {
            return false;
        }
        $aneis = [];
        foreach ($a['poligonos'] as $anel) {
            $passo = max(1, (int) ceil(count($anel) / 500));
            $simples = [];
            foreach ($anel as $k => $pt) {
                if ($k % $passo === 0) {
                    $simples[] = [round((float) $pt[0], 5), round((float) $pt[1], 5)];
                }
            }
            $aneis[] = $simples;
        }
        $f = self::arquivoMapa($eid);
        @mkdir(dirname($f), 02770, true);
        return (bool) file_put_contents($f, json_encode(['aneis' => $aneis, 'municipio' => $a['municipio']]));
    }

    /** O contorno do município: ['aneis' => [[[lng, lat], ...]], 'municipio'] ou null. */
    public static function mapa(int $eid): ?array
    {
        $j = json_decode((string) @file_get_contents(self::arquivoMapa($eid)), true);
        return is_array($j) && !empty($j['aneis']) ? $j : null;
    }

    /**
     * O lugar cai dentro do retângulo da divisa do município (com folga de
     * 15%)? Coordenada digitada errada (outro estado, zero) fica de fora do
     * mapa e do "Um dia aqui". Sem divisa conhecida, vale tudo.
     */
    public static function naArea(int $eid, array $i): bool
    {
        static $caixa = [];
        if (!array_key_exists($eid, $caixa)) {
            $m = self::mapa($eid);
            $caixa[$eid] = null;
            if ($m) {
                $pts = array_merge(...$m['aneis']);
                [$x0, $x1, $y0, $y1] = [min(array_column($pts, 0)), max(array_column($pts, 0)), min(array_column($pts, 1)), max(array_column($pts, 1))];
                $fx = ($x1 - $x0) * .15;
                $fy = ($y1 - $y0) * .15;
                $caixa[$eid] = [$x0 - $fx, $x1 + $fx, $y0 - $fy, $y1 + $fy];
            }
        }
        if ($i['latitude'] === null) {
            return false;
        }
        $c = $caixa[$eid];
        return !$c || ((float) $i['longitude'] >= $c[0] && (float) $i['longitude'] <= $c[1] && (float) $i['latitude'] >= $c[2] && (float) $i['latitude'] <= $c[3]);
    }

    /**
     * Aberto agora, pelo horário da ficha: ['aberto' => bool, 'texto' => 'Aberto · fecha às 18:00']
     * ou null quando a ficha não tem horário.
     */
    public static function agora(?string $json): ?array
    {
        $h = json_decode((string) $json, true) ?: [];
        if (!empty($h['24h'])) {
            return ['aberto' => true, 'texto' => 'Aberto 24 horas'];
        }
        $dias = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'];
        if (!array_filter(array_intersect_key($h, array_flip($dias)))) {
            return null;
        }
        $hoje = (int) date('N') - 1;
        $agora = date('H:i');
        foreach ((array) ($h[$dias[$hoje]] ?? []) as $p) {
            if (is_array($p) && ($p[0] ?? '') !== '' && ($p[1] ?? '') !== '' && $agora >= $p[0] && $agora < $p[1]) {
                return ['aberto' => true, 'texto' => 'Aberto · fecha às ' . $p[1]];
            }
        }
        // Fechado: quando abre de novo (hoje mais tarde ou num dos próximos dias).
        for ($d = 0; $d < 7; $d++) {
            $dia = $dias[($hoje + $d) % 7];
            foreach ((array) ($h[$dia] ?? []) as $p) {
                if (is_array($p) && ($p[0] ?? '') !== '' && ($d > 0 || $p[0] > $agora)) {
                    $quando = $d === 0 ? 'hoje' : ($d === 1 ? 'amanhã' : ['segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado', 'domingo'][($hoje + $d) % 7]);
                    return ['aberto' => false, 'texto' => 'Fechado · abre ' . $quando . ' às ' . $p[0]];
                }
            }
        }
        return ['aberto' => false, 'texto' => 'Fechado'];
    }

    /** Distância em metros entre dois pontos. */
    public static function distancia(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /**
     * O "Um dia aqui": um dos roteiros desse tipo que a equipe montou e
     * publicou (app/UmDia.php), sorteado pela semente. Null sem roteiro.
     */
    public static function umDia(int $eid, int $semente): ?array
    {
        require_once __DIR__ . '/UmDia.php';
        return UmDia::montar($eid, $semente, self::previa($eid), fn (int $id) => self::roteiro($eid, $id));
    }

    /** O portal tem "Um dia aqui" (algo definido pela equipe)? */
    public static function temUmDia(int $eid): bool
    {
        require_once __DIR__ . '/UmDia.php';
        return UmDia::existe($eid, self::previa($eid));
    }

    /** Distância para ler: "350 m", "1,2 km". */
    public static function metros(?int $m): string
    {
        if ($m === null) {
            return '';
        }
        return $m < 1000 ? (int) (round($m / 10) * 10) . ' m' : number_format($m / 1000, 1, ',', '') . ' km';
    }

    /** Coordenada para ler: 26°10′S 52°39′O */
    public static function coordenada(float $lat, float $lng): string
    {
        $f = function (float $v, string $pos, string $neg): string {
            $a = abs($v);
            return (int) $a . '°' . str_pad((string) (int) round(($a - (int) $a) * 60), 2, '0', STR_PAD_LEFT) . '′' . ($v < 0 ? $neg : $pos);
        };
        return $f($lat, 'N', 'S') . ' ' . $f($lng, 'L', 'O');
    }

    /** Slug para o endereço: /lugar/12-hotel-bedin */
    public static function slug(string $t): string
    {
        $t = iconv('UTF-8', 'ASCII//TRANSLIT', $t) ?: $t;
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), '-');
    }
}
