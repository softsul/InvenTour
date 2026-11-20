<?php
/**
 * Grava a chave do WAHA (chat.venatio.com.br) em app/config.php.
 * Softsul Software
 *
 *   php scripts/configurar_waha.php
 *
 * É a chave que busca a foto do perfil do WhatsApp das pessoas (Meus dados,
 * Usuários) e confere se o número tem WhatsApp. A mesma do Orbit e do
 * EventCity. A chave nunca passa por chat, e-mail nem histórico: o script
 * pergunta sem eco e regrava o arquivo com var_export. No fim, testa a
 * conexão com a sessão de consulta.
 */
declare(strict_types=1);

$arquivo = __DIR__ . '/../app/config.php';
if (!is_writable($arquivo)) {
    fwrite(STDERR, "Sem permissão para gravar {$arquivo}. Rode como o dono do arquivo (ubuntu).\n");
    exit(1);
}
$cfg = require $arquivo;
echo "Servidor: {$cfg['waha']['url']}  (sessão de consulta: {$cfg['waha']['sessao']})\n";
echo 'Chave da API do WAHA (X-Api-Key): ';
system('stty -echo');
$chave = trim((string) fgets(STDIN));
system('stty echo');
echo "\n";
if (strlen($chave) < 16) {
    fwrite(STDERR, "Chave curta demais. Nada foi gravado.\n");
    exit(1);
}

// Testa antes de gravar: a sessão de consulta precisa responder.
$c = curl_init(rtrim((string) $cfg['waha']['url'], '/') . '/sessions/' . rawurlencode((string) $cfg['waha']['sessao']));
curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Api-Key: ' . $chave]]);
$resposta = (string) curl_exec($c);
$codigo = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
curl_close($c);
if ($codigo !== 200) {
    fwrite(STDERR, "O WAHA respondeu {$codigo} com esta chave. Nada foi gravado.\n");
    exit(1);
}
echo 'Sessão ' . $cfg['waha']['sessao'] . ': ' . ((json_decode($resposta, true)['status'] ?? '?')) . "\n";

$cfg['waha']['api_key'] = $chave;
$texto = "<?php\n/**\n * Configuração real do InvenTour. NÃO versionar. Modelo: config.exemplo.php.\n * Softsul Software\n */\nreturn " . var_export($cfg, true) . ";\n";
$tmp = $arquivo . '.' . bin2hex(random_bytes(4));
$dono = fileowner($arquivo);
$grupo = filegroup($arquivo);
file_put_contents($tmp, $texto);
chmod($tmp, 0640);
@chown($tmp, $dono);
@chgrp($tmp, $grupo);
rename($tmp, $arquivo);
echo "Gravado. A foto do WhatsApp passa a aparecer em Meus dados e em Usuários.\n";
