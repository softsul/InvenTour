<?php
/**
 * Experiências aprovadas pela curadoria, com os 4 Es; ?x=ID mostra uma em
 * destaque no alto. Softsul Software.
 */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';

$todas = Portal::experiencias($eid);
$escolhida = null;
foreach ($todas as $x) {
    if ((int) $x['id'] === (int) ($_GET['x'] ?? 0)) {
        $escolhida = $x;
    }
}
$faixas = ['gratuita' => 'Gratuita', 'ate_80' => 'Até R$ 80', 'de_81_a_200' => 'De R$ 81 a R$ 200', 'de_201_a_500' => 'De R$ 201 a R$ 500', 'acima_500' => 'Acima de R$ 500'];
$dims = ['entretenimento' => 'Entretenimento', 'educacional' => 'Aprender fazendo', 'escapismo' => 'Imersão', 'estetica' => 'Contemplação'];
$desenhar = function (array $x, bool $grande) use ($eid, $faixas, $dims): string {
    $f = Portal::foto($eid, $x['foto'] ?: $x['foto_item'], 'm');
    $d = json_decode((string) $x['dimensoes'], true) ?: [];
    $h = '<article class="xp' . ($grande ? ' destaque' : '') . '" id="x' . (int) $x['id'] . '">' . ($f ? '<img src="' . e($f) . '" alt="" loading="lazy">' : '')
        . '<div class="xp-txt"><h2>' . e((string) $x['titulo']) . '</h2><p class="xp-onde"><a href="' . e(Portal::url('/lugar/' . (int) $x['item_id'] . '-' . Portal::slug((string) $x['item_nome']))) . '">' . e((string) $x['item_nome']) . '</a></p>'
        . '<p class="xp-meta">' . ($x['duracao_minutos'] ? (int) $x['duracao_minutos'] . ' min · ' : '') . 'grupo de ' . (int) $x['pessoas_min'] . ($x['pessoas_max'] ? ' a ' . (int) $x['pessoas_max'] : '') . ' pessoas · '
        . e($faixas[$x['faixa_valor']] ?? '') . ($x['agendamento'] ? ' · com agendamento' : '') . '</p>'
        . '<p>' . nl2br(e($grande ? (string) $x['narrativa'] : mb_strimwidth((string) $x['narrativa'], 0, 220, '...'))) . '</p>';
    if ($grande && $d) {
        $h .= '<dl class="ficha-dados">';
        foreach ($d as $k => $t) {
            $h .= '<dt>' . e($dims[$k] ?? $k) . '</dt><dd>' . e((string) $t) . '</dd>';
        }
        $h .= '</dl>';
    }
    if (!$grande) {
        $h .= '<a href="' . e(Portal::url('/experiencias', ['x' => (int) $x['id']])) . '#x' . (int) $x['id'] . '">Ver mais</a>';
    }
    return $h . '</div></article>';
};
PortalLayout::inicio($destino, $escolhida ? (string) $escolhida['titulo'] : 'Experiências', true, ['ativo' => 'experiencias',
    'imagem' => $escolhida ? Portal::foto($eid, $escolhida['foto'] ?: $escolhida['foto_item'], 'm') : null,
    'descricao' => 'Vivências para fazer em ' . $destino['nome_fantasia'] . ': colheita, oficina, degustação e visita técnica.']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> › <span>Experiências</span></nav>
<h1>Experiências</h1>
<p class="lead">Vivências que os lugares de <?= e((string) $destino['nome_fantasia']) ?> oferecem, aprovadas pela curadoria do destino.</p>
<?php if ($escolhida) { echo $desenhar($escolhida, true); } ?>
<?php if (!$todas): ?><p class="aviso-vazio">As experiências aparecem aqui depois da curadoria do destino.</p><?php endif; ?>
<div class="lista-xp"><?php foreach ($todas as $x) { if (!$escolhida || (int) $x['id'] !== (int) $escolhida['id']) { echo $desenhar($x, false); } } ?></div>
<?php PortalLayout::fim();
