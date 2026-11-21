<?php
/**
 * O "Um dia aqui" do portal, definido pela equipe do destino.
 * Softsul Software
 *
 * Duas fontes, que o portal sorteia entre si (a semente muda o sorteio e o
 * mantém no link):
 *  - os roteiros do tipo "Um dia aqui" publicados, montados pela equipe, com
 *    cada parada numa etapa do dia;
 *  - as regras por etapa: os tipos de lugar que valem em cada etapa. O portal
 *    sorteia o começo entre os lugares da manhã e segue pelo mais perto que
 *    obedece a regra da etapa seguinte e atende naquela hora (horário da
 *    ficha ou período de atendimento; sem nenhum dos dois, vale).
 * Sem roteiro e sem regra, não há "Um dia aqui": o item sai do menu.
 *
 * As regras ficam em ajustes (grupo um_dia, painel) e numa cópia em
 * storage/cache/portal/um-dia-<destino>.json, porque o usuário do banco do
 * portal não lê ajustes.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

final class UmDia
{
    /** etapa => [hora, rótulo no portal, nome no painel, períodos de atendimento que servem] */
    public const ETAPAS = [
        'manha'     => ['09:00', 'Comece por', 'Manhã', ['cafe', 'almoco']],
        'almoco'    => ['12:30', 'Almoce em', 'Almoço', ['almoco']],
        'tarde'     => ['14:30', 'À tarde', 'Tarde', ['tarde']],
        'fim_tarde' => ['17:00', 'No fim da tarde', 'Fim da tarde', ['tarde']],
        'jantar'    => ['20:00', 'Jante em', 'Jantar', ['jantar']],
        'pouso'     => ['22:00', 'Durma em', 'Pouso', []],
    ];

    private static function arquivo(int $eid): string
    {
        $cfg = require __DIR__ . '/config.php';
        return rtrim((string) $cfg['caminhos']['cache'], '/') . '/portal/um-dia-' . $eid . '.json';
    }

    /** As regras do destino: etapa => lista de tipo_id (só as etapas com regra). */
    public static function regras(int $eid): array
    {
        $d = json_decode((string) @file_get_contents(self::arquivo($eid)), true);
        $r = [];
        foreach (self::ETAPAS as $k => $_) {
            $ids = array_values(array_filter(array_map('intval', (array) ($d[$k] ?? []))));
            if ($ids) {
                $r[$k] = $ids;
            }
        }
        return $r;
    }

    /** Grava as regras (painel): ajustes e a cópia que o portal lê. */
    public static function salvarRegras(int $eid, array $regras, ?int $usuarioId = null): void
    {
        require_once __DIR__ . '/Ajustes.php';
        $limpo = [];
        foreach (self::ETAPAS as $k => $_) {
            $limpo[$k] = array_values(array_unique(array_filter(array_map('intval', (array) ($regras[$k] ?? [])))));
        }
        Ajustes::salvar('um_dia', ['regras' => json_encode($limpo)], $eid, $usuarioId);
        self::publicarRegras($eid, $limpo);
    }

    /** Refaz a cópia do portal a partir dos ajustes (a fila chama todo dia). */
    public static function publicarRegras(int $eid, ?array $regras = null): void
    {
        if ($regras === null) {
            require_once __DIR__ . '/Ajustes.php';
            $regras = json_decode(Ajustes::ler('um_dia', 'regras', '{}', $eid), true) ?: [];
        }
        $f = self::arquivo($eid);
        @mkdir(dirname($f), 02770, true);
        file_put_contents($f . '.tmp', json_encode($regras));
        rename($f . '.tmp', $f);
    }

    /** Os roteiros "Um dia aqui" que o portal pode mostrar (ids). */
    public static function roteiros(int $eid, bool $previa): array
    {
        $q = Database::conexao()->prepare("SELECT id FROM roteiros WHERE empresa_id = ? AND tipo = 'um_dia'" . ($previa ? '' : ' AND publicado = 1') . ' ORDER BY id');
        $q->execute([$eid]);
        return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    /** O portal tem "Um dia aqui"? (roteiro da equipe ou regra de manhã) */
    public static function existe(int $eid, bool $previa): bool
    {
        return self::roteiros($eid, $previa) || isset(self::regras($eid)['manha']);
    }

    /** O lugar atende na etapa? Horário por dia vale primeiro; depois o período; sem nada, vale. */
    public static function atende(?string $horario, string $etapa, int $diaDaSemana): bool
    {
        $h = json_decode((string) $horario, true) ?: [];
        if (!empty($h['24h']) || $etapa === 'pouso') {
            return true;
        }
        $dias = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'];
        if (array_filter(array_intersect_key($h, array_flip($dias)))) {
            $hora = self::ETAPAS[$etapa][0];
            foreach ((array) ($h[$dias[$diaDaSemana - 1]] ?? []) as $p) {
                if (is_array($p) && ($p[0] ?? '') !== '' && ($p[1] ?? '') !== '' && $hora >= $p[0] && ($hora < $p[1] || $p[1] < $p[0])) {
                    return true;
                }
            }
            return false;
        }
        if (!empty($h['periodos'])) {
            return (bool) array_intersect((array) $h['periodos'], self::ETAPAS[$etapa][3]);
        }
        return true;
    }

    /**
     * O dia sorteado: ['titulo', 'descricao', 'equipe' => bool, 'passos' => [hora, rotulo, item, metros, nota]].
     * Null quando o destino não definiu nada (ou não há lugar que sirva).
     *
     * @param callable():array $todos itens públicos com tipo_id e coordenada (Portal::itens)
     * @param callable(int):?array $roteiro roteiro público com as paradas (Portal::roteiro)
     */
    public static function montar(int $eid, int $semente, bool $previa, callable $roteiro, callable $todos): ?array
    {
        $opcoes = array_map(fn ($id) => ['roteiro', $id], self::roteiros($eid, $previa));
        $regras = self::regras($eid);
        if (isset($regras['manha'])) {
            $opcoes[] = ['regras', 0];
        }
        if (!$opcoes) {
            return null;
        }
        mt_srand($semente);
        $ordem = $opcoes;
        shuffle($ordem);
        $dia = null;
        foreach ($ordem as [$tipo, $id]) {
            $dia = $tipo === 'roteiro' ? self::doRoteiro($roteiro($id)) : self::pelasRegras($eid, $regras, $todos());
            if ($dia) {
                break;
            }
        }
        mt_srand();
        return $dia;
    }

    private static function doRoteiro(?array $r): ?array
    {
        if (!$r || empty($r['paradas'])) {
            return null;
        }
        $passos = [];
        $ultimo = null;
        foreach ($r['paradas'] as $item) {
            $e = self::ETAPAS[$item['etapa'] ?? ''] ?? null;
            $metros = $ultimo && $item['latitude'] !== null && $ultimo['latitude'] !== null
                ? (int) round(self::distancia($ultimo, $item)) : null;
            $passos[] = ['hora' => $e[0] ?? '', 'rotulo' => $e[1] ?? 'Depois', 'item' => $item, 'metros' => $metros, 'nota' => $item['nota'] ?? null];
            $ultimo = $item;
        }
        return ['titulo' => (string) $r['titulo'], 'descricao' => (string) ($r['descricao'] ?? ''), 'equipe' => true, 'roteiro_id' => (int) $r['id'], 'passos' => $passos];
    }

    private static function pelasRegras(int $eid, array $regras, array $todos): ?array
    {
        $diaSemana = (int) date('N');
        $comPonto = array_filter($todos, fn ($i) => $i['latitude'] !== null && $i['longitude'] !== null && !empty($i['tipo_id']));
        $daEtapa = fn (string $k) => array_values(array_filter($comPonto, fn ($i) => in_array((int) $i['tipo_id'], $regras[$k] ?? [], true)
            && self::atende($i['horario'] ?? null, $k, $diaSemana)));
        $manha = $daEtapa('manha');
        if (!$manha) {
            return null;
        }
        $comFoto = array_values(array_filter($manha, fn ($i) => !empty($i['foto'])));
        $base = $comFoto ?: $manha;
        $inicio = $base[mt_rand(0, count($base) - 1)];
        $usados = [(int) $inicio['id'] => true];
        $passos = [['hora' => self::ETAPAS['manha'][0], 'rotulo' => self::ETAPAS['manha'][1], 'item' => $inicio, 'metros' => null, 'nota' => null]];
        $ultimo = $inicio;
        foreach (array_slice(array_keys(self::ETAPAS), 1) as $k) {
            if (empty($regras[$k])) {
                continue;
            }
            $melhor = null;
            $dist = PHP_FLOAT_MAX;
            foreach ($daEtapa($k) as $i) {
                if (isset($usados[(int) $i['id']])) {
                    continue;
                }
                $d = self::distancia($ultimo, $i);
                if ($d < $dist) {
                    [$melhor, $dist] = [$i, $d];
                }
            }
            if (!$melhor) {
                continue;
            }
            $usados[(int) $melhor['id']] = true;
            $passos[] = ['hora' => self::ETAPAS[$k][0], 'rotulo' => self::ETAPAS[$k][1], 'item' => $melhor, 'metros' => (int) round($dist), 'nota' => null];
            $ultimo = $melhor;
        }
        return count($passos) > 1 ? ['titulo' => '', 'descricao' => '', 'equipe' => false, 'roteiro_id' => 0, 'passos' => $passos] : null;
    }

    private static function distancia(array $a, array $b): float
    {
        [$la1, $lo1, $la2, $lo2] = array_map('deg2rad', [(float) $a['latitude'], (float) $a['longitude'], (float) $b['latitude'], (float) $b['longitude']]);
        $h = sin(($la2 - $la1) / 2) ** 2 + cos($la1) * cos($la2) * sin(($lo2 - $lo1) / 2) ** 2;
        return 2 * 6371000 * asin(min(1, sqrt($h)));
    }
}
