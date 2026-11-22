<?php
/**
 * Relatórios gerenciais (etapa 7): uma lista só, e a mesma tela para todos.
 * Softsul Software
 *
 * O mesmo motor do EventCity e do Orbit: cada relatório é uma definição
 * (nome, quem pode ver, que filtros aceita, colunas e a consulta que as
 * preenche). A tela, o CSV e o PDF (public/relatorio.php) valem para todos:
 * relatório novo é uma entrada aqui, não uma tela nova.
 *
 * Toda consulta passa pelo destino (Empresa::filtro) e, para quem tem
 * escopo de fichas, pelo escopo (Esteira::filtroEscopo): relatório é o
 * lugar mais fácil de vazar dado, porque é onde tudo aparece junto.
 *
 * `sem_periodo`: o relatório é a situação de hoje (o inventário como está),
 * sem filtro de datas. `categoria`: liga o filtro de categoria do MTur.
 */
declare(strict_types=1);
require_once __DIR__ . '/EventCity.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Empresa.php';
require_once __DIR__ . '/Esteira.php';
require_once __DIR__ . '/Inventario.php';

final class Relatorio
{
    /** Grupos na ordem em que aparecem na tela inicial. */
    public const GRUPOS = [
        'inventario' => 'Inventário da oferta',
        'publico'    => 'Público e portal',
        'gestao'     => 'Gestão e evolução',
    ];

