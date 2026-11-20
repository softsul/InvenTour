<?php
/**
 * Conexão PDO única e compartilhada, e os auxiliares de data usados em todo
 * o projeto.
 * Softsul Software
 *
 * Duas conexões possíveis: a do painel e da fila (config db, leitura e
 * escrita em tudo) e a do portal público (config db_portal, só leitura nas
 * tabelas públicas e escrita nas métricas). Uma falha no portal não alcança
 * usuários nem auditoria porque o usuário do MySQL não tem como.
 */
declare(strict_types=1);

// Fuso fixo do Brasil. Sem isto, toda hora exibida na tela e todo
// agrupamento por dia sai em UTC, que é o padrão do servidor e não bate com
// o horário de ninguém aqui.
date_default_timezone_set('America/Sao_Paulo');

/**
 * Converte um datetime vindo do BANCO para timestamp Unix.
 *
 * A conexão já executa SET time_zone = '-03:00' ao conectar, então NOW() e
 * qualquer datetime gravado pela aplicação já chegam em horário de Brasília.
 * Por isso aqui basta um strtotime simples: tratar como UTC e converter de
 * novo produziria dupla conversão.
 */
function epocaDoBanco(?string $valor): ?int
{
    if (!$valor) {
        return null;
    }
    $epoca = strtotime($valor);
    return $epoca === false ? null : $epoca;
}

/** Formata um datetime do banco no padrão brasileiro. Usar sempre esta, em vez de date() solto, para o formato ficar igual em todo lugar. */
function dataBr(?string $valor, string $formato = 'd/m/Y H:i'): string
{
    $epoca = epocaDoBanco($valor);
    if ($epoca === null) {
        return '-';
    }
    return date($formato, $epoca);
}

/**
 * Formata um valor decimal como moeda brasileira.
 *
 * Sem o R$ quando o valor vai para dentro de um campo de digitação: ali ele
 * seria só mais um caractere para a pessoa apagar antes de escrever.
 */
function moedaBr(?float $valor, bool $comSimbolo = true): string
{
    if ($valor === null) {
        return $comSimbolo ? '-' : '';
    }
    return ($comSimbolo ? 'R$ ' : '') . number_format($valor, 2, ',', '.');
}

class Database
{
    private static ?PDO $instancia = null;
    private static ?PDO $portal = null;

    public static function conexao(): PDO
    {
        if (self::$instancia === null) {
            $cfg = require __DIR__ . '/config.php';
            self::$instancia = self::abrir($cfg['db']);
        }
        return self::$instancia;
    }

    /**
     * Conexão para consultas que só leem. Hoje devolve a mesma conexão de
     * conexao(); existe para o portal e os relatórios passarem a usar uma
     * réplica de leitura no futuro sem mudar nenhuma tela: basta trocar aqui.
     */
    public static function leitura(): PDO
    {
        return self::conexao();
    }

    /**
     * Conexão do portal público, com o usuário inventour_portal (config
     * db_portal). As mesmas opções da conexão principal; o que muda é o que
     * o MySQL deixa esse usuário fazer.
     */
    public static function conexaoPortal(): PDO
    {
        if (self::$portal === null) {
            $cfg = require __DIR__ . '/config.php';
            self::$portal = self::abrir($cfg['db_portal'] ?? $cfg['db']);
        }
        return self::$portal;
    }

    /** Abre uma conexão com as opções da casa, a partir de um bloco de config. */
    private static function abrir(array $db): PDO
    {
        $dsn = "mysql:host={$db['host']};dbname={$db['nome']};charset={$db['charset']}";
        $pdo = new PDO($dsn, $db['usuario'], $db['senha'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Offset fixo em vez do nome da zona: nem todo MySQL tem as
        // tabelas de fuso carregadas (mysql_tzinfo_to_sql), e o offset
        // funciona sempre, sem depender de configuração no servidor.
        $pdo->exec("SET time_zone = '-03:00'");
        return $pdo;
    }

    /** Executa um bloco dentro de transação, desfazendo tudo se algo falhar. */
    public static function transacao(callable $bloco): mixed
    {
        $pdo = self::conexao();
        // Transação aninhada não existe em PDO: se já estamos dentro de uma,
        // apenas roda o bloco e deixa quem abriu decidir o commit.
        if ($pdo->inTransaction()) {
            return $bloco($pdo);
        }
        $pdo->beginTransaction();
        try {
            $retorno = $bloco($pdo);
            $pdo->commit();
            return $retorno;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
