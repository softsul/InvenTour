<?php
/**
 * Parâmetros do InvenTour por destino, editáveis pela tela.
 * Softsul Software
 *
 * Chave e valor por grupo, na tabela `ajustes`, com empresa_id: cada
 * destino tem os seus (regra de curadoria, termo, limites de conflito,
 * integrações), e a linha de empresa 0 é a da plataforma. O que estiver no
 * banco vale; o que não estiver cai no config.php, que passa a ser só o
 * valor de partida. Assim uma instalação nova funciona antes de alguém
 * abrir a tela, e a tela nunca precisa de tudo preenchido para subir.
 *
 * Valor secreto (chave de API, senha de SMTP) é gravado cifrado com Cripto
 * e marcado em `cifrado`: um dump do banco sozinho não entrega o acesso.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Cripto.php';
require_once __DIR__ . '/Empresa.php';

final class Ajustes
{
    /** @var array<string,array<string,string>> cache por "empresa:grupo" */
    private static array $cache = [];

    /** A empresa pedida, ou a atual; na visão da plataforma é 0. */
    private static function empresa(?int $empresaId): int
    {
        if ($empresaId !== null) {
            return max(0, $empresaId);
        }
        return session_status() === PHP_SESSION_ACTIVE ? Empresa::id() : 0;
    }

    /**
     * Todos os valores de um grupo, com o config.php por baixo.
     *
     * @return array<string,string>
     */
    public static function grupo(string $grupo, ?int $empresaId = null): array
    {
        $empresa = self::empresa($empresaId);
        $marca = $empresa . ':' . $grupo;
        if (isset(self::$cache[$marca])) {
            return self::$cache[$marca];
        }
        $cfg = require __DIR__ . '/config.php';
        $base = [];
        foreach ((array) ($cfg[$grupo] ?? []) as $chave => $valor) {
            // Só o que é escalar vira valor de partida: um bloco aninhado do
            // config não tem como ser editado pela tela nem gravado numa linha.
            if (is_scalar($valor) || $valor === null) {
                $base[(string) $chave] = is_bool($valor) ? ($valor ? '1' : '0') : (string) $valor;
            }
        }

        $s = Database::conexao()->prepare('SELECT chave, valor, cifrado FROM ajustes WHERE empresa_id = ? AND grupo = ?');
        $s->execute([$empresa, $grupo]);
        foreach ($s->fetchAll() as $linha) {
            // String vazia no banco é resposta: quer dizer "não tenho este
            // dado", e não "use o do arquivo".
            $valor = (string) $linha['valor'];
            $base[$linha['chave']] = $linha['cifrado'] ? Cripto::abrir($valor) : $valor;
        }
        return self::$cache[$marca] = $base;
    }

    /** Um valor, com padrão para quando não houver. */
    public static function ler(string $grupo, string $chave, string $padrao = '', ?int $empresaId = null): string
    {
        return self::grupo($grupo, $empresaId)[$chave] ?? $padrao;
    }

    /**
     * Grava um grupo inteiro. Só as chaves recebidas são tocadas. As chaves
     * listadas em $cifradas vão cifradas com Cripto::guardar e marcadas em
     * `cifrado`, e grupo() as abre ao ler.
     *
     * @param array<string,string|null> $valores
     * @param list<string> $cifradas
     */
    public static function salvar(string $grupo, array $valores, ?int $empresaId = null, ?int $usuarioId = null, array $cifradas = []): void
    {
        $empresa = self::empresa($empresaId);
        $pdo = Database::conexao();
        $stmt = $pdo->prepare(
            'INSERT INTO ajustes (empresa_id, grupo, chave, valor, cifrado, atualizado_em, atualizado_por)
             VALUES (?, ?, ?, ?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor),
                                     cifrado = VALUES(cifrado),
                                     atualizado_em = NOW(),
                                     atualizado_por = VALUES(atualizado_por)'
        );
        foreach ($valores as $chave => $valor) {
            $cifrar = in_array((string) $chave, $cifradas, true);
            $texto = $valor === null ? null : (string) $valor;
            if ($cifrar && $texto !== null && $texto !== '') {
                $texto = Cripto::guardar($texto);
            }
            $stmt->execute([$empresa, $grupo, (string) $chave, $texto, $cifrar && $texto !== null && $texto !== '' ? 1 : 0, $usuarioId]);
        }
        unset(self::$cache[$empresa . ':' . $grupo]);
    }

    /** Esquece o cache, para quem gravou direto no banco (migração, script). */
    public static function esquecer(): void
    {
        self::$cache = [];
    }
}
