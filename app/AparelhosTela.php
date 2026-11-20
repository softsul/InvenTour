<?php
/**
 * A aba Aplicativo da Coleta de campo: o passo do código e a tabela dos
 * aparelhos conectados.
 * Softsul Software
 *
 * Desenhados aqui para a tela (public/coleta.php) e para a atualização
 * sozinha (public/api/campo-aparelhos.php, chamada pelo
 * assets/coleta-app.js): quando o celular usa o código, o código some, o
 * aparelho aparece na tabela e o passo mostra quem conectou, sem recarregar.
 */
declare(strict_types=1);

/**
 * O passo 2. $estado: 'novo' (sem código), 'pendente' (mostra o código),
 * 'usado' (conectou: $aparelho diz qual) ou 'vencido'.
 */
function passoCodigo(string $estado, ?string $codigo, ?string $aparelho, string $csrf, array $u, array $empresa): string
{
    $botao = '<form method="post" action="/coleta#app"><input type="hidden" name="csrf" value="' . e($csrf) . '">'
           . '<button type="submit" name="acao" value="codigo" class="botao%s">' . icone('chave') . '%s</button></form>';
    return match ($estado) {
        'pendente' => '<b class="app-codigo dado" id="app-codigo">' . e(substr((string) $codigo, 0, 4) . ' ' . substr((string) $codigo, 4)) . '</b>'
            . '<span class="nulo app-esperando"><i class="pulso"></i>Esperando o aparelho. Vale 15 minutos e uma vez só.</span>',
        'usado' => '<p class="aviso bom app-conectou">' . icone('check') . '<span><b>Conectado:</b> ' . e($aparelho ?: 'aparelho') . '. O código já foi usado e não vale mais.</span></p>'
            . sprintf($botao, ' claro', 'Gerar código para outro aparelho'),
        'vencido' => '<p class="aviso atencao">' . icone('alerta') . '<span>O código venceu sem ser usado.</span></p>'
            . sprintf($botao, '', 'Gerar outro código'),
        default => '<p>O código liga o aparelho a você (<b>' . e((string) $u['nome']) . '</b>) em ' . e((string) $empresa['nome_fantasia']) . ', sem senha no celular.</p>'
            . sprintf($botao, '', 'Gerar código de conexão'),
    };
}

/** O cartão dos aparelhos conectados (cabeçalho, tabela ou vazio, ajuda). */
function cartaoAparelhos(array $aparelhos, bool $daEquipe, int $uid, string $csrf, ?int $destacar = null): string
{
    ob_start();
    ?>
    <div class="cab-cartao"><h2>Aparelhos conectados<?= $daEquipe ? ' da equipe' : '' ?> (<?= count($aparelhos) ?>)</h2>
      <span class="nota app-ao-vivo"><i class="pulso"></i>atualiza sozinho</span></div>
    <?php if (!$aparelhos): ?>
      <div class="vazio"><?= icone('digital') ?><p><strong>Nenhum aparelho conectado.</strong></p><p>Siga os três passos acima no celular de quem vai a campo.</p></div>
    <?php else: ?>
    <div class="rolagem">
      <table class="dados">
        <thead><tr><?= $daEquipe ? '<th>Pessoa</th>' : '' ?><th>Aparelho</th><th>Conectado em</th><th>Último uso</th><th>Envios</th><th class="acao"></th></tr></thead>
        <tbody>
        <?php foreach ($aparelhos as $a): ?>
          <tr<?= $destacar === (int) $a['id'] ? ' class="recem-chegado"' : '' ?>>
            <?php if ($daEquipe): ?><td class="elastica"><span class="cli"><?= avatarIniciais((string) $a['pessoa'], 'avatar') ?><span class="txt"><b><?= e((string) $a['pessoa']) ?></b><span><?= (int) $a['usuario_id'] === $uid ? 'você' : 'equipe' ?></span></span></span></td><?php endif; ?>
            <td<?= $daEquipe ? '' : ' class="elastica"' ?>><?= e((string) ($a['nome'] ?: 'Aparelho')) ?></td>
            <td class="dado"><?= e(dataBr((string) $a['conectado_em'], 'd/m/Y H:i')) ?></td>
            <td class="dado"><?= $a['ultimo_uso_em'] ? e(dataBr((string) $a['ultimo_uso_em'], 'd/m/Y H:i')) : '<span class="nulo">-</span>' ?></td>
            <td class="dado"><?= (int) $a['envios'] ?></td>
            <td class="acao"><div class="acoes-linha">
              <form method="post" action="/coleta#app"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button type="submit" name="acao" value="desconectar" title="Desconectar" aria-label="Desconectar <?= e((string) ($a['nome'] ?: 'aparelho')) ?>"
                  data-confirmar="Desconectar este aparelho? O que estiver só nele não sobe mais."><?= icone('sair') ?></button></form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <p class="ajuda">Celular perdido ou pessoa que saiu da equipe: desconecte aqui. Trocar a senha também desliga todos os aparelhos da pessoa.</p>
    <?php
    return (string) ob_get_clean();
}

