<?php
/**
 * A fotografia mensal do destino (etapa 7, PLANEJAMENTO 2.9).
 * Softsul Software
 *
 * Um retrato por mês, imutável: quantos lugares por categoria e por
 * situação, a capacidade (UH, leitos, assentos, público), a formalização no
 * Cadastur, a acessibilidade, a conservação dos atrativos, as experiências,
 * os roteiros, o trade e o uso do portal no mês. É o que o relatório de
 * evolução compara mês a mês e o que o diagnóstico DTI usa de evidência.
 *
 * Tirada pela fila (job fotografia_mensal, todo dia às 2h30: só grava o
 * mês que ainda não tem retrato). Uma vez gravado, o mês não muda.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

final class Fotografia
{
    /** Os números de agora de um destino. */
    public static function medir(int $eid): array
    {
        $pdo = Database::conexao();
        $um = function (string $sql, array $par = []) use ($pdo, $eid) {
            $q = $pdo->prepare($sql);
            $q->execute(array_merge([$eid], $par));
            return $q->fetch(PDO::FETCH_ASSOC) ?: [];
        };
        $publicos = "i.situacao IN ('homologada', 'desatualizada')";
        $situacoes = [];
        $q = $pdo->prepare("SELECT situacao, COUNT(*) FROM inventario_itens WHERE empresa_id = ? GROUP BY situacao");
        $q->execute([$eid]);
        foreach ($q->fetchAll(PDO::FETCH_KEY_PAIR) as $s => $n) {
            $situacoes[$s] = (int) $n;
        }
        $categorias = [];
        $q = $pdo->prepare("SELECT f.categoria, COUNT(*) FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                            WHERE i.empresa_id = ? AND {$publicos} GROUP BY f.categoria");
        $q->execute([$eid]);
        foreach ($q->fetchAll(PDO::FETCH_KEY_PAIR) as $c => $n) {
            $categorias[$c] = (int) $n;
        }
        $cap = $um("SELECT COALESCE(SUM(CASE WHEN f.ordem IN (20, 21) THEN i.capacidade_uh END), 0) uh,
                           COALESCE(SUM(CASE WHEN f.ordem IN (20, 21) THEN i.capacidade_leitos END), 0) leitos,
                           COALESCE(SUM(i.capacidade_assentos), 0) assentos,
                           COALESCE(SUM(CASE WHEN f.ordem = 25 THEN i.capacidade_publico END), 0) eventos,
                           COUNT(*) publicos,
                           COALESCE(SUM(i.cadastur IS NOT NULL AND i.cadastur <> ''), 0) cadastur,
                           COALESCE(SUM(i.cadastur_validade IS NOT NULL AND i.cadastur_validade < CURDATE()), 0) cadastur_vencido,
                           COALESCE(SUM(i.acessibilidade_possui = 1), 0) acessiveis
                    FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id WHERE i.empresa_id = ? AND {$publicos}");
        $conservacao = [];
        $q = $pdo->prepare("SELECT COALESCE(i.conservacao, 'sem_informacao'), COUNT(*) FROM inventario_itens i JOIN inventario_fichas f ON f.id = i.ficha_id
                            WHERE i.empresa_id = ? AND {$publicos} AND f.categoria = 'C' GROUP BY 1");
        $q->execute([$eid]);
        foreach ($q->fetchAll(PDO::FETCH_KEY_PAIR) as $c => $n) {
            $conservacao[$c] = (int) $n;
        }
        $outros = $um("SELECT (SELECT COUNT(*) FROM inventario_experiencias WHERE empresa_id = e.id AND situacao = 'aprovada') experiencias,
                              (SELECT COUNT(*) FROM roteiros WHERE empresa_id = e.id AND publicado = 1) roteiros,
                              (SELECT COUNT(*) FROM inventario_reivindicacoes WHERE empresa_id = e.id AND situacao = 'aprovada') responsaveis,
                              (SELECT COUNT(*) FROM qr_pontos WHERE empresa_id = e.id AND ativo = 1) pontos_qr
                       FROM empresas e WHERE e.id = ?");
        $inicio = date('Y-m-01');
        $q = $pdo->prepare('SELECT (SELECT COALESCE(SUM(paginas), 0) FROM metricas_portal WHERE empresa_id = ? AND dia >= ?) paginas,
                                   (SELECT COALESCE(SUM(leituras), 0) FROM qr_leituras WHERE empresa_id = ? AND dia >= ?) leituras_qr,
                                   (SELECT COUNT(*) FROM inventario_itens WHERE empresa_id = ? AND homologado_em >= ?) homologados_no_mes');
        $q->execute([$eid, $inicio, $eid, $inicio, $eid, $inicio]);
        $mes = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'situacoes' => $situacoes,
            'categorias' => $categorias,
            'publicos' => (int) ($cap['publicos'] ?? 0),
            'uh' => (int) ($cap['uh'] ?? 0), 'leitos' => (int) ($cap['leitos'] ?? 0), 'assentos' => (int) ($cap['assentos'] ?? 0),
            'capacidade_eventos' => (int) ($cap['eventos'] ?? 0),
            'cadastur' => (int) ($cap['cadastur'] ?? 0), 'cadastur_vencido' => (int) ($cap['cadastur_vencido'] ?? 0),
            'acessiveis' => (int) ($cap['acessiveis'] ?? 0),
            'conservacao_atrativos' => $conservacao,
            'experiencias' => (int) ($outros['experiencias'] ?? 0), 'roteiros' => (int) ($outros['roteiros'] ?? 0),
            'responsaveis' => (int) ($outros['responsaveis'] ?? 0), 'pontos_qr' => (int) ($outros['pontos_qr'] ?? 0),
            'paginas_no_mes' => (int) ($mes['paginas'] ?? 0), 'leituras_qr_no_mes' => (int) ($mes['leituras_qr'] ?? 0),
            'homologados_no_mes' => (int) ($mes['homologados_no_mes'] ?? 0),
        ];
    }

    /** Tira o retrato do mês corrente de cada destino que ainda não tem. Devolve o log. */
    public static function tirar(): array
    {
        $pdo = Database::conexao();
        $mes = date('Y-m-01');
        $log = [];
        $ins = $pdo->prepare('INSERT IGNORE INTO destino_fotografias (empresa_id, mes, dados, tirada_em) VALUES (?, ?, ?, NOW())');
        foreach ($pdo->query('SELECT id, nome_fantasia FROM empresas WHERE ativo = 1')->fetchAll() as $e) {
            $ins->execute([(int) $e['id'], $mes, json_encode(self::medir((int) $e['id']), JSON_UNESCAPED_UNICODE)]);
            if ($ins->rowCount()) {
                $log[] = $e['nome_fantasia'] . ': retrato de ' . date('m/Y');
            }
        }
        return $log;
    }

    /** Os retratos de um destino, do mais antigo para o mais novo. */
    public static function doDestino(int $eid, int $meses = 24): array
    {
        $q = Database::conexao()->prepare('SELECT mes, dados, tirada_em FROM destino_fotografias WHERE empresa_id = ? ORDER BY mes DESC LIMIT ' . max(1, $meses));
        $q->execute([$eid]);
        return array_reverse(array_map(fn ($l) => ['mes' => $l['mes'], 'tirada_em' => $l['tirada_em'], 'dados' => json_decode((string) $l['dados'], true) ?: []], $q->fetchAll()));
    }
}
