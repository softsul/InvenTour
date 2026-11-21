<?php
/**
 * A cor do destino como folha de estilo (/acento.css?c=RRGGBB): a política
 * de segurança do portal não aceita estilo embutido. Softsul Software.
 */
declare(strict_types=1);
$c = strtoupper((string) ($_GET['c'] ?? ''));
if (!preg_match('/^[0-9A-F]{6}$/', $c)) {
    $c = '0E7C66';
}
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');
// Um tom mais escuro para texto e foco, calculado da própria cor.
[$r, $g, $b] = array_map('hexdec', str_split($c, 2));
$escuro = sprintf('%02X%02X%02X', (int) ($r * .72), (int) ($g * .72), (int) ($b * .72));
echo ":root{--acento:#{$c};--acento-escuro:#{$escuro};--acento-sup:rgba({$r},{$g},{$b},.10)}\n";
