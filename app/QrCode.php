<?php
/**
 * Gerador de QR Code em PHP puro, com saída em SVG.
 * Softsul Software
 *
 * Existe por uma razão de segurança: o cadastro do segundo fator precisa
 * mostrar um QR Code, e a alternativa comum é chamar uma API pública de QR
 * passando o conteúdo na URL. Isso entregaria a semente do TOTP a um
 * terceiro, que passaria a gerar códigos válidos daquele usuário para
 * sempre. É o que este arquivo evita.
 *
 * Escopo deliberadamente estreito, porque o uso é um só: modo byte,
 * correção de erro nível M e versões 1 a 10, o que cobre com folga os ~120
 * caracteres de uma URI otpauth://. Saída em SVG, que é a regra do projeto
 * para imagem e ainda por cima não perde nitidez em nenhuma tela.
 *
 * Referência: ISO/IEC 18004. A implementação foi conferida contra o
 * qrencode e decodificada de volta com o zbarimg.
 */
declare(strict_types=1);

class QrCode
{
    /** Codewords de dados por versão, no nível M. */
    private const DADOS_POR_VERSAO = [
        1 => 16, 2 => 28, 3 => 44, 4 => 64, 5 => 86,
        6 => 108, 7 => 124, 8 => 154, 9 => 182, 10 => 216,
    ];

    /**
     * Estrutura de blocos por versão, no nível M:
     * [codewords de correção por bloco, [ [qtd de blocos, codewords de dados], ... ] ]
     */
    private const BLOCOS = [
        1  => [10, [[1, 16]]],
        2  => [16, [[1, 28]]],
        3  => [26, [[1, 44]]],
        4  => [18, [[2, 32]]],
        5  => [24, [[2, 43]]],
        6  => [16, [[4, 27]]],
        7  => [18, [[4, 31]]],
        8  => [22, [[2, 38], [2, 39]]],
        9  => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];

    /** Centros dos padrões de alinhamento, por versão. */
    private const ALINHAMENTO = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    private static array $exp = [];
    private static array $log = [];

    // ------------------------------------------------------------ público

