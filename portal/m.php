<?php
/**
 * Contador de métricas: POST /m/<ação>/<lugar>, enviado pelo portal.js
 * (navigator.sendBeacon). Só incrementa um contador do dia e devolve 204.
 * O mesmo do EventCity (portal/m.php de lá), com os lugares no lugar dos
 * eventos.
 * Softsul Software
 *
 * Sem cookie, sem IP gravado, sem identificar ninguém (LGPD sem aviso).
 * Robô que não roda JavaScript não conta. Ação fora da lista e lugar que
 * não é público neste destino não gravam nada. Limite por IP contra
 * enchimento artificial. Quem usa o painel (cookie it_painel, gravado pelo
 * app em .inventour.com.br) é equipe e não conta, como no Google Analytics;
 * em domínio próprio o cookie não chega, então ali a equipe ainda conta.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Limite.php';
require_once __DIR__ . '/../app/Tenant.php';

const ACOES_METRICA = [
    'ver'      => 'visualizacoes',
    'rota'     => 'cliques_rota',
    'whatsapp' => 'cliques_whatsapp',
    'ligar'    => 'cliques_ligar',
    'site'     => 'cliques_site',
    'guardar'  => 'guardados',
];

header('Cache-Control: no-store');
$acao = (string) ($_GET['acao'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($acao !== 'pagina' && !isset(ACOES_METRICA[$acao]))) {
    http_response_code(404);
    exit;
}
$destino = Tenant::atual();
$aberto = $destino && ($destino['modo_operacao'] === 'ativo'
    || ($destino['modo_operacao'] === 'avaliacao' && (string) $destino['avaliacao_ate'] >= date('Y-m-d')));
if (!$aberto || ($_COOKIE['it_painel'] ?? '') === '1') {
    http_response_code(204);
    exit;
}
Limite::porIp('metricas', 240, 60);
$empresaId = (int) $destino['id'];
$pdo = Database::conexaoPortal();
// Contador nunca derruba nada: sem a permissão do banco (GRANT ainda não
// aplicado) ou com o banco ocupado, só deixa de contar.
try {
if ($acao === 'pagina') {
    $pdo->prepare('INSERT INTO metricas_portal (empresa_id, dia, paginas) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE paginas = paginas + 1')
        ->execute([$empresaId]);
} else {
    $q = $pdo->prepare("SELECT 1 FROM inventario_itens WHERE id = ? AND empresa_id = ? AND situacao IN ('homologada', 'desatualizada')");
    $q->execute([$id, $empresaId]);
    if ($q->fetchColumn()) {
        $coluna = ACOES_METRICA[$acao];
        $pdo->prepare("INSERT INTO metricas_lugares (item_id, dia, empresa_id, {$coluna}) VALUES (?, CURDATE(), ?, 1)
                       ON DUPLICATE KEY UPDATE {$coluna} = {$coluna} + 1")->execute([$id, $empresaId]);
    }
}
} catch (Throwable $e) {
    error_log('metricas: ' . $e->getMessage());
}
http_response_code(204);
