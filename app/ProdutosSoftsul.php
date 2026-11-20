<?php
/**
 * Versão do InvenTour e lista dos produtos da Softsul, para o pé do menu.
 * Softsul Software
 *
 * A lista central mora no Orbit (https://erp.softsul.com/produtos-softsul.json).
 * O painel tem CSP só com 'self', então o navegador não busca lá: o servidor
 * busca, guarda por um minuto e entrega em /produtos-softsul. Os ícones vêm
 * junto como data: (img-src aceita data:), guardados por um dia.
 * Padrão: https://erp.softsul.com/assets/marca/padrao-versao-e-produtos.txt
 */
declare(strict_types=1);

final class ProdutosSoftsul
{
    private const CENTRAL   = 'https://erp.softsul.com/produtos-softsul.json';
    private const PROPRIA   = __DIR__ . '/../public/versao.json';
    private const GUARDADO  = __DIR__ . '/../storage/cache/produtos-softsul.json';
    private const ICONES    = __DIR__ . '/../storage/cache/icones-produtos';
    private const VALIDADE  = 60;
    private const VALIDADE_ICONE = 86400;
    private const ICONE_MAX = 200000;

    /** A versão do próprio InvenTour, gerada pelo gancho do git. */
    public static function versaoPropria(): ?array
    {
        $d = json_decode((string) @file_get_contents(self::PROPRIA), true);
        return is_array($d) && is_string($d['versao'] ?? null) && preg_match('/^\d+\.\d{2}\.\d{3,}$/', $d['versao']) ? $d : null;
    }

    /** A lista pronta, com os ícones embutidos. Sem resposta do Orbit, fica a última guardada. */
    public static function lista(): array
    {
        $guardado = json_decode((string) @file_get_contents(self::GUARDADO), true);
        if (is_array($guardado) && time() - (int) ($guardado['buscado_em'] ?? 0) < self::VALIDADE) {
            return $guardado['produtos'] ?? [];
        }
        $central = json_decode((string) self::baixar(self::CENTRAL, 8), true);
        if (!is_array($central) || !is_array($central['produtos'] ?? null)) {
            return is_array($guardado) ? ($guardado['produtos'] ?? []) : [];
        }
        $produtos = [];
        foreach ($central['produtos'] as $p) {
            if (!is_array($p) || !is_string($p['chave'] ?? null)) {
                continue;
            }
            $produtos[] = [
                'chave'     => $p['chave'],
                'nome'      => (string) ($p['nome'] ?? $p['chave']),
                'versao'    => is_string($p['versao'] ?? null) ? $p['versao'] : null,
                'descricao' => (string) ($p['descricao'] ?? ''),
                'login'     => self::https($p['login'] ?? null),
                'icone'     => self::icone(self::https($p['icone'] ?? null)),
                'interno'   => !empty($p['interno']),
                'grupo'     => (int) ($p['grupo'] ?? 0),
            ];
        }
        @file_put_contents(self::GUARDADO, json_encode(['buscado_em' => time(), 'produtos' => $produtos], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $produtos;
    }

    private static function https(mixed $u): ?string
    {
        return is_string($u) && preg_match('#^https://[^\s"\'<>]+$#', $u) ? $u : null;
    }

    /** O ícone como data:, ou null (vira o quadrado neutro na tela). */
    private static function icone(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        if (!is_dir(self::ICONES)) {
            @mkdir(self::ICONES, 02770, true);
        }
        $arq = self::ICONES . '/' . sha1($url) . '.txt';
        if (is_file($arq) && time() - (int) filemtime($arq) < self::VALIDADE_ICONE) {
            $d = (string) file_get_contents($arq);
            return $d !== '' ? $d : null;
        }
        $tipo = '';
        $corpo = self::baixar($url, 5, $tipo);
        $tipo = strtolower(trim(explode(';', $tipo)[0]));
        $aceitos = ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'];
        $dado = ($corpo !== null && strlen($corpo) <= self::ICONE_MAX && in_array($tipo, $aceitos, true))
            ? 'data:' . $tipo . ';base64,' . base64_encode($corpo) : '';
        @file_put_contents($arq, $dado, LOCK_EX);
        return $dado !== '' ? $dado : null;
    }

    private static function baixar(string $url, int $tempo, string &$tipo = ''): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $tempo, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'InvenTour (app.inventour.com.br)',
        ]);
        $r = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $tipo = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        return ($r === false || $codigo !== 200) ? null : (string) $r;
    }
}
