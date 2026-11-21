<?php
/** Surpreenda-me: leva a um lugar do guia com foto, sorteado na hora (O que fazer, ou qualquer pilar). Softsul Software. */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
$lista = array_values(array_filter(Portal::itens($eid, 'o-que-fazer', [], 2000), fn ($i) => $i['foto'])) ?: array_values(Portal::itens($eid, null, [], 2000));
if (!$lista) {
    header('Location: ' . Portal::url('/'), true, 302);
    exit;
}
$i = $lista[random_int(0, count($lista) - 1)];
header('Cache-Control: no-store');
header('Location: ' . PortalLayout::urlLugar($i), true, 302);