/** O código gerado nesta sessão: 'pendente', 'usado' (com o aparelho e o id) ou 'vencido'; null se não há. */
function estadoDoCodigo(): ?array
{
    $id = (int) ($_SESSION['campo_codigo_id'] ?? 0);
    if (!$id) {
        return null;
    }
    $q = Database::conexao()->prepare('SELECT id, nome, token_hash IS NOT NULL AS usado, codigo_expira > NOW() AS valendo, revogado_em
                                       FROM campo_aparelhos WHERE id = ? AND usuario_id = ? AND empresa_id = ?');
    $q->execute([$id, (int) Auth::usuarioOuNulo()['id'], Empresa::id()]);
    $r = $q->fetch();
    if (!$r) {
        return ['estado' => 'vencido', 'aparelho' => null, 'id' => $id];
    }
    if ((int) $r['usado']) {
        return ['estado' => 'usado', 'aparelho' => $r['nome'], 'id' => (int) $r['id']];
    }
    return ['estado' => (int) $r['valendo'] ? 'pendente' : 'vencido', 'aparelho' => null, 'id' => (int) $r['id']];
}

/**
 * Histórico de sincronização do destino, montado dos envios gravados em
 * inventario_coletas (um por passo: salvar, foto, enviar à conferência).
 * Envios seguidos da mesma pessoa e do mesmo aparelho, com menos de 10
 * minutos entre eles, formam uma sincronização. Últimos 30 dias.
 */
function historicoSincronizacao(bool $daEquipe, int $uid, int $limite = 30): array
{
    $sql = "SELECT c.tipo, c.item_id, c.usuario_id, c.aparelho_id, c.resultado, c.recebido_em, i.nome AS item_nome,
                   u.nome AS pessoa, a.nome AS aparelho
            FROM inventario_coletas c
            JOIN usuarios u ON u.id = c.usuario_id
            LEFT JOIN inventario_itens i ON i.id = c.item_id
            LEFT JOIN campo_aparelhos a ON a.id = c.aparelho_id
            WHERE c.empresa_id = ? AND c.recebido_em > NOW() - INTERVAL 30 DAY";
    $args = [Empresa::id()];
    if (!$daEquipe) {
        $sql .= ' AND c.usuario_id = ?';
        $args[] = $uid;
    }
    $q = Database::conexao()->prepare($sql . ' ORDER BY c.recebido_em DESC, c.id DESC LIMIT 1000');
    $q->execute($args);
    $sessoes = [];
    $atual = null;
    foreach ($q->fetchAll() as $r) {
        $t = strtotime((string) $r['recebido_em']);
        $chave = $r['usuario_id'] . '-' . ($r['aparelho_id'] ?? 'painel');
        if (!$atual || $atual['chave'] !== $chave || $atual['inicio'] - $t > 600) {
            if ($atual) {
                $sessoes[] = $atual;
                if (count($sessoes) >= $limite) {
                    $atual = null;
                    break;
                }
            }
            $atual = ['chave' => $chave, 'fim' => $t, 'inicio' => $t, 'pessoa' => $r['pessoa'], 'aparelho' => $r['aparelho'],
                      'pelo_app' => $r['aparelho_id'] !== null, 'itens' => [], 'fotos' => 0, 'erros' => 0];
        }
        $atual['inicio'] = $t;
        $id = (int) $r['item_id'];
        $item = $atual['itens'][$id] ?? ['nome' => $r['item_nome'] ?? 'Item excluído', 'salvou' => false, 'fotos' => 0, 'enviou' => false, 'erro' => null];
        $res = json_decode((string) $r['resultado'], true) ?: [];
        if ($r['tipo'] === 'foto') {
            $item['fotos']++;
            $atual['fotos']++;
        } elseif (empty($res['ok'])) {
            $item['erro'] = match ($res['motivo'] ?? '') {
                'situacao' => 'não aplicado: o item já tinha saído da coleta',
                'item' => 'não aplicado: item fora do escopo',
                default => 'não aplicado',
            };
            $atual['erros']++;
        } elseif (($res['situacao'] ?? '') === 'em_conferencia') {
            $item['enviou'] = true;
        } else {
            $item['salvou'] = true;
        }
        $atual['itens'][$id] = $item;
    }
    if ($atual) {
        $sessoes[] = $atual;
    }
    return $sessoes;
}

/** O cartão do histórico de sincronização da equipe. */
function cartaoHistorico(array $sessoes, bool $daEquipe): string
{
    ob_start();
    ?>
    <div class="cab-cartao"><h2>Histórico de sincronização<?= $daEquipe ? ' da equipe' : '' ?></h2><span class="nota">últimos 30 dias</span></div>
    <?php if (!$sessoes): ?>
      <div class="vazio"><?= icone('relogio') ?><p><strong>Nenhuma sincronização ainda.</strong></p><p>Quando alguém tocar em Sincronizar no app, o que subiu aparece aqui.</p></div>
    <?php else: ?>
    <ul class="hist-lista">
      <?php foreach ($sessoes as $s):
        $n = count($s['itens']);
        $resumo = plural($n, 'item', 'itens') . ($s['fotos'] ? ', ' . plural($s['fotos'], 'foto', 'fotos') : ''); ?>
        <li class="hist-sinc<?= $s['erros'] ? ' atencao' : '' ?>">
          <div class="hist-cab"><?= icone($s['erros'] ? 'alerta' : 'check') ?><b><?= e(date('d/m/Y H:i', (int) $s['fim'])) ?></b>
            <span><?= e((string) $s['pessoa']) ?> · <?= $s['pelo_app'] ? e((string) ($s['aparelho'] ?: 'app')) : 'painel ou aparelho antigo' ?></span>
            <span class="pilula <?= $s['erros'] ? 'at' : 'ok' ?>"><?= e($resumo) ?></span></div>
          <ul class="hist-itens">
            <?php foreach ($s['itens'] as $i):
              $partes = [];
              if ($i['salvou']) { $partes[] = 'dados gravados'; }
              if ($i['fotos']) { $partes[] = plural($i['fotos'], 'foto', 'fotos'); }
              if ($i['enviou']) { $partes[] = 'enviado à conferência'; } ?>
              <li<?= $i['erro'] ? ' class="com-erro"' : '' ?>><b><?= e((string) $i['nome']) ?></b><span><?= e(implode(' · ', $partes) ?: '-') ?></span>
                <?php if ($i['erro']): ?><span class="erro"><?= e((string) $i['erro']) ?></span><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php
    return (string) ob_get_clean();
}
