<?php
/**
 * reCAPTCHA v2 (caixa "Não sou um robô") do Google, para os formulários
 * públicos: contato do portal e do site.
 * Softsul Software
 *
 * Mesma conferência do Matter: a resposta do navegador vai ao siteverify do
 * Google com a chave secreta, e só "success" com o nosso domínio vale.
 * Sem as chaves no config.php, o formulário não envia: contato público sem
 * reCAPTCHA vira canal de spam (decisão do Luiz, 22/10/2026).
 */
declare(strict_types=1);

final class Recaptcha
{
    private static function config(): array
    {
        return (require __DIR__ . '/config.php')['recaptcha'] ?? [];
    }

    public static function chaveDoSite(): string
    {
        return (string) (self::config()['site_key'] ?? '');
    }

    public static function configurado(): bool
    {
        $c = self::config();
        return !empty($c['site_key']) && !empty($c['secret_key']);
    }

    /** A resposta do navegador confere com o Google? Nunca lança. */
    public static function conferir(string $resposta, ?string $ip = null): bool
    {
        if (!self::configurado() || $resposta === '' || strlen($resposta) > 4000) {
            return false;
        }
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(array_filter([
                'secret'   => (string) self::config()['secret_key'],
                'response' => $resposta,
                'remoteip' => $ip,
            ])),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $corpo = curl_exec($ch);
        curl_close($ch);
        $r = is_string($corpo) ? json_decode($corpo, true) : null;
        if (!is_array($r) || empty($r['success'])) {
            return false;
        }
        // A chave vale para inventour.com.br e subdomínios e para o site do
        // InvenTour; outro host é resposta copiada de outro site.
        $host = strtolower((string) ($r['hostname'] ?? ''));
        return $host === 'inventour.com.br' || str_ends_with($host, '.inventour.com.br') || in_array($host, ['inventour.com.br', 'www.inventour.com.br'], true);
    }
}
