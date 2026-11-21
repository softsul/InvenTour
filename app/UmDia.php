<?php
/**
 * O "Um dia aqui" do portal: só o que a equipe do destino montou.
 * Softsul Software
 *
 * A equipe cria, em Roteiros, quantos roteiros do tipo "Um dia aqui" quiser
 * e decide tudo em cada um: onde o dia começa, a hora de cada parada, quanto
 * tempo fica em cada lugar e onde termina. O portal só sorteia entre os que
 * estão publicados (a semente muda o sorteio e o mantém no link). Nada é
 * montado sozinho: sem roteiro publicado, o item sai do menu do portal.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

final class UmDia
{
    /** Hora HH:MM válida, ou null. */
    public static function hora(mixed $v): ?string
    {
        $v = trim(is_scalar($v) ? (string) $v : '');
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
    }

    /** Os roteiros "Um dia aqui" que o portal pode mostrar (ids). */
    public static function roteiros(int $eid, bool $previa): array
    {
        $q = Database::conexao()->prepare("SELECT id FROM roteiros WHERE empresa_id = ? AND tipo = 'um_dia'" . ($previa ? '' : ' AND publicado = 1') . ' ORDER BY id');
        $q->execute([$eid]);
        return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    /** O portal tem "Um dia aqui"? */
    public static function existe(int $eid, bool $previa): bool
    {
        return (bool) self::roteiros($eid, $previa);
    }

    /** Tempo para ler: "40 min", "1 h", "1 h 30". */
    public static function duracao(?int $min): string
    {
        if (!$min) {
            return '';
        }
        return $min < 60 ? $min . ' min' : intdiv($min, 60) . ' h' . ($min % 60 ? ' ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) : '');
    }

    /**
     * Um dos roteiros da equipe, sorteado: ['titulo', 'descricao',
     * 'roteiro_id', 'passos' => list<[hora, rotulo, duracao, item, metros, nota]>].
     * Null quando não há roteiro publicado (ou nenhum com parada visível).
     *
     * @param callable(int):?array $roteiro roteiro público com as paradas (Portal::roteiro)
     */
    public static function montar(int $eid, int $semente, bool $previa, callable $roteiro): ?array
    {
        $ids = self::roteiros($eid, $previa);
        if (!$ids) {
            return null;
        }
        mt_srand($semente);
        shuffle($ids);
        mt_srand();
        foreach ($ids as $id) {
            if ($dia = self::doRoteiro($roteiro($id))) {
                return $dia;
            }
        }
        return null;
    }

    private static function doRoteiro(?array $r): ?array
    {
        if (!$r || empty($r['paradas'])) {
            return null;
        }
        $passos = [];
        $ultimo = null;
        foreach ($r['paradas'] as $item) {
            $metros = $ultimo && $item['latitude'] !== null && $ultimo['latitude'] !== null ? (int) round(self::distancia($ultimo, $item)) : null;
            $passos[] = ['hora' => (string) ($item['hora'] ?? ''), 'rotulo' => '', 'duracao' => self::duracao(isset($item['duracao_min']) ? (int) $item['duracao_min'] : null),
                         'item' => $item, 'metros' => $metros, 'nota' => $item['nota'] ?? null];
            $ultimo = $item;
        }
        return ['titulo' => (string) $r['titulo'], 'descricao' => (string) ($r['descricao'] ?? ''), 'roteiro_id' => (int) $r['id'], 'passos' => $passos];
    }

    private static function distancia(array $a, array $b): float
    {
        [$la1, $lo1, $la2, $lo2] = array_map('deg2rad', [(float) $a['latitude'], (float) $a['longitude'], (float) $b['latitude'], (float) $b['longitude']]);
        $h = sin(($la2 - $la1) / 2) ** 2 + cos($la1) * cos($la2) * sin(($lo2 - $lo1) / 2) ** 2;
        return 2 * 6371000 * asin(min(1, sqrt($h)));
    }
}
