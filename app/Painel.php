<?php
/**
 * Os números do Início (dashboard): resumos e séries para os gráficos.
 * Softsul Software
 *
 * Cada método é uma ou duas consultas agregadas, sempre com o destino no
 * WHERE. A tela decide o que mostrar pela permissão de cada módulo.
 *
 * Regra do Luiz (23/10/2026, vale também no InvenTour): a cada
 * funcionalidade nova, o resumo dela entra aqui e no Início.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

final class Painel
{
    public const MESES_CURTOS = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    public const DIAS_CURTOS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    private static function pdo(): PDO
    {
        return Database::conexao();
    }

    /** Pessoas com acesso ao destino, fora a equipe Softsul. */
    public static function equipe(int $empresaId): int
    {
        $q = self::pdo()->prepare('SELECT COUNT(*) FROM usuario_empresa ue JOIN usuarios u ON u.id = ue.usuario_id AND u.ativo = 1 AND u.softsul = 0
                                   WHERE ue.empresa_id = ? AND ue.ativo = 1');
        $q->execute([$empresaId]);
        return (int) $q->fetchColumn();
    }

    /** Ações registradas na auditoria por dia (do destino, ou de todos na plataforma). */
    public static function atividade(int $empresaId, int $dias = 14): array
    {
        $de = date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'));
        $sql = 'SELECT DATE(criado_em) d, COUNT(*) FROM auditoria WHERE criado_em >= ?' . ($empresaId ? ' AND empresa_id = ?' : '') . ' GROUP BY d';
        $q = self::pdo()->prepare($sql);
        $q->execute($empresaId ? [$de, $empresaId] : [$de]);
        $porDia = array_map('intval', $q->fetchAll(PDO::FETCH_KEY_PAIR));
        $serie = [];
        for ($i = 0; $i < $dias; $i++) {
            $d = date('Y-m-d', strtotime($de . " +{$i} days"));
            $serie[] = ['rotulo' => self::DIAS_CURTOS[(int) date('w', strtotime($d))], 'titulo' => dataBr($d, 'd/m'), 'data' => $d, 'acoes' => $porDia[$d] ?? 0, 'link' => null];
        }
        return $serie;
    }

    /** O que chegou da coleta de campo por dia: fichas gravadas e fotos (inventario_coletas). */
    public static function coletaPorDia(int $empresaId, int $dias = 14): array
    {
        $de = date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'));
        $q = self::pdo()->prepare('SELECT DATE(recebido_em) d, tipo, COUNT(*) n FROM inventario_coletas
                                   WHERE empresa_id = ? AND recebido_em >= ? GROUP BY d, tipo');
        $q->execute([$empresaId, $de]);
        $porDia = [];
        foreach ($q->fetchAll() as $l) {
            $porDia[$l['d']][$l['tipo']] = (int) $l['n'];
        }
        $serie = [];
        for ($i = 0; $i < $dias; $i++) {
            $d = date('Y-m-d', strtotime($de . " +{$i} days"));
            $serie[] = ['rotulo' => self::DIAS_CURTOS[(int) date('w', strtotime($d))], 'titulo' => dataBr($d, 'd/m'), 'data' => $d,
                        'itens' => $porDia[$d]['item'] ?? 0, 'fotos' => $porDia[$d]['foto'] ?? 0, 'link' => null];
        }
        return $serie;
    }

    /** Uma linha por destino ativo, com a equipe e a última atividade. */
    public static function destinosPlataforma(): array
    {
        return self::pdo()->query(
            "SELECT e.id, e.nome_fantasia, e.slug, e.dominio_proprio, e.cidade, e.uf, e.logo, e.modo_operacao,
                    (SELECT COUNT(*) FROM usuario_empresa ue JOIN usuarios u ON u.id = ue.usuario_id AND u.ativo = 1 AND u.softsul = 0
                      WHERE ue.empresa_id = e.id AND ue.ativo = 1) AS equipe,
                    (SELECT MAX(a.criado_em) FROM auditoria a WHERE a.empresa_id = e.id) AS ultima_atividade
             FROM empresas e WHERE e.ativo = 1 ORDER BY e.nome_fantasia"
        )->fetchAll();
    }

    /** A fila de jobs: atrasados (passaram da hora), agendados, rodando, falhas de 7 dias e a última conclusão. */
    public static function filaPlataforma(): array
    {
        $l = self::pdo()->query("SELECT SUM(status = 'pendente' AND executar_em <= NOW() - INTERVAL 5 MINUTE), SUM(status = 'pendente' AND executar_em > NOW()),
                                        SUM(status = 'processando'), MAX(concluido_em), SUM(status = 'falhou' AND criado_em >= NOW() - INTERVAL 7 DAY)
                                 FROM jobs")->fetch(PDO::FETCH_NUM) ?: [0, 0, 0, null, 0];
        return ['atrasados' => (int) $l[0], 'agendados' => (int) $l[1], 'rodando' => (int) $l[2], 'ultimo' => $l[3], 'falhos7' => (int) $l[4]];
    }
}
