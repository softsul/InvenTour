<?php
/**
 * Moldura do portal público do destino: o "guia de campo".
 * Softsul Software
 *
 * Direção visual (Luiz pediu, 21/11/2026, um portal autêntico e não de
 * molde): o inventário é um levantamento de campo, e o portal assume isso.
 * Papel claro, tipografia pesada (Open Sans 800, com itálico nos títulos),
 * dados em fonte de máquina (IBM Plex Mono): coordenadas, código da ficha,
 * plus code, horário. As fontes moram no portal (assets/fontes), sem CDN.
 *
 * Sem login e sem cookie; HTML pronto no servidor, com Open Graph por
 * página. A cor do destino entra por /acento.css (a CSP não aceita estilo
 * embutido). Script só o do portal (assets/portal.js). Nenhum balão nativo
 * do navegador (title): só o balão da casa, com data-balao.
 */
declare(strict_types=1);
require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/Portal.php';

final class PortalLayout
{
    private static ?array $destino = null;

    /**
     * @param array<string,mixed>|null $destino
     * @param array{descricao?:?string,imagem?:?string,ativo?:string,classe?:string,jsonld?:list<array>} $og
     *        jsonld: os dados estruturados da página (app/PortalSeo.php)
     */
    public static function inicio(?array $destino, string $titulo, bool $indexar = true, array $og = []): void
    {
        if ($destino && !array_key_exists('municipio_id', $destino)) {
            // O cache do host (Tenant) guarda só o necessário para achar o
            // destino; o município e o contato vêm daqui.
            $q = Database::conexaoPortal()->prepare('SELECT municipio_id, whatsapp, email FROM empresas WHERE id = ?');
            $q->execute([(int) $destino['id']]);
            $destino += $q->fetch() ?: [];
        }
        self::$destino = $destino;
        $nome = $destino ? (string) $destino['nome_fantasia'] : 'InvenTour';
        $eid = $destino ? (int) $destino['id'] : 0;
        $previa = $eid && Portal::previa($eid);
        $indexar = $indexar && !$previa;
        $versao = fn (string $a) => (int) @filemtime(__DIR__ . '/../portal/assets/' . $a);
        $cor = $destino && preg_match('/^#[0-9A-F]{6}$/i', (string) ($destino['cor_acento'] ?? '')) ? strtoupper(substr((string) $destino['cor_acento'], 1)) : '0E7C66';
        $tituloPagina = $titulo === $nome ? $nome . ' · Guia oficial de turismo' : $titulo . ' · ' . $nome;
        $descricao = (string) ($og['descricao'] ?? '') ?: 'Onde dormir, onde comer, o que fazer e roteiros em ' . $nome . ': o guia oficial do destino, feito a partir do inventário turístico.';
        $host = 'https://' . ($_SERVER['HTTP_HOST'] ?? '');
        $canonico = $host . strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
        $ativo = (string) ($og['ativo'] ?? '');
        $coord = $destino ? self::coordenadaDoDestino($destino) : '';
        ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($tituloPagina) ?></title>
<meta name="description" content="<?= e(mb_substr($descricao, 0, 300)) ?>">
<?php if (!$indexar): ?><meta name="robots" content="noindex"><?php else: ?><link rel="canonical" href="<?= e($canonico) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="<?= e($nome) ?> · Guia oficial de turismo">
<meta property="og:title" content="<?= e($titulo) ?>">
<meta property="og:description" content="<?= e(mb_substr($descricao, 0, 300)) ?>">
<meta property="og:url" content="<?= e($canonico) ?>">
<?php if (!empty($og['imagem'])): ?><meta property="og:image" content="<?= e($host . $og['imagem']) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<?php if ($indexar && !empty($og['jsonld'])) { require_once __DIR__ . '/PortalSeo.php'; echo PortalSeo::bloco((array) $og['jsonld']); } ?>
<meta name="theme-color" content="#<?= e($cor) ?>">
<link rel="icon" href="/assets/icone.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/icone-180.png">
<link rel="preload" href="/assets/fontes/open-sans-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/portal.css?v=<?= $versao('portal.css') ?>">
<link rel="stylesheet" href="/acento.css?c=<?= e($cor) ?>">
<script src="/assets/portal.js?v=<?= $versao('portal.js') ?>" defer></script>
</head>
<body class="<?= e((string) ($og['classe'] ?? '')) ?>">
<?php svgSprite(); ?>
<?php if ($previa): ?><div class="faixa-previa">Prévia interna · mostra também o que ainda não foi homologado · não compartilhe este link</div>
<?php elseif (isset($_GET['previa'])): ?><div class="faixa-previa vencida">Este link de prévia venceu (vale 2 horas) · aqui só aparece o que já foi homologado · gere outro em Prévia do portal, no Início do painel</div><?php endif; ?>
<?php if ($destino): ?>
<header class="topo">
  <div class="topo-dados">
    <span><?= $coord !== '' ? e($coord) . ' · ' : '' ?>Guia oficial de turismo</span>
    <a class="meu-roteiro-link" href="<?= e(Portal::url('/meu-roteiro')) ?>" data-balao-titulo="Meu roteiro" data-balao="Os lugares que você guardou neste aparelho, com a rota no mapa e o link para compartilhar." data-balao-roteiro>Meu roteiro <b data-conta-roteiro hidden>0</b></a>
  </div>
  <div class="topo-linha">
    <a class="marca" href="<?= e(Portal::url('/')) ?>"><?= e($nome) ?></a>
    <nav class="menu-portal" id="menu-portal" aria-label="Seções">
      <?php foreach (self::secoes($eid) as $k => [$rotulo, $url]): ?>
        <a href="<?= e(Portal::url($url)) ?>"<?= $ativo === $k ? ' aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
      <?php endforeach; ?>
      <form class="busca-menu" method="get" action="/busca" role="search">
        <?php if ($previa): ?><input type="hidden" name="previa" value="<?= e((string) $_GET['previa']) ?>"><?php endif; ?>
        <span class="entrada"><?= icone('busca') ?><input type="search" name="q" placeholder="Buscar no guia" aria-label="Buscar no guia" value="<?= e((string) ($_GET['q'] ?? '')) ?>"></span>
      </form>
    </nav>
    <button type="button" class="abrir-menu-portal" aria-controls="menu-portal" aria-expanded="false" aria-label="Abrir o menu"><span></span><span></span></button>
  </div>
</header>
<?php endif; ?>
<main class="palco">
        <?php
    }

