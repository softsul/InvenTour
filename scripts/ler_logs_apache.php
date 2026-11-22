<?php
/**
 * Lê os logs do Apache e soma nos contadores de acessos (app/Acessos.php).
 * Softsul Software
 *
 *   php scripts/ler_logs_apache.php [--pasta /outra/pasta]
 *
 * Roda pelo timer inventour-logs.timer, a cada 10 minutos, como www-data
 * com o grupo adm (é o grupo dos logs; o Apache e a fila não têm). Fica
 * fora da fila de propósito: a fila não precisa enxergar os logs.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Acessos.php';

$i = array_search('--pasta', $argv, true);
foreach (Acessos::ler($i !== false && isset($argv[$i + 1]) ? (string) $argv[$i + 1] : Acessos::PASTA) as $linha) {
    echo $linha, "\n";
}
