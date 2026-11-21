<?php
/**
 * Moldura do portal público do destino (etapa 5).
 * Softsul Software
 *
 * Sem login e sem cookie; HTML pronto no servidor, com título, descrição e
 * Open Graph por página (a prévia do link no WhatsApp e nas redes). A cor
 * do destino entra por /acento.css (a política de segurança não aceita
 * estilo embutido). Script só o do próprio portal (assets/portal.js): menu
 * no celular, Meu roteiro guardado no navegador e a galeria de fotos.
 */
declare(strict_types=1);
require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/Portal.php';

final class PortalLayout
{
    private static ?array $destino = null;

    /**
     * @param array<string,mixed>|null $destino
     * @param array{descricao?:string,imagem?:?string,ativo?:string,canonico?:string} $og
     */
    public static function inicio(?array $destino, string $titulo, bool $indexar = true, array $og = []): void
    {
        self::$destino = $destino;
        $nome = $destino ? (string) $destino['nome_fantasia'] : 'InvenTour';
        $eid = $destino ? (int) $destino['id'] : 0;
        $previa = $eid && Portal::previa($eid);
        $indexar = $indexar && !$previa;
        $versao = fn (string $a) => (int) @filemtime(__DIR__ . '/../portal/assets/' . $a);
        $cor = $destino && preg_match('/^#[0-9A-F]{6}$/i', (string) ($destino['cor_acento'] ?? '')) ? strtoupper(substr((string) $destino['cor_acento'], 1)) : '0E7C66';
        $tituloPagina = $titulo === $nome ? $nome . ' · Turismo oficial' : $titulo . ' · ' . $nome;
        $descricao = (string) ($og['descricao'] ?? ('Onde dormir, onde comer, o que fazer e roteiros em ' . $nome . ': o guia oficial do destino.'));
        $host = 'https://' . ($_SERVER['HTTP_HOST'] ?? '');
        $canonico = $host . strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
        $ativo = (string) ($og['ativo'] ?? '');
        ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($tituloPagina) ?></title>
<meta name="description" content="<?= e(mb_substr($descricao, 0, 300)) ?>">
<?php if (!$indexar): ?><meta name="robots" content="noindex"><?php else: ?><link rel="canonical" href="<?= e($canonico) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="<?= e($nome) ?> · Turismo oficial">
<meta property="og:title" content="<?= e($titulo) ?>">
<meta property="og:description" content="<?= e(mb_substr($descricao, 0, 300)) ?>">
<meta property="og:url" content="<?= e($canonico) ?>">
<?php if (!empty($og['imagem'])): ?><meta property="og:image" content="<?= e($host . $og['imagem']) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<meta name="theme-color" content="#<?= e($cor) ?>">
<link rel="icon" href="/assets/icone.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/icone-180.png">
<link rel="stylesheet" href="/assets/portal.css?v=<?= $versao('portal.css') ?>">
<link rel="stylesheet" href="/acento.css?c=<?= e($cor) ?>">
<script src="/assets/portal.js?v=<?= $versao('portal.js') ?>" defer></script>
</head>
<body>
<?php svgSprite(); ?>
<?php if ($previa): ?><div class="faixa-previa">Prévia interna: mostra também o que ainda não foi homologado. Não compartilhe este link.</div><?php endif; ?>
<?php if ($destino): ?>
<header class="topo">
  <div class="topo-linha">
    <a class="marca" href="<?= e(Portal::url('/')) ?>"><span class="marca-nome"><?= e($nome) ?></span><span class="marca-sub">Turismo oficial</span></a>
    <form class="busca-topo" method="get" action="/busca" role="search">
      <?php if ($previa): ?><input type="hidden" name="previa" value="<?= e((string) $_GET['previa']) ?>"><?php endif; ?>
      <span class="entrada"><?= icone('busca') ?><input type="search" name="q" placeholder="Buscar hotel, restaurante, atrativo" aria-label="Buscar no portal" value="<?= e((string) ($_GET['q'] ?? '')) ?>"></span>
    </form>
    <a class="meu-roteiro-link" href="<?= e(Portal::url('/meu-roteiro')) ?>" title="Meu roteiro"><?= icone('mapa') ?><span>Meu roteiro</span><b data-conta-roteiro hidden>0</b></a>
    <button type="button" class="abrir-menu-portal" aria-controls="menu-portal" aria-expanded="false" aria-label="Abrir o menu"><?= icone('menu') ?></button>
  </div>
  <nav class="menu-portal" id="menu-portal" aria-label="Seções">
    <?php foreach (self::secoes($eid) as $k => [$rotulo, $url]): ?>
      <a href="<?= e(Portal::url($url)) ?>"<?= $ativo === $k ? ' aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<?php endif; ?>
<main class="palco">
        <?php
    }

