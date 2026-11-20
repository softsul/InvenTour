<?php
/**
 * Aplicador de migrações de banco.
 * Softsul Software
 *
 *   php scripts/migrar.php              aplica o que falta
 *   php scripts/migrar.php --situacao   só mostra o que falta, sem aplicar
 *
 * Cada migração é um arquivo em migracoes/ chamado NNN-descricao.sql, aplicado
 * uma única vez e na ordem do número. O que já rodou fica registrado na tabela
 * `migracoes`, com o hash do arquivo: se alguém editar uma migração já
 * aplicada, o script acusa em vez de ignorar em silêncio. Correção é sempre
 * migração nova.
 *
 * O mesmo aplicador do Orbit e do Centurion.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';

$soMostrar = in_array('--situacao', $argv, true);
$pdo = Database::conexao();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migracoes (
        id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        arquivo     VARCHAR(190) NOT NULL,
        hash        CHAR(64)     NOT NULL,
        aplicada_em DATETIME     NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_migracoes_arquivo (arquivo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$aplicadas = [];
foreach ($pdo->query('SELECT arquivo, hash FROM migracoes') as $linha) {
    $aplicadas[$linha['arquivo']] = $linha['hash'];
}

$arquivos = glob(__DIR__ . '/../migracoes/*.sql') ?: [];
sort($arquivos, SORT_NATURAL);

if (!$arquivos) {
    echo "Nenhuma migração encontrada em migracoes/.\n";
    exit(0);
}

$pendentes = [];
$alteradas = [];
foreach ($arquivos as $caminho) {
    $nome = basename($caminho);
    $hash = hash_file('sha256', $caminho);
    if (!isset($aplicadas[$nome])) {
        $pendentes[] = [$caminho, $nome, $hash];
    } elseif ($aplicadas[$nome] !== $hash) {
        $alteradas[] = $nome;
    }
}

// Migração já aplicada que mudou de conteúdo é sempre um erro: ou alguém
// editou o passado (e os bancos ficaram diferentes entre si), ou o arquivo
// corrompeu. Melhor parar e avisar do que aplicar o resto por cima.
if ($alteradas) {
    fwrite(STDERR, "ERRO: migrações já aplicadas foram alteradas depois:\n");
    foreach ($alteradas as $nome) {
        fwrite(STDERR, "  - {$nome}\n");
    }
    fwrite(STDERR, "Crie uma migração NOVA com a correção, em vez de editar a antiga.\n");
    exit(1);
}

if (!$pendentes) {
    echo 'Banco em dia: ' . count($arquivos) . " migração(ões) aplicada(s), nenhuma pendente.\n";
    exit(0);
}

echo count($pendentes) . " migração(ões) pendente(s):\n";
foreach ($pendentes as [, $nome]) {
    echo "  - {$nome}\n";
}
if ($soMostrar) {
    exit(0);
}
echo "\n";

$registrar = $pdo->prepare('INSERT INTO migracoes (arquivo, hash, aplicada_em) VALUES (?, ?, NOW())');

foreach ($pendentes as [$caminho, $nome, $hash]) {
    echo "aplicando {$nome} ... ";
    $sql = (string) file_get_contents($caminho);

    // DDL no MySQL faz commit implícito, então envolver em transação daria
    // uma falsa sensação de segurança. O que dá para garantir é parar no
    // primeiro erro, sem marcar a migração como aplicada.
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        echo "FALHOU\n";
        fwrite(STDERR, "\n{$nome}: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Nada foi registrado para esta migração. Corrija e rode de novo.\n");
        exit(1);
    }
    $registrar->execute([$nome, $hash]);
    echo "ok\n";
}

echo "\nPronto. " . count($pendentes) . " migração(ões) aplicada(s).\n";
