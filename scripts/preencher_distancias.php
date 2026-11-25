<?php
/**
 * Preenche as distâncias da ficha (aeroporto, rodoviária, estações, ônibus,
 * táxi, sede) de todos os itens com ponto e campos vazios. Ver
 * Transporte::preencherItens. A fila faz o mesmo sozinha depois de buscar os
 * pontos de transporte do destino (job distancias_itens).
 * Softsul Software
 *
 *   sudo -u www-data php scripts/preencher_distancias.php --destino=1 [--item=220] [--simular]
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Transporte.php';

$opc = getopt('', ['destino:', 'item:', 'simular']);
$eid = (int) ($opc['destino'] ?? 0);
if (!$eid) {
    fwrite(STDERR, "uso: php scripts/preencher_distancias.php --destino=N [--item=N] [--simular]\n");
    exit(1);
}
[$itens, $campos] = Transporte::preencherItens($eid, isset($opc['item']) ? (int) $opc['item'] : null, isset($opc['simular']),
    function (string $linha): void { echo $linha, "\n"; });
echo (isset($opc['simular']) ? 'Simulação: ' : '') . "{$itens} itens, {$campos} campos.\n";
