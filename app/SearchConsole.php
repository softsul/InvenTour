<?php
/**
 * Google Search Console pela API, com conta de serviço (o mesmo do
 * EventCity, app/SearchConsole.php de lá).
 * Softsul Software
 *
 * A propriedade é o domínio inteiro (sc-domain:inventour.com.br), então
 * cada destino sai do filtro de página pelo endereço do portal dele. O job
 * puxar_busca roda uma vez por dia e grava em busca_diaria e busca_termos:
 * as telas leem dali, sem esperar o Google. Só entram os portais abertos
 * (licença ativa ou avaliação no prazo): portal fechado não vai ao Google.
 *
 * A chave JSON da conta de serviço é colada na tela da plataforma
 * (Configurações, Google Search Console) e fica cifrada em ajustes. O
 * token sai de um JWT assinado aqui mesmo com openssl, sem biblioteca.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Ajustes.php';

final class SearchConsole
{
    public const PROPRIEDADE = 'sc-domain:inventour.com.br';
    private const ESCOPO = 'https://www.googleapis.com/auth/webmasters';

    /** Tokens por escopo (o Analytics usa a mesma conta de serviço). */
    private static array $tokens = [];

    /** A chave da conta de serviço, ou null. */
    public static function chave(): ?array
    {
        $json = Ajustes::ler('google', 'chave_json', '', 0);
        $c = $json !== '' ? json_decode($json, true) : null;
        return is_array($c) && !empty($c['client_email']) && !empty($c['private_key']) ? $c : null;
    }

    public static function configurado(): bool
    {
        return self::chave() !== null;
    }

    /** Os destinos com o portal aberto ao público (o que vai aos buscadores). */
    public static function destinosAbertos(): array
    {
        return Database::conexao()->query("SELECT id, nome_fantasia, slug, dominio_proprio FROM empresas
                                           WHERE ativo = 1 AND (modo_operacao = 'ativo' OR (modo_operacao = 'avaliacao' AND avaliacao_ate >= CURDATE()))")->fetchAll();
    }

    /** O endereço do portal de um destino, como o Google vê. */
    public static function prefixo(array $empresa): string
    {
        $cfg = require __DIR__ . '/config.php';
        return 'https://' . ($empresa['dominio_proprio'] ?: $empresa['slug'] . '.' . ($cfg['app']['dominio_portal'] ?? 'inventour.com.br')) . '/';
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    /** Token de acesso (1 hora), por JWT assinado com a chave privada. */
    private static function token(): string
    {
        return self::tokenPara(self::ESCOPO);
    }

    /** Token da mesma conta de serviço para outro escopo (Analytics). */
    public static function tokenPara(string $escopo): string
    {
        if (isset(self::$tokens[$escopo]) && self::$tokens[$escopo]['ate'] > time() + 60) {
            return self::$tokens[$escopo]['valor'];
        }
        $c = self::chave();
        if (!$c) {
            throw new RuntimeException('Falta a chave da conta de serviço do Google.');
        }
        $agora = time();
        $cab = self::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $corpo = self::b64(json_encode(['iss' => $c['client_email'], 'scope' => $escopo, 'aud' => 'https://oauth2.googleapis.com/token',
                                        'iat' => $agora, 'exp' => $agora + 3600]));
        if (!openssl_sign($cab . '.' . $corpo, $assinatura, (string) $c['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('A chave privada do Google não abriu.');
        }
        $r = self::http('POST', 'https://oauth2.googleapis.com/token', http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $cab . '.' . $corpo . '.' . self::b64($assinatura),
        ]), ['Content-Type: application/x-www-form-urlencoded']);
        if (empty($r['access_token'])) {
            throw new RuntimeException('O Google recusou a conta de serviço: ' . ($r['error_description'] ?? $r['error'] ?? 'sem resposta'));
        }
        self::$tokens[$escopo] = ['valor' => (string) $r['access_token'], 'ate' => $agora + (int) ($r['expires_in'] ?? 3600)];
        return self::$tokens[$escopo]['valor'];
    }

    public static function http(string $metodo, string $url, ?string $corpo = null, array $cabecalhos = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $cabecalhos),
            CURLOPT_USERAGENT => 'InvenTour (app.inventour.com.br)',
        ]);
        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo);
        }
        $resposta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $falha = curl_error($ch);
        curl_close($ch);
        if ($resposta === false) {
            throw new RuntimeException('Sem resposta do Google: ' . $falha);
        }
        $dados = json_decode((string) $resposta, true) ?: [];
        if ($codigo >= 400 && !str_contains($url, 'oauth2')) {
            throw new RuntimeException('O Google respondeu ' . $codigo . ': ' . ($dados['error']['message'] ?? substr((string) $resposta, 0, 200)));
        }
        return $dados;
    }

    private static function api(string $metodo, string $caminho, ?array $corpo = null): array
    {
        return self::http($metodo, 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode(self::PROPRIEDADE) . $caminho,
            $corpo !== null ? json_encode($corpo) : null, ['Authorization: Bearer ' . self::token(), 'Content-Type: application/json']);
    }

    /** Uma consulta de desempenho, filtrada pelo portal do destino. */
    public static function consultar(string $prefixo, string $de, string $ate, array $dimensoes, int $linhas = 1000): array
    {
        $r = self::api('POST', '/searchAnalytics/query', [
            'startDate' => $de, 'endDate' => $ate, 'dimensions' => $dimensoes, 'rowLimit' => $linhas, 'dataState' => 'all',
            'dimensionFilterGroups' => [['filters' => [['dimension' => 'page', 'operator' => 'contains', 'expression' => $prefixo]]]],
        ]);
        return $r['rows'] ?? [];
    }

    /** Envia (ou reenvia) o sitemap de um portal. */
    public static function enviarSitemap(string $prefixo): void
    {
        self::api('PUT', '/sitemaps/' . rawurlencode($prefixo . 'sitemap.xml'));
    }

    /** Tira o sitemap de um portal (destino apagado). */
    public static function removerSitemap(string $prefixo): void
    {
        self::api('DELETE', '/sitemaps/' . rawurlencode($prefixo . 'sitemap.xml'));
    }

    /** Teste da tela: a lista de sitemaps que a conta enxerga. */
    public static function sitemaps(): array
    {
        return self::api('GET', '/sitemaps')['sitemap'] ?? [];
    }

    /**
     * Puxa os últimos 35 dias de cada destino ativo: o diário (que o Google
     * ainda ajusta nos dias recentes) e os termos e páginas de 28 dias.
     *
     * @return list<string> uma linha por destino, para o log da fila
     */
    public static function puxar(): array
    {
        $pdo = Database::conexao();
        $ate = date('Y-m-d', strtotime('-1 day'));
        $de = date('Y-m-d', strtotime('-35 days'));
        $de28 = date('Y-m-d', strtotime('-28 days'));
        $dia = $pdo->prepare('INSERT INTO busca_diaria (empresa_id, dia, cliques, impressoes, posicao) VALUES (?, ?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE cliques = VALUES(cliques), impressoes = VALUES(impressoes), posicao = VALUES(posicao)');
        $apaga = $pdo->prepare('DELETE FROM busca_termos WHERE empresa_id = ?');
        $termo = $pdo->prepare('INSERT INTO busca_termos (empresa_id, tipo, chave, cliques, impressoes, posicao) VALUES (?, ?, ?, ?, ?, ?)');
        $log = [];
        // Destino novo entra no Google sozinho: o sitemap que a propriedade
        // ainda não tem é enviado aqui.
        $enviados = array_column(self::sitemaps(), 'path');
        foreach (self::destinosAbertos() as $e) {
            $prefixo = self::prefixo($e);
            if (!in_array($prefixo . 'sitemap.xml', $enviados, true)) {
                self::enviarSitemap($prefixo);
                $log[] = $e['nome_fantasia'] . ': sitemap enviado';
            }
            $diario = self::consultar($prefixo, $de, $ate, ['date']);
            $termos = self::consultar($prefixo, $de28, $ate, ['query'], 50);
            $paginas = self::consultar($prefixo, $de28, $ate, ['page'], 50);
            $pdo->beginTransaction();
            foreach ($diario as $l) {
                $dia->execute([(int) $e['id'], $l['keys'][0], (int) $l['clicks'], (int) $l['impressions'], round((float) $l['position'], 2)]);
            }
            $apaga->execute([(int) $e['id']]);
            foreach ([['termo', $termos], ['pagina', $paginas]] as [$tipo, $linhas]) {
                foreach ($linhas as $l) {
                    $termo->execute([(int) $e['id'], $tipo, mb_substr((string) $l['keys'][0], 0, 500), (int) $l['clicks'], (int) $l['impressions'], round((float) $l['position'], 2)]);
                }
            }
            $pdo->commit();
            $log[] = $e['nome_fantasia'] . ': ' . count($diario) . ' dias, ' . count($termos) . ' termos';
        }
        return $log;
    }

    /** Resumo de 28 dias de um destino (ou de todos, com null) para o Início. */
    public static function resumo(?int $empresaId): array
    {
        $pdo = Database::conexao();
        $onde = $empresaId ? 'empresa_id = ' . (int) $empresaId : '1 = 1';
        $t = $pdo->query("SELECT COALESCE(SUM(cliques), 0), COALESCE(SUM(impressoes), 0), SUM(posicao * impressoes) / NULLIF(SUM(impressoes), 0), MAX(dia)
                          FROM busca_diaria WHERE {$onde} AND dia >= CURDATE() - INTERVAL 30 DAY")->fetch(PDO::FETCH_NUM);
        $termos = $pdo->query("SELECT chave, SUM(cliques) c, SUM(impressoes) i, MIN(posicao) p FROM busca_termos
                               WHERE {$onde} AND tipo = 'termo' GROUP BY chave ORDER BY c DESC, i DESC LIMIT 8")->fetchAll();
        $serie = $pdo->query("SELECT dia, SUM(cliques) c, SUM(impressoes) i FROM busca_diaria WHERE {$onde} AND dia >= CURDATE() - INTERVAL 30 DAY
                              GROUP BY dia ORDER BY dia")->fetchAll();
        return ['cliques' => (int) $t[0], 'impressoes' => (int) $t[1], 'posicao' => $t[2] !== null ? round((float) $t[2], 1) : null,
                'ultimo' => $t[3], 'termos' => $termos, 'serie' => $serie];
    }
}
