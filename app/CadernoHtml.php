<?php
/**
 * As páginas do caderno oficial, em HTML para o wkhtmltopdf.
 * Softsul Software
 *
 * O wkhtmltopdf usa um WebKit antigo: nada de grid nem de flex com gap,
 * só tabela e bloco. Fonte Liberation Sans (instalada no servidor, a mesma
 * métrica da Arial). Títulos h1 (capítulo) e h2 (ficha do MTur) viram o
 * sumário; h3 em diante não.
 */
declare(strict_types=1);

final class CadernoHtml
{
    private array $c;
    private array $d;

    public function __construct(array $caderno, array $dados)
    {
        $this->c = $caderno;
        $this->d = $dados;
    }

    private static function e(?string $t): string
    {
        return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
    }

    private static function n(float|int|string|null $v, int $casas = 0): string
    {
        $f = (float) $v;
        $casas = $casas ?: (floor($f) == $f ? 0 : 2);
        return number_format($f, $casas, ',', '.');
    }

    // ------------------------------------------------------------ moldura

    public function documento(string $corpo): string
    {
        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>' . $this->css() . '</style></head><body>' . $corpo . '</body></html>';
    }

    private function css(): string
    {
        return <<<'CSS'
body{font-family:"Liberation Sans",Arial,sans-serif;font-size:9.6pt;color:#1f2a37;line-height:1.42;margin:0}
h1{font-size:19pt;color:#0f5f5c;margin:0 0 4mm;padding-bottom:2mm;border-bottom:1.2pt solid #20b295;page-break-after:avoid}
h2{font-size:12.5pt;color:#0f5f5c;margin:7mm 0 2.5mm;page-break-after:avoid}
h2 .codigo{display:inline-block;background:#e6f6f1;color:#0f5f5c;border-radius:3pt;padding:0 4pt;margin-right:4pt;font-size:10.5pt}
h2 .inexistente{color:#8a94a6;font-weight:normal;font-size:10pt}
h3{font-size:11pt;margin:0 0 1.5mm;color:#111827}
h4{font-size:8.6pt;text-transform:uppercase;letter-spacing:.4pt;color:#0f5f5c;margin:3mm 0 1mm}
p{margin:0 0 2.4mm}
.capitulo{page-break-before:always}
.primeiro{page-break-before:auto}
.nulo{color:#8a94a6}
.pequeno{font-size:8pt;color:#5b6474}
table{border-collapse:collapse;width:100%}
table.quadro{margin:1mm 0 3mm;font-size:8.4pt;page-break-inside:auto}
table.quadro th{text-align:left;background:#0f5f5c;color:#fff;padding:1.4mm 2mm;font-weight:bold}
table.quadro td{padding:1.2mm 2mm;border-bottom:.5pt solid #d9dee7;vertical-align:top}
table.quadro tr{page-break-inside:avoid}
table.quadro td.num,table.quadro th.num{text-align:right}
table.quadro tr.total td{font-weight:bold;border-top:1pt solid #0f5f5c}
.item{page-break-inside:avoid;border:.6pt solid #d9dee7;border-radius:3pt;padding:3.5mm 4mm;margin:0 0 4mm}
.item-cab td{vertical-align:top}
.item .tipo{color:#5b6474;font-size:8.6pt;margin-bottom:2mm}
.item .foto{width:46mm;padding-left:4mm;text-align:right}
.item .foto img{width:46mm;height:32mm;object-fit:cover;border-radius:2pt}
.item .qr{width:21mm;padding-left:3mm;text-align:center;font-size:6.5pt;color:#5b6474}
.item .qr img{width:19mm;height:19mm}
table.campos{font-size:8.6pt}
table.campos td{padding:.7mm 0;vertical-align:top;border-bottom:.4pt dotted #e1e5ec}
table.campos td.r{width:38%;color:#5b6474;padding-right:3mm}
.aus{color:#8a94a6;font-style:italic}
.texto-longo{font-size:8.8pt;margin:1.5mm 0 0}
table.sub{font-size:7.8pt;margin:.6mm 0}
table.sub th{text-align:left;color:#5b6474;font-weight:bold;border-bottom:.5pt solid #d9dee7;padding:.4mm 1.5mm .4mm 0}
table.sub td{padding:.4mm 1.5mm .4mm 0;border-bottom:.3pt dotted #e1e5ec}
.c-pagina{width:210mm;height:296mm;position:relative;overflow:hidden;background:#fff}
.c-foto{height:118mm;background-size:cover;background-position:center;position:relative}
.c-veu{position:absolute;left:0;right:0;bottom:0;height:30mm;background:-webkit-linear-gradient(top,rgba(0,0,0,0),rgba(0,0,0,.45))}
.c-credito{position:absolute;right:10mm;bottom:4mm;color:#fff;font-size:7.5pt;opacity:.9}
.c-sem-foto{overflow:hidden}
.c-circulo{position:absolute;border-radius:50%;background:rgba(255,255,255,.07)}
.c-circulo.c1{width:150mm;height:150mm;right:-45mm;top:-55mm}
.c-circulo.c2{width:64mm;height:64mm;right:92mm;top:44mm;background:rgba(255,255,255,.05)}
.c-circulo.c3{width:40mm;height:40mm;left:18mm;top:22mm;background:rgba(255,255,255,.06)}
.c-emblema{position:absolute;right:26mm;top:26mm;height:52mm;background:#fff;border-radius:50%;padding:7mm}
.c-faixa{color:#fff;padding:11mm 18mm 12mm}
.c-sobre{font-size:9pt;letter-spacing:2pt;text-transform:uppercase;opacity:.8}
.c-titulo{font-size:28pt;font-weight:bold;line-height:1.12;margin:3mm 0 2mm}
.c-sub{font-size:12.5pt;opacity:.92;margin-bottom:2mm}
.c-lugar{font-size:11pt;font-weight:bold;opacity:.95;margin-top:3mm}
table.c-numeros{margin:9mm 18mm 0;width:174mm}
table.c-numeros td{width:25%;padding:0 4mm 0 0;vertical-align:top}
table.c-numeros b{display:block;font-size:22pt;line-height:1.1}
table.c-numeros span{display:block;font-size:8.6pt;color:#5b6474;margin-top:1mm}
table.c-meta{margin:8mm 18mm 0;width:174mm;border-top:.6pt solid #d9dee7}
table.c-meta td{padding:3mm 4mm 0 0;vertical-align:top;width:25%}
table.c-meta span{display:block;font-size:7.5pt;text-transform:uppercase;letter-spacing:.6pt;color:#8a94a6}
table.c-meta b{display:block;font-size:9.5pt;margin-top:.8mm}
.c-chancela{margin:7mm 18mm 0;display:block;border:1pt solid #e2620b;color:#e2620b;padding:2mm 3.5mm;border-radius:3pt;font-weight:bold;font-size:9pt}
table.c-rodape{position:absolute;left:18mm;right:18mm;bottom:14mm;width:174mm;border-top:.6pt solid #d9dee7}
table.c-rodape td{padding-top:5mm;vertical-align:middle}
.c-marca img{height:12mm;float:left;margin-right:3mm}
.c-marca b{display:block;font-size:11pt;color:#0f5f5c;padding-top:1mm}
.c-marca span{display:block;font-size:7.5pt;color:#5b6474}
.c-qr{text-align:right}
.c-qr div{display:inline-block;vertical-align:middle;text-align:right;margin-right:3mm}
.c-qr b{display:block;font-size:8.5pt}
.c-qr span{display:block;font-size:7.5pt;color:#5b6474}
.c-qr .c-codigo{font-weight:bold;color:#1f2a37;margin-top:.8mm}
.c-qr img{width:24mm;height:24mm;vertical-align:middle}
.capa{height:258mm;position:relative}
.capa .faixa{background:#0f5f5c;color:#fff;padding:22mm 16mm 18mm;margin:0 -16mm}
.capa .sobre{font-size:10pt;letter-spacing:1.5pt;text-transform:uppercase;color:#9fe3cf}
.capa .titulo{font-size:30pt;font-weight:bold;line-height:1.15;margin:4mm 0 3mm}
.capa .sub{font-size:13pt;color:#d6f3ea}
.capa .meta{margin-top:12mm;font-size:10pt;color:#1f2a37}
.capa .meta td{padding:1.2mm 6mm 1.2mm 0}
.capa .rodape{position:absolute;bottom:0;left:0;right:0}
.capa .rodape td{vertical-align:bottom}
.capa .qr img{width:30mm;height:30mm}
.capa .marca img{height:13mm}
.chancela{display:inline-block;border:1pt solid #e2620b;color:#e2620b;padding:1.5mm 3mm;border-radius:3pt;font-weight:bold;font-size:9pt;margin-top:6mm}
.sumario td{padding:0;height:6.1mm;vertical-align:bottom;font-size:9.4pt}
.sumario td.pg{text-align:right;width:12mm}
.sumario td.titulo{border-bottom:.5pt dotted #b9c1ce}
.sumario tr.n1 td{font-weight:bold;color:#0f5f5c;padding-top:2.2mm}
.sumario tr.n2 td.titulo{padding-left:6mm}
.marco td{vertical-align:top;padding:1.4mm 0;border-bottom:.4pt dotted #d9dee7}
.marco td.data{width:24mm;font-weight:bold;color:#0f5f5c}
.indicadores td{border:.5pt solid #d9dee7;padding:2mm 3mm;width:33%;vertical-align:top}
.indicadores b{display:block;font-size:13pt;color:#0f5f5c}
.narrativa .subtitulo{font-weight:bold;color:#0f5f5c;margin-top:3mm;font-size:10.5pt}
.titulo-xp{font-size:11pt;font-weight:bold;margin-bottom:1mm}
.narrativa ul{margin:0 0 2.4mm 5mm;padding:0}
.divisor{height:240mm;position:relative}
.divisor .bloco{position:absolute;top:80mm;left:0;right:0;border-left:3mm solid #20b295;padding:6mm 8mm}
.divisor .bloco h1{border:0;font-size:24pt;margin:0}
.divisor .bloco p{font-size:10.5pt;color:#5b6474;margin-top:3mm}
.xp{page-break-inside:avoid;border:.6pt solid #d9dee7;border-radius:3pt;padding:3mm 4mm;margin:0 0 3.5mm}
CSS;
    }

    // ------------------------------------------------------------ partes fixas

    private function qr(string $url, int $tamanho = 4): string
    {
        $png = shell_exec('qrencode -t PNG -o - -s ' . $tamanho . ' -m 1 -l M ' . escapeshellarg($url) . ' 2>/dev/null');
        return $png ? 'data:image/png;base64,' . base64_encode($png) : '';
    }

    public function capa(): string
    {
        $e = $this->d['empresa'];
        $cfg = $this->d['config'];
        $cor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $e['cor_acento']) ? (string) $e['cor_acento'] : '#0E7C66';
        $verificar = $this->d['portal'] . '/verificar/' . $this->c['codigo'];
        $marca = 'file://' . ($this->d['emblema'] ?? ($this->d['raiz'] . '/public/assets/icone-512.png'));

        // Os números do inventário que o caderno traz.
        $porCat = ['A' => 0, 'B' => 0, 'C' => 0];
        $hoteis = 0;
        $leitos = 0;
        foreach ($this->d['itens'] as $i) {
            $f = $this->d['fichas'][(int) $i['ficha_id']] ?? null;
            if (!$f) {
                continue;
            }
            $porCat[$f['categoria']]++;
            if ((int) $f['ordem'] === 20) {
                $hoteis++;
                $leitos += (int) $i['capacidade_leitos'];
            }
        }
        $numeros = [[count($this->d['itens']), 'itens inventariados'], [$porCat['C'], 'atrativos turísticos'],
                    [$hoteis, 'meios de hospedagem'], [$leitos, 'leitos']];
        if ($this->d['experiencias']) {
            $numeros[3] = [count($this->d['experiencias']), 'experiências'];
        }

        // A foto da capa é a escolhida na Encadernação: tirada sozinha do
        // inventário, saía a primeira que houvesse, às vezes uma foto de teste.
        $foto = $this->d['foto_capa'] ? ['arquivo' => $this->d['foto_capa'], 'nome' => $cfg['credito_capa'] ?? ''] : null;

        $chancela = $this->c['previa'] ? 'Versão preliminar: inclui fichas ainda não homologadas'
            : ($e['modo_operacao'] === 'projeto' ? 'Documento estruturado via InvenTour' : '');
        $topo = $foto
            ? '<div class="c-foto" style="background-image:url(\'file://' . self::e($foto['arquivo']) . '\')"><div class="c-veu"></div>'
              . ($cfg['desfocar_capa'] ? '<img class="c-emblema" src="' . self::e($marca) . '" alt="">' : '')
              . ($foto['nome'] !== '' ? '<div class="c-credito">' . self::e($foto['nome']) . '</div>' : '') . '</div>'
            : '<div class="c-foto c-sem-foto" style="background:' . $cor . '"><div class="c-circulo c1"></div><div class="c-circulo c2"></div><div class="c-circulo c3"></div>'
              . '<img class="c-emblema" src="' . self::e($marca) . '" alt=""></div>';
        $h = '<div class="c-pagina">' . $topo
            . '<div class="c-faixa" style="background:' . $cor . '"><div class="c-sobre">Inventário da Oferta Turística</div>'
            . '<div class="c-titulo">' . self::e($cfg['titulo']) . '</div>'
            . ($cfg['subtitulo'] !== '' ? '<div class="c-sub">' . self::e($cfg['subtitulo']) . '</div>' : '')
            . '<div class="c-lugar">' . self::e(trim(($e['municipio_nome'] ?? $e['nome_fantasia']) . ($e['municipio_uf'] ? ' · ' . $e['municipio_uf'] : ''))) . ' · ' . (int) $this->c['ano'] . '</div></div>'
            . '<table class="c-numeros"><tr>';
        foreach ($numeros as [$n, $rotulo]) {
            $h .= '<td><b style="color:' . $cor . '">' . self::n($n) . '</b><span>' . self::e($rotulo) . '</span></td>';
        }
        $h .= '</tr></table>'
            . '<table class="c-meta"><tr><td><span>Versão</span><b>' . (int) $this->c['versao'] . '</b></td>'
            . '<td><span>Recorte</span><b>' . self::e(Caderno::descreverRecorte($this->c['recorte'])) . '</b></td>'
            . '<td><span>Gerado em</span><b>' . date('d/m/Y') . '</b></td>'
            . '<td><span>Metodologia</span><b>Ministério do Turismo</b></td></tr></table>'
            . ($chancela !== '' ? '<div class="c-chancela">' . self::e($chancela) . '</div>' : '')
            . '<table class="c-rodape"><tr><td class="c-marca"><img src="' . self::e($marca) . '" alt=""><div><b>InvenTour</b>'
            . '<span>Produto Softsul Software e Zapping Consultoria</span></div></td>'
            . '<td class="c-qr"><div><b>Verifique a autenticidade</b><span>Leia o QR ou acesse ' . self::e(preg_replace('#^https://#', '', $this->d['portal'])) . '/verificar</span>'
            . '<span class="c-codigo">Código ' . self::e(Caderno::codigoLegivel((string) $this->c['codigo'])) . '</span></div>'
            . '<img src="' . $this->qr($verificar, 6) . '" alt=""></td></tr></table></div>';
        return $h;
    }

    public function fichaTecnica(): string
    {
        $e = $this->d['empresa'];
        $g = $this->d['governanca'];
        $cfg = $this->d['config'];
        $linhas = [
            ['Título', $cfg['titulo']],
            ['Destino', $e['nome'] . ($e['municipio_nome'] ? ' · ' . $e['municipio_nome'] . '/' . $e['municipio_uf'] : '')],
            ['População', $e['populacao'] ? self::n($e['populacao']) . ' habitantes (IBGE)' : ''],
            ['Órgão oficial de turismo', trim(($g['orgao_turismo_nome'] ?? '') . (!empty($g['orgao_turismo_lei']) ? ' (' . $g['orgao_turismo_lei'] . ')' : ''))],
            ['Conselho Municipal de Turismo', trim(($g['comtur_nome'] ?? '') . (!empty($g['comtur_lei']) ? ' (' . $g['comtur_lei'] . ')' : ''))],
            ['Ano de referência', (string) $this->c['ano']],
            ['Versão', $this->c['versao'] . ', gerada em ' . date('d/m/Y H:i')],
            ['Recorte', Caderno::descreverRecorte($this->c['recorte'])],
            ['Fichas e itens', count($this->d['fichas']) . ' fichas do Ministério do Turismo, ' . self::n(count($this->d['itens'])) . ' itens'],
            ['Situação dos itens', $this->c['previa'] ? 'Versão preliminar: inclui itens em pré-cadastro, em campo e em conferência' : 'Somente itens homologados'],
            ['Metodologia', 'Inventário da Oferta Turística do Ministério do Turismo (fichas de 2011), categorias A, B e C'],
            ['Código de verificação', Caderno::codigoLegivel((string) $this->c['codigo']) . ' em ' . preg_replace('#^https://#', '', $this->d['portal']) . '/verificar'],
        ];
        $h = '<h1>Ficha técnica</h1><table class="quadro">';
        foreach ($linhas as [$r, $v]) {
            if (trim((string) $v) !== '') {
                $h .= '<tr><td style="width:34%;color:#5b6474">' . self::e($r) . '</td><td>' . self::e((string) $v) . '</td></tr>';
            }
        }
        $h .= '</table>';
        if (trim($cfg['equipe']) !== '') {
            $h .= '<h4>Equipe técnica</h4><table class="quadro">';
            foreach (preg_split('/\R/', trim($cfg['equipe'])) as $l) {
                [$nome, $funcao] = array_map('trim', explode(':', $l, 2) + [1 => '']);
                $h .= '<tr><td style="width:50%">' . self::e($nome) . '</td><td>' . self::e($funcao) . '</td></tr>';
            }
            $h .= '</table>';
        }
        if (trim($cfg['fontes']) !== '') {
            $h .= '<h4>Fontes</h4><p class="pequeno">' . nl2br(self::e($cfg['fontes'])) . '</p>';
        }
        $h .= '<h4>Sistema</h4><p class="pequeno">Coleta, conferência, homologação e edição no InvenTour, Produto Softsul Software e Zapping Consultoria. '
            . 'Campos sem informação ficam de fora; quando a ficha registra a ausência, aparece por extenso (Não se aplica, Não informou, Não existe). '
            . 'Não são publicados CPF nem contato pessoal da equipe de pesquisa.</p>';
        return $h;
    }

    /** Quantas páginas o sumário vai ocupar (para numerar o conteúdo antes de imprimi-lo). */
    public function paginasDoSumario(array $d): int
    {
        $linhas = 1 + count($d['narrativas']) + ($d['marcos'] ? 1 : 0);
        foreach ($d['categorias'] as $cat) {
            $linhas += 1 + count(array_filter($d['fichas'], fn ($f) => $f['categoria'] === $cat));
        }
        $linhas += $d['experiencias'] ? 2 : 0;
        return max(1, (int) ceil(($linhas + 4) / 36));
    }

    public function sumario(array $entradas): string
    {
        $h = '<h1>Sumário</h1><table class="sumario">';
        foreach ($entradas as $t) {
            $h .= '<tr class="n' . (int) $t['nivel'] . '"><td class="titulo">' . self::e($t['titulo']) . '</td><td class="pg">' . (int) $t['pagina'] . '</td></tr>';
        }
        return $h . '</table>';
    }

    // ------------------------------------------------------------ o destino

    public function destino(): string
    {
        $h = '';
        $primeiro = true;
        foreach ($this->d['narrativas'] as $n) {
            // Subtítulo do texto não entra no sumário: h3 e h4 viram parágrafo de destaque.
            $texto = preg_replace(['#<h[34]>#i', '#</h[34]>#i'], ['<p class="subtitulo">', '</p>'], (string) $n['conteudo_html']);
            $h .= '<div class="capitulo' . ($primeiro ? ' primeiro' : '') . '"><h1>' . self::e($n['titulo']) . '</h1><div class="narrativa">' . $texto . '</div>';
            $ind = json_decode((string) ($n['indicadores'] ?? ''), true) ?: [];
            if ($ind) {
                $rotulos = ['acts' => 'Atividades características do turismo', 'cadastur' => 'Prestadores no Cadastur', 'empregos' => 'Empregos formais no turismo',
                            'vaf' => 'VAF de alojamento e alimentação (R$)', 'idat' => 'Índice estadual', 'dti' => 'Maturidade DTI'];
                $h .= '<p class="subtitulo" style="font-weight:bold;color:#0f5f5c;margin-top:4mm">Indicadores</p><table class="indicadores"><tr>';
                $k = 0;
                foreach ($ind as $chave => $v) {
                    if ($k && $k % 3 === 0) {
                        $h .= '</tr><tr>';
                    }
                    $h .= '<td><span class="pequeno">' . self::e($rotulos[$chave] ?? $chave) . '</span><b>' . self::e((string) $v['valor']) . '</b>'
                        . '<span class="pequeno">' . self::e(trim(($v['fonte'] ?? '') . ($v['ano'] ? ', ' . $v['ano'] : ''), ', ')) . '</span></td>';
                    $k++;
                }
                $h .= str_repeat('<td style="border:0"></td>', (3 - $k % 3) % 3) . '</tr></table>';
            }
            $h .= '</div>';
            $primeiro = false;
        }
        if ($this->d['marcos']) {
            $h .= '<div class="capitulo' . ($primeiro ? ' primeiro' : '') . '"><h1>Linha do tempo do turismo</h1><table>';
            foreach ($this->d['marcos'] as $m) {
                $data = match ($m['precisao']) {
                    'ano' => substr((string) $m['data_marco'], 0, 4),
                    'mes' => date('m/Y', strtotime((string) $m['data_marco'])),
                    default => date('d/m/Y', strtotime((string) $m['data_marco'])),
                };
                $h .= '<tr class="marco"><td class="data">' . $data . '</td><td><b>' . self::e($m['titulo']) . '</b>'
                    . ($m['descricao'] ? '<br><span class="pequeno">' . self::e($m['descricao']) . '</span>' : '') . '</td></tr>';
            }
            $h .= '</table></div>';
            $primeiro = false;
        }
        if ($primeiro) {
            $h .= '<h1>O destino</h1><p class="nulo">Os textos de apresentação, histórico, economia do turismo, aspectos geográficos e infraestrutura básica ainda não foram escritos em O destino.</p>';
        }
        return $h;
    }

    // ------------------------------------------------------------ categorias

    public function categoria(string $cat): string
    {
        $titulo = Caderno::CATEGORIAS[$cat];
        $explica = [
            'A' => 'As estruturas e os serviços que dão condições ao município de receber o visitante: acesso, comunicação, segurança, saúde, educação e outros serviços de apoio.',
            'B' => 'A oferta de serviços turísticos: hospedagem, alimentação, agenciamento, transporte, eventos, lazer e entretenimento.',
            'C' => 'Os elementos que motivam a visita: atrativos naturais e culturais, atividades econômicas, realizações técnicas e científicas e eventos programados.',
        ][$cat];
        $h = '<div class="divisor"><div class="bloco"><h1>' . self::e($titulo) . '</h1><p>' . self::e($explica) . '</p></div></div>';
        $fichas = array_filter($this->d['fichas'], fn ($f) => $f['categoria'] === $cat);
        $porFicha = [];
        foreach ($this->d['itens'] as $i) {
            $porFicha[(int) $i['ficha_id']][] = $i;
        }
        if ($cat === 'B') {
            $h .= $this->quadroLeitos();
        }
        foreach ($fichas as $fid => $f) {
            $itens = $porFicha[$fid] ?? [];
            $h .= '<h2><span class="codigo">' . self::e($f['codigo']) . '</span> ' . self::e($f['nome'])
                . ($itens ? '' : ' <span class="inexistente">(inexistente)</span>') . '</h2>';
            if (!$itens) {
                continue;
            }
            $h .= $this->quadroResumo($itens);
            foreach ($itens as $i) {
                if ($this->temDetalhe($i, $f)) {
                    $h .= $this->item($i, $f);
                }
            }
        }
        return $h;
    }

    /** Quadro de UHs e leitos dos meios de hospedagem (B1). */
    private function quadroLeitos(): string
    {
        $hoteis = array_filter($this->d['itens'], fn ($i) => (int) ($this->d['fichas'][(int) $i['ficha_id']]['ordem'] ?? 0) === 20);
        if (!$hoteis) {
            return '';
        }
        $h = '<h2>Quadro de unidades habitacionais e leitos</h2><table class="quadro"><tr><th>Meio de hospedagem</th><th>Tipo</th><th class="num">UH</th><th class="num">Leitos</th></tr>';
        $uh = $leitos = 0;
        foreach ($hoteis as $i) {
            $uh += (int) $i['capacidade_uh'];
            $leitos += (int) $i['capacidade_leitos'];
            $h .= '<tr><td>' . self::e($i['nome']) . '</td><td>' . self::e((string) $i['tipo_nome']) . '</td><td class="num">'
                . ($i['capacidade_uh'] !== null ? self::n($i['capacidade_uh']) : '<span class="nulo">-</span>') . '</td><td class="num">'
                . ($i['capacidade_leitos'] !== null ? self::n($i['capacidade_leitos']) : '<span class="nulo">-</span>') . '</td></tr>';
        }
        return $h . '<tr class="total"><td colspan="2">Total (' . self::n(count($hoteis)) . ' meios de hospedagem)</td><td class="num">' . self::n($uh) . '</td><td class="num">' . self::n($leitos) . '</td></tr></table>';
    }

    private function quadroResumo(array $itens): string
    {
        $h = '<table class="quadro"><tr><th style="width:34%">Nome</th><th>Endereço</th><th style="width:17%">Telefone</th><th style="width:13%">Plus code</th></tr>';
        foreach ($itens as $i) {
            $end = trim(implode(', ', array_filter([trim(($i['logradouro'] ?? '') . ($i['numero'] ? ', ' . $i['numero'] : '')), $i['bairro']])));
            $h .= '<tr><td><b>' . self::e($i['nome']) . '</b>' . ($i['tipo_nome'] ? '<br><span class="pequeno">' . self::e($i['tipo_nome']) . '</span>' : '') . '</td>'
                . '<td>' . ($end !== '' ? self::e($end) : '<span class="nulo">-</span>') . '</td>'
                . '<td>' . self::e(self::fone($i['telefone'] ?: $i['whatsapp'])) . '</td>'
                . '<td>' . self::e((string) $i['plus_code']) . '</td></tr>';
        }
        return $h . '</table>';
    }

    /** A ficha detalhada só sai quando há mais que o quadro já mostra. */
    private function temDetalhe(array $i, array $f): bool
    {
        return $f['forma'] === 'municipio' || $i['valores'] || $i['acess'] || $i['foto'] || trim((string) $i['descricao_tecnica']) !== ''
            || $i['cnpj'] || $i['cadastur'] || $i['site'] || $i['email'] || $i['capacidade_uh'] || $i['conservacao']
            || count(array_filter((array) (json_decode((string) $i['horario'], true) ?: []), 'is_array')) > 0;
    }

    private static function fone(?string $t): string
    {
        $d = preg_replace('/\D/', '', (string) $t);
        // Gravado com o 55 do país (5546999...): sai como número nacional.
        if (strlen($d) >= 12 && str_starts_with($d, '55')) {
            $d = substr($d, 2);
        }
        if (strlen($d) === 11) {
            return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
        }
        if (strlen($d) === 10) {
            return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
        }
        return (string) $t;
    }

    private function item(array $i, array $f): string
    {
        $foto = '';
        if ($i['foto']) {
            $base = $this->d['pasta_fotos'] . '/' . basename((string) $i['foto']['arquivo']);
            $media = preg_replace('/\.jpg$/', '-m.jpg', $base);
            $arquivo = is_readable($media) ? $media : (is_readable($base) ? $base : null);
            if ($arquivo) {
                $foto = '<td class="foto"><img src="file://' . self::e($arquivo) . '" alt=""></td>';
            }
        }
        $url = $this->d['portal'] . '/qr/' . $this->c['codigo'] . '/' . (int) $i['id'];
        $qr = '<td class="qr"><img src="' . $this->qr($url, 3) . '" alt=""><br>Ficha viva</td>';
        $tipo = trim(($i['tipo_pai'] ? $i['tipo_pai'] . ' / ' : '') . ($i['tipo_nome'] ?? ''));

        $h = '<div class="item"><table class="item-cab"><tr><td><h3>' . self::e($i['nome']) . '</h3>'
            . '<div class="tipo">' . self::e($f['codigo'] . ' ' . $f['nome'] . ($tipo !== '' ? ' · ' . $tipo : ''))
            . ($this->c['previa'] && !in_array($i['situacao'], ['homologada', 'desatualizada'], true)
                ? ' · <span style="color:#e2620b">' . self::e(Inventario::SITUACOES[$i['situacao']][0] ?? $i['situacao']) . '</span>' : '') . '</div>'
            . $this->camposDoNucleo($i) . '</td>' . $foto . $qr . '</tr></table>';
        if (trim((string) $i['descricao_tecnica']) !== '') {
            $h .= '<h4>Descrição</h4><p class="texto-longo">' . nl2br(self::e(trim((string) $i['descricao_tecnica']))) . '</p>';
        }
        $h .= $this->camposEspecificos($i, $f) . $this->acessibilidade($i);
        return $h . '</div>';
    }

    private function camposDoNucleo(array $i): string
    {
        $end = trim(implode(', ', array_filter([trim(($i['logradouro'] ?? '') . ($i['numero'] ? ', ' . $i['numero'] : '')), $i['complemento'], $i['bairro'],
                                                $i['distrito'], $i['cep'] ? 'CEP ' . substr((string) $i['cep'], 0, 5) . '-' . substr((string) $i['cep'], 5) : null])));
        $lista = fn ($j) => implode(', ', array_filter((array) (json_decode((string) $j, true) ?: []), 'is_string'));
        $cnpj = preg_replace('/\D/', '', (string) $i['cnpj']);
        $campos = [
            'Nome popular'          => $i['nome_popular'] !== $i['nome'] ? $i['nome_popular'] : null,
            'Razão social'          => $i['razao_social'] !== $i['nome'] ? $i['razao_social'] : null,
            'CNPJ'                  => strlen($cnpj) === 14 ? substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12) : $i['cnpj'],
            'Cadastur'              => $i['cadastur'] ? $i['cadastur'] . ($i['cadastur_validade'] ? ' (válido até ' . date('d/m/Y', strtotime((string) $i['cadastur_validade'])) . ')' : '') : null,
            'Natureza'              => Inventario::NATUREZAS[$i['natureza']] ?? null,
            'Endereço'              => $end ?: null,
            'Zona'                  => Inventario::ZONAS[$i['zona']] ?? null,
            'Plus code'             => $i['plus_code'],
            'Coordenadas'           => $i['latitude'] !== null ? number_format((float) $i['latitude'], 6, ',', '') . ' ; ' . number_format((float) $i['longitude'], 6, ',', '') : null,
            'Referência'            => $i['referencia'],
            'Telefone'              => $i['telefone'] ? self::fone($i['telefone']) . ($i['ramal'] ? ' ramal ' . $i['ramal'] : '') : null,
            'WhatsApp'              => $i['whatsapp'] ? self::fone($i['whatsapp']) : null,
            'E-mail'                => $i['email'],
            'Site'                  => $i['site'],
            'Instagram'             => $i['instagram'],
            'Funcionamento'         => $this->horario($i['horario']),
            'Formas de pagamento'   => $lista($i['formas_pagamento']) ?: null,
            'Idiomas no atendimento' => trim($lista($i['idiomas']) . ($i['idiomas_outro'] ? ', ' . $i['idiomas_outro'] : ''), ', ') ?: null,
            'Unidades habitacionais' => $i['capacidade_uh'] !== null ? self::n($i['capacidade_uh']) : null,
            'Leitos'                => $i['capacidade_leitos'] !== null ? self::n($i['capacidade_leitos']) : null,
            'Assentos'              => $i['capacidade_assentos'] !== null ? self::n($i['capacidade_assentos']) : null,
            'Capacidade de público' => $i['capacidade_publico'] !== null ? self::n($i['capacidade_publico']) : null,
            'Funcionários'          => $i['funcionarios'] !== null ? self::n($i['funcionarios']) : null,
            'Início da atividade'   => $i['inicio_atividade'] ?: null,
            'Estado de conservação' => Inventario::CONSERVACAO[$i['conservacao']] ?? null,
        ];
        $h = '<table class="campos">';
        foreach ($campos as $r => $v) {
            if ($v !== null && trim((string) $v) !== '') {
                $h .= '<tr><td class="r">' . self::e($r) . '</td><td>' . self::e((string) $v) . '</td></tr>';
            }
        }
        return $h . '</table>';
    }

    private function horario(?string $json): ?string
    {
        $h = json_decode((string) $json, true) ?: [];
        if (!empty($h['24h'])) {
            return 'Aberto 24 horas';
        }
        $partes = [];
        foreach (Inventario::DIAS as $k => $dia) {
            if (!empty($h[$k]) && is_array($h[$k])) {
                $partes[] = mb_substr($dia, 0, 3) . ' ' . implode(' e ', array_map(fn ($p) => ($p[0] ?? '') . ' às ' . ($p[1] ?? ''), $h[$k]));
            }
        }
        if (isset($h['feriados_abre'])) {
            $partes[] = $h['feriados_abre'] ? 'abre em feriados' : 'fecha em feriados';
        }
        if (!$partes && !empty($h['periodos'])) {
            $partes[] = 'atende em: ' . mb_strtolower(implode(', ', array_intersect_key(Inventario::PERIODOS, array_flip((array) $h['periodos']))));
        }
        return $partes ? implode('; ', $partes) : null;
    }

    private function camposEspecificos(array $i, array $f): string
    {
        if (!$i['valores']) {
            return '';
        }
        $h = '';
        foreach ($f['atributos'] as $grupo => $atributos) {
            $linhas = '';
            foreach ($atributos as $a) {
                $v = $i['valores'][(int) $a['id']] ?? null;
                if (!$v) {
                    continue;
                }
                $mostra = $this->valor($a, $v);
                if ($mostra !== '') {
                    $linhas .= '<tr><td class="r">' . self::e($a['rotulo']) . '</td><td>' . $mostra . '</td></tr>';
                }
            }
            if ($linhas !== '') {
                $h .= ($grupo !== '' ? '<h4>' . self::e($grupo) . '</h4>' : '') . '<table class="campos">' . $linhas . '</table>';
            }
        }
        return $h;
    }

    /** O valor de um campo específico, já em HTML. */
    private function valor(array $a, array $v): string
    {
        if ($v['ausencia']) {
            return '<span class="aus">' . self::e(Inventario::AUSENCIAS_TEXTO[$v['ausencia']] ?? $v['ausencia']) . '</span>';
        }
        $outro = $v['valor_outro'] ? ' (' . self::e($v['valor_outro']) . ')' : '';
        switch ($a['tipo']) {
            case 'numero':
                return $v['valor_numero'] !== null ? self::n($v['valor_numero']) : '';
            case 'sim_nao':
                return $v['valor_numero'] === null ? '' : ((float) $v['valor_numero'] ? 'Sim' : 'Não');
            case 'data':
                return $v['valor_texto'] ? date('d/m/Y', strtotime((string) $v['valor_texto'])) : '';
            case 'multipla':
                $l = json_decode((string) $v['valor_texto'], true) ?: [];
                return $l ? self::e(implode(', ', $l)) . $outro : '';
            case 'escolha':
                return $v['valor_texto'] ? self::e((string) $v['valor_texto']) . $outro : '';
            case 'tabela':
                $linhas = json_decode((string) $v['valor_texto'], true) ?: [];
                if (!$linhas) {
                    return '';
                }
                require_once __DIR__ . '/FichaFormulario.php';
                $colunas = FichaFormulario::colunas($a);
                $t = '<table class="sub"><tr>';
                foreach ($colunas as $c) {
                    $t .= '<th>' . self::e($c['rotulo']) . '</th>';
                }
                $t .= '</tr>';
                foreach ($linhas as $linha) {
                    $t .= '<tr>';
                    foreach ($colunas as $k => $c) {
                        $cel = $linha[$k] ?? '';
                        $cel = match ($c['tipo']) {
                            'meses' => implode(', ', array_map(fn ($m) => mb_substr((string) $m, 0, 3), (array) $cel)),
                            'sim_nao' => $cel === '1' ? 'Sim' : ($cel === '0' ? 'Não' : ''),
                            'numero' => $cel !== '' ? self::n($cel) : '',
                            'data' => $cel ? date('d/m/Y', strtotime((string) $cel)) : '',
                            default => (string) $cel,
                        };
                        $t .= '<td>' . self::e($cel) . '</td>';
                    }
                    $t .= '</tr>';
                }
                return $t . '</table>';
            default:
                return $v['valor_texto'] !== null ? nl2br(self::e((string) $v['valor_texto'])) : '';
        }
    }

    private function acessibilidade(array $i): string
    {
        if (!$i['acess'] && $i['acessibilidade_possui'] === null) {
            return '';
        }
        $blocos = Inventario::blocosComuns()['acessibilidade'] ?? [];
        $linhas = '';
        foreach ($blocos as $b) {
            $marcadas = $i['acess'][$b['chave']] ?? [];
            if (!$marcadas) {
                continue;
            }
            $texto = implode(', ', $marcadas) . (!empty($i['acess_outro'][$b['chave']]) ? ' (' . $i['acess_outro'][$b['chave']] . ')' : '');
            $linhas .= '<tr><td class="r">' . self::e($b['rotulo']) . '</td><td>' . self::e($texto) . '</td></tr>';
        }
        if ($linhas === '') {
            return $i['acessibilidade_possui'] !== null && !(int) $i['acessibilidade_possui']
                ? '<h4>Acessibilidade</h4><p class="pequeno">Não possui recursos de acessibilidade.</p>' : '';
        }
        return '<h4>Acessibilidade</h4><table class="campos">' . $linhas . '</table>';
    }

    // ------------------------------------------------------------ anexos

    public function anexos(): string
    {
        $faixas = ['gratuita' => 'Gratuita', 'ate_80' => 'Até R$ 80', 'de_81_a_200' => 'De R$ 81 a R$ 200', 'de_201_a_500' => 'De R$ 201 a R$ 500', 'acima_500' => 'Acima de R$ 500'];
        $dims = ['entretenimento' => 'Entretenimento', 'educacional' => 'Educacional', 'escapismo' => 'Escapismo', 'estetica' => 'Estética'];
        $h = '<h1>Anexo: experiências turísticas</h1><p class="pequeno">Vivências oferecidas nos lugares do inventário, '
            . ($this->c['previa'] ? 'aprovadas ou em curadoria (versão preliminar).' : 'aprovadas pela curadoria.') . '</p>';
        foreach ($this->d['experiencias'] as $x) {
            $d = json_decode((string) $x['dimensoes'], true) ?: [];
            $h .= '<div class="xp"><div class="titulo-xp">' . self::e($x['titulo']) . '</div><div class="pequeno">' . self::e($x['item_nome'])
                . ($x['duracao_minutos'] ? ' · ' . (int) $x['duracao_minutos'] . ' min' : '')
                . ' · grupo de ' . (int) $x['pessoas_min'] . ($x['pessoas_max'] ? ' a ' . (int) $x['pessoas_max'] : '') . ' pessoas'
                . ' · ' . self::e($faixas[$x['faixa_valor']] ?? '') . ($x['agendamento'] ? ' · com agendamento' : '') . '</div>';
            if (trim((string) $x['narrativa']) !== '') {
                $h .= '<p class="texto-longo">' . nl2br(self::e((string) $x['narrativa'])) . '</p>';
            }
            if ($d) {
                $h .= '<table class="campos">';
                foreach ($d as $k => $t) {
                    $h .= '<tr><td class="r">' . self::e($dims[$k] ?? $k) . '</td><td>' . self::e((string) $t) . '</td></tr>';
                }
                $h .= '</table>';
            }
            $h .= '</div>';
        }
        return $h;
    }
}
