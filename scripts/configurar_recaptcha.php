<?php
/**
 * Grava as chaves do reCAPTCHA v2 (caixa "Não sou um robô") em app/config.php.
 * Softsul Software
 *
 *   php scripts/configurar_recaptcha.php
 *
 * As chaves saem de https://www.google.com/recaptcha/admin (tipo v2, caixa
 * "Não sou um robô", domínio inventour.com.br, que vale para os
 * subdomínios). A chave secreta é digitada sem eco e nunca passa por chat;
 * o arquivo é regravado com var_export, como no configurar_smtp.php.
 */
declare(strict_types=1);

$arquivo = __DIR__ . '/../app/config.php';
if (!is_writable($arquivo)) {
    fwrite(STDERR, "Sem permissão para gravar {$arquivo}. Rode como o dono do arquivo.\n");
    exit(1);
}
$cfg = require $arquivo;

$site = trim((string) readline('Chave do site (começa com 6L): '));
echo 'Chave secreta: ';
system('stty -echo');
$secreta = trim((string) fgets(STDIN));
system('stty echo');
echo "\n";

if (!preg_match('/^6L[A-Za-z0-9_-]{30,50}$/', $site) || !preg_match('/^6L[A-Za-z0-9_-]{30,50}$/', $secreta) || $site === $secreta) {
    fwrite(STDERR, "As duas chaves do reCAPTCHA começam com 6L e têm cerca de 40 caracteres, uma diferente da outra. Nada foi gravado.\n");
    exit(1);
}

$cfg['recaptcha'] = ['site_key' => $site, 'secret_key' => $secreta];
$texto = "<?php\n/**\n * Configuração real do InvenTour. NÃO versionar. Modelo: config.exemplo.php.\n * Softsul Software\n */\nreturn " . var_export($cfg, true) . ";\n";
$tmp = $arquivo . '.' . bin2hex(random_bytes(4));
$dono = fileowner($arquivo);
$grupo = filegroup($arquivo);
file_put_contents($tmp, $texto);
chmod($tmp, 0640);
@chown($tmp, $dono);
@chgrp($tmp, $grupo);
rename($tmp, $arquivo);
echo "Gravado. O Fale conosco já pode enviar.\n";
