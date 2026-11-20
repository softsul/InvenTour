<?php
/**
 * Guarda segredo no banco sem deixá-lo legível ali.
 * Softsul Software
 *
 * Senha de SMTP não é senha de usuário: ela precisa ser recuperada para poder
 * ser usada, então hash não serve. O que dá para fazer é não deixá-la em texto
 * claro no banco, para um dump não entregar o acesso ao e-mail da empresa
 * junto com o resto.
 *
 * A chave fica no config.php, que não vai para o Git e é 640. Quem tem os dois,
 * banco e arquivo, tem o segredo de qualquer jeito; a proteção é contra ter
 * apenas um deles, que é o caso comum de um backup vazado.
 */
declare(strict_types=1);

final class Cripto
{
    private static ?string $chave = null;

    /** Chave de 32 bytes, criada na primeira vez e guardada no config. */
    private static function chave(): string
    {
        if (self::$chave !== null) {
            return self::$chave;
        }
        $cfg = require __DIR__ . '/config.php';
        $bruta = (string) ($cfg['app']['chave_secreta'] ?? '');
        if ($bruta === '') {
            throw new RuntimeException(
                'Falta app.chave_secreta no config.php. Gere uma com: '
                . 'php -r \'echo bin2hex(random_bytes(32));\''
            );
        }
        $chave = @hex2bin($bruta) ?: '';
        if (strlen($chave) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            // Aceita uma chave escrita à mão derivando dela, em vez de recusar:
            // a alternativa seria o sistema parar por causa de uma linha de
            // configuração digitada com um caractere a mais.
            $chave = hash('sha256', $bruta, true);
        }
        return self::$chave = $chave;
    }

    /** Devolve texto guardável, com o nonce junto. */
    public static function guardar(string $claro): string
    {
        if ($claro === '') {
            return '';
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($claro, $nonce, self::chave()));
    }

    /** Devolve o texto original, ou string vazia se o dado não abrir. */
    public static function abrir(?string $guardado): string
    {
        $bruto = base64_decode((string) $guardado, true);
        if ($bruto === false || strlen($bruto) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }
        $nonce = substr($bruto, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $corpo = substr($bruto, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $claro = sodium_crypto_secretbox_open($corpo, $nonce, self::chave());
        return $claro === false ? '' : $claro;
    }
}
