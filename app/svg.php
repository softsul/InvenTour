<?php
/**
 * Funções de tela: ícones, marca, formatação, endereços, paginação e campos.
 * Softsul Software
 *
 * Trazidas do app/svg.php do Centurion (que por sua vez veio do Orbit), sem
 * o que era domínio de lá. O que ficou vale para qualquer painel da casa:
 * o sprite de ícones, a marca em SVG, escape, endereços limpos, paginação,
 * zona de upload e o seletor com busca. Tudo cabe na CSP do painel, que não
 * aceita script nem estilo escritos no elemento: o avatar pinta por classe.
 *
 * Imagem no código é sempre SVG. O sprite entra uma vez por página e o resto
 * referencia por <use>.
 */
declare(strict_types=1);

/**
 * Versão dos arquivos de tela, para o navegador não ficar com a folha velha.
 * Sai da hora em que a folha ou o script foram mudados pela última vez: com
 * número escrito à mão, uma tela sempre fica para trás (lição do Orbit).
 */
define('VERSAO_ASSETS', (string) max(
    (int) @filemtime(__DIR__ . '/../public/assets/tema.css'),
    (int) @filemtime(__DIR__ . '/../public/assets/inventour.css'),
    (int) @filemtime(__DIR__ . '/../public/assets/inventour.js'),
    (int) @filemtime(__DIR__ . '/../public/assets/marca/marca.css'),
    (int) @filemtime(__DIR__ . '/../public/assets/marca/marca.svg'),
    (int) @filemtime(__DIR__ . '/../portal/assets/portal.css'),
    (int) @filemtime(__DIR__ . '/../portal/assets/acessibilidade.js'),
    (int) @filemtime(__DIR__ . '/../portal/assets/contato.js'),
    (int) @filemtime(__DIR__ . '/../portal/assets/portal.js')
));

/**
 * O sprite de ícones, uma vez por página. Traço 2, caixa de 24, cor do
 * texto (currentColor): o CSS decide tamanho e cor em cada lugar.
 *
 * Os nomes dos módulos (migração 002) são chaves daqui: casa, calendario,
 * check, alerta, jornal, imagem, contatos, mapa, qrcode, manual, pessoas,
 * lista, engrenagem, predio, etiqueta, relogio, rede, financeiro, cupom e
 * megafone. Os demais servem o layout, as telas de acesso e as listas.
 */
function svgSprite(): void
{
    static $jaSaiu = false;
    if ($jaSaiu) {
        return;
    }
    $jaSaiu = true;
    $a = ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
    echo '<svg class="sprite" width="0" height="0" aria-hidden="true" focusable="false">';
    foreach (desenhosDosIcones() as $nome => $desenho) {
        echo '<symbol id="i-' . $nome . '"' . $a . $desenho . '</symbol>';
    }
    echo '</svg>';
}

/**
 * Os desenhos do sprite, nome => miolo do <symbol>.
 *
 * Separado do svgSprite() porque a escolha de ícone dos catálogos
 * (segmento e atributo) precisa da lista de nomes: o que se oferece na tela
 * é exatamente o que o sprite desenha, sem uma segunda lista para esquecer.
 *
 * @return array<string,string>
 */
