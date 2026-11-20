<?php
/**
 * TOTP (RFC 6238), compatível com Google Authenticator, Authy e similares.
 * Implementação própria, sem dependência externa: só hash_hmac do PHP.
 * Softsul Software
 */
declare(strict_types=1);

class TOTP
{
    private const ALFABETO_BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Segredo aleatório novo, em base32 (20 bytes = 160 bits, o recomendado pela RFC 4226). */
    public static function gerarSegredo(): string
    {
        return self::base32Codificar(random_bytes(20));
    }

    /** URI otpauth:// que o aplicativo autenticador lê. */
    public static function uri(string $segredo, string $emailUsuario, string $emissor = 'InvenTour'): string
    {
        $label  = rawurlencode($emissor) . ':' . rawurlencode($emailUsuario);
        $params = http_build_query([
            'secret'    => $segredo,
            'issuer'    => $emissor,
            'algorithm' => 'SHA1',
            'digits'    => 6,
            'period'    => 30,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Segredo quebrado em grupos de 4, que é como se digita à mão no
     * aplicativo quando a câmera não ajuda.
     */
    public static function segredoLegivel(string $segredo): string
    {
        return trim(chunk_split($segredo, 4, ' '));
    }

    /**
     * Confere um código de 6 dígitos.
     *
     * A janela tolera um período de 30s para cada lado, porque o relógio do
     * celular raramente bate exatamente com o do servidor.
     */
    public static function verificar(string $segredo, string $codigo, int $janela = 1): bool
    {
        return self::periodoDoCodigo($segredo, $codigo, $janela) !== null;
    }

    /**
     * Período de 30 s a que o código pertence, ou null se não confere.
     *
     * Quem guarda o último período aceito consegue recusar o mesmo código uma
     * segunda vez (Auth::conferirCodigo), que é o que impede reaproveitar um
     * código visto por cima do ombro ou capturado numa página falsa.
     */
    public static function periodoDoCodigo(string $segredo, string $codigo, int $janela = 1): ?int
    {
        $codigo = preg_replace('/\D/', '', $codigo);
        if (strlen($codigo) !== 6 || $segredo === '') {
            return null;
        }
        $periodoAtual = (int) floor(time() / 30);
        for ($i = -$janela; $i <= $janela; $i++) {
            // hash_equals para não vazar o código por tempo de comparação.
            if (hash_equals(self::gerarCodigo($segredo, $periodoAtual + $i), $codigo)) {
                return $periodoAtual + $i;
            }
        }
        return null;
    }

    private static function gerarCodigo(string $segredoBase32, int $periodo): string
    {
        $chave    = self::base32Decodificar($segredoBase32);
        $contador = pack('N*', 0) . pack('N*', $periodo); // 8 bytes, big-endian
        $hash     = hash_hmac('sha1', $contador, $chave, true);
        $deslocamento = ord($hash[19]) & 0x0F;
        $trecho = ((ord($hash[$deslocamento]) & 0x7F) << 24)
                | ((ord($hash[$deslocamento + 1]) & 0xFF) << 16)
                | ((ord($hash[$deslocamento + 2]) & 0xFF) << 8)
                |  (ord($hash[$deslocamento + 3]) & 0xFF);
        return str_pad((string) ($trecho % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Codificar(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 5) as $bloco) {
            $saida .= self::ALFABETO_BASE32[bindec(str_pad($bloco, 5, '0', STR_PAD_RIGHT))];
        }
        return $saida;
    }

    private static function base32Decodificar(string $texto): string
    {
        $texto = strtoupper(rtrim($texto, '='));
        $bits  = '';
        for ($i = 0; $i < strlen($texto); $i++) {
            $pos = strpos(self::ALFABETO_BASE32, $texto[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 8) as $bloco) {
            if (strlen($bloco) === 8) {
                $saida .= chr(bindec($bloco));
            }
        }
        return $saida;
    }
}

// NOTA DELIBERADA: aqui não existe um urlQrCode() que mande o segredo para
// uma API pública de QR code: isso entregaria a semente do segundo fator a
// um terceiro, que passaria a gerar códigos válidos indefinidamente. O QR
// é desenhado localmente por QrCode::svg(), e segredoLegivel() cobre a
// digitação manual quando a câmera não ajuda.
