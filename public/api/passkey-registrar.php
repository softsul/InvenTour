<?php
/**
 * Cadastrar o aparelho que vai entrar por biometria.
 * Softsul Software
 *
 * Exige sessão aberta e CSRF: a digital passa a valer porque foi cadastrada
 * por quem já tinha entrado com senha e código.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/funcoes.php';
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Passkey.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): Cadastro de aparelho, dois passos por cadastro.
Limite::porUsuario('passkey-registrar', 20, 60);
header('Cache-Control: no-store');
$eu = Auth::usuarioOuNulo();

try {
    if (!$eu) {
        http_response_code(401);
        throw new RuntimeException('A sessão terminou. Entre de novo.');
    }
    $passo = (string) ($_GET['passo'] ?? '');
    if ($passo === 'iniciar') {
        $motor = Passkey::motor();
        $args = $motor->getCreateArgs(
            hash('sha256', $eu['id'] . '|' . $eu['email'], true),
            (string) $eu['email'],
            (string) $eu['nome'],
            60,
            true,
            true,
            null,
            Passkey::idsDoUsuario((int) $eu['id'])
        );
        $_SESSION['passkey_desafio'] = $motor->getChallenge();
        echo json_encode(['ok' => true] + (array) $args);
        exit;
    }
    if ($passo === 'finalizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $desafio = $_SESSION['passkey_desafio'] ?? null;
        unset($_SESSION['passkey_desafio']);
        if (!$desafio || !Auth::conferirCsrf($_POST['csrf'] ?? null)) {
            throw new RuntimeException('A página ficou aberta tempo demais. Recarregue e tente de novo.');
        }
        $dados = Passkey::motor()->processCreate(
            (string) base64_decode((string) ($_POST['clientDataJSON'] ?? '')),
            (string) base64_decode((string) ($_POST['attestationObject'] ?? '')),
            $desafio,
            true,
            true,
            false
        );
        Passkey::guardar((int) $eu['id'], $dados->credentialId, $dados->credentialPublicKey,
            (string) ($_POST['apelido'] ?? ''),
            property_exists($dados, 'isBackupEligible') ? $dados->isBackupEligible : null);
        // A assinatura da lista NOVA volta junto, para o navegador marcar este
        // aparelho como cadastrado sem depender de uma segunda chamada.
        echo json_encode(['ok' => true, 'assinatura' => Passkey::assinatura((int) $eu['id'])]);
        exit;
    }
    throw new RuntimeException('passo desconhecido');
} catch (Throwable $e) {
    error_log('passkey registrar: ' . $e->getMessage());
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(['ok' => false, 'recado' => $e instanceof RuntimeException ? $e->getMessage() : 'Não deu para cadastrar este aparelho.'], JSON_UNESCAPED_UNICODE);
}
