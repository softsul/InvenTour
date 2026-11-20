<?php
/**
 * Devoluções e reclamações do SES, recebidas pelo SNS (api/ses-avisos.php).
 * Softsul Software
 *
 * A Amazon pausa o envio da conta inteira se as devoluções passarem de uns
 * 5% ou as reclamações de 0,1%. Por isso endereço com devolução definitiva
 * ou com reclamação não recebe mais nada do InvenTour (Email::enviar
 * pergunta aqui) até alguém corrigir o cadastro e liberar.
 *
 * Toda mensagem do SNS tem a assinatura conferida contra o certificado da
 * própria Amazon antes de valer, e só o tópico configurado é aceito.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

final class SesAvisos
{
    /** O tópico aceito, do config.php (mail.topico_sns). */
    public static function topico(): string
    {
        return (string) ((require __DIR__ . '/config.php')['mail']['topico_sns'] ?? '');
    }

    /** Endereço bloqueado para envio? */
    public static function bloqueado(string $email): bool
    {
        $q = Database::conexao()->prepare(
            "SELECT 1 FROM email_ocorrencias WHERE email = ? AND liberado_em IS NULL AND (tipo = 'reclamacao' OR permanente = 1) LIMIT 1"
        );
        $q->execute([mb_strtolower(trim($email))]);
        return (bool) $q->fetchColumn();
    }

    /** O motivo do bloqueio, para o aviso na ficha; null se não está bloqueado. */
    public static function motivo(string $email): ?string
    {
        $q = Database::conexao()->prepare(
            "SELECT tipo, subtipo, criado_em FROM email_ocorrencias WHERE email = ? AND liberado_em IS NULL AND (tipo = 'reclamacao' OR permanente = 1)
             ORDER BY id DESC LIMIT 1"
        );
        $q->execute([mb_strtolower(trim($email))]);
        $o = $q->fetch();
        if (!$o) {
            return null;
        }
        return ($o['tipo'] === 'reclamacao' ? 'marcou um e-mail do InvenTour como spam' : 'o e-mail voltou: o endereço não existe ou recusa mensagens')
             . ' em ' . date('d/m/Y', strtotime((string) $o['criado_em']));
    }

    /** Libera o endereço (cadastro corrigido ou pessoa pediu para voltar a receber). */
    public static function liberar(string $email, int $usuarioId): void
    {
        Database::conexao()->prepare('UPDATE email_ocorrencias SET liberado_em = NOW(), liberado_por = ? WHERE email = ? AND liberado_em IS NULL')
            ->execute([$usuarioId, mb_strtolower(trim($email))]);
    }

    /** Totais de 30 dias para o Início da plataforma: [devoluções, reclamações, bloqueados agora]. */
    public static function resumo(): array
    {
        $l = Database::conexao()->query(
            "SELECT SUM(tipo = 'devolucao' AND criado_em >= NOW() - INTERVAL 30 DAY), SUM(tipo = 'reclamacao' AND criado_em >= NOW() - INTERVAL 30 DAY),
                    COUNT(DISTINCT CASE WHEN liberado_em IS NULL AND (tipo = 'reclamacao' OR permanente = 1) THEN email END)
             FROM email_ocorrencias"
        )->fetch(PDO::FETCH_NUM);
        return array_map('intval', $l ?: [0, 0, 0]);
    }

    /**
     * Confere a assinatura de uma mensagem do SNS (versões 1 e 2), com o
     * certificado baixado só de sns.<região>.amazonaws.com.
     */
    public static function assinaturaValida(array $m): bool
    {
        $cert = (string) ($m['SigningCertURL'] ?? '');
        if (!preg_match('#^https://sns\.[a-z0-9-]+\.amazonaws\.com/[^?]+\.pem$#', $cert)) {
            return false;
        }
        $campos = ($m['Type'] ?? '') === 'Notification'
            ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        $texto = '';
        foreach ($campos as $c) {
            if (isset($m[$c])) {
                $texto .= $c . "\n" . $m[$c] . "\n";
            }
        }
        $cache = sys_get_temp_dir() . '/inventour-sns-' . sha1($cert) . '.pem';
        $pem = is_file($cache) && filemtime($cache) > time() - 86400 ? file_get_contents($cache) : false;
        if (!$pem) {
            $ch = curl_init($cert);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
            $pem = curl_exec($ch);
            curl_close($ch);
            if (!$pem || !str_contains((string) $pem, 'BEGIN CERTIFICATE')) {
                return false;
            }
            @file_put_contents($cache, $pem);
        }
        $algo = ($m['SignatureVersion'] ?? '1') === '2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
        return openssl_verify($texto, (string) base64_decode((string) ($m['Signature'] ?? '')), (string) $pem, $algo) === 1;
    }

    /**
     * Grava os destinatários de uma notificação do SES (o Message do SNS).
     *
     * @return int quantos endereços entraram
     */
    public static function registrar(array $n): int
    {
        $tipo = (string) ($n['notificationType'] ?? $n['eventType'] ?? '');
        $msg = substr((string) ($n['mail']['messageId'] ?? ''), 0, 120);
        $ins = Database::conexao()->prepare(
            'INSERT INTO email_ocorrencias (email, tipo, permanente, subtipo, detalhe, mensagem_id, criado_em) VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $n_ = 0;
        if ($tipo === 'Bounce') {
            $b = (array) ($n['bounce'] ?? []);
            $perm = ($b['bounceType'] ?? '') === 'Permanent' ? 1 : 0;
            foreach ((array) ($b['bouncedRecipients'] ?? []) as $r) {
                $ins->execute([mb_strtolower((string) ($r['emailAddress'] ?? '')), 'devolucao', $perm, substr((string) ($b['bounceSubType'] ?? ''), 0, 60),
                               substr((string) ($r['diagnosticCode'] ?? ''), 0, 500) ?: null, $msg ?: null]);
                $n_++;
            }
        } elseif ($tipo === 'Complaint') {
            $c = (array) ($n['complaint'] ?? []);
            foreach ((array) ($c['complainedRecipients'] ?? []) as $r) {
                $ins->execute([mb_strtolower((string) ($r['emailAddress'] ?? '')), 'reclamacao', 1, substr((string) ($c['complaintFeedbackType'] ?? ''), 0, 60) ?: null,
                               null, $msg ?: null]);
                $n_++;
            }
        }
        return $n_;
    }
}
