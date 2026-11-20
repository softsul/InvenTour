<?php
/**
 * Entrar com o certificado digital A1 instalado no computador.
 * Softsul Software
 *
 * O navegador só mostra a lista de certificados quando o servidor pede o
 * certificado no início da conexão, e isso acontece num endereço próprio do
 * Orbit, certificado.softsul.com. Ele confere que o computador tem a chave
 * privada e devolve aqui um passe assinado (HMAC-SHA256 com o segredo do
 * InvenTour), válido por 2 minutos e preso à sessão pelo "estado".
 *
 * A cadeia da ICP-Brasil não é conferida, de propósito: só entra o
 * certificado que a própria pessoa vinculou à conta dela, estando logada.
 * Como no Orbit, o certificado vale pelos dois fatores.
 *
 * Contrato: /home/www/erp.softsul.com/docs/certificado-digital-outros-sistemas.md
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';

final class Certificado
{
    private const ENDERECO = 'https://certificado.softsul.com/certificado';
    private const SISTEMA  = 'inventour';
    private const VALIDADE_ESTADO_SEG = 300;

    private static function cfg(): array
    {
        return (require __DIR__ . '/config.php')['certificado'] ?? [];
    }

    private static function segredo(): string
    {
        $s = (string) (self::cfg()['segredo'] ?? '');
        return preg_match('/^[0-9a-f]{64}$/', $s) ? $s : '';
    }

    /** O botão só aparece depois de o Orbit cadastrar o InvenTour. */
    public static function ligado(): bool
    {
        return !empty(self::cfg()['ligado']) && self::segredo() !== '';
    }

    /**
     * O endereço da ida. O estado é novo a cada clique e fica na sessão: é ele
     * que impede alguém de fazer a vítima entrar na conta errada.
     */
    public static function ida(string $acao): string
    {
        $estado = bin2hex(random_bytes(32));
        $_SESSION['certificado_estado'] = ['valor' => $estado, 'acao' => $acao, 'em' => time()];
        return self::ENDERECO . '?' . http_build_query(['sistema' => self::SISTEMA, 'acao' => $acao, 'estado' => $estado]);
    }

    /**
     * Confere o passe que voltou, na ordem do contrato, e devolve o conteúdo
     * ou null. O estado sai da sessão na hora, valendo ou não.
     */
    public static function lerPasse(string $passe): ?array
    {
        $estado = $_SESSION['certificado_estado'] ?? null;
        unset($_SESSION['certificado_estado']);
        $segredo = self::segredo();
        $partes = explode('.', $passe);
        if ($segredo === '' || !$estado || count($partes) !== 2
            || time() - (int) $estado['em'] > self::VALIDADE_ESTADO_SEG) {
            return null;
        }
        [$conteudo, $assinatura] = $partes;
        $b64 = fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $esperada = $b64(hash_hmac('sha256', $conteudo, (string) hex2bin($segredo), true));
        if (!hash_equals($esperada, $assinatura)) {
            return null;
        }
        $p = json_decode((string) base64_decode(strtr($conteudo, '-_', '+/')), true);
        if (!is_array($p) || ($p['v'] ?? 0) !== 1 || ($p['sistema'] ?? '') !== self::SISTEMA
            || ($p['acao'] ?? '') !== $estado['acao']
            || (int) ($p['expira_em'] ?? 0) < time() || (int) ($p['valido_ate'] ?? 0) < time()
            || !hash_equals((string) $estado['valor'], (string) ($p['estado'] ?? ''))
            || !preg_match('/^[0-9a-f]{64}$/', (string) ($p['impressao'] ?? ''))
            || !preg_match('/^[0-9a-f]{32}$/', (string) ($p['id'] ?? ''))) {
            return null;
        }
        // Cada passe vale uma vez: a chave primária recusa o segundo uso.
        $pdo = Database::conexao();
        $pdo->exec('DELETE FROM certificado_passes_usados WHERE usado_em < NOW() - INTERVAL 1 DAY');
        try {
            $pdo->prepare('INSERT INTO certificado_passes_usados (id, usado_em) VALUES (?, NOW())')->execute([$p['id']]);
        } catch (PDOException $e) {
            return null;
        }
        return $p;
    }

    /** O vínculo ativo deste certificado, com o dono, ou null. */
    public static function dono(string $impressao): ?array
    {
        $q = Database::conexao()->prepare(
            'SELECT c.*, u.softsul FROM usuario_certificados c
             JOIN usuarios u ON u.id = c.usuario_id AND u.ativo = 1
             WHERE c.impressao = ?'
        );
        $q->execute([$impressao]);
        return $q->fetch() ?: null;
    }

    /** Devolve '' se vinculou, ou a mensagem do motivo. */
    public static function vincular(int $usuarioId, array $p): string
    {
        $pdo = Database::conexao();
        $q = $pdo->prepare('SELECT usuario_id FROM usuario_certificados WHERE impressao = ?');
        $q->execute([$p['impressao']]);
        $de = $q->fetchColumn();
        if ($de !== false) {
            return (int) $de === $usuarioId
                ? 'Este certificado já está vinculado à sua conta.'
                : 'Este certificado já está vinculado a outra conta. Cada certificado entra numa conta só.';
        }
        $pdo->prepare(
            'INSERT INTO usuario_certificados (usuario_id, impressao, titular, emissor, valido_ate, criado_em)
             VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), NOW())'
        )->execute([$usuarioId, $p['impressao'], mb_substr((string) $p['titular'], 0, 190),
                    mb_substr((string) ($p['emissor'] ?? ''), 0, 190) ?: null, (int) $p['valido_ate']]);
        Auditoria::registrar('criou', 'usuario_certificados', $usuarioId, 'vinculou o certificado digital de ' . $p['titular']);
        return '';
    }

    public static function doUsuario(int $usuarioId): array
    {
        $q = Database::conexao()->prepare('SELECT * FROM usuario_certificados WHERE usuario_id = ? ORDER BY criado_em');
        $q->execute([$usuarioId]);
        return $q->fetchAll();
    }

    public static function marcarUso(int $id): void
    {
        Database::conexao()->prepare('UPDATE usuario_certificados SET ultimo_uso_em = NOW() WHERE id = ?')->execute([$id]);
    }

    public static function remover(int $usuarioId, int $id): bool
    {
        $q = Database::conexao()->prepare('SELECT titular FROM usuario_certificados WHERE id = ? AND usuario_id = ?');
        $q->execute([$id, $usuarioId]);
        $titular = $q->fetchColumn();
        if ($titular === false) {
            return false;
        }
        Database::conexao()->prepare('DELETE FROM usuario_certificados WHERE id = ? AND usuario_id = ?')->execute([$id, $usuarioId]);
        Auditoria::registrar('excluiu', 'usuario_certificados', $usuarioId, 'desvinculou o certificado digital de ' . $titular);
        return true;
    }

    public static function removerTodos(int $usuarioId): void
    {
        Database::conexao()->prepare('DELETE FROM usuario_certificados WHERE usuario_id = ?')->execute([$usuarioId]);
    }
}
