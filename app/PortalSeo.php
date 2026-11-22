<?php
/**
 * Dados estruturados (schema.org em JSON-LD) das páginas do portal, no
 * molde do EventCity (schema.org/Event lá; aqui, lugares e roteiros).
 * Softsul Software
 *
 * É o que o Google usa para entender que a página é de um hotel, de um
 * restaurante ou de um atrativo, com endereço, mapa, horário, telefone e
 * fotos, e o que alimenta os quadros de lugares e as respostas das buscas
 * por IA. Cada página do portal passa a lista em PortalLayout::inicio
 * (opção 'jsonld'); a prévia e as páginas sem indexação não levam nada.
 * Só entra o que a ficha homologada tem: campo vazio fica de fora.
 */
declare(strict_types=1);
require_once __DIR__ . '/Portal.php';
require_once __DIR__ . '/svg.php';

final class PortalSeo
{
    /** Ficha do MTur (ordem) => tipo do schema.org. O que não está aqui é LocalBusiness ou TouristAttraction pelo pilar. */
    private const TIPOS = [
        20 => 'LodgingBusiness', 21 => 'LodgingBusiness', 22 => 'FoodEstablishment', 23 => 'TravelAgency', 24 => 'TravelAgency',
        25 => 'EventVenue', 27 => 'Park', 28 => 'Museum',
    ];

    private const DIAS = ['seg' => 'Monday', 'ter' => 'Tuesday', 'qua' => 'Wednesday', 'qui' => 'Thursday', 'sex' => 'Friday', 'sab' => 'Saturday', 'dom' => 'Sunday'];

    public static function base(): string
    {
        return 'https://' . ($_SERVER['HTTP_HOST'] ?? '');
    }

    /** O destino (página inicial): o lugar turístico e o site, com a busca do portal. */
    public static function destino(array $destino, ?string $descricao, ?string $imagem): array
    {
        $base = self::base();
        $nome = (string) $destino['nome_fantasia'];
        $lugar = array_filter([
            '@type' => 'TouristDestination', 'name' => $nome, 'url' => $base . '/', 'description' => $descricao,
            'image' => $imagem ? $base . $imagem : null,
            'address' => array_filter(['@type' => 'PostalAddress', 'addressLocality' => $destino['cidade'] ?? null, 'addressRegion' => $destino['uf'] ?? null, 'addressCountry' => 'BR']),
        ]);
        $site = ['@type' => 'WebSite', 'name' => $nome . ' · Guia oficial de turismo', 'url' => $base . '/', 'inLanguage' => 'pt-BR',
                 'potentialAction' => ['@type' => 'SearchAction', 'target' => $base . '/busca?q={busca}', 'query-input' => 'required name=busca']];
        return [$lugar, $site];
    }

