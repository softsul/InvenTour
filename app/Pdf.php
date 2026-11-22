<?php
/**
 * Gera PDF a partir de HTML.
 * Softsul Software
 *
 * wkhtmltopdf, e não um montador de PDF em PHP, porque o documento comercial
 * é layout: a mesma folha de estilo que desenha a tela desenha o papel, e
 * mudar a margem de uma tabela passa a ser mudar CSS.
 *
 * Copiado do Orbit: o documento sai igual todas as vezes, sem rede e sem
 * custo por página. No InvenTour, é a folha dos relatórios gerenciais.
 */
declare(strict_types=1);

final class Pdf
{
    private const BINARIO = '/usr/local/bin/wkhtmltopdf';

    /**
     * Converte HTML em PDF e devolve os bytes.
     *
     * @param array<string,string> $opcoes margens e afins, em milímetros
     */
    public static function deHtml(string $html, array $opcoes = []): string
    {
        if (!is_executable(self::BINARIO)) {
            throw new RuntimeException('wkhtmltopdf não está disponível no servidor');
        }

        $pasta = sys_get_temp_dir();
        $base  = $pasta . '/inventour-pdf-' . bin2hex(random_bytes(8));
        $entrada = $base . '.html';
        $saida   = $base . '.pdf';
        file_put_contents($entrada, $html);

        // Cabeçalho repetido em todas as páginas: o wkhtmltopdf desenha um
        // HTML à parte no alto de cada folha. Ele lê o arquivo do disco, então
        // só esse arquivo é liberado no --allow; o resto segue sem acesso.
        $cabecalho = null;
        if (!empty($opcoes['cabecalho_html'])) {
            $cabecalho = $base . '-cab.html';
            file_put_contents($cabecalho, $opcoes['cabecalho_html']);
        }

        $args = [
            '--quiet', '--encoding', 'utf-8',
            '--page-size', $opcoes['tamanho'] ?? 'A4',
            // Deitado quando a tabela é larga: encolher a fonte até caber
            // torna o relatório ilegível, e virar a folha não.
            '--orientation', ($opcoes['deitado'] ?? false) ? 'Landscape' : 'Portrait',
            '--margin-top', ($opcoes['topo'] ?? '14') . 'mm',
            '--margin-bottom', ($opcoes['rodape'] ?? '16') . 'mm',
            '--margin-left', ($opcoes['esquerda'] ?? '14') . 'mm',
            '--margin-right', ($opcoes['direita'] ?? '14') . 'mm',
            // Sem acesso a arquivo local nem a rede: o HTML já vem inteiro,
            // com estilo e imagem embutidos, e uma referência externa só
            // atrasaria a geração ou vazaria o que o documento contém.
            '--disable-local-file-access',
            '--print-media-type',
        ];
        if ($cabecalho !== null) {
            array_push($args, '--header-html', $cabecalho, '--header-spacing', (string) ($opcoes['cabecalho_espaco'] ?? '4'),
                       '--allow', $cabecalho);
        }
        if (!empty($opcoes['rodape_texto'])) {
            array_push($args, '--footer-font-size', '7', '--footer-font-name', 'DejaVu Sans',
                       '--footer-left', $opcoes['rodape_texto'],
                       '--footer-right', 'Página [page] de [topage]', '--footer-spacing', '6');
        } elseif (!empty($opcoes['paginas'])) {
            // Só o número da página, centralizado e discreto: com papel de
            // carta, o rodapé já tem o endereço do site no mesmo lugar.
            array_push($args, '--footer-font-size', '7', '--footer-font-name', 'DejaVu Sans',
                       '--footer-center', $opcoes['paginas'], '--footer-spacing', '1');
        }
        $args[] = $entrada;
        $args[] = $saida;

        // O locale tem de ser UTF-8 nos dois lados. No PHP, porque o
        // escapeshellarg apaga os bytes acentuados quando o processo roda em
        // "C", que é o padrão do php-fpm: o rodapé saía "Oramento" e
        // "pgina". No wkhtmltopdf, porque ele lê o argumento pelo locale do
        // ambiente, e o texto certo chegaria torto.
        $antes = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C.UTF-8');
        $comando = 'LC_ALL=C.UTF-8 ' . escapeshellcmd(self::BINARIO) . ' '
                 . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
        setlocale(LC_CTYPE, $antes ?: 'C');
        exec($comando, $linhas, $codigo);

        // Papel de carta: o PDF do fundo vai por baixo de todas as páginas.
        // Por baixo, e não como imagem no HTML: o wkhtmltopdf não repete
        // imagem de fundo com a página inteira quando há margem, e o papel
        // precisa ir até a borda enquanto o texto respeita as margens dele.
        if (!empty($opcoes['fundo']) && is_file($saida)) {
            if (!is_readable($opcoes['fundo'])) {
                @unlink($entrada);
                @unlink($saida);
                throw new RuntimeException('o papel de carta não está no servidor: ' . $opcoes['fundo']);
            }
            $comFundo = $base . '-fundo.pdf';
            exec('qpdf ' . escapeshellarg($saida) . ' --underlay ' . escapeshellarg($opcoes['fundo'])
                . ' --repeat=1 -- ' . escapeshellarg($comFundo) . ' 2>&1', $saidaQpdf, $codigoQpdf);
            // 3 é aviso do qpdf, e o arquivo sai do mesmo jeito.
            if (is_file($comFundo) && in_array($codigoQpdf, [0, 3], true)) {
                rename($comFundo, $saida);
            } else {
                @unlink($comFundo);
                error_log('pdf: papel de carta não aplicado: ' . implode(' ', $saidaQpdf));
            }
        }

        $pdf = is_file($saida) ? (string) file_get_contents($saida) : '';
        if ($cabecalho !== null) {
            @unlink($cabecalho);
        }
        @unlink($entrada);
        @unlink($saida);

        // O wkhtmltopdf devolve código diferente de zero por coisas pequenas,
        // como uma imagem que não carregou, e ainda assim escreve o PDF. O que
        // decide é haver PDF na saída.
        if ($pdf === '' || !str_starts_with($pdf, '%PDF')) {
            throw new RuntimeException('não consegui gerar o PDF: ' . implode(' ', $linhas));
        }
        return $pdf;
    }

    /** Quantas páginas o PDF tem. O qpdf lê o que a expressão regular não vê. */
    public static function paginas(string $pdf): int
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'inventour-pdf-');
        file_put_contents($arquivo, $pdf);
        $n = (int) trim((string) shell_exec('qpdf --show-npages ' . escapeshellarg($arquivo) . ' 2>/dev/null'));
        @unlink($arquivo);
        return $n;
    }

    /** Imagem embutida como data URI, para o PDF não depender de rede. */
    public static function embutir(string $caminho): string
    {
        if (!is_readable($caminho)) {
            return '';
        }
        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($caminho) ?: 'image/png';
        return 'data:' . $tipo . ';base64,' . base64_encode((string) file_get_contents($caminho));
    }
}
