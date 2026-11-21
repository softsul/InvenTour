<?php
/**
 * Ficha pública de um lugar: /lugar/12-nome. Fotos (galeria), descrição,
 * contato (WhatsApp com a mensagem pronta, telefone, site, Instagram), mapa
 * e como chegar, funcionamento, selos de acessibilidade e de perfis de
 * viajante, experiências e os próximos eventos no local (EventCity, quando
 * a ponte responde). Nunca mostra CNPJ nem dado técnico. Softsul Software.
 */
declare(strict_types=1);
require __DIR__ . '/inc/abrir.php';
require_once __DIR__ . '/../app/EventCity.php';

$i = Portal::item($eid, (int) ($_GET['id'] ?? 0)) ?? naoAchou($destino, 'Este lugar não está no guia ou saiu do ar.');
$certo = '/lugar/' . (int) $i['id'] . '-' . Portal::slug((string) $i['nome']);
if (strtok((string) $_SERVER['REQUEST_URI'], '?') !== $certo) {
    header('Location: ' . Portal::url($certo), true, 301);
    exit;
}
$nome = (string) $i['nome'];
$pilar = Portal::pilarDe((int) $i['ficha_ordem']);
$foto = Portal::foto($eid, $i['foto'], 'm');
$end = trim(implode(', ', array_filter([trim(($i['logradouro'] ?? '') . ($i['numero'] ? ', ' . $i['numero'] : '')), $i['complemento'], $i['bairro']])));
$zap = preg_replace('/\D/', '', (string) ($i['whatsapp'] ?? ''));
$zap = $zap !== '' && strlen($zap) <= 11 ? '55' . $zap : $zap;
$mensagem = 'Olá! Vi ' . $nome . ' no guia de turismo de ' . $destino['nome_fantasia'] . ' e gostaria de mais informações.';
$tel = preg_replace('/\D/', '', (string) ($i['telefone'] ?? ''));
$coord = $i['latitude'] !== null ? $i['latitude'] . ',' . $i['longitude'] : null;
$horario = Portal::horario($i['horario']);
$pagamentos = array_filter((array) (json_decode((string) $i['formas_pagamento'], true) ?: []), 'is_string');
$idiomas = array_filter((array) (json_decode((string) $i['idiomas'], true) ?: []), 'is_string');
$eventos = array_slice(EventCity::eventosDoLocal($eid, (int) $i['id']), 0, 6);
$descricao = trim((string) $i['descricao_promocional']);
$site = (string) ($i['site'] ?? '');
if ($site !== '' && !preg_match('#^https?://#i', $site)) {
    $site = 'https://' . $site;
}
$insta = ltrim(preg_replace('#^(https?://)?(www\.)?instagram\.com/#i', '', (string) ($i['instagram'] ?? '')), '@/');

PortalLayout::inicio($destino, $nome, true, ['ativo' => (string) $pilar, 'imagem' => $foto,
    'descricao' => $descricao !== '' ? mb_strimwidth($descricao, 0, 200, '...') : ($i['tipo_nome'] ?: $i['ficha_nome']) . ' em ' . $destino['nome_fantasia'] . '.']);
?>
<nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a> ›
  <?php if ($pilar): ?><a href="<?= e(Portal::url('/' . $pilar)) ?>"><?= e(Portal::PILARES[$pilar][0]) ?></a> › <?php endif; ?><span><?= e($nome) ?></span></nav>

<div class="ficha-topo">
  <div>
    <h1><?= e($nome) ?></h1>
    <p class="lead"><?= e((string) ($i['tipo_nome'] ?: $i['ficha_nome'])) ?><?= $i['bairro'] ? ' · ' . e((string) $i['bairro']) : '' ?></p>
  </div>
  <button type="button" class="guardar grande" data-roteiro="<?= (int) $i['id'] ?>" aria-pressed="false"><?= icone('mais') ?><span>Guardar no meu roteiro</span></button>
</div>

