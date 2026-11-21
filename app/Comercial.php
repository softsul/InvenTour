<?php
/**
 * A política comercial do InvenTour: preços por porte de município,
 * implantação, assinatura e o período de avaliação.
 * Softsul Software
 *
 * Fica nas Configurações da plataforma (/configuracoes/comercial), na tabela
 * `ajustes` (empresa 0, grupo comercial), e vale para todos os destinos. O
 * porte de cada destino sai da população do município sede (IBGE). O texto
 * do convite é o que a prévia bloqueada do QR do caderno mostra no modo
 * projeto, com o contato comercial.
 */
declare(strict_types=1);
require_once __DIR__ . '/Ajustes.php';

final class Comercial
{
    /** Faixas de partida: o limite de população de cada uma (null = sem teto). */
    public const FAIXAS_PADRAO = [
        ['nome' => 'Até 20 mil habitantes', 'ate' => 20000],
        ['nome' => 'De 20 a 50 mil habitantes', 'ate' => 50000],
        ['nome' => 'De 50 a 100 mil habitantes', 'ate' => 100000],
        ['nome' => 'De 100 a 500 mil habitantes', 'ate' => 500000],
        ['nome' => 'Acima de 500 mil habitantes', 'ate' => null],
    ];
    public const CONVITE_PADRAO = 'Este é o inventário turístico oficial do destino, estruturado no InvenTour. '
        . 'Para ver a ficha completa, com fotos, mapa e contatos, o destino pode ativar a avaliação gratuita.';

    /** Tudo o que a tela edita, já com os valores de partida. */
    public static function dados(): array
    {
        $g = Ajustes::grupo('comercial', 0);
        $faixas = json_decode((string) ($g['faixas'] ?? ''), true);
        if (!is_array($faixas) || !$faixas) {
            $faixas = array_map(fn ($f) => $f + ['implantacao' => null, 'mensal' => null, 'anual' => null], self::FAIXAS_PADRAO);
        }
        return [
            'faixas'           => $faixas,
            'dias_avaliacao'   => max(1, (int) ($g['dias_avaliacao'] ?? 30)),
            'convite'          => (string) ($g['convite'] ?? '') ?: self::CONVITE_PADRAO,
            'contato_email'    => (string) ($g['contato_email'] ?? ''),
            'contato_whatsapp' => (string) ($g['contato_whatsapp'] ?? ''),
        ];
    }

    /**
     * O convite e o contato comercial para a prévia do QR no portal. O
     * portal não lê a tabela ajustes (que guarda segredos de integração):
     * lê este arquivo, regravado a cada salvar da tela Comercial.
     */
    public static function publicarParaOPortal(): void
    {
        $d = self::dados();
        $cfg = require __DIR__ . '/config.php';
        @file_put_contents(rtrim((string) $cfg['caminhos']['cache'], '/') . '/portal-comercial.json', json_encode([
            'convite' => $d['convite'], 'email' => $d['contato_email'], 'whatsapp' => $d['contato_whatsapp'],
        ], JSON_UNESCAPED_UNICODE));
    }

    /** O que o portal mostra na prévia (o arquivo, ou o texto de partida). */
    public static function paraOPortal(): array
    {
        $cfg = require __DIR__ . '/config.php';
        $j = json_decode((string) @file_get_contents(rtrim((string) $cfg['caminhos']['cache'], '/') . '/portal-comercial.json'), true);
        return is_array($j) ? $j + ['convite' => self::CONVITE_PADRAO, 'email' => '', 'whatsapp' => '']
            : ['convite' => self::CONVITE_PADRAO, 'email' => '', 'whatsapp' => ''];
    }

    public static function diasDeAvaliacao(): int
    {
        return self::dados()['dias_avaliacao'];
    }

    /** A faixa de um município pela população (ou null, sem população conhecida). */
    public static function faixaDaPopulacao(?int $populacao): ?array
    {
        if ($populacao === null || $populacao <= 0) {
            return null;
        }
        foreach (self::dados()['faixas'] as $f) {
            if ($f['ate'] === null || $populacao <= (int) $f['ate']) {
                return $f;
            }
        }
        return null;
    }
}
