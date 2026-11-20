<?php
/**
 * Gera as miniaturas que faltam das fotos já guardadas (inventário e
 * experiências). Fotos novas já nascem com elas (Inventario::guardarFoto).
 * Softsul Software
 *
 *   php scripts/gerar_miniaturas.php
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Imagem.php';

$cfg = require __DIR__ . '/../app/config.php';
$raiz = rtrim((string) $cfg['caminhos']['midias'], '/') . '/inventario';
$feitas = $faltando = 0;
foreach (Database::conexao()->query('SELECT empresa_id, arquivo FROM inventario_midias') as $f) {
    $pasta = $raiz . '/' . (int) $f['empresa_id'];
    $grande = $pasta . '/' . basename((string) $f['arquivo']);
    if (!is_file($grande)) {
        $faltando++;
        continue;
    }
    $precisa = array_filter(array_keys(Imagem::MINIATURAS), fn ($s) => !is_file($pasta . '/' . Imagem::nomeMiniatura($grande, $s)));
    if (!$precisa) {
        continue;
    }
    foreach (Imagem::miniaturas((string) file_get_contents($grande)) as $sufixo => $bytes) {
        file_put_contents($pasta . '/' . Imagem::nomeMiniatura($grande, $sufixo), $bytes);
    }
    $feitas++;
}
echo "Miniaturas geradas para {$feitas} foto(s)" . ($faltando ? "; {$faltando} sem o arquivo grande" : '') . ".\n";
