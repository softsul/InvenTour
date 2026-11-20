<?php
/**
 * Entrar pela digital do aparelho.
 * Softsul Software
 *
 * Público de propósito: é o próprio mecanismo de entrada. Não recebe e-mail,
 * porque o aparelho já sabe quais contas tem neste domínio. O desafio fica na
 * sessão entre os dois passos, e é gasto no segundo.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/funcoes.php';
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Passkey.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): Público: por IP, dois passos por entrada.
Limite::porIp('passkey-entrar', 20, 60);
header('Cache-Control: no-store');
Auth::iniciarSessao();

try {
    $passo = (string) ($_GET['passo'] ?? '');
    if ($passo === 'iniciar') {
        $motor = Passkey::motor();
        $args = $motor->getGetArgs([], 60, true, true, true, true, true, true);
        $_SESSION['passkey_desafio'] = $motor->getChallenge();
        echo json_encode(['ok' => true] + (array) $args);
        exit;
    }
    if ($passo === 'finalizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $desafio = $_SESSION['passkey_desafio'] ?? null;
        unset($_SESSION['passkey_desafio']);
        $id = (string) ($_POST['id'] ?? '');
        if ($id === '' || !$desafio) {
            throw new RuntimeException('A página ficou aberta tempo demais. Recarregue e tente de novo.');
        }
        $credencial = Passkey::achar((string) base64_decode($id));
        if (!$credencial) {
            Auditoria::falhaDeAcesso('passkey_negada');
            throw new RuntimeException('Este aparelho não está cadastrado. Entre com a senha e cadastre-o de novo.');
        }
        if (!$credencial['ativo'] || !Auth::temAlgumAcesso((int) $credencial['uid'], (bool) $credencial['softsul'])) {
            Auditoria::registrar('login_negado', 'usuarios', (int) $credencial['uid'], 'biometria de conta sem acesso');
            throw new RuntimeException('Não foi possível entrar com este aparelho.');
        }
        $motor = Passkey::motor();
        // processGet lança quando a assinatura não confere.
        $motor->processGet(
            (string) base64_decode((string) ($_POST['clientDataJSON'] ?? '')),
            (string) base64_decode((string) ($_POST['authenticatorData'] ?? '')),
            (string) base64_decode((string) ($_POST['signature'] ?? '')),
            (string) $credencial['chave_publica'],
            $desafio,
            (int) $credencial['contador'],
            true,
            true
        );
        Passkey::marcarUso((int) $credencial['id'], (int) $motor->getSignatureCounter());
        Auth::entrarPorBiometria((int) $credencial['uid'], (string) $credencial['apelido']);
        // Volta para onde a pessoa estava quando a sessão venceu (o destino da tela de login).
        echo json_encode(['ok' => true, 'para' => destinoSeguro($_POST['destino'] ?? null)]);
        exit;
    }
    throw new RuntimeException('passo desconhecido');
} catch (Throwable $e) {
    error_log('passkey entrar: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['ok' => false, 'recado' => $e instanceof RuntimeException ? $e->getMessage() : 'Não deu para entrar pela digital.'], JSON_UNESCAPED_UNICODE);
}
