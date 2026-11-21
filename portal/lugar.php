<?php
/**
 * Ficha pública de um lugar: /lugar/12-nome. Capa com a foto (ou a inicial
 * gigante), a linha de dados do inventário (código da ficha, plus code,
 * coordenada, aberto agora), galeria, descrição, para quem é (selos com o
 * que a ficha registra), experiências, eventos no local (EventCity), mapa,
 * contato e funcionamento. No celular, a barra fixa de ações (WhatsApp,
 * ligar, rota, guardar). Nunca mostra CNPJ nem dado técnico. Softsul Software.
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
$fotoGrande = Portal::foto($eid, $i['foto'], '');
// Legenda e crédito de cada foto, como vieram da ficha ("Fachada · Foto: Prefeitura").
$rotuloDaFoto = fn (array $f): string => trim((string) ($f['legenda'] ?? '') . (!empty($f['credito']) ? ' · Foto: ' . $f['credito'] : ''), ' ·');
$capaInfo = $i['fotos'][0] ?? null;
$end = trim(implode(', ', array_filter([trim(($i['logradouro'] ?? '') . ($i['numero'] ? ', ' . $i['numero'] : '')), $i['complemento'], $i['bairro']])));
// Telefone e WhatsApp gravados no padrão 55 + DDD + número (telefoneParaBanco).
$zap = (string) telefoneParaBanco((string) ($i['whatsapp'] ?? ''));
$zap = $zap !== '' && strlen($zap) <= 11 ? '55' . $zap : $zap;
$mensagem = 'Olá! Vi ' . $nome . ' no guia de turismo de ' . $destino['nome_fantasia'] . ' e gostaria de mais informações.';
$tel = (string) telefoneParaBanco((string) ($i['telefone'] ?? ''));
$coord = $i['latitude'] !== null ? $i['latitude'] . ',' . $i['longitude'] : null;
$rota = $coord ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($coord) : null;
$agora = Portal::agora($i['horario']);
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
$tipo = (string) ($i['tipo_nome'] ?: $i['ficha_nome']);

// Perto daqui: os lugares mais próximos de outros pilares (comer perto do hotel, dormir perto do atrativo).
$perto = [];
if ($coord) {
    foreach (Portal::itens($eid, null, [], 2000) as $o) {
        if ((int) $o['id'] === (int) $i['id'] || $o['latitude'] === null) {
            continue;
        }
        $o['_m'] = (int) round(Portal::distancia((float) $i['latitude'], (float) $i['longitude'], (float) $o['latitude'], (float) $o['longitude']));
        $perto[] = $o;
    }
    usort($perto, fn ($a, $b) => $a['_m'] <=> $b['_m']);
    $perto = array_slice($perto, 0, 4);
}

PortalLayout::inicio($destino, $nome, true, ['ativo' => (string) $pilar, 'imagem' => $foto, 'classe' => 'com-barra',
    'descricao' => $descricao !== '' ? mb_strimwidth($descricao, 0, 200, '...') : $tipo . ' em ' . $destino['nome_fantasia'] . '.']);
?>
<section class="ficha-capa<?= $fotoGrande ? '' : ' sem-capa' ?>">
  <?php if ($fotoGrande): ?><img src="<?= e($fotoGrande) ?>" alt="<?= e((string) ($capaInfo['legenda'] ?? '')) ?>"><?php if (!empty($capaInfo['credito'])): ?><span class="capa-credito">Foto: <?= e((string) $capaInfo['credito']) ?></span><?php endif; ?><?php else: ?><span class="inicial-gigante" aria-hidden="true"><?= e(PortalLayout::inicial($nome)) ?></span><?php endif; ?>
  <div class="ficha-capa-txt">
    <nav class="trilha"><a href="<?= e(Portal::url('/')) ?>">Início</a><?php if ($pilar): ?> / <a href="<?= e(Portal::url('/' . $pilar)) ?>"><?= e(Portal::PILARES[$pilar][0]) ?></a><?php endif; ?></nav>
    <span class="rotulo"><?= e($tipo) ?><?= $i['bairro'] ? ' · ' . e((string) $i['bairro']) : '' ?></span>
    <h1><?= e($nome) ?></h1>
    <div class="ficha-meta">
      <span>Ficha <?= e((string) $i['codigo']) ?></span>
      <?php if ($coord): ?><span><?= e(Portal::coordenada((float) $i['latitude'], (float) $i['longitude'])) ?></span><?php endif; ?>
      <?php if ($agora): ?><span class="estado<?= $agora['aberto'] ? ' aberto' : '' ?>"><?= e($agora['texto']) ?></span><?php endif; ?>
      <span class="dist" data-dist data-lat="<?= e((string) $i['latitude']) ?>" data-lng="<?= e((string) $i['longitude']) ?>"></span>
    </div>
  </div>
</section>

<div class="ficha-colunas">
  <div class="ficha-principal">
    <?php if (count($i['fotos']) > 1): ?>
    <div class="galeria" data-galeria>
      <?php foreach (array_slice($i['fotos'], 0, 9) as $f): ?>
        <a href="<?= e(Portal::foto($eid, $f['arquivo'], '')) ?>" data-foto data-legenda="<?= e($rotuloDaFoto($f)) ?>"><img src="<?= e(Portal::foto($eid, $f['arquivo'], 'p')) ?>" alt="<?= e((string) ($f['legenda'] ?: $nome)) ?>" loading="lazy"></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($descricao !== ''): ?><div class="texto"><?= nl2br(e($descricao)) ?></div><?php endif; ?>

    <?php if ($i['selos'] || $i['tribos']): ?>
    <h2>Para quem</h2>
    <ul class="selos-lista">
      <?php foreach ($i['selos'] as $s): ?><li><?= icone(PortalLayout::iconeDoSelo($s)) ?><span><b><?= e(Portal::SELOS_ACESSIBILIDADE[$s]) ?></b>
        <?php if (!empty($i['selos_det'][$s])): ?><small><?= e(implode('; ', $i['selos_det'][$s])) ?></small><?php endif; ?></span></li><?php endforeach; ?>
      <?php foreach ($i['tribos'] as $t): ?><li class="tribo"><?= icone((string) ($t['icone'] ?: 'coracao')) ?><span><b><?= e((string) $t['nome']) ?></b>
        <small><?= e((string) $t['facilidades']) ?></small></span></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($i['experiencias']): ?>
    <h2>Para viver aqui</h2>
    <?php foreach ($i['experiencias'] as $x): ?>
      <div class="xp-caixa"><b><?= e((string) $x['titulo']) ?></b>
        <p><?= e(mb_strimwidth((string) $x['narrativa'], 0, 260, '...')) ?></p>
        <a href="<?= e(Portal::url('/experiencias', ['x' => (int) $x['id']])) ?>">Ver a experiência</a></div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($eventos): ?>
    <h2>Próximos eventos aqui</h2>
    <ul class="lista-eventos">
      <?php foreach ($eventos as $ev): ?><li><a target="_blank" rel="noopener" href="<?= e((string) ($ev['url'] ?? '#')) ?>"><span class="data"><?= e(date('d/m', strtotime((string) ($ev['proximo_dia'] ?? $ev['data_inicio'])))) ?></span><b><?= e((string) $ev['titulo']) ?></b></a></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($coord): ?>
    <h2>Onde fica</h2>
    <?php // O mapa próprio do destino, centrado no lugar e com os vizinhos do "Perto daqui".
      $noMapa = [(int) $i['id'] => $i + ['pilar' => $pilar ?? 'o-que-fazer']];
      foreach ($perto as $o) { $noMapa[(int) $o['id']] ??= $o; } ?>
    <div class="mapa-caixa mapa-do-lugar" data-mapa><?= PortalLayout::mapa($eid, $noMapa, 'Mapa de ' . $nome . ' e arredores', ['filtro' => false, 'zoom' => 16, 'destaque' => (int) $i['id']]) ?></div>
    <?php endif; ?>

    <?php if ($perto): ?>
    <h2>Perto daqui</h2>
    <div class="grade-lugares"><?php foreach ($perto as $o) { echo PortalLayout::cartao($eid, $o, '<span class="dist-fixa">a ' . e(Portal::metros($o['_m'])) . '</span>'); } ?></div>
    <?php endif; ?>
  </div>

  <aside class="ficha-lado">
    <div class="caixa-contato">
      <button type="button" class="guardar grande" data-roteiro="<?= (int) $i['id'] ?>" aria-pressed="false"><?= icone('mais') ?><span>Guardar no meu roteiro</span></button>
      <?php if ($zap !== ''): ?><a target="_blank" rel="noopener" class="cta acento" href="https://wa.me/<?= e($zap) ?>?text=<?= e(rawurlencode($mensagem)) ?>"><?= icone('whatsapp') ?>Chamar no WhatsApp</a><?php endif; ?>
      <?php if ($tel !== ''): ?><a class="cta claro" href="tel:+<?= e($tel) ?>"><?= icone('contatos') ?>Ligar <?= e(formatarTelefone((string) $i['telefone'])) ?></a><?php endif; ?>
      <?php if ($rota): ?><a target="_blank" rel="noopener" class="cta claro" href="<?= e($rota) ?>"><?= icone('mapa') ?>Como chegar</a><?php endif; ?>
      <?php if ($site !== ''): ?><a target="_blank" rel="noopener nofollow" class="link-lado" href="<?= e($site) ?>"><?= icone('rede') ?>Site</a><?php endif; ?>
      <?php if ($insta !== ''): ?><a target="_blank" rel="noopener nofollow" class="link-lado" href="https://instagram.com/<?= e($insta) ?>"><?= icone('imagem') ?>@<?= e($insta) ?></a><?php endif; ?>
    </div>
    <dl class="ficha-dados">
      <?php if ($end !== ''): ?><dt>Endereço</dt><dd><?= e($end) ?></dd><?php endif; ?>
      <?php if ($i['referencia']): ?><dt>Referência</dt><dd><?= e((string) $i['referencia']) ?></dd><?php endif; ?>
      <?php if ($horario): ?><dt>Funciona</dt><dd><?= implode('<br>', array_map('e', $horario)) ?></dd><?php endif; ?>
      <?php if ($pagamentos): ?><dt>Pagamento</dt><dd><?= e(implode(', ', $pagamentos)) ?></dd><?php endif; ?>
      <?php if ($idiomas): ?><dt>Idiomas</dt><dd><?= e(implode(', ', $idiomas)) ?></dd><?php endif; ?>
      <?php if ($i['capacidade_uh']): ?><dt>Quartos</dt><dd><?= milhar((int) $i['capacidade_uh']) ?></dd><?php endif; ?>
      <?php if ($i['capacidade_leitos']): ?><dt>Leitos</dt><dd><?= milhar((int) $i['capacidade_leitos']) ?></dd><?php endif; ?>
      <?php if ($i['capacidade_assentos']): ?><dt>Lugares</dt><dd><?= milhar((int) $i['capacidade_assentos']) ?></dd><?php endif; ?>
      <?php if ($i['capacidade_publico']): ?><dt>Capacidade</dt><dd><?= plural((int) $i['capacidade_publico'], 'pessoa', 'pessoas') ?></dd><?php endif; ?>
      <?php if ($i['cadastur'] && (!$i['cadastur_validade'] || $i['cadastur_validade'] >= date('Y-m-d'))): ?><dt>Cadastur</dt><dd>Cadastro ativo no Ministério do Turismo</dd><?php endif; ?>
    </dl>
    <?php /* Etapa 6: o trade pede o acesso pelo painel (público, /reivindicar),
             só em destino ativo e para lugar homologado. */
    if (($destino['modo_operacao'] ?? '') === 'ativo' && in_array($i['situacao'] ?? 'homologada', ['homologada', 'desatualizada'], true)): ?>
      <a class="sou-responsavel" target="_blank" rel="noopener" href="<?= e(rtrim((string) ((require __DIR__ . '/../app/config.php')['app']['url'] ?? 'https://app.inventour.com.br'), '/') . '/reivindicar?i=' . (int) $i['id']) ?>"><?= icone('lapis') ?><span><b>É o responsável por este lugar?</b> Mantenha horário, contato e fotos em dia</span></a>
    <?php endif; ?>
  </aside>
</div>

<nav class="barra-acoes" aria-label="Ações rápidas">
  <?php if ($zap !== ''): ?><a target="_blank" rel="noopener" href="https://wa.me/<?= e($zap) ?>?text=<?= e(rawurlencode($mensagem)) ?>"><?= icone('whatsapp') ?>WhatsApp</a><?php endif; ?>
  <?php if ($tel !== ''): ?><a href="tel:+<?= e($tel) ?>"><?= icone('contatos') ?>Ligar</a><?php endif; ?>
  <?php if ($rota): ?><a target="_blank" rel="noopener" href="<?= e($rota) ?>"><?= icone('mapa') ?>Rota</a><?php endif; ?>
  <button type="button" data-roteiro="<?= (int) $i['id'] ?>" aria-pressed="false"><?= icone('mais') ?>Guardar</button>
</nav>
<?php PortalLayout::fim();
