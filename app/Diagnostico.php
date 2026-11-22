<?php
/**
 * Diagnóstico DTI (etapa 7, PLANEJAMENTO 2.9).
 * Softsul Software
 *
 * Catálogo de metodologias (dti_metodologias, dti_requisitos). A primeira é
 * a oficial do Ministério do Turismo, DTI Brasil 2022: 105 requisitos, cada
 * um avaliado de 1 a 5 (Anexo 1 do Manual Metodológico), com a situação
 * atual como evidência (fonte e data) e as ações do Plano de Transformação
 * (Anexo 2). A do Sebrae entra depois como outra metodologia.
 *
 * Cada diagnóstico é um ciclo do destino (dti_ciclos). Aberto, a equipe
 * responde; fechado, a nota e as médias por eixo ficam gravadas e nada muda.
 * O ciclo novo pode partir do último (copia as respostas para revisar).
 *
 * Parte das evidências o InvenTour já tem (inventário, portal, governança,
 * acessibilidade, trade, monitoramento): evidenciasAutomaticas() escreve o
 * texto para a equipe trazer à resposta. O nível é sempre de quem avalia.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/funcoes.php';

final class Diagnostico
{
    /** Os grupos da tela: o sistema de gestão (capítulos 4 a 10, fora o 8.2) e os 9 eixos do 8.2. */
    public const GRUPOS = [
        'sistema'          => ['Sistema de gestão', 'engrenagem', 'Contexto, liderança, planejamento, apoio, operação, avaliação de desempenho e aperfeiçoamento (capítulos 4 a 10).'],
        'governanca'       => ['Governança', 'equipe', 'Governança, gestão digital, planejamento urbano e usos do solo, participação e gestão aberta.'],
        'inovacao'         => ['Inovação', 'sol', 'Ideias, inovações e programas de inovação no destino e no trade.'],
        'tecnologia'       => ['Tecnologia', 'rede', 'Telecomunicações, aplicativos e plataformas, segurança informática.'],
        'acessibilidade'   => ['Acessibilidade', 'cadeirante', 'Informação, sensibilização e cumprimento dos requisitos de acessibilidade.'],
        'sustentabilidade' => ['Sustentabilidade', 'natureza', 'Economia, ambiente e social.'],
        'criatividade'     => ['Criatividade', 'cultura', 'Criatividade e cultura.'],
        'mobilidade'       => ['Mobilidade e transporte', 'carro', 'Meios, acessibilidade, conectividade e segurança viária.'],
        'promocao'         => ['Promoção e marketing', 'megafone', 'Plataformas de promoção e comercialização, comportamento do turista, acessibilidade divulgada.'],
        'seguranca'        => ['Segurança', 'escudo', 'Segurança sanitária, segurança pública e prevenção da exploração de crianças e adolescentes.'],
    ];

    /** Cor de cada nível na tela (classe nivel-N no CSS). */
    public const CURTO = [1 => 'Não conceituado', 2 => 'Não conformidade', 3 => 'Observação', 4 => 'Conformidade estrita', 5 => 'Conformidade plena'];

    private static function pdo(): PDO
    {
        return Database::conexao();
    }

    /** @return list<array> as metodologias ativas */
    public static function metodologias(): array
    {
        return array_map(fn ($m) => ['escala' => json_decode((string) $m['escala'], true)] + $m,
            self::pdo()->query('SELECT * FROM dti_metodologias WHERE ativo = 1 ORDER BY ano DESC, id')->fetchAll());
    }

    public static function metodologia(int $id): ?array
    {
        $q = self::pdo()->prepare('SELECT * FROM dti_metodologias WHERE id = ?');
        $q->execute([$id]);
        $m = $q->fetch();
        return $m ? ['escala' => json_decode((string) $m['escala'], true)] + $m : null;
    }

    /** Os requisitos de uma metodologia, na ordem, com o grupo da tela. */
    public static function requisitos(int $metodologiaId): array
    {
        $q = self::pdo()->prepare('SELECT * FROM dti_requisitos WHERE metodologia_id = ? ORDER BY numero');
        $q->execute([$metodologiaId]);
        return array_map(fn ($r) => $r + ['grupo' => $r['eixo'] ?: 'sistema'], $q->fetchAll());
    }

    /** Os ciclos do destino, o mais novo primeiro, com quantos requisitos foram respondidos. */
    public static function ciclos(int $eid): array
    {
        $q = self::pdo()->prepare('SELECT c.*, m.nome AS metodologia, m.chave AS metodologia_chave,
                                          (SELECT COUNT(*) FROM dti_requisitos r WHERE r.metodologia_id = c.metodologia_id) AS total,
                                          (SELECT COUNT(*) FROM dti_respostas s WHERE s.ciclo_id = c.id AND s.nivel IS NOT NULL) AS respondidos
                                   FROM dti_ciclos c JOIN dti_metodologias m ON m.id = c.metodologia_id
                                   WHERE c.empresa_id = ? ORDER BY c.criado_em DESC, c.id DESC');
        $q->execute([$eid]);
        return $q->fetchAll();
    }

    public static function ciclo(int $id, int $eid): ?array
    {
        $q = self::pdo()->prepare('SELECT c.*, m.nome AS metodologia, m.chave AS metodologia_chave FROM dti_ciclos c
                                   JOIN dti_metodologias m ON m.id = c.metodologia_id WHERE c.id = ? AND c.empresa_id = ?');
        $q->execute([$id, $eid]);
        return $q->fetch() ?: null;
    }

    /** As respostas de um ciclo, por id do requisito. */
    public static function respostas(int $cicloId): array
    {
        $q = self::pdo()->prepare('SELECT * FROM dti_respostas WHERE ciclo_id = ?');
        $q->execute([$cicloId]);
        $r = [];
        foreach ($q->fetchAll() as $l) {
            $r[(int) $l['requisito_id']] = $l;
        }
        return $r;
    }

    /** Abre um ciclo. Com $origemId, copia as respostas daquele ciclo (do mesmo destino e da mesma metodologia). */
    public static function abrir(int $eid, int $metodologiaId, string $titulo, ?int $origemId, int $usuarioId): int
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO dti_ciclos (empresa_id, metodologia_id, titulo, origem_id, criado_em, criado_por) VALUES (?, ?, ?, ?, NOW(), ?)')
                ->execute([$eid, $metodologiaId, $titulo, $origemId, $usuarioId]);
            $id = (int) $pdo->lastInsertId();
            if ($origemId) {
                $pdo->prepare('INSERT INTO dti_respostas (ciclo_id, requisito_id, empresa_id, nivel, situacao_atual, fonte, evidencia_em, acoes, responsavel, prazo, atualizado_em, atualizado_por)
                               SELECT ?, s.requisito_id, s.empresa_id, s.nivel, s.situacao_atual, s.fonte, s.evidencia_em, s.acoes, s.responsavel, s.prazo, NOW(), ?
                               FROM dti_respostas s JOIN dti_ciclos o ON o.id = s.ciclo_id
                               WHERE s.ciclo_id = ? AND o.empresa_id = ? AND o.metodologia_id = ?')
                    ->execute([$id, $usuarioId, $origemId, $eid, $metodologiaId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Auditoria::registrar('criou', 'dti_ciclos', $id, 'abriu o diagnóstico DTI ' . $titulo, null, null, $eid);
        return $id;
    }

    /** Grava a resposta de um requisito num ciclo aberto. */
    public static function responder(array $ciclo, int $requisitoId, array $d, int $usuarioId): void
    {
        if ($ciclo['situacao'] !== 'aberto') {
            throw new RuntimeException('Este diagnóstico já foi fechado.');
        }
        $q = self::pdo()->prepare('SELECT numero FROM dti_requisitos WHERE id = ? AND metodologia_id = ?');
        $q->execute([$requisitoId, (int) $ciclo['metodologia_id']]);
        $numero = $q->fetchColumn();
        if ($numero === false) {
            throw new RuntimeException('Requisito de outra metodologia.');
        }
        $antes = self::respostas((int) $ciclo['id'])[$requisitoId] ?? null;
        $campos = ['nivel' => $d['nivel'], 'situacao_atual' => $d['situacao_atual'], 'fonte' => $d['fonte'], 'evidencia_em' => $d['evidencia_em'],
                   'acoes' => $d['acoes'], 'responsavel' => $d['responsavel'], 'prazo' => $d['prazo']];
        self::pdo()->prepare('INSERT INTO dti_respostas (ciclo_id, requisito_id, empresa_id, ' . implode(', ', array_keys($campos)) . ', atualizado_em, atualizado_por)
                              VALUES (?, ?, ?' . str_repeat(', ?', count($campos)) . ', NOW(), ?)
                              ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn ($c) => "$c = VALUES($c)", array_keys($campos))) . ', atualizado_em = NOW(), atualizado_por = VALUES(atualizado_por)')
            ->execute([(int) $ciclo['id'], $requisitoId, (int) $ciclo['empresa_id'], ...array_values($campos), $usuarioId]);
        Auditoria::registrar('alterou', 'dti_respostas', (int) $ciclo['id'], 'diagnóstico DTI ' . $ciclo['titulo'] . ': requisito ' . $numero,
            $antes ? array_intersect_key($antes, $campos) : null, $campos, (int) $ciclo['empresa_id']);
    }

    /**
     * As médias de um ciclo: geral e por grupo (sistema de gestão e cada eixo),
     * com respondidos, total e quantos estão em cada nível. A média é a
     * aritmética dos níveis respondidos, como na avaliação de conformidade.
     */
    public static function resultado(array $requisitos, array $respostas): array
    {
        $grupos = [];
        foreach (array_keys(self::GRUPOS) as $g) {
            $grupos[$g] = ['total' => 0, 'respondidos' => 0, 'soma' => 0, 'niveis' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]];
        }
        $geral = ['total' => 0, 'respondidos' => 0, 'soma' => 0, 'niveis' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], 'acoes' => 0];
        foreach ($requisitos as $r) {
            $g = $r['grupo'];
            $grupos[$g]['total']++;
            $geral['total']++;
            $s = $respostas[(int) $r['id']] ?? null;
            if ($s && trim((string) $s['acoes']) !== '') {
                $geral['acoes']++;
            }
            if ($s && $s['nivel'] !== null) {
                $n = (int) $s['nivel'];
                $grupos[$g]['respondidos']++;
                $grupos[$g]['soma'] += $n;
                $grupos[$g]['niveis'][$n]++;
                $geral['respondidos']++;
                $geral['soma'] += $n;
                $geral['niveis'][$n]++;
            }
        }
        $media = fn (array $x) => $x['respondidos'] ? round($x['soma'] / $x['respondidos'], 2) : null;
        foreach ($grupos as &$x) {
            $x['media'] = $media($x);
        }
        unset($x);
        $geral['media'] = $media($geral);
        return ['geral' => $geral, 'grupos' => $grupos];
    }

    /** Fecha o ciclo: todos os requisitos respondidos; grava a nota e o resultado. Devolve o erro, ou ''. */
    public static function fechar(array $ciclo, int $usuarioId): string
    {
        if ($ciclo['situacao'] !== 'aberto') {
            return 'Este diagnóstico já foi fechado.';
        }
        $requisitos = self::requisitos((int) $ciclo['metodologia_id']);
        $res = self::resultado($requisitos, self::respostas((int) $ciclo['id']));
        $faltam = $res['geral']['total'] - $res['geral']['respondidos'];
        if ($faltam > 0) {
            return 'Ainda ' . plural($faltam, 'requisito está', 'requisitos estão') . ' sem nível. Para fechar, todos precisam de resposta.';
        }
        self::pdo()->prepare("UPDATE dti_ciclos SET situacao = 'fechado', nota = ?, resultado = ?, fechado_em = NOW(), fechado_por = ? WHERE id = ? AND situacao = 'aberto'")
            ->execute([$res['geral']['media'], json_encode($res, JSON_UNESCAPED_UNICODE), $usuarioId, (int) $ciclo['id']]);
        Auditoria::registrar('alterou', 'dti_ciclos', (int) $ciclo['id'], 'fechou o diagnóstico DTI ' . $ciclo['titulo'] . ' com média ' . number_format((float) $res['geral']['media'], 2, ',', ''),
            null, null, (int) $ciclo['empresa_id']);
        return '';
    }

    /** "3,42" */
    public static function nota(?float $v): string
    {
        return $v === null ? '-' : number_format($v, 2, ',', '');
    }

    /** A distinção do Anexo 1: média mínima e algum requisito em conformidade (nível 4 ou 5). */
    public static function distincao(array $metodologia, array $geral): ?bool
    {
        if ($geral['media'] === null || $metodologia['nota_minima'] === null) {
            return null;
        }
        return $geral['media'] >= (float) $metodologia['nota_minima'] && ($geral['niveis'][4] + $geral['niveis'][5]) > 0;
    }

    /**
     * Gráfico de radar dos grupos (0 a 5). Cada ponto é a média do grupo; o
     * anel tracejado é a média mínima da distinção.
     *
     * @param array<string,array{media:?float}> $grupos
     */
    public static function radar(array $grupos, ?float $minima = 3.0): string
    {
        $chaves = array_keys(self::GRUPOS);
        $n = count($chaves);
        $c = 170;
        $raio = 120;
        $ponto = function (int $i, float $v) use ($n, $c, $raio): array {
            $a = -M_PI / 2 + 2 * M_PI * $i / $n;
            return [round($c + cos($a) * $raio * $v / 5, 1), round($c + sin($a) * $raio * $v / 5, 1)];
        };
        $svg = '<svg class="radar-dti" viewBox="-80 -6 500 352" role="img" aria-label="Média de cada eixo, de 0 a 5">';
        for ($anel = 1; $anel <= 5; $anel++) {
            $pts = implode(' ', array_map(fn ($i) => implode(',', $ponto($i, $anel)), range(0, $n - 1)));
            $svg .= '<polygon class="anel" points="' . $pts . '"/>';
        }
        if ($minima) {
            $pts = implode(' ', array_map(fn ($i) => implode(',', $ponto($i, $minima)), range(0, $n - 1)));
            $svg .= '<polygon class="minima" points="' . $pts . '"/>';
        }
        foreach ($chaves as $i => $k) {
            [$x, $y] = $ponto($i, 5);
            $svg .= '<line class="eixo" x1="' . $c . '" y1="' . $c . '" x2="' . $x . '" y2="' . $y . '"/>';
        }
        $valores = array_map(fn ($k) => (float) ($grupos[$k]['media'] ?? 0), $chaves);
        $svg .= '<polygon class="valor" points="' . implode(' ', array_map(fn ($i) => implode(',', $ponto($i, $valores[$i])), range(0, $n - 1))) . '"/>';
        foreach ($chaves as $i => $k) {
            [$x, $y] = $ponto($i, $valores[$i]);
            $media = $grupos[$k]['media'] ?? null;
            $svg .= '<circle class="no" cx="' . $x . '" cy="' . $y . '" r="3.5" data-balao-titulo="' . e(self::GRUPOS[$k][0]) . '" data-balao="Média '
                . e(self::nota($media)) . ' · ' . (int) ($grupos[$k]['respondidos'] ?? 0) . ' de ' . (int) ($grupos[$k]['total'] ?? 0) . ' respondidos"/>';
            [$lx, $ly] = $ponto($i, 6.1);
            $ancora = abs($lx - $c) < 12 ? 'middle' : ($lx < $c ? 'end' : 'start');
            $svg .= '<text x="' . $lx . '" y="' . ($ly + 4) . '" text-anchor="' . $ancora . '">' . e(self::GRUPOS[$k][0]) . '</text>';
        }
        return $svg . '</svg>';
    }

    /**
     * O que o InvenTour já sabe e serve de evidência, por número de requisito
     * do DTI Brasil 2022. Só fatos do sistema; o nível continua com a equipe.
     *
     * @return array<int,string>
     */
    public static function evidenciasAutomaticas(array $empresa): array
    {
        require_once __DIR__ . '/Fotografia.php';
        require_once __DIR__ . '/Caderno.php';
        $eid = (int) $empresa['id'];
        $m = Fotografia::medir($eid);
        $pdo = self::pdo();
        $gov = $pdo->prepare('SELECT * FROM destino_governanca WHERE empresa_id = ?');
        $gov->execute([$eid]);
        $gov = $gov->fetch() ?: [];
        $portal = Caderno::portal($empresa);
        $aberto = in_array($empresa['modo_operacao'] ?? '', ['ativo', 'avaliacao'], true);
        $total = array_sum($m['situacoes']) - (int) ($m['situacoes']['arquivada'] ?? 0);
        $esteira = (int) ($m['situacoes']['em_campo'] ?? 0) + (int) ($m['situacoes']['em_conferencia'] ?? 0);
        $e = [];

        $e[6] = 'Inventário da oferta turística no InvenTour: ' . plural($total, 'lugar cadastrado', 'lugares cadastrados') . ', '
            . plural($m['publicos'], 'homologado', 'homologados') . ' no portal e ' . plural($esteira, 'em coleta ou conferência', 'em coleta ou conferência')
            . ', nas fichas do Inventário da Oferta Turística do MTur.';

        $g = [];
        if (!empty($gov['orgao_turismo_nome'])) {
            $g[] = 'Órgão oficial de turismo: ' . $gov['orgao_turismo_nome'] . (!empty($gov['orgao_turismo_lei']) ? ' (' . $gov['orgao_turismo_lei'] . ')' : '') . '.';
        }
        if (!empty($gov['comtur_nome'])) {
            $g[] = $gov['comtur_nome'] . (!empty($gov['comtur_lei']) ? ' (' . $gov['comtur_lei'] . ')' : '') . ($gov['comtur_ativo'] ? ', com reuniões regulares.' : ', sem reuniões regulares registradas.');
        }
        if (!empty($gov['fumtur_lei'])) {
            $g[] = 'Fundo municipal de turismo: ' . $gov['fumtur_lei'] . '.';
        }
        if (!empty($gov['plano_turismo_lei'])) {
            $g[] = 'Plano ou política municipal de turismo: ' . $gov['plano_turismo_lei'] . '.';
        }
        if ($g) {
            $e[31] = implode(' ', $g);
            if (!empty($gov['comtur_nome'])) {
                $e[36] = $gov['comtur_nome'] . ($gov['comtur_ativo'] ? ' ativo, com reuniões regulares' : ' criado, sem reuniões regulares registradas')
                    . '. O portal tem o Fale conosco e o aviso de informação errada em cada ficha.';
            }
        }
        $e[32] = 'Coleta de campo, conferência e homologação do inventário feitas no InvenTour, com histórico de cada ficha e auditoria das alterações.';
        if ($aberto) {
            $e[33] = 'Pelo portal, o responsável por um lugar pede o acesso e atualiza a ficha (trade turístico): '
                . plural($m['responsaveis'], 'responsável aprovado', 'responsáveis aprovados') . '. O turista fala com a equipe pelo Fale conosco.';
            $e[37] = 'Portal oficial de turismo aberto em ' . $portal . ', sem login, com os lugares homologados, roteiros, agenda e mapa.';
            $qr = $m['pontos_qr'] ? '; ' . plural($m['pontos_qr'], 'ponto de QR leva', 'pontos de QR levam') . ' o turista ao portal' : '';
            $e[43] = 'Portal ' . $portal . ' (instalável no celular) com mapa, roteiros e fichas dos lugares' . $qr . '.';
            $e[79] = 'Portal ' . $portal . ' com SEO (dados estruturados, sitemap, Search Console e Bing) para a promoção do destino' . $qr . '.';
        }
        $e[44] = 'O destino usa o InvenTour para o inventário, a homologação, o caderno oficial, o portal, os relatórios gerenciais e este diagnóstico.';
        $e[46] = 'Oferta digitalizada: ' . plural($m['publicos'], 'lugar homologado', 'lugares homologados') . ' no portal, '
            . plural($m['experiencias'], 'experiência', 'experiências') . ' e ' . plural($m['roteiros'], 'roteiro', 'roteiros') . '.';
        $e[48] = 'Plataforma com conexão cifrada (HTTPS), login com dois fatores e monitoramento dos acessos ao servidor.';
        $e[49] = 'Política de privacidade publicada no portal; o portal mede o uso sem cookie e pede consentimento para o Google Analytics. Dados do inventário separados por destino.';
        if ($m['publicos']) {
            $e[50] = plural($m['acessiveis'], 'lugar', 'lugares') . ' de ' . milhar($m['publicos']) . ' no portal com acessibilidade registrada na ficha, que o turista vê e filtra.';
            $e[82] = $e[50];
        }
        if ($m['responsaveis'] || $m['experiencias']) {
            $e[56] = plural($m['responsaveis'], 'estabelecimento atualiza', 'estabelecimentos atualizam') . ' a própria ficha no portal e '
                . plural($m['experiencias'], 'experiência local está publicada', 'experiências locais estão publicadas') . '.';
        }
        $cons = array_sum($m['conservacao_atrativos']);
        if ($cons) {
            $e[73] = plural($cons, 'atrativo', 'atrativos') . ' com o estado de conservação registrado em campo no inventário.';
        }
        $e[81] = 'O portal mede, sem cookie, as visitas a cada lugar e os cliques em rota, WhatsApp, telefone e site; com consentimento, o Google Analytics mostra origem, cidade e comportamento.';
        $e[90] = 'Relatórios gerenciais do InvenTour, fotografia mensal do destino e monitoramento do portal.';
        require_once __DIR__ . '/EventCity.php';
        $e[93] = 'Fotografia mensal do destino (oferta, leitos, Cadastur, uso do portal)'
            . (EventCity::config($eid)['ligado'] ? ' e cruzamento dos leitos com os eventos do EventCity nos próximos 90 dias.' : '.');
        $e[94] = 'Indicadores da fotografia mensal e dos relatórios do InvenTour (evolução mês a mês, radar de obsolescência, engajamento).';
        $e[95] = 'Este diagnóstico, feito no InvenTour sobre os 105 requisitos do Referencial Técnico, com ciclos guardados.';
        $e[96] = 'O InvenTour reúne inventário, indicadores, relatórios, o diagnóstico DTI e o Plano de Transformação.';
        ksort($e);
        return $e;
    }

    /** dd/mm/aaaa ou aaaa-mm-dd para o banco, ou null. */
    public static function data(string $v): ?string
    {
        $v = trim($v);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $p) && checkdate((int) $p[2], (int) $p[1], (int) $p[3])) {
            return "$p[3]-$p[2]-$p[1]";
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $v, $p) && checkdate((int) $p[2], (int) $p[3], (int) $p[1])) {
            return $v;
        }
        return null;
    }
}
