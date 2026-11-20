<?php
/**
 * Importa os dados abertos do Cadastur para a cópia local.
 * Softsul Software
 *
 *   php scripts/importar_cadastur.php                  todas as atividades novas
 *   php scripts/importar_cadastur.php --pacote X       só um conjunto
 *   php scripts/importar_cadastur.php --forcar         mesmo o que já entrou
 *
 * Roda também pela fila (job importar_cadastur, toda semana): a planilha é
 * trimestral, e o que já foi importado não é baixado de novo.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Cadastur.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$forcar = in_array('--forcar', $argv, true);
$pacote = null;
foreach ($argv as $i => $a) {
    if ($a === '--pacote' && isset($argv[$i + 1])) {
        $pacote = (string) $argv[$i + 1];
    }
}
$inicio = microtime(true);
$linhas = $pacote ? [$pacote . ': ' . Cadastur::importar($pacote, $forcar)] : Cadastur::importarTudo($forcar);
foreach ($linhas as $l) {
    echo $l, "\n";
}
printf("Pronto em %.0f s.\n", microtime(true) - $inicio);
