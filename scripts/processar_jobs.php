<?php
/**
 * Processador da fila.
 * Softsul Software
 *
 *   php scripts/processar_jobs.php [--limite 10]
 *
 * Roda pelo timer inventour-fila.timer, de minuto em minuto, como www-data.
 * Cada execução pega o que estiver pendente e sai; não fica residente. No
 * fim, reagenda os jobs recorrentes que não estão pendentes: é o "cron
 * dentro da fila", um lugar só para olhar quando algo não acontecer.
 *
 * Cada tipo de job é uma closure em $tratadores. Os módulos do inventário
 * só acrescentam entradas aqui.
 */
declare(strict_types=1);
require_once __DIR__ . '/../app/Fila.php';

$limite = 10;
foreach ($argv as $i => $a) {
    if ($a === '--limite' && isset($argv[$i + 1])) {
        $limite = max(1, (int) $argv[$i + 1]);
    }
}

$tratadores = [
    // Faxina diária: tentativas de login, limites e passes de certificado
    // velhos, jobs concluídos com mais de 30 dias, cache de página vencido.
    'faxina' => function (array $payload): void {
        $pdo = Database::conexao();
        $pdo->exec('DELETE FROM tentativas_login WHERE criado_em < NOW() - INTERVAL 2 DAY');
        $pdo->exec('DELETE FROM limites WHERE janela < NOW() - INTERVAL 1 DAY');
        $pdo->exec('DELETE FROM certificado_passes_usados WHERE usado_em < NOW() - INTERVAL 1 DAY');
        $pdo->exec('DELETE FROM jobs WHERE status IN ("concluido", "falhou") AND concluido_em < NOW() - INTERVAL 30 DAY');
        // LGPD (política de privacidade): aviso de devolução já liberado sai em um ano.
        $pdo->exec('DELETE FROM email_ocorrencias WHERE liberado_em < NOW() - INTERVAL 1 YEAR');
        $cfg = require __DIR__ . '/../app/config.php';
        foreach (glob($cfg['caminhos']['cache'] . '/*/*.html') ?: [] as $arquivo) {
            if (filemtime($arquivo) < time() - 3600) {
                @unlink($arquivo);
            }
        }
    },

    // Semanal: traz a planilha nova do Cadastur quando o Ministério publica
    // (a cada trimestre). O que já entrou não é baixado de novo.
    'importar_cadastur' => function (array $payload): void {
        require_once __DIR__ . '/../app/Cadastur.php';
        foreach (Cadastur::importarTudo() as $linha) {
            echo "\n   {$linha}";
        }
        echo "\n";
    },

    // Mensal: o Mapa do Turismo (regiões turísticas e categorias) muda uma
    // vez por ano; importar de novo só regrava o ano corrente.
    // Etapa 4: o caderno oficial do inventário (PDF, XLSX e CSV).
    'caderno' => function (array $payload): void {
        require_once __DIR__ . '/../app/Caderno.php';
        $r = Caderno::gerar((int) ($payload['id'] ?? 0));
        echo $r['paginas'] . ' páginas, ' . $r['itens'] . ' itens ';
    },

    // Uma vez (21/11/2026): os PDFs unidos pelo pdfunite, com o índice
    // inconsistente, regravados pelo qpdf e linearizados. O SHA-256 da
    // verificação acompanha o arquivo novo.
    'caderno_reparar' => function (array $payload): void {
        require_once __DIR__ . '/../app/Caderno.php';
        $pdo = Database::conexao();
        foreach ($pdo->query("SELECT * FROM cadernos WHERE situacao = 'pronto'")->fetchAll() as $c) {
            $pdf = Caderno::arquivo($c, 'pdf');
            if (!is_file($pdf)) {
                continue;
            }
            exec('pdfinfo ' . escapeshellarg($pdf) . ' 2>/dev/null | grep -c "Optimized:  *yes"', $s);
            if ((int) end($s) > 0) {
                continue;
            }
            $novo = $pdf . '.novo';
            exec(implode(' ', array_map('escapeshellarg', ['qpdf', '--linearize', '--object-streams=generate', $pdf, $novo])) . ' 2>/dev/null');
            if (is_file($novo) && filesize($novo) > 0) {
                rename($novo, $pdf);
                $pdo->prepare('UPDATE cadernos SET sha256 = ?, bytes = ? WHERE id = ?')->execute([hash_file('sha256', $pdf), filesize($pdf), (int) $c['id']]);
                echo 'v' . $c['versao'] . ' ';
            }
        }
    },

    // Versões do caderno apagadas fora da tela (os arquivos são do www-data).
    'caderno_apagar' => function (array $payload): void {
        require_once __DIR__ . '/../app/Caderno.php';
        foreach ((array) ($payload['ids'] ?? []) as $id) {
            echo Caderno::apagar((int) $id, (int) ($payload['empresa_id'] ?? 0)) ? '#' . (int) $id . ' ' : '';
        }
    },

    // Etapa 5: ponte com o EventCity. A agenda de cada destino com a ponte
    // ligada vai para o cache que o portal lê; o aviso de item homologado
    // vai assinado e, se falhar, a fila tenta de novo (10, 20, 40 minutos).
    'eventcity_agenda' => function (array $payload): void {
        require_once __DIR__ . '/../app/EventCity.php';
        foreach (Database::conexao()->query("SELECT empresa_id FROM ajustes WHERE grupo = 'eventcity' AND chave = 'ligado' AND valor = '1'")
                 ->fetchAll(PDO::FETCH_COLUMN) as $eid) {
            $n = EventCity::atualizarAgenda((int) $eid);
            echo '#' . $eid . ':' . ($n === null ? 'sem resposta' : $n) . ' ';
        }
    },
    'eventcity_aviso' => function (array $payload): void {
        require_once __DIR__ . '/../app/EventCity.php';
        $eid = (int) $payload['empresa_id'];
        $aviso = !empty($payload['totais']) ? EventCity::avisoDosTotais($eid) : EventCity::avisoDoItem((int) ($payload['item_id'] ?? 0));
        if (!$aviso) {
            return;
        }
        [$codigo, $resposta] = EventCity::pedir($eid, 'POST', '/api/v1/inventour/aviso', $aviso);
        if ($codigo === 202 || $codigo === 200) {
            echo ($resposta['resultado'] ?? 'aceito') . ' ';
            return;
        }
        // 422: envelope recusado. Reenviar igual não adianta: registra e para.
        if ($codigo === 422) {
            echo 'recusado (422): ' . mb_substr((string) json_encode($resposta, JSON_UNESCAPED_UNICODE), 0, 300) . ' ';
            return;
        }
        throw new RuntimeException('EventCity respondeu ' . $codigo . ' ao aviso ' . $aviso['tipo'] . (isset($payload['item_id']) ? ' do item ' . (int) $payload['item_id'] : ''));
    },

    // Etapa 5: o cartaz A4 de um ponto de QR (phygital).
    'qr_cartaz' => function (array $payload): void {
        require_once __DIR__ . '/../app/PontosQr.php';
        PontosQr::gerarCartaz((int) ($payload['id'] ?? 0));
    },

    // O contorno do município para o mapa do portal (diário, e quando pedido).
    'portal_mapa' => function (array $payload): void {
        require_once __DIR__ . '/../app/Portal.php';
        foreach (Database::conexao()->query('SELECT id FROM empresas WHERE ativo = 1 AND municipio_id IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $eid) {
            echo '#' . $eid . (Portal::gravarMapa((int) $eid) ? ' ok ' : ' sem divisa ');
        }
    },

    // O mapa base do portal (ruas do OpenStreetMap): de hora em hora, o
    // próximo destino sem mapa ou com mapa vencido (30 dias) ou com a divisa
    // mudada; com empresa_id (botão do painel), aquele destino.
    'portal_mapa_base' => function (array $payload): void {
        require_once __DIR__ . '/../app/MapaBase.php';
        $eid = (int) ($payload['empresa_id'] ?? 0) ?: MapaBase::proximo();
        if (!$eid) {
            echo 'nada a fazer ';
            return;
        }
        $s = MapaBase::gerar($eid);
        echo '#' . $eid . ' ' . $s['blocos'] . ' blocos, versão ' . $s['versao'] . ' ';
    },

    'importar_mapa_turismo' => function (array $payload): void {
        require_once __DIR__ . '/../app/MapaTurismo.php';
        echo MapaTurismo::importar() . ' ';
    },

];

$feitos = 0;
while ($feitos < $limite && ($job = Fila::proximo())) {
    $feitos++;
    echo date('H:i:s') . " #{$job['id']} {$job['tipo']} ";
    if (!isset($tratadores[$job['tipo']])) {
        Fila::falhar((int) $job['id'], 'tipo de job desconhecido');
        echo "desconhecido\n";
        continue;
    }
    try {
        $tratadores[$job['tipo']]($job['payload']);
        Fila::concluir((int) $job['id']);
        echo "ok\n";
    } catch (Throwable $e) {
        Fila::falhar((int) $job['id'], get_class($e) . ': ' . $e->getMessage());
        echo 'falhou: ' . $e->getMessage() . "\n";
    }
}

// Reagenda os recorrentes. Um por tipo: se já há um pendente, não cria outro.
$pendentes = Database::conexao()
    ->query('SELECT DISTINCT tipo FROM jobs WHERE status IN ("pendente", "processando")')
    ->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('faxina', $pendentes, true)) {
    Fila::enfileirar('faxina', [], date('Y-m-d H:i:s', strtotime('tomorrow 03:00')));
}
// Domingo de madrugada, longe do uso: são cerca de 200 mil linhas quando
// há planilha nova.
if (!in_array('importar_cadastur', $pendentes, true)) {
    Fila::enfileirar('importar_cadastur', [], date('Y-m-d H:i:s', strtotime('next sunday 04:00')));
}
if (!in_array('portal_mapa', $pendentes, true)) {
    Fila::enfileirar('portal_mapa', [], date('Y-m-d H:i:s', strtotime('tomorrow 03:30')));
}
if (!in_array('portal_mapa_base', $pendentes, true)) {
    Fila::enfileirar('portal_mapa_base', [], date('Y-m-d H:i:s', strtotime('+1 hour')));
}
if (!in_array('eventcity_agenda', $pendentes, true)) {
    Fila::enfileirar('eventcity_agenda', [], date('Y-m-d H:i:s', strtotime('+5 minutes')));
}
if (!in_array('importar_mapa_turismo', $pendentes, true)) {
    Fila::enfileirar('importar_mapa_turismo', [], date('Y-m-d H:i:s', strtotime('first day of next month 05:00')));
}