    /** Um lugar do inventário, com o tipo pela ficha. */
    public static function lugar(array $destino, array $i, string $pilar, string $caminho): array
    {
        $base = self::base();
        $ordem = (int) ($i['ficha_ordem'] ?? 0);
        $tipo = self::TIPOS[$ordem] ?? ($pilar === 'o-que-fazer' ? 'TouristAttraction' : 'LocalBusiness');
        $fotos = [];
        foreach ((array) ($i['fotos'] ?? []) as $f) {
            if ($u = Portal::foto((int) $destino['id'], $f['arquivo'] ?? null, '')) {
                $fotos[] = $base . $u;
            }
        }
        $rua = trim(($i['logradouro'] ?? '') . ($i['numero'] ? ', ' . $i['numero'] : '') . ($i['complemento'] ? ', ' . $i['complemento'] : ''));
        $tel = (string) telefoneParaBanco((string) ($i['telefone'] ?: $i['whatsapp'] ?? ''));
        $site = (string) ($i['site'] ?? '');
        if ($site !== '' && !preg_match('#^https?://#i', $site)) {
            $site = 'https://' . $site;
        }
        $insta = ltrim(preg_replace('#^(https?://)?(www\.)?instagram\.com/#i', '', (string) ($i['instagram'] ?? '')), '@/');
        $pagamentos = array_filter((array) (json_decode((string) ($i['formas_pagamento'] ?? ''), true) ?: []), 'is_string');
        $idiomas = array_filter((array) (json_decode((string) ($i['idiomas'] ?? ''), true) ?: []), fn ($x) => is_string($x) && $x !== 'Outros');
        $d = [
            '@type' => $tipo, 'name' => (string) $i['nome'], 'url' => $base . $caminho,
            'description' => trim((string) ($i['descricao_promocional'] ?? '')) ?: null,
            'image' => $fotos ?: null,
            'address' => array_filter(['@type' => 'PostalAddress', 'streetAddress' => $rua ?: null, 'addressLocality' => $destino['cidade'] ?? null,
                                       'addressRegion' => $destino['uf'] ?? null, 'postalCode' => preg_match('/^\d{8}$/', (string) ($i['cep'] ?? '')) ? substr((string) $i['cep'], 0, 5) . '-' . substr((string) $i['cep'], 5) : null, 'addressCountry' => 'BR']),
            'geo' => $i['latitude'] !== null ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $i['latitude'], 'longitude' => (float) $i['longitude']] : null,
            'telephone' => $tel !== '' ? '+' . $tel : null,
            'sameAs' => array_values(array_filter([$site ?: null, $insta !== '' ? 'https://instagram.com/' . $insta : null])) ?: null,
            'openingHoursSpecification' => self::horario((string) ($i['horario'] ?? '')) ?: null,
            'paymentAccepted' => $pagamentos ? implode(', ', $pagamentos) : null,
            'availableLanguage' => $idiomas ? array_values($idiomas) : null,
            'containedInPlace' => ['@type' => 'City', 'name' => $destino['cidade'] ?? $destino['nome_fantasia']],
        ];
        if ($tipo === 'LodgingBusiness' && !empty($i['capacidade_uh'])) {
            $d['numberOfRooms'] = (int) $i['capacidade_uh'];
        }
        if (in_array($tipo, ['TouristAttraction', 'Park', 'Museum'], true)) {
            $d['touristType'] = $i['tipo_nome'] ?? null;
        }
        $titulo = Portal::PILARES[$pilar][0] ?? null;
        return [array_filter($d, fn ($v) => $v !== null && $v !== '' && $v !== []),
                self::trilha(array_filter([['Início', '/'], $titulo ? [$titulo, '/' . $pilar] : null, [(string) $i['nome'], $caminho]]))];
    }

    /** Um roteiro: a viagem com as paradas em ordem. */
    public static function roteiro(array $destino, array $r, string $caminho): array
    {
        $base = self::base();
        $paradas = [];
        foreach (array_values($r['paradas']) as $k => $p) {
            $paradas[] = ['@type' => 'ListItem', 'position' => $k + 1, 'item' => array_filter([
                '@type' => 'TouristAttraction', 'name' => (string) $p['nome'],
                'url' => $base . '/lugar/' . (int) $p['id'] . '-' . Portal::slug((string) $p['nome']),
                'geo' => ($p['latitude'] ?? null) !== null ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $p['latitude'], 'longitude' => (float) $p['longitude']] : null,
            ])];
        }
        $d = array_filter([
            '@type' => 'TouristTrip', 'name' => (string) $r['titulo'], 'url' => $base . $caminho,
            'description' => trim((string) ($r['descricao'] ?? '')) ?: null,
            'itinerary' => $paradas ? ['@type' => 'ItemList', 'numberOfItems' => count($paradas), 'itemListElement' => $paradas] : null,
            'provider' => ['@type' => 'GovernmentOrganization', 'name' => 'Turismo de ' . $destino['nome_fantasia']],
        ]);
        return [$d, self::trilha([['Início', '/'], ['Roteiros', '/roteiros'], [(string) $r['titulo'], $caminho]])];
    }

    /** A trilha (BreadcrumbList), da página inicial até a página. */
    public static function trilha(array $passos): array
    {
        $base = self::base();
        $itens = [];
        foreach (array_values($passos) as $k => [$nome, $caminho]) {
            $itens[] = ['@type' => 'ListItem', 'position' => $k + 1, 'name' => $nome, 'item' => $base . $caminho];
        }
        return ['@type' => 'BreadcrumbList', 'itemListElement' => $itens];
    }

    /** O horário da ficha em OpeningHoursSpecification. */
    private static function horario(string $json): array
    {
        $h = json_decode($json, true) ?: [];
        if (!empty($h['24h'])) {
            return [['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => array_values(self::DIAS), 'opens' => '00:00', 'closes' => '23:59']];
        }
        $saida = [];
        foreach (self::DIAS as $dia => $nome) {
            foreach ((array) ($h[$dia] ?? []) as $f) {
                if (preg_match('/^\d\d:\d\d$/', (string) ($f[0] ?? '')) && preg_match('/^\d\d:\d\d$/', (string) ($f[1] ?? ''))) {
                    $saida[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $nome, 'opens' => $f[0], 'closes' => $f[1]];
                }
            }
        }
        return $saida;
    }

    /** O bloco para o <head>: um @graph com tudo o que a página mandou. */
    public static function bloco(array $itens): string
    {
        if (!$itens) {
            return '';
        }
        $json = json_encode(['@context' => 'https://schema.org', '@graph' => array_values($itens)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        return '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }
}
