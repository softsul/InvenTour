<?php
/**
 * Página do portal para host que não é de nenhum destino ativo.
 * Softsul Software
 *
 * Neutra de propósito: não diz se o destino existe e está inativo ou se
 * nunca existiu, para não servir de consulta a quem varre subdomínios.
 */
declare(strict_types=1);
require_once __DIR__ . '/../PortalLayout.php';

PortalLayout::inicio(null, 'Endereço sem inventário', false);
?>
  <h1>Não há inventário neste endereço.</h1>
  <p class="lead">Confira o nome da cidade no endereço, ou conheça o InvenTour, o inventário dinâmico da oferta turística dos destinos.</p>
  <a class="cta" href="https://inventour.com.br" target="_blank" rel="noopener">Conhecer o InvenTour</a>
<?php PortalLayout::fim();
