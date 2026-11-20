<?php
/**
 * Carregador da biblioteca lbuchs/WebAuthn, sem Composer.
 * Softsul Software
 *
 * A mesma cópia que roda no Orbit e no FrameFlow. É a camada criptográfica
 * da passkey (CBOR, COSE, assinaturas) e não foi reescrita à mão de propósito.
 * Biblioteca: https://github.com/lbuchs/WebAuthn (MIT).
 */
spl_autoload_register(function ($classe) {
    $prefixo = 'lbuchs\\WebAuthn\\';
    if (strncmp($classe, $prefixo, strlen($prefixo)) !== 0) {
        return;
    }
    $caminho = __DIR__ . '/webauthn/' . str_replace('\\', '/', substr($classe, strlen($prefixo))) . '.php';
    if (is_file($caminho)) {
        require $caminho;
    }
});
