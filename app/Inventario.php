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
        return $r;
    }
}
