<?php
/**
 * Moldura provisória do portal público do destino.
 * Softsul Software
 *
 * Na fundação do InvenTour o portal só diz de quem é e que o inventário
 * está a caminho; o web app do destino (oferta, atrativos, mapa, QR e a aba
 * Eventos lida do EventCity) substitui esta moldura quando o módulo ficar
 * pronto. Sem cookie, sem sessão e sem script.
 */
declare(strict_types=1);
require_once __DIR__ . '/funcoes.php';

final class PortalLayout
{
    /** @param array<string,mixed>|null $destino */
    public static function inicio(?array $destino, string $titulo, bool $indexar = true): void
    {
        $nome = $destino ? (string) $destino['nome_fantasia'] : 'InvenTour';
        $cor = $destino && preg_match('/^#[0-9A-F]{6}$/i', (string) ($destino['cor_acento'] ?? '')) ? (string) $destino['cor_acento'] : '#0E7C66';
        $css = '/assets/portal.css?v=' . (int) @filemtime(__DIR__ . '/../portal/assets/portal.css');
        ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo === $nome ? $nome . ' | InvenTour' : $titulo . ' | ' . $nome) ?></title>
<?php if (!$indexar): ?><meta name="robots" content="noindex"><?php endif; ?>
<meta name="theme-color" content="<?= e($cor) ?>">
<link rel="icon" href="/assets/icone.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/icone-180.png">
<link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body data-acento="<?= e($cor) ?>">
<main class="palco">
        <?php
    }

    public static function fim(): void
    {
        ?>
</main>
<footer class="rodape">
  <a href="/privacidade">Privacidade</a>
  <span>Produto Softsul Software e Zapping Consultoria</span>
</footer>
</body>
</html>
        <?php
    }
}
