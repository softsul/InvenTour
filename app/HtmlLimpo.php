<?php
/**
 * Limpa o HTML que chega do editor de texto (colado do Word, do Google Docs
 * ou de um site): só ficam as tags da lista, sem estilo, classe, script,
 * evento nem imagem embutida.
 * Softsul Software
 *
 * É o "pipeline de sanitização" do documento 02 (Módulo 2): o texto das
 * narrativas do destino vai para o portal e para o caderno, e um <script> ou
 * um style="..." colado não pode passar. Lista de permitidas (allowlist), e
 * não de proibidas: o que não está aqui vira texto.
 */
declare(strict_types=1);

final class HtmlLimpo
{
    /** tag => atributos aceitos */
    private const PERMITIDAS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h3' => [], 'h4' => [], 'blockquote' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'a' => ['href'],
    ];
    /** Tags cujo conteúdo inteiro é descartado. */
    private const DESCARTAR = ['script', 'style', 'iframe', 'object', 'embed', 'img', 'svg', 'math', 'form', 'input', 'button', 'select', 'textarea', 'head', 'title', 'meta', 'link'];
    /** Sinônimos do Word e do navegador. */
    private const TROCAR = ['h1' => 'h3', 'h2' => 'h3', 'h5' => 'h4', 'h6' => 'h4', 'div' => 'p', 'section' => 'p', 'article' => 'p'];

    public static function limpar(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        // Comentários condicionais do Word e afins saem antes do parser.
        $html = (string) preg_replace('/<!--.*?-->/s', '', $html);
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="raiz">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $raiz = $doc->getElementById('raiz');
        if (!$raiz) {
            return '';
        }
        $saida = new DOMDocument('1.0', 'UTF-8');
        $caixa = $saida->createElement('div');
        $saida->appendChild($caixa);
        self::copiar($raiz, $caixa, $saida);
        $resultado = '';
        foreach ($caixa->childNodes as $filho) {
            $resultado .= $saida->saveHTML($filho);
        }
        // Parágrafos vazios que o Word espalha.
        $resultado = (string) preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#u', '', $resultado);
        return trim($resultado);
    }

    private static function copiar(DOMNode $origem, DOMNode $destino, DOMDocument $saida): void
    {
        foreach ($origem->childNodes as $no) {
            if ($no instanceof DOMText) {
                // Travessão vira vírgula (regra da casa: nunca travessão).
                // Entre números (2010-2020) vira "a".
                $texto = (string) preg_replace('/(\d)\s*[\x{2013}\x{2014}]\s*(\d)/u', '$1 a $2', (string) $no->nodeValue);
                $texto = (string) preg_replace('/\s*[\x{2013}\x{2014}]\s*/u', ', ', $texto);
                $destino->appendChild($saida->createTextNode($texto));
                continue;
            }
            if (!$no instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($no->tagName);
            if (in_array($tag, self::DESCARTAR, true)) {
                continue;
            }
            $tag = self::TROCAR[$tag] ?? $tag;
            if (!isset(self::PERMITIDAS[$tag])) {
                // Tag desconhecida (span, font, o:p): some, o conteúdo fica.
                self::copiar($no, $destino, $saida);
                continue;
            }
            $novo = $saida->createElement($tag);
            foreach (self::PERMITIDAS[$tag] as $atributo) {
                $valor = trim((string) $no->getAttribute($atributo));
                if ($valor === '') {
                    continue;
                }
                if ($atributo === 'href' && !preg_match('#^https?://#i', $valor)) {
                    continue;
                }
                if (in_array($atributo, ['colspan', 'rowspan'], true) && !ctype_digit($valor)) {
                    continue;
                }
                $novo->setAttribute($atributo, $valor);
            }
            if ($tag === 'a') {
                $novo->setAttribute('target', '_blank');
                $novo->setAttribute('rel', 'noopener');
            }
            $destino->appendChild($novo);
            self::copiar($no, $novo, $saida);
        }
    }
}