    /** 26°10′S 52°39′O do município sede, ou vazio. */
    public static function coordenadaDoDestino(array $destino): string
    {
        if (empty($destino['id'])) {
            return '';
        }
        $q = Database::conexaoPortal()->prepare('SELECT m.latitude, m.longitude FROM empresas e JOIN municipios m ON m.id = e.municipio_id WHERE e.id = ?');
        $q->execute([(int) $destino['id']]);
        $m = $q->fetch();
        return $m && $m['latitude'] !== null ? Portal::coordenada((float) $m['latitude'], (float) $m['longitude']) : '';
    }

    /** As seções do menu (a Agenda só quando o EventCity responde). */
    public static function secoes(int $eid): array
    {
        $s = [];
        foreach (Portal::PILARES as $k => $p) {
            if ($k !== 'servicos') {
                $s[$k] = [$p[0], '/' . $k];
            }
        }
        if (Portal::temUmDia($eid)) {
            $s['um-dia'] = ['Um dia aqui', '/um-dia'];
        }
        $s['roteiros'] = ['Roteiros', '/roteiros'];
        $s['experiencias'] = ['Experiências', '/experiencias'];
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
  <div class="rodape-grande"><?= e((string) $d['nome_fantasia']) ?></div>
  <div class="rodape-colunas">
    <div>
      <b>O guia</b>
      <?php foreach (self::secoes((int) $d['id']) as [$rotulo, $url]): ?><a href="<?= e(Portal::url($url)) ?>"><?= e($rotulo) ?></a><?php endforeach; ?>
    </div>
    <div>
      <b>Fale com o turismo</b>
      <?php if (!empty($d['whatsapp'])): ?><a target="_blank" rel="noopener" href="https://wa.me/<?= e((string) telefoneParaBanco((string) $d['whatsapp'])) ?>">WhatsApp</a><?php endif; ?>
      <?php if (!empty($d['email'])): ?><a href="mailto:<?= e((string) $d['email']) ?>"><?= e((string) $d['email']) ?></a><?php endif; ?>
      <a href="/privacidade">Privacidade</a>
    </div>
    <div>
      <b>Sobre os dados</b>
      <p>Cada lugar deste guia vem do Inventário da Oferta Turística do destino, conferido em campo e homologado pela equipe de turismo.</p>
      <p class="assinatura">Produto <a href="https://softsul.com" target="_blank" rel="noopener">Softsul Software</a> e <a href="https://azapping.com.br" target="_blank" rel="noopener">Zapping Consultoria</a></p>
    </div>
  </div>
  <?php endif; ?>
</footer>
</body>
</html>
        <?php
    }

