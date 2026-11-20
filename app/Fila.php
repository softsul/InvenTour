<?php
/**
 * Fila de trabalho em segundo plano.
 * Softsul Software
 *
 * Mesmo desenho da fila do Orbit e do Matter: uma tabela no MySQL e um
 * processo que toma o job com um UPDATE condicional, que é a trava atômica.
 * Dois processadores rodando ao mesmo tempo não pegam o mesmo job, porque
 * só um consegue mudar a linha de 'pendente' para 'processando'.
 *
 * Sem Redis, que não faz falta nesta escala. Imagem, card, PDF, e-mail,
 * WhatsApp e detecção de conflito nunca rodam dentro da requisição do
 * usuário. O job leva empresa_id para o painel da plataforma ver a fila por
 * destino; é informativo, não filtro: o processador roda tudo.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

class Fila
{
    /**
     * Enfileira um trabalho. $quando permite agendar para o futuro;
     * $empresaId marca de que destino é o trabalho (null para o que é da
     * plataforma ou de todos, como a faxina).
     */
    public static function enfileirar(string $tipo, array $payload = [], ?string $quando = null, ?int $empresaId = null): int
    {
        $pdo = Database::conexao();
        $pdo->prepare(
            'INSERT INTO jobs (empresa_id, tipo, payload, status, executar_em, criado_em)
             VALUES (?, ?, ?, "pendente", ?, NOW())'
        )->execute([$empresaId ?: null, $tipo, json_encode($payload, JSON_UNESCAPED_UNICODE), $quando ?? date('Y-m-d H:i:s')]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Toma o próximo job pendente, ou devolve null.
     *
     * O UPDATE condicional é o ponto todo: entre o SELECT e o UPDATE outro
     * processo pode ter pegado a mesma linha, e só quem consegue mudar o
     * status de 'pendente' fica com o trabalho.
     */
    public static function proximo(): ?array
    {
        $pdo = Database::conexao();
        $busca = $pdo->query(
            'SELECT id FROM jobs
             WHERE status = "pendente" AND executar_em <= NOW()
             ORDER BY id LIMIT 5'
        )->fetchAll();

        foreach ($busca as $linha) {
            $trava = $pdo->prepare(
                'UPDATE jobs SET status = "processando", iniciado_em = NOW(),
                        tentativas = tentativas + 1
                 WHERE id = ? AND status = "pendente"'
            );
            $trava->execute([$linha['id']]);
            if ($trava->rowCount() === 1) {
                $job = $pdo->prepare('SELECT * FROM jobs WHERE id = ?');
                $job->execute([$linha['id']]);
                $job = $job->fetch();
                $job['payload'] = json_decode((string) $job['payload'], true) ?: [];
                return $job;
            }
        }
        return null;
    }

    public static function concluir(int $id): void
    {
        Database::conexao()
            ->prepare('UPDATE jobs SET status = "concluido", concluido_em = NOW(), erro = NULL WHERE id = ?')
            ->execute([$id]);
    }

    /**
     * Marca a falha. Volta para a fila enquanto houver tentativa sobrando,
     * com espera crescente, porque a maioria das falhas é temporária: API
     * fora do ar, disco ocupado, GD concorrendo com outro job.
     */
    public static function falhar(int $id, string $erro): void
    {
        $pdo = Database::conexao();
        $job = $pdo->prepare('SELECT tentativas, max_tentativas FROM jobs WHERE id = ?');
        $job->execute([$id]);
        $job = $job->fetch();

        if ($job && (int) $job['tentativas'] < (int) $job['max_tentativas']) {
            $espera = 5 * (2 ** (int) $job['tentativas']); // 10, 20, 40 minutos
            $pdo->prepare(
                'UPDATE jobs SET status = "pendente", erro = ?,
                        executar_em = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?'
            )->execute([mb_substr($erro, 0, 2000), $espera, $id]);
            return;
        }
        $pdo->prepare('UPDATE jobs SET status = "falhou", erro = ?, concluido_em = NOW() WHERE id = ?')
            ->execute([mb_substr($erro, 0, 2000), $id]);
    }
}
