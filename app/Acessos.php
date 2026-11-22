<?php
/**
 * Acessos do servidor: os logs do Apache resumidos em contadores (o mesmo
 * do EventCity, app/Acessos.php de lá).
 * Softsul Software
 *
 * O timer inventour-logs roda scripts/ler_logs_apache.php a cada 10
 * minutos, como www-data com o grupo adm (só esse serviço lê os logs). Cada
 * arquivo é lido de onde parou (acessos_leitura guarda inode e posição; no
 * rodízio diário o arquivo vira .log.1 e a leitura termina nele antes de
 * começar o novo). Na primeira vez, lê os 14 dias que o logrotate guarda.
 *
 * Nada de IP no banco: o visitante único é um resumo do IP com sal do dia
 * (acessos_visitantes), que não volta ao IP e some em 35 dias.
 *
 * O log do portal tem o host na frente (LogFormat inventour_host no vhost):
 * é por ele que o pedido vira destino. As linhas antigas, sem host, contam
 * para a plataforma com o destino 0.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/funcoes.php';

final class Acessos
{
    public const PASTA = '/var/log/apache2';

    /** Arquivo de log => site. */
    public const ARQUIVOS = [
        'portal.inventour.com.br_access.log' => 'portal',
        'app.inventour.com.br_access.log' => 'painel',
    ];

    /** O site institucional (inventour.com.br) fica em outro servidor: não entra. */
    public const SITES = ['portal' => 'Portais dos destinos', 'painel' => 'Painel'];

    public const GRUPOS = [
        'pessoa' => ['rotulo' => 'Pessoas', 'cor' => '#1C3E73'],
        'busca' => ['rotulo' => 'Robôs de busca', 'cor' => '#2E8B3E'],
        'ia' => ['rotulo' => 'Robôs de IA', 'cor' => '#7B4FC9'],
        'social' => ['rotulo' => 'Prévia de link', 'cor' => '#F5A524'],
        'outro' => ['rotulo' => 'Outros robôs', 'cor' => '#8A94A6'],
    ];

    /**
     * Robôs conhecidos, na ordem em que são testados: grupo, nome e o que
     * aparece no User-Agent. O que não casa com nenhum, mas se apresenta
     * como robô ou ferramenta, cai em "Outros robôs".
     */
    private const ROBOS = [
        ['ia', 'OpenAI', '/GPTBot|ChatGPT-User|OAI-SearchBot/i'],
        ['ia', 'Anthropic', '/ClaudeBot|Claude-User|Claude-SearchBot|anthropic-ai/i'],
        ['ia', 'Perplexity', '/PerplexityBot|Perplexity-User/i'],
        ['ia', 'Meta IA', '/meta-externalagent|meta-externalfetcher|FacebookBot/i'],
        ['ia', 'ByteDance', '/Bytespider|TikTokSpider/i'],
        ['ia', 'Amazon', '/Amazonbot/i'],
        ['ia', 'Common Crawl', '/CCBot/i'],
        ['ia', 'DuckAssist', '/DuckAssistBot/i'],
        ['ia', 'Mistral', '/MistralAI-User/i'],
        ['ia', 'Cohere', '/cohere-ai|cohere-training/i'],
        ['ia', 'You.com', '/YouBot/i'],
        ['ia', 'Diffbot', '/Diffbot/i'],
        ['busca', 'Google', '/Googlebot|Google-InspectionTool|GoogleOther|Storebot-Google|AdsBot-Google|APIs-Google|Mediapartners-Google|Google-Site-Verification/i'],
        ['busca', 'Bing', '/bingbot|BingPreview|adidxbot|msnbot/i'],
        ['busca', 'Yandex', '/Yandex/i'],
        ['busca', 'DuckDuckGo', '/DuckDuckBot|DuckDuckGo-Favicons/i'],
        ['busca', 'Apple', '/Applebot/i'],
        ['busca', 'Baidu', '/Baiduspider/i'],
        ['busca', 'Petal (Huawei)', '/PetalBot/i'],
        ['busca', 'Yahoo', '/Slurp/i'],
        ['busca', 'Seznam', '/SeznamBot/i'],
        ['social', 'WhatsApp', '/WhatsApp/i'],
        ['social', 'Facebook e Instagram', '/facebookexternalhit|facebookcatalog|Instagram/i'],
        ['social', 'X (Twitter)', '/Twitterbot/i'],
        ['social', 'LinkedIn', '/LinkedInBot/i'],
        ['social', 'Telegram', '/TelegramBot/i'],
        ['social', 'Slack', '/Slackbot/i'],
        ['social', 'Discord', '/Discordbot/i'],
        ['social', 'Pinterest', '/Pinterest/i'],
        ['social', 'Skype e Teams', '/SkypeUriPreview|Teams/i'],
        ['outro', 'Semrush', '/SemrushBot/i'],
        ['outro', 'Ahrefs', '/AhrefsBot/i'],
        ['outro', 'Majestic', '/MJ12bot/i'],
        ['outro', 'Moz', '/DotBot|rogerbot/i'],
        ['outro', 'Monitoramento', '/UptimeRobot|Pingdom|StatusCake|Better ?Uptime|Site24x7/i'],
        ['outro', 'Varredura', '/Censys|Expanse|zgrab|masscan|Nmap|nuclei|l9explore|Palo ?Alto|InternetMeasurement|ModatScanner|Odin/i'],
        ['outro', 'Ferramentas', '/curl|wget|python|Go-http-client|okhttp|axios|libwww|Java\/|node-fetch|aiohttp|httpx|Scrapy|Apache-HttpClient|PHP\//i'],
        ['outro', 'Outros robôs', '/bot\b|bot\/|crawl|spider|scrape|fetch|scan|headless|preview|monitor/i'],
    ];

    /** O que é arquivo ou chamada de API e não página: não entra em páginas vistas. */
    private const ARQUIVO = '#^/(assets|midia|foto|mapa-base|baixar|api|m)/|^/(acento\.css|capa\.jpg|sitemap\.xml|robots\.txt|BingSiteAuth\.xml|sw-coleta\.js|[a-f0-9]{32}\.txt)$|\.(css|js|mjs|map|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|otf|eot|mp4|webm|mp3|pdf|webmanifest|json)$#i';

    private static array $hosts = [];
    private static string $sal = '';

    /** Grupo e nome de quem pediu, pelo User-Agent. */
    public static function quem(string $ua): array
    {
        if ($ua === '' || $ua === '-') {
            return ['outro', 'Sem identificação'];
        }
        foreach (self::ROBOS as [$grupo, $nome, $re]) {
            if (preg_match($re, $ua)) {
                return [$grupo, $nome];
            }
        }
        return ['pessoa', 'pessoa'];
    }

    /** Host do portal => id do destino, com o subdomínio e o domínio próprio. */
    private static function carregarHosts(): void
    {
        $cfg = require __DIR__ . '/config.php';
        $dominio = $cfg['app']['dominio_portal'] ?? 'inventour.com.br';
        self::$sal = (string) ($cfg['app']['chave_secreta'] ?? '');
        foreach (Database::conexao()->query('SELECT id, slug, dominio_proprio FROM empresas')->fetchAll() as $e) {
            self::$hosts[strtolower($e['slug'] . '.' . $dominio)] = (int) $e['id'];
            if ($e['dominio_proprio']) {
                self::$hosts[strtolower((string) $e['dominio_proprio'])] = (int) $e['id'];
            }
        }
    }

    /**
     * Lê o que entrou nos logs desde a última vez e soma nos contadores.
     *
     * $pasta muda só no teste, com uma cópia dos logs.
     *
     * @return list<string> uma linha por arquivo, para o log do timer
     */
    public static function ler(string $pasta = self::PASTA): array
    {
        self::carregarHosts();
        $pdo = Database::conexao();
        $log = [];
        foreach (self::ARQUIVOS as $base => $site) {
            $atual = $pasta . '/' . $base;
            if (!is_readable($atual)) {
                $log[] = $base . ': sem acesso ao arquivo';
                continue;
            }
            $q = $pdo->prepare('SELECT inode, posicao FROM acessos_leitura WHERE arquivo = ?');
            $q->execute([$base]);
            $estado = $q->fetch();
            clearstatcache();
            $inodeAtual = (int) fileinode($atual);
            $velho = $atual . '.1';
            $partes = [];
            if (!$estado) {
                // Primeira leitura: o histórico que o logrotate guarda, do
                // mais velho para o mais novo.
                $gz = glob($atual . '.*.gz') ?: [];
                usort($gz, fn ($a, $b) => self::numero($b) <=> self::numero($a));
                foreach ($gz as $g) {
                    $partes[] = [$g, 0];
                }
                if (is_file($velho)) {
                    $partes[] = [$velho, 0];
                }
                $partes[] = [$atual, 0];
            } elseif (is_file($velho) && (int) fileinode($velho) === (int) $estado['inode']) {
                $partes[] = [$velho, (int) $estado['posicao']];
                $partes[] = [$atual, 0];
            } elseif ($inodeAtual === (int) $estado['inode']) {
                $partes[] = [$atual, filesize($atual) < (int) $estado['posicao'] ? 0 : (int) $estado['posicao']];
            } else {
                $partes[] = [$atual, 0];
            }
            $soma = new SomaAcessos($site);
            $posicao = 0;
            foreach ($partes as [$arquivo, $de]) {
                $ate = self::lerArquivo($arquivo, $de, $soma);
                if ($arquivo === $atual) {
                    $posicao = $ate;
                }
            }
            Database::transacao(function (PDO $pdo) use ($soma, $base, $inodeAtual, $posicao): void {
                $soma->gravar($pdo);
                $pdo->prepare('INSERT INTO acessos_leitura (arquivo, inode, posicao, lido_em) VALUES (?, ?, ?, NOW())
                               ON DUPLICATE KEY UPDATE inode = VALUES(inode), posicao = VALUES(posicao), lido_em = NOW()')
                    ->execute([$base, $inodeAtual, $posicao]);
            });
            $log[] = $base . ': ' . $soma->linhas . ' linhas' . ($soma->ruins ? ', ' . $soma->ruins . ' fora do formato' : '');
        }
        $pdo->exec('DELETE FROM acessos_visitantes WHERE dia < CURDATE() - INTERVAL 35 DAY');
        $pdo->exec('DELETE FROM acessos_caminhos WHERE dia < CURDATE() - INTERVAL 90 DAY');
        $pdo->exec('DELETE FROM acessos_hora WHERE dia < CURDATE() - INTERVAL 400 DAY');
        return $log;
    }

    private static function numero(string $arquivo): int
    {
        return preg_match('/\.(\d+)\.gz$/', $arquivo, $m) ? (int) $m[1] : 0;
    }

    /** Lê de $de até a última linha completa; devolve onde parou. */
    private static function lerArquivo(string $arquivo, int $de, SomaAcessos $soma): int
    {
        if (str_ends_with($arquivo, '.gz')) {
            $f = gzopen($arquivo, 'rb');
            if (!$f) {
                return 0;
            }
            while (($linha = gzgets($f)) !== false) {
                self::linha($linha, $soma);
            }
            gzclose($f);
            return 0;
        }
        $f = fopen($arquivo, 'rb');
        if (!$f) {
            return $de;
        }
        fseek($f, $de);
        $pos = $de;
        while (($linha = fgets($f)) !== false) {
            if (!str_ends_with($linha, "\n")) {
                break; // o Apache ainda está escrevendo esta linha
            }
            $pos += strlen($linha);
            self::linha($linha, $soma);
        }
        fclose($f);
        return $pos;
    }

    /** Uma linha do formato combined, com ou sem o host na frente. */
    private static function linha(string $linha, SomaAcessos $soma): void
    {
        if (!preg_match('/^(?:(\S+) )?(\S+) \S+ \S+ \[([^\]]+)\] "((?:[^"\\\\]|\\\\.)*)" (\d{3}) (\S+) "(?:[^"\\\\]|\\\\.)*" "((?:[^"\\\\]|\\\\.)*)"/', $linha, $m)) {
            $soma->ruins++;
            return;
        }
        $quando = DateTime::createFromFormat('d/M/Y:H:i:s O', $m[3]);
        if (!$quando) {
            $soma->ruins++;
            return;
        }
        $quando->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $host = strtolower((string) preg_replace('/:\d+$/', '', $m[1]));
        $empresa = $soma->site === 'portal' ? (self::$hosts[$host] ?? 0) : 0;
        $pedido = explode(' ', $m[4]);
        $caminho = count($pedido) === 3 ? (string) strtok($pedido[1], '?') : '(pedido inválido)';
        $caminho = mb_substr($caminho === '' ? '/' : $caminho, 0, 255);
        [$grupo, $agente] = self::quem(stripcslashes($m[7]));
        $dia = $quando->format('Y-m-d');
        $pagina = !preg_match(self::ARQUIVO, $caminho);
        $codigo = (int) $m[5];
        $classe = in_array($codigo, [404, 429], true) ? $codigo : intdiv($codigo, 100) * 100;
        $soma->somar($dia, (int) $quando->format('G'), $empresa, $grupo, $agente, $classe, $pagina, $m[6] === '-' ? 0 : (int) $m[6]);
        // Página, e todo erro (inclusive de arquivo), entra na lista de caminhos.
        if ($pagina || $classe >= 400) {
            $soma->caminho($dia, $empresa, $grupo, $classe, $caminho);
        }
        if ($grupo === 'pessoa' && $pagina && $classe < 400) {
            $soma->visitante($dia, $empresa, substr(hash_hmac('sha256', $m[2] . '|' . $dia, self::$sal, true), 0, 8));
        }
        $soma->linhas++;
    }

    // ------------------------------------------------------------ CONSULTAS

    /** Filtro comum das consultas: período, sites e destinos. */
    private static function onde(int $dias, array $sites, array $empresas, string $alias = ''): array
    {
        $a = $alias !== '' ? $alias . '.' : '';
        $sql = ["{$a}dia >= ?"];
        $args = [date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'))];
        if ($sites) {
            $sql[] = "{$a}site IN (" . implode(',', array_fill(0, count($sites), '?')) . ')';
            array_push($args, ...$sites);
        }
        if ($empresas) {
            $sql[] = "{$a}empresa_id IN (" . implode(',', array_fill(0, count($empresas), '?')) . ')';
            array_push($args, ...array_map('intval', $empresas));
        }
        return [implode(' AND ', $sql), $args];
    }

    private static function consulta(string $sql, array $args): array
    {
        $q = Database::conexao()->prepare($sql);
        $q->execute($args);
        return $q->fetchAll();
    }

    /** Os números do topo: páginas, visitantes, robôs, erros e tráfego. */
    public static function resumo(int $dias, array $sites = [], array $empresas = []): array
    {
        [$w, $a] = self::onde($dias, $sites, $empresas);
        $t = self::consulta("SELECT COALESCE(SUM(pedidos), 0) pedidos,
                COALESCE(SUM(CASE WHEN grupo = 'pessoa' AND classe < 400 THEN paginas END), 0) paginas,
                COALESCE(SUM(CASE WHEN grupo <> 'pessoa' THEN pedidos END), 0) robos,
                COALESCE(SUM(CASE WHEN classe = 404 THEN pedidos END), 0) e404,
                COALESCE(SUM(CASE WHEN classe = 429 THEN pedidos END), 0) e429,
                COALESCE(SUM(CASE WHEN classe BETWEEN 400 AND 499 AND classe NOT IN (404, 429) THEN pedidos END), 0) e4,
                COALESCE(SUM(CASE WHEN classe >= 500 THEN pedidos END), 0) e5,
                COALESCE(SUM(bytes), 0) bytes
            FROM acessos_hora WHERE {$w}", $a)[0];
        $v = self::consulta("SELECT COUNT(*) n FROM acessos_visitantes WHERE {$w}", $a)[0];
        $ultimo = Database::conexao()->query('SELECT MAX(lido_em) FROM acessos_leitura')->fetchColumn();
        return ['pedidos' => (int) $t['pedidos'], 'paginas' => (int) $t['paginas'], 'robos' => (int) $t['robos'],
                'e404' => (int) $t['e404'], 'e429' => (int) $t['e429'], 'e4' => (int) $t['e4'], 'e5' => (int) $t['e5'], 'bytes' => (int) $t['bytes'], 'visitantes' => (int) $v['n'],
                'parte_robos' => $t['pedidos'] ? (int) round($t['robos'] / $t['pedidos'] * 100) : 0, 'ultimo' => $ultimo ?: null];
    }

    /** Páginas por dia, pessoas e robôs empilhados (Graficos::colunas). */
    public static function porDia(int $dias, array $sites = [], array $empresas = []): array
    {
        [$w, $a] = self::onde($dias, $sites, $empresas);
        $linhas = self::consulta("SELECT dia, SUM(CASE WHEN grupo = 'pessoa' THEN paginas END) p, SUM(CASE WHEN grupo <> 'pessoa' THEN paginas END) r
                                  FROM acessos_hora WHERE {$w} AND classe < 400 GROUP BY dia", $a);
        $porDia = array_column($linhas, null, 'dia');
        $de = $a[0];
        $serie = [];
        for ($i = 0; $i < $dias; $i++) {
            $d = date('Y-m-d', strtotime($de . " +{$i} days"));
            // Com 30 ou 90 dias, só alguns rótulos, para caberem.
            $rot = $dias <= 14 ? ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][(int) date('w', strtotime($d))]
                 : ($i % ($dias > 31 ? 14 : 5) === 0 ? date('d/m', strtotime($d)) : '');
            $serie[] = ['rotulo' => $rot, 'titulo' => dataBr($d, 'd/m'), 'data' => $d, 'pessoas' => (int) ($porDia[$d]['p'] ?? 0), 'robos' => (int) ($porDia[$d]['r'] ?? 0)];
        }
        return $serie;
    }

    /** Páginas vistas por pessoas em cada hora do dia, somadas no período. */
    public static function porHora(int $dias, array $sites = [], array $empresas = []): array
    {
        [$w, $a] = self::onde($dias, $sites, $empresas);
        $porHora = array_column(self::consulta("SELECT hora, SUM(paginas) n FROM acessos_hora WHERE {$w} AND grupo = 'pessoa' AND classe < 400 GROUP BY hora", $a), 'n', 'hora');
        $serie = [];
        for ($h = 0; $h < 24; $h++) {
            $serie[] = ['rotulo' => $h % 3 === 0 ? (string) $h : '', 'titulo' => sprintf('%02dh', $h), 'pessoas' => (int) ($porHora[$h] ?? 0)];
        }
        return $serie;
    }

    /** Pedidos por grupo (faixa) e os robôs mais ativos (barras). */
    public static function quemPediu(int $dias, array $sites = [], array $empresas = []): array
    {
        [$w, $a] = self::onde($dias, $sites, $empresas);
        $grupos = array_column(self::consulta("SELECT grupo, SUM(pedidos) n FROM acessos_hora WHERE {$w} GROUP BY grupo", $a), 'n', 'grupo');
        $faixa = [];
        foreach (self::GRUPOS as $g => $d) {
            $faixa[] = ['rotulo' => $d['rotulo'], 'valor' => (int) ($grupos[$g] ?? 0), 'cor' => $d['cor']];
        }
        $robos = array_map(fn ($r) => ['rotulo' => $r['agente'], 'valor' => (int) $r['n'], 'cor' => self::GRUPOS[$r['grupo']]['cor']],
            self::consulta("SELECT grupo, agente, SUM(pedidos) n FROM acessos_hora WHERE {$w} AND grupo <> 'pessoa' GROUP BY grupo, agente ORDER BY n DESC LIMIT 12", $a));
        return ['faixa' => $faixa, 'robos' => $robos];
    }

    /** Páginas vistas por pessoas em cada destino (só o portal). */
    public static function porDestino(int $dias, array $empresas = []): array
    {
        [$w, $a] = self::onde($dias, ['portal'], $empresas, 'h');
        return array_map(fn ($r) => ['rotulo' => $r['nome'] ?? 'Sem destino', 'valor' => (int) $r['n'], 'cor' => '#1C3E73'],
            self::consulta("SELECT e.nome_fantasia nome, SUM(h.paginas) n FROM acessos_hora h LEFT JOIN empresas e ON e.id = h.empresa_id
                            WHERE {$w} AND h.grupo = 'pessoa' AND h.classe < 400 GROUP BY h.empresa_id, e.nome_fantasia ORDER BY n DESC LIMIT 12", $a));
    }

    /**
     * Os caminhos mais pedidos de um tipo: 'pessoas' (páginas vistas),
     * 'robos' (o que os robôs de busca e de IA leem), '404', '429' e '5xx'.
     */
    public static function caminhos(string $tipo, int $dias, array $sites = [], array $empresas = [], int $limite = 10): array
    {
        [$w, $a] = self::onde($dias, $sites, $empresas, 'c');
        $filtro = match ($tipo) {
            'pessoas' => "c.grupo = 'pessoa' AND c.classe < 400",
            'robos' => "c.grupo IN ('busca', 'ia') AND c.classe < 400",
            '404' => 'c.classe = 404',
            '429' => 'c.classe = 429',
            default => 'c.classe >= 500',
        };
        return self::consulta("SELECT c.site, c.caminho, e.slug, e.dominio_proprio, SUM(c.pedidos) n FROM acessos_caminhos c LEFT JOIN empresas e ON e.id = c.empresa_id
                               WHERE {$w} AND {$filtro} GROUP BY c.site, c.empresa_id, e.slug, e.dominio_proprio, c.caminho ORDER BY n DESC LIMIT {$limite}", $a);
    }
}

/** Contadores de uma leitura, gravados de uma vez no fim. */
final class SomaAcessos
{
    public int $linhas = 0;
    public int $ruins = 0;
    private array $hora = [];
    private array $caminhos = [];
    private array $visitantes = [];

    public function __construct(public readonly string $site)
    {
    }

    public function somar(string $dia, int $hora, int $empresa, string $grupo, string $agente, int $classe, bool $pagina, int $bytes): void
    {
        $c = "{$dia}|{$hora}|{$empresa}|{$grupo}|{$agente}|{$classe}";
        $this->hora[$c] ??= [0, 0, 0];
        $this->hora[$c][0]++;
        $this->hora[$c][1] += $pagina ? 1 : 0;
        $this->hora[$c][2] += $bytes;
    }

    public function caminho(string $dia, int $empresa, string $grupo, int $classe, string $caminho): void
    {
        $c = "{$dia}|{$empresa}|{$grupo}|{$classe}|{$caminho}";
        $this->caminhos[$c] = ($this->caminhos[$c] ?? 0) + 1;
    }

    public function visitante(string $dia, int $empresa, string $marca): void
    {
        $this->visitantes["{$dia}|{$empresa}|" . bin2hex($marca)] = true;
    }

    public function gravar(PDO $pdo): void
    {
        foreach (array_chunk($this->hora, 500, true) as $bloco) {
            $args = [];
            foreach ($bloco as $c => [$p, $pg, $b]) {
                [$dia, $hora, $empresa, $grupo, $agente, $classe] = explode('|', (string) $c);
                array_push($args, $dia, (int) $hora, $this->site, (int) $empresa, $grupo, $agente, (int) $classe, $p, $pg, $b);
            }
            $pdo->prepare('INSERT INTO acessos_hora (dia, hora, site, empresa_id, grupo, agente, classe, pedidos, paginas, bytes) VALUES '
                . implode(',', array_fill(0, count($bloco), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'))
                . ' ON DUPLICATE KEY UPDATE pedidos = pedidos + VALUES(pedidos), paginas = paginas + VALUES(paginas), bytes = bytes + VALUES(bytes)')
                ->execute($args);
        }
        foreach (array_chunk($this->caminhos, 500, true) as $bloco) {
            $args = [];
            foreach ($bloco as $c => $n) {
                [$dia, $empresa, $grupo, $classe, $caminho] = explode('|', (string) $c, 5);
                array_push($args, $dia, $this->site, (int) $empresa, $grupo, (int) $classe, $caminho, $n);
            }
            $pdo->prepare('INSERT INTO acessos_caminhos (dia, site, empresa_id, grupo, classe, caminho, pedidos) VALUES '
                . implode(',', array_fill(0, count($bloco), '(?, ?, ?, ?, ?, ?, ?)'))
                . ' ON DUPLICATE KEY UPDATE pedidos = pedidos + VALUES(pedidos)')->execute($args);
        }
        foreach (array_chunk(array_keys($this->visitantes), 500) as $bloco) {
            $args = [];
            foreach ($bloco as $c) {
                [$dia, $empresa, $marca] = explode('|', (string) $c);
                array_push($args, $dia, $this->site, (int) $empresa, hex2bin($marca));
            }
            $pdo->prepare('INSERT IGNORE INTO acessos_visitantes (dia, site, empresa_id, marca) VALUES '
                . implode(',', array_fill(0, count($bloco), '(?, ?, ?, ?)')))->execute($args);
        }
    }
}
