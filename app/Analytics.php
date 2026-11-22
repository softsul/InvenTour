<?php
/**
 * Google Analytics 4: a etiqueta no portal, com consentimento, e a leitura
 * pela Data API (o mesmo do EventCity, app/Analytics.php de lá).
 * Softsul Software
 *
 * Uma propriedade para a plataforma inteira, com um fluxo da Web para
 * inventour.com.br: cada destino sai do filtro pelo host do portal
 * (hostName), como no Search Console. A leitura usa a mesma conta de
 * serviço do Search Console, que precisa ser Leitor na propriedade. O GA4
 * padrão e a Data API não cobram; a Data API só tem cota diária, longe do
 * que a leitura uma vez por dia gasta.
 *
 * No portal: sem o código de medição gravado, nada muda (nem aviso de
 * cookies). Com ele, o aviso aparece e o script do Google só é carregado
 * depois do "Aceitar" (portal/assets/consentimento.js), sem sinais de
 * anúncio e com o cookie preso ao host do portal, para cada destino pedir
 * o próprio consentimento. O código de medição vai num arquivo do cache,
 * porque o portal não lê a tabela ajustes.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Ajustes.php';
require_once __DIR__ . '/SearchConsole.php';
require_once __DIR__ . '/funcoes.php';

final class Analytics
{
    private const ESCOPO = 'https://www.googleapis.com/auth/analytics.readonly';

    /** Código de medição (G-XXXX) e id numérico da propriedade. */
    public static function medicao(): string
    {
        return Ajustes::ler('analytics', 'medicao', '', 0);
    }

    public static function propriedade(): string
    {
        return Ajustes::ler('analytics', 'propriedade', '', 0);
    }

    /** Pronto para ler: propriedade gravada e a conta de serviço do Google. */
    public static function configurado(): bool
    {
        return self::propriedade() !== '' && SearchConsole::configurado();
    }

    private static function arquivoMedicao(): string
    {
        $cfg = require __DIR__ . '/config.php';
        return $cfg['caminhos']['cache'] . '/ga4-medicao.txt';
    }

    /** Grava os dois códigos e o arquivo que o portal lê. */
    public static function gravar(string $medicao, string $propriedade, int $usuarioId): void
    {
        Ajustes::salvar('analytics', ['medicao' => $medicao, 'propriedade' => $propriedade], 0, $usuarioId);
        $arquivo = self::arquivoMedicao();
        if ($medicao === '') {
            @unlink($arquivo);
        } else {
            file_put_contents($arquivo, $medicao);
        }
    }

    /** O código de medição para o portal, ou '' (sem etiqueta e sem aviso). */
    public static function medicaoNoPortal(): string
    {
        static $codigo = null;
        if ($codigo === null) {
            $arquivo = self::arquivoMedicao();
            $c = is_file($arquivo) ? trim((string) file_get_contents($arquivo)) : '';
            $codigo = preg_match('/^G-[A-Z0-9]{4,20}$/', $c) ? $c : '';
        }
        return $codigo;
    }

    /** Um relatório da Data API; devolve as linhas como listas de valores. */
    private static function relatorio(array $corpo): array
    {
        $r = SearchConsole::http('POST', 'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode(self::propriedade()) . ':runReport',
            json_encode($corpo + ['limit' => 100000]), ['Authorization: Bearer ' . SearchConsole::tokenPara(self::ESCOPO), 'Content-Type: application/json']);
        $linhas = [];
        foreach ($r['rows'] ?? [] as $l) {
            $linhas[] = [array_column($l['dimensionValues'] ?? [], 'value'), array_map('floatval', array_column($l['metricValues'] ?? [], 'value'))];
        }
        return $linhas;
    }

    private static function dim(array $nomes): array
    {
        return array_map(fn ($n) => ['name' => $n], $nomes);
    }

    /** Teste da tela: os usuários de ontem na propriedade inteira. */
    public static function testar(): int
    {
        $r = self::relatorio(['dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'today']], 'metrics' => self::dim(['activeUsers'])]);
        return (int) ($r[0][1][0] ?? 0);
    }

    /**
     * Puxa os 35 últimos dias por destino e as partes de 28 dias.
     *
     * @return list<string>
     */
    public static function puxar(): array
    {
        $pdo = Database::conexao();
        $hosts = [];
        $nomes = [];
        foreach (SearchConsole::destinosAbertos() as $e) {
            $hosts[(string) parse_url(SearchConsole::prefixo($e), PHP_URL_HOST)] = (int) $e['id'];
            if ($e['dominio_proprio']) {
                $hosts[strtolower((string) $e['dominio_proprio'])] = (int) $e['id'];
            }
            $nomes[(int) $e['id']] = (string) $e['nome_fantasia'];
        }
        $empresa = fn (string $host): ?int => $hosts[strtolower($host)] ?? null;
        $metricas = self::dim(['activeUsers', 'sessions', 'screenPageViews', 'engagedSessions', 'userEngagementDuration']);
        $diario = self::relatorio(['dateRanges' => [['startDate' => '35daysAgo', 'endDate' => 'yesterday']],
                                   'dimensions' => self::dim(['date', 'hostName']), 'metrics' => $metricas]);
        $periodo = [['startDate' => '28daysAgo', 'endDate' => 'yesterday']];
        $partes = [];
        foreach (['total' => null, 'canal' => 'sessionDefaultChannelGroup', 'origem' => 'sessionSource', 'pagina' => 'pagePath',
                  'cidade' => 'city', 'aparelho' => 'deviceCategory'] as $tipo => $dimensao) {
            $partes[$tipo] = self::relatorio(['dateRanges' => $periodo, 'dimensions' => self::dim(array_values(array_filter(['hostName', $dimensao]))),
                                              'metrics' => self::dim(['activeUsers', 'sessions', 'screenPageViews'])]);
        }

        $somaDia = [];
        foreach ($diario as [$d, $m]) {
            if (($id = $empresa($d[1])) === null) {
                continue;
            }
            $dia = substr($d[0], 0, 4) . '-' . substr($d[0], 4, 2) . '-' . substr($d[0], 6, 2);
            $somaDia[$id][$dia] ??= [0, 0, 0, 0, 0];
            foreach ($m as $i => $v) {
                $somaDia[$id][$dia][$i] += (int) $v;
            }
        }
        $somaPartes = [];
        foreach ($partes as $tipo => $linhas) {
            foreach ($linhas as [$d, $m]) {
                if (($id = $empresa($d[0])) === null) {
                    continue;
                }
                $chave = mb_substr($tipo === 'total' ? '' : (string) ($d[1] ?? ''), 0, 500);
                $somaPartes[$id][$tipo][$chave] ??= [0, 0, 0];
                foreach ($m as $i => $v) {
                    $somaPartes[$id][$tipo][$chave][$i] += (int) $v;
                }
            }
        }

        $dia = $pdo->prepare('INSERT INTO analytics_diaria (empresa_id, dia, usuarios, sessoes, visualizacoes, engajadas, engajamento) VALUES (?, ?, ?, ?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE usuarios = VALUES(usuarios), sessoes = VALUES(sessoes), visualizacoes = VALUES(visualizacoes),
                                                      engajadas = VALUES(engajadas), engajamento = VALUES(engajamento)');
        $parte = $pdo->prepare('INSERT INTO analytics_partes (empresa_id, tipo, chave, usuarios, sessoes, visualizacoes) VALUES (?, ?, ?, ?, ?, ?)');
        $log = [];
        Database::transacao(function (PDO $pdo) use ($nomes, $somaDia, $somaPartes, $dia, $parte, &$log): void {
            foreach ($nomes as $id => $nome) {
                foreach ($somaDia[$id] ?? [] as $d => $m) {
                    $dia->execute([$id, $d, ...$m]);
                }
                $pdo->prepare('DELETE FROM analytics_partes WHERE empresa_id = ?')->execute([$id]);
                foreach ($somaPartes[$id] ?? [] as $tipo => $linhas) {
                    // Até 50 por tipo, os de mais sessões.
                    uasort($linhas, fn ($a, $b) => [$b[1], $b[2]] <=> [$a[1], $a[2]]);
                    foreach (array_slice($linhas, 0, 50, true) as $chave => $m) {
                        $parte->execute([$id, $tipo, (string) $chave, ...$m]);
                    }
                }
                $log[] = $nome . ': ' . plural(count($somaDia[$id] ?? []), 'dia', 'dias');
            }
        });
        return $log;
    }

    /** Resumo de 28 dias de um destino (ou de todos, com null) para o Início. */
    public static function resumo(?int $empresaId): array
    {
        $pdo = Database::conexao();
        $onde = $empresaId ? 'empresa_id = ' . (int) $empresaId : '1 = 1';
        $t = $pdo->query("SELECT COALESCE(SUM(sessoes), 0) s, COALESCE(SUM(visualizacoes), 0) v, COALESCE(SUM(engajadas), 0) en,
                                 COALESCE(SUM(engajamento), 0) seg, MAX(dia) ultimo
                          FROM analytics_diaria WHERE {$onde} AND dia >= CURDATE() - INTERVAL 28 DAY")->fetch();
        $usuarios = (int) $pdo->query("SELECT COALESCE(SUM(usuarios), 0) FROM analytics_partes WHERE {$onde} AND tipo = 'total'")->fetchColumn();
        $parte = fn (string $tipo, int $n) => $pdo->query("SELECT chave, SUM(sessoes) s, SUM(usuarios) u FROM analytics_partes
                                                          WHERE {$onde} AND tipo = '{$tipo}' AND chave NOT IN ('', '(not set)')
                                                          GROUP BY chave ORDER BY s DESC LIMIT {$n}")->fetchAll();
        $serie = $pdo->query("SELECT dia, SUM(sessoes) s FROM analytics_diaria WHERE {$onde} AND dia >= CURDATE() - INTERVAL 14 DAY GROUP BY dia ORDER BY dia")->fetchAll();
        return ['usuarios' => $usuarios, 'sessoes' => (int) $t['s'], 'visualizacoes' => (int) $t['v'],
                'engajamento' => $t['s'] ? (int) round($t['en'] / $t['s'] * 100) : 0,
                'tempo' => $usuarios ? (int) round($t['seg'] / max(1, $usuarios)) : 0, 'ultimo' => $t['ultimo'],
                'canais' => $parte('canal', 6), 'origens' => $parte('origem', 8), 'cidades' => array_map(fn ($c) => ['chave' => self::nomeCidade((string) $c['chave'])] + $c, $parte('cidade', 8)), 'serie' => $serie];
    }

    // ------------------------------------------------- MONITORAMENTO, VISITAS

    /** Pasta do cache das consultas da tela (uma hora; ao vivo, um minuto). */
    private static function pastaCache(): string
    {
        $cfg = require __DIR__ . '/config.php';
        $pasta = $cfg['caminhos']['cache'] . '/ga4';
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0770, true);
        }
        return $pasta;
    }

    private static function linhas(array $relatorio): array
    {
        $saida = [];
        foreach ($relatorio['rows'] ?? [] as $l) {
            $saida[] = [array_column($l['dimensionValues'] ?? [], 'value'), array_map('floatval', array_column($l['metricValues'] ?? [], 'value'))];
        }
        return $saida;
    }

    /**
     * Até cinco relatórios numa chamada só (batchRunReports), guardados em
     * cache: a tela abre sem esperar o Google e gasta pouco da cota diária.
     */
    private static function lote(array $pedidos, int $validade = 3600): array
    {
        $arquivo = self::pastaCache() . '/' . md5(self::propriedade() . json_encode($pedidos)) . '.json';
        if (is_file($arquivo) && filemtime($arquivo) > time() - $validade) {
            $dado = json_decode((string) file_get_contents($arquivo), true);
            if (is_array($dado)) {
                return $dado;
            }
        }
        $r = SearchConsole::http('POST', 'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode(self::propriedade()) . ':batchRunReports',
            json_encode(['requests' => $pedidos]), ['Authorization: Bearer ' . SearchConsole::tokenPara(self::ESCOPO), 'Content-Type: application/json']);
        $saida = array_map([self::class, 'linhas'], $r['reports'] ?? []);
        @file_put_contents($arquivo, json_encode($saida));
        return $saida;
    }

    /** Os hosts dos portais dos destinos pedidos (vazio: todos). */
    public static function hosts(array $empresas): array
    {
        if (!$empresas) {
            return [];
        }
        $q = Database::conexao()->prepare('SELECT id, slug, dominio_proprio FROM empresas WHERE id IN (' . implode(',', array_fill(0, count($empresas), '?')) . ')');
        $q->execute(array_map('intval', $empresas));
        $hosts = [];
        foreach ($q->fetchAll() as $e) {
            $hosts[] = (string) parse_url(SearchConsole::prefixo($e), PHP_URL_HOST);
            if ($e['dominio_proprio']) {
                $hosts[] = strtolower((string) $e['dominio_proprio']);
            }
        }
        return array_values(array_unique($hosts));
    }

    /** Filtro do pedido: os hosts e, se vier, mais uma condição. */
    private static function filtro(array $hosts, ?array $outro = null): ?array
    {
        $partes = [];
        if ($hosts) {
            $partes[] = ['filter' => ['fieldName' => 'hostName', 'inListFilter' => ['values' => $hosts]]];
        }
        if ($outro) {
            $partes[] = $outro;
        }
        return match (count($partes)) { 0 => null, 1 => $partes[0], default => ['andGroup' => ['expressions' => $partes]] };
    }

    private static function pedido(int $dias, array $hosts, array $dimensoes, array $metricas, int $limite = 10, ?array $outro = null, ?string $ordem = null): array
    {
        $p = ['dateRanges' => [['startDate' => ($dias - 1) . 'daysAgo', 'endDate' => 'today']],
              'dimensions' => self::dim($dimensoes), 'metrics' => self::dim($metricas), 'limit' => $limite];
        if ($f = self::filtro($hosts, $outro)) {
            $p['dimensionFilter'] = $f;
        }
        $p['orderBys'] = [$ordem === 'dimensao'
            ? ['dimension' => ['dimensionName' => $dimensoes[0]]]
            : ['metric' => ['metricName' => $metricas[0]], 'desc' => true]];
        return $p;
    }

    /**
     * O Google devolve cidade e estado em inglês e sem acento ("State of
     * Parana", "Sao Lourenco do Oeste"). Aqui voltam ao nome oficial, pela
     * tabela de municípios e estados; o de fora do Brasil fica como veio,
     * salvo os poucos nomes que mudam em português.
     */
    private const NOMES_DE_FORA = [
        'Lisbon' => 'Lisboa', 'Federal District' => 'Distrito Federal', 'Brasilia' => 'Brasília', 'Asuncion' => 'Assunção',
        'Buenos Aires Province' => 'Província de Buenos Aires', 'Paco de Arcos' => 'Paço de Arcos',
        'Porto District' => 'Distrito do Porto', 'Lisbon District' => 'Distrito de Lisboa', 'Madrid' => 'Madri', 'London' => 'Londres',
        'New York' => 'Nova York', 'Florida' => 'Flórida', 'California' => 'Califórnia',
    ];
    private static ?array $municipiosPorNome = null;
    private static ?array $estadosPorNome = null;

    private static function carregarNomes(): void
    {
        if (self::$municipiosPorNome !== null) {
            return;
        }
        self::$municipiosPorNome = [];
        self::$estadosPorNome = [];
        foreach (Database::conexao()->query('SELECT m.nome, e.nome AS estado FROM municipios m JOIN estados e ON e.id = m.estado_id')->fetchAll() as $l) {
            self::$municipiosPorNome[self::textoSemAcento((string) $l['nome'])][self::textoSemAcento((string) $l['estado'])] = (string) $l['nome'];
        }
        foreach (Database::conexao()->query('SELECT nome FROM estados')->fetchAll(PDO::FETCH_COLUMN) as $e) {
            self::$estadosPorNome[self::textoSemAcento((string) $e)] = (string) $e;
        }
    }

    public static function nomeEstado(string $estado): string
    {
        self::carregarNomes();
        $sem = preg_replace('/^State of /i', '', trim($estado));
        return self::$estadosPorNome[self::textoSemAcento($sem)] ?? (self::NOMES_DE_FORA[$sem] ?? $sem);
    }

    /** Com o estado, desempata cidades de mesmo nome; sem ele, só troca se o nome for único. */
    public static function nomeCidade(string $cidade, string $estado = ''): string
    {
        self::carregarNomes();
        $achados = self::$municipiosPorNome[self::textoSemAcento($cidade)] ?? [];
        if ($estado !== '') {
            $chaveEstado = self::textoSemAcento(self::nomeEstado($estado));
            if (isset($achados[$chaveEstado])) {
                return $achados[$chaveEstado];
            }
        } elseif ($achados && count(array_unique($achados)) === 1) {
            return reset($achados);
        }
        return self::NOMES_DE_FORA[$cidade] ?? $cidade;
    }

    private static function textoSemAcento(string $t): string
    {
        $t = mb_strtolower(trim($t));
        return strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'î' => 'i', 'û' => 'u']);
    }

    /**
     * Tudo o que a tela Visitas mostra, para os destinos pedidos (vazio:
     * todos, na visão da plataforma). Só conta quem aceitou os cookies.
     */
    public static function visitas(int $dias, array $empresas): array
    {
        $hosts = self::hosts($empresas);
        $eventos = fn (array $nomes) => ['filter' => ['fieldName' => 'eventName', 'inListFilter' => ['values' => $nomes]]];
        $cliques = array_keys(self::CLIQUES);
        [$total, $porDia, $cidades, $canais, $origens] = self::lote([
            self::pedido($dias, $hosts, [], ['activeUsers', 'newUsers', 'sessions', 'engagedSessions', 'userEngagementDuration', 'screenPageViews'], 1),
            self::pedido($dias, $hosts, ['date'], ['sessions', 'activeUsers'], 400, null, 'dimensao'),
            self::pedido($dias, $hosts, ['hostName', 'city', 'region'], ['activeUsers'], 2000),
            self::pedido($dias, $hosts, ['sessionDefaultChannelGroup'], ['sessions'], 20),
            self::pedido($dias, $hosts, ['sessionSource'], ['sessions'], 10),
        ]);
        [$paginas, $buscas, $acoes, $aparelhos, $quando] = self::lote([
            self::pedido($dias, $hosts, ['pageTitle'], ['screenPageViews'], 10),
            self::pedido($dias, $hosts, ['searchTerm'], ['eventCount'], 10, $eventos(['view_search_results'])),
            self::pedido($dias, $hosts, ['eventName', 'sessionDefaultChannelGroup'], ['eventCount'], 200, $eventos($cliques)),
            self::pedido($dias, $hosts, ['deviceCategory'], ['activeUsers'], 5),
            self::pedido($dias, $hosts, ['dayOfWeek', 'hour'], ['sessions'], 200),
        ]);
        [$campanhas, $porHost, $pilares] = self::lote([
            self::pedido($dias, $hosts, ['sessionCampaignName', 'sessionSource'], ['sessions'], 10,
                ['notExpression' => ['filter' => ['fieldName' => 'sessionCampaignName', 'inListFilter' => ['values' => ['(not set)', '(direct)', '(organic)', '(referral)', '(none)']]]]]),
            self::pedido($dias, $hosts, ['hostName'], ['sessions'], 50),
            // As páginas de cada pilar (onde dormir, onde comer...) pelo endereço.
            self::pedido($dias, $hosts, ['pagePath'], ['screenPageViews'], 200),
        ]);

        $t = $total[0][1] ?? [0, 0, 0, 0, 0, 0];
        $usuarios = (int) $t[0];

        // De fora: a cidade do visitante não é a do destino do portal.
        $cidadeDoHost = [];
        foreach (Database::conexao()->query('SELECT slug, dominio_proprio, cidade FROM empresas')->fetchAll() as $e) {
            $cidadeDoHost[(string) parse_url(SearchConsole::prefixo($e), PHP_URL_HOST)] = self::textoSemAcento((string) $e['cidade']);
            if ($e['dominio_proprio']) {
                $cidadeDoHost[strtolower((string) $e['dominio_proprio'])] = self::textoSemAcento((string) $e['cidade']);
            }
        }
        $deFora = 0;
        $conhecidos = 0;
        $porCidade = [];
        $porEstado = [];
        foreach ($cidades as [$d, $m]) {
            [$host, $cidade, $estado] = $d;
            $n = (int) $m[0];
            if ($cidade !== '(not set)') {
                $conhecidos += $n;
                $nomeCidade = self::nomeCidade($cidade, $estado !== '(not set)' ? $estado : '');
                $porCidade[$nomeCidade] = ($porCidade[$nomeCidade] ?? 0) + $n;
                if (isset($cidadeDoHost[$host]) && self::textoSemAcento($cidade) !== $cidadeDoHost[$host]) {
                    $deFora += $n;
                }
            }
            if ($estado !== '(not set)') {
                $nomeEstado = self::nomeEstado($estado);
                $porEstado[$nomeEstado] = ($porEstado[$nomeEstado] ?? 0) + $n;
            }
        }
        arsort($porCidade);
        arsort($porEstado);

        $serie = [];
        $mapa = [];
        foreach ($porDia as [$d, $m]) {
            $mapa[$d[0]] = (int) $m[0];
        }
        for ($i = $dias - 1; $i >= 0; $i--) {
            $dia = date('Ymd', strtotime("-{$i} days"));
            $serie[] = ['rotulo' => $dias <= 14 ? ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][(int) date('w', strtotime($dia))]
                                                : ((($dias - 1 - $i) % ($dias > 31 ? 14 : 5)) === 0 ? date('d/m', strtotime($dia)) : ''),
                        'titulo' => date('d/m', strtotime($dia)), 'data' => date('Y-m-d', strtotime($dia)), 'sessoes' => $mapa[$dia] ?? 0];
        }

        $nomesCliques = self::CLIQUES;
        $porAcao = [];
        $acaoPorCanal = [];
        foreach ($acoes as [$d, $m]) {
            $porAcao[$d[0]] = ($porAcao[$d[0]] ?? 0) + (int) $m[0];
            $acaoPorCanal[self::canal($d[1])] = ($acaoPorCanal[self::canal($d[1])] ?? 0) + (int) $m[0];
        }
        arsort($acaoPorCanal);

        $diasSemana = array_fill(0, 7, 0);
        $horas = array_fill(0, 24, 0);
        foreach ($quando as [$d, $m]) {
            $diasSemana[(int) $d[0]] += (int) $m[0];
            $horas[(int) $d[1]] += (int) $m[0];
        }

        $nomeDoHost = [];
        foreach (Database::conexao()->query('SELECT nome_fantasia, slug, dominio_proprio FROM empresas')->fetchAll() as $e) {
            $nomeDoHost[(string) parse_url(SearchConsole::prefixo($e), PHP_URL_HOST)] = (string) $e['nome_fantasia'];
            if ($e['dominio_proprio']) {
                $nomeDoHost[strtolower((string) $e['dominio_proprio'])] = (string) $e['nome_fantasia'];
            }
        }

        return [
            'usuarios' => $usuarios, 'novos' => $usuarios ? (int) round($t[1] / $usuarios * 100) : 0, 'sessoes' => (int) $t[2],
            'engajamento' => $t[2] ? (int) round($t[3] / $t[2] * 100) : 0, 'tempo' => $usuarios ? (int) round($t[4] / $usuarios) : 0,
            'paginas_sessao' => $t[2] ? round($t[5] / $t[2], 1) : 0,
            'de_fora' => $conhecidos ? (int) round($deFora / $conhecidos * 100) : null,
            'serie' => $serie,
            'cidades' => array_slice($porCidade, 0, 12, true), 'estados' => array_slice($porEstado, 0, 8, true),
            'canais' => array_map(fn ($l) => ['rotulo' => self::canal($l[0][0]), 'valor' => (int) $l[1][0]], $canais),
            'origens' => array_map(fn ($l) => ['rotulo' => $l[0][0], 'valor' => (int) $l[1][0]], array_filter($origens, fn ($l) => $l[0][0] !== '(not set)')),
            'paginas' => array_map(fn ($l) => ['rotulo' => $l[0][0], 'valor' => (int) $l[1][0]], $paginas),
            'buscas' => array_map(fn ($l) => ['rotulo' => $l[0][0], 'valor' => (int) $l[1][0]], array_filter($buscas, fn ($l) => $l[0][0] !== '(not set)')),
            'acoes' => array_map(fn ($k, $v) => ['rotulo' => $v, 'valor' => $porAcao[$k] ?? 0], array_keys($nomesCliques), $nomesCliques),
            'acao_por_canal' => $acaoPorCanal,
            'aparelhos' => array_map(fn ($l) => ['rotulo' => ['mobile' => 'Celular', 'desktop' => 'Computador', 'tablet' => 'Tablet'][$l[0][0]] ?? $l[0][0], 'valor' => (int) $l[1][0]], $aparelhos),
            'dias_semana' => $diasSemana, 'horas' => $horas,
            'campanhas' => array_map(fn ($l) => ['rotulo' => $l[0][0] . ' (' . $l[0][1] . ')', 'valor' => (int) $l[1][0]], $campanhas),
            'pilares' => self::porPilar($pilares),
            'por_destino' => array_map(fn ($l) => ['rotulo' => $nomeDoHost[$l[0][0]] ?? $l[0][0], 'valor' => (int) $l[1][0]], $porHost),
        ];
    }

    /** Usuários ativos nos últimos 30 minutos, por página (só a plataforma: o tempo real não filtra por host). */
    public static function aoVivo(): array
    {
        $r = SearchConsole::http('POST', 'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode(self::propriedade()) . ':runRealtimeReport',
            json_encode(['dimensions' => self::dim(['unifiedScreenName']), 'metrics' => self::dim(['activeUsers']), 'limit' => 8]),
            ['Authorization: Bearer ' . SearchConsole::tokenPara(self::ESCOPO), 'Content-Type: application/json']);
        $linhas = self::linhas($r);
        return ['total' => array_sum(array_map(fn ($l) => (int) $l[1][0], $linhas)),
                'paginas' => array_map(fn ($l) => ['rotulo' => $l[0][0], 'valor' => (int) $l[1][0]], $linhas)];
    }

    // ------------------------------------------------ LEMBRETE DOS EVENTOS

    /** Os cliques do portal que o portal.js manda (só com cookies aceitos), com o nome na tela. */
    public const CLIQUES = ['clique_whatsapp' => 'WhatsApp do lugar', 'clique_rota' => 'Como chegar', 'clique_ligar' => 'Ligou',
                            'clique_site' => 'Site ou Instagram', 'guardar_roteiro' => 'Guardou no meu roteiro'];

    /** Os cliques que devem virar eventos principais na propriedade. */
    public const PRINCIPAIS = ['clique_whatsapp', 'clique_rota'];

    /** As visualizações por pilar do portal, pelo começo do endereço (lugar conta no pilar da lista). */
    private static function porPilar(array $linhas): array
    {
        require_once __DIR__ . '/Portal.php';
        $soma = [];
        foreach ($linhas as [$d, $m]) {
            $caminho = strtok((string) $d[0], '?');
            foreach (Portal::PILARES as $k => $p) {
                if ($caminho === '/' . $k || str_starts_with($caminho, '/' . $k . '/')) {
                    $soma[$p[0]] = ($soma[$p[0]] ?? 0) + (int) $m[0];
                }
            }
            if (str_starts_with($caminho, '/roteiro')) {
                $soma['Roteiros'] = ($soma['Roteiros'] ?? 0) + (int) $m[0];
            } elseif (str_starts_with($caminho, '/experiencias')) {
                $soma['Experiências'] = ($soma['Experiências'] ?? 0) + (int) $m[0];
            } elseif (str_starts_with($caminho, '/lugar/')) {
                $soma['Fichas dos lugares'] = ($soma['Fichas dos lugares'] ?? 0) + (int) $m[0];
            }
        }
        arsort($soma);
        return array_map(fn ($k, $v) => ['rotulo' => $k, 'valor' => $v], array_keys($soma), $soma);
    }

    /**
     * Situação do lembrete: 'esperando' (os cliques ainda não chegaram ao
     * Google), 'marcar' (chegaram: falta marcar a estrela em Administrador,
     * Eventos) ou 'feito' (confirmado na tela).
     */
    public static function lembrete(): string
    {
        return Ajustes::ler('analytics', 'lembrete_principais', 'esperando', 0) ?: 'esperando';
    }

    public static function confirmarPrincipais(int $usuarioId): void
    {
        Ajustes::salvar('analytics', ['lembrete_principais' => 'feito'], 0, $usuarioId);
    }

    /**
     * Na leitura diária: se os cliques já chegaram e ainda não estavam
     * avisados, avisa por e-mail quem atende o contato (a equipe Softsul) e
     * acende o aviso no Início da plataforma. O Google só deixa marcar como
     * evento principal o que já chegou ao menos uma vez.
     */
    public static function conferirPrincipais(): string
    {
        if (self::lembrete() !== 'esperando') {
            return '';
        }
        [$linhas] = self::lote([self::pedido(90, [], ['eventName'], ['eventCount'], 10,
            ['filter' => ['fieldName' => 'eventName', 'inListFilter' => ['values' => self::PRINCIPAIS]]])], 0);
        $chegaram = array_map(fn ($l) => $l[0][0], $linhas);
        if (array_diff(self::PRINCIPAIS, $chegaram)) {
            return 'cliques de WhatsApp e rota ainda não chegaram ao Google';
        }
        Ajustes::salvar('analytics', ['lembrete_principais' => 'marcar'], 0);
        require_once __DIR__ . '/Email.php';
        $para = (string) ((require __DIR__ . '/config.php')['contato']['email'] ?? '');
        if (Email::configurado() && filter_var($para, FILTER_VALIDATE_EMAIL)) {
            Email::enviar($para, '[InvenTour] Marque os eventos principais no Google Analytics', Email::molde('Hora de marcar os eventos principais',
                Email::paragrafos("Os cliques em WhatsApp e em Como chegar já chegaram ao Google Analytics da propriedade InvenTour.\n\n"
                    . "No Google Analytics: Administrador, Exibição de dados, Eventos. Marque a estrela de clique_whatsapp e de clique_rota.\n\n"
                    . "Depois, em Configurações, Google Analytics, clique em Já marquei para apagar este aviso do Início."),
                ['texto' => 'Abrir o Google Analytics', 'url' => 'https://analytics.google.com/analytics/web/#/p' . self::propriedade() . '/admin/events/hub']));
        }
        return 'cliques chegaram: lembrete de eventos principais enviado';
    }

    /** Nome do canal em português (o GA4 devolve em inglês). */
    public static function canal(string $c): string
    {
        return [
            'Direct' => 'Direto', 'Organic Search' => 'Busca orgânica', 'Organic Social' => 'Redes sociais', 'Referral' => 'Outros sites',
            'Paid Search' => 'Busca paga', 'Paid Social' => 'Redes sociais pagas', 'Email' => 'E-mail', 'Unassigned' => 'Sem origem',
            'Organic Video' => 'Vídeo', 'Display' => 'Anúncio gráfico', 'Organic Shopping' => 'Compras', 'Cross-network' => 'Várias redes',
            'SMS' => 'SMS', 'Mobile Push Notifications' => 'Notificação', 'Audio' => 'Áudio', 'Affiliates' => 'Afiliados',
        ][$c] ?? $c;
    }
}