function desenhosDosIcones(): array
{
    static $icones = null;
    if ($icones !== null) {
        return $icones;
    }
    $engrenagem = '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 8.9 19a1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 8.9a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>';
    $pessoas    = '<circle cx="9" cy="8" r="3"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M16 5.5a3 3 0 0 1 0 5.5M18 20a6 6 0 0 0-2-4.5"/>';
    $predio     = '<path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16"/><path d="M15 10h4a1 1 0 0 1 1 1v10"/><path d="M8 8h3M8 12h3M8 16h3M3 21h18"/>';
    $arquivo    = '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v4h4"/><path d="M9 13h6M9 17h4"/>';
    $icones = [
        // Módulos da migração 002.
        'casa'          => '<path d="M3 11 12 3l9 8"/><path d="M5 10v11h14V10"/><path d="M10 21v-6h4v6"/>',
        'calendario'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="M7.5 14h2M11 14h2M14.5 14h2M7.5 17.5h2M11 17.5h2"/>',
        'check'         => '<path d="m20 6-11 11-5-5"/>',
        'alerta'        => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'jornal'        => '<rect x="3" y="5" width="15" height="14" rx="2"/><path d="M18 9h3v8a2 2 0 0 1-2 2"/><path d="M7 9h7M7 12h7M7 15h4"/>',
        'imagem'        => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 8"/>',
        'contatos'      => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'mapa'          => '<path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'camera'        => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
        'qrcode'        => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v3M17 20h3M14 20h.01"/>',
        'manual'        => '<path d="M4 4h6a3 3 0 0 1 2 1 3 3 0 0 1 2-1h6v14h-6a3 3 0 0 0-2 1 3 3 0 0 0-2-1H4z"/><path d="M12 5v14"/>',
        'pessoas'       => $pessoas,
        'lista'         => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
        'engrenagem'    => $engrenagem,
        'predio'        => $predio,
        'etiqueta'      => '<path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'relogio'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'rede'          => '<circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="19" r="2.5"/><path d="M12 7.5V12M12 12l-5.3 5M12 12l5.3 5"/>',
        'financeiro'    => '<path d="M3 6h18v12H3z"/><path d="M3 10h18"/><circle cx="7" cy="14" r="1"/>',
        'cupom'         => '<path d="M4 7h16v3a2 2 0 0 0 0 4v3H4v-3a2 2 0 0 0 0-4z"/><path d="M10 9v1M10 12v1M10 15v1"/>',
        'megafone'      => '<path d="M3 10v4h3l7 4V6l-7 4z"/><path d="M17 9a4 4 0 0 1 0 6"/>',
        // Os mesmos desenhos pelos nomes que as telas herdadas do Centurion e
        // do Orbit usam (configuracoes, equipe, destino, usuarios, auditoria).
        'configuracoes' => $engrenagem,
        'equipe'        => $pessoas,
        'destino'       => $predio,
        'usuarios'      => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
        'auditoria'     => '<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
        // Layout, acesso, listas e formulários.
        'painel'        => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'busca'         => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'seta'          => '<path d="m9 18 6-6-6-6"/>',
        'menu'          => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'sair'          => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'cadeado'       => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'escudo'        => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'digital'       => '<path d="M12 11v4M8.5 8.5A5 5 0 0117 12v2M7 12v1a5 5 0 001.4 3.5M12 7a5 5 0 00-5 5"/><path d="M5 5.5A9 9 0 0121 12v1M3 12a9 9 0 011.2-4.5M16 20a9 9 0 001-2.5"/>',
        'certificado'   => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M7 9h10M7 12h6"/><circle cx="16" cy="17" r="2.5"/><path d="M14.8 19l-.8 3 2-1 2 1-.8-3"/>',
        'chave'         => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>',
        'sino'          => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M10.5 21a2 2 0 0 0 3 0"/>',
        'emails'        => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/>',
        'whatsapp'      => '<path d="M3 21l1.7-5A8.5 8.5 0 1 1 8 19.3z"/>',
        'enviar'        => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/>',
        'fechar'        => '<path d="M18 6 6 18M6 6l12 12"/>',
        'lixeira'       => '<path d="M4 7h16M10 4h4M6 7l1 13h10l1-13"/><path d="M10 11v6M14 11v6"/>',
        'lapis'         => '<path d="M4 20h4L20 8a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m14 6 4 4"/>',
        'mais'          => '<path d="M12 5v14M5 12h14"/>',
        'baixar'        => '<path d="M12 3v12"/><path d="m7 11 5 5 5-5"/><path d="M4 20h16"/>',
        'olho'          => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'olho-fechado'  => '<path d="M2 12s3.5-7 10-7c2 0 3.7.7 5.1 1.6M22 12s-3.5 7-10 7c-2 0-3.7-.7-5.1-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="M3 3l18 18"/>',
        'copiar'        => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 00-1-1H5a1 1 0 00-1 1v10a1 1 0 001 1h3"/>',
        'trocar'        => '<path d="M4 8h13l-3-3M20 16H7l3 3"/>',
        'arquivo'       => $arquivo,
        'contratos'     => $arquivo,
        'sol'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
        'lua'           => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        // Catálogos da migração 004: segmentos e atributos guardam o nome
        // do ícone no registro. Desenho de poucos traços, para ler em 16px
        // no grid do portal e na lista do painel.
        'gastronomia'   => '<path d="M4 3v6a3 3 0 0 0 6 0V3M7 3v18"/><path d="M19 21V3c-2.5 1-4 3.5-4 7v4h4"/>',
        'cultura'       => '<path d="M4 4h16v7a8 8 0 0 1-16 0z"/><path d="M8 9h2M14 9h2"/><path d="M9 14.5a4 4 0 0 0 6 0"/>',
        'religioso'     => '<path d="M12 2v6M9.5 4.5h5"/><path d="M5 21v-8l7-5 7 5v8z"/><path d="M10 21v-4a2 2 0 0 1 4 0v4"/>',
        'esporte'       => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4"/><path d="M12 13v5M8 21h8"/>',
        'natureza'      => '<path d="M2 20 9 8l4 6 2-3 7 9z"/><circle cx="17" cy="5" r="2"/>',
        'rural'         => '<path d="M3 21V10l9-6 9 6v11z"/><path d="M8 21v-8h8v8M8 13l8 8M16 13l-8 8"/>',
        'lazer'         => '<path d="M3 12a9 9 0 0 1 18 0z"/><path d="M12 3v1M12 12v7a2 2 0 0 0 4 0"/>',
        'academico'     => '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/><path d="M22 9v6"/>',
        'negocios'      => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M3 13h18"/>',
        'compras'       => '<path d="M5 8h14l-1 13H6z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
        'libras'        => '<path d="M7 14V7a1.5 1.5 0 0 1 3 0v4M10 11V5a1.5 1.5 0 0 1 3 0v6M13 11V6a1.5 1.5 0 0 1 3 0v5M16 11V9a1.5 1.5 0 0 1 3 0v5a7 7 0 0 1-7 7h-1a6 6 0 0 1-4.5-2l-2.9-3.4a1.5 1.5 0 0 1 2.3-1.9L7 15"/>',
        'audiodescricao'=> '<path d="m2.5 17 3-10 3 10M3.7 13.5h3.6"/><path d="M10.5 7v10h1a5 5 0 0 0 0-10z"/><path d="M18.5 9a5 5 0 0 1 0 6M21 7a8.5 8.5 0 0 1 0 10"/>',
        'cadeirante'    => '<circle cx="10" cy="4" r="1.5"/><path d="M10 7v6h6l2 5h2M10 10h5"/><path d="M7 11.5a5 5 0 1 0 7.5 6"/>',
        'banheiro'      => '<path d="M5 3h5v7H5z"/><path d="M4 10h16a7 7 0 0 1-7 7h-2a7 7 0 0 1-7-7"/><path d="M9 17l-1 4h8l-1-4"/>',
        'carro'         => '<path d="M5 17H3v-5h18v5h-2M9.5 17h5"/><path d="m5 12 2-5h10l2 5"/><circle cx="7.5" cy="17" r="2"/><circle cx="16.5" cy="17" r="2"/>',
        'coberto'       => '<path d="m2 14 10-7 10 7"/><path d="M5 12v9M19 12v9"/><path d="M7 2v2M12 1v2M17 2v2"/>',
        'ar'            => '<path d="M12 2v20M3.3 7l17.4 10M3.3 17 20.7 7"/><path d="m9 3.5 3 2 3-2M9 20.5l3-2 3 2"/>',
        'kids'          => '<path d="M12 3a6 6 0 0 1 6 6c0 4-3 7-6 7s-6-3-6-7a6 6 0 0 1 6-6z"/><path d="m11 18 1-2 1 2zM12 18c0 2-2 2-2 4"/>',
        'pet'           => '<circle cx="5.5" cy="11" r="2"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="6" r="2"/><circle cx="18.5" cy="11" r="2"/><path d="M12 12c-3 0-5.5 3.5-5.5 6a2.5 2.5 0 0 0 3 2.5c1-.2 1.7-.5 2.5-.5s1.5.3 2.5.5a2.5 2.5 0 0 0 3-2.5c0-2.5-2.5-6-5.5-6z"/>',
        'coracao'       => '<path d="M12 20s-7.5-4.6-9-9.2A4.7 4.7 0 0 1 12 7.5a4.7 4.7 0 0 1 9 3.3C19.5 15.4 12 20 12 20z"/>',
        'play'          => '<circle cx="12" cy="12" r="9"/><path d="M10 8.5l5.5 3.5-5.5 3.5z"/>',
        'maiores-18'    => '<circle cx="12" cy="12" r="9.5"/><path d="M7.5 9.5 9.5 8v8"/><circle cx="14.5" cy="10" r="2"/><circle cx="14.5" cy="14" r="2"/>',
        // Ordem dos catálogos: a alça de arrastar a linha e o A-Z.
        'arrastar'      => '<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>',
        'alfabetica'    => '<path d="M3 10 6 3l3 7M4 7.7h4"/><path d="M3 14h6l-6 7h6"/><path d="M17 4v16M13.5 16.5 17 20l3.5-3.5"/>',
        'filtro'        => '<path d="M4 5h16l-6 7.5V19l-4-2v-4.5L4 5z"/>',
    ];
    return $icones;
}

