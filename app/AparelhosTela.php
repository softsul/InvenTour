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
