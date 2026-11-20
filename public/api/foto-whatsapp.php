<?php
/**
 * Foto do perfil do WhatsApp, para o editor de foto (botão "Buscar no
 * WhatsApp" em Meus dados e na ficha de usuário).
 * Softsul Software
 *
 * Devolve a imagem sem gravar: ela entra no enquadramento e só vira a foto
 * se a pessoa usar e salvar a ficha. O número é sempre o salvo no cadastro,
 * nunca um vindo do navegador: senão daria para ver a foto de qualquer
 * número. Só a própria pessoa ou quem pode editar aquele usuário.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Usuarios.php';
require_once __DIR__ . '/../../app/Foto.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Cache-Control: no-store');
// Limite (app/Limite.php tem a tabela de números): consulta o WhatsApp do InvenTour.
Limite::porUsuario('foto-whatsapp', 10, 60);

function respostaFotoWhatsapp(int $codigo, string $erro): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => $erro], JSON_UNESCAPED_UNICODE);
    exit;
}

$eu = Auth::usuarioOuNulo();
if (!$eu || $_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::conferirCsrf($_POST['csrf'] ?? null)) {
    respostaFotoWhatsapp(403, 'A página ficou aberta tempo demais. Recarregue e tente de novo.');
}
$id = (int) ($_POST['id'] ?? 0);
if ($id === (int) $eu['id']) {
    $alvo = $eu;
} else {
    $alvo = $id ? Usuarios::noContexto($id) : null;
    if (!$alvo || !Auth::pode('usuarios.editar') || !Usuarios::podeMexer($alvo) || !Usuarios::podeMexerNaConta($alvo)) {
        respostaFotoWhatsapp(403, 'Sem permissão para mexer na foto desta pessoa.');
    }
}
$telefone = (string) ($alvo['telefone'] ?? '');
if ($telefone === '') {
    respostaFotoWhatsapp(422, 'Salve o WhatsApp na ficha antes de buscar a foto.');
}

$r = Foto::buscarNoWhatsapp($id, $telefone);
if ($r['resultado'] !== 'foto') {
    respostaFotoWhatsapp(422, match ($r['resultado']) {
        'sem_whatsapp' => 'Este número não tem WhatsApp.',
        'sem_foto'     => 'O número tem WhatsApp, mas a foto do perfil não está visível.',
        default        => 'Não consegui falar com o WhatsApp agora. Tente mais tarde.',
    });
}
$img = Foto::reduzir($r['img']);
header('Content-Type: ' . ($img['ext'] === 'jpg' ? 'image/jpeg' : 'image/' . $img['ext']));
echo $img['dados'];
