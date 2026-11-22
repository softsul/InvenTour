<?php
/**
 * Bing Webmaster pela API, com a chave de API da conta, e o IndexNow
 * (o mesmo do EventCity, app/Bing.php de lá).
 * Softsul Software
 *
 * No mesmo molde do Search Console (app/SearchConsole.php): o job
 * puxar_bing roda uma vez por dia e grava em bing_diaria e bing_termos, e
 * as telas leem dali. A diferença é que o Bing não tem a propriedade do
 * domínio inteiro: cada portal é um site na conta. O job cadastra o portal
 * que falta (AddSite), deixa o código de verificação onde o portal o serve
 * (/BingSiteAuth.xml, portal/bing-auth.php), pede a verificação e, com o
 * site verificado, envia o sitemap e lê os números.
 *
 * Os números do Bing valem também para o Yahoo, o DuckDuckGo e o Ecosia,
 * que mostram resultados do Bing, e o índice dele serve ao Copilot e à
 * busca do ChatGPT.
 *
 * IndexNow: o job avisar_indexnow manda, de hora em hora, os endereços de
 * lugar homologado e de roteiro publicado que mudaram. O Bing, o Yandex e outros buscadores
 * dividem os avisos entre si. A chave sai da chave secreta do config, e
 * cada portal a serve em /{chave}.txt (portal/indexnow.php).
 *
 * A chave de API fica cifrada em ajustes (grupo bing, empresa 0) e nunca
 * entra em mensagem de erro, porque viaja na URL.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Ajustes.php';
require_once __DIR__ . '/SearchConsole.php';
require_once __DIR__ . '/funcoes.php';

final class Bing
{
    private const API = 'https://ssl.bing.com/webmaster/api.svc/json/';
    private const INDEXNOW = 'https://api.indexnow.org/indexnow';

    public static function chave(): string
    {
        return trim(Ajustes::ler('bing', 'api_chave', '', 0));
    }

    public static function configurado(): bool
    {
        return self::chave() !== '';
    }

    /** O fim da chave, para a tela mostrar qual está gravada. */
    public static function finalDaChave(): string
    {
        $c = self::chave();
        return $c !== '' ? '...' . substr($c, -4) : '';
    }

    private static function http(string $metodo, string $url, ?array $corpo, string $origem): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json; charset=utf-8'],
            CURLOPT_USERAGENT => 'InvenTour (app.inventour.com.br)',
        ]);
        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_SLASHES));
        }
        $resposta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($resposta === false) {
            throw new RuntimeException('Sem resposta do ' . $origem . '.');
        }
        $dados = json_decode((string) $resposta, true);
        if ($codigo >= 400) {
            $msg = is_array($dados) ? ($dados['Message'] ?? $dados['message'] ?? '') : '';
            throw new RuntimeException('O ' . $origem . ' respondeu ' . $codigo . ($msg !== '' ? ': ' . $msg : '') . '.');
        }
        return ['codigo' => $codigo, 'dados' => is_array($dados) ? $dados : []];
    }

    /** Uma chamada da API do Bing; devolve o "d" da resposta. */
    private static function api(string $metodo, array $consulta = [], ?array $corpo = null): mixed
    {
        if (!self::configurado()) {
            throw new RuntimeException('Falta a chave de API do Bing Webmaster.');
        }
        $url = self::API . $metodo . '?' . http_build_query($consulta + ['apikey' => self::chave()]);
        return self::http($corpo !== null ? 'POST' : 'GET', $url, $corpo, 'Bing')['dados']['d'] ?? null;
    }

    /** "/Date(1316156400000-0700)/" para Y-m-d, no dia em que o Bing contou. */
    private static function data(string $d): ?string
    {
        if (!preg_match('#Date\((-?\d+)([+-]\d{4})?\)#', $d, $m)) {
            return null;
        }
        $segundos = intdiv((int) $m[1], 1000);
        if (!empty($m[2])) {
            $sinal = $m[2][0] === '-' ? -1 : 1;
            $segundos += $sinal * ((int) substr($m[2], 1, 2) * 3600 + (int) substr($m[2], 3, 2) * 60);
        }
        return gmdate('Y-m-d', $segundos);
    }

    /** O endereço do portal como o Bing guarda (sem a barra do fim). */
    private static function normal(string $url): string
    {
        return strtolower(rtrim($url, '/'));
    }

    /** Os sites da conta, pelo endereço normalizado. */
    public static function sites(): array
    {
        $saida = [];
        foreach ((array) self::api('GetUserSites') as $s) {
            $saida[self::normal((string) ($s['Url'] ?? ''))] = $s;
        }
        return $saida;
    }

    /** Pasta onde o portal acha o código de verificação de cada host. */
    private static function pastaCodigos(): string
    {
        $cfg = require __DIR__ . '/config.php';
        return $cfg['caminhos']['cache'] . '/bing';
    }

    /** O código que o portal deste host serve em /BingSiteAuth.xml, ou ''. */
    public static function codigoDoHost(string $host): string
    {
        if (!preg_match('/^[a-z0-9.-]{1,253}$/', $host)) {
            return '';
        }
        $arquivo = self::pastaCodigos() . '/' . $host . '.txt';
        $c = is_file($arquivo) ? trim((string) file_get_contents($arquivo)) : '';
        return preg_match('/^[A-Fa-f0-9]{16,64}$/', $c) ? $c : '';
    }

    private static function guardarCodigo(string $host, string $codigo): void
    {
        $pasta = self::pastaCodigos();
        if (!is_dir($pasta)) {
            mkdir($pasta, 0770, true);
        }
        file_put_contents($pasta . '/' . $host . '.txt', $codigo);
    }

    /**
     * Cadastra e verifica os portais que faltam, envia o sitemap e puxa os
     * números de cada destino ativo.
     *
     * @return list<string> uma linha por destino, para o log da fila
     */
    public static function puxar(): array
    {
        $pdo = Database::conexao();
        $sites = self::sites();
        $log = [];
        $dia = $pdo->prepare('INSERT INTO bing_diaria (empresa_id, dia, cliques, impressoes) VALUES (?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE cliques = VALUES(cliques), impressoes = VALUES(impressoes)');
        $apaga = $pdo->prepare('DELETE FROM bing_termos WHERE empresa_id = ?');
        $termo = $pdo->prepare('INSERT INTO bing_termos (empresa_id, tipo, chave, cliques, impressoes, posicao) VALUES (?, ?, ?, ?, ?, ?)');
        $situacao = $pdo->prepare('INSERT INTO bing_sites (empresa_id, url, verificado, sitemap, conferido_em) VALUES (?, ?, ?, ?, NOW())
                                   ON DUPLICATE KEY UPDATE url = VALUES(url), verificado = VALUES(verificado), sitemap = VALUES(sitemap), conferido_em = NOW()');
        $enviados = array_column($pdo->query('SELECT empresa_id, sitemap FROM bing_sites')->fetchAll(), 'sitemap', 'empresa_id');
        foreach (SearchConsole::destinosAbertos() as $e) {
            $id = (int) $e['id'];
            $url = SearchConsole::prefixo($e);
            $host = (string) parse_url($url, PHP_URL_HOST);
            $chave = self::normal($url);
            if (!isset($sites[$chave])) {
                self::api('AddSite', [], ['siteUrl' => $url]);
                $sites = self::sites();
                $log[] = $e['nome_fantasia'] . ': cadastrado no Bing';
            }
            $site = $sites[$chave] ?? null;
            if (!$site) {
                $log[] = $e['nome_fantasia'] . ': o Bing não devolveu o site cadastrado';
                continue;
            }
            $verificado = !empty($site['IsVerified']);
            if (!$verificado && !empty($site['AuthenticationCode'])) {
                self::guardarCodigo($host, (string) $site['AuthenticationCode']);
                try {
                    $verificado = (bool) self::api('VerifySite', [], ['siteUrl' => $url]);
                } catch (RuntimeException $erro) {
                    $verificado = false;
                }
                $log[] = $e['nome_fantasia'] . ($verificado ? ': verificado agora' : ': aguardando a verificação');
            }
            $sitemap = (int) ($enviados[$id] ?? 0);
            if (!$verificado) {
                $situacao->execute([$id, $url, 0, $sitemap]);
                continue;
            }
            if (!$sitemap) {
                self::api('SubmitFeed', [], ['siteUrl' => $url, 'feedUrl' => $url . 'sitemap.xml']);
                $sitemap = 1;
                $log[] = $e['nome_fantasia'] . ': sitemap enviado';
            }
            if ($envio = self::empurrarIndexacao($id, $url, $host)) {
                $log[] = $e['nome_fantasia'] . ': ' . $envio;
            }
            $diario = (array) self::api('GetRankAndTrafficStats', ['siteUrl' => $url]);
            $termos = self::agrupar((array) self::api('GetQueryStats', ['siteUrl' => $url]));
            $paginas = self::agrupar((array) self::api('GetPageStats', ['siteUrl' => $url]));
            $pdo->beginTransaction();
            foreach ($diario as $l) {
                if ($d = self::data((string) ($l['Date'] ?? ''))) {
                    $dia->execute([$id, $d, (int) ($l['Clicks'] ?? 0), (int) ($l['Impressions'] ?? 0)]);
                }
            }
            $apaga->execute([$id]);
            foreach ([['termo', $termos], ['pagina', $paginas]] as [$tipo, $linhas]) {
                foreach ($linhas as $chaveTermo => $l) {
                    $termo->execute([$id, $tipo, mb_substr((string) $chaveTermo, 0, 500), $l['c'], $l['i'], $l['p']]);
                }
            }
            $situacao->execute([$id, $url, 1, $sitemap]);
            $pdo->commit();
            $log[] = $e['nome_fantasia'] . ': ' . count($diario) . ' dias, ' . count($termos) . ' termos';
        }
        return $log;
    }

    /**
     * Domínio novo fica semanas na fila do Bing sem nenhuma página no índice
     * (12/11/2026: nem o início dos portais tinha sido rastreado, só o
     * sitemap lido). Por isso, uma vez por destino, o IndexNow recebe todos
     * os endereços do sitemap, e a API do Bing recebe pedidos de indexação
     * dentro da cota do dia (100), do início para o fim do sitemap, até
     * cobrir tudo. O avisarIndexNow segue avisando só o que muda.
     */
    private static function empurrarIndexacao(int $empresaId, string $url, string $host): string
    {
        $feito = (int) Ajustes::ler('bing', 'indexacao_pedidos', '0', $empresaId);
        $indexnow = Ajustes::ler('bing', 'indexacao_indexnow', '', $empresaId);
        $xml = @file_get_contents($url . 'sitemap.xml', false, stream_context_create(['http' => ['timeout' => 20]]));
        if (!$xml || !preg_match_all('#<loc>([^<]+)</loc>#', $xml, $m)) {
            return '';
        }
        $urls = array_values(array_unique(array_map('html_entity_decode', $m[1])));
        $partes = [];
        if ($indexnow === '') {
            $chave = self::chaveIndexNow();
            foreach (array_chunk($urls, 10000) as $lote) {
                self::http('POST', self::INDEXNOW, ['host' => $host, 'key' => $chave, 'keyLocation' => $url . $chave . '.txt', 'urlList' => $lote], 'IndexNow');
            }
            Ajustes::salvar('bing', ['indexacao_indexnow' => date('Y-m-d H:i:s')], $empresaId);
            $partes[] = count($urls) . ' endereços no IndexNow';
        }
        if ($feito < count($urls)) {
            $cota = (int) (((array) self::api('GetUrlSubmissionQuota', ['siteUrl' => $url]))['DailyQuota'] ?? 0);
            $lote = array_slice($urls, $feito, min($cota, 500));
            if ($lote) {
                self::api('SubmitUrlBatch', [], ['siteUrl' => $url, 'urlList' => $lote]);
                Ajustes::salvar('bing', ['indexacao_pedidos' => (string) ($feito + count($lote))], $empresaId);
                $partes[] = count($lote) . ' pedidos de indexação (' . ($feito + count($lote)) . ' de ' . count($urls) . ')';
            }
        }
        return implode(', ', $partes);
    }

    /**
     * O Bing devolve termo e página por semana; aqui viram os 28 últimos
     * dias somados, com a posição média pesada pelas impressões, os 50
     * primeiros por clique.
     *
     * @return array<string,array{c:int,i:int,p:?float}>
     */
    private static function agrupar(array $linhas): array
    {
        $de = date('Y-m-d', strtotime('-28 days'));
        $soma = [];
        foreach ($linhas as $l) {
            $d = self::data((string) ($l['Date'] ?? ''));
            $k = trim((string) ($l['Query'] ?? ''));
            if ($k === '' || ($d !== null && $d < $de)) {
                continue;
            }
            $soma[$k] ??= ['c' => 0, 'i' => 0, 'pi' => 0.0];
            $soma[$k]['c'] += (int) ($l['Clicks'] ?? 0);
            $soma[$k]['i'] += (int) ($l['Impressions'] ?? 0);
            $soma[$k]['pi'] += (float) ($l['AvgImpressionPosition'] ?? 0) * (int) ($l['Impressions'] ?? 0);
        }
        uasort($soma, fn ($a, $b) => [$b['c'], $b['i']] <=> [$a['c'], $a['i']]);
        return array_map(fn ($s) => ['c' => $s['c'], 'i' => $s['i'], 'p' => $s['i'] ? round($s['pi'] / $s['i'], 2) : null], array_slice($soma, 0, 50, true));
    }

    /** Resumo de 30 dias de um destino (ou de todos, com null) para o Início. */
    public static function resumo(?int $empresaId): array
    {
        $pdo = Database::conexao();
        $onde = $empresaId ? 'empresa_id = ' . (int) $empresaId : '1 = 1';
        $t = $pdo->query("SELECT COALESCE(SUM(cliques), 0), COALESCE(SUM(impressoes), 0), MAX(dia)
                          FROM bing_diaria WHERE {$onde} AND dia >= CURDATE() - INTERVAL 30 DAY")->fetch(PDO::FETCH_NUM);
        $p = $pdo->query("SELECT SUM(posicao * impressoes) / NULLIF(SUM(impressoes), 0) FROM bing_termos WHERE {$onde} AND tipo = 'termo'")->fetchColumn();
        $termos = $pdo->query("SELECT chave, SUM(cliques) c, SUM(impressoes) i FROM bing_termos
                               WHERE {$onde} AND tipo = 'termo' GROUP BY chave ORDER BY c DESC, i DESC LIMIT 8")->fetchAll();
        $sites = $pdo->query("SELECT COUNT(*), COALESCE(SUM(verificado), 0) FROM bing_sites WHERE {$onde}")->fetch(PDO::FETCH_NUM);
        return ['cliques' => (int) $t[0], 'impressoes' => (int) $t[1], 'posicao' => $p !== null && $p !== false ? round((float) $p, 1) : null,
                'ultimo' => $t[2], 'termos' => $termos, 'sites' => (int) $sites[0], 'verificados' => (int) $sites[1]];
    }

    // ------------------------------------------------------------- INDEXNOW

    /** A chave do IndexNow: 32 letras e números, a mesma em todo portal. */
    public static function chaveIndexNow(): string
    {
        $cfg = require __DIR__ . '/config.php';
        return substr(hash_hmac('sha256', 'indexnow', (string) ($cfg['app']['chave_secreta'] ?? '')), 0, 32);
    }

    /**
     * Avisa o IndexNow dos lugares e roteiros que mudaram desde a última vez
     * (e do início de cada portal com mudança). Lugar homologado que mudou,
     * foi homologado ou saiu do ar (arquivado, voltou para conferência)
     * entra: o buscador vê a página nova ou que ela não existe mais.
     *
     * @return list<string>
     */
    public static function avisarIndexNow(): array
    {
        $pdo = Database::conexao();
        $agora = date('Y-m-d H:i:s');
        $marca = Ajustes::ler('bing', 'indexnow_marca', '', 0) ?: date('Y-m-d H:i:s', strtotime('-1 hour'));
        $porEmpresa = [];
        $q = $pdo->prepare("SELECT id, empresa_id, nome FROM inventario_itens
                            WHERE homologado_em IS NOT NULL AND GREATEST(COALESCE(atualizado_em, homologado_em), homologado_em) > ? LIMIT 5000");
        $q->execute([$marca]);
        foreach ($q->fetchAll() as $i) {
            $porEmpresa[(int) $i['empresa_id']][] = '/lugar/' . (int) $i['id'] . '-' . self::slug((string) $i['nome']);
        }
        $q = $pdo->prepare('SELECT id, empresa_id, titulo FROM roteiros WHERE COALESCE(atualizado_em, criado_em) > ? LIMIT 5000');
        $q->execute([$marca]);
        foreach ($q->fetchAll() as $r) {
            $porEmpresa[(int) $r['empresa_id']][] = '/roteiro/' . (int) $r['id'] . '-' . self::slug((string) $r['titulo']);
        }
        $log = [];
        if ($porEmpresa) {
            $empresas = SearchConsole::destinosAbertos();
            $chave = self::chaveIndexNow();
            foreach ($empresas as $e) {
                $caminhos = $porEmpresa[(int) $e['id']] ?? [];
                if (!$caminhos) {
                    continue;
                }
                $base = rtrim(SearchConsole::prefixo($e), '/');
                $host = (string) parse_url($base, PHP_URL_HOST);
                $urls = array_values(array_unique(array_merge([$base . '/'], array_map(fn ($c) => $base . $c, $caminhos))));
                foreach (array_chunk($urls, 10000) as $lote) {
                    self::http('POST', self::INDEXNOW, ['host' => $host, 'key' => $chave, 'keyLocation' => $base . '/' . $chave . '.txt', 'urlList' => $lote], 'IndexNow');
                }
                $log[] = $e['nome_fantasia'] . ': ' . plural(count($urls), 'endereço avisado', 'endereços avisados');
            }
        }
        Ajustes::salvar('bing', ['indexnow_marca' => $agora], 0);
        return $log;
    }

    /** O apelido do endereço, o mesmo do portal (Portal::slug). */
    private static function slug(string $t): string
    {
        require_once __DIR__ . '/Portal.php';
        return Portal::slug($t);
    }
}
