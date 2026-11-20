<?php
/**
 * O padrão de toda foto guardada no InvenTour: dimensões e formato fixos
 * por uso, para as telas mostrarem tudo igual e o armazenamento não crescer
 * à toa.
 * Softsul Software
 *
 * - pessoa (usuário, destino): quadrada 512 x 512, cortada no centro (o
 *   rosto fica no meio; o editor de foto já enquadra antes);
 * - produto: quadrada 1000 x 1000, inteira e centralizada em fundo branco
 *   (nunca cortada), para logotipos e imagens que precisam aparecer inteiras.
 *
 * - logo (marca do destino): sem quadro fixo. A margem vazia em volta
 *   (transparente ou branca) é recortada, o formato do desenho é mantido,
 *   o lado maior fica em até 800 px e a saída é PNG, para a transparência
 *   do brasão sobreviver sobre o fundo escuro do portal (Imagem::logo).
 *
 * Foto do inventário: formato original, lado maior em até 1600 px
 * (Imagem::foto), com a coordenada do EXIF lida antes (Imagem::gpsDoExif).
 *
 * Fora o logo, sempre JPEG de qualidade 80 (igual ao olho, e menor que o original), com
 * a rotação da câmera corrigida (EXIF) e sem metadados: a imagem é
 * redesenhada pelo GD, nada do arquivo original é guardado (localização do
 * celular, aparelho, conteúdo escondido).
 */
declare(strict_types=1);

final class Imagem
{
    /** perfil => [largura, altura, modo] (cobrir corta; conter completa com branco) */
    public const PERFIS = [
        'pessoa'  => [512, 512, 'cobrir'],
        'produto' => [1000, 1000, 'conter'],
        'artista' => [1280, 1280, 'cobrir'],
    ];
    public const QUALIDADE = 80;

    /**
     * Padroniza a imagem (bytes de JPEG, PNG, WebP ou GIF) no perfil.
     * Devolve os bytes do JPEG, ou null se não for imagem.
     */
    public static function padronizar(string $dados, string $perfil): ?string
    {
        [$larg, $alt, $modo] = self::PERFIS[$perfil];
        $img = @imagecreatefromstring($dados);
        if (!$img) {
            return null;
        }
        $img = self::endireitar($img, $dados);
        [$l, $a] = [imagesx($img), imagesy($img)];
        $saida = imagecreatetruecolor($larg, $alt);
        imagefill($saida, 0, 0, imagecolorallocate($saida, 255, 255, 255));
        if ($modo === 'cobrir') {
            // Corta o que sobra no centro, para encher o quadro.
            $escala = max($larg / $l, $alt / $a);
            $lOrig = (int) round($larg / $escala);
            $aOrig = (int) round($alt / $escala);
            imagecopyresampled($saida, $img, 0, 0, (int) (($l - $lOrig) / 2), (int) (($a - $aOrig) / 2), $larg, $alt, $lOrig, $aOrig);
        } else {
            // A imagem inteira, centralizada; o resto fica branco. Não amplia foto pequena.
            $escala = min($larg / $l, $alt / $a, 1);
            $lNovo = (int) round($l * $escala);
            $aNovo = (int) round($a * $escala);
            imagecopyresampled($saida, $img, (int) (($larg - $lNovo) / 2), (int) (($alt - $aNovo) / 2), 0, 0, $lNovo, $aNovo, $l, $a);
        }
        imageinterlace($saida, true); // JPEG progressivo: aparece aos poucos na conexão lenta
        ob_start();
        imagejpeg($saida, null, self::QUALIDADE);
        return (string) ob_get_clean();
    }

    /**
     * Foto do inventário (atrativo, equipamento): formato original mantido,
     * lado maior em até $max px (não amplia), rotação da câmera corrigida e
     * JPEG sem metadados. Devolve [bytes, largura, altura] ou null.
     */
    public static function foto(string $dados, int $max = 1600): ?array
    {
        $img = @imagecreatefromstring($dados);
        if (!$img) {
            return null;
        }
        $img = self::endireitar($img, $dados);
        [$l, $a] = [imagesx($img), imagesy($img)];
        $escala = min($max / max($l, $a), 1);
        [$lNovo, $aNovo] = [(int) round($l * $escala), (int) round($a * $escala)];
        $saida = imagecreatetruecolor($lNovo, $aNovo);
        imagefill($saida, 0, 0, imagecolorallocate($saida, 255, 255, 255));
        imagecopyresampled($saida, $img, 0, 0, 0, 0, $lNovo, $aNovo, $l, $a);
        imageinterlace($saida, true);
        ob_start();
        imagejpeg($saida, null, self::QUALIDADE);
        return [(string) ob_get_clean(), $lNovo, $aNovo];
    }

    /**
     * Coordenada gravada pela câmera (EXIF), lida antes de a imagem ser
     * redesenhada sem metadados. [latitude, longitude] ou null.
     */
    public static function gpsDoExif(string $dados): ?array
    {
        if (!function_exists('exif_read_data')) {
            return null;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($dados));
        if (!$exif || empty($exif['GPSLatitude']) || empty($exif['GPSLongitude'])) {
            return null;
        }
        $grau = function (array $partes): float {
            $n = array_map(function ($p) {
                [$a, $b] = array_pad(explode('/', (string) $p), 2, '1');
                return (float) $b ? (float) $a / (float) $b : 0.0;
            }, $partes);
            return ($n[0] ?? 0) + ($n[1] ?? 0) / 60 + ($n[2] ?? 0) / 3600;
        };
        $lat = $grau((array) $exif['GPSLatitude']) * (($exif['GPSLatitudeRef'] ?? 'N') === 'S' ? -1 : 1);
        $lng = $grau((array) $exif['GPSLongitude']) * (($exif['GPSLongitudeRef'] ?? 'E') === 'W' ? -1 : 1);
        return abs($lat) <= 90 && abs($lng) <= 180 && ($lat || $lng) ? [round($lat, 6), round($lng, 6)] : null;
    }

