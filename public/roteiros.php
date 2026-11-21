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

// Blocos do "Um dia aqui": nome, hora de início, quantos lugares, minutos
// em cada um e os tipos de lugar que valem. Livres: cinco pontos numa manhã
// ou um lugar que toma o dia.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'blocos_um_dia') {
    if (!Auth::conferirCsrf($_POST['csrf'] ?? null) || !$podeRegras) {
        avisar('A página ficou aberta tempo demais, ou você não pode alterar roteiros.', 'ruim');
    } else {
        $antes = UmDia::blocos($eid);
        $enviados = array_values(array_filter((array) ($_POST['blocos'] ?? []), fn ($b) => is_array($b) && empty($b['tirar'])));
        $novos = UmDia::salvarBlocos($eid, $enviados, (int) Auth::usuarioOuNulo()['id']);
        Auditoria::registrar('alterou', 'ajustes', null, 'alterou os blocos do Um dia aqui', ['blocos' => $antes], ['blocos' => $novos], $eid);
        avisar($novos ? plural(count($novos), 'bloco salvo', 'blocos salvos') . ' no Um dia aqui.' : 'Sem blocos: o Um dia aqui usa só os roteiros do tipo Um dia aqui.');
    }
    redirecionar('/roteiros#um-dia');
}
$blocos = UmDia::blocos($eid);
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
  <input type="hidden" name="csrf" value="<?= e(Auth::tokenCsrf()) ?>"><input type="hidden" name="acao" value="blocos_um_dia">
  <h2 class="titulo-secao">Um dia aqui, montado por blocos</h2>
  <p class="ajuda">O portal sorteia o dia entre os roteiros do tipo Um dia aqui publicados e o dia montado por estes blocos. Cada bloco começa numa hora, visita quantos lugares você quiser, com o tempo que cada um pede, e aceita só os tipos de lugar escolhidos: por exemplo, cinco pontos turísticos às 09:00 com 40 min cada, almoço às 13:00, três museus às 14:30 com 50 min cada. O dia segue sempre pelo lugar mais perto que serve ao bloco e atende naquela hora. Sem blocos e sem roteiros, o Um dia aqui sai do portal.</p>
  <div class="blocos-dia">
    <?php $linhas = $blocos; if (count($linhas) < UmDia::MAX_BLOCOS) { $linhas[] = ['nome' => '', 'hora' => '', 'lugares' => 1, 'minutos' => 60, 'tipos' => [], 'novo' => true]; } ?>
    <?php foreach ($linhas as $n => $b): $novo = !empty($b['novo']); ?>
      <div class="bloco-dia">
        <div class="bloco-dia-cab"><b><?= $novo ? 'Novo bloco' : 'Bloco ' . ($n + 1) ?></b>
          <?php if (!$novo && $podeRegras): ?><label class="check"><input type="checkbox" name="blocos[<?= $n ?>][tirar]" value="1"><i></i>Tirar este bloco</label><?php endif; ?></div>
        <div class="grade">
          <div class="campo c-4"><label for="bloco-nome-<?= $n ?>">Nome no portal</label>
            <div class="entrada"><input type="text" id="bloco-nome-<?= $n ?>" name="blocos[<?= $n ?>][nome]" maxlength="60" placeholder="Pontos turísticos do centro" value="<?= e((string) $b['nome']) ?>"<?= $podeRegras ? '' : ' readonly' ?>></div></div>
          <div class="campo c-2"><label for="bloco-hora-<?= $n ?>">Começa às</label>
            <div class="entrada"><input type="text" id="bloco-hora-<?= $n ?>" name="blocos[<?= $n ?>][hora]" data-hora inputmode="numeric" maxlength="5" placeholder="00:00" autocomplete="off" value="<?= e((string) $b['hora']) ?>"<?= $podeRegras ? '' : ' readonly' ?>></div></div>
          <div class="campo c-3"><label for="bloco-lugares-<?= $n ?>">Quantos lugares</label>
            <div class="entrada"><input type="number" id="bloco-lugares-<?= $n ?>" name="blocos[<?= $n ?>][lugares]" min="1" max="<?= UmDia::MAX_LUGARES ?>" inputmode="numeric" value="<?= (int) $b['lugares'] ?>"<?= $podeRegras ? '' : ' readonly' ?>></div></div>
          <div class="campo c-3"><label for="bloco-min-<?= $n ?>">Tempo em cada um</label>
            <div class="entrada com-sufixo"><input type="number" id="bloco-min-<?= $n ?>" name="blocos[<?= $n ?>][minutos]" min="5" max="600" step="5" inputmode="numeric" value="<?= (int) $b['minutos'] ?>"<?= $podeRegras ? '' : ' readonly' ?>><span class="sufixo">min</span></div></div>
          <div class="campo c-12"><label id="bloco-tipos-<?= $n ?>">Tipos de lugar</label>
            <?= multiEscolha('blocos[' . $n . '][tipos]', 'bloco-tipos-' . $n, $tiposDeLugar, $b['tipos'], 'Escolha os tipos de lugar deste bloco', !$podeRegras) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($podeRegras): ?><div class="cartao-rodape"><button type="submit" class="botao"><?= icone('check') ?>Salvar os blocos</button>
    <span class="ajuda">Para mais um bloco, preencha o Novo bloco e salve: aparece outro em branco.</span></div><?php endif; ?>
</form>
<?php paginaFim();
