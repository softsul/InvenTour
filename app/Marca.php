<?php
/**
 * A identidade visual: a marca do produto e a marca de cada destino.
 * Softsul Software
 *
 * O painel inteiro usa a marca do InvenTour, um SVG em public/assets/
 * (logo.svg; simbolo.svg e palavra.svg quando existirem, para a barra
 * lateral, onde símbolo e nome têm tamanhos próprios). O SVG é preparado
 * pela Softsul e lido do disco, nunca enviado por terceiro: SVG enviado
 * pode levar script junto.
 *
 * Cada destino tem a sua marca em empresas.logo, um arquivo em
 * storage/midias/marcas/ servido pelo Apache por /midia/marcas/. Ela é
 * imagem raster (PNG, WebP ou JPEG, reprocessada ao subir) e aparece no
 * portal público do destino e no cabeçalho das telas que são dele. A cor
 * de acento do destino (empresas.cor_acento) vale só no portal.
 */
declare(strict_types=1);
require_once __DIR__ . '/Empresa.php';

final class Marca
{
    private const PASTA = __DIR__ . '/../public/assets/';

    /** O identificador da marca do produto, igual para todos os destinos. */
    public static function slug(): string
    {
        return 'inventour';
    }

    public static function nome(): string
    {
        $cfg = require __DIR__ . '/config.php';
        return (string) ($cfg['app']['nome'] ?? 'InvenTour');
    }

    /**
     * O caminho público do logotipo do destino atual, ou null se ele não
     * tiver um. Vale só com sessão aberta e dentro de um destino.
     */
    public static function doDestino(): ?string
    {
        $empresa = session_status() === PHP_SESSION_ACTIVE ? Empresa::atual() : null;
        return $empresa ? self::caminhoDoLogo(self::logoVisivel($empresa) ?: null) : null;
    }

    /**
     * Nome antigo de doDestino(), mantido para quem ainda chama assim:
     * devolve o caminho público do logotipo do destino atual.
     */
    public static function doBox(): ?string
    {
        return self::doDestino();
    }

    /**
     * O SVG da marca do produto em linha, com a classe pedida. Pinta com
     * currentColor. $qual: 'logo' (símbolo e nome juntos), 'simbolo' ou
     * 'capacete' (só o símbolo; o nome antigo continua valendo), 'palavra'
     * (só o nome). Sem o arquivo próprio, símbolo cai no logo inteiro e
     * palavra fica vazia, para a barra lateral não mostrar a marca duas vezes.
     */
    public static function svg(string $qual, string $classe): string
    {
        $arquivo = match ($qual) {
            'palavra'            => 'palavra.svg',
            'simbolo', 'capacete' => is_file(self::PASTA . 'simbolo.svg') ? 'simbolo.svg' : 'logo.svg',
            default              => 'logo.svg',
        };
        $caminho = self::PASTA . $arquivo;
        if (!is_file($caminho)) {
            return '';
        }
        $svg = trim((string) file_get_contents($caminho));
        return (string) preg_replace('/^<svg /', '<svg class="' . htmlspecialchars($classe, ENT_QUOTES) . '" aria-hidden="true" focusable="false" ', $svg, 1);
    }

    /**
     * A marca de um destino pelo slug (portal público), como <img>, ou a
     * marca do produto em SVG se o destino não tiver logotipo. A assinatura
     * é a herdada, para o layout não mudar; $qual só importa na queda para
     * a marca do produto.
     */
    public static function svgDoBox(string $slug, string $qual, string $classe): string
    {
        $slug = (string) preg_replace('/[^a-z0-9-]/', '', $slug);
        if ($slug === '') {
            return self::svg($qual, $classe);
        }
        $q = Database::leitura()->prepare('SELECT nome_fantasia, logo, logo_no_portal FROM empresas WHERE slug = ? AND ativo = 1');
        $q->execute([$slug]);
        $e = $q->fetch();
        $caminho = $e ? self::caminhoDoLogo(self::logoVisivel($e) ?: null) : null;
        if (!$caminho) {
            return self::svg($qual, $classe);
        }
        return '<img class="' . htmlspecialchars($classe, ENT_QUOTES) . '" src="' . htmlspecialchars($caminho, ENT_QUOTES)
             . '" alt="' . htmlspecialchars((string) $e['nome_fantasia'], ENT_QUOTES) . '" loading="lazy">';
    }

    /** O mesmo que svgDoBox(), com o nome do vocabulário do InvenTour. */
    public static function svgDoDestino(string $slug, string $qual, string $classe): string
    {
        return self::svgDoBox($slug, $qual, $classe);
    }

    /**
     * Folha de cores própria, se houver. O painel usa sempre o tema do
     * InvenTour; a cor do destino vale no portal, por corAcento(). Mantido
     * para o layout poder perguntar sem saber disso.
     */
    public static function tema(): ?string
    {
        return null;
    }

    /** A cor de acento do destino atual (#RRGGBB), ou null para a do InvenTour. */
    public static function corAcento(): ?string
    {
        $empresa = session_status() === PHP_SESSION_ACTIVE ? Empresa::atual() : null;
        $cor = (string) ($empresa['cor_acento'] ?? '');
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $cor) ? $cor : null;
    }

    /**
     * De empresas.logo (nome do arquivo em storage/midias/marcas/) para o
     * endereço público. Só o nome do arquivo entra no endereço: o campo
     * nunca vira caminho relativo nem sobe de pasta.
     */
    /**
     * O logo do destino (o nome do arquivo em empresas.logo) quando ele pode
     * aparecer, ou '' quando o destino marcou "Ocultar o logo" na ficha
     * (logo_no_portal = 0). Oculto vale para tudo: portal, cards do feed e do
     * story, posts das redes, kit de mídia, cartazes de QR, listas e menu.
     * O arquivo fica guardado e só a ficha do destino o mostra. Sem a coluna
     * na linha recebida, lê pelo id (Luiz, 20/11/2026).
     *
     * @param array<string,mixed> $d a linha de empresas (ou parte dela)
     */
    public static function logoVisivel(array $d): string
    {
        static $visivel = [];
        $logo = (string) ($d['logo'] ?? '');
        if ($logo === '') {
            return '';
        }
        if (array_key_exists('logo_no_portal', $d)) {
            return (int) $d['logo_no_portal'] ? $logo : '';
        }
        $id = (int) ($d['id'] ?? 0);
        if (!$id) {
            return '';
        }
        if (!isset($visivel[$id])) {
            $q = Database::leitura()->prepare('SELECT logo_no_portal FROM empresas WHERE id = ?');
            $q->execute([$id]);
            $visivel[$id] = (int) $q->fetchColumn() === 1;
        }
        return $visivel[$id] ? $logo : '';
    }

    /** O caminho público de um logo gravado em empresas.logo, ou null. */
    public static function logoDe(?string $logo): ?string
    {
        return self::caminhoDoLogo($logo);
    }

    private static function caminhoDoLogo(?string $logo): ?string
    {
        $logo = basename((string) $logo);
        if ($logo === '' || $logo === '.' || !preg_match('/\.(png|webp|jpe?g)$/i', $logo)) {
            return null;
        }
        $cfg = require __DIR__ . '/config.php';
        if (!is_file($cfg['caminhos']['midias'] . '/marcas/' . $logo)) {
            return null;
        }
        return '/midia/marcas/' . rawurlencode($logo);
    }
}
