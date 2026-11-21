<?php
/**
 * Planilha XLSX mínima, sem biblioteca: várias abas, texto e número, a
 * primeira linha em negrito e congelada. É o que o caderno exporta (resumo
 * territorial, A, B, C e experiências). Softsul Software.
 */
declare(strict_types=1);

final class Xlsx
{
    /** @var array<string,list<list<string|int|float|null>>> nome da aba => linhas (a primeira é o cabeçalho) */
    private array $abas = [];

    public function aba(string $nome, array $linhas): void
    {
        // Nome de aba: até 31 caracteres, sem []:*?/\
        $nome = mb_substr(preg_replace('#[\[\]:*?/\\\\]#', ' ', $nome), 0, 31);
        $this->abas[$nome] = $linhas;
    }

    public function salvar(string $arquivo): void
    {
        $zip = new ZipArchive();
        if ($zip->open($arquivo, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não consegui criar a planilha.');
        }
        $n = count($this->abas);
        $tipos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $n; $i++) {
            $tipos .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $zip->addFromString('[Content_Types].xml', $tipos . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $livro = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $i = 0;
        foreach (array_keys($this->abas) as $nome) {
            $i++;
            $livro .= '<sheet name="' . self::esc($nome) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $zip->addFromString('xl/workbook.xml', $livro . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels . '</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>');
        $i = 0;
        foreach ($this->abas as $linhas) {
            $i++;
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
            foreach (array_values($linhas) as $r => $linha) {
                $xml .= '<row r="' . ($r + 1) . '">';
                foreach (array_values($linha) as $c => $v) {
                    $ref = self::coluna($c) . ($r + 1);
                    $estilo = $r === 0 ? ' s="1"' : '';
                    if ($v === null || $v === '') {
                        continue;
                    }
                    if ((is_int($v) || is_float($v)) && $r > 0) {
                        $xml .= '<c r="' . $ref . '"' . $estilo . '><v>' . $v . '</v></c>';
                    } else {
                        $xml .= '<c r="' . $ref . '"' . $estilo . ' t="inlineStr"><is><t xml:space="preserve">' . self::esc((string) $v) . '</t></is></c>';
                    }
                }
                $xml .= '</row>';
            }
            $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', $xml . '</sheetData></worksheet>');
        }
        $zip->close();
    }

    private static function coluna(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    private static function esc(string $t): string
    {
        // Caractere de controle quebra o XML da planilha.
        $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $t);
        return htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
