<?php
/**
 * O destino em que a pessoa está trabalhando agora.
 * Softsul Software
 *
 * Cada destino (município, região turística ou corporação) é uma linha de
 * `empresas`. Todo dado operacional (organizador, local, evento, notícia)
 * leva empresa_id, e o filtro é aplicado na CONSULTA, sempre com
 * Empresa::id() ou Empresa::filtro(), e nunca na tela. Quem tem vínculo com
 * mais de um destino troca por /trocar-empresa. A equipe Softsul pode
 * escolher qualquer destino, ou nenhum (visão da plataforma, id 0).
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

final class Empresa
{
    private static ?array $atual = null;

    /** Id do destino atual, ou 0 na visão da plataforma. */
    public static function id(): int
    {
        return (int) ($_SESSION['empresa_id'] ?? 0);
    }

    public static function atual(): ?array
    {
        $id = self::id();
        if (!$id) {
            return null;
        }
        if (self::$atual === null || (int) self::$atual['id'] !== $id) {
            $q = Database::conexao()->prepare('SELECT * FROM empresas WHERE id = ?');
            $q->execute([$id]);
            self::$atual = $q->fetch() ?: null;
        }
        return self::$atual;
    }

    /** Os destinos que a pessoa pode escolher. */
    public static function disponiveis(int $usuarioId): array
    {
        $pdo = Database::conexao();
        $s = $pdo->prepare('SELECT softsul FROM usuarios WHERE id = ?');
        $s->execute([$usuarioId]);
        if ((int) $s->fetchColumn() === 1) {
            return $pdo->query(
                'SELECT e.id, e.nome_fantasia, e.cidade, e.uf, e.slug, e.tipo, e.logo, e.foto, NULL AS papel_nome
                 FROM empresas e WHERE e.ativo = 1 ORDER BY e.nome_fantasia'
            )->fetchAll();
        }
        $q = $pdo->prepare(
            "SELECT e.id, e.nome_fantasia, e.cidade, e.uf, e.slug, e.tipo, e.logo, e.foto,
                    (SELECT GROUP_CONCAT(p.nome ORDER BY p.ordem SEPARATOR ', ') FROM usuario_empresa_papel uep
                     JOIN papeis p ON p.id = uep.papel_id WHERE uep.usuario_id = ue.usuario_id AND uep.empresa_id = e.id) AS papel_nome
             FROM usuario_empresa ue
             JOIN empresas e ON e.id = ue.empresa_id
             WHERE ue.usuario_id = ? AND ue.ativo = 1 AND e.ativo = 1
             ORDER BY e.nome_fantasia"
        );
        $q->execute([$usuarioId]);
        return $q->fetchAll();
    }

    public static function podeEntrar(int $usuarioId, int $empresaId): bool
    {
        foreach (self::disponiveis($usuarioId) as $e) {
            if ((int) $e['id'] === $empresaId) {
                return true;
            }
        }
        return false;
    }

    /**
     * No login: o destino padrão da pessoa, se ainda valer; senão o primeiro
     * da lista. A equipe Softsul sem padrão começa na visão da plataforma.
     */
    public static function escolherAoEntrar(int $usuarioId): void
    {
        $q = Database::conexao()->prepare('SELECT softsul, empresa_padrao_id FROM usuarios WHERE id = ?');
        $q->execute([$usuarioId]);
        $u = $q->fetch();
        $lista = self::disponiveis($usuarioId);
        $ids = array_map(fn ($e) => (int) $e['id'], $lista);
        $padrao = (int) ($u['empresa_padrao_id'] ?? 0);
        if ($padrao && in_array($padrao, $ids, true)) {
            $_SESSION['empresa_id'] = $padrao;
        } elseif (!empty($u['softsul'])) {
            $_SESSION['empresa_id'] = 0;
        } else {
            $_SESSION['empresa_id'] = $ids[0] ?? 0;
        }
        self::$atual = null;
    }

    /** Troca de destino, conferindo o acesso no servidor. */
    public static function trocar(int $usuarioId, int $empresaId, bool $tornarPadrao = true): bool
    {
        $softsul = Auth::ehSoftsul();
        if ($empresaId === 0 ? !$softsul : !self::podeEntrar($usuarioId, $empresaId)) {
            return false;
        }
        $_SESSION['empresa_id'] = $empresaId;
        self::$atual = null;
        Auth::esquecerCache();
        if ($tornarPadrao) {
            Database::conexao()->prepare('UPDATE usuarios SET empresa_padrao_id = ? WHERE id = ?')
                ->execute([$empresaId ?: null, $usuarioId]);
        }
        Auditoria::registrar('trocou_empresa', 'empresas', $empresaId ?: null,
            $empresaId ? 'passou a trabalhar neste destino' : 'voltou para a visão da plataforma', null, null, $empresaId);
        return true;
    }

    /**
     * Para telas que só fazem sentido dentro de um destino: na visão da
     * plataforma, manda escolher um antes.
     */
    public static function exigir(): array
    {
        $e = self::atual();
        if (!$e) {
            header('Location: /trocar-empresa?destino=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }
        return $e;
    }

    /**
     * O pedaço de WHERE que prende a consulta ao destino atual, com o
     * parâmetro separado: [' AND e.empresa_id = ?', [id]]. Na visão da
     * plataforma devolve ' AND 1 = 0': sem destino nenhum, nada é visível.
     * É o mesmo princípio do Escopo: na dúvida, não mostra.
     *
     * Uso:
     *     [$filtro, $params] = Empresa::filtro('o');
     *     $sql = "SELECT * FROM organizadores o WHERE o.ativo = 1 {$filtro}";
     *
     * A tela que precisa de todos os destinos de propósito (Destinos, Fila,
     * na plataforma) não usa este filtro, e isso fica visível na consulta.
     *
     * @return array{0:string,1:array}
     */
    public static function filtro(string $alias = ''): array
    {
        $p = $alias === '' ? '' : $alias . '.';
        $id = self::id();
        return $id ? [" AND {$p}empresa_id = ?", [$id]] : [' AND 1 = 0', []];
    }

    /**
     * Desliga um destino. Sobe a versao_sessao de todo mundo vinculado a
     * ele, derrubando na hora quem está dentro: a conta continua existindo,
     * mas sem destino ativo ela não entra (Auth::temAlgumAcesso).
     */
    public static function inativar(int $empresaId): void
    {
        $pdo = Database::conexao();
        $pdo->prepare('UPDATE empresas SET ativo = 0, atualizado_em = NOW() WHERE id = ?')->execute([$empresaId]);
        $pdo->prepare(
            'UPDATE usuarios u JOIN usuario_empresa ue ON ue.usuario_id = u.id
             SET u.versao_sessao = u.versao_sessao + 1 WHERE ue.empresa_id = ?'
        )->execute([$empresaId]);
        if (self::$atual !== null && (int) self::$atual['id'] === $empresaId) {
            self::$atual = null;
        }
    }

    /** Religa um destino. Os vínculos voltam a valer sem mexer em ninguém. */
    public static function ativar(int $empresaId): void
    {
        Database::conexao()->prepare('UPDATE empresas SET ativo = 1, atualizado_em = NOW() WHERE id = ?')->execute([$empresaId]);
        self::$atual = null;
    }
}