    /** Ícone de cada selo de acessibilidade. */
    public static function iconeDoSelo(string $s): string
    {
        return ['cadeirante' => 'cadeirante', 'auditiva' => 'libras', 'visual' => 'olho', 'vaga' => 'carro'][$s] ?? 'pessoas';
    }

    /** Selo de acessibilidade com o balão completo (o que a ficha registra). */
    public static function selo(string $s, array $detalhes): string
    {
        $texto = $detalhes ? 'Segundo a ficha do inventário: ' . implode('; ', $detalhes) . '.' : 'Registrado na ficha do inventário.';
        return '<span class="selo" tabindex="0" role="img" aria-label="' . e(Portal::SELOS_ACESSIBILIDADE[$s] . '. ' . $texto) . '" data-balao-titulo="'
            . e(Portal::SELOS_ACESSIBILIDADE[$s]) . '" data-balao="' . e($texto) . '">' . icone(self::iconeDoSelo($s)) . '</span>';
    }

    /** Selo de perfil de viajante (tribo), com as facilidades que o justificam. */
    public static function seloTribo(array $t): string
    {
        $texto = 'Bom para este perfil de viajante. Tem: ' . ($t['facilidades'] ?? '') . '.';
        return '<span class="selo tribo" tabindex="0" role="img" aria-label="' . e($t['nome'] . '. ' . $texto) . '" data-balao-titulo="' . e((string) $t['nome'])
            . '" data-balao="' . e($texto) . '">' . icone((string) ($t['icone'] ?: 'coracao')) . '</span>';
    }

    /** Endereço da ficha de um lugar. */
    public static function urlLugar(array $i): string
    {
        return Portal::url('/lugar/' . (int) $i['id'] . '-' . Portal::slug((string) $i['nome']));
    }

    /** A inicial do nome, para o lugar sem foto (sem "Hotel", "H." e afins na frente). */
    public static function inicial(string $nome): string
    {
        $n = trim((string) preg_replace('/^(H\.|Hotel|Pousada|Restaurante|Bar|Lanchonete|Pizzaria)\s+/iu', '', $nome));
        return mb_strtoupper(mb_substr($n !== '' ? $n : $nome, 0, 1));
    }

    /**
     * Cartão de um lugar: a "etiqueta de campo". Foto (ou a inicial grande
     * com o plus code), código da ficha, tipo e bairro, o estado agora
     * (aberto ou fechado, pelo horário da ficha), a distância (o portal.js
     * preenche com o GPS do visitante) e os selos.
     */
    public static function cartao(int $eid, array $i, string $extra = ''): string
    {
        $foto = Portal::foto($eid, $i['foto'] ?? null, 'm');
        $agora = Portal::agora($i['horario'] ?? null);
        $selos = '';
        foreach ($i['selos'] ?? [] as $sel) {
            $selos .= self::selo($sel, (array) ($i['selos_det'][$sel] ?? []));
        }
        foreach ($i['tribos'] ?? [] as $t) {
            $selos .= self::seloTribo($t);
        }
        $tipo = trim(($i['tipo_nome'] ?: $i['ficha_nome']) . ($i['bairro'] ? ' · ' . $i['bairro'] : ''));
        $geo = $i['latitude'] !== null ? ' data-lat="' . e((string) $i['latitude']) . '" data-lng="' . e((string) $i['longitude']) . '"' : '';
        return '<article class="lugar"' . $geo . ' data-aberto="' . ($agora ? ($agora['aberto'] ? '1' : '0') : '') . '"><a href="' . e(self::urlLugar($i)) . '">'
            . '<figure>' . ($foto ? '<img src="' . e($foto) . '" alt="" loading="lazy">'
                : '<span class="sem-foto"><b>' . e(self::inicial((string) $i['nome'])) . '</b></span>')
            . (!empty($i['codigo']) ? '<span class="codigo">' . e((string) $i['codigo']) . '</span>' : '')
            // A distância até o visitante (quando ele tocou em Perto de mim), em destaque na imagem.
            . '<span class="dist dist-figura" data-dist></span></figure>'
            . '<span class="lugar-txt"><span class="lugar-tipo">' . e($tipo) . '</span><b class="lugar-nome">' . e((string) $i['nome']) . '</b>'
            . ($agora ? '<span class="estado' . ($agora['aberto'] ? ' aberto' : '') . '">' . e($agora['texto']) . '</span>'
                : (($per = Portal::periodos($i['horario'] ?? null)) ? '<span class="estado">' . e($per) . '</span>' : ''))
            . (!empty($i['experiencias']) ? '<span class="tem-xp">Tem experiência para viver</span>' : '')
            . $extra
            . ($selos !== '' ? '<span class="selos">' . $selos . '</span>' : '') . '</span></a>'
            . '<button type="button" class="guardar" data-roteiro="' . (int) $i['id'] . '" aria-pressed="false" data-balao-titulo="Guardar no meu roteiro" data-balao="'
            . e((string) $i['nome']) . ' entra na sua lista de lugares para visitar, guardada só neste aparelho. Toque de novo para tirar." aria-label="Guardar '
            . e((string) $i['nome']) . ' no meu roteiro">' . icone('mais') . '</button></article>';
    }