    /** @return array<string,array> */
    public static function todos(): array
    {
        return [
            'lugares' => [
                'nome' => 'Lugares do inventário', 'grupo' => 'inventario', 'icone' => 'mapa', 'permissao' => 'relatorios.ver',
                'descricao' => 'Lugar a lugar: ficha, tipo, bairro, situação na esteira, Cadastur e a última alteração.',
                'data' => 'situação de hoje', 'sem_periodo' => true, 'categoria' => true, 'deitado' => true,
                'situacoes' => ['' => 'Todos', 'publicos' => 'No portal', 'esteira' => 'Na esteira', 'pre_cadastro' => 'Pré-cadastro'],
            ],
            'capacidade' => [
                'nome' => 'Capacidade instalada', 'grupo' => 'inventario', 'icone' => 'predio', 'permissao' => 'relatorios.ver',
                'descricao' => 'Por tipo de lugar homologado: quantos são, unidades habitacionais, leitos, lugares sentados e público simultâneo.',
                'data' => 'situação de hoje', 'sem_periodo' => true, 'categoria' => true,
            ],
            'cadastur' => [
                'nome' => 'Formalização no Cadastur', 'grupo' => 'inventario', 'icone' => 'certificado', 'permissao' => 'relatorios.ver',
                'descricao' => 'Lugares com e sem Cadastur e os vencidos, para a busca ativa e para o indicador do diagnóstico DTI.',
                'data' => 'situação de hoje', 'sem_periodo' => true, 'categoria' => true,
                'situacoes' => ['' => 'Todos', 'regular' => 'Regular', 'vencido' => 'Vencido', 'sem' => 'Sem Cadastur'],
            ],
            'acessibilidade' => [
                'nome' => 'Acessibilidade', 'grupo' => 'inventario', 'icone' => 'cadeirante', 'permissao' => 'relatorios.ver',
                'descricao' => 'Por ficha: quantos lugares homologados têm acessibilidade registrada e quantos não.',
                'data' => 'situação de hoje', 'sem_periodo' => true, 'categoria' => true,
            ],
            'conservacao' => [
                'nome' => 'Conservação dos atrativos', 'grupo' => 'inventario', 'icone' => 'natureza', 'permissao' => 'relatorios.ver',
                'descricao' => 'Atrativos homologados com o estado de conservação anotado em campo, os ruins primeiro.',
                'data' => 'situação de hoje', 'sem_periodo' => true,
                'situacoes' => ['' => 'Todos', 'ruim' => 'Ruins', 'bom' => 'Bons', 'muito_bom' => 'Muito bons', 'sem' => 'Sem informação'],
            ],
            'radar' => [
                'nome' => 'Radar de obsolescência', 'grupo' => 'inventario', 'icone' => 'alerta', 'permissao' => 'relatorios.ver',
                'descricao' => 'Os lugares desatualizados (sem conferência há mais de 180 dias ou com o Cadastur vencido) e o último aviso mandado ao estabelecimento.',
                'data' => 'situação de hoje', 'sem_periodo' => true, 'categoria' => true,
                'situacoes' => ['' => 'Todos', 'sem_aviso' => 'Sem aviso', 'sem_resposta' => 'Aviso sem resposta', 'atualizar' => 'Pediram para atualizar'],
                'explicacao_titulo' => 'Como o radar funciona',
                'explicacao' => 'Todo dia, às 2h, o lugar homologado sem conferência há mais de 180 dias, ou com o Cadastur vencido, vira desatualizado: '
                    . 'continua no portal com o selo "Dados em atualização". Em destino ativo, quem tem e-mail (ou WhatsApp, com o envio ligado) na ficha '
                    . 'recebe um link de uso único por 15 dias; "Está tudo igual" renova a conferência sozinho. Sem contato na ficha, a equipe confere em campo.',
            ],
            'homologacoes' => [
                'nome' => 'Homologações', 'grupo' => 'inventario', 'icone' => 'check', 'permissao' => 'relatorios.ver',
                'descricao' => 'Cada lugar homologado no período, com a ficha e quem coletou.',
                'data' => 'dia da homologação', 'categoria' => true,
            ],
            'engajamento' => [
                'nome' => 'Engajamento por lugar', 'grupo' => 'publico', 'icone' => 'olho', 'permissao' => 'relatorios.ver',
                'descricao' => 'O que o público fez na ficha de cada lugar no portal: visualizações, rota, WhatsApp, ligação, site e roteiro. Sem cookie.',
                'data' => 'dia da visita', 'categoria' => true, 'deitado' => true,
            ],
            'qr' => [
                'nome' => 'Leituras de QR', 'grupo' => 'publico', 'icone' => 'qrcode', 'permissao' => 'phygital.ver',
                'descricao' => 'Quantas vezes o QR de cada ponto foi lido, sem identificar quem leu.',
                'data' => 'dia da leitura',
            ],
            'experiencias' => [
                'nome' => 'Experiências', 'grupo' => 'publico', 'icone' => 'natureza', 'permissao' => 'relatorios.ver',
                'descricao' => 'As experiências cadastradas no período, com o lugar, a situação na curadoria e a faixa de preço.',
                'data' => 'dia do cadastro',
                'situacoes' => ['' => 'Todas', 'aprovada' => 'Aprovadas', 'aguardando' => 'Aguardando curadoria', 'ajuste' => 'Ajuste pedido'],
            ],
            'evolucao' => [
                'nome' => 'Evolução mês a mês', 'grupo' => 'gestao', 'icone' => 'calendario', 'permissao' => 'relatorios.ver',
                'descricao' => 'A fotografia mensal do destino: lugares no portal, leitos, Cadastur, acessibilidade, experiências, roteiros e visitas.',
                'data' => 'mês da fotografia', 'deitado' => true,
                'explicacao_titulo' => 'De onde vêm os números',
                'explicacao' => 'Todo mês a fila tira um retrato do destino (destino_fotografias) e ele não muda mais: é o registro de como o '
                    . 'inventário estava. Lugares no portal são os homologados e os desatualizados; leitos e UH contam só a hospedagem; '
                    . 'páginas e leituras de QR são as do mês, sem cookie.',
            ],
            'leitos_eventos' => [
                'nome' => 'Leitos e eventos', 'grupo' => 'gestao', 'icone' => 'calendario', 'permissao' => 'relatorios.ver',
                'descricao' => 'Noite a noite, nos próximos 90 dias: os turistas que os eventos do EventCity esperam contra os leitos da hospedagem no portal.',
                'data' => 'próximos 90 dias', 'sem_periodo' => true,
                'situacoes' => ['' => 'Todas as noites', 'risco' => 'Faltam leitos ou atenção', 'falta' => 'Faltam leitos'],
                'explicacao_titulo' => 'Como é calculado',
                'explicacao' => 'Entram os eventos da agenda do EventCity com o número de visitantes turistas informado pelo proponente: quem ocupa leito é o turista, '
                    . 'não o morador nem o excursionista que volta no mesmo dia. Cada dia do evento conta como uma noite e os eventos da mesma noite se somam. '
                    . 'Os leitos são os da hospedagem homologada no inventário, sem descontar a ocupação de rotina dos hotéis. '
                    . 'A partir de ' . EventCity::ATENCAO . '% dos leitos a noite fica em atenção; acima dos leitos, faltam leitos. '
                    . 'Evento turístico sem a divisão do público fica fora da conta até o proponente dividir no EventCity.',
            ],
            'dti' => [
                'nome' => 'Diagnóstico DTI', 'grupo' => 'gestao', 'icone' => 'emblema', 'permissao' => 'diagnostico.ver',
                'descricao' => 'Os 105 requisitos do DTI Brasil no diagnóstico mais recente: eixo, nível de conformidade, evidência, fonte e data.',
                'data' => 'diagnóstico mais recente', 'sem_periodo' => true, 'deitado' => true,
                'situacoes' => ['' => 'Todos', 'nao_conforme' => 'Níveis 1 e 2', 'observacao' => 'Nível 3', 'conforme' => 'Níveis 4 e 5', 'sem' => 'Sem resposta'],
            ],
            'dti_plano' => [
                'nome' => 'Plano de Transformação', 'grupo' => 'gestao', 'icone' => 'lista', 'permissao' => 'diagnostico.ver',
                'descricao' => 'Do diagnóstico DTI mais recente: os requisitos abaixo da conformidade e os que têm ações, com responsável e prazo.',
                'data' => 'diagnóstico mais recente', 'sem_periodo' => true, 'deitado' => true,
            ],
            'trade' => [
                'nome' => 'Trade turístico', 'grupo' => 'gestao', 'icone' => 'negocios', 'permissao' => 'trade.ver',
                'descricao' => 'Os pedidos de responsável e as revisões que os responsáveis mandaram, com a decisão da equipe.',
                'data' => 'dia do pedido',
            ],
        ];
    }

