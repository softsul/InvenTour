<?php
/**
 * Gráficos do painel, desenhados no servidor em SVG.
 * Softsul Software
 *
 * Como no Orbit: são poucos valores por gráfico, e uma biblioteca de
 * gráfico seria peso sem retorno (e a CSP do painel não aceita script de
 * fora). A CSP também não aceita style no elemento: toda cor vai como
 * atributo fill de SVG. Cores: as dos segmentos quando o gráfico é por
 * segmento; o acento do painel nos demais.
 *
 * Balão detalhado (12/11/2026, no molde do FrameFlow): cada coluna, dia,
 * barra e fatia leva data-dica-json {t: título, l: [[cor, rótulo, valor,
 * destaque]], r: [[rótulo, valor]]}, que o inventour.js desenha no balão da
 * casa ao passar o mouse (ou num toque, no celular): o dia por extenso, cada
 * série com a cor, o total, a comparação com o anterior, a média do
 * período e a participação no total. O mesmo texto vai no aria-label.
 */
declare(strict_types=1);
require_once __DIR__ . '/svg.php';

final class Graficos
{
    private const COR = '#0C6C78';

    private static function cor(?string $c): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $c) ? (string) $c : self::COR;
    }

    private const DIAS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    private static function numero(float $n, int $casas = 0): string
    {
        return number_format($n, $casas, ',', '.');
    }

    private static function pct(float $parte, float $total): string
    {
        return $total > 0 ? str_replace('.', ',', (string) round($parte / $total * 100, 1)) . '%' : '-';
    }

    /** Título do balão: o dia por extenso ("terça, 10/11/2026") quando o item traz a data. */
    private static function tituloDica(array $i): string
    {
        if (!empty($i['data']) && ($t = strtotime((string) $i['data']))) {
            return self::DIAS[(int) date('w', $t)] . ', ' . date('d/m/Y', $t);
        }
        return (string) ($i['titulo'] ?? $i['rotulo']);
    }

    /** Com que o item se compara: dia, mês ou hora anterior, pelo jeito do título. */
    private static function nomeAnterior(array $i): string
    {
        $t = (string) ($i['titulo'] ?? '');
        return !empty($i['data']) ? 'Dia anterior' : (preg_match('~/\d{4}$~', $t) ? 'Mês anterior' : (preg_match('/^\d{2}h$/', $t) ? 'Hora anterior' : 'Anterior'));
    }

    /** "+3 (+25%)", "-1 (-10%)", "igual". */
    private static function variacao(int $agora, int $antes): string
    {
        $d = $agora - $antes;
        if ($d === 0) {
            return 'igual';
        }
        $txt = ($d > 0 ? '+' : '-') . self::numero(abs($d));
        return $antes > 0 ? $txt . ' (' . ($d > 0 ? '+' : '-') . self::pct(abs($d), $antes) . ')' : $txt;
    }

    /** Atributos do balão: o JSON para o inventour.js e o mesmo texto para o leitor de tela. */
    private static function dica(array $d): string
    {
        $falado = [$d['t'] ?? ''];
        foreach (array_merge($d['l'] ?? [], $d['r'] ?? []) as $l) {
            $falado[] = count($l) >= 3 ? $l[1] . ': ' . $l[2] : $l[0] . ': ' . $l[1];
        }
        return ' data-dica-json="' . e((string) json_encode($d, JSON_UNESCAPED_UNICODE)) . '" aria-label="'
             . e(implode('. ', array_filter($falado, 'strlen'))) . '"';
    }

    /**
     * Colunas verticais, uma ou duas séries (empilhadas), com os rótulos
     * e os números embaixo. $itens: [['rotulo' => 'Out', 'a' => 3, 'b' => 1, 'link' => '/eventos?...']].
     * $series: [['chave' => 'a', 'nome' => 'Aprovados', 'cor' => '#...'], ...].
     */
    public static function colunas(array $itens, array $series, string $titulo): string
    {
        if (!$itens) {
            return '';
        }
        $maior = 0;
        foreach ($itens as $i) {
            $maior = max($maior, array_sum(array_map(fn ($s) => (int) ($i[$s['chave']] ?? 0), $series)));
        }
        $maior = max(1, $maior);
        $larg = 100.0 / count($itens);
        $totais = array_map(fn ($i) => array_sum(array_map(fn ($s) => (int) ($i[$s['chave']] ?? 0), $series)), $itens);
        $soma = array_sum($totais);
        $media = $soma / count($itens);
        $svg = '<svg class="grafico-svg grafico-dicas" viewBox="0 0 100 46" preserveAspectRatio="none" role="img" aria-label="' . e($titulo) . '">';
        foreach ($itens as $n => $i) {
            $base = 44.0;
            $x = round($n * $larg + $larg * 0.2, 2);
            $w = round($larg * 0.6, 2);
            $linhas = [];
            $svg .= '<g class="col">';
            foreach ($series as $s) {
                $v = (int) ($i[$s['chave']] ?? 0);
                $linhas[] = [self::cor($s['cor']), $s['nome'], self::numero($v)];
                if ($v <= 0) {
                    continue;
                }
                $h = round($v / $maior * 40, 2);
                $base -= $h;
                $svg .= '<rect class="barra" x="' . $x . '" y="' . round($base, 2) . '" width="' . $w . '" height="' . $h . '" rx="0.8" fill="' . self::cor($s['cor']) . '"></rect>';
            }
            if ($base === 44.0) {
                $svg .= '<rect class="barra" x="' . $x . '" y="43.4" width="' . $w . '" height="0.6" fill="#C9CED6"></rect>';
            }
            if (count($series) > 1) {
                $linhas[] = ['', 'Total', self::numero($totais[$n]), true];
            }
            $rodape = [];
            if ($n > 0) {
                $rodape[] = [self::nomeAnterior($i), self::variacao($totais[$n], $totais[$n - 1])];
            }
            $rodape[] = ['Média do período', self::numero($media, $media == (int) $media ? 0 : 1)];
            $rodape[] = ['Participação no total', self::pct($totais[$n], $soma)];
            // A faixa da coluna inteira pega o mouse: coluna baixa também abre o balão.
            $svg .= '<rect class="pega" x="' . round($n * $larg, 2) . '" y="0" width="' . round($larg, 2) . '" height="46" fill="transparent"'
                  . self::dica(['t' => self::tituloDica($i), 'l' => $linhas, 'r' => $rodape]) . '></rect></g>';
        }
        // Com muitas colunas (24 horas, 30 dias) o número embaixo não cabe:
        // fica só o rótulo, e o número segue no <title> de cada coluna.
        $numeros = count($itens) <= 16;
        $svg .= '</svg><div class="pes-grafico">';
        foreach ($itens as $i) {
            $total = array_sum(array_map(fn ($s) => (int) ($i[$s['chave']] ?? 0), $series));
            // Mil ou mais vira 1,2k: quatro dígitos não cabem na coluna do celular.
            $mostrar = $total >= 1000 ? str_replace('.', ',', (string) round($total / 1000, $total >= 10000 ? 0 : 1)) . 'k' : (string) $total;
            $pe = '<b>' . e($i['rotulo']) . '</b>' . ($numeros ? '<span>' . ($total ? $mostrar : '-') . '</span>' : '');
            $svg .= !empty($i['link']) ? '<a class="pe" href="' . e($i['link']) . '">' . $pe . '</a>' : '<div class="pe">' . $pe . '</div>';
        }
        $svg .= '</div>';
        if (count($series) > 1) {
            $svg .= '<div class="legenda-grafico">';
            foreach ($series as $s) {
                $svg .= '<span>' . self::ponto($s['cor']) . e($s['nome']) . '</span>';
            }
            $svg .= '</div>';
        }
        return '<div class="grafico">' . $svg . '</div>';
    }

    /** Um ponto de legenda na cor pedida (SVG, pela CSP). */
    public static function ponto(?string $cor): string
    {
        return '<svg class="ponto-legenda" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="' . self::cor($cor) . '"/></svg>';
    }

    /**
     * Barras horizontais, uma por linha, com o rótulo à esquerda e o número
     * à direita. $itens: [['rotulo' => 'Gastronômico', 'valor' => 4, 'cor' => '#...', 'link' => '...']].
     */
    public static function barras(array $itens): string
    {
        if (!$itens) {
            return '';
        }
        $maior = max(1, max(array_map(fn ($i) => (int) $i['valor'], $itens)));
        $soma = array_sum(array_map(fn ($i) => (int) $i['valor'], $itens));
        $media = $soma / count($itens);
        $html = '<ul class="barras-h grafico-dicas">';
        foreach (array_values($itens) as $n => $i) {
            $pct = (int) $i['valor'] ? max(2, round((int) $i['valor'] / $maior * 100, 1)) : 0;
            $linha = '<span class="rot">' . self::ponto($i['cor'] ?? null) . e($i['rotulo']) . '</span>'
                   . '<svg class="trilho" viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true">'
                   . '<rect x="0" y="0" width="100" height="8" rx="4" fill="#EEF0F3"/>'
                   . ($pct ? '<rect x="0" y="0" width="' . $pct . '" height="8" rx="4" fill="' . self::cor($i['cor'] ?? null) . '"/>' : '') . '</svg>'
                   . '<b>' . self::numero((int) $i['valor']) . '</b>';
            $dica = self::dica(['t' => (string) $i['rotulo'], 'l' => [[self::cor($i['cor'] ?? null), 'Quantidade', self::numero((int) $i['valor'])]],
                                'r' => [['Participação no total', self::pct((int) $i['valor'], $soma)],
                                        ['Média da lista', self::numero($media, $media == (int) $media ? 0 : 1)],
                                        ['Posição', ($n + 1) . 'º de ' . count($itens)]]]);
            $html .= '<li' . $dica . '>' . (!empty($i['link']) ? '<a href="' . e($i['link']) . '">' . $linha . '</a>' : $linha) . '</li>';
        }
        return $html . '</ul>';
    }

    /**
     * Uma barra só, dividida em partes (a esteira do evento: rascunho,
     * pendente, ajuste, aprovado, recusado), com a legenda clicável.
     * $partes: [['rotulo' => 'Aprovados', 'valor' => 5, 'cor' => '#...', 'link' => '...']].
     */
    public static function faixa(array $partes): string
    {
        $total = array_sum(array_map(fn ($p) => (int) $p['valor'], $partes));
        if (!$total) {
            return '<p class="ajuda">Nada ainda.</p>';
        }
        $html = '<svg class="faixa-partes grafico-dicas" viewBox="0 0 100 10" preserveAspectRatio="none" role="img" aria-label="'
              . e(implode(', ', array_map(fn ($p) => $p['rotulo'] . ': ' . (int) $p['valor'], $partes))) . '">';
        $x = 0.0;
        foreach ($partes as $p) {
            if ((int) $p['valor'] <= 0) {
                continue;
            }
            $w = (int) $p['valor'] / $total * 100;
            $html .= '<rect class="barra" x="' . round($x, 3) . '" y="0" width="' . round($w, 3) . '" height="10" fill="' . self::cor($p['cor']) . '"'
                   . self::dica(['t' => (string) $p['rotulo'], 'l' => [[self::cor($p['cor']), 'Quantidade', self::numero((int) $p['valor'])]],
                                 'r' => [['Participação no total', self::pct((int) $p['valor'], $total)], ['Total da barra', self::numero($total)]]]) . '></rect>';
            $x += $w;
        }
        $html .= '</svg><ul class="legenda-faixa">';
        foreach ($partes as $p) {
            $item = self::ponto($p['cor']) . e($p['rotulo']) . ' <b>' . self::numero((int) $p['valor']) . '</b>';
            $html .= '<li>' . (!empty($p['link']) ? '<a href="' . e($p['link']) . '">' . $item . '</a>' : $item) . '</li>';
        }
        return $html . '</ul>';
    }

    /**
     * Hastes, no desenho do "Batimentos por treino" do Centurion: em cada
     * dia, um ponto cheio embaixo ('baixo', ligado aos outros por uma linha
     * de tendência) e um ponto vazado em cima ('alto', com o número), unidos
     * por uma haste. Grade pontilhada com a escala à esquerda.
     * $itens: [['rotulo' => '24', 'titulo' => 'sáb, 24/10', 'baixo' => 2, 'alto' => 5, 'link' => '...']].
     */
    public static function hastes(array $itens, string $titulo, string $nomeBaixo, string $nomeAlto): string
    {
        if (!$itens) {
            return '';
        }
        $W = 320;
        $H = 150;
        $esq = 26;
        $topo = 22;
        $base = $H - 22;
        $hi = max(1, max(array_map(fn ($i) => max((int) $i['alto'], (int) $i['baixo']), $itens)));
        $hi = (int) (ceil(($hi + 1) / 2) * 2);
        $y = fn (float $v) => round($base - $v / $hi * ($base - $topo), 1);
        $passo = ($W - $esq - 10) / count($itens);
        $x = fn (int $n) => round($esq + $passo * ($n + 0.5), 1);
        $svg = '<svg class="grafico-hastes" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e($titulo) . '">';
        foreach ([0, (int) round($hi / 2), $hi] as $g) {
            $svg .= '<line class="guia" x1="' . $esq . '" y1="' . $y($g) . '" x2="' . ($W - 4) . '" y2="' . $y($g) . '"/>'
                  . '<text class="esc" x="' . ($esq - 6) . '" y="' . ($y($g) + 3) . '">' . self::numero($g) . '</text>';
        }
        $pts = [];
        foreach ($itens as $n => $i) {
            $pts[] = $x($n) . ',' . $y((float) $i['baixo']);
        }
        $svg .= '<polyline class="tend" points="' . implode(' ', $pts) . '"/>';
        $soma = array_sum(array_map(fn ($i) => (int) $i['alto'], $itens));
        $media = $soma / count($itens);
        foreach ($itens as $n => $i) {
            $cx = $x($n);
            $alto = (int) $i['alto'];
            $baixo = (int) $i['baixo'];
            $rodape = [];
            if ($n > 0) {
                $rodape[] = [self::nomeAnterior($i), self::variacao($alto, (int) $itens[$n - 1]['alto'])];
            }
            $rodape[] = ['Média do período', self::numero($media, $media == (int) $media ? 0 : 1)];
            $rodape[] = ['Participação no total', self::pct($alto, $soma)];
            // Uma faixa invisível por dia pega o mouse; a guia tracejada marca o dia.
            $grupo = '<g class="dia-hastes"' . self::dica(['t' => self::tituloDica($i), 'l' => [
                         [self::COR, ucfirst($nomeAlto), self::numero($alto), false],
                         [self::COR, ucfirst($nomeBaixo), self::numero($baixo), false]], 'r' => $rodape]) . '>'
                   . '<rect class="pega" x="' . round($cx - $passo / 2, 1) . '" y="0" width="' . round($passo, 1) . '" height="' . $H . '" fill="transparent"/>'
                   . '<line class="guia-dia" x1="' . $cx . '" y1="' . ($topo - 12) . '" x2="' . $cx . '" y2="' . $base . '"/>';
            if ($alto > $baixo) {
                $grupo .= '<line class="haste" x1="' . $cx . '" y1="' . $y($baixo) . '" x2="' . $cx . '" y2="' . $y($alto) . '"/>'
                        . '<circle class="alto" cx="' . $cx . '" cy="' . $y($alto) . '" r="4"/>';
            }
            if ($alto > 0) {
                $grupo .= '<text class="val" x="' . $cx . '" y="' . round($y($alto) - 8, 1) . '">' . self::numero($alto) . '</text>';
            }
            $grupo .= '<circle class="baixo" cx="' . $cx . '" cy="' . $y($baixo) . '" r="4.5"/>'
                    . '<text class="dia" x="' . $cx . '" y="' . ($H - 6) . '">' . e($i['rotulo']) . '</text></g>';
            $svg .= !empty($i['link']) ? '<a href="' . e($i['link']) . '">' . $grupo . '</a>' : $grupo;
        }
        $svg .= '</svg>';
        $leg = '<div class="legenda-grafico"><span><svg class="ponto-legenda" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="4.5" fill="' . self::COR . '"/></svg>'
             . e($nomeBaixo) . '</span><span><svg class="ponto-legenda" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="3.6" fill="#fff" stroke="' . self::COR . '" stroke-width="1.8"/></svg>'
             . e($nomeAlto) . '</span></div>';
        return '<div class="grafico">' . $svg . $leg . '</div>';
    }
}
