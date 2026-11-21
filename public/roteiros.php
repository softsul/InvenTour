<?php
/**
 * Roteiros do destino (etapa 5): os caminhos prontos que o portal mostra,
 * permanentes, de evento ou de um perfil de viajante, com os lugares do
 * inventário em ordem. O roteiro abre em modal (roteiro.php).
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/UmDia.php';

Auth::exigirLogin();
$empresa = Empresa::exigir();
Auth::exigirPermissao('roteiros.ver');
Auth::exigirModuloLigado('roteiros');
$pdo = Database::conexao();
$eid = (int) $empresa['id'];
$podeRegras = Auth::pode('roteiros.editar');

// Regras do "Um dia aqui": os tipos de lugar que valem em cada etapa do dia.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'regras_um_dia') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeRegras) {
        avisar('A página ficou aberta tempo demais, ou você não pode alterar roteiros.', 'ruim');
    } else {
        $antes = UmDia::regras($eid);
        $novas = [];
        foreach (UmDia::ETAPAS as $k => $_) {
            $novas[$k] = array_map('intval', (array) ($_POST['etapa_' . $k] ?? []));
        }
        UmDia::salvarRegras($eid, $novas, (int) Auth::usuarioOuNulo()['id']);
        Auditoria::registrar('alterou', 'ajustes', null, 'alterou as regras do Um dia aqui', $antes, array_filter($novas), $eid);
        avisar(empty($novas['manha']) ? 'Regras salvas. Sem tipo na manhã, o portal usa só os roteiros Um dia aqui.' : 'Regras do Um dia aqui salvas.');
    }
    redirecionar('/roteiros#um-dia');
}
$regras = UmDia::regras($eid);
$q = $pdo->prepare("SELECT t.id, COALESCE(CONCAT(pai.nome, ': ', t.nome), t.nome) AS nome, f.nome AS ficha, f.ordem, COUNT(i.id) AS n
                    FROM inventario_itens i JOIN inventario_tipos t ON t.id = i.tipo_id LEFT JOIN inventario_tipos pai ON pai.id = t.pai_id
                    JOIN inventario_fichas f ON f.id = i.ficha_id
                    WHERE i.empresa_id = ? AND i.situacao <> 'arquivada' GROUP BY t.id, nome, f.nome, f.ordem ORDER BY f.ordem, nome");
$q->execute([$eid]);
$tiposDeLugar = array_map(fn ($t) => ['id' => (int) $t['id'], 'nome' => (string) $t['nome'],
    'descricao' => $t['ficha'] . ' · ' . plural((int) $t['n'], 'lugar', 'lugares')], $q->fetchAll());
$q = $pdo->prepare('SELECT r.*, t.nome AS tribo_nome, (SELECT COUNT(*) FROM roteiro_itens ri WHERE ri.roteiro_id = r.id) AS paradas
                    FROM roteiros r LEFT JOIN tribos t ON t.id = r.tribo_id WHERE r.empresa_id = ? ORDER BY r.ordem, r.titulo');
$q->execute([$eid]);
$roteiros = $q->fetchAll();
$tipos = ['permanente' => 'Permanente', 'evento' => 'De evento', 'tribo' => 'Por perfil de viajante', 'um_dia' => 'Um dia aqui'];
paginaInicio('Roteiros', 'roteiros');
?>
<div class="cab-pag">
  <div><h1>Roteiros</h1>
    <p>Caminhos prontos para o visitante, com os lugares do inventário em ordem e uma dica em cada parada. Publicados, aparecem no portal com a rota no mapa.</p></div>
  <?php if (Auth::pode('roteiros.criar')): ?>
    <div class="acoes"><a class="botao" href="/roteiro" data-ficha="Novo roteiro" data-abre-novo><?= icone('mais') ?>Novo roteiro</a></div>
  <?php endif; ?>
</div>
<div class="cartao">
  <?php if (!$roteiros): ?>
    <div class="vazio"><?= icone('mapa') ?><p><strong>Nenhum roteiro ainda.</strong></p><p>Monte o primeiro: um dia no centro, a rota do café colonial, o roteiro tecnológico.</p></div>
  <?php else: ?>
  <div class="rolagem">
    <table class="dados">
      <thead><tr><th>Roteiro</th><th>Tipo</th><th class="num">Paradas</th><th>Situação</th><th class="acao"></th></tr></thead>
      <tbody>
      <?php foreach ($roteiros as $r): $url = endereco('roteiro', (int) $r['id'], (string) $r['titulo']); ?>
        <tr>
          <td class="elastica"><b><a href="<?= e($url) ?>" data-ficha="<?= e($r['titulo']) ?>"><?= e((string) $r['titulo']) ?></a></b>
            <?php if ($r['duracao']): ?><br><span class="nulo"><?= e((string) $r['duracao']) ?></span><?php endif; ?></td>
          <td><?= e($tipos[$r['tipo']] ?? $r['tipo']) ?><?= $r['tribo_nome'] ? ': ' . e((string) $r['tribo_nome']) : '' ?></td>
          <td class="num dado"><?= milhar((int) $r['paradas']) ?></td>
          <td><?= $r['publicado'] ? '<span class="pilula ok">No portal</span>' : '<span class="pilula nu">Rascunho</span>' ?></td>
          <td class="acao"><div class="acoes-linha"><a href="<?= e($url) ?>" data-ficha="<?= e($r['titulo']) ?>" title="Abrir" aria-label="Abrir <?= e($r['titulo']) ?>"><?= icone('lapis') ?></a></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<form method="post" action="/roteiros" class="cartao" id="um-dia">
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="regras_um_dia">
  <h2 class="titulo-secao">Um dia aqui, montado por regras</h2>
  <p class="ajuda">O portal sorteia o dia entre os roteiros do tipo Um dia aqui publicados e o dia montado por estas regras. Escolha que tipos de lugar valem em cada etapa: o dia começa num lugar da manhã e segue pelo mais perto que obedece a etapa seguinte e atende naquela hora (horário ou período de atendimento da ficha). Etapa sem tipo fica de fora; sem tipo na manhã, valem só os roteiros. Sem nenhum dos dois, o Um dia aqui sai do portal.</p>
  <div class="grade">
    <?php foreach (UmDia::ETAPAS as $k => [$hora, , $nomeEtapa]): ?>
      <div class="campo c-6"><label id="rotulo-etapa-<?= $k ?>"><?= e($nomeEtapa) ?> <span class="dado"><?= e($hora) ?></span></label>
        <?= multiEscolha('etapa_' . $k, 'rotulo-etapa-' . $k, $tiposDeLugar, $regras[$k] ?? [], 'Nenhum tipo: etapa fora do dia', !$podeRegras) ?></div>
    <?php endforeach; ?>
  </div>
  <?php if ($podeRegras): ?><div class="acoes"><button type="submit" class="botao"><?= icone('check') ?>Salvar as regras</button></div><?php endif; ?>
</form>
<?php paginaFim();
