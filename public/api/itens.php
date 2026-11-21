<?php
/**
 * Busca de item do inventário para o campo de escolha (seletorBuscavel).
 * Softsul Software
 *
 * Usada na experiência nova aberta pela tela Experiências, onde ainda não
 * há item escolhido. GET q: pelo menos duas letras do nome, nome popular,
 * razão social, bairro ou rua. Fica de fora a ficha do município (não tem
 * experiência) e o que está arquivado. Devolve até 20, do destino atual.
 *
 * O texto vai pronto para HTML em "nome" e "extra" e cru em "texto".
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/Limite.php';

header('Content-Type: application/json; charset=utf-8');
// Limite (app/Limite.php tem a tabela de números): busca ao vivo do item.
Limite::porUsuario('itens', 120, 60);
header('Cache-Control: no-store');
if (!Auth::usuarioOuNulo() || !Auth::pode('inventario.ver') || !Empresa::id()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Entre de novo.']);
    exit;
}
$busca = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($busca) < 2) {
    echo '[]';
    exit;
}
[$sql, $args] = Lista::porPalavras($busca, ['i.nome', 'i.nome_popular', 'i.razao_social', 'i.bairro', 'i.logradouro']);
$q = Database::conexao()->prepare(
    "SELECT i.id, i.nome, i.bairro, f.codigo, f.nome AS ficha_nome
     FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
     WHERE i.empresa_id = ? AND i.situacao <> 'arquivada' AND f.forma <> 'municipio' AND {$sql}
     ORDER BY i.nome LIKE ? DESC, i.nome LIMIT 20"
);
$q->execute([Empresa::id(), ...$args, $busca . '%']);
echo json_encode(array_map(fn ($i) => [
    'id'    => (int) $i['id'],
    'nome'  => e((string) $i['nome']),
    'extra' => e($i['codigo'] . ' ' . $i['ficha_nome'] . ($i['bairro'] ? ' · ' . $i['bairro'] : '')),
    'texto' => (string) $i['nome'],
    'iniciais' => avatarIniciais((string) $i['nome'], 'avatar'),
], $q->fetchAll()), JSON_UNESCAPED_UNICODE);