    /**
     * Devolve o QR Code como SVG pronto para inserir no HTML.
     *
     * @param int    $quieto Borda clara em módulos. A norma pede 4, e leitor
     *                       de celular erra sem ela.
     */
    public static function svg(string $texto, int $quieto = 4, string $classe = ''): string
    {
        $matriz = self::matriz($texto);
        $n      = count($matriz);
        $lado   = $n + 2 * $quieto;

        // Um <path> só, em vez de um <rect> por módulo: para uma versão 6
        // seriam mais de mil elementos, e o SVG fica dez vezes maior.
        $d = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matriz[$y][$x]) {
                    $d .= 'M' . ($x + $quieto) . ' ' . ($y + $quieto) . 'h1v1h-1z';
                }
            }
        }
        $cls = $classe !== '' ? ' class="' . htmlspecialchars($classe, ENT_QUOTES) . '"' : '';
        return '<svg' . $cls . ' viewBox="0 0 ' . $lado . ' ' . $lado . '"'
             . ' xmlns="http://www.w3.org/2000/svg" shape-rendering="crispEdges"'
             . ' role="img" aria-label="QR Code para o aplicativo autenticador">'
             . '<rect width="' . $lado . '" height="' . $lado . '" fill="#fff"/>'
             . '<path d="' . $d . '" fill="#000"/>'
             . '</svg>';
    }

    // ------------------------------------------------------------ matriz

    /** @return array<int,array<int,int>> matriz de 0 e 1, já mascarada */
    public static function matriz(string $texto): array
    {
        $versao = self::escolherVersao($texto);
        $bits   = self::montarFluxoDeBits($texto, $versao);
        $codes  = self::intercalarBlocos($bits, $versao);

        $melhor = null;
        $melhorNota = PHP_INT_MAX;
        for ($mascara = 0; $mascara < 8; $mascara++) {
            $m = self::desenhar($versao, $codes, $mascara);
            $nota = self::penalidade($m);
            if ($nota < $melhorNota) {
                $melhorNota = $nota;
                $melhor = $m;
            }
        }
        return $melhor;
    }

    private static function escolherVersao(string $texto): int
    {
        $bytes = strlen($texto);
        foreach (self::DADOS_POR_VERSAO as $v => $codewords) {
            // 4 bits de modo mais o contador (8 bits até a versão 9, 16 a partir da 10).
            $cabecalho = 4 + ($v >= 10 ? 16 : 8);
            if ($bytes * 8 + $cabecalho <= $codewords * 8) {
                return $v;
            }
        }
        throw new RuntimeException('Texto grande demais para as versões cobertas por este gerador.');
    }

    /** Monta o fluxo de bits: modo, contador, dados, terminador e enchimento. */
    private static function montarFluxoDeBits(string $texto, int $versao): array
    {
        $bits = [];
        $por  = function (int $valor, int $largura) use (&$bits): void {
            for ($i = $largura - 1; $i >= 0; $i--) {
                $bits[] = ($valor >> $i) & 1;
            }
        };

        $por(0b0100, 4);                                  // modo byte
        $por(strlen($texto), $versao >= 10 ? 16 : 8);     // contador
        foreach (str_split($texto) as $c) {
            $por(ord($c), 8);
        }

        $capacidade = self::DADOS_POR_VERSAO[$versao] * 8;
        // Terminador de até 4 bits zero, se couber.
        for ($i = 0; $i < 4 && count($bits) < $capacidade; $i++) {
            $bits[] = 0;
        }
        // Completa o byte corrente.
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        // Enchimento alternando 0xEC e 0x11, como manda a norma.
        $enchimento = [0xEC, 0x11];
        $i = 0;
        while (count($bits) < $capacidade) {
            $por($enchimento[$i++ % 2], 8);
        }
        return $bits;
    }

    /** Divide em blocos, calcula a correção de erro e intercala tudo. */
    private static function intercalarBlocos(array $bits, int $versao): array
    {
        [$ecPorBloco, $grupos] = self::BLOCOS[$versao];

        $bytes = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $b = 0;
            for ($j = 0; $j < 8; $j++) {
                $b = ($b << 1) | $bits[$i + $j];
            }
            $bytes[] = $b;
        }

        $blocosDados = [];
        $blocosEc    = [];
        $pos = 0;
        foreach ($grupos as [$quantos, $tamanho]) {
            for ($k = 0; $k < $quantos; $k++) {
                $bloco = array_slice($bytes, $pos, $tamanho);
                $pos  += $tamanho;
                $blocosDados[] = $bloco;
                $blocosEc[]    = self::reedSolomon($bloco, $ecPorBloco);
            }
        }

        // Intercalação: primeiro codeword de cada bloco, depois o segundo, e
        // assim por diante. É isso que espalha um risco no papel por vários
        // blocos, em vez de destruir um bloco inteiro.
        $saida = [];
        $maior = max(array_map('count', $blocosDados));
        for ($i = 0; $i < $maior; $i++) {
            foreach ($blocosDados as $bloco) {
                if (isset($bloco[$i])) {
                    $saida[] = $bloco[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPorBloco; $i++) {
            foreach ($blocosEc as $bloco) {
                $saida[] = $bloco[$i];
            }
        }
        return $saida;
    }

    // ------------------------------------------------- Reed-Solomon GF(256)

    private static function iniciarGalois(): void
    {
        if (self::$exp) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;  // polinômio primitivo da norma
            }
        }
        for ($i = 256; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function reedSolomon(array $dados, int $quantos): array
    {
        self::iniciarGalois();

        // Polinômio gerador: produto de (x - alfa^i).
        $gerador = [1];
        for ($i = 0; $i < $quantos; $i++) {
            $novo = array_fill(0, count($gerador) + 1, 0);
            foreach ($gerador as $j => $coef) {
                $novo[$j] ^= $coef;
                if ($coef !== 0) {
                    $novo[$j + 1] ^= self::$exp[(self::$log[$coef] + $i) % 255];
                }
            }
            $gerador = $novo;
        }

        $resto = array_merge($dados, array_fill(0, $quantos, 0));
        for ($i = 0; $i < count($dados); $i++) {
            $coef = $resto[$i];
            if ($coef === 0) {
                continue;
            }
            $l = self::$log[$coef];
            foreach ($gerador as $j => $g) {
                if ($g !== 0) {
                    $resto[$i + $j] ^= self::$exp[(self::$log[$g] + $l) % 255];
                }
            }
        }
        return array_slice($resto, count($dados), $quantos);
    }

    // ------------------------------------------------------------ desenho

    private static function desenhar(int $versao, array $codewords, int $mascara): array
    {
        $n = $versao * 4 + 17;
        $m = array_fill(0, $n, array_fill(0, $n, 0));
        // Marca o que é padrão de função, para os dados não passarem por cima.
        $reservado = array_fill(0, $n, array_fill(0, $n, false));

        $marcar = function (int $x, int $y, int $v) use (&$m, &$reservado, $n): void {
            if ($x >= 0 && $y >= 0 && $x < $n && $y < $n) {
                $m[$y][$x] = $v;
                $reservado[$y][$x] = true;
            }
        };

        // Localizadores nos três cantos, com separador de um módulo claro.
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$ox, $oy]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $borda = ($x >= 0 && $x <= 6 && ($y === 0 || $y === 6))
                          || ($y >= 0 && $y <= 6 && ($x === 0 || $x === 6));
                    $miolo = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
                    $marcar($ox + $x, $oy + $y, ($borda || $miolo) ? 1 : 0);
                }
            }
        }

        // Linhas de temporização, que dão a referência de módulo ao leitor.
        for ($i = 8; $i < $n - 8; $i++) {
            $marcar($i, 6, ($i % 2 === 0) ? 1 : 0);
            $marcar(6, $i, ($i % 2 === 0) ? 1 : 0);
        }

        // Padrões de alinhamento, menos onde colidiriam com os localizadores.
        $centros = self::ALINHAMENTO[$versao];
        foreach ($centros as $cy) {
            foreach ($centros as $cx) {
                $noCanto = ($cx <= 8 && $cy <= 8)
                        || ($cx <= 8 && $cy >= $n - 9)
                        || ($cx >= $n - 9 && $cy <= 8);
                if ($noCanto) {
                    continue;
                }
                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $aceso = max(abs($x), abs($y)) !== 1;
                        $marcar($cx + $x, $cy + $y, $aceso ? 1 : 0);
                    }
                }
            }
        }

        // Módulo escuro obrigatório e reserva das áreas de formato.
        $marcar(8, $n - 8, 1);
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $marcar($i, 8, 0);
                $marcar(8, $i, 0);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $marcar($n - 1 - $i, 8, 0);
            if ($i < 7) {
                $marcar(8, $n - 1 - $i, 0);
            }
        }

        // Informação de versão, só a partir da versão 7.
        if ($versao >= 7) {
            $info = self::infoVersao($versao);
            for ($i = 0; $i < 18; $i++) {
                $bit = ($info >> $i) & 1;
                $marcar($i % 3 + $n - 11, intdiv($i, 3), $bit);
                $marcar(intdiv($i, 3), $i % 3 + $n - 11, $bit);
            }
        }

        // Dados em ziguezague, de baixo para cima, duas colunas por vez,
        // pulando a coluna 6 (a de temporização vertical).
        $bitsDados = [];
        foreach ($codewords as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bitsDados[] = ($byte >> $i) & 1;
            }
        }
        $idx = 0;
        $subindo = true;
        for ($col = $n - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            for ($i = 0; $i < $n; $i++) {
                $y = $subindo ? $n - 1 - $i : $i;
                foreach ([0, 1] as $d) {
                    $x = $col - $d;
                    if ($reservado[$y][$x]) {
                        continue;
                    }
                    $bit = $bitsDados[$idx] ?? 0;
                    $idx++;
                    $m[$y][$x] = $bit ^ (self::mascara($mascara, $x, $y) ? 1 : 0);
                }
            }
            $subindo = !$subindo;
        }

        // Formato (nível de correção e máscara), gravado duas vezes.
        $formato = self::infoFormato($mascara);
        for ($i = 0; $i < 15; $i++) {
            $bit = ($formato >> $i) & 1;
            // cópia junto ao localizador superior esquerdo
            if ($i < 6) {
                $m[8][$i] = $bit;
            } elseif ($i === 6) {
                $m[8][7] = $bit;
            } elseif ($i === 7) {
                $m[8][8] = $bit;
            } elseif ($i === 8) {
                $m[7][8] = $bit;
            } else {
                $m[14 - $i][8] = $bit;
            }
            // cópia distribuída pelos outros dois cantos
            if ($i < 8) {
                $m[8][$n - 1 - $i] = $bit;
            } else {
                $m[$n - 15 + $i][8] = $bit;
            }
        }
        $m[$n - 8][8] = 1; // módulo escuro

        return $m;
    }

    private static function mascara(int $tipo, int $x, int $y): bool
    {
        return match ($tipo) {
            0 => ($y + $x) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($y + $x) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => (($y * $x) % 2) + (($y * $x) % 3) === 0,
            6 => ((($y * $x) % 2) + (($y * $x) % 3)) % 2 === 0,
            7 => ((($y + $x) % 2) + (($y * $x) % 3)) % 2 === 0,
        };
    }

    /** BCH(15,5) sobre o nível de correção M (0b00) e a máscara. */
    private static function infoFormato(int $mascara): int
    {
        $dados = (0b00 << 3) | $mascara;
        $resto = $dados << 10;
        for ($i = 14; $i >= 10; $i--) {
            if (($resto >> $i) & 1) {
                $resto ^= 0b10100110111 << ($i - 10);
            }
        }
        return (($dados << 10) | $resto) ^ 0b101010000010010;
    }

    /**
     * BCH(18,6) da informação de versão, usada da versão 7 em diante.
     *
     * O gerador é x^12+x^11+x^10+x^9+x^8+x^5+x^2+1, ou seja 0b1111100100101,
     * que tem 13 bits. Escrever um valor mais curto aqui produz um QR que
     * parece certo a olho nu e que nenhum leitor decodifica, porque só as
     * versões 7 em diante carregam este campo.
     */
    private static function infoVersao(int $versao): int
    {
        $resto = $versao << 12;
        for ($i = 17; $i >= 12; $i--) {
            if (($resto >> $i) & 1) {
                $resto ^= 0b1111100100101 << ($i - 12);
            }
        }
        return ($versao << 12) | $resto;
    }

    /**
     * Penalidade das quatro regras da norma. A máscara escolhida é a de menor
     * nota, porque é a que menos confunde o leitor.
     */
    private static function penalidade(array $m): int
    {
        $n = count($m);
        $nota = 0;

        // Regra 1: sequências de 5 ou mais iguais, em linha e em coluna.
        for ($i = 0; $i < $n; $i++) {
            foreach ([true, false] as $porLinha) {
                $anterior = -1;
                $seguidos = 0;
                for ($j = 0; $j < $n; $j++) {
                    $v = $porLinha ? $m[$i][$j] : $m[$j][$i];
                    if ($v === $anterior) {
                        $seguidos++;
                    } else {
                        if ($seguidos >= 5) {
                            $nota += 3 + ($seguidos - 5);
                        }
                        $anterior = $v;
                        $seguidos = 1;
                    }
                }
                if ($seguidos >= 5) {
                    $nota += 3 + ($seguidos - 5);
                }
            }
        }

        // Regra 2: blocos 2x2 da mesma cor.
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $v = $m[$y][$x];
                if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) {
                    $nota += 3;
                }
            }
        }

        // Regra 3: o desenho 1:1:3:1:1 do localizador aparecendo nos dados.
        $alvo1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $alvo2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j <= $n - 11; $j++) {
                $linha = array_slice($m[$i], $j, 11);
                $coluna = [];
                for ($k = 0; $k < 11; $k++) {
                    $coluna[] = $m[$j + $k][$i];
                }
                foreach ([$linha, $coluna] as $trecho) {
                    if ($trecho === $alvo1 || $trecho === $alvo2) {
                        $nota += 40;
                    }
                }
            }
        }

        // Regra 4: desequilíbrio entre módulos claros e escuros.
        $escuros = 0;
        foreach ($m as $linha) {
            $escuros += array_sum($linha);
        }
        $percentual = $escuros * 100 / ($n * $n);
        $nota += (int) (abs($percentual - 50) / 5) * 10;

        return $nota;
    }
}
