<?php
/**
 * Consulta de CNPJ para o cadastro de destino.
 * Softsul Software
 *
 * Atrás de login e de permissão: aberto, viraria um consultor de CNPJ
 * hospedado e pago pela Softsul. Vale para a plataforma (cadastro do
 * destino) e para o gestor que edita os dados do próprio destino.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Cnpj.php';
require_once __DIR__ . '/../../app/funcoes.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): Consulta serviço externo, pago pela Softsul.
Limite::porUsuario('cnpj', 20, 60);
$podePlataforma = Auth::ehSoftsul() && (Auth::pode('destinos.criar') || Auth::pode('destinos.editar') || Auth::pode('artistas.criar') || Auth::pode('artistas.editar'));
// Gestor do destino (dados do destino) e quem cadastra organizador.
$podeDestino = Empresa::id() > 0 && (Auth::pode('configuracoes.editar') || Auth::pode('organizadores.criar') || Auth::pode('organizadores.editar'));
if (!Auth::usuarioOuNulo() || (!$podePlataforma && !$podeDestino)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão para consultar CNPJ.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$cnpj = (string) ($_GET['cnpj'] ?? '');
$r = Cnpj::consultar($cnpj);
if (!$r['ok']) {
    http_response_code(422);
    echo json_encode(['erro' => $r['erro']], JSON_UNESCAPED_UNICODE);
    exit;
}
// Já existe? Melhor avisar antes de a pessoa preencher tudo de novo. Quem é
// de destino só fica sabendo que existe, sem o link, que é da plataforma.
$s = Database::conexao()->prepare('SELECT id, nome AS razao_social, nome_fantasia FROM empresas WHERE cnpj = ?');
$s->execute([Cnpj::normalizar($cnpj)]);
$existente = $s->fetch() ?: null;
if ($existente && $existente['id'] === Empresa::id()) {
    // O próprio destino, na edição pelo gestor: não é duplicidade.
    $existente = null;
}
if ($existente) {
    if ($podePlataforma) {
        $existente['url'] = endereco('destino', (int) $existente['id'], $existente['nome_fantasia']);
    } else {
        // Quem é de um destino só fica sabendo que o CNPJ já é cliente da
        // plataforma, sem o nome de quem é: um destino não descobre nada
        // sobre outro por aqui (achado do scripts/conferir_escopo.php).
        $existente = ['id' => 0, 'razao_social' => '', 'nome_fantasia' => 'outro destino da plataforma', 'url' => '/painel'];
    }
}
echo json_encode(['fonte' => $r['fonte'], 'dados' => $r['dados'], 'existente' => $existente], JSON_UNESCAPED_UNICODE);