    /**
     * O mapa do destino em SVG: a divisa oficial (IBGE) e um ponto por lugar,
     * na cor do pilar. Sem serviço de mapa de fora: é o desenho do próprio
     * inventário. Tocar num ponto abre o cartão (portal.js).
     *
     * @param array<int,array> $itens com latitude, longitude e 'pilar'
     */
    /**
     * O mapa do destino: o mapa base próprio (ruas, rios, praças e bairros do
     * OpenStreetMap, no estilo do caderno de viagem, servido pelo portal em
     * /mapa-base/<destino>) com a divisa do município e um ponto por lugar,
     * na cor do pilar. O portal.js desenha com o MapLibre; aqui vão o filtro,
     * os botões de zoom e a lista dos pontos (que também é o mapa para quem
     * usa leitor de tela). Sem o recorte do destino, fica só a divisa no papel.
     *
     * @param array<int,array> $itens com latitude, longitude e 'pilar'
     * @param array{filtro?:bool,zoom?:int,destaque?:int} $opcoes no mapa de um lugar: sem filtro, zoom fixo e o lugar em destaque
     */
    public static function mapa(int $eid, array $itens, string $rotulo = 'Mapa do destino', array $opcoes = []): string
    {
        $m = Portal::mapa($eid);
        // Só o que cai na área do município: coordenada errada não estica o mapa.
        $pontos = array_filter($itens, fn ($i) => Portal::naArea($eid, $i));
        if (!$m && !$pontos) {
            return '';
        }
        $todos = [];
        $divisa = [];
        foreach ($m['aneis'] ?? [] as $anel) {
            $linha = [];
            foreach ($anel as $p) {
                $todos[] = $p;
                $linha[] = [round((float) $p[0], 5), round((float) $p[1], 5)];
            }
            $divisa[] = $linha;
        }
        foreach ($pontos as $i) {
            $todos[] = [(float) $i['longitude'], (float) $i['latitude']];
        }
        $lngs = array_column($todos, 0);
        $lats = array_column($todos, 1);
        $limites = implode(',', array_map(fn ($v) => round((float) $v, 5), [min($lngs), min($lats), max($lngs), max($lats)]));
        // Onde o mapa abre: o miolo dos lugares (sem os 8% mais afastados de
        // cada lado), que costuma ser a cidade; o botão do mapa mostra tudo.
        $inicio = $limites;
        if (count($pontos) >= 10) {
            $pl = array_map(fn ($i) => (float) $i['longitude'], $pontos);
            $pa = array_map(fn ($i) => (float) $i['latitude'], $pontos);
            sort($pl);
            sort($pa);
            $q = fn (array $v, float $f) => $v[(int) floor($f * (count($v) - 1))];
            $inicio = implode(',', array_map(fn ($v) => round($v, 5), [$q($pl, .08), $q($pa, .08), $q($pl, .92), $q($pa, .92)]));
        }

        // O filtro: com lugares de mais de um pilar, um botão por pilar; num
        // pilar só, um por tipo (os seis mais comuns e "Outros").
        $grupoDe = [];
        $pilares = array_unique(array_map(fn ($i) => (string) ($i['pilar'] ?? 'o-que-fazer'), $pontos));
        if (count($pilares) > 1) {
            $grupos = [];
            foreach (Portal::PILARES as $k => $pil) {
                $n = count(array_filter($pontos, fn ($i) => ($i['pilar'] ?? '') === $k));
                if ($n) {
                    $grupos[$k] = [$pil[0], $n, $k];
                }
            }
            foreach ($pontos as $id => $i) {
                $grupoDe[$id] = (string) ($i['pilar'] ?? 'o-que-fazer');
            }
        } else {
            $porTipo = [];
            foreach ($pontos as $id => $i) {
                $porTipo[(string) ($i['tipo_nome'] ?: $i['ficha_nome'])][] = $id;
            }
            uasort($porTipo, fn ($a, $b) => count($b) <=> count($a));
            $grupos = [];
            $n = 0;
            foreach ($porTipo as $tipo => $ids) {
                $chave = ++$n <= 6 || count($porTipo) === 7 ? 't' . $n : 'outros';
                $grupos[$chave] ??= [$chave === 'outros' ? 'Outros' : $tipo, 0, ''];
                $grupos[$chave][1] += count($ids);
                foreach ($ids as $id) {
                    $grupoDe[$id] = $chave;
                }
            }
        }

        $lista = '';
        foreach ($pontos as $id => $i) {
            $lista .= '<li><a href="' . e(self::urlLugar($i)) . '"' . ((int) ($opcoes['destaque'] ?? 0) === (int) $id ? ' data-destaque' : '') . ' data-lng="' . round((float) $i['longitude'], 6) . '" data-lat="' . round((float) $i['latitude'], 6) . '"'
                . ' data-pilar="' . e((string) ($i['pilar'] ?? 'o-que-fazer')) . '" data-grupo="' . e($grupoDe[$id] ?? '') . '"'
                . ' data-lugar="' . e(json_encode(self::dadosDoPonto($eid, $i), JSON_UNESCAPED_UNICODE)) . '">' . e((string) $i['nome']) . '</a></li>';
        }

        $filtro = '<div class="mapa-filtro" role="group" aria-label="Mostrar no mapa"><button type="button" class="ativo" aria-pressed="true" data-grupo-filtro="">Tudo <small>' . milhar(count($pontos)) . '</small></button>';
        foreach ($grupos as $chave => [$nome, $n, $cor]) {
            $filtro .= '<button type="button" aria-pressed="false" data-grupo-filtro="' . e((string) $chave) . '">'
                . ($cor !== '' ? '<i class="l-' . e($cor) . '"></i>' : '') . e($nome) . ' <small>' . milhar($n) . '</small></button>';
        }
        $filtro .= '</div>';
        if (($opcoes['filtro'] ?? true) === false || count($pontos) < 2) {
            $filtro = '';
        }
        $base = is_dir(dirname(__DIR__) . '/portal/mapa-base/' . $eid) ? ' data-mapa-base="/mapa-base/' . $eid . '"' : '';
        $zoom = '<div class="mapa-zoom"><button type="button" data-zoom="1" aria-label="Aproximar">+</button><button type="button" data-zoom="-1" aria-label="Afastar">&minus;</button>'
            . '<button type="button" data-zoom="0" aria-label="Ver o município inteiro">' . icone('mapa') . '</button></div>';
        return $filtro . '<div class="mapa-palco"' . $base . ' data-limites="' . $limites . '" data-inicio="' . $inicio . '"' . (!empty($opcoes['zoom']) ? ' data-zoom-inicial="' . (int) $opcoes['zoom'] . '"' : '')
            . ' data-divisa="' . e(json_encode($divisa)) . '" role="region" aria-label="' . e($rotulo) . '">'
            . '<div class="mapa-tela"></div>' . $zoom
            . '<ul class="mapa-lista-pontos">' . $lista . '</ul></div>';
    }

