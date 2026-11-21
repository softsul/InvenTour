<?php
/**
 * Mapa do Turismo Brasileiro: regiões turísticas e categoria dos municípios.
 * Softsul Software
 *
 * O Ministério do Turismo publica o Mapa em mapa.turismo.gov.br, com uma
 * API pública (sem login) que o próprio site usa. Uma consulta por UF
 * devolve os municípios do Mapa daquele ano: região turística, categoria
 * (1 turístico, 2 oferta complementar, 3 apoio), empregos e
 * estabelecimentos de hospedagem, visitas estimadas, arrecadação e a
 * instância de governança. São 27 chamadas pequenas.
 *
 * A importação guarda um retrato por ano em mapa_turismo, cria ou
 * reaproveita a região pelo nome dentro da UF e põe em municipios.regiao_id
 * a região do ano mais recente. O Mapa muda uma vez por ano; a fila roda
 * todo mês e só regrava o ano corrente.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/svg.php';

final class MapaTurismo
{
    private const API = 'https://www.mapa.turismo.gov.br/mapa/rest/publico/';
    private const AGENTE = 'InvenTour/1.0 (Softsul Software; importacao do Mapa do Turismo Brasileiro)';
    private const LOTE = 500;

    public const CATEGORIAS = [
        1 => 'Município turístico',
        2 => 'Oferta turística complementar',
        3 => 'Apoio ao turismo',
    ];

    /** Na pílula da lista, que é estreita. */
    public const CATEGORIAS_CURTAS = [1 => 'Turístico', 2 => 'Oferta complementar', 3 => 'Apoio'];

    /** O tom da pílula de cada categoria, como nas outras listas. */
    public const TONS = [1 => 'ok', 2 => 'at', 3 => 'nu'];

    /** Baixa o Mapa do ano mais recente e grava. Devolve o resumo para o log. */
    public static function importar(): string
    {
        $anos = array_map('intval', (array) self::pedir('regionalizacao/listar/anos-atualizacao'));
        $ano = $anos ? max($anos) : (int) date('Y');
        if ($ano < 2015 || $ano > (int) date('Y') + 1) {
            throw new RuntimeException("ano do Mapa fora do esperado: {$ano}");
        }

        $pdo = Database::conexao();
        $estados = $pdo->query('SELECT uf, id FROM estados')->fetchAll(PDO::FETCH_KEY_PAIR);
        $existe = array_flip(array_map('intval', $pdo->query('SELECT id FROM municipios')->fetchAll(PDO::FETCH_COLUMN)));

        // A API numera as UFs de 1 a 27 do jeito dela; quem diz a UF é o
        // sgUf de cada linha. O Distrito Federal (7) vem vazio: Brasília não
        // está no Mapa por UF. A conta do total, abaixo, pega resposta curta.
        $linhas = [];
        for ($nuUf = 1; $nuUf <= 27; $nuUf++) {
            array_push($linhas, ...array_values((array) self::pedir('regionalizacao/pesquisar',
                ['nuRegiao' => null, 'nuUf' => $nuUf, 'nuLocalidade' => null, 'colCluster' => null])));
        }

        $registros = [];
        $regioes = [];
        $foraDaBase = 0;
        foreach ($linhas as $l) {
            $ibge = (int) ($l['nuMunicipioIbge'] ?? 0);
            $estado = $estados[strtoupper((string) ($l['sgUf'] ?? ''))] ?? null;
            $regiao = self::nomeRegiao((string) ($l['noRegiaoTuristica'] ?? ''));
            $categoria = (int) ($l['coCluster'] ?? 0);
            if (!isset($existe[$ibge]) || !$estado || $regiao === '' || !isset(self::CATEGORIAS[$categoria])) {
                $foraDaBase++;
                continue;
            }
            $chave = $estado . '|' . mb_strtolower(semAcento($regiao));
            $regioes[$chave] ??= [$estado, $regiao];
            $cnpj = preg_replace('/\D/', '', (string) ($l['coCNPJInstancia'] ?? ''));
            $registros[$ibge] = [
                'regiao'                 => $chave,
                'categoria'              => $categoria,
                'empregos'               => self::inteiro($l['qtEmprego'] ?? null),
                'estabelecimentos'       => self::inteiro($l['qtEstabelecimento'] ?? null),
                'visitas_nacionais'      => self::inteiro($l['qtVisitaNacionalEstimada'] ?? null),
                'visitas_internacionais' => self::inteiro($l['qtVisitaInternacionalEstimada'] ?? null),
                'arrecadacao'            => is_numeric($l['arrecadacao'] ?? null) ? round((float) $l['arrecadacao'], 2) : null,
                'instancia'              => mb_substr(trim((string) ($l['noInstancia'] ?? '')), 0, 150) ?: null,
                'instancia_cnpj'         => strlen((string) $cnpj) === 14 ? $cnpj : null,
                'conselho_ativo'         => isset($l['flSituacaoConselho']) ? (int) (bool) $l['flSituacaoConselho'] : null,
            ];
        }

        // Proteção contra resposta pela metade: o Mapa tem perto de 3 mil
        // municípios, e um ano novo não perde metade deles de uma vez.
        $antes = (int) $pdo->query('SELECT COUNT(*) FROM mapa_turismo WHERE ano = (SELECT MAX(ano) FROM mapa_turismo)')->fetchColumn();
        if (count($registros) < 1000 || count($registros) < $antes * 0.7) {
            throw new RuntimeException('o Mapa veio com ' . count($registros) . " municípios (antes: {$antes}); nada foi gravado");
        }

        $agora = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('INSERT INTO regioes_turisticas (estado_id, nome, tipo, mapa_ano, ativo, criado_em)
                                VALUES (?, ?, "regiao_turistica", ?, 1, ?)
                                ON DUPLICATE KEY UPDATE nome = VALUES(nome), mapa_ano = VALUES(mapa_ano), ativo = 1, atualizado_em = VALUES(criado_em)');
            foreach ($regioes as [$estado, $nome]) {
                $q->execute([$estado, $nome, $ano, $agora]);
            }
            // Região que não está no Mapa deste ano sai das listas, mas fica
            // para os anos anteriores.
            $pdo->prepare('UPDATE regioes_turisticas SET ativo = 0, atualizado_em = ? WHERE tipo = "regiao_turistica" AND (mapa_ano IS NULL OR mapa_ano < ?) AND ativo = 1')
                ->execute([$agora, $ano]);
            $ids = [];
            foreach ($pdo->query('SELECT id, estado_id, nome FROM regioes_turisticas') as $r) {
                $ids[$r['estado_id'] . '|' . mb_strtolower(semAcento((string) $r['nome']))] = (int) $r['id'];
            }

            $pdo->prepare('DELETE FROM mapa_turismo WHERE ano = ?')->execute([$ano]);
            $colunas = ['ano', 'municipio_id', 'regiao_id', 'categoria', 'empregos', 'estabelecimentos', 'visitas_nacionais',
                'visitas_internacionais', 'arrecadacao', 'instancia', 'instancia_cnpj', 'conselho_ativo', 'importado_em'];
            foreach (array_chunk($registros, self::LOTE, true) as $lote) {
                $valores = [];
                foreach ($lote as $ibge => $r) {
                    array_push($valores, $ano, $ibge, $ids[$r['regiao']], $r['categoria'], $r['empregos'], $r['estabelecimentos'],
                        $r['visitas_nacionais'], $r['visitas_internacionais'], $r['arrecadacao'], $r['instancia'], $r['instancia_cnpj'],
                        $r['conselho_ativo'], $agora);
                }
                $marcas = implode(',', array_fill(0, count($lote), '(' . implode(',', array_fill(0, count($colunas), '?')) . ')'));
                $pdo->prepare('INSERT INTO mapa_turismo (' . implode(',', $colunas) . ") VALUES {$marcas}")->execute($valores);
            }

            // A região de cada município é a do Mapa mais recente.
            if ($ano >= (int) $pdo->query('SELECT MAX(ano) FROM mapa_turismo')->fetchColumn()) {
                $pdo->exec('UPDATE municipios SET regiao_id = NULL WHERE regiao_id IS NOT NULL');
                $pdo->prepare('UPDATE municipios m JOIN mapa_turismo t ON t.municipio_id = m.id AND t.ano = ? SET m.regiao_id = t.regiao_id')
                    ->execute([$ano]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        @unlink(self::arquivoTotais());

        return sprintf('Mapa do Turismo %d: %d municípios em %d regiões%s.', $ano, count($registros), count($regioes),
            $foraDaBase ? ", {$foraDaBase} linhas sem município conhecido" : '');
    }

    /** O ano do Mapa mais recente na base, ou null antes da primeira importação. */
    public static function ano(): ?int
    {
        $a = Database::conexao()->query('SELECT MAX(ano) FROM mapa_turismo')->fetchColumn();
        return $a ? (int) $a : null;
    }

    /**
     * O município no Mapa mais recente: região, categoria e números. Null
     * se ele não está no Mapa.
     */
    public static function doMunicipio(int $municipioId): ?array
    {
        $q = Database::conexao()->prepare(
            'SELECT t.*, r.nome AS regiao, (SELECT COUNT(*) FROM mapa_turismo v WHERE v.ano = t.ano AND v.regiao_id = t.regiao_id) AS na_regiao
             FROM mapa_turismo t JOIN regioes_turisticas r ON r.id = t.regiao_id
             WHERE t.municipio_id = ? AND t.ano = (SELECT MAX(ano) FROM mapa_turismo)'
        );
        $q->execute([$municipioId]);
        return $q->fetch() ?: null;
    }

    /**
     * Para o município que está fora do Mapa: a região turística dos
     * vizinhos (a mais comum entre os 8 municípios mais próximos da mesma UF
     * que estão no Mapa). É onde ele se encaixa ao pedir a inclusão.
     */
    public static function regiaoDosVizinhos(int $municipioId): ?array
    {
        $pdo = Database::conexao();
        $q = $pdo->prepare(
            'SELECT t.regiao_id FROM municipios m
             JOIN municipios o ON o.id = ? AND o.estado_id = m.estado_id
             JOIN mapa_turismo t ON t.municipio_id = m.id AND t.ano = (SELECT MAX(ano) FROM mapa_turismo)
             WHERE m.id <> o.id AND m.latitude IS NOT NULL AND o.latitude IS NOT NULL
             ORDER BY POW(m.latitude - o.latitude, 2) + POW((m.longitude - o.longitude) * COS(RADIANS(o.latitude)), 2)
             LIMIT 8'
        );
        $q->execute([$municipioId]);
        $contagem = array_count_values(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)));
        if (!$contagem) {
            return null;
        }
        arsort($contagem);
        $regiao = (int) array_key_first($contagem);
        $q = $pdo->prepare('SELECT r.id, r.nome, (SELECT COUNT(*) FROM mapa_turismo v WHERE v.ano = (SELECT MAX(ano) FROM mapa_turismo) AND v.regiao_id = r.id) AS na_regiao
                            FROM regioes_turisticas r WHERE r.id = ?');
        $q->execute([$regiao]);
        return $q->fetch() ?: null;
    }

    /** Os municípios de uma região no Mapa mais recente, por categoria e nome. */
    public static function daRegiao(int $regiaoId): array
    {
        $q = Database::conexao()->prepare(
            'SELECT m.id, m.nome, t.categoria FROM mapa_turismo t JOIN municipios m ON m.id = t.municipio_id
             WHERE t.regiao_id = ? AND t.ano = (SELECT MAX(ano) FROM mapa_turismo) ORDER BY t.categoria, m.nome'
        );
        $q->execute([$regiaoId]);
        return $q->fetchAll();
    }

    /**
     * Totais do Mapa mais recente: municípios, regiões e quantos em cada
     * categoria. Guardados até a próxima importação (a importação apaga).
     *
     * @return array{ano:?int,municipios:int,regioes:int,categorias:array<int,int>}
     */
    public static function totais(): array
    {
        $guardado = is_file(self::arquivoTotais()) ? json_decode((string) @file_get_contents(self::arquivoTotais()), true) : null;
        if (is_array($guardado) && isset($guardado['categorias'])) {
            return $guardado;
        }
        $pdo = Database::conexao();
        $ano = self::ano();
        $t = ['ano' => $ano, 'municipios' => 0, 'regioes' => 0, 'categorias' => [1 => 0, 2 => 0, 3 => 0]];
        if ($ano) {
            $q = $pdo->prepare('SELECT categoria, COUNT(*) FROM mapa_turismo WHERE ano = ? GROUP BY categoria');
            $q->execute([$ano]);
            foreach ($q->fetchAll(PDO::FETCH_KEY_PAIR) as $c => $n) {
                $t['categorias'][(int) $c] = (int) $n;
            }
            $t['municipios'] = array_sum($t['categorias']);
            $q = $pdo->prepare('SELECT COUNT(DISTINCT regiao_id) FROM mapa_turismo WHERE ano = ?');
            $q->execute([$ano]);
            $t['regioes'] = (int) $q->fetchColumn();
        }
        @file_put_contents(self::arquivoTotais(), json_encode($t), LOCK_EX);
        return $t;
    }

    // ------------------------------------------------------------- interno

    private static function arquivoTotais(): string
    {
        $cfg = require __DIR__ . '/config.php';
        return $cfg['caminhos']['tmp'] . '/mapa-turismo-totais.json';
    }

    /** GET (sem corpo) ou POST com JSON na API pública, com algumas tentativas. */
    private static function pedir(string $caminho, ?array $corpo = null): mixed
    {
        $erro = '';
        for ($tentativa = 1; $tentativa <= 4; $tentativa++) {
            $c = curl_init(self::API . $caminho);
            $opcoes = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => self::AGENTE,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => ['Accept: application/json']];
            if ($corpo !== null) {
                $opcoes[CURLOPT_POST] = true;
                $opcoes[CURLOPT_POSTFIELDS] = json_encode($corpo);
                $opcoes[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            }
            curl_setopt_array($c, $opcoes);
            $r = curl_exec($c);
            $codigo = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
            curl_close($c);
            $json = is_string($r) && $codigo === 200 ? json_decode($r, true) : null;
            if (is_array($json)) {
                return $json;
            }
            $erro = $codigo ? "HTTP {$codigo}" : 'sem resposta';
            sleep(3 * $tentativa);
        }
        throw new RuntimeException("API do Mapa do Turismo ({$caminho}): {$erro}");
    }

    /** "8.034" e "105" viram número; vazio vira null. */
    private static function inteiro(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return max(0, (int) $v);
        }
        $s = preg_replace('/\D/', '', explode(',', (string) $v)[0]);
        return $s === '' ? null : (int) $s;
    }

    /**
     * Nome da região como o Ministério publica, sem espaço sobrando. Parte
     * das UFs manda tudo em maiúsculas: esses viram "Águas do Noroeste".
     */
    private static function nomeRegiao(string $nome): string
    {
        $nome = trim((string) preg_replace('/\s+/u', ' ', $nome));
        if ($nome === '' || $nome !== mb_strtoupper($nome) || !preg_match('/\p{L}{4}/u', $nome)) {
            return $nome;
        }
        $palavras = explode(' ', mb_convert_case(mb_strtolower($nome), MB_CASE_TITLE));
        foreach ($palavras as $i => $p) {
            // Siglas que aparecem nos nomes: IGR (instância de governança
            // regional) e a de associação de municípios.
            if (in_array(mb_strtoupper($p), ['IGR', 'AMSAP'], true)) {
                $palavras[$i] = mb_strtoupper($p);
            } elseif ($i > 0 && in_array(mb_strtolower($p), ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'na', 'no', 'nas', 'nos', 'a', 'o', 'para', 'com'], true)) {
                $palavras[$i] = mb_strtolower($p);
            }
        }
        return implode(' ', $palavras);
    }
}
