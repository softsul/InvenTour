<?php
/**
 * Entrar pela digital ou pelo rosto do aparelho (WebAuthn, passkey).
 * Softsul Software
 *
 * O aparelho guarda uma chave privada que nunca sai dele; o servidor guarda a
 * pública, e entrar é assinar um desafio. Vale por senha MAIS segundo fator,
 * porque a assinatura só acontece depois de o aparelho conferir a pessoa.
 * Cadastrar exige sessão aberta com senha e código: a digital vale porque foi
 * cadastrada por quem já tinha provado quem era.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/vendor/webauthn-autoload.php';

final class Passkey
{
    /**
     * O rpId é o host de config app.url: é ele que prende a credencial a
     * este domínio. Vem do config, e não do HTTP_HOST, para um cabeçalho
     * forjado não mudar o domínio da credencial; o nome é o do produto.
     */
    public static function motor(): \lbuchs\WebAuthn\WebAuthn
    {
        $cfg  = require __DIR__ . '/config.php';
        $host = (string) (parse_url((string) ($cfg['app']['url'] ?? ''), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $motor = new \lbuchs\WebAuthn\WebAuthn((string) ($cfg['app']['nome'] ?? 'InvenTour'), (string) preg_replace('/:\d+$/', '', $host));
        // App Android InvenTour Gestão (mobile/gestao): lá dentro a digital chega com a
        // origem "android:apk-key-hash:<hash da chave de assinatura>", e não com
        // https://app.inventour.com.br. A ligação do app com o site está em
        // public/.well-known/assetlinks.json. Um hash por chave: a nossa e, quando o
        // app estiver no Google Play, a chave com que o Google assina a versão da loja.
        $motor->addAndroidKeyHashes(self::CHAVES_DO_APP);
        return $motor;
    }

    /** Hashes (SHA-256 em base64url) das chaves que assinam o app InvenTour Gestão. */
    private const CHAVES_DO_APP = [
        '3VdRrymJaX0loKyDIsN9buLuGA8u0kiw1bPqvtkINDg', // chave própria (/opt/inventour-gestao-android)
        'oCQmXnfSX1SfhxojidGZiQE0W0FWizqpbd_lgRiwBr0', // chave do Google Play (Assinatura de apps, A0:24:26:...:06:BD)
    ];

    public static function doUsuario(int $usuarioId): array
    {
        $q = Database::conexao()->prepare(
            'SELECT id, apelido, sincronizavel, ultimo_uso_em, ultimo_aparelho, criado_em
               FROM credenciais_webauthn WHERE usuario_id = ? ORDER BY criado_em'
        );
        $q->execute([$usuarioId]);
        return $q->fetchAll();
    }

    public static function idsDoUsuario(int $usuarioId): array
    {
        $q = Database::conexao()->prepare('SELECT credencial_id FROM credenciais_webauthn WHERE usuario_id = ?');
        $q->execute([$usuarioId]);
        return $q->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function guardar(int $usuarioId, string $credencialId, string $chavePublica,
                                   string $apelido, ?bool $sincronizavel): void
    {
        $apelido = mb_substr(trim($apelido) ?: 'Aparelho sem nome', 0, 60);
        Database::conexao()->prepare(
            'INSERT INTO credenciais_webauthn (usuario_id, credencial_id, chave_publica, apelido, sincronizavel, criado_em)
             VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([$usuarioId, $credencialId, $chavePublica, $apelido,
                    $sincronizavel === null ? null : ($sincronizavel ? 1 : 0)]);
        Auditoria::registrar('criou', 'credenciais_webauthn', $usuarioId,
            'cadastrou o aparelho "' . $apelido . '" para entrar por biometria');
    }

    /** A credencial e o dono, com o que decide se ele ainda pode entrar. */
    public static function achar(string $credencialId): ?array
    {
        $q = Database::conexao()->prepare(
            'SELECT c.*, u.id AS uid, u.nome, u.ativo, u.softsul
               FROM credenciais_webauthn c
               JOIN usuarios u ON u.id = c.usuario_id
              WHERE c.credencial_id = ?'
        );
        $q->execute([$credencialId]);
        return $q->fetch() ?: null;
    }

    /** O contador cresce a cada uso e denuncia clonagem. */
    public static function marcarUso(int $id, int $contador): void
    {
        Database::conexao()->prepare(
            'UPDATE credenciais_webauthn SET contador = ?, ultimo_uso_em = NOW(), ultimo_ip = ?, ultimo_aparelho = ? WHERE id = ?'
        )->execute([$contador, $_SERVER['REMOTE_ADDR'] ?? null, self::aparelhoDaRequisicao(), $id]);
    }

    /**
     * Um palpite honesto do aparelho, pelo user agent: "Celular Android",
     * "iPhone", "Computador Windows". Serve para a lista mostrar de onde veio
     * o último acesso, já que a chave sincronizada entra de qualquer aparelho
     * da mesma conta.
     */
    public static function aparelhoDaRequisicao(): string
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $tipo = match (true) {
            (bool) preg_match('/android/i', $ua) => preg_match('/mobile/i', $ua) ? 'Celular Android' : 'Tablet Android',
            (bool) preg_match('/iphone|ipod/i', $ua) => 'iPhone',
            (bool) preg_match('/ipad/i', $ua) => 'iPad',
            (bool) preg_match('/macintosh/i', $ua) => 'Mac ou iPad',
            (bool) preg_match('/windows/i', $ua) => 'Computador Windows',
            (bool) preg_match('/linux/i', $ua) => 'Computador Linux',
            default => 'Aparelho não identificado',
        };
        $nav = match (true) {
            (bool) preg_match('/Edg\//', $ua) => 'Edge',
            (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung Internet',
            (bool) preg_match('/Firefox\//', $ua) => 'Firefox',
            (bool) preg_match('/Chrome\//', $ua) => 'Chrome',
            (bool) preg_match('/Safari\//', $ua) => 'Safari',
            default => '',
        };
        return mb_substr($tipo . ($nav !== '' ? ' · ' . $nav : ''), 0, 80);
    }

    /**
     * Uma assinatura da lista de aparelhos da pessoa. O navegador guarda a
     * que viu ao cadastrar: se a lista mudar (a chave foi apagada do outro
     * lado), a marca deixa de valer e o aparelho volta a ser convidado.
     * Não é segredo: são identificadores que o navegador já recebe.
     */
    public static function assinatura(int $usuarioId): string
    {
        $q = Database::conexao()->prepare('SELECT GROUP_CONCAT(id ORDER BY id) FROM credenciais_webauthn WHERE usuario_id = ?');
        $q->execute([$usuarioId]);
        return substr(hash('sha256', (string) $q->fetchColumn()), 0, 16);
    }

    /** O nome sugerido pelo navegador erra; dá para corrigir sem cadastrar de novo. */
    public static function renomear(int $usuarioId, int $id, string $apelido): bool
    {
        $apelido = mb_substr(trim($apelido), 0, 60);
        if ($apelido === '') {
            return false;
        }
        $q = Database::conexao()->prepare('UPDATE credenciais_webauthn SET apelido = ? WHERE id = ? AND usuario_id = ?');
        $q->execute([$apelido, $id, $usuarioId]);
        return $q->rowCount() > 0;
    }

    public static function remover(int $usuarioId, int $id): bool
    {
        $q = Database::conexao()->prepare('SELECT apelido FROM credenciais_webauthn WHERE id = ? AND usuario_id = ?');
        $q->execute([$id, $usuarioId]);
        $apelido = $q->fetchColumn();
        if ($apelido === false) {
            return false;
        }
        Database::conexao()->prepare('DELETE FROM credenciais_webauthn WHERE id = ? AND usuario_id = ?')
            ->execute([$id, $usuarioId]);
        Auditoria::registrar('excluiu', 'credenciais_webauthn', $usuarioId, 'removeu o aparelho "' . $apelido . '"');
        return true;
    }

    public static function removerTodos(int $usuarioId): void
    {
        Database::conexao()->prepare('DELETE FROM credenciais_webauthn WHERE usuario_id = ?')->execute([$usuarioId]);
    }
}
