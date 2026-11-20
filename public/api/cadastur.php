<?php
/**
 * Situação de um CNPJ ou CPF no Cadastur, para a ficha do organizador.
 * Softsul Software
 *
 * GET doc: o CNPJ (14 caracteres) ou o CPF (11 dígitos). Consulta a cópia
 * local dos dados abertos do Ministério do Turismo (app/Cadastur.php), sem
 * ir a serviço externo, e devolve o registro de cada atividade com a
 * situação, a validade e se está vigente. A ficha usa isto ao sair do campo
 * do documento, para preencher o número do CADASTUR e mostrar a situação.
 *
 * Só para quem cadastra ou edita organizador, e com limite por usuário: a
 * base tem CPF de guia de turismo, e não pode virar consulta em massa.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Limite.php';
require_once __DIR__ . '/../../app/Cadastur.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// Uma consulta por saída de campo; 30 por minuto sobra para quem cadastra.
Limite::porUsuario('cadastur', 30, 60);

if (!Auth::usuarioOuNulo()) {
    http_response_code(401);
    header('X-Sessao: expirada');
    echo json_encode(['erro' => 'A sessão expirou. Entre de novo.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!Auth::pode('organizadores.criar') && !Auth::pode('organizadores.editar')) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$doc = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) ($_GET['doc'] ?? '')));
if (!in_array(strlen($doc), [11, 14], true)) {
    http_response_code(422);
    echo json_encode(['erro' => 'Informe um CNPJ ou um CPF completo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$registros = [];
$certificado = null;
foreach (Cadastur::consultar($doc) as $r) {
    $vigente = Cadastur::vigente($r);
    if ($vigente && $certificado === null && !empty($r['certificado'])) {
        $certificado = (string) $r['certificado'];
    }
    $registros[] = [
        'atividade' => (string) $r['atividade'],
        'situacao'  => Cadastur::situacao($r),
        'validade'  => $r['validade'] ? date('d/m/Y', strtotime((string) $r['validade'])) : null,
        'vigente'   => $vigente,
    ];
}
$base = Cadastur::base();
echo json_encode([
    'registros'   => $registros,
    'certificado' => $certificado,
    'base'        => $base ? (string) $base['recurso_nome'] : null,
], JSON_UNESCAPED_UNICODE);
