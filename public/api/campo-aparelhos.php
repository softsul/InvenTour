<?php
/**
 * A aba Aplicativo da Coleta de campo se atualizando sozinha
 * (assets/coleta-app.js): a tabela dos aparelhos e o que aconteceu com o
 * código gerado nesta sessão.
 * Softsul Software
 *
 *   GET /api/campo-aparelhos  {ok, assinatura, total, html, codigo: {estado, html}}
 *
 * A assinatura muda quando entra, sai ou envia um aparelho: a tela só troca
 * o HTML quando ela muda.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Esteira.php';
require_once __DIR__ . '/../../app/Campo.php';
require_once __DIR__ . '/../../app/AparelhosTela.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!Auth::usuarioOuNulo() || !Empresa::id()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
if (!Auth::pode('coleta.ver')) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
// A tela pergunta a cada 3 s enquanto há código esperando, e a cada 15 s depois.
Limite::porUsuario('campo-aparelhos', 60, 60);

$u = Auth::usuarioOuNulo();
$empresa = Empresa::atual() ?? [];
$csrf = Auth::tokenCsrf();
$daEquipe = Esteira::coordena();
$aparelhos = Campo::aparelhosDoDestino($daEquipe);
$estado = estadoDoCodigo();
$sessoes = historicoSincronizacao($daEquipe, (int) $u['id']);
$assinatura = md5(json_encode(array_map(fn ($a) => [$a['id'], $a['ultimo_uso_em'], $a['envios']], $aparelhos)) . json_encode(array_map(fn ($s) => [$s['fim'], count($s['itens']), $s['fotos']], $sessoes)));
$destacar = $estado && $estado['estado'] === 'usado' ? (int) $estado['id'] : null;

echo json_encode([
    'ok' => true,
    'assinatura' => $assinatura,
    'total' => count($aparelhos),
    'html' => cartaoAparelhos($aparelhos, $daEquipe, (int) $u['id'], $csrf, $destacar),
    'historico' => cartaoHistorico($sessoes, $daEquipe),
    'codigo' => $estado && $estado['estado'] !== 'pendente'
        ? ['estado' => $estado['estado'], 'html' => passoCodigo($estado['estado'], null, $estado['aparelho'], $csrf, $u, $empresa)]
        : ['estado' => $estado['estado'] ?? 'novo'],
], JSON_UNESCAPED_UNICODE);
