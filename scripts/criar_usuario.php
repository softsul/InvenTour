<?php
/**
 * Cria o primeiro administrador da plataforma num servidor novo.
 * Softsul Software
 *
 *   php scripts/criar_usuario.php "Nome Completo" email@softsul.com
 *
 * Não pede senha: imprime o link de uso único (válido por 7 dias), montado
 * sobre config app.url, para a própria pessoa escolher a senha e cadastrar
 * o autenticador. Os demais usuários se criam pela interface, em /usuario.
 * A conta nasce com softsul = 1 e o papel plataforma_admin, que enxerga
 * todos os destinos e trabalha na visão da plataforma.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auditoria.php';
require_once __DIR__ . '/../app/Convite.php';

if ($argc < 3) {
    fwrite(STDERR, "Uso: php scripts/criar_usuario.php \"Nome Completo\" email@dominio\n");
    exit(1);
}
$nome  = trim($argv[1]);
$email = mb_strtolower(trim($argv[2]));
if ($nome === '') {
    fwrite(STDERR, "Informe o nome completo.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "E-mail inválido.\n");
    exit(1);
}
$pdo = Database::conexao();
$existe = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$existe->execute([$email]);
if ($existe->fetchColumn()) {
    fwrite(STDERR, "Já existe um usuário com este e-mail.\n");
    exit(1);
}
$papel = (int) $pdo->query("SELECT id FROM papeis WHERE chave = 'plataforma_admin'")->fetchColumn();
if (!$papel) {
    fwrite(STDERR, "O papel plataforma_admin não existe. Rode php scripts/migrar.php antes.\n");
    exit(1);
}
$pdo->prepare('INSERT INTO usuarios (nome, email, softsul, papel_softsul_id, ativo, criado_em) VALUES (?, ?, 1, ?, 1, NOW())')
    ->execute([$nome, $email, $papel]);
$id = (int) $pdo->lastInsertId();
Auditoria::registrar('criou', 'usuarios', $id, 'primeiro administrador da plataforma, criado pela linha de comando');
echo "Usuário {$email} criado como Administrador da plataforma.\n";
echo "Link para definir a senha (vale 7 dias, uma vez só):\n" . Convite::criar($id, 'convite', null) . "\n";
