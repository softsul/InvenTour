<?php
/**
 * Recebe do SNS as devoluções e reclamações do SES (SesAvisos).
 * Softsul Software
 *
 * Sem login: quem chama é a Amazon. Vale só mensagem com assinatura
 * conferida e do tópico configurado em mail.topico_sns. A confirmação da
 * assinatura do tópico é automática (visita ao SubscribeURL, que também
 * precisa ser da Amazon).
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/SesAvisos.php';

header('Content-Type: text/plain; charset=utf-8');
$m = json_decode((string) file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($m)) {
    http_response_code(400);
    exit("nada\n");
}
$topico = SesAvisos::topico();
if ($topico === '' || ($m['TopicArn'] ?? '') !== $topico || !SesAvisos::assinaturaValida($m)) {
    error_log('ses-avisos: mensagem recusada do tópico ' . ($m['TopicArn'] ?? '?'));
    http_response_code(403);
    exit("recusado\n");
}
$tipo = (string) ($m['Type'] ?? '');
if ($tipo === 'SubscriptionConfirmation') {
    $url = (string) ($m['SubscribeURL'] ?? '');
    if (!preg_match('#^https://sns\.[a-z0-9-]+\.amazonaws\.com/\?Action=ConfirmSubscription#', $url)) {
        http_response_code(403);
        exit("endereço de confirmação inesperado\n");
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    $ok = curl_exec($ch) !== false && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
    curl_close($ch);
    exit($ok ? "confirmado\n" : "falhou\n");
}
if ($tipo === 'Notification') {
    $n = json_decode((string) ($m['Message'] ?? ''), true);
    echo is_array($n) ? SesAvisos::registrar($n) . " registrados\n" : "mensagem sem JSON\n";
    exit;
}
echo "ignorado\n";