/** Ícone do sprite. O tamanho vem do CSS quando $px é nulo. */
function icone(string $nome, ?int $px = null, string $classe = ''): string
{
    $tam = $px !== null ? ' width="' . $px . '" height="' . $px . '"' : '';
    $cls = $classe !== '' ? ' class="' . htmlspecialchars($classe, ENT_QUOTES) . '"' : '';
    $nome = preg_replace('/[^a-z0-9_-]/', '', $nome);
    return '<svg' . $tam . $cls . ' aria-hidden="true" focusable="false"><use href="#i-' . $nome . '"/></svg>';
}

/**
 * A marca do InvenTour, a mesma do site inventour.com.br.
 *
 * Os desenhos ficam num arquivo só, public/assets/marca/marca.svg, com três
 * símbolos: o emblema (o "e" em meia-lua rosa sobre o arco azul-marinho), o
 * logo (emblema e nome) e o nome sozinho. A página usa <use> apontando para
 * ele: o arquivo vem uma vez e fica no cache, em vez de 40 KB de desenho em
 * toda página.
 *
 * As cores vêm do CSS (marca.css): o rosa herda `fill` e o marinho usa
 * `currentColor`, as duas propriedades que atravessam o <use>. É assim que
 * o tema escuro clareia o marinho, que sumiria sobre o fundo preto.
 *
 * $qual: 'logo' (emblema e nome), 'simbolo' (só o emblema) ou 'palavra'
 * (só o nome). Os nomes antigos continuam valendo para as telas que já os
 * chamam.
 */
function logoInvenTour(string $qual = 'logo', string $classe = ''): string
{
    $qual = match ($qual) {
        'simbolo', 'emblema' => 'emblema',
        'palavra', 'nome'    => 'nome',
        default              => 'logo',
    };
    $emblema = '<img class="marca-emblema-img" src="/assets/marca/emblema.svg?v=' . VERSAO_ASSETS . '" alt="" width="446" height="446">';
    // O nome em duas cores, como no site: "inven" no marinho (color) e
    // "tour" no verde-água (fill). textLength garante a largura do desenho
    // com qualquer fonte.
    $nome = '<svg class="marca-nome-svg" viewBox="0 0 480 100" aria-hidden="true" focusable="false">'
          . '<text x="0" y="80" textLength="480" lengthAdjust="spacingAndGlyphs" font-family="Inter, Arial, sans-serif" font-weight="800" font-size="100">'
          . '<tspan fill="currentColor">inven</tspan><tspan>tour</tspan></text></svg>';
    $cls = htmlspecialchars(trim('marca-svg marca-' . $qual . ' ' . $classe), ENT_QUOTES);
    return match ($qual) {
        'emblema' => '<span class="' . $cls . '" aria-hidden="true">' . $emblema . '</span>',
        'nome'    => '<span class="' . $cls . '" aria-hidden="true">' . $nome . '</span>',
        default   => '<span class="' . $cls . '" aria-hidden="true">' . $emblema . $nome . '</span>',
    };
}

/**
 * O emblema da entrada sobre um "mapa vivo": um mapa redondo com curvas de
 * nível, uma rota tracejada que se desenha entre cinco pinos que caem um a
 * um, e ondas de localização ("você está aqui") saindo do centro, onde fica o
 * emblema. Só HTML, SVG e CSS (marca.css): a CSP não deixa estilo no
 * elemento. Substitui a explosão de raios herdada do EventCity (pedido do
 * Luiz, 20/11/2026: algo de mapa e posicionamento).
 *
 * O tamanho vem do CSS pela variável --tam do elemento (padrão 320px).
 * Quem pede menos movimento (prefers-reduced-motion) vê tudo parado.
 */
function explosaoDaMarca(string $classe = ''): string
{
    // Cinco pontos do roteiro, em volta do centro (viewBox 400 x 400).
    $pinos = [[96, 128, 'verde'], [298, 92, 'ouro'], [334, 246, 'azul'], [150, 312, 'verde'], [62, 236, 'ouro']];
    $svgPinos = '';
    foreach ($pinos as [$x, $y, $cor]) {
        $svgPinos .= '<g transform="translate(' . $x . ' ' . $y . ')"><g class="pino ' . $cor . '">'
                   . '<ellipse class="sombra" cx="0" cy="1" rx="7" ry="2.4"/>'
                   . '<path d="M0 0C-6-9-11-14-11-21a11 11 0 1 1 22 0c0 7-5 12-11 21z"/><circle cx="0" cy="-21" r="4.2"/>'
                   . '<circle class="ping" cx="0" cy="0" r="6"/></g></g>';
    }
    $svg = '<svg class="mapa-vivo" viewBox="0 0 400 400" aria-hidden="true" focusable="false">'
         . '<defs><clipPath id="mapa-redondo"><circle cx="200" cy="200" r="188"/></clipPath></defs>'
         . '<circle class="mapa-disco" cx="200" cy="200" r="188"/>'
         . '<g clip-path="url(#mapa-redondo)"><g class="curvas">'
         . '<path d="M-20 90C60 60 120 120 200 96S330 40 420 70"/>'
         . '<path d="M-20 150C70 120 130 180 210 156S340 100 420 132"/>'
         . '<path d="M-20 262C80 236 140 296 220 270S350 214 420 246"/>'
         . '<path d="M-20 322C70 300 150 352 230 330S360 282 420 306"/>'
         . '<ellipse cx="300" cy="190" rx="54" ry="34"/><ellipse cx="300" cy="190" rx="30" ry="17"/>'
         . '<ellipse cx="110" cy="205" rx="40" ry="26"/>'
         . '</g><g class="grade"><path d="M0 200H400M200 0V400"/><circle cx="200" cy="200" r="120"/></g></g>'
         . '<path class="rota" d="M96 128C160 70 236 70 298 92S352 190 334 246 230 330 150 312 50 290 62 236"/>'
         . $svgPinos . '</svg>';
    return '<div class="' . htmlspecialchars(trim('explosao ' . $classe), ENT_QUOTES) . '" aria-hidden="true">'
         . '<div class="halo"></div>' . $svg
         . '<div class="ondas"><span></span><span></span><span></span></div>'
         . logoInvenTour('simbolo', 'emblema-central') . '</div>';
}

