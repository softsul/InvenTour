<?php
/**
 * Plus code (Open Location Code) para latitude e longitude, sem chamada
 * externa e sem custo.
 * Softsul Software
 *
 * O caderno de Pato Branco traz o código curto de cada lugar (Q8FF+VM): os
 * quatro primeiros dígitos do código completo foram tirados porque valem
 * para a cidade toda. Para recuperar, usa-se uma referência próxima (a
 * coordenada do município), como manda o algoritmo aberto do Google
 * (github.com/google/open-location-code, licença Apache 2.0).
 */
declare(strict_types=1);

final class PlusCode
{
    private const ALFABETO = '23456789CFGHJMPQRVWX';
    private const SEPARADOR_POS = 8;

    public static function valido(string $codigo): bool
    {
        return (bool) preg_match('/^[23456789CFGHJMPQRVWX]{2,8}\+[23456789CFGHJMPQRVWX]{0,3}$/', strtoupper(trim($codigo)));
    }

    /** Centro do código (completo ou curto, com a referência). [lat, lng] ou null. */
    public static function coordenada(string $codigo, ?float $refLat = null, ?float $refLng = null): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if (!self::valido($codigo)) {
            return null;
        }
        $pos = strpos($codigo, '+');
        if ($pos < self::SEPARADOR_POS) {
            if ($refLat === null || $refLng === null) {
                return null;
            }
            return self::recuperar($codigo, $refLat, $refLng);
        }
        return self::centro($codigo);
    }

    private static function recuperar(string $curto, float $refLat, float $refLng): array
    {
        $tirados = self::SEPARADOR_POS - strpos($curto, '+');
        $resolucao = 20 ** (2 - $tirados / 2);
        $meia = $resolucao / 2;
        $prefixo = substr(self::codificar($refLat, $refLng), 0, $tirados);
        [$lat, $lng] = self::centro($prefixo . $curto);
        if ($refLat + $meia < $lat && $lat - $resolucao >= -90) {
            $lat -= $resolucao;
        } elseif ($refLat - $meia > $lat && $lat + $resolucao <= 90) {
            $lat += $resolucao;
        }
        if ($refLng + $meia < $lng) {
            $lng -= $resolucao;
        } elseif ($refLng - $meia > $lng) {
            $lng += $resolucao;
        }
        return [round($lat, 6), round($lng, 6)];
    }

    /** Código completo de 10 dígitos (o suficiente para achar o prefixo). */
    private static function codificar(float $lat, float $lng): string
    {
        $lat = min(max($lat, -90), 90) + 90;
        $lng = fmod(fmod($lng + 180, 360) + 360, 360);
        $codigo = '';
        $res = 20.0;
        for ($i = 0; $i < 5; $i++) {
            $dLat = (int) floor($lat / $res);
            $dLng = (int) floor($lng / $res);
            $lat -= $dLat * $res;
            $lng -= $dLng * $res;
            $codigo .= self::ALFABETO[min($dLat, 19)] . self::ALFABETO[min($dLng, 19)];
            $res /= 20;
            if ($i === 3) {
                $codigo .= '+';
            }
        }
        return $codigo;
    }

    /** Centro de um código completo (até 11 dígitos). */
    private static function centro(string $codigo): array
    {
        $limpo = str_replace('+', '', $codigo);
        $lat = -90.0;
        $lng = -180.0;
        $res = 20.0;
        $pares = min(strlen($limpo), 10);
        for ($i = 0; $i < $pares; $i += 2) {
            $lat += strpos(self::ALFABETO, $limpo[$i]) * $res;
            $lng += strpos(self::ALFABETO, $limpo[$i + 1] ?? '2') * $res;
            if ($i + 2 < $pares) {
                $res /= 20;
            }
        }
        $altura = $res;
        $largura = $res;
        // Décimo primeiro dígito: grade de 4 x 5 dentro da última célula.
        if (strlen($limpo) > 10) {
            $altura = $res / 5;
            $largura = $res / 4;
            $d = strpos(self::ALFABETO, $limpo[10]);
            $lat += intdiv($d, 4) * $altura;
            $lng += ($d % 4) * $largura;
        }
        return [round($lat + $altura / 2, 6), round($lng + $largura / 2, 6)];
    }
}