<?php if ($i['fotos']): ?>
<div class="galeria" data-galeria>
  <?php foreach (array_slice($i['fotos'], 0, 9) as $k => $f): ?>
    <a href="<?= e(Portal::foto($eid, $f['arquivo'], '')) ?>" data-foto class="<?= $k === 0 ? 'principal' : '' ?>"><img src="<?= e(Portal::foto($eid, $f['arquivo'], $k === 0 ? 'm' : 'p')) ?>" alt="<?= e((string) ($f['legenda'] ?: $nome)) ?>" loading="lazy"></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="ficha-colunas">
  <div class="ficha-principal">
    <?php if ($descricao !== ''): ?><div class="texto"><?= nl2br(e($descricao)) ?></div><?php endif; ?>

    <?php if ($i['selos'] || $i['tribos']): ?>
    <h2>Para quem</h2>
    <ul class="selos-lista">
      <?php foreach ($i['selos'] as $s): ?><li><?= icone($s === 'cadeirante' ? 'cadeirante' : ($s === 'auditiva' ? 'libras' : ($s === 'visual' ? 'olho' : ($s === 'vaga' ? 'carro' : 'pessoas')))) ?><?= e(Portal::SELOS_ACESSIBILIDADE[$s]) ?></li><?php endforeach; ?>
      <?php foreach ($i['tribos'] as $t): ?><li class="tribo"><?= icone((string) ($t['icone'] ?: 'coracao')) ?><?= e((string) $t['nome']) ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($i['experiencias']): ?>
    <h2>Experiências aqui</h2>
    <?php foreach ($i['experiencias'] as $x): ?>
      <div class="xp-caixa"><b><?= e((string) $x['titulo']) ?></b>
        <p><?= e(mb_strimwidth((string) $x['narrativa'], 0, 260, '...')) ?></p>
        <a href="<?= e(Portal::url('/experiencias', ['x' => (int) $x['id']])) ?>">Ver a experiência</a></div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($eventos): ?>
    <h2>Próximos eventos aqui</h2>
    <ul class="lista-eventos">
      <?php foreach ($eventos as $ev): ?><li><a href="<?= e((string) ($ev['url'] ?? '#')) ?>" rel="noopener"><span class="data"><?= e(date('d/m', strtotime((string) ($ev['proximo_dia'] ?? $ev['data_inicio'])))) ?></span><b><?= e((string) $ev['titulo']) ?></b></a></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($coord): ?>
    <h2>Onde fica</h2>
    <div class="mapa"><iframe src="https://www.google.com/maps?q=<?= e($coord) ?>&amp;z=16&amp;hl=pt-BR&amp;output=embed" title="Mapa de <?= e($nome) ?>" loading="lazy" referrerpolicy="no-referrer"></iframe></div>
    <?php endif; ?>
  </div>

  <aside class="ficha-lado">
    <div class="caixa-contato">
      <?php if ($zap !== ''): ?><a class="cta" href="https://wa.me/<?= e($zap) ?>?text=<?= e(rawurlencode($mensagem)) ?>" rel="noopener"><?= icone('whatsapp') ?>Chamar no WhatsApp</a><?php endif; ?>
      <?php if ($tel !== ''): ?><a class="cta claro" href="tel:+55<?= e($tel) ?>"><?= icone('contatos') ?>Ligar <?= e(formatarTelefone((string) $i['telefone'])) ?></a><?php endif; ?>
      <?php if ($coord): ?><a class="cta claro" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= e(rawurlencode($coord)) ?>" rel="noopener"><?= icone('mapa') ?>Como chegar</a><?php endif; ?>
      <?php if ($site !== ''): ?><a class="link-lado" href="<?= e($site) ?>" rel="noopener nofollow"><?= icone('rede') ?>Site</a><?php endif; ?>
      <?php if ($insta !== ''): ?><a class="link-lado" href="https://instagram.com/<?= e($insta) ?>" rel="noopener nofollow"><?= icone('imagem') ?>@<?= e($insta) ?></a><?php endif; ?>
    </div>
    <dl class="ficha-dados">
      <?php if ($end !== ''): ?><dt>Endereço</dt><dd><?= e($end) ?></dd><?php endif; ?>
      <?php if ($i['referencia']): ?><dt>Referência</dt><dd><?= e((string) $i['referencia']) ?></dd><?php endif; ?>
      <?php if ($horario): ?><dt>Funcionamento</dt><dd><?= implode('<br>', array_map('e', $horario)) ?></dd><?php endif; ?>
      <?php if ($pagamentos): ?><dt>Pagamento</dt><dd><?= e(implode(', ', $pagamentos)) ?></dd><?php endif; ?>
      <?php if ($idiomas): ?><dt>Idiomas</dt><dd><?= e(implode(', ', $idiomas)) ?></dd><?php endif; ?>
      <?php if ($i['capacidade_uh']): ?><dt>Quartos</dt><dd><?= milhar((int) $i['capacidade_uh']) ?></dd><?php endif; ?>
      <?php if ($i['capacidade_leitos']): ?><dt>Leitos</dt><dd><?= milhar((int) $i['capacidade_leitos']) ?></dd><?php endif; ?>
      <?php if ($i['capacidade_assentos']): ?><dt>Lugares</dt><dd><?= milhar((int) $i['capacidade_assentos']) ?></dd><?php endif; ?>
      <?php if ($i['cadastur'] && (!$i['cadastur_validade'] || $i['cadastur_validade'] >= date('Y-m-d'))): ?><dt>Cadastur</dt><dd>Cadastro ativo no Ministério do Turismo</dd><?php endif; ?>
    </dl>
  </aside>
</div>
<?php PortalLayout::fim();
