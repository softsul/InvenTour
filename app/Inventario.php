<?php
/**
 * O inventário do destino: itens (atrativos, equipamentos, serviços), seus
 * campos específicos por ficha, acessibilidade e histórico.
 * Softsul Software
 *
 * Toda consulta leva o destino (Empresa::filtro) no SQL, nunca na tela. O
 * núcleo comum das fichas do MTur está em colunas de inventario_itens; o que
 * é próprio de cada ficha vem do catálogo inventario_atributos e o valor de
 * cada item em inventario_item_atributos (docs/PLANEJAMENTO.md, 2.4).
 *
 * Ausência de resposta (metodologia do MTur): NA não se aplica, NI não
 * informou, NE não existe. Campo vazio sem uma delas é campo por preencher.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Empresa.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Auditoria.php';

final class Inventario
{
    /** A esteira da ficha (PLANEJAMENTO, 2.5). [rótulo, tom da pílula] */
    public const SITUACOES = [
        'pre_cadastro'   => ['Pré-cadastro', 'nu'],
        'em_campo'       => ['Em campo', 'at'],
        'em_conferencia' => ['Em conferência', 'at'],
        'homologada'     => ['Homologada', 'ok'],
        'desatualizada'  => ['Desatualizada', 'at'],
        'arquivada'      => ['Arquivada', 'ruim'],
    ];
    public const CATEGORIAS = [
        'A' => 'Infraestrutura de apoio',
        'B' => 'Serviços e equipamentos',
        'C' => 'Atrativos',
    ];
    public const ZONAS = ['urbana' => 'Urbana', 'rural' => 'Rural'];
    public const NATUREZAS = ['publica' => 'Pública', 'privada' => 'Privada', 'mista' => 'Mista', 'outra' => 'Outra'];
    public const JURISDICOES = ['municipal' => 'Municipal', 'estadual' => 'Estadual', 'federal' => 'Federal', 'nao_aplicavel' => 'Não se aplica'];
    /** Escala da ficha oficial de 2011 (decisão pendente no PLANEJAMENTO, 4.2). */
    public const CONSERVACAO = ['muito_bom' => 'Muito bom', 'bom' => 'Bom', 'ruim' => 'Ruim', 'nao_aplicavel' => 'Não se aplica'];
    public const AUSENCIAS = ['na' => 'NA, não se aplica', 'ni' => 'NI, não informou', 'ne' => 'NE, não existe'];
    public const PAGAMENTOS = ['Dinheiro', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Tíquete', 'Gratuito'];
    public const IDIOMAS = ['Português', 'Inglês', 'Espanhol', 'Libras', 'Outros'];
    public const DIAS = ['seg' => 'Segunda', 'ter' => 'Terça', 'qua' => 'Quarta', 'qui' => 'Quinta', 'sex' => 'Sexta', 'sab' => 'Sábado', 'dom' => 'Domingo'];

    /**
     * Atributos de ficha que são também capacidade instalada do núcleo: o
     * valor do campo da ficha alimenta a coluna, que vai ao EventCity e ao BI.
     * [ordem da ficha => [chave do atributo => coluna]]
     */
    private const CAPACIDADES = [
        20 => ['total_uh' => 'capacidade_uh', 'total_leitos' => 'capacidade_leitos'],
        22 => ['capacidade_simultanea' => 'capacidade_assentos'],
        29 => ['capacidade_total' => 'capacidade_publico'],
    ];

    private static function pdo(): PDO
    {
        return Database::conexao();
    }

    /** Item do destino atual, ou null (o chamador responde 404). */
    public static function buscar(int $id): ?array
    {
        [$f, $a] = Empresa::filtro('i');
        $q = self::pdo()->prepare("SELECT i.*, f.codigo AS ficha_codigo, f.nome AS ficha_nome, f.categoria, f.forma, f.ordem AS ficha_ordem,
                                          f.eventcity, t.nome AS tipo_nome
                                   FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                                   LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
                                   WHERE i.id = ?{$f}");
        $q->execute([$id, ...$a]);
        return $q->fetch() ?: null;
    }

    public static function ficha(int $id): ?array
    {
        $q = self::pdo()->prepare('SELECT * FROM inventario_fichas WHERE id = ? AND ativo = 1');
        $q->execute([$id]);
        return $q->fetch() ?: null;
    }

    /** As fichas ativas agrupadas por categoria, para escolher a do item novo. */
    public static function fichasPorCategoria(): array
    {
        $grupos = [];
        foreach (self::pdo()->query('SELECT id, ordem, codigo, categoria, grupo, nome FROM inventario_fichas WHERE ativo = 1 ORDER BY ordem') as $f) {
            $grupos[$f['categoria']][] = $f;
        }
        return $grupos;
    }

    /** Tipos ativos da ficha, já com o nome do tipo antes do subtipo. */
    public static function tipos(int $fichaId): array
    {
        $q = self::pdo()->prepare('SELECT t.id, t.nome, t.definicao, t.pai_id, p.nome AS pai_nome
                                   FROM inventario_tipos t LEFT JOIN inventario_tipos p ON p.id = t.pai_id
                                   WHERE t.ficha_id = ? AND t.ativo = 1
                                   ORDER BY COALESCE(p.ordem, t.ordem), t.pai_id IS NOT NULL, t.ordem');
        $q->execute([$fichaId]);
        return $q->fetchAll();
    }

    /** Campos específicos da ficha, na ordem, agrupados pelo subtítulo. */
    public static function atributos(int $fichaId): array
    {
        $q = self::pdo()->prepare('SELECT * FROM inventario_atributos WHERE ficha_id = ? AND ativo = 1 ORDER BY ordem, id');
        $q->execute([$fichaId]);
        $grupos = [];
        foreach ($q->fetchAll() as $a) {
            $a['opcoes'] = json_decode((string) $a['opcoes'], true) ?: [];
            $grupos[$a['grupo']][] = $a;
        }
        return $grupos;
    }

    /** Valores dos campos específicos do item: [atributo_id => linha]. */
    public static function valores(int $itemId): array
    {
        [$f, $a] = Empresa::filtro();
        $q = self::pdo()->prepare("SELECT * FROM inventario_item_atributos WHERE item_id = ?{$f}");
        $q->execute([$itemId, ...$a]);
        $v = [];
        foreach ($q->fetchAll() as $l) {
            $v[(int) $l['atributo_id']] = $l;
        }
        return $v;
    }

    /** Subitens de acessibilidade da ficha oficial (seção 6). */
    public static function blocosComuns(): array
    {
        static $b = null;
        return $b ??= json_decode((string) file_get_contents(__DIR__ . '/dados/blocos-comuns-2011.json'), true) ?: [];
    }

    public static function acessibilidade(int $itemId): array
    {
        [$f, $a] = Empresa::filtro();
        $q = self::pdo()->prepare("SELECT chave, opcoes FROM inventario_item_acessibilidade WHERE item_id = ?{$f}");
        $q->execute([$itemId, ...$a]);
        return array_map(fn ($j) => json_decode((string) $j, true) ?: [], $q->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public static function historico(int $itemId): array
    {
        [$f, $a] = Empresa::filtro('h');
        $q = self::pdo()->prepare("SELECT h.*, u.nome AS usuario_nome FROM inventario_item_historico h
                                   LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.item_id = ?{$f} ORDER BY h.id DESC LIMIT 50");
        $q->execute([$itemId, ...$a]);
        return $q->fetchAll();
    }

    public static function registrarHistorico(int $itemId, string $acao, ?string $de = null, ?string $para = null, ?string $nota = null,
                                              ?float $lat = null, ?float $lng = null): void
    {
        $u = Auth::usuarioOuNulo();
        self::pdo()->prepare('INSERT INTO inventario_item_historico (item_id, empresa_id, usuario_id, acao, de, para, nota, latitude, longitude, criado_em)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$itemId, Empresa::id(), $u['id'] ?? null, $acao, $de, $para, $nota !== null ? mb_substr($nota, 0, 500) : null, $lat, $lng]);
    }

    /**
     * Grava o item (novo ou existente) com os campos do núcleo, os
     * específicos e a acessibilidade, numa transação. Devolve o id.
     *
     * @param array<string,mixed> $nucleo colunas de inventario_itens já validadas
     * @param array<int,array{texto:?string,numero:?float,ausencia:?string}> $especificos por atributo_id
     * @param array<string,list<string>> $acess por chave do subitem
     */
    public static function salvar(?int $id, int $fichaId, array $nucleo, array $especificos, array $acess): int
    {
        $pdo = self::pdo();
        $eid = Empresa::id();
        $uid = (int) (Auth::usuarioOuNulo()['id'] ?? 0) ?: null;
        $ficha = self::ficha($fichaId);
        // O campo da ficha que também é capacidade alimenta a coluna.
        foreach (self::CAPACIDADES[(int) ($ficha['ordem'] ?? 0)] ?? [] as $chave => $coluna) {
            $q = $pdo->prepare('SELECT id FROM inventario_atributos WHERE ficha_id = ? AND chave = ?');
            $q->execute([$fichaId, $chave]);
            $aid = (int) $q->fetchColumn();
            if ($aid && isset($especificos[$aid])) {
                $nucleo[$coluna] = $especificos[$aid]['numero'] !== null ? (int) $especificos[$aid]['numero'] : null;
            }
        }
        $pdo->beginTransaction();
        try {
            if ($id) {
                $antes = self::buscar($id);
                $pdo->prepare('UPDATE inventario_itens SET ' . implode(' = ?, ', array_keys($nucleo)) . ' = ?, atualizado_em = NOW(), atualizado_por = ?
                               WHERE id = ? AND empresa_id = ?')
                    ->execute([...array_values($nucleo), $uid, $id, $eid]);
            } else {
                $cols = array_keys($nucleo);
                $pdo->prepare('INSERT INTO inventario_itens (empresa_id, ficha_id, ' . implode(', ', $cols) . ', criado_em, criado_por)
                               VALUES (?, ?' . str_repeat(', ?', count($cols)) . ', NOW(), ?)')
                    ->execute([$eid, $fichaId, ...array_values($nucleo), $uid]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM inventario_item_atributos WHERE item_id = ? AND empresa_id = ?')->execute([$id, $eid]);
            $ins = $pdo->prepare('INSERT INTO inventario_item_atributos (item_id, atributo_id, empresa_id, valor_texto, valor_numero, ausencia) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($especificos as $aid => $v) {
                if ($v['texto'] !== null || $v['numero'] !== null || $v['ausencia'] !== null) {
                    $ins->execute([$id, $aid, $eid, $v['texto'], $v['numero'], $v['ausencia']]);
                }
            }
            $pdo->prepare('DELETE FROM inventario_item_acessibilidade WHERE item_id = ? AND empresa_id = ?')->execute([$id, $eid]);
            $insA = $pdo->prepare('INSERT INTO inventario_item_acessibilidade (item_id, empresa_id, chave, opcoes) VALUES (?, ?, ?, ?)');
            foreach ($acess as $chave => $opcoes) {
                if ($opcoes) {
                    $insA->execute([$id, $eid, $chave, json_encode(array_values($opcoes), JSON_UNESCAPED_UNICODE)]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        if (empty($antes)) {
            self::registrarHistorico($id, 'criou', null, (string) ($nucleo['situacao'] ?? 'em_campo'));
            Auditoria::registrar('criou', 'inventario_itens', $id, 'cadastrou ' . $nucleo['nome'] . ' no inventário', null, null, $eid);
        } else {
            $mudouSituacao = isset($nucleo['situacao']) && $nucleo['situacao'] !== $antes['situacao'];
            self::registrarHistorico($id, $mudouSituacao ? 'mudou_situacao' : 'alterou', $mudouSituacao ? $antes['situacao'] : null,
                $mudouSituacao ? (string) $nucleo['situacao'] : null);
            Auditoria::registrar('alterou', 'inventario_itens', $id, 'alterou ' . $nucleo['nome'] . ' no inventário',
                array_intersect_key($antes, $nucleo), $nucleo, $eid);
        }
        return $id;
    }

    // ------------------------------------------------------- TRIBOS DE CONSUMO

    /** Tribos ativas com as facilidades, para o formulário do item. */
    public static function tribos(): array
    {
        $tribos = [];
        foreach (self::pdo()->query('SELECT t.id AS tribo_id, t.macro, t.nome AS tribo, t.icone, f.id, f.nome
                                     FROM tribos t JOIN tribo_facilidades f ON f.tribo_id = t.id AND f.ativo = 1
                                     WHERE t.ativo = 1 ORDER BY t.ordem, f.ordem') as $l) {
            $tribos[(int) $l['tribo_id']] ??= ['macro' => $l['macro'], 'nome' => $l['tribo'], 'icone' => $l['icone'], 'facilidades' => []];
            $tribos[(int) $l['tribo_id']]['facilidades'][(int) $l['id']] = $l['nome'];
        }
        return $tribos;
    }

    /** Ids das facilidades marcadas no item. */
    public static function facilidades(int $itemId): array
    {
        [$f, $a] = Empresa::filtro();
        $q = self::pdo()->prepare("SELECT facilidade_id FROM inventario_item_facilidades WHERE item_id = ?{$f}");
        $q->execute([$itemId, ...$a]);
        return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function salvarFacilidades(int $itemId, array $ids): void
    {
        $eid = Empresa::id();
        $validos = array_map('intval', self::pdo()->query('SELECT id FROM tribo_facilidades WHERE ativo = 1')->fetchAll(PDO::FETCH_COLUMN));
        $ids = array_values(array_intersect($validos, array_map('intval', $ids)));
        self::pdo()->prepare('DELETE FROM inventario_item_facilidades WHERE item_id = ? AND empresa_id = ?')->execute([$itemId, $eid]);
        $ins = self::pdo()->prepare('INSERT INTO inventario_item_facilidades (item_id, facilidade_id, empresa_id) VALUES (?, ?, ?)');
        foreach ($ids as $fid) {
            $ins->execute([$itemId, $fid, $eid]);
        }
    }

    /** Tribos cujo selo o item ganha: pelo menos 2 facilidades marcadas. */
    public static function selosDeTribo(int $itemId): array
    {
        [$f, $a] = Empresa::filtro('i');
        $q = self::pdo()->prepare("SELECT t.nome, t.icone, COUNT(*) n FROM inventario_item_facilidades i
                                   JOIN tribo_facilidades f ON f.id = i.facilidade_id JOIN tribos t ON t.id = f.tribo_id
                                   WHERE i.item_id = ?{$f} GROUP BY t.id HAVING n >= 2 ORDER BY t.ordem");
        $q->execute([$itemId, ...$a]);
        return $q->fetchAll();
    }

    // ---------------------------------------------------- PRÉ-CADASTRO CADASTUR

    /**
     * Atividade do Cadastur => ordem da ficha do MTur em que o prestador
     * entra (docs/PLANEJAMENTO.md, 2.4). O pré-cadastro poupa a digitação do
     * que a base oficial já tem; o pesquisador confere em campo.
     */
    public const CADASTUR_FICHAS = [
        'Meio de Hospedagem'                                             => 20,
        'Acampamento Turístico'                                          => 21,
        'Restaurante, Cafeteria, Bar e Similares'                        => 22,
        'Agência de Turismo'                                             => 23,
        'Transportadora Turística'                                       => 24,
        'Locadora de Veículos para Turistas'                             => 24,
        'Centro de Convenções'                                           => 25,
        'Organizadora de Eventos'                                        => 26,
        'Prestador de Infraestrutura de Apoio para Eventos'              => 26,
        'Parque Aquático e Empreendimento de Lazer'                      => 27,
        'Parque Temático'                                                => 27,
        'Empreendimento de Apoio ao Turismo Náutico ou à Pesca Desportiva' => 30,
        'Casa de Espetáculos e Equipamento de Animação Turística'        => 31,
        'Guia de Turismo'                                                => 35,
        'Prestador Especializado em Segmentos Turísticos'                => 35,
    ];

    /** Prestadores do Cadastur no município do destino, por atividade, e quantos já estão no inventário. */
    public static function resumoCadastur(int $empresaId): array
    {
        $q = self::pdo()->prepare("SELECT c.atividade, COUNT(*) total,
                                          SUM(EXISTS (SELECT 1 FROM inventario_itens i WHERE i.empresa_id = e.id
                                                      AND (i.cadastur_prestador_id = c.id OR (c.tipo_documento = 'cnpj' AND i.cnpj = c.documento)))) ja
                                   FROM empresas e JOIN cadastur_prestadores c ON c.municipio_id = e.municipio_id
                                   WHERE e.id = ? AND COALESCE(c.situacao_cadastral, 'Regular') <> 'Cancelado'
                                   GROUP BY c.atividade ORDER BY total DESC");
        $q->execute([$empresaId]);
        return $q->fetchAll();
    }

    /**
     * Cria um item em pré-cadastro para cada prestador das atividades
     * escolhidas que ainda não está no inventário do destino. Guia pessoa
     * física entra só com o nome: o CPF não vai para o inventário (LGPD).
     * Devolve quantos entraram.
     */
    public static function preCadastrarCadastur(array $atividades): int
    {
        $eid = Empresa::id();
        $pdo = self::pdo();
        $fichaPorOrdem = $pdo->query('SELECT ordem, id FROM inventario_fichas')->fetchAll(PDO::FETCH_KEY_PAIR);
        $atividades = array_values(array_intersect(array_keys(self::CADASTUR_FICHAS), $atividades));
        if (!$atividades) {
            return 0;
        }
        $q = $pdo->prepare('SELECT c.*, m.nome AS municipio_nome FROM cadastur_prestadores c
                            JOIN empresas e ON e.municipio_id = c.municipio_id AND e.id = ?
                            JOIN municipios m ON m.id = c.municipio_id
                            WHERE c.atividade IN (' . implode(',', array_fill(0, count($atividades), '?')) . ")
                              AND COALESCE(c.situacao_cadastral, 'Regular') <> 'Cancelado'
                              AND NOT EXISTS (SELECT 1 FROM inventario_itens i WHERE i.empresa_id = e.id
                                              AND (i.cadastur_prestador_id = c.id OR (c.tipo_documento = 'cnpj' AND i.cnpj = c.documento)))");
        $q->execute([$eid, ...$atividades]);
        $uid = Auth::usuarioOuNulo()['id'] ?? null;
        $ins = $pdo->prepare("INSERT INTO inventario_itens (empresa_id, ficha_id, situacao, origem, nome, razao_social, cnpj, cadastur, cadastur_validade,
                                     cadastur_prestador_id, logradouro, bairro, cep, telefone, email, site, capacidade_uh, capacidade_leitos, criado_em, criado_por)
                              VALUES (?, ?, 'pre_cadastro', 'cadastur', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $n = 0;
        foreach ($q->fetchAll() as $c) {
            $fichaId = (int) ($fichaPorOrdem[self::CADASTUR_FICHAS[$c['atividade']]] ?? 0);
            if (!$fichaId) {
                continue;
            }
            $pj = $c['tipo_documento'] === 'cnpj';
            // "Av. Tupi 2566 Pato Branco Centro CEP: 85501065 PR": o que vem
            // antes do município é o logradouro; entre ele e o CEP, o bairro.
            $end = (string) $c['endereco'];
            $cep = preg_match('/CEP:?\s*(\d{5})-?(\d{3})/', $end, $m) ? $m[1] . $m[2] : null;
            $logradouro = $bairro = null;
            $pos = mb_stripos($end, (string) $c['municipio_nome']);
            if ($pos !== false) {
                $logradouro = trim(mb_substr($end, 0, $pos)) ?: null;
                $resto = trim(mb_substr($end, $pos + mb_strlen((string) $c['municipio_nome'])));
                $bairro = trim((string) preg_replace('/\s*CEP:?.*$/', '', $resto)) ?: null;
            } elseif ($end !== '') {
                $logradouro = trim((string) preg_replace('/\s*CEP:?.*$/', '', $end));
            }
            $hosp = $c['atividade'] === 'Meio de Hospedagem';
            $ins->execute([
                $eid, $fichaId,
                mb_substr((string) ($c['nome_fantasia'] ?: $c['nome_exibicao'] ?: $c['nome_juridico']), 0, 180),
                $pj ? mb_substr((string) $c['nome_juridico'], 0, 180) : null,
                $pj ? $c['documento'] : null,
                $c['certificado'] ?: null, $c['validade'] ?: null, $c['id'],
                $logradouro ? mb_substr($logradouro, 0, 200) : null, $bairro ? mb_substr($bairro, 0, 120) : null, $cep,
                telefoneParaBanco($c['telefone'] ?? null), $c['email'] ? mb_strtolower(mb_substr((string) $c['email'], 0, 150)) : null,
                $c['site'] ? mb_substr((string) $c['site'], 0, 255) : null,
                $hosp && $c['unidades'] ? (int) $c['unidades'] : null, $hosp && $c['leitos'] ? (int) $c['leitos'] : null, $uid,
            ]);
            self::registrarHistorico((int) $pdo->lastInsertId(), 'importou', null, 'pre_cadastro', 'Cadastur: ' . $c['atividade']);
            $n++;
        }
        if ($n) {
            Auditoria::registrar('criou', 'inventario_itens', null, 'pré-cadastrou ' . $n . ' prestadores do Cadastur no inventário', null, null, $eid);
        }
        return $n;
    }

    // ----------------------------------------------------------------- FOTOS

    /** Pasta das fotos do destino, servida por /midia/inventario/<empresa>/. */
    private static function pastaFotos(int $empresaId): string
    {
        $cfg = require __DIR__ . '/config.php';
        $pasta = rtrim((string) $cfg['caminhos']['midias'], '/') . '/inventario/' . $empresaId;
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0770, true);
        }
        return $pasta;
    }

    public static function urlFoto(int $empresaId, string $arquivo): string
    {
        return '/midia/inventario/' . $empresaId . '/' . rawurlencode(basename($arquivo));
    }

    public static function fotos(int $itemId): array
    {
        [$f, $a] = Empresa::filtro();
        $q = self::pdo()->prepare("SELECT * FROM inventario_midias WHERE item_id = ? AND experiencia_id IS NULL{$f} ORDER BY capa DESC, ordem, id");
        $q->execute([$itemId, ...$a]);
        return $q->fetchAll();
    }

    /**
     * Guarda uma foto enviada: lê o GPS do EXIF, redesenha a imagem sem
     * metadados (Imagem::foto) e grava. A primeira vira capa. Se o item não
     * tem coordenada, a da foto passa a valer. Devolve o erro ou null.
     */
    public static function guardarFoto(int $itemId, array $arquivo, ?string $credito, ?int $experienciaId = null): ?string
    {
        require_once __DIR__ . '/Imagem.php';
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $arquivo['tmp_name'])) {
            return 'A foto não chegou inteira.';
        }
        if ((int) $arquivo['size'] > 20 * 1048576) {
            return 'A foto passa de 20 MB.';
        }
        $dados = (string) file_get_contents((string) $arquivo['tmp_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($dados);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return 'Use fotos em JPG, PNG ou WebP.';
        }
        $gps = $mime === 'image/jpeg' ? Imagem::gpsDoExif($dados) : null;
        $foto = Imagem::foto($dados);
        if ($foto === null) {
            return 'O arquivo não é uma imagem válida.';
        }
        [$bytes, $l, $a] = $foto;
        $eid = Empresa::id();
        $nome = $itemId . '-' . bin2hex(random_bytes(6)) . '.jpg';
        if (@file_put_contents(self::pastaFotos($eid) . '/' . $nome, $bytes) === false) {
            return 'Não consegui guardar a foto agora.';
        }
        $pdo = self::pdo();
        $q = $pdo->prepare('SELECT COUNT(*) FROM inventario_midias WHERE item_id = ? AND empresa_id = ? AND experiencia_id <=> ?');
        $q->execute([$itemId, $eid, $experienciaId]);
        $ja = (int) $q->fetchColumn();
        $pdo->prepare('INSERT INTO inventario_midias (empresa_id, item_id, experiencia_id, arquivo, largura, altura, credito, capa, ordem, latitude, longitude, criado_em, criado_por)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)')
            ->execute([$eid, $itemId, $experienciaId, $nome, $l, $a, $credito, $ja === 0 ? 1 : 0, $ja + 1, $gps[0] ?? null, $gps[1] ?? null,
                       Auth::usuarioOuNulo()['id'] ?? null]);
        if ($gps && $experienciaId === null) {
            $pdo->prepare('UPDATE inventario_itens SET latitude = ?, longitude = ? WHERE id = ? AND empresa_id = ? AND latitude IS NULL')
                ->execute([$gps[0], $gps[1], $itemId, $eid]);
        }
        return null;
    }

    public static function definirCapa(int $itemId, int $fotoId): void
    {
        $eid = Empresa::id();
        $pdo = self::pdo();
        $pdo->prepare('UPDATE inventario_midias SET capa = (id = ?) WHERE item_id = ? AND empresa_id = ? AND experiencia_id IS NULL')
            ->execute([$fotoId, $itemId, $eid]);
    }

    public static function legendarFoto(int $itemId, int $fotoId, ?string $legenda, ?string $credito): void
    {
        self::pdo()->prepare('UPDATE inventario_midias SET legenda = ?, credito = ? WHERE id = ? AND item_id = ? AND empresa_id = ?')
            ->execute([$legenda, $credito, $fotoId, $itemId, Empresa::id()]);
    }

    public static function excluirFoto(int $itemId, int $fotoId): void
    {
        $eid = Empresa::id();
        $q = self::pdo()->prepare('SELECT arquivo, capa FROM inventario_midias WHERE id = ? AND item_id = ? AND empresa_id = ?');
        $q->execute([$fotoId, $itemId, $eid]);
        if (!($f = $q->fetch())) {
            return;
        }
        self::pdo()->prepare('DELETE FROM inventario_midias WHERE id = ? AND empresa_id = ?')->execute([$fotoId, $eid]);
        @unlink(self::pastaFotos($eid) . '/' . basename((string) $f['arquivo']));
        if ((int) $f['capa']) {
            self::pdo()->prepare('UPDATE inventario_midias SET capa = 1 WHERE item_id = ? AND empresa_id = ? AND experiencia_id IS NULL ORDER BY ordem, id LIMIT 1')
                ->execute([$itemId, $eid]);
        }
    }

    /** Totais do destino para o Início: por situação e por categoria. */
    public static function resumo(int $empresaId): array
    {
        $q = self::pdo()->prepare('SELECT i.situacao, f.categoria, COUNT(*) n FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                                   WHERE i.empresa_id = ? GROUP BY i.situacao, f.categoria');
        $q->execute([$empresaId]);
        $r = ['total' => 0, 'situacao' => array_fill_keys(array_keys(self::SITUACOES), 0), 'categoria' => ['A' => 0, 'B' => 0, 'C' => 0]];
        foreach ($q->fetchAll() as $l) {
            $r['total'] += (int) $l['n'];
            $r['situacao'][$l['situacao']] += (int) $l['n'];
            if ($l['situacao'] !== 'arquivada') {
                $r['categoria'][$l['categoria']] += (int) $l['n'];
            }
        }
        $q = self::pdo()->prepare("SELECT COALESCE(SUM(capacidade_uh), 0), COALESCE(SUM(capacidade_leitos), 0) FROM inventario_itens
                                   WHERE empresa_id = ? AND situacao IN ('homologada', 'desatualizada')");
        $q->execute([$empresaId]);
        [$r['uh'], $r['leitos']] = array_map('intval', $q->fetch(PDO::FETCH_NUM));
        // De onde veio cada item (Cadastur, caderno importado, painel, campo).
        $q = self::pdo()->prepare("SELECT origem, COUNT(*) FROM inventario_itens WHERE empresa_id = ? AND situacao <> 'arquivada' GROUP BY origem");
        $q->execute([$empresaId]);
        $r['origem'] = array_map('intval', $q->fetchAll(PDO::FETCH_KEY_PAIR));
        return $r;
    }
}