    /**
     * A linha do tempo de um roteiro (página do roteiro e Um dia aqui):
     * trilho vertical com a hora e um ponto em cada parada, um cartão baixo
     * (miniatura, tipo, nome, tempo no lugar e dica) e, entre as paradas, o
     * deslocamento (a pé ou de carro, minutos e distância).
     */
    public static function linhaDoTempo(int $eid, array $passos): string
    {
        require_once __DIR__ . '/UmDia.php';
        $h = '<ol class="tl">';
        foreach ($passos as $k => $p) {
            $i = $p['item'];
            $m = $p['metros'] ?? null;
            if ($k > 0 && $p['deslocamento'] !== null) {
                $meio = UmDia::MEIOS[$p['meio'] ?? 'pe'][0] ?? 'A pé';
                $texto = (int) $p['deslocamento'] === 0 ? 'Ao lado' : $meio . ', cerca de ' . UmDia::duracao((int) $p['deslocamento']);
                $h .= '<li class="tl-trajeto"><span>' . icone('mapa') . e($texto . ($m !== null && $m >= 20 ? ' · ' . Portal::metros($m) : '')) . '</span></li>';
            }
            $foto = Portal::foto($eid, $i['foto'] ?? null, 'p');
            $tipo = trim((string) (($i['tipo_nome'] ?? '') ?: ($i['ficha_nome'] ?? '')));
            $h .= '<li class="tl-parada' . ($k === 0 ? ' tl-inicio' : '') . ($k === count($passos) - 1 ? ' tl-fim' : '') . '">'
                . '<span class="tl-hora">' . e((string) $p['hora']) . '</span><span class="tl-ponto" aria-hidden="true"></span>'
                . '<div class="tl-cartao"><a href="' . e(self::urlLugar($i)) . '">'
                . '<figure>' . ($foto ? '<img src="' . e($foto) . '" alt="" loading="lazy">' : '<span class="sem-foto"><b>' . e(self::inicial((string) $i['nome'])) . '</b></span>') . '</figure>'
                . '<span class="tl-txt">' . ($tipo !== '' ? '<span class="lugar-tipo">' . e($tipo) . '</span>' : '')
                . '<b>' . e((string) $i['nome']) . '</b>'
                . ($p['duracao'] !== '' ? '<span class="tl-tempo">' . e('Cerca de ' . $p['duracao'] . ' aqui') . '</span>' : '')
                . (!empty($p['nota']) ? '<span class="tl-dica">' . e((string) $p['nota']) . '</span>' : '') . '</span></a>'
                . '<button type="button" class="guardar" data-roteiro="' . (int) $i['id'] . '" aria-pressed="false" data-balao-titulo="Guardar no meu roteiro" data-balao="'
                . e((string) $i['nome']) . ' entra na sua lista de lugares para visitar, guardada só neste aparelho." aria-label="Guardar ' . e((string) $i['nome']) . ' no meu roteiro">'
                . icone('mais') . '</button></div></li>';
        }
        return $h . '</ol>';
    }

