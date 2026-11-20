<?php
/**
 * Link de uso único para definir a senha: convite de usuário novo,
 * redefinição pedida pelo responsável e "esqueci a senha".
 * Softsul Software
 *
 * Só o hash do token vai para o banco. Quem cria o usuário nunca escolhe a
 * senha dele: é assim que senha acaba viajando por WhatsApp.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/Email.php';

final class Convite
{
    /** Validade por origem, em horas. */
    private const VALIDADE = ['convite' => 168, 'redefinir' => 72, 'esqueci' => 1];

    /** Cria o link e devolve o endereço completo. Links anteriores da pessoa deixam de valer. */
    public static function criar(int $usuarioId, string $origem, ?int $criadoPor): string
    {
        $pdo = Database::conexao();
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE convites SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL')
            ->execute([$usuarioId]);
        $pdo->prepare(
            'INSERT INTO convites (usuario_id, token_hash, origem, expira_em, criado_por, criado_em)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?, NOW())'
        )->execute([$usuarioId, hash('sha256', $token), $origem, self::VALIDADE[$origem], $criadoPor]);
        $cfg = require __DIR__ . '/config.php';
        return rtrim($cfg['app']['url'], '/') . '/convite?t=' . $token;
    }

    /** O convite ainda válido para este token, com os dados da pessoa, ou null. */
    public static function achar(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $q = Database::conexao()->prepare(
            'SELECT c.id, c.origem, u.id AS usuario_id, u.nome, u.email
             FROM convites c JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.token_hash = ? AND c.usado_em IS NULL AND c.expira_em > NOW() AND u.ativo = 1'
        );
        $q->execute([hash('sha256', $token)]);
        return $q->fetch() ?: null;
    }

    /** Gasta o convite. A gravação condicional impede dois usos simultâneos. */
    public static function usar(int $conviteId): bool
    {
        $q = Database::conexao()->prepare('UPDATE convites SET usado_em = NOW() WHERE id = ? AND usado_em IS NULL');
        $q->execute([$conviteId]);
        return $q->rowCount() === 1;
    }

    /** Manda o link por e-mail. Devolve false quando o SMTP não está configurado ou falhou. */
    public static function enviar(string $email, string $nome, string $link, string $origem): bool
    {
        if (!Email::configurado()) {
            return false;
        }
        $primeiro = explode(' ', trim($nome))[0];
        [$assunto, $texto, $botao] = match ($origem) {
            'esqueci' => ['Redefinir a sua senha do InvenTour',
                "Olá {$primeiro},\n\nRecebemos um pedido para redefinir a sua senha. O link vale por 1 hora e serve uma vez só. "
                . "Até lá, a senha atual continua valendo.\n\nSe não foi você quem pediu, ignore este e-mail: nada muda na sua conta.",
                'Escolher a senha nova'],
            'redefinir' => ['Defina uma senha nova no InvenTour',
                "Olá {$primeiro},\n\nO gestor do seu destino pediu que você defina uma senha nova. O link vale por 3 dias e serve uma vez só.",
                'Definir a senha'],
            default => ['Seu acesso ao InvenTour',
                "Olá {$primeiro},\n\nVocê recebeu acesso ao InvenTour. Use o botão abaixo para escolher a sua senha. "
                . 'O link vale por 7 dias e serve uma vez só.',
                'Escolher a minha senha'],
        };
        try {
            Email::enviar($email, $assunto, Email::molde($assunto, Email::paragrafos($texto), ['texto' => $botao, 'url' => $link]), $nome);
            return true;
        } catch (Throwable $e) {
            error_log('convite: ' . $e->getMessage());
            return false;
        }
    }
}
