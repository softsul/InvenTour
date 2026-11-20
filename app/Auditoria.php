<?php
/**
 * Registro de auditoria.
 * Softsul Software
 *
 * Quem mudou o quê, quando e em qual destino. Isso não se reconstrói depois,
 * então todo caminho que grava passa por aqui.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

final class Auditoria
{
    /**
     * @param string $acao criou, alterou, excluiu, entrou, saiu, exportou,
     *                     login_negado, acesso_negado, trocou_empresa
     */
    public static function registrar(
        string $acao,
        ?string $entidade = null,
        ?int $entidadeId = null,
        ?string $resumo = null,
        ?array $antes = null,
        ?array $depois = null,
        ?int $empresaId = null
    ): void {
        // A auditoria nunca derruba a operação que ela observa: se o registro
        // falhar, o trabalho continua e o erro vai para o log do PHP.
        try {
            $usuario = class_exists('Auth', false) ? Auth::usuarioOuNulo() : null;
            if ($empresaId === null && class_exists('Empresa', false)) {
                $empresaId = Empresa::id() ?: null;
            }
            Database::conexao()->prepare(
                'INSERT INTO auditoria
                    (usuario_id, usuario_nome, empresa_id, acao, entidade, entidade_id, resumo,
                     dados_antes, dados_depois, ip, user_agent, criado_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $usuario['id']   ?? null,
                $usuario['nome'] ?? null,
                $empresaId ?: null,
                $acao,
                $entidade,
                $entidadeId,
                $resumo !== null ? mb_substr($resumo, 0, 255) : null,
                $antes  !== null ? json_encode(self::limpar($antes),  JSON_UNESCAPED_UNICODE) : null,
                $depois !== null ? json_encode(self::limpar($depois), JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255) ?: null,
            ]);
        } catch (Throwable $e) {
            error_log('auditoria falhou: ' . $e->getMessage());
        }
    }

    /**
     * Linha no log de segurança, lida pelo fail2ban, em UTC como o relógio do
     * servidor. Fica num arquivo próprio, com formato fixo, para o filtro não
     * depender do texto da auditoria.
     */
    public static function falhaDeAcesso(string $motivo): void
    {
        $cfg = require __DIR__ . '/config.php';
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $linha = gmdate('Y-m-d H:i:s') . ' UTC inventour ' . preg_replace('/[^a-z_]/', '', $motivo) . ' ip=' . $ip . "\n";
        @file_put_contents($cfg['caminhos']['logs'] . '/seguranca.log', $linha, FILE_APPEND | LOCK_EX);
    }

    /** Auditoria que guarda hash de senha ou segredo vira, ela mesma, um alvo. */
    private static function limpar(array $dados): array
    {
        $proibidos = ['senha', 'senha_hash', 'duplo_fator_segredo', 'api_key', 'token',
                      'token_hash', 'client_secret', 'smtp_senha', 'segredo'];
        foreach ($dados as $chave => $valor) {
            if (in_array($chave, $proibidos, true)) {
                $dados[$chave] = '***';
            }
        }
        return $dados;
    }
}
