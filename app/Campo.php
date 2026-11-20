<?php
/**
 * Aplicativo InvenTour Campo: conexão do aparelho e acesso da API.
 * Softsul Software
 *
 * O app (mobile/campo) traz a tela embutida e funciona sem sinal; fala com
 * o servidor só pelas APIs da coleta, com o token do aparelho no cabeçalho
 * X-Aparelho. A conexão é por um código de 8 letras gerado no painel
 * (Coleta de campo), que vale 15 minutos e uma vez só: assim a senha e o
 * segundo fator ficam no painel, e o celular perdido se desliga lá.
 */
declare(strict_types=1);

final class Campo
{
    /** Origens do WebView do Capacitor (Android com esquema https, iPhone). */
    private const ORIGENS_DO_APP = ['https://localhost', 'capacitor://localhost', 'ionic://localhost'];
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Abre a API para o app: CORS para as origens do WebView e o pré-voo
     * respondido aqui. Chame no começo de cada API que o app usa, antes de
     * qualquer saída, e depois Auth::entrarPorAparelho().
     */
    public static function abrirParaApp(): void
    {
        $origem = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if (in_array($origem, self::ORIGENS_DO_APP, true)) {
            header('Access-Control-Allow-Origin: ' . $origem);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: X-Aparelho, Content-Type');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Max-Age: 600');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        if (!empty($_SERVER['HTTP_X_APARELHO'])) {
            Auth::entrarPorAparelho();
        }
    }

    /** Novo código de conexão para a pessoa logada no destino atual. Devolve o código (mostrado uma vez). */
    public static function novoCodigo(): string
    {
        $u = Auth::usuarioOuNulo();
        $codigo = '';
        for ($i = 0; $i < 8; $i++) {
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }
        $pdo = Database::conexao();
        // Código antigo não usado da mesma pessoa sai: só um vale por vez.
        $pdo->prepare('DELETE FROM campo_aparelhos WHERE usuario_id = ? AND empresa_id = ? AND token_hash IS NULL')
            ->execute([(int) $u['id'], Empresa::id()]);
        $pdo->prepare('INSERT INTO campo_aparelhos (usuario_id, empresa_id, codigo_hash, codigo_expira, versao_sessao, criado_em)
                       VALUES (?, ?, ?, NOW() + INTERVAL 15 MINUTE, ?, NOW())')
            ->execute([(int) $u['id'], Empresa::id(), hash('sha256', $codigo), (int) $u['versao_sessao']]);
        Auditoria::registrar('criou', 'campo_aparelhos', (int) $pdo->lastInsertId(), 'gerou um código para conectar o app InvenTour Campo');
        return $codigo;
    }

    /**
     * Troca o código pelo token do aparelho. Devolve [token, usuário, destino]
     * ou null (código errado, vencido ou já usado).
     */
    public static function conectar(string $codigo, string $nomeAparelho): ?array
    {
        $codigo = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $codigo));
        if (strlen($codigo) !== 8) {
            return null;
        }
        $pdo = Database::conexao();
        $q = $pdo->prepare('SELECT a.id, a.usuario_id, a.empresa_id, u.nome, e.nome_fantasia
                            FROM campo_aparelhos a JOIN usuarios u ON u.id = a.usuario_id JOIN empresas e ON e.id = a.empresa_id
                            WHERE a.codigo_hash = ? AND a.token_hash IS NULL AND a.revogado_em IS NULL AND a.codigo_expira > NOW() AND u.ativo = 1');
        $q->execute([hash('sha256', $codigo)]);
        $a = $q->fetch();
        if (!$a) {
            return null;
        }
        $token = rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
        $pdo->prepare('UPDATE campo_aparelhos SET token_hash = ?, codigo_hash = NULL, codigo_expira = NULL, nome = ?, conectado_em = NOW(), ultimo_uso_em = NOW()
                       WHERE id = ?')
            ->execute([hash('sha256', $token), mb_substr(trim($nomeAparelho), 0, 80) ?: null, (int) $a['id']]);
        return [$token, (string) $a['nome'], (string) $a['nome_fantasia']];
    }

    /** Aparelhos conectados da pessoa no destino atual. */
    public static function aparelhos(): array
    {
        $q = Database::conexao()->prepare('SELECT id, nome, conectado_em, ultimo_uso_em FROM campo_aparelhos
                                           WHERE usuario_id = ? AND empresa_id = ? AND token_hash IS NOT NULL AND revogado_em IS NULL
                                           ORDER BY conectado_em DESC');
        $q->execute([(int) Auth::usuarioOuNulo()['id'], Empresa::id()]);
        return $q->fetchAll();
    }

    public static function desconectar(int $id): void
    {
        Database::conexao()->prepare('UPDATE campo_aparelhos SET revogado_em = NOW() WHERE id = ? AND usuario_id = ? AND empresa_id = ?')
            ->execute([$id, (int) Auth::usuarioOuNulo()['id'], Empresa::id()]);
        Auditoria::registrar('alterou', 'campo_aparelhos', $id, 'desconectou um aparelho do app InvenTour Campo');
    }
}
