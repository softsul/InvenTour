<?php
/**
 * O "Um dia aqui" do portal, definido pela equipe do destino.
 * Softsul Software
 *
 * Duas fontes, que o portal sorteia entre si (a semente muda o sorteio e o
 * mantém no link):
 *  - os roteiros do tipo "Um dia aqui" publicados: a equipe põe as paradas
 *    que quiser, cada uma com a hora de chegada e o tempo de visita livres
 *    (cinco pontos numa manhã, ou um lugar que toma o dia inteiro);
 *  - os blocos de regras: cada bloco tem nome, hora de início, quantos
 *    lugares, minutos em cada lugar e os tipos de lugar que valem. O portal
 *    começa num lugar do primeiro bloco e segue sempre pelo mais perto que
 *    serve ao bloco e atende naquela hora (horário da ficha ou período de
 *    atendimento; sem nenhum dos dois, vale).
 * Sem roteiro e sem bloco, não há "Um dia aqui": o item sai do menu.
 *
 * Os blocos ficam em ajustes (grupo um_dia, chave blocos) e numa cópia em
 * storage/cache/portal/um-dia-<destino>.json, porque o usuário do banco do
 * portal não lê ajustes.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

final class UmDia
{
    public const MAX_BLOCOS = 12;
    public const MAX_LUGARES = 10;

    private static function arquivo(int $eid): string
    {
        $cfg = require __DIR__ . '/config.php';
        return rtrim((string) $cfg['caminhos']['cache'], '/') . '/portal/um-dia-' . $eid . '.json';
    }

    /** Hora HH:MM válida, ou null. */
    public static function hora(mixed $v): ?string
    {
        $v = trim(is_scalar($v) ? (string) $v : '');
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
    }

    /**
     * Limpa os blocos: nome, hora, lugares (1 a 10), minutos (5 a 600), tipos.
     * Bloco sem hora ou sem tipo sai; a lista fica na ordem da hora.
     *
     * @return list<array{nome:string,hora:string,lugares:int,minutos:int,tipos:list<int>}>
     */
    public static function limpar(array $blocos): array
    {
        $limpo = [];
        foreach (array_slice($blocos, 0, self::MAX_BLOCOS) as $b) {
            $hora = self::hora($b['hora'] ?? null);
            $tipos = array_values(array_unique(array_filter(array_map('intval', (array) ($b['tipos'] ?? [])))));
            if (!$hora || !$tipos) {
                continue;
            }
            $limpo[] = ['nome' => mb_substr(trim((string) ($b['nome'] ?? '')), 0, 60), 'hora' => $hora,
                        'lugares' => max(1, min(self::MAX_LUGARES, (int) ($b['lugares'] ?? 1))),
                        'minutos' => max(5, min(600, (int) ($b['minutos'] ?? 60))), 'tipos' => $tipos];
        }
        usort($limpo, fn ($a, $b) => strcmp($a['hora'], $b['hora']));
        return $limpo;
    }

    /** Os blocos do destino, como o portal lê. */
    public static function blocos(int $eid): array
    {
        $d = json_decode((string) @file_get_contents(self::arquivo($eid)), true);
        return self::limpar(is_array($d) && array_is_list($d) ? $d : []);
    }

    /** Grava os blocos (painel): ajustes e a cópia que o portal lê. */
    public static function salvarBlocos(int $eid, array $blocos, ?int $usuarioId = null): array
    {
        require_once __DIR__ . '/Ajustes.php';
        $limpo = self::limpar($blocos);
        Ajustes::salvar('um_dia', ['blocos' => json_encode($limpo, JSON_UNESCAPED_UNICODE)], $eid, $usuarioId);
        self::publicar($eid, $limpo);
        return $limpo;
    }

    /** Refaz a cópia do portal a partir dos ajustes (a fila chama todo dia). */
    public static function publicar(int $eid, ?array $blocos = null): void
    {
        if ($blocos === null) {
            require_once __DIR__ . '/Ajustes.php';
            $blocos = self::limpar(json_decode(Ajustes::ler('um_dia', 'blocos', '[]', $eid), true) ?: []);
        }
        $f = self::arquivo($eid);
        @mkdir(dirname($f), 02770, true);
        file_put_contents($f . '.tmp', json_encode($blocos, JSON_UNESCAPED_UNICODE));
        rename($f . '.tmp', $f);
    }

    /** Os roteiros "Um dia aqui" que o portal pode mostrar (ids). */
    public static function roteiros(int $eid, bool $previa): array
    {
        $q = Database::conexao()->prepare("SELECT id FROM roteiros WHERE empresa_id = ? AND tipo = 'um_dia'" . ($previa ? '' : ' AND publicado = 1') . ' ORDER BY id');
        $q->execute([$eid]);
        return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    /** O portal tem "Um dia aqui"? (roteiro da equipe ou bloco de regras) */
    public static function existe(int $eid, bool $previa): bool
    {
        return self::roteiros($eid, $previa) || self::blocos($eid);
    }

    /** Tempo para ler: "40 min", "1 h", "1 h 30". */
    public static function duracao(?int $min): string
    {
        if (!$min) {
            return '';
        }
        return $min < 60 ? $min . ' min' : intdiv($min, 60) . ' h' . ($min % 60 ? ' ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) : '');
    }

    /** HH:MM + minutos. */
    private static function somar(string $hora, int $min): string
    {
        [$h, $m] = array_map('intval', explode(':', $hora));
        $t = min(23 * 60 + 59, $h * 60 + $m + $min);
        return sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
    }

    /**
     * O lugar atende nesta hora? Horário por dia vale primeiro; depois o
     * período de atendimento (café até 11h, almoço até 15h, tarde até 19h,
     * jantar depois); sem nada, vale.
     */
    public static function atende(?string $horario, string $hora, int $diaDaSemana): bool
    {
        $h = json_decode((string) $horario, true) ?: [];
        if (!empty($h['24h'])) {
            return true;
        }
        $dias = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'];
        if (array_filter(array_intersect_key($h, array_flip($dias)))) {
            foreach ((array) ($h[$dias[$diaDaSemana - 1]] ?? []) as $p) {
                if (is_array($p) && ($p[0] ?? '') !== '' && ($p[1] ?? '') !== '' && $hora >= $p[0] && ($hora < $p[1] || $p[1] < $p[0])) {
                    return true;
                }
            }
            return false;
        }
        if (!empty($h['periodos'])) {
            $periodo = $hora < '11:00' ? 'cafe' : ($hora < '15:00' ? 'almoco' : ($hora < '19:00' ? 'tarde' : 'jantar'));
            return in_array($periodo, (array) $h['periodos'], true);
        }
        return true;
    }

    /**
     * O dia sorteado: ['titulo', 'descricao', 'equipe' => bool, 'passos' =>
     * list<[hora, rotulo, duracao, item, metros, nota]>]. Null quando o destino
     * não definiu nada (ou nenhum lugar serve).
     *
     * @param callable(int):?array $roteiro roteiro público com as paradas (Portal::roteiro)
     * @param callable():array $todos itens públicos com tipo_id e coordenada (Portal::itens)
     */
    public static function montar(int $eid, int $semente, bool $previa, callable $roteiro, callable $todos): ?array
    {
        $opcoes = array_map(fn ($id) => ['roteiro', $id], self::roteiros($eid, $previa));
        $blocos = self::blocos($eid);
        if ($blocos) {
            $opcoes[] = ['regras', 0];
        }
        if (!$opcoes) {
            return null;
        }
        mt_srand($semente);
        shuffle($opcoes);
        $dia = null;
        foreach ($opcoes as [$tipo, $id]) {
            $dia = $tipo === 'roteiro' ? self::doRoteiro($roteiro($id)) : self::pelosBlocos($blocos, $todos());
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
            $metros = $ultimo && $item['latitude'] !== null && $ultimo['latitude'] !== null ? (int) round(self::distancia($ultimo, $item)) : null;
            $passos[] = ['hora' => (string) ($item['hora'] ?? ''), 'rotulo' => '', 'duracao' => self::duracao(isset($item['duracao_min']) ? (int) $item['duracao_min'] : null),
                         'item' => $item, 'metros' => $metros, 'nota' => $item['nota'] ?? null];
            $ultimo = $item;
        }
        return ['titulo' => (string) $r['titulo'], 'descricao' => (string) ($r['descricao'] ?? ''), 'equipe' => true, 'roteiro_id' => (int) $r['id'], 'passos' => $passos];
    }

    private static function pelosBlocos(array $blocos, array $todos): ?array
    {
        $diaSemana = (int) date('N');
        $comPonto = array_values(array_filter($todos, fn ($i) => $i['latitude'] !== null && $i['longitude'] !== null && !empty($i['tipo_id'])));
        $serve = fn (array $i, array $b, string $hora) => in_array((int) $i['tipo_id'], $b['tipos'], true) && self::atende($i['horario'] ?? null, $hora, $diaSemana);
        $usados = [];
        $passos = [];
        $ultimo = null;
        foreach ($blocos as $b) {
            for ($n = 0; $n < $b['lugares']; $n++) {
                $hora = self::somar($b['hora'], $n * $b['minutos']);
                $cand = array_values(array_filter($comPonto, fn ($i) => !isset($usados[(int) $i['id']]) && $serve($i, $b, $hora)));
                if (!$cand) {
                    break;
                }
                if ($ultimo === null) {
                    // O começo do dia é sorteado (com foto, quando há).
                    $comFoto = array_values(array_filter($cand, fn ($i) => !empty($i['foto'])));
                    $base = $comFoto ?: $cand;
                    [$escolhido, $metros] = [$base[mt_rand(0, count($base) - 1)], null];
                } else {
                    usort($cand, fn ($x, $y) => self::distancia($ultimo, $x) <=> self::distancia($ultimo, $y));
                    [$escolhido, $metros] = [$cand[0], (int) round(self::distancia($ultimo, $cand[0]))];
                }
                $usados[(int) $escolhido['id']] = true;
                $passos[] = ['hora' => $hora, 'rotulo' => $n === 0 ? $b['nome'] : '', 'duracao' => self::duracao($b['minutos']),
                             'item' => $escolhido, 'metros' => $metros, 'nota' => null];
                $ultimo = $escolhido;
            }
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