/** Escapa para HTML. Toda saída de dado do banco passa por aqui. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Valida um destino de redirecionamento vindo da URL.
 *
 * Sem isto, /login?destino=https://site-falso funcionaria como
 * redirecionamento aberto: o link sairia do domínio e levaria a pessoa a uma
 * cópia da tela de entrada. Só caminho interno é aceito, e "//" é recusado
 * porque o navegador o lê como protocolo relativo (//outro.site).
 */
function destinoSeguro(?string $destino, string $padrao = '/painel'): string
{
    if (!is_string($destino) || $destino === '' || $destino[0] !== '/') {
        return $padrao;
    }
    if (str_starts_with($destino, '//') || str_contains($destino, "\n") || str_contains($destino, "\r")) {
        return $padrao;
    }
    return $destino;
}

/** CNPJ com máscara, só para exibir. No banco fica sempre só o número. */
function formatarCnpj(?string $cnpj): string
{
    // Alfanumérico desde julho de 2026: letras nas 12 primeiras posições.
    $d = preg_replace('/[^0-9A-Z]/', '', strtoupper((string) $cnpj));
    if (strlen($d) !== 14) {
        return (string) $cnpj;
    }
    return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3)
         . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
}

/** Telefone em E.164 devolvido no formato que se lê no Brasil. */
function formatarTelefone(?string $tel): string
{
    $d = preg_replace('/\D/', '', (string) $tel);
    if (str_starts_with($d, '55') && strlen($d) >= 12) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    }
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    }
    return (string) $tel;
}

/** CEP no formato 85760-000. No banco fica só o número. */
function formatarCep(?string $cep): string
{
    $d = preg_replace('/\D/', '', (string) $cep);
    return strlen($d) === 8 ? substr($d, 0, 5) . '-' . substr($d, 5) : (string) $cep;
}

/**
 * Tira o acento, sem depender da extensão intl, que não está instalada.
 *
 * Existe sozinha porque o apelido de URL e o slug do portal precisam da mesma
 * coisa e terminam diferente.
 */
function semAcento(?string $texto): string
{
    return strtr((string) $texto, [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u',
        'û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
        'Á'=>'a','À'=>'a','Ã'=>'a','Â'=>'a','Ä'=>'a','É'=>'e','Ê'=>'e','Ë'=>'e',
        'Í'=>'i','Î'=>'i','Ï'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ö'=>'o','Ú'=>'u',
        'Û'=>'u','Ü'=>'u','Ç'=>'c','Ñ'=>'n',
    ]);
}

/**
 * Transforma um nome em pedaço de URL: "Prefeitura de Pato Branco" vira
 * "prefeitura-de-pato-branco".
 *
 * O "sem-nome" do fim é para URL, onde alguma coisa precisa aparecer depois
 * da barra. Para apelido de gente ele não serve, e usá-lo lá foi o que fez
 * dois usuários sem apelido colidirem na chave única (lição do Orbit).
 */
function apelido(?string $texto, int $limite = 60): string
{
    $t = mb_strtolower(semAcento($texto));
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim((string) $t, '-');
    return mb_substr((string) $t, 0, $limite) ?: 'sem-nome';
}

/**
 * As telas que moram em Configurações: o endereço delas começa por
 * /configuracoes/. A lista é a mesma do .htaccess (regra 1b).
 */
const TELAS_DE_CONFIGURACOES = ['usuarios', 'usuario', 'auditoria', 'destino', 'email', 'modulos', 'comercial', 'eventcity'];

/**
 * Endereço de um registro, no formato /recurso/id-apelido.
 *
 * O id vem primeiro e é o que vale: dois destinos podem ter evento com o
 * mesmo nome, e nome muda com o tempo. O apelido existe só para a URL ser
 * legível e para o link continuar dizendo do que se trata quando alguém o
 * cola num e-mail. Trocar o apelido não quebra o endereço, porque a rota lê
 * apenas os dígitos do começo.
 */
function endereco(string $recurso, ?int $id, ?string $nome = null): string
{
    if (in_array($recurso, TELAS_DE_CONFIGURACOES, true)) {
        $recurso = 'configuracoes/' . $recurso;
    }
    if (!$id) {
        return '/' . $recurso;
    }
    $ap = $nome !== null && trim($nome) !== '' ? '-' . apelido($nome) : '';
    return '/' . $recurso . '/' . $id . $ap;
}

/**
 * Leva o navegador para o endereço amigável quando ele chegou por outro.
 *
 * Cobre o link antigo com ?id=, o /evento/3 digitado sem apelido e o apelido
 * desatualizado depois de o registro ser renomeado. Sem isto, a mesma ficha
 * responderia em vários endereços e a barra mostraria o feio.
 *
 * Só redireciona em GET: num POST o redirecionamento perderia o que foi
 * enviado.
 */
function canonizar(string $recurso, ?int $id, ?string $nome): void
{
    if (!$id || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }
    $certo  = endereco($recurso, $id, $nome);
    $atual  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (rtrim($atual, '/') === $certo) {
        return;
    }
    // Preserva o que veio na query, menos o id, que agora está no caminho.
    $query = $_GET;
    unset($query['id']);
    $sufixo = $query ? '?' . http_build_query($query) : '';
    header('Location: ' . $certo . $sufixo, true, 301);
    exit;
}

/**
 * Interruptor de ligar e desligar, no desenho da chave do tema escuro. O campo
 * oculto leva o estado no formulário (1 ou vazio), e o inventour.js alterna.
 */
function interruptor(string $campo, string $titulo, bool $ligado, string $textoLigado, string $textoDesligado, bool $travado = false): string
{
    return '<button type="button" class="chave-cliente interruptor" role="switch" aria-checked="' . ($ligado ? 'true' : 'false') . '" data-interruptor'
         . ' data-ligado="' . e($textoLigado) . '" data-desligado="' . e($textoDesligado) . '"' . ($travado ? ' disabled' : '') . '>'
         . '<span class="txt"><b>' . e($titulo) . '</b><span data-interruptor-estado>' . e($ligado ? $textoLigado : $textoDesligado) . '</span></span>'
         . '<span class="chave" aria-hidden="true"></span>'
         . '<input type="hidden" name="' . e($campo) . '" value="' . ($ligado ? '1' : '') . '"></button>';
}

