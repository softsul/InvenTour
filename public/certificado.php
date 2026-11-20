<?php
/**
 * Ida para certificado.softsul.com, onde o navegador mostra os certificados.
 * Softsul Software
 *
 *   /certificado?acao=entrar     da tela de login
 *   /certificado?acao=vincular   de Meus dados, com sessão aberta
 *
 * A volta chega em /certificado-retorno. Sem o Orbit ter cadastrado o
 * InvenTour (config certificado.ligado), a tela manda de volta para o login.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Certificado.php';

$acao = (string) ($_GET['acao'] ?? '');
if (!Certificado::ligado() || !in_array($acao, ['entrar', 'vincular'], true)) {
    redirecionar('/login');
}
if ($acao === 'vincular') {
    Auth::exigirLogin();
} elseif (Auth::usuarioOuNulo()) {
    redirecionar(destinoSeguro($_GET['destino'] ?? null));
} else {
    // Na volta do certificado, vai para onde a pessoa estava quando a sessão venceu.
    $_SESSION['destino_login'] = destinoSeguro($_GET['destino'] ?? null);
}
redirecionar(Certificado::ida($acao));
