<?php
/**
 * Os arquivos de uma versão do caderno: PDF (no visualizador), XLSX e CSV.
 * Softsul Software
 *
 * Atrás de login e só do destino atual. O PDF vai inline, para abrir no
 * visualizador do painel; as planilhas vão como anexo.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/Caderno.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('caderno.ver');
$tipo = (string) ($_GET['tipo'] ?? 'pdf');
// A foto da capa escolhida na Encadernação (a miniatura e a grande).
if ($tipo === 'capa') {
    $foto = Caderno::fotoDaCapa((int) $empresa['id']) ?? negarRegistro();
    if (!empty($_GET['mini'])) {
        require_once __DIR__ . '/../app/Imagem.php';
        $m = Imagem::miniaturas((string) file_get_contents($foto))['m'] ?? null;
        if ($m) {
            header('Content-Type: image/jpeg');
            header('Cache-Control: private, max-age=60');
            echo $m;
            exit;
        }
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=60');
    readfile($foto);
    exit;
}
if (!in_array($tipo, ['pdf', 'xlsx', 'csv'], true)) {
    negarRegistro();
}
if ($tipo !== 'pdf') {
    Auth::exigirPermissao('caderno.exportar');
}
$q = Database::conexao()->prepare("SELECT * FROM cadernos WHERE id = ? AND empresa_id = ? AND situacao = 'pronto'");
$q->execute([(int) ($_GET['id'] ?? 0), (int) $empresa['id']]);
$c = $q->fetch() ?: negarRegistro();
$arquivo = Caderno::arquivo($c, $tipo);
if (!is_file($arquivo)) {
    negarRegistro();
}
if ($tipo !== 'pdf') {
    Auditoria::registrar('exportou', 'cadernos', (int) $c['id'], 'baixou o caderno versão ' . $c['versao'] . ' em ' . strtoupper($tipo), null, null, (int) $empresa['id']);
}
$nome = Caderno::nomeParaBaixar($c, (string) $empresa['nome_fantasia'], $tipo);
header('Content-Type: ' . ['pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv; charset=utf-8'][$tipo]);
header('Content-Disposition: ' . ($tipo === 'pdf' ? 'inline' : 'attachment') . '; filename="' . $nome . '"');
header('Content-Length: ' . filesize($arquivo));
header('Cache-Control: private, no-store');
readfile($arquivo);