/**
 * Zona de arrastar e soltar para envio de arquivo.
 *
 * Todo upload do sistema passa por aqui, para o comportamento ser sempre o
 * mesmo. A zona inteira também é clicável: quem usa teclado não arrasta
 * arquivo, e sem o clique o envio ficaria inacessível.
 */
function zonaDeUpload(
    string $campo = 'arquivo',
    string $aceita = 'application/pdf',
    string $titulo = 'Arraste o arquivo aqui',
    string $ajuda = 'ou clique para escolher',
    bool $autoenviar = true,
    bool $varios = false,
    ?array $recorte = null,
    string $nomeRecorte = 'recortes',
    bool $recorteInteira = false
): string {
    $id = 'zona-' . preg_replace('/[^a-z0-9]/i', '', $campo);
    // Um evento chega com a capa, a galeria e o regulamento: obrigar a mandar
    // um de cada vez é fazer a pessoa repetir o mesmo gesto várias vezes.
    $nome = $varios ? $campo . '[]' : $campo;
    // Com $recorte ([largura, altura]), cada imagem passa pelo enquadramento
    // antes de subir: a pessoa escolhe o pedaço que fica no tamanho padrão.
    // $nomeRecorte separa as zonas de um mesmo formulário; $recorteInteira
    // oferece usar a imagem toda, para logo que não é quadrado nem grande.
    return '<div class="zona-upload" data-autoenviar="' . ($autoenviar ? 'sim' : 'nao') . '"'
         . ($recorte ? ' data-recorte="' . (int) $recorte[0] . 'x' . (int) $recorte[1] . '" data-recorte-nome="' . e($nomeRecorte) . '"' . ($recorteInteira ? ' data-recorte-inteira="1"' : '') : '') . '>'
         . '<div class="zona-alvo" role="button" tabindex="0" aria-controls="' . $id . '">'
         . icone('arquivo', null, 'zona-icone')
         . '<span class="zona-texto">' . e($titulo) . '</span>'
         . '<span class="zona-ajuda">' . e($ajuda) . '</span>'
         . '</div>'
         . '<input type="file" id="' . $id . '" name="' . e($nome) . '" accept="' . e($aceita) . '"'
         . ($varios ? ' multiple' : '') . ' class="oculto-visual">'
         . '<p class="zona-escolhido" hidden></p>'
         . '</div>';
}

/**
 * As marcas que fazem o sistema ser instalável como aplicativo.
 *
 * Ficam numa função porque cada tela de fora do sistema (entrada, convite,
 * primeiro acesso) monta o próprio <head>: repetidas à mão, uma delas ficaria
 * para trás na primeira mudança, e a instalação passaria a funcionar em umas
 * telas e não em outras, que é pior do que não existir.
 */
function cabecaAplicativo(?bool $escuro = null): string
{
    // Tema escuro: a barra de botões do Android, no aplicativo instalado,
    // segue as cores do MANIFESTO (o theme-color só pinta a de cima). Por isso
    // há um segundo manifesto, escuro, com o mesmo "id": o Chrome reconhece o
    // mesmo aplicativo e só troca as cores na próxima verificação dele (de
    // horas a um dia; reinstalar mostra na hora). Sem pessoa logada, vale o
    // que o navegador lembra, e aí o JavaScript da página troca.
    if ($escuro === null) {
        $escuro = class_exists('Auth', false) && ((Auth::usuarioOuNulo()['tema'] ?? '') === 'escuro');
    }
    // Endereço fixo, sem versão na query: é por ele que o Chrome reconhece o
    // aplicativo já instalado, e mudar o endereço a cada publicação faz o
    // celular ver outro aplicativo em vez de uma versão nova do mesmo. Quem
    // evita a cópia velha é o Cache-Control do .htaccess.
    return '<link rel="manifest" href="/manifest' . ($escuro ? '-escuro' : '') . '.webmanifest">' . "\n"
         . '<meta name="color-scheme" content="' . ($escuro ? 'dark' : 'light') . '">' . "\n"
         // A cor da barra do navegador é a mesma da barra do topo (--branco de
         // cada tema): com outra cor, o aplicativo abre com uma faixa que não
         // é de lugar nenhum.
         . '<meta name="theme-color" content="' . ($escuro ? '#141414' : '#FFFFFF') . '">' . "\n"
         . '<meta name="application-name" content="InvenTour">' . "\n"
         // O iPhone não lê o manifesto: para ele, o ícone e o nome vêm daqui.
         . '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n"
         . '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n"
         . '<meta name="apple-mobile-web-app-title" content="InvenTour">' . "\n"
         // Com versão no endereço: o iPhone guarda o ícone antigo pelo nome.
         . '<link rel="apple-touch-icon" href="/assets/icone-180.png?v=20261022">' . "\n";
}

/**
 * Quantas linhas por página em toda lista do sistema.
 *
 * Continua existindo como piso: é o que vale antes de haver usuário na
 * sessão, e o padrão de quem nunca escolheu. A escolha de cada pessoa está em
 * Auth::porPagina(), e é ela que as telas usam.
 */
const POR_PAGINA = 50;

/**
 * Navegação de páginas, no estilo de lista do Joomla.
 *
 * Mostra primeira, anterior, uma janela de números ao redor da atual, próxima
 * e última. A janela existe porque uma lista de trinta páginas encheria a
 * linha inteira de números e nenhum deles ajudaria.
 */