    /** As seções do menu (a Agenda só quando o EventCity responde). */
    public static function secoes(int $eid): array
    {
        $s = ['inicio' => ['Início', '/']];
        foreach (Portal::PILARES as $k => $p) {
            if ($k !== 'servicos') {
                $s[$k] = [$p[0], '/' . $k];
            }
        }
        $s['experiencias'] = ['Experiências', '/experiencias'];
        $s['roteiros'] = ['Roteiros', '/roteiros'];
        require_once __DIR__ . '/EventCity.php';
        if (EventCity::temAgenda($eid)) {
            $s['agenda'] = ['Agenda', '/agenda'];
        }
        $s['o-destino'] = ['O destino', '/o-destino'];
        $s['servicos'] = ['Serviços', '/servicos'];
        return $s;
    }

    public static function fim(): void
    {
        $d = self::$destino;
        ?>
</main>
<footer class="rodape">
  <?php if ($d): ?>
  <div class="rodape-destino">
    <b><?= e((string) $d['nome_fantasia']) ?></b>
    <?php if (!empty($d['whatsapp'])): ?><a href="https://wa.me/55<?= e(preg_replace('/\D/', '', (string) $d['whatsapp'])) ?>" rel="noopener"><?= icone('whatsapp') ?>WhatsApp do turismo</a><?php endif; ?>
    <?php if (!empty($d['email'])): ?><a href="mailto:<?= e((string) $d['email']) ?>"><?= icone('emails') ?><?= e((string) $d['email']) ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="rodape-links">
    <a href="<?= e(Portal::url('/o-destino')) ?>">O destino</a>
    <a href="/privacidade">Privacidade</a>
    <span>Dados do Inventário da Oferta Turística, no InvenTour. Produto Softsul Software e Zapping Consultoria</span>
  </div>
</footer>
</body>
</html>
        <?php
    }

    /** Cartão de um lugar nas listas. */
    public static function cartao(int $eid, array $i): string
    {
        $foto = Portal::foto($eid, $i['foto'] ?? null, 'm');
        $url = Portal::url('/lugar/' . (int) $i['id'] . '-' . Portal::slug((string) $i['nome']));
        $selos = '';
        foreach ($i['selos'] ?? [] as $s) {
            $selos .= '<span class="selo" title="' . e(Portal::SELOS_ACESSIBILIDADE[$s]) . '">' . icone($s === 'cadeirante' ? 'cadeirante' : ($s === 'auditiva' ? 'libras' : ($s === 'visual' ? 'olho' : ($s === 'vaga' ? 'carro' : 'pessoas')))) . '</span>';
        }
        foreach ($i['tribos'] ?? [] as $t) {
            $selos .= '<span class="selo tribo" title="' . e($t['nome']) . '">' . icone((string) ($t['icone'] ?: 'coracao')) . '</span>';
        }
        return '<article class="cartao-lugar"><a href="' . e($url) . '">'
            . ($foto ? '<img src="' . e($foto) . '" alt="" loading="lazy">' : '<span class="sem-foto">' . icone('imagem') . '</span>')
            . '<span class="cartao-txt"><b>' . e((string) $i['nome']) . '</b><span>' . e((string) ($i['tipo_nome'] ?: $i['ficha_nome']))
            . ($i['bairro'] ? ' · ' . e((string) $i['bairro']) : '') . '</span>'
            . (!empty($i['experiencias']) ? '<span class="tem-xp">' . icone('coracao') . 'Tem experiência</span>' : '')
            . ($selos !== '' ? '<span class="selos">' . $selos . '</span>' : '') . '</span></a>'
            . '<button type="button" class="guardar" data-roteiro="' . (int) $i['id'] . '" aria-pressed="false" title="Guardar no meu roteiro" aria-label="Guardar ' . e((string) $i['nome']) . ' no meu roteiro">' . icone('mais') . '</button>'
            . '</article>';
    }
}
