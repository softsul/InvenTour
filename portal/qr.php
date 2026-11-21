<?php
/**
 * QR de uma ficha do caderno oficial: /qr/<código do caderno>/<item>.
 * Softsul Software
 *
 * Conta a leitura (por dia, sem IP e sem cookie: é o funil comercial) e
 * mostra a ficha. Com o destino licenciado ou em avaliação, a ficha viva
 * (os dados públicos de hoje, sem reimprimir o caderno). No modo projeto,
 * a prévia bloqueada: foto e título, o resto desfocado (sem o dado real no
 * HTML) e o convite da avaliação. O item 0 é o QR da capa, que leva à
 * verificação do caderno.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Limite.php';
require_once __DIR__ . '/../app/Tenant.php';
require_once __DIR__ . '/../app/PortalLayout.php';
require_once __DIR__ . '/../app/Imagem.php';
require_once __DIR__ . '/../app/Comercial.php';

$destino = Tenant::exigir();
$eid = (int) $destino['id'];
$codigo = strtoupper((string) ($_GET['c'] ?? ''));
$itemId = (int) ($_GET['i'] ?? 0);
$pdo = Database::conexaoPortal();
$q = $pdo->prepare("SELECT id, codigo FROM cadernos WHERE codigo = ? AND empresa_id = ? AND situacao = 'pronto'");
$q->execute([$codigo, $eid]);
$caderno = $q->fetch();
if (!$caderno) {
    http_response_code(404);
    PortalLayout::inicio($destino, 'QR não encontrado', false);
    echo '<p class="sobre">Caderno oficial</p><h1>Este QR não é de um caderno deste destino.</h1><p class="lead">Confira se o código foi lido inteiro.</p>';
    PortalLayout::fim();
    exit;
}
if ($itemId === 0) {
    header('Location: /verificar/' . $caderno['codigo'], true, 302);
    exit;
}
$q = $pdo->prepare('SELECT i.id, i.nome, i.situacao, i.logradouro, i.numero, i.bairro, i.telefone, i.whatsapp, i.site, i.instagram,
                           i.latitude, i.longitude, i.descricao_promocional, f.codigo AS ficha_codigo, f.nome AS ficha_nome, t.nome AS tipo_nome
                    FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id LEFT JOIN inventario_tipos t ON t.id = i.tipo_id
                    WHERE i.id = ? AND i.empresa_id = ?');
$q->execute([$itemId, $eid]);
$item = $q->fetch();
if (!$item) {
    http_response_code(404);
    PortalLayout::inicio($destino, 'Ficha não encontrada', false);
    echo '<p class="sobre">Caderno oficial</p><h1>Esta ficha saiu do inventário.</h1>';
    PortalLayout::fim();
    exit;
}
// A leitura do dia, sem IP e sem cookie.
$pdo->prepare('INSERT INTO caderno_leituras (empresa_id, caderno_id, item_id, dia, leituras) VALUES (?, ?, ?, CURDATE(), 1)
               ON DUPLICATE KEY UPDATE leituras = leituras + 1')->execute([$eid, (int) $caderno['id'], $itemId]);

$q = $pdo->prepare('SELECT arquivo FROM inventario_midias WHERE item_id = ? AND empresa_id = ? AND experiencia_id IS NULL ORDER BY capa DESC, ordem, id LIMIT 1');
$q->execute([$itemId, $eid]);
$foto = $q->fetchColumn();
$fotoUrl = $foto ? '/midia/inventario/' . $eid . '/' . rawurlencode(Imagem::nomeMiniatura((string) $foto, 'm')) : null;
$aberto = $destino['modo_operacao'] === 'ativo'
    || ($destino['modo_operacao'] === 'avaliacao' && (string) $destino['avaliacao_ate'] >= date('Y-m-d'));
$homologado = in_array($item['situacao'], ['homologada', 'desatualizada'], true);
$sub = $item['ficha_codigo'] . ' ' . $item['ficha_nome'] . ($item['tipo_nome'] ? ' · ' . $item['tipo_nome'] : '');
header('Cache-Control: no-store');

PortalLayout::inicio($destino, (string) $item['nome'], false);
?>
  <p class="sobre">Inventário oficial · <?= e((string) $destino['nome_fantasia']) ?></p>
  <h1><?= e((string) $item['nome']) ?></h1>
  <p class="lead"><?= e($sub) ?></p>
  <?php if ($fotoUrl): ?><img class="ficha-foto" src="<?= e($fotoUrl) ?>" alt="<?= e((string) $item['nome']) ?>"><?php endif; ?>
<?php if (!$aberto): $c = Comercial::paraOPortal(); ?>
  <div class="ficha-bloqueada" aria-hidden="true">
    <span></span><span></span><span class="curta"></span><span></span><span class="curta"></span>
  </div>
  <div class="convite">
    <p><?= e((string) $c['convite']) ?></p>
    <?php if ($c['whatsapp']): ?><a class="cta" href="https://wa.me/<?= e(preg_replace('/\D/', '', (string) $c['whatsapp'])) ?>">Falar pelo WhatsApp</a><?php endif; ?>
    <?php if ($c['email']): ?><a class="cta claro" href="mailto:<?= e((string) $c['email']) ?>">Escrever um e-mail</a><?php endif; ?>
  </div>
<?php elseif (!$homologado): ?>
  <p class="convite">Esta ficha está em atualização pela equipe do inventário. Volte em breve.</p>
<?php else:
    $end = trim(implode(', ', array_filter([trim(($item['logradouro'] ?? '') . ($item['numero'] ? ', ' . $item['numero'] : '')), $item['bairro']])));
    $tel = preg_replace('/\D/', '', (string) ($item['whatsapp'] ?: ''));
    ?>
  <?php if ($item['descricao_promocional']): ?><p><?= nl2br(e((string) $item['descricao_promocional'])) ?></p><?php endif; ?>
  <dl class="ficha-dados">
    <?php if ($end !== ''): ?><dt>Endereço</dt><dd><?= e($end) ?></dd><?php endif; ?>
    <?php if ($item['telefone']): ?><dt>Telefone</dt><dd><?= e((string) $item['telefone']) ?></dd><?php endif; ?>
    <?php if ($item['site']): ?><dt>Site</dt><dd><a href="<?= e((string) $item['site']) ?>" rel="noopener nofollow"><?= e((string) $item['site']) ?></a></dd><?php endif; ?>
  </dl>
  <p>
    <?php if ($tel !== ''): ?><a class="cta" href="https://wa.me/<?= e(strlen($tel) <= 11 ? '55' . $tel : $tel) ?>">WhatsApp</a><?php endif; ?>
    <?php if ($item['latitude'] !== null): ?><a class="cta claro" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e($item['latitude'] . ',' . $item['longitude']) ?>" rel="noopener">Como chegar</a><?php endif; ?>
  </p>
<?php endif; ?>
<?php PortalLayout::fim();