function paginacao(int $total, int $pagina, string $base, array $parametros = []): string
{
    // O tamanho é escolha da pessoa, e não mais o cinquenta escrito no código.
    $porPagina = max(1, Auth::porPagina());
    // A ordem escolhida no cabeçalho vale para todas as páginas da lista.
    foreach (['ord', 'dir'] as $chaveOrdem) {
        if (!isset($parametros[$chaveOrdem]) && isset($_GET[$chaveOrdem]) && is_string($_GET[$chaveOrdem])) {
            $parametros[$chaveOrdem] = $_GET[$chaveOrdem];
        }
    }
    $paginas = (int) ceil($total / $porPagina);
    // Lista que cabe na menor das opções não tem o que paginar nem o que
    // escolher: a barra inteira seria enfeite embaixo de oito linhas.
    if ($paginas <= 1 && $total <= min(Auth::POR_PAGINA_OPCOES)) {
        return '';
    }
    $pagina = max(1, min($pagina, $paginas));
    $liga = function (int $p) use ($base, $parametros): string {
        $q = $parametros;
        if ($p > 1) {
            $q['p'] = $p;
        } else {
            unset($q['p']);
        }
        return $base . ($q ? '?' . http_build_query($q) : '');
    };

    $item = function (int $p, string $rotulo, bool $atual = false, bool $ativo = true) use ($liga): string {
        if (!$ativo) {
            return '<span class="pg desligado">' . $rotulo . '</span>';
        }
        if ($atual) {
            return '<span class="pg atual" aria-current="page">' . $rotulo . '</span>';
        }
        return '<a class="pg" href="' . htmlspecialchars($liga($p), ENT_QUOTES) . '">' . $rotulo . '</a>';
    };

    $inicio = max(1, $pagina - 2);
    $fim    = min($paginas, $pagina + 2);
    $html   = '<nav class="paginacao" aria-label="Páginas">';
    $html  .= '<span class="resumo">' . number_format($total, 0, ',', '.') . ' registros'
            . ($paginas > 1 ? ' · página ' . $pagina . ' de ' . $paginas : '') . '</span>';

    require_once __DIR__ . '/Lista.php';
    $html .= Lista::seletorTamanho(function (array $troca) use ($base, $parametros): string {
        $q = array_filter($troca + $parametros, fn ($v) => $v !== null && $v !== '');
        unset($q['p']);
        return $base . ($q ? '?' . http_build_query($q) : '');
    });

    if ($paginas <= 1) {
        return $html . '</nav>';
    }
    $html  .= '<span class="botoes">';
    $html  .= $item(1, '&laquo;', false, $pagina > 1);
    $html  .= $item($pagina - 1, '&lsaquo;', false, $pagina > 1);
    if ($inicio > 1) {
        $html .= '<span class="pg reticencia">…</span>';
    }
    for ($p = $inicio; $p <= $fim; $p++) {
        $html .= $item($p, (string) $p, $p === $pagina);
    }
    if ($fim < $paginas) {
        $html .= '<span class="pg reticencia">…</span>';
    }
    $html .= $item($pagina + 1, '&rsaquo;', false, $pagina < $paginas);
    $html .= $item($paginas, '&raquo;', false, $pagina < $paginas);
    return $html . '</span></nav>';
}

/**
 * Seletor de mês com passo para trás e para frente.
 *
 * O calendário do navegador serve para saltar longe, mas o uso de todo dia é
 * andar um mês por vez, e para isso ele obriga a abrir o painel, achar a
 * seta e escolher. As duas setas ao lado resolvem em um clique.
 *
 * $extras são os outros filtros da tela, que precisam sobreviver ao passo:
 * mudar de mês não pode apagar a busca nem a pílula que a pessoa escolheu.
 *
 * @param array<string,string> $extras
 */
function navegadorDeMes(string $endereco, string $mes, array $extras = []): string
{
    $passo = static function (int $quantos) use ($endereco, $mes, $extras): string {
        $alvo = date('Y-m', (int) strtotime($mes . '-01 ' . ($quantos > 0 ? '+' : '-')
                                            . abs($quantos) . ' month'));
        $q = http_build_query(array_filter(['mes' => $alvo] + $extras));
        $rotulo = $quantos > 0 ? 'Mês seguinte' : 'Mês anterior';
        return '<a class="passo-mes' . ($quantos > 0 ? '' : ' para-tras') . '" href="'
             . e($endereco) . '?' . e($q) . '" title="' . $rotulo . '" aria-label="' . $rotulo
             . '" rel="nofollow">' . icone('seta') . '</a>';
    };

    return '<div class="nav-mes">' . $passo(-1)
         . '<div class="entrada"><input type="month" name="mes" value="' . e($mes)
         . '" data-enviar-ao-mudar aria-label="Mês"></div>'
         . $passo(1) . '</div>';
}

/**
 * Converte dinheiro digitado para o formato do banco.
 *
 * Aceita "52.800,12" (brasileiro), "52,800.12" (americano) e "52800.12" (cru):
 * o último separador é o decimal. Existe no servidor e não só no navegador
 * porque, sem JavaScript, é o texto do formulário que chega aqui, e gravar
 * 52.800,12 como 52 seria um erro silencioso e caro.
 */
function moedaParaBanco(?string $texto): ?string
{
    $t = preg_replace('/[^\d,.-]/', '', (string) $texto);
    if ($t === '' || $t === null) {
        return null;
    }
    $virgula = strrpos($t, ',');
    $ponto   = strrpos($t, '.');
    if ($virgula !== false && ($ponto === false || $virgula > $ponto)) {
        $t = str_replace(',', '.', str_replace('.', '', $t));
    } elseif ($ponto !== false) {
        $t = str_replace(',', '', $t);
    }
    return is_numeric($t) ? number_format((float) $t, 2, '.', '') : null;
}

/**
 * Campo de escolha com digitação e busca (organizador, local, pessoa).
 *
 * Uma lista suspensa deixa de funcionar quando os registros passam de
 * algumas dezenas: com mil, rolar até o certo é pior que digitar. Aqui a
 * pessoa digita nome, documento ou cidade e escolhe entre os que casam.
 *
 * O que o formulário envia continua sendo o ID, num campo escondido: o texto
 * visível é só para ler. Assim não existe o caso de gravar um nome que não
 * corresponde a registro nenhum.
 *
 * Serve tanto de campo de cadastro quanto de filtro de lista. Como filtro,
 * $enviarAoEscolher faz o formulário ser enviado assim que a pessoa escolhe,
 * e $obrigatorio fica falso porque "nenhum" quer dizer "todos".
 */
/**
 * Combo de múltipla escolha para a linha de filtros de uma lista: a caixa
 * com o ícone do que filtra mostra "Todas", o nome da única escolhida ou
 * "3 atividades"; a lista abre com caixas de marcar de verdade (campo[]),
 * e marcar uma atualiza a lista viva na hora (o "change" do formulário).
 *
 * $o: campo, icone, rotulo (aria), vazio ("Todas as atividades"), plural
 * ("atividades"), opcoes [valor => texto], escolhidos [valor].
 * Com fonte (endpoint no formato do seletorBuscavel) a lista ganha um campo
 * de busca no topo: as opções são as já escolhidas mais o que a busca acha
 * (município: são milhares, não cabem numa lista fixa); buscar é o texto
 * de ajuda do campo.
 */