    /** O tamanho de referência do enquadramento na tela, por uso; a saída segue o perfil. */
    public const RECORTE_ARTISTA = [800, 800];
    public const RECORTE_LOGO = [400, 400];

    /** O enquadramento enviado pela zona de upload: "x,y,largura,altura", um por arquivo, ou null. */
    public static function recorteDoFormulario(string $nome, int $indice = 0): ?array
    {
        $texto = ((array) ($_POST[$nome] ?? []))[$indice] ?? null;
        if (!is_string($texto) || !preg_match('/^(\d{1,5}),(\d{1,5}),(\d{1,5}),(\d{1,5})$/', $texto, $m)) {
            return null;
        }
        return ['x' => (int) $m[1], 'y' => (int) $m[2], 'l' => (int) $m[3], 'a' => (int) $m[4]];
    }

    /**
     * Aplica o enquadramento antes do tratamento de cada uso, em PNG para manter
     * a transparência do logo. Sem recorte, ou com um fora da imagem, devolve os
     * bytes como vieram, e a imagem é tratada inteira.
     */
    public static function recortar(string $dados, ?array $r): string
    {
        $img = $r ? @imagecreatefromstring($dados) : false;
        if (!$img) {
            return $dados;
        }
        $img = self::endireitar($img, $dados);
        if ($r['l'] < 1 || $r['a'] < 1 || $r['x'] + $r['l'] > imagesx($img) || $r['y'] + $r['a'] > imagesy($img)) {
            return $dados;
        }
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        $saida = imagecreatetruecolor($r['l'], $r['a']);
        imagealphablending($saida, false);
        imagesavealpha($saida, true);
        imagecopy($saida, $img, 0, 0, $r['x'], $r['y'], $r['l'], $r['a']);
        ob_start();
        imagepng($saida, null, 1);
        return (string) ob_get_clean();
    }

    /** Lado maior do logo guardado. */
    public const LOGO_MAX = 800;

    /**
     * Padroniza o logo de um destino: recorta a margem vazia (pixel quase
     * transparente ou quase branco), reduz o lado maior a LOGO_MAX sem
     * ampliar e devolve PNG com transparência. O desenho é redesenhado pelo
     * GD, sem metadados, como as fotos. Null se não for imagem.
     */
    public static function logo(string $dados): ?string
    {
        $img = @imagecreatefromstring($dados);
        if (!$img) {
            return null;
        }
        $img = self::endireitar($img, $dados);
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        [$l, $a] = [imagesx($img), imagesy($img)];

        // Vazio = alfa alto (quase transparente) ou cor quase branca.
        $vazio = static function (int $c): bool {
            $alfa = ($c >> 24) & 0x7F;
            if ($alfa > 110) {
                return true;
            }
            return (($c >> 16) & 0xFF) > 242 && (($c >> 8) & 0xFF) > 242 && ($c & 0xFF) > 242;
        };
        [$x0, $y0, $x1, $y1] = [$l, $a, -1, -1];
        // Passo de 1 px até 1500 de lado; acima disso, amostra (o recorte
        // só perde um ou dois pixels de folga, que a margem repõe).
        $passo = max(1, (int) floor(max($l, $a) / 1500));
        for ($y = 0; $y < $a; $y += $passo) {
            for ($x = 0; $x < $l; $x += $passo) {
                if (!$vazio(imagecolorat($img, $x, $y))) {
                    $x0 = min($x0, $x); $x1 = max($x1, $x);
                    $y0 = min($y0, $y); $y1 = max($y1, $y);
                }
            }
        }
        if ($x1 < 0) {
            [$x0, $y0, $x1, $y1] = [0, 0, $l - 1, $a - 1];
        }
        // Uma folga de 2% em volta, para o desenho não encostar na borda.
        $folga = (int) round(max($x1 - $x0, $y1 - $y0) * 0.02) + $passo;
        $x0 = max(0, $x0 - $folga); $y0 = max(0, $y0 - $folga);
        $x1 = min($l - 1, $x1 + $folga); $y1 = min($a - 1, $y1 + $folga);
        $lc = $x1 - $x0 + 1;
        $ac = $y1 - $y0 + 1;

        $escala = min(self::LOGO_MAX / max($lc, $ac), 1);
        $lNovo = max(1, (int) round($lc * $escala));
        $aNovo = max(1, (int) round($ac * $escala));
        $saida = imagecreatetruecolor($lNovo, $aNovo);
        imagealphablending($saida, false);
        imagesavealpha($saida, true);
        imagefill($saida, 0, 0, imagecolorallocatealpha($saida, 255, 255, 255, 127));
        imagecopyresampled($saida, $img, 0, 0, $x0, $y0, $lNovo, $aNovo, $lc, $ac);
        ob_start();
        imagepng($saida, null, 9);
        return (string) ob_get_clean();
    }

    /** Gira conforme a orientação gravada pela câmera (foto de celular "deitada"). */
    private static function endireitar(GdImage $img, string $dados): GdImage
    {
        if (!function_exists('exif_read_data') || !str_starts_with($dados, "\xFF\xD8")) {
            return $img;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($dados));
        $rotacao = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        return $rotacao ? (imagerotate($img, $rotacao, 0) ?: $img) : $img;
    }
}
