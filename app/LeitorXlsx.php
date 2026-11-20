<?php
/**
 * Lê a primeira planilha de um arquivo XLSX, linha a linha.
 * Softsul Software
 *
 * Feito para as planilhas do Cadastur, que chegam a 30 MB de XML: o
 * XMLReader percorre o arquivo em fluxo, sem montar o documento inteiro na
 * memória, e cada linha sai de um gerador. Só os textos compartilhados
 * (sharedStrings) ficam numa lista, porque as células apontam para eles
 * pelo índice.
 *
 * Sem biblioteca externa de propósito: o projeto não usa Composer, e o
 * formato que interessa é pequeno (texto, número e texto em linha).
 */
declare(strict_types=1);

final class LeitorXlsx
{
    /**
     * As linhas da primeira aba, cada uma como lista de valores na ordem
     * das colunas (célula vazia vira null).
     *
     * @return Generator<int, list<string|null>>
     */
    public static function linhas(string $arquivo): Generator
    {
        $zip = new ZipArchive();
        if ($zip->open($arquivo) !== true) {
            throw new RuntimeException('não é um XLSX válido: ' . basename($arquivo));
        }
        $planilha = self::caminhoDaPrimeiraAba($zip);
        $textos = self::textosCompartilhados($zip);
        $zip->close();

        $r = new XMLReader();
        if (!$r->open('zip://' . $arquivo . '#' . $planilha, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('não consegui abrir a planilha dentro do XLSX');
        }
        $linha = [];
        $coluna = 0;
        $tipo = '';
        $dentroDeLinha = false;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT) {
                if ($r->localName === 'row') {
                    $linha = [];
                    $dentroDeLinha = true;
                    if ($r->isEmptyElement) {
                        yield [];
                        $dentroDeLinha = false;
                    }
                } elseif ($r->localName === 'c' && $dentroDeLinha) {
                    $coluna = self::indiceDaColuna((string) $r->getAttribute('r'));
                    $tipo = (string) $r->getAttribute('t');
                    if ($r->isEmptyElement) {
                        $linha[$coluna] = null;
                    }
                } elseif (($r->localName === 'v' || $r->localName === 't') && $dentroDeLinha) {
                    $valor = $r->readString();
                    if ($tipo === 's') {
                        $valor = $textos[(int) $valor] ?? '';
                    }
                    $linha[$coluna] = ($linha[$coluna] ?? '') . $valor;
                }
            } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'row') {
                $dentroDeLinha = false;
                if ($linha) {
                    $completa = array_fill(0, max(array_keys($linha)) + 1, null);
                    foreach ($linha as $i => $v) {
                        $completa[$i] = $v;
                    }
                    yield $completa;
                } else {
                    yield [];
                }
            }
        }
        $r->close();
    }

    /** Data do Excel (número de dias desde 30/12/1899) para AAAA-MM-DD. */
    public static function data(?string $valor): ?string
    {
        if ($valor === null || $valor === '' || $valor === '-') {
            return null;
        }
        if (is_numeric($valor)) {
            $dias = (int) floor((float) $valor);
            return (new DateTimeImmutable('1899-12-30'))->modify("+{$dias} days")->format('Y-m-d');
        }
        $t = strtotime($valor);
        return $t ? date('Y-m-d', $t) : null;
    }

    private static function caminhoDaPrimeiraAba(ZipArchive $zip): string
    {
        // O nome costuma ser sheet1.xml, mas o certo é seguir o workbook.
        $rels = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');
        $livro = (string) $zip->getFromName('xl/workbook.xml');
        if ($rels !== '' && $livro !== '' && preg_match('/<sheet[^>]*r:id="([^"]+)"/', $livro, $m)
            && preg_match('/<Relationship[^>]*Id="' . preg_quote($m[1], '/') . '"[^>]*Target="([^"]+)"/', $rels, $t)) {
            $alvo = ltrim($t[1], '/');
            return str_starts_with($alvo, 'xl/') ? $alvo : 'xl/' . $alvo;
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /** @return list<string> */
    private static function textosCompartilhados(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $r = new XMLReader();
        $r->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        $lista = [];
        $atual = null;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'si') {
                $atual = '';
                if ($r->isEmptyElement) {
                    $lista[] = '';
                    $atual = null;
                }
            } elseif ($r->nodeType === XMLReader::ELEMENT && $r->localName === 't' && $atual !== null) {
                $atual .= $r->readString();
            } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'si') {
                $lista[] = (string) $atual;
                $atual = null;
            }
        }
        $r->close();
        return $lista;
    }

    /** "AC12" vira 28 (A = 0). */
    private static function indiceDaColuna(string $referencia): int
    {
        $letras = preg_replace('/\d+/', '', $referencia) ?? '';
        $n = 0;
        foreach (str_split(strtoupper($letras)) as $l) {
            $n = $n * 26 + (ord($l) - 64);
        }
        return max(0, $n - 1);
    }
}