function multiEscolhaFiltro(array $o): string
{
    $campo = (string) $o['campo'];
    $escolhidos = array_map('strval', (array) ($o['escolhidos'] ?? []));
    // enviar: tela sem lista viva (relatório) envia o formulário ao fechar
    // a lista, se algo mudou, e não a cada caixa marcada.
    $fonte = (string) ($o['fonte'] ?? '');
    $html = '<div class="multi-escolha multi-filtro" data-multi-escolha' . (!empty($o['enviar']) ? ' data-enviar-ao-fechar' : '')
          . ($fonte !== '' ? ' data-fonte="' . e($fonte) . '" data-campo="' . e($campo) . '"' : '') . '>'
          . '<button type="button" class="entrada multi-alvo" aria-haspopup="listbox" aria-expanded="false" aria-label="' . e((string) $o['rotulo']) . '">'
          . icone((string) ($o['icone'] ?? 'lista'))
          . '<span class="multi-fichas" data-vazio="' . e((string) $o['vazio']) . '" data-plural="' . e((string) $o['plural']) . '"></span>'
          . icone('seta', null, 'chevron') . '</button>'
          . '<div class="multi-opcoes" role="listbox" aria-multiselectable="true" hidden>';
    // filtrar: lista longa já na página (organizadores, locais) ganha um
    // campo que esconde o que não bate com o que se digita.
    if ($fonte !== '' || (!empty($o['filtrar']) && count((array) $o['opcoes']) > 8)) {
        $html .= '<div class="entrada multi-busca">' . icone('busca') . '<input type="text" autocomplete="off" spellcheck="false" placeholder="'
               . e((string) ($o['buscar'] ?? 'Digite para buscar')) . '" aria-label="' . e((string) ($o['buscar'] ?? 'Buscar')) . '"></div>'
               . '<p class="multi-nada" hidden>Nada encontrado.</p>';
    }
    foreach ((array) $o['opcoes'] as $valor => $texto) {
        $html .= '<label class="check multi-opcao"><input type="checkbox" name="' . e($campo) . '[]" value="' . e((string) $valor) . '"'
               . ' data-nome="' . e((string) $texto) . '"' . (in_array((string) $valor, $escolhidos, true) ? ' checked' : '') . '><i></i>'
               . '<span><b>' . e((string) $texto) . '</b></span></label>';
    }
    return $html . '</div></div>';
}

function seletorBuscavel(array $o): string
{
    $campo   = (string) ($o['campo'] ?? 'id');
    $idCampo = preg_replace('/[^a-z0-9_]/i', '', $campo);
    $html  = '<div class="busca-viva" data-fonte="' . e((string) $o['fonte']) . '"'
           . ' data-vazio="' . e((string) ($o['nada'] ?? 'Nada encontrado.')) . '"'
           . (!empty($o['enviarAoEscolher']) ? ' data-enviar="1"' : '') . '>';
    $html .= '<input type="hidden" name="' . e($campo) . '" id="' . $idCampo . '"'
           . ' value="' . e((string) ($o['valor'] ?? '')) . '"'
           . (!empty($o['obrigatorio']) ? ' data-obrigatorio="1"' : '')
           . ' data-oque="' . e((string) ($o['oque'] ?? 'o item')) . '">';
    $html .= '<div class="entrada">';
    $html .= icone((string) ($o['icone'] ?? 'busca'));
    $html .= '<input type="text" id="' . $idCampo . '_texto" autocomplete="off" spellcheck="false"'
           . ' placeholder="' . e((string) ($o['vazio'] ?? 'Digite para buscar')) . '"'
           . ' value="' . e((string) ($o['texto'] ?? '')) . '"'
           . (!empty($o['travado']) ? ' readonly' : '') . '>';
    if (empty($o['travado'])) {
        $html .= '<button type="button" class="revelar limpar-busca" title="Limpar" hidden>&times;</button>';
    }
    $html .= '</div>';
    $html .= '<ul class="sugestoes" role="listbox" hidden></ul>';
    $html .= '<p class="ajuda aviso-busca" hidden></p>';
    return $html . '</div>';
}

/**
 * O domínio do e-mail recebe mensagem (tem MX, ou ao menos endereço)?
 *
 * Erro de digitação no domínio só aparece quando o convite volta: um
 * ".rs.gov.br" no lugar de ".pr.gov.br" passa pelo cadastro e o e-mail nunca
 * chega. Endereço que volta ainda derruba a reputação do envio.
 */
function emailDominioRecebe(string $email): bool
{
    $dominio = mb_strtolower(trim(explode('@', $email, 2)[1] ?? ''));
    static $visto = [];
    if ($dominio === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $dominio)) {
        return false;
    }
    return $visto[$dominio] ??= (checkdnsrr($dominio, 'MX') || checkdnsrr($dominio, 'A'));
}

/**
 * Telefone brasileiro no formato do banco: E.164 sem o "+", ou seja
 * 55 + DDD + número.
 *
 * É o formato que o WAHA usa para montar o chatId, e guardar de outro jeito
 * obrigaria a converter em cada disparo, com uma regra diferente em cada
 * lugar que envia.
 *
 * Aceita 8 e 9 dígitos após o DDD, com ou sem máscara, com ou sem o 55. O
 * nono dígito do celular existe desde 2016; fixo continua com 8, e os dois
 * seguem valendo.
 */
function telefoneParaBanco(?string $texto): ?string
{
    $d = preg_replace('/\D/', '', (string) $texto);
    if ($d === '' || $d === null) {
        return null;
    }
    // Descarta o zero de operadora que às vezes vem colado ("0 46 3552-1321").
    // Precisa vir ANTES da checagem de tamanho: com o zero, o número tem 11
    // dígitos e passaria por celular, virando (04) 63552-1321.
    $d = ltrim($d, '0');
    if ($d === '') {
        return null;
    }
    // Até 11 dígitos é número nacional (DDD + número), e ganha o país. Não
    // olhar se começa com 55: o DDD 55 (Santa Maria, Frederico Westphalen)
    // começa igual ao país, e "(55) 9646-4175" era recusado como incompleto.
    if (strlen($d) <= 11) {
        $d = '55' . $d;
    }
    // 12 = fixo (55 + DDD + 8), 13 = celular (55 + DDD + 9). Com 12 ou mais
    // dígitos, tem de começar pelo país: "469991051717" (DDD + 10 dígitos, um
    // 9 repetido) não é número, e antes passava como fixo completo.
    return in_array(strlen($d), [12, 13], true) && str_starts_with($d, '55') ? $d : null;
}

