<?php
/**
 * Importa o Mapa do Turismo Brasileiro (regiões turísticas e categoria dos
 * municípios) da API pública do Ministério do Turismo.
 * Softsul Software
 *
 *   php scripts/importar_mapa_turismo.php
 *
 * Roda também pela fila (job importar_mapa_turismo, todo mês): o Mapa muda
 * uma vez por ano, e importar de novo só regrava o ano corrente.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/MapaTurismo.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$inicio = microtime(true);
echo MapaTurismo::importar(), "\n";
printf("Pronto em %.0f s.\n", microtime(true) - $inicio);