    /** O que o balão do mapa mostra de cada lugar (chaves curtas: são centenas de pontos). */
    private static function dadosDoPonto(int $eid, array $i): array
    {
        $agora = Portal::agora($i['horario'] ?? null);
        $extra = [];
        if (!empty($i['capacidade_leitos'])) {
            $extra[] = plural((int) $i['capacidade_leitos'], 'leito', 'leitos') . (!empty($i['capacidade_uh']) ? ' em ' . plural((int) $i['capacidade_uh'], 'quarto', 'quartos') : '');
        }
        if (!empty($i['cadastur_ok'])) {
            $extra[] = 'Cadastur em dia';
        }
        if (!empty($i['experiencias'])) {
            $extra[] = plural((int) $i['experiencias'], 'experiência', 'experiências');
        }
        return array_filter([
            'n' => (string) $i['nome'],
            't' => (string) ($i['tipo_nome'] ?: $i['ficha_nome']),
            'c' => (string) ($i['codigo'] ?? ''),
            'f' => Portal::foto($eid, $i['foto'] ?? null, 'p'),
            'b' => implode(' · ', array_filter([trim((string) ($i['bairro'] ?? '')), ($i['zona'] ?? '') === 'rural' ? 'no campo' : ''])),
            'a' => $agora ? $agora['texto'] : Portal::periodos($i['horario'] ?? null),
            'o' => $agora ? (int) $agora['aberto'] : null,
            'x' => $extra,
            's' => array_values(array_map(fn ($k) => Portal::SELOS_ACESSIBILIDADE[$k], array_filter($i['selos'] ?? [], fn ($k) => isset(Portal::SELOS_ACESSIBILIDADE[$k])))),
            'p' => array_values(array_map(fn ($t) => (string) $t['nome'], $i['tribos'] ?? [])),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