/** Diz se o número é celular, pelo nono dígito. */
function ehCelular(?string $telefone): bool
{
    $d = preg_replace('/\D/', '', (string) $telefone);
    if (str_starts_with((string) $d, '55') && strlen((string) $d) >= 12) {
        $d = substr((string) $d, 2);
    }
    return strlen((string) $d) === 11;
}

/**
 * Há quanto tempo, em linguagem de gente.
 *
 * "há 3 minutos" diz mais do que "05/09/2026 16:42" numa lista de atividade,
 * onde o que importa é se foi agora ou na semana passada. A data exata fica
 * no title, para quem precisar dela.
 */
function quandoFoi(?string $quando): string
{
    if (!$quando) {
        return '';
    }
    $seg = time() - strtotime($quando);
    return match (true) {
        $seg < 60      => 'agora',
        $seg < 3600    => 'há ' . floor($seg / 60) . ' min',
        $seg < 86400   => 'há ' . floor($seg / 3600) . 'h',
        $seg < 172800  => 'ontem',
        $seg < 604800  => 'há ' . floor($seg / 86400) . ' dias',
        default        => dataBr($quando, 'd/m/Y'),
    };
}

/** Tamanho de arquivo em unidade que se lê de relance. */
function tamanhoLegivel(?int $bytes): string
{
    $b = (int) $bytes;
    return match (true) {
        $b >= 1048576 => number_format($b / 1048576, 1, ',', '.') . ' MB',
        $b >= 1024    => number_format($b / 1024, 0, ',', '.') . ' KB',
        default       => $b . ' B',
    };
}

/** Primeiro nome, para caber onde o nome inteiro não cabe. */
function primeiroNome(?string $nome): string
{
    $p = preg_split('/\s+/', trim((string) $nome));
    return $p[0] ?? '';
}

/** Iniciais, usadas quando a pessoa não tem foto. */
function iniciaisDe(string $nome): string
{
    $partes = preg_split('/\s+/u', trim($nome)) ?: [];
    $texto  = mb_substr($partes[0] ?? '?', 0, 1);
    if (count($partes) > 1) {
        $texto .= mb_substr(end($partes), 0, 1);
    }
    return mb_strtoupper($texto);
}

/**
 * Avatar de iniciais, para quando não há foto nem logotipo.
 *
 * A cor sai do próprio nome, sempre a mesma para o mesmo nome. Sem isso,
 * todas as iniciais ficam do mesmo tom e a lista vira uma coluna de círculos
 * idênticos, que não ajuda a achar ninguém.
 *
 * O matiz percorre o círculo de cores inteiro, mas a saturação e o brilho são
 * fixos e baixos, para nenhuma combinação berrar ao lado das outras nem
 * perder contraste com o texto escuro.
 */
function avatarIniciais(?string $nome, string $classe = 'avatar'): string
{
    // No Orbit o matiz vai num style; aqui a CSP não deixa, então o círculo de
    // cores é dividido em 12 classes (matiz-0 a matiz-11, em inventour.css).
    $matiz = crc32(mb_strtolower(trim((string) $nome))) % 12;
    return '<span class="' . e($classe) . ' iniciais matiz-' . $matiz . '" aria-hidden="true">'
         . e(iniciaisDe((string) $nome)) . '</span>';
}

/**
 * Nome com iniciais maiúsculas (regra do Luiz, 21/11/2026): "HOTEL SAN
 * PIETRO" vira "Hotel San Pietro" e "CASATUR AGENCIA DE VIAGENS E TURISMO"
 * vira "Casatur Agencia de Viagens e Turismo". Só mexe em nome que está
 * inteiro em maiúsculas (o que veio do Cadastur e de planilhas); nome
 * digitado com minúsculas fica como a pessoa escreveu. Preposições, artigos
 * e conjunções ficam minúsculas (menos na primeira palavra); siglas
 * conhecidas, palavras sem vogal (CTG, GSSR), números romanos e palavras
 * com número continuam em maiúsculas.
 */
function nomeProprio(?string $nome): ?string
{
    if ($nome === null) {
        return null;
    }
    $nome = trim((string) preg_replace('/\s+/u', ' ', $nome));
    if ($nome === '' || !preg_match('/\p{Lu}/u', $nome) || preg_match('/\p{Ll}/u', $nome)) {
        return $nome;
    }
    static $miudas = ['a', 'à', 'ao', 'aos', 'as', 'às', 'o', 'os', 'e', 'de', 'da', 'das', 'do', 'dos', 'em', 'na', 'nas', 'no', 'nos',
                      'com', 'para', 'por', 'pela', 'pelo', 'sem', 'sob', 'del', 'di', 'du', 'la', 'le', 'van', 'von', 'y'];
    static $siglas = ['AABB', 'ABRASEL', 'ACEPB', 'AFFISP', 'APAE', 'ATISFA', 'CAPS', 'CAT', 'CDL', 'CRAS', 'CTG', 'DEPATRAN', 'EPP', 'FADEP', 'FIEP',
                      'FURP', 'IAPAR', 'IAT', 'IFPR', 'IPARDES', 'MEI', 'ME', 'NTI', 'PB', 'PR', 'BR', 'PUC', 'SA', 'SEBRAE', 'SENAC', 'SENAI', 'SENAR',
                      'SESC', 'SESI', 'SUS', 'UBS', 'UFPR', 'UNIDEP', 'UNIMATER', 'UPA', 'UTFPR', 'TV', 'FM', 'AM', 'GNV', 'RV', 'SP', 'SC', 'RS'];
    $palavras = explode(' ', $nome);
    foreach ($palavras as $k => $p) {
        $base = preg_replace('/[^\p{L}]/u', '', $p);
        $minusc = mb_strtolower($p, 'UTF-8');
        if ($base === '' || preg_match('/\d/', $p) || in_array($base, $siglas, true) || preg_match('/^[IVXLC]{2,}$/', $base)
            || !preg_match('/[AEIOUÁÉÍÓÚÂÊÔÃÕÀYaeiou]/u', $base) || (mb_strlen($base) === 1 && !($k > 0 && in_array($minusc, $miudas, true)))) {
            continue;
        }
        if ($k > 0 && in_array($minusc, $miudas, true)) {
            $palavras[$k] = $minusc;
            continue;
        }
        // Primeira letra maiúscula também depois de hífen e apóstrofo (Apart-Hotel, D'Ávila).
        $palavras[$k] = preg_replace_callback("/(^|[-'’.])(\p{Ll})/u", fn ($m) => $m[1] . mb_strtoupper($m[2], 'UTF-8'), $minusc);
    }
    return implode(' ', $palavras);
}