    /** Um relatório pela chave, se a pessoa puder vê-lo. */
    public static function achar(string $chave): ?array
    {
        $r = self::todos()[$chave] ?? null;
        if (!$r || !Auth::pode((string) $r['permissao'])) {
            return null;
        }
        return $r + ['chave' => $chave];
    }

    /** @return array{colunas:array<int,array>, linhas:array<int,array>, totais:array<string,float>} */
    public static function rodar(string $chave, array $filtro): array
    {
        $metodo = 'rel' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $chave)));
        if (!method_exists(self::class, $metodo)) {
            throw new RuntimeException('Relatório desconhecido.');
        }
        $definicao = self::$metodo($filtro);
        [$colunas, $sql, $par] = $definicao;
        $depois = $definicao[3] ?? null;
        $q = Database::conexao()->prepare($sql);
        $q->execute($par);
        $linhas = $q->fetchAll();
        if (is_callable($depois)) {
            $linhas = $depois($linhas);
        }
        $totais = [];
        foreach ($colunas as $c) {
            if (!empty($c['soma'])) {
                $totais[$c['chave']] = 0.0;
            }
        }
        foreach ($linhas as $l) {
            foreach ($totais as $k => $_) {
                $totais[$k] += (float) ($l[$k] ?? 0);
            }
        }
        return ['colunas' => $colunas, 'linhas' => $linhas, 'totais' => $totais];
    }

    /** Destino, escopo de fichas e as categorias escolhidas na tela, sobre os alias do item e da ficha. */
    private static function recortes(array $f, string $i = 'i', string $fi = 'f'): array
    {
        [$sql, $par] = Empresa::filtro($i);
        [$se, $pe] = Esteira::filtroEscopo($fi);
        $sql .= $se;
        array_push($par, ...$pe);
        $cats = array_values(array_intersect(['A', 'B', 'C'], (array) ($f['categoria'] ?? [])));
        if ($cats) {
            $sql .= " AND {$fi}.categoria IN (" . implode(',', array_fill(0, count($cats), '?')) . ')';
            array_push($par, ...$cats);
        }
        return [$sql, $par];
    }

    private const PUBLICOS = "('homologada', 'desatualizada')";

    // ------------------------------------------------------------ inventário

    private static function relLugares(array $f): array
    {
        [$fr, $pr] = self::recortes($f);
        $situacao = match ($f['situacao'] ?? '') {
            'publicos'     => ' AND i.situacao IN ' . self::PUBLICOS,
            'esteira'      => " AND i.situacao IN ('em_campo', 'em_conferencia')",
            'pre_cadastro' => " AND i.situacao = 'pre_cadastro'",
            default        => " AND i.situacao <> 'arquivada'",
        };
        $rotulos = array_map(fn ($s) => $s[0], Inventario::SITUACOES);
        return [
            [
                ['chave' => 'nome', 'titulo' => 'Lugar', 'tipo' => 'texto', 'sub' => 'tipo_nome', 'sub_titulo' => 'Tipo'],
                ['chave' => 'ficha', 'titulo' => 'Ficha', 'tipo' => 'texto'],
                ['chave' => 'bairro', 'titulo' => 'Bairro', 'tipo' => 'texto'],
                ['chave' => 'situacao', 'titulo' => 'Situação', 'tipo' => 'texto'],
                ['chave' => 'cadastur', 'titulo' => 'Cadastur', 'tipo' => 'texto'],
                ['chave' => 'alterado', 'titulo' => 'Última alteração', 'tipo' => 'data'],
            ],
            "SELECT f.codigo AS ficha, i.nome, t.nome AS tipo_nome, i.bairro, i.situacao, i.cadastur, COALESCE(i.atualizado_em, i.criado_em) AS alterado
             FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
             WHERE 1 = 1{$situacao}{$fr} ORDER BY f.ordem, i.nome",
            $pr,
            fn (array $linhas) => array_map(fn ($l) => ['situacao' => $rotulos[$l['situacao']] ?? $l['situacao']] + $l, $linhas),
        ];
    }

    private static function relCapacidade(array $f): array
    {
        [$fr, $pr] = self::recortes($f);
        return [
            [
                ['chave' => 'ficha', 'titulo' => 'Ficha', 'tipo' => 'texto'],
                ['chave' => 'tipo', 'titulo' => 'Tipo', 'tipo' => 'texto'],
                ['chave' => 'lugares', 'titulo' => 'Lugares', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'uh', 'titulo' => 'UH', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'leitos', 'titulo' => 'Leitos', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'assentos', 'titulo' => 'Lugares sentados', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'publico', 'titulo' => 'Público simultâneo', 'tipo' => 'numero', 'soma' => true],
            ],
            "SELECT CONCAT(f.codigo, ' ', f.nome) AS ficha, COALESCE(t.nome, 'Sem tipo') AS tipo, COUNT(*) AS lugares,
                    COALESCE(SUM(i.capacidade_uh), 0) uh, COALESCE(SUM(i.capacidade_leitos), 0) leitos,
                    COALESCE(SUM(i.capacidade_assentos), 0) assentos, COALESCE(SUM(i.capacidade_publico), 0) publico
             FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
             WHERE i.situacao IN " . self::PUBLICOS . "{$fr} GROUP BY f.id, t.id ORDER BY f.ordem, tipo",
            $pr,
        ];
    }

    private static function relCadastur(array $f): array
    {
        [$fr, $pr] = self::recortes($f);
        $sit = match ($f['situacao'] ?? '') {
            'regular' => " AND i.cadastur <> '' AND (i.cadastur_validade IS NULL OR i.cadastur_validade >= CURDATE())",
            'vencido' => " AND i.cadastur <> '' AND i.cadastur_validade < CURDATE()",
            'sem'     => " AND (i.cadastur IS NULL OR i.cadastur = '')",
            default   => '',
        };
        return [
            [
                ['chave' => 'nome', 'titulo' => 'Lugar', 'tipo' => 'texto'],
                ['chave' => 'ficha', 'titulo' => 'Ficha', 'tipo' => 'texto'],
                ['chave' => 'cadastur', 'titulo' => 'Cadastur', 'tipo' => 'texto'],
                ['chave' => 'cadastur_validade', 'titulo' => 'Validade', 'tipo' => 'data'],
                ['chave' => 'estado', 'titulo' => 'Situação', 'tipo' => 'texto'],
            ],
            "SELECT f.codigo AS ficha, i.nome, i.cadastur, i.cadastur_validade,
                    CASE WHEN i.cadastur IS NULL OR i.cadastur = '' THEN 'sem Cadastur'
                         WHEN i.cadastur_validade < CURDATE() THEN 'vencido' ELSE 'regular' END AS estado
             FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
             WHERE i.situacao IN " . self::PUBLICOS . " AND f.categoria = 'B'{$sit}{$fr} ORDER BY estado, f.ordem, i.nome",
            $pr,
        ];
    }

    private static function relAcessibilidade(array $f): array
    {
        [$fr, $pr] = self::recortes($f);
        return [
            [
                ['chave' => 'ficha', 'titulo' => 'Ficha', 'tipo' => 'texto'],
                ['chave' => 'lugares', 'titulo' => 'Lugares', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'com', 'titulo' => 'Com acessibilidade', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'sem', 'titulo' => 'Sem', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'sem_info', 'titulo' => 'Sem informação', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'parte', 'titulo' => 'Com acessibilidade', 'tipo' => 'percentual'],
            ],
            "SELECT CONCAT(f.codigo, ' ', f.nome) AS ficha, COUNT(*) AS lugares, COALESCE(SUM(i.acessibilidade_possui = 1), 0) AS com,
                    COALESCE(SUM(i.acessibilidade_possui = 0), 0) AS sem, COALESCE(SUM(i.acessibilidade_possui IS NULL), 0) AS sem_info,
                    100 * COALESCE(SUM(i.acessibilidade_possui = 1), 0) / COUNT(*) AS parte
             FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
             WHERE i.situacao IN " . self::PUBLICOS . " AND f.forma <> 'municipio'{$fr} GROUP BY f.id ORDER BY f.ordem",
            $pr,
        ];
    }

    private static function relConservacao(array $f): array
    {
        [$fr, $pr] = self::recortes(['categoria' => ['C']] + $f);
        $sit = match ($f['situacao'] ?? '') {
            'ruim', 'bom', 'muito_bom' => " AND i.conservacao = '" . $f['situacao'] . "'",
            'sem' => ' AND i.conservacao IS NULL',
            default => '',
        };
        return [
            [
                ['chave' => 'nome', 'titulo' => 'Atrativo', 'tipo' => 'texto', 'sub' => 'tipo_nome', 'sub_titulo' => 'Tipo'],
                ['chave' => 'ficha', 'titulo' => 'Ficha', 'tipo' => 'texto'],
                ['chave' => 'estado', 'titulo' => 'Conservação', 'tipo' => 'texto'],
                ['chave' => 'coletado_em', 'titulo' => 'Coletado em', 'tipo' => 'data'],
            ],
            "SELECT f.codigo AS ficha, i.nome, t.nome AS tipo_nome, i.conservacao, i.coletado_em
             FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
             WHERE i.situacao IN " . self::PUBLICOS . "{$sit}{$fr}
             ORDER BY FIELD(COALESCE(i.conservacao, 'z'), 'ruim', 'bom', 'muito_bom', 'nao_aplicavel', 'z'), i.nome",
            $pr,
            fn (array $linhas) => array_map(fn ($l) => ['estado' => Inventario::CONSERVACAO[$l['conservacao'] ?? ''] ?? 'Sem informação'] + $l, $linhas),
        ];
    }

    private static function relRadar(array $f): array
    {
        [$fr, $pr] = self::recortes($f);
        $sit = match ($f['situacao'] ?? '') {
            'sem_aviso'    => ' AND a.id IS NULL',
            'sem_resposta' => ' AND a.id IS NOT NULL AND a.respondido_em IS NULL',
            'atualizar'    => " AND a.resposta = 'atualizar'",
            default        => '',
        };
        return [
            [
                ['chave' => 'nome', 'titulo' => 'Lugar', 'tipo' => 'texto', 'sub' => 'ficha', 'sub_titulo' => 'Ficha'],
                ['chave' => 'conferido', 'titulo' => 'Última conferência', 'tipo' => 'data'],
                ['chave' => 'cadastur_validade', 'titulo' => 'Cadastur até', 'tipo' => 'data'],
                ['chave' => 'aviso', 'titulo' => 'Último aviso', 'tipo' => 'data'],
                ['chave' => 'estado', 'titulo' => 'Resposta', 'tipo' => 'texto'],
            ],
            "SELECT i.nome, CONCAT(f.codigo, ' ', f.nome) AS ficha, COALESCE(i.ultima_auditoria_em, i.homologado_em) AS conferido, i.cadastur_validade,
                    a.criado_em AS aviso, a.resposta, a.respondido_em, a.expira_em
             FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
             LEFT JOIN radar_avisos a ON a.id = (SELECT MAX(id) FROM radar_avisos x WHERE x.item_id = i.id)
             WHERE i.situacao = 'desatualizada'{$sit}{$fr} ORDER BY conferido, i.nome",
            $pr,
            fn (array $linhas) => array_map(fn ($l) => ['estado' => match (true) {
                $l['aviso'] === null => 'sem aviso (sem contato ou envio desligado)',
                $l['resposta'] === 'atualizar' => 'pediu para atualizar',
                $l['resposta'] === 'igual' => 'confirmou',
                $l['expira_em'] < date('Y-m-d H:i:s') => 'link venceu sem resposta',
                default => 'esperando resposta',
            }] + $l, $linhas),
        ];
    }

    private static function relHomologacoes(array $f): array
    {
        [$fr, $pr] = self::recortes($f);
        return [
            [
                ['chave' => 'homologado_em', 'titulo' => 'Homologado', 'tipo' => 'data'],
                ['chave' => 'ficha', 'titulo' => 'Ficha', 'tipo' => 'texto'],
                ['chave' => 'nome', 'titulo' => 'Lugar', 'tipo' => 'texto'],
                ['chave' => 'pesquisador', 'titulo' => 'Coletado por', 'tipo' => 'texto'],
            ],
            "SELECT i.homologado_em, f.codigo AS ficha, i.nome, u.nome AS pesquisador
             FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN usuarios u ON u.id = i.pesquisador_id
             WHERE i.homologado_em BETWEEN ? AND ?{$fr} ORDER BY i.homologado_em DESC",
            array_merge([$f['de'] . ' 00:00:00', $f['ate'] . ' 23:59:59'], $pr),
        ];
    }

    // ------------------------------------------------------------ público

    private static function relEngajamento(array $f): array
    {
        [$fr, $pr] = self::recortes($f);
        return [
            [
                ['chave' => 'nome', 'titulo' => 'Lugar', 'tipo' => 'texto', 'sub' => 'ficha', 'sub_titulo' => 'Ficha'],
                ['chave' => 'v', 'titulo' => 'Visualizações', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'r', 'titulo' => 'Como chegar', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'w', 'titulo' => 'WhatsApp', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'l', 'titulo' => 'Ligou', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 's', 'titulo' => 'Site', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'g', 'titulo' => 'Guardou', 'tipo' => 'numero', 'soma' => true],
            ],
            "SELECT i.nome, CONCAT(f.codigo, ' ', f.nome) AS ficha, SUM(m.visualizacoes) v, SUM(m.cliques_rota) r, SUM(m.cliques_whatsapp) w,
                    SUM(m.cliques_ligar) l, SUM(m.cliques_site) s, SUM(m.guardados) g
             FROM metricas_lugares m JOIN inventario_itens i ON i.id = m.item_id JOIN inventario_fichas f ON f.id = i.ficha_id
             WHERE m.dia BETWEEN ? AND ?{$fr} GROUP BY i.id, i.nome, f.codigo, f.nome ORDER BY v DESC, i.nome",
            array_merge([$f['de'], $f['ate']], $pr),
        ];
    }

    private static function relQr(array $f): array
    {
        [$fe, $pe] = Empresa::filtro('p');
        return [
            [
                ['chave' => 'titulo', 'titulo' => 'Ponto', 'tipo' => 'texto', 'sub' => 'apelido', 'sub_titulo' => 'Endereço do QR'],
                ['chave' => 'leituras', 'titulo' => 'Leituras', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'dias', 'titulo' => 'Dias com leitura', 'tipo' => 'numero'],
            ],
            "SELECT p.titulo, CONCAT('/qr/', p.apelido) AS apelido, COALESCE(SUM(m.leituras), 0) AS leituras, COUNT(m.dia) AS dias
             FROM qr_pontos p LEFT JOIN qr_leituras m ON m.ponto_id = p.id AND m.dia BETWEEN ? AND ?
             WHERE 1 = 1{$fe} GROUP BY p.id, p.titulo, p.apelido ORDER BY leituras DESC, p.titulo",
            array_merge([$f['de'], $f['ate']], $pe),
        ];
    }

    private static function relExperiencias(array $f): array
    {
        [$fe, $pe] = Empresa::filtro('x');
        $sit = in_array($f['situacao'] ?? '', ['aprovada', 'aguardando', 'ajuste'], true) ? " AND x.situacao = '" . $f['situacao'] . "'" : '';
        $faixas = ['gratuita' => 'Gratuita', 'ate_80' => 'Até R$ 80', 'de_81_a_200' => 'R$ 81 a 200', 'de_201_a_500' => 'R$ 201 a 500', 'acima_500' => 'Acima de R$ 500'];
        $situacoes = ['rascunho' => 'Rascunho', 'aguardando' => 'Aguardando curadoria', 'aprovada' => 'Aprovada', 'ajuste' => 'Ajuste pedido', 'reprovada' => 'Reprovada'];
        return [
            [
                ['chave' => 'criado_em', 'titulo' => 'Cadastro', 'tipo' => 'data'],
                ['chave' => 'titulo', 'titulo' => 'Experiência', 'tipo' => 'texto', 'sub' => 'lugar', 'sub_titulo' => 'Lugar'],
                ['chave' => 'faixa', 'titulo' => 'Preço', 'tipo' => 'texto'],
                ['chave' => 'estado', 'titulo' => 'Situação', 'tipo' => 'texto'],
            ],
            "SELECT x.criado_em, x.titulo, i.nome AS lugar, x.faixa_valor, x.situacao
             FROM inventario_experiencias x JOIN inventario_itens i ON i.id = x.item_id
             WHERE x.criado_em BETWEEN ? AND ?{$sit}{$fe} ORDER BY x.criado_em DESC",
            array_merge([$f['de'] . ' 00:00:00', $f['ate'] . ' 23:59:59'], $pe),
            fn (array $linhas) => array_map(fn ($l) => ['faixa' => $faixas[$l['faixa_valor']] ?? '', 'estado' => $situacoes[$l['situacao']] ?? $l['situacao']] + $l, $linhas),
        ];
    }

    // ------------------------------------------------------------ gestão

    private static function relEvolucao(array $f): array
    {
        [$fe, $pe] = Empresa::filtro('d');
        return [
            [
                ['chave' => 'mes', 'titulo' => 'Mês', 'tipo' => 'texto'],
                ['chave' => 'publicos', 'titulo' => 'No portal', 'tipo' => 'numero'],
                ['chave' => 'pre', 'titulo' => 'Pré-cadastro', 'tipo' => 'numero'],
                ['chave' => 'leitos', 'titulo' => 'Leitos', 'tipo' => 'numero'],
                ['chave' => 'uh', 'titulo' => 'UH', 'tipo' => 'numero'],
                ['chave' => 'cadastur', 'titulo' => 'Com Cadastur', 'tipo' => 'numero'],
                ['chave' => 'acessiveis', 'titulo' => 'Acessíveis', 'tipo' => 'numero'],
                ['chave' => 'experiencias', 'titulo' => 'Experiências', 'tipo' => 'numero'],
                ['chave' => 'roteiros', 'titulo' => 'Roteiros', 'tipo' => 'numero'],
                ['chave' => 'paginas', 'titulo' => 'Páginas vistas', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'homologados', 'titulo' => 'Homologados no mês', 'tipo' => 'numero', 'soma' => true],
            ],
            "SELECT d.mes, d.dados FROM destino_fotografias d WHERE d.mes BETWEEN ? AND ?{$fe} ORDER BY d.mes",
            array_merge([substr($f['de'], 0, 7) . '-01', $f['ate']], $pe),
            function (array $linhas): array {
                $meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
                return array_map(function ($l) use ($meses) {
                    $d = json_decode((string) $l['dados'], true) ?: [];
                    return ['mes' => $meses[(int) substr($l['mes'], 5, 2)] . ' de ' . substr($l['mes'], 0, 4), 'publicos' => $d['publicos'] ?? 0,
                            'pre' => $d['situacoes']['pre_cadastro'] ?? 0, 'leitos' => $d['leitos'] ?? 0, 'uh' => $d['uh'] ?? 0,
                            'cadastur' => $d['cadastur'] ?? 0, 'acessiveis' => $d['acessiveis'] ?? 0, 'experiencias' => $d['experiencias'] ?? 0,
                            'roteiros' => $d['roteiros'] ?? 0, 'paginas' => $d['paginas_no_mes'] ?? 0, 'homologados' => $d['homologados_no_mes'] ?? 0];
                }, $linhas);
            },
        ];
    }

    private static function relLeitosEventos(array $f): array
    {
        $eid = (int) Empresa::id();
        $c = $eid ? EventCity::noitesDeEventos($eid) : ['noites' => [], 'leitos' => 0];
        $nivel = (string) ($f['situacao'] ?? '');
        $linhas = array_values(array_filter(array_map(fn ($n) => [
            'dia' => $n['dia'],
            'eventos' => implode('; ', array_map(fn ($e) => $e['titulo'] . ' (' . milhar($e['turistas']) . ')', $n['eventos'])),
            'turistas' => $n['turistas'], 'leitos' => $c['leitos'] ?: null, 'percentual' => $n['percentual'],
            'faltam' => $n['faltam'], 'situacao' => EventCity::NIVEIS[$n['nivel']], 'nivel' => $n['nivel'],
        ], $c['noites']), fn ($l) => match ($nivel) {
            'falta' => $l['nivel'] === 'falta',
            'risco' => in_array($l['nivel'], ['falta', 'atencao'], true),
            default => true,
        }));
        return [
            [
                ['chave' => 'dia', 'titulo' => 'Noite', 'tipo' => 'data', 'sub' => 'eventos', 'sub_titulo' => 'Eventos (turistas)'],
                ['chave' => 'turistas', 'titulo' => 'Turistas', 'tipo' => 'numero'],
                ['chave' => 'leitos', 'titulo' => 'Leitos', 'tipo' => 'numero'],
                ['chave' => 'percentual', 'titulo' => 'Dos leitos', 'tipo' => 'percentual'],
                ['chave' => 'faltam', 'titulo' => 'Faltam', 'tipo' => 'numero'],
                ['chave' => 'situacao', 'titulo' => 'Situação', 'tipo' => 'texto'],
            ],
            'SELECT 1', [],
            fn () => $linhas,
        ];
    }

    /** O diagnóstico DTI mais recente do destino (o aberto, ou o último fechado). */
    private static function cicloDti(): ?array
    {
        $eid = (int) Empresa::id();
        if (!$eid) {
            return null;
        }
        $q = Database::conexao()->prepare("SELECT id, titulo FROM dti_ciclos WHERE empresa_id = ? ORDER BY situacao = 'aberto' DESC, criado_em DESC, id DESC LIMIT 1");
        $q->execute([$eid]);
        return $q->fetch() ?: null;
    }

    private static function relDti(array $f): array
    {
        require_once __DIR__ . '/Diagnostico.php';
        $c = self::cicloDti();
        $nivel = match ($f['situacao'] ?? '') {
            'nao_conforme' => ' AND s.nivel IN (1, 2)', 'observacao' => ' AND s.nivel = 3', 'conforme' => ' AND s.nivel >= 4', 'sem' => ' AND s.nivel IS NULL',
            default => '',
        };
        return [
            [
                ['chave' => 'numero', 'titulo' => 'Nº', 'tipo' => 'numero'],
                ['chave' => 'requisito', 'titulo' => 'Requisito', 'tipo' => 'texto', 'sub' => 'onde', 'sub_titulo' => 'Eixo'],
                ['chave' => 'nivel_texto', 'titulo' => 'Nível', 'tipo' => 'texto'],
                ['chave' => 'situacao_atual', 'titulo' => 'Evidência', 'tipo' => 'texto'],
                ['chave' => 'fonte', 'titulo' => 'Fonte', 'tipo' => 'texto'],
                ['chave' => 'evidencia_em', 'titulo' => 'Data', 'tipo' => 'data'],
            ],
            "SELECT r.numero, r.texto, r.eixo, r.subeixo, r.subcapitulo, s.nivel, s.situacao_atual, s.fonte, s.evidencia_em
             FROM dti_requisitos r JOIN dti_ciclos c ON c.metodologia_id = r.metodologia_id AND c.id = ?
             LEFT JOIN dti_respostas s ON s.ciclo_id = c.id AND s.requisito_id = r.id
             WHERE 1 = 1{$nivel} ORDER BY r.numero",
            [(int) ($c['id'] ?? 0)],
            fn (array $linhas) => array_map(fn ($l) => [
                'requisito' => strtok((string) $l['texto'], "\n"),
                'onde' => Diagnostico::GRUPOS[$l['eixo'] ?: 'sistema'][0] . ' · ' . ($l['subeixo'] ?: $l['subcapitulo']),
                'nivel_texto' => $l['nivel'] === null ? '' : $l['nivel'] . ' · ' . Diagnostico::CURTO[(int) $l['nivel']],
            ] + $l, $linhas),
        ];
    }

    private static function relDtiPlano(array $f): array
    {
        require_once __DIR__ . '/Diagnostico.php';
        $c = self::cicloDti();
        return [
            [
                ['chave' => 'numero', 'titulo' => 'Nº', 'tipo' => 'numero'],
                ['chave' => 'requisito', 'titulo' => 'Requisito', 'tipo' => 'texto', 'sub' => 'onde', 'sub_titulo' => 'Eixo'],
                ['chave' => 'nivel', 'titulo' => 'Nível', 'tipo' => 'numero'],
                ['chave' => 'acoes', 'titulo' => 'Ações', 'tipo' => 'texto'],
                ['chave' => 'responsavel', 'titulo' => 'Responsável', 'tipo' => 'texto'],
                ['chave' => 'prazo', 'titulo' => 'Prazo', 'tipo' => 'data'],
            ],
            "SELECT r.numero, r.texto, r.eixo, r.subeixo, r.subcapitulo, s.nivel, s.acoes, s.responsavel, s.prazo
             FROM dti_respostas s JOIN dti_requisitos r ON r.id = s.requisito_id
             WHERE s.ciclo_id = ? AND ((s.acoes IS NOT NULL AND s.acoes <> '') OR s.nivel < 4)
             ORDER BY s.prazo IS NULL, s.prazo, r.numero",
            [(int) ($c['id'] ?? 0)],
            fn (array $linhas) => array_map(fn ($l) => [
                'requisito' => strtok((string) $l['texto'], "\n"),
                'onde' => Diagnostico::GRUPOS[$l['eixo'] ?: 'sistema'][0] . ' · ' . ($l['subeixo'] ?: $l['subcapitulo']),
            ] + $l, $linhas),
        ];
    }

    private static function relTrade(array $f): array
    {
        [$fe, $pe] = Empresa::filtro('r');
        $situacoes = ['pendente' => 'Esperando análise', 'aprovada' => 'Aprovado', 'rejeitada' => 'Rejeitado', 'revogada' => 'Revogado'];
        return [
            [
                ['chave' => 'criado_em', 'titulo' => 'Pedido', 'tipo' => 'data'],
                ['chave' => 'lugar', 'titulo' => 'Lugar', 'tipo' => 'texto'],
                ['chave' => 'nome', 'titulo' => 'Responsável', 'tipo' => 'texto'],
                ['chave' => 'estado', 'titulo' => 'Situação', 'tipo' => 'texto'],
                ['chave' => 'revisoes', 'titulo' => 'Revisões', 'tipo' => 'numero', 'soma' => true],
                ['chave' => 'aprovadas', 'titulo' => 'Aprovadas', 'tipo' => 'numero', 'soma' => true],
            ],
            "SELECT r.criado_em, i.nome AS lugar, r.nome, r.situacao,
                    (SELECT COUNT(*) FROM inventario_revisoes v WHERE v.item_id = r.item_id AND v.usuario_id <=> r.usuario_id) AS revisoes,
                    (SELECT COUNT(*) FROM inventario_revisoes v WHERE v.item_id = r.item_id AND v.usuario_id <=> r.usuario_id AND v.situacao = 'aprovada') AS aprovadas
             FROM inventario_reivindicacoes r JOIN inventario_itens i ON i.id = r.item_id
             WHERE r.criado_em BETWEEN ? AND ?{$fe} ORDER BY r.criado_em DESC",
            array_merge([$f['de'] . ' 00:00:00', $f['ate'] . ' 23:59:59'], $pe),
            fn (array $linhas) => array_map(fn ($l) => ['estado' => $situacoes[$l['situacao']] ?? $l['situacao']] + $l, $linhas),
        ];
    }
}
