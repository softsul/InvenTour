<?php
/**
 * Grava o usuário e a senha SMTP do Amazon SES em app/config.php.
 * Softsul Software
 *
 *   php scripts/configurar_smtp.php
 *
 * A credencial nunca passa por chat, e-mail nem histórico do terminal: o
 * script pergunta, a senha é digitada sem eco, e o arquivo é regravado com
 * var_export, sem sed (um caractere especial na senha quebraria o PHP).
 * No fim, manda um e-mail de teste para quem rodou, se pedido.
 */
declare(strict_types=1);

$arquivo = __DIR__ . '/../app/config.php';
if (!is_writable($arquivo)) {
    fwrite(STDERR, "Sem permissão para gravar {$arquivo}. Rode como o dono do arquivo.\n");
    exit(1);
}
$cfg = require $arquivo;

echo "Servidor: {$cfg['mail']['smtp_host']}:{$cfg['mail']['smtp_porta']}\n";
echo "Remetente: {$cfg['mail']['remetente_nome']} <{$cfg['mail']['remetente_email']}>\n\n";

$usuario = trim((string) readline('Usuário SMTP (começa com AKIA): '));
echo 'Senha SMTP: ';
system('stty -echo');
$senha = trim((string) fgets(STDIN));
system('stty echo');
echo "\n";

if (!preg_match('/^AKIA[A-Z0-9]{16}$/', $usuario)) {
    fwrite(STDERR, "O usuário SMTP do SES tem 20 caracteres e começa com AKIA. Nada foi gravado.\n");
    exit(1);
}
if (strlen($senha) < 40) {
    fwrite(STDERR, 'A senha SMTP do SES tem 44 caracteres; esta tem ' . strlen($senha) . ". Nada foi gravado.\n");
    exit(1);
}

$cfg['mail']['smtp_usuario'] = $usuario;
$cfg['mail']['smtp_senha'] = $senha;
$texto = "<?php\n/**\n * Configuração real do InvenTour. NÃO versionar. Modelo: config.exemplo.php.\n * Softsul Software\n */\nreturn " . var_export($cfg, true) . ";\n";
$tmp = $arquivo . '.' . bin2hex(random_bytes(4));
// O arquivo novo nasce com o dono de quem roda o script. Rodado como root,
// ficava root:www-data e o deploy (ubuntu) não conseguia mais ler: mantém
// o dono e o grupo do arquivo anterior.
$dono = fileowner($arquivo);
$grupo = filegroup($arquivo);
file_put_contents($tmp, $texto);
chmod($tmp, 0640);
@chown($tmp, $dono);
@chgrp($tmp, $grupo);
rename($tmp, $arquivo);
echo "Gravado.\n";

$para = trim((string) readline('E-mail para receber um teste (Enter para pular): '));
if ($para !== '') {
    require_once __DIR__ . '/../app/svg.php';
    require_once __DIR__ . '/../app/Email.php';
    try {
        Email::enviar($para, 'Teste de envio do InvenTour',
            Email::molde('O envio de e-mail está funcionando', Email::paragrafos("Esta mensagem saiu do servidor do InvenTour pelo Amazon SES.\n\nSe ela chegou na caixa de entrada, e não no spam, o domínio está bem configurado.")));
        echo "Enviado para {$para}.\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Falhou: ' . $e->getMessage() . "\n");
        exit(1);
    }
}
