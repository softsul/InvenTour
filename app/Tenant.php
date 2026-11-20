<?php
/**
 * Resolve o destino do portal público pelo host da requisição.
 * Softsul Software
 *
 * patobranco.inventour.com.br e turismo.patobranco.pr.gov.br levam ao mesmo
 * destino. O portal não tem sessão: o destino vem sempre do host, e nada no
 * portal consulta o banco sem ele resolvido.
 *
 * Em escala nacional o portal recebe muito mais requisições que o painel.
 * A resolução fica em cache por host, em arquivo, por 60 segundos: uma
 * consulta por minuto por host, e não uma por página. Host desconhecido
 * também vai para o cache, para que varredura de subdomínio não vire carga
 * no banco.
 *
 * O limite de requisições por IP do portal (120 por minuto, app/Limite.php)
 * também fica aqui: toda página do portal resolve o destino antes de
 * qualquer outra coisa, então página nova do portal já nasce limitada sem
 * precisar lembrar de chamar o limite.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Limite.php';

final class Tenant
{
    private const VALIDADE_CACHE = 60;

    private static ?array $atual = null;
    private static bool $resolvido = false;

    /** Tira do cache os hosts do destino, para o que foi salvo valer na hora no portal. */
    public static function esquecerHosts(array $destino): void
    {
        $cfg = require __DIR__ . '/config.php';
        $hosts = [$destino['slug'] . '.' . $cfg['app']['dominio_portal'], (string) ($destino['dominio_proprio'] ?? '')];
        foreach (array_filter($hosts) as $host) {
            if (preg_match('/^[a-z0-9.-]+$/', $host)) {
                @unlink($cfg['caminhos']['cache'] . '/hosts/' . $host . '.json');
            }
        }
    }

    /** O destino do host atual (id, slug, nome_fantasia, cor_acento, logo, cidade, uf, dominio_proprio), ou null. */
    public static function atual(): ?array
    {
        if (!self::$resolvido) {
            // Conta antes de resolver o host: varredura de subdomínio também
            // passa pelo limite. A conexão é a do portal, que só tem escrita
            // em limites e nas métricas.
            Limite::usarConexao(Database::conexaoPortal());
            Limite::porIp('portal', 120, 60);
            self::$atual = self::resolver(self::host());
            self::$resolvido = true;
        }
        return self::$atual;
    }

    /** Destino resolvido ou página 404 neutra da plataforma. */
    public static function exigir(): array
    {
        $destino = self::atual();
        if ($destino === null) {
            http_response_code(404);
            header('Cache-Control: public, max-age=60');
            require __DIR__ . '/vistas/portal-inexistente.php';
            exit;
        }
        return $destino;
    }

    /**
     * Host normalizado: minúsculo, sem porta e sem ponto final. Só letras,
     * dígitos, hífen e ponto passam; qualquer outra coisa é tratada como
     * host desconhecido, sem chegar ao banco nem ao nome do arquivo de cache.
     */
    public static function host(): string
    {
        $h = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $h = rtrim((string) preg_replace('/:\d+$/', '', $h), '.');
        return preg_match('/^[a-z0-9.-]{1,253}$/', $h) ? $h : '';
    }

    private static function resolver(string $host): ?array
    {
        if ($host === '') {
            return null;
        }
        $cfg = require __DIR__ . '/config.php';
        $arquivo = $cfg['caminhos']['cache'] . '/hosts/' . $host . '.json';
        if (is_file($arquivo) && filemtime($arquivo) > time() - self::VALIDADE_CACHE) {
            $dado = json_decode((string) file_get_contents($arquivo), true);
            return is_array($dado) && $dado ? $dado : null;
        }

        $raiz = '.' . $cfg['app']['dominio_portal'];
        $slug = str_ends_with($host, $raiz) ? substr($host, 0, -strlen($raiz)) : null;
        $s = Database::conexaoPortal()->prepare(
            'SELECT id, slug, nome_fantasia, cor_acento, logo, logo_no_portal, cidade, uf, dominio_proprio, modo_operacao, avaliacao_ate
             FROM empresas
             WHERE ativo = 1 AND (slug = ? OR dominio_proprio = ?)
             LIMIT 1'
        );
        $s->execute([$slug ?? '', $host]);
        $destino = $s->fetch() ?: null;

        // Gravação atômica: arquivo temporário e rename, para duas
        // requisições simultâneas não lerem um JSON pela metade.
        @mkdir(dirname($arquivo), 02770, true);
        $tmp = $arquivo . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode($destino ?: [], JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $arquivo);
        }
        return $destino;
    }
}
