<?php
/**
 * Caderno oficial do inventário: o PDF por versão, com XLSX e CSV.
 * Softsul Software
 *
 * A geração roda na fila (job "caderno"), nunca na requisição. Ordem fixa
 * da capitulação (PLANEJAMENTO 2.7): capa, ficha técnica, sumário, os
 * textos de O destino e a linha do tempo, as categorias A, B (com o quadro
 * de UHs e leitos) e C, e os anexos (experiências). Cada ficha do MTur
 * aparece, mesmo sem item, marcada "inexistente", como no caderno de Pato
 * Branco 2025. O campo vazio fica de fora; NA, NI e NE aparecem por extenso.
 *
 * O PDF sai em blocos pelo wkhtmltopdf e é unido em disco (qpdf, linearizado), para
 * um caderno de centenas de páginas não estourar a memória. O sumário usa
 * o mapa de títulos que o wkhtmltopdf devolve (--dump-outline), e a
 * numeração segue de um bloco para o outro (--page-offset). Rodapé com o
 * código de verificação; QR na capa (verificação) e em cada ficha (no modo
 * projeto abre a prévia bloqueada; com licença, a ficha viva).
 *
 * Só sai o que foi homologado. A prévia (opção ao pedir) inclui o que ainda
 * está em pré-cadastro, em campo ou em conferência, com a marca de versão
 * preliminar em todas as páginas. Nunca sai CPF nem contato do pesquisador.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Ajustes.php';
require_once __DIR__ . '/Inventario.php';
require_once __DIR__ . '/CadernoHtml.php';
require_once __DIR__ . '/Xlsx.php';

final class Caderno
{
    public const SITUACOES = [
        'pendente' => ['Na fila', 'at'],
        'gerando'  => ['Gerando', 'at'],
        'pronto'   => ['Pronto', 'ok'],
        'falhou'   => ['Falhou', 'ruim'],
    ];
    public const CATEGORIAS = [
        'A' => '1. Infraestrutura de apoio ao turismo',
        'B' => '2. Serviços e equipamentos turísticos',
        'C' => '3. Atrativos turísticos',
    ];
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private static function pdo(): PDO
    {
        return Database::conexao();
    }

    // ------------------------------------------------------------ ajustes

    /** A encadernação do destino (título, ano, equipe técnica, fontes), com os valores de partida. */
    public static function config(int $eid): array
    {
        $g = Ajustes::grupo('caderno', $eid);
        $q = self::pdo()->prepare('SELECT nome_fantasia FROM empresas WHERE id = ?');
        $q->execute([$eid]);
        $destino = (string) $q->fetchColumn();
        return [
            'titulo'    => (string) ($g['titulo'] ?? '') ?: 'Inventário da Oferta Turística de ' . $destino,
            'subtitulo' => (string) ($g['subtitulo'] ?? ''),
            'ano'       => (int) ($g['ano'] ?? 0) ?: (int) date('Y'),
            'equipe'    => (string) ($g['equipe'] ?? ''),
            'fontes'    => (string) ($g['fontes'] ?? ''),
            'credito_capa' => (string) ($g['credito_capa'] ?? ''),
            // A foto da capa sai desfocada (fundo, como a foto aérea do município), a não ser que se peça nítida.
            'desfocar_capa' => ($g['desfocar_capa'] ?? '1') !== '0',
        ];
    }

    // ------------------------------------------------------------ pedido

    /**
     * Pede uma versão nova e põe na fila. $categorias vazio é o caderno
     * completo; $fichas restringe a algumas fichas.
     */
    public static function pedir(int $eid, array $categorias, array $fichas, bool $previa, int $uid): int
    {
        $cfg = self::config($eid);
        $categorias = array_values(array_intersect(array_keys(self::CATEGORIAS), $categorias));
        $fichas = array_values(array_unique(array_filter(array_map('intval', $fichas))));
        $recorte = ($categorias && count($categorias) < 3) || $fichas ? ['categorias' => $categorias, 'fichas' => $fichas] : null;
        $pdo = self::pdo();
        $q = $pdo->prepare('SELECT COALESCE(MAX(versao), 0) + 1 FROM cadernos WHERE empresa_id = ?');
        $q->execute([$eid]);
        $versao = (int) $q->fetchColumn();
        do {
            $codigo = '';
            for ($i = 0; $i < 10; $i++) {
                $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
            $ja = $pdo->prepare('SELECT 1 FROM cadernos WHERE codigo = ?');
            $ja->execute([$codigo]);
        } while ($ja->fetchColumn());
        $pdo->prepare('INSERT INTO cadernos (empresa_id, titulo, ano, versao, recorte, previa, situacao, codigo, pedido_por, criado_em)
                       VALUES (?, ?, ?, ?, ?, ?, "pendente", ?, ?, NOW())')
            ->execute([$eid, $cfg['titulo'], $cfg['ano'], $versao, $recorte ? json_encode($recorte) : null, $previa ? 1 : 0, $codigo, $uid]);
        $id = (int) $pdo->lastInsertId();
        require_once __DIR__ . '/Fila.php';
        Fila::enfileirar('caderno', ['id' => $id], null, $eid);
        return $id;
    }

    public static function codigoLegivel(string $c): string
    {
        return substr($c, 0, 4) . '-' . substr($c, 4, 4) . '-' . substr($c, 8);
    }

    /** O que o recorte pediu, em palavras: "Completo", "Categoria A", "Fichas B1, B2". */
    public static function descreverRecorte(?string $json): string
    {
        $r = json_decode((string) $json, true);
        if (!$r) {
            return 'Completo';
        }
        $partes = [];
        if (!empty($r['categorias'])) {
            $partes[] = (count($r['categorias']) === 1 ? 'Categoria ' : 'Categorias ') . implode(', ', $r['categorias']);
        }
        if (!empty($r['fichas'])) {
            $marcas = implode(',', array_fill(0, count($r['fichas']), '?'));
            $q = self::pdo()->prepare("SELECT DISTINCT codigo FROM inventario_fichas WHERE id IN ({$marcas}) ORDER BY ordem");
            $q->execute(array_map('intval', $r['fichas']));
            $partes[] = 'Fichas ' . implode(', ', $q->fetchAll(PDO::FETCH_COLUMN));
        }
        return implode(' e ', $partes);
    }

    // ------------------------------------------------------------ arquivos

    public static function pasta(int $eid): string
    {
        $cfg = require __DIR__ . '/config.php';
        $pasta = dirname(rtrim((string) $cfg['caminhos']['midias'], '/')) . '/cadernos/' . $eid;
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0770, true);
        }
        return $pasta;
    }

    /** A foto da capa escolhida na Encadernação (storage/cadernos/<destino>/capa.jpg), ou null. */
    public static function fotoDaCapa(int $eid): ?string
    {
        $f = self::pasta($eid) . '/capa.jpg';
        return is_readable($f) ? $f : null;
    }

    /** Guarda a foto da capa (redesenhada, sem metadados). Devolve o erro ou null. */
    public static function guardarFotoDaCapa(int $eid, array $arquivo): ?string
    {
        require_once __DIR__ . '/Imagem.php';
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $arquivo['tmp_name'])) {
            return 'A foto não chegou inteira.';
        }
        if ((int) $arquivo['size'] > 20 * 1048576) {
            return 'A foto passa de 20 MB.';
        }
        $foto = Imagem::foto((string) file_get_contents((string) $arquivo['tmp_name']), 2400);
        if ($foto === null) {
            return 'O arquivo não é uma imagem válida.';
        }
        return @file_put_contents(self::pasta($eid) . '/capa.jpg', $foto[0]) === false ? 'Não consegui guardar a foto agora.' : null;
    }

    public static function arquivo(array $c, string $ext): string
    {
        return self::pasta((int) $c['empresa_id']) . '/caderno-' . (int) $c['id'] . '.' . $ext;
    }

    /** Apaga uma versão: os arquivos (PDF, XLSX, CSV), as leituras do QR e o registro. */
    public static function apagar(int $id, int $eid): bool
    {
        $q = self::pdo()->prepare("SELECT * FROM cadernos WHERE id = ? AND empresa_id = ? AND situacao IN ('pronto', 'falhou')");
        $q->execute([$id, $eid]);
        $c = $q->fetch();
        if (!$c) {
            return false;
        }
        foreach (['pdf', 'xlsx', 'csv'] as $ext) {
            @unlink(self::arquivo($c, $ext));
        }
        self::pdo()->prepare('DELETE FROM cadernos WHERE id = ? AND empresa_id = ?')->execute([$id, $eid]);
        return true;
    }

    /** Nome do arquivo para quem baixa: inventario-pato-branco-2026-v3.pdf */
    public static function nomeParaBaixar(array $c, string $destino, string $ext): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $destino) ?: 'destino')), '-');
        return 'inventario-' . $slug . '-' . (int) $c['ano'] . '-v' . (int) $c['versao'] . ($c['previa'] ? '-previa' : '') . '.' . $ext;
    }

    /** Endereço do portal do destino. */
    public static function portal(array $empresa): string
    {
        $cfg = require __DIR__ . '/config.php';
        return 'https://' . ($empresa['dominio_proprio'] ?: $empresa['slug'] . '.' . ($cfg['app']['dominio_portal'] ?? 'inventour.com.br'));
    }

    // ------------------------------------------------------------ geração

    /**
     * Gera a versão pedida. $pastaSaida troca o destino dos arquivos (teste
     * pela linha de comando); sem ela, grava na pasta do destino.
     */
    public static function gerar(int $id, ?string $pastaSaida = null): array
    {
        $pdo = self::pdo();
        $q = $pdo->prepare('SELECT * FROM cadernos WHERE id = ?');
        $q->execute([$id]);
        $c = $q->fetch();
        if (!$c) {
            throw new RuntimeException('Caderno ' . $id . ' não existe.');
        }
        $eid = (int) $c['empresa_id'];
        $marcar = function (string $situacao, ?string $progresso = null, array $mais = []) use ($pdo, $id): void {
            $campos = ['situacao' => $situacao, 'progresso' => $progresso] + $mais;
            $pdo->prepare('UPDATE cadernos SET ' . implode(' = ?, ', array_keys($campos)) . ' = ? WHERE id = ?')
                ->execute([...array_values($campos), $id]);
        };
        $tmp = ($pastaSaida ?? sys_get_temp_dir()) . '/caderno-' . $id . '-' . bin2hex(random_bytes(3));
        @mkdir($tmp, 0770, true);
        try {
            $marcar('gerando', 'Lendo as fichas');
            $d = self::dados($c);
            // O emblema em PNG de alta resolução: o wkhtmltopdf rasteriza o SVG
            // em baixa resolução, e o logo saía quadriculado na capa.
            $emblema = $tmp . '/emblema.png';
            exec('rsvg-convert -w 1024 -o ' . escapeshellarg($emblema) . ' ' . escapeshellarg($d['raiz'] . '/public/assets/marca/emblema.svg') . ' 2>/dev/null');
            $d['emblema'] = is_file($emblema) && filesize($emblema) > 0 ? $emblema : null;
            if ($d['foto_capa'] && $d['config']['desfocar_capa']) {
                $d['foto_capa'] = self::desfocar($d['foto_capa'], $tmp . '/capa-desfocada.jpg') ?? $d['foto_capa'];
            }
            $html = new CadernoHtml($c, $d);

            $marcar('gerando', 'Montando as páginas');
            $rodape = 'Código de verificação ' . self::codigoLegivel((string) $c['codigo']) . ' · ' . preg_replace('#^https://#', '', $d['portal']) . '/verificar';
            $cabecalho = $c['previa'] ? 'VERSÃO PRELIMINAR: inclui fichas ainda não homologadas' : ($d['empresa']['modo_operacao'] === 'projeto' ? 'Documento estruturado via InvenTour' : '');
            $pdf = function (string $nome, string $corpo, ?int $deslocamento, ?array &$titulos = null) use ($tmp, $html, $rodape, $cabecalho): int {
                $arquivoHtml = $tmp . '/' . $nome . '.html';
                file_put_contents($arquivoHtml, $html->documento($corpo));
                // A capa (sem numeração) sangra até a borda do papel; o resto tem margem.
                $margens = $deslocamento === null ? ['-T', '0', '-B', '0', '-L', '0', '-R', '0'] : ['-T', '18mm', '-B', '18mm', '-L', '16mm', '-R', '16mm'];
                $args = ['wkhtmltopdf', '-q', '--encoding', 'utf-8', '--enable-local-file-access', '-s', 'A4', ...$margens, '--outline-depth', '2',
                         // Sem o encolhimento automático, milímetro no CSS é milímetro no papel.
                         '--disable-smart-shrinking', '--dpi', '96'];
                if ($deslocamento !== null) {
                    array_push($args, '--page-offset', (string) $deslocamento,
                        '--footer-font-name', 'Liberation Sans', '--footer-font-size', '7', '--footer-spacing', '6',
                        '--footer-left', $rodape, '--footer-right', '[page]');
                    if ($cabecalho !== '') {
                        array_push($args, '--header-font-name', 'Liberation Sans', '--header-font-size', '7', '--header-spacing', '6',
                            '--header-center', $cabecalho);
                    }
                }
                array_push($args, '--dump-outline', $tmp . '/' . $nome . '.xml', $arquivoHtml, $tmp . '/' . $nome . '.pdf');
                self::rodar($args);
                if (func_num_args() > 3) {
                    $titulos = self::titulos($tmp . '/' . $nome . '.xml');
                }
                return self::paginas($tmp . '/' . $nome . '.pdf');
            };

            // Capa e ficha técnica não dependem da numeração.
            $pCapa = $pdf('01-capa', $html->capa(), null);
            $pFicha = $pdf('02-ficha', $html->fichaTecnica(), $pCapa);

            // Os blocos de conteúdo, na ordem do caderno.
            $blocos = [['03-destino', fn () => $html->destino()]];
            foreach ($d['categorias'] as $cat) {
                $blocos[] = ['04-' . $cat, fn () => $html->categoria($cat)];
            }
            if ($d['experiencias']) {
                $blocos[] = ['05-anexos', fn () => $html->anexos()];
            }
            $corpos = [];
            foreach ($blocos as [$nome, $fazer]) {
                $marcar('gerando', 'Montando ' . $nome);
                $corpos[$nome] = $fazer();
            }

            // O sumário ocupa S páginas; o conteúdo começa depois dele. Se a
            // conta do S errar, refaz o conteúdo com o S certo.
            $s = $html->paginasDoSumario($d);
            for ($tentativa = 0; $tentativa < 3; $tentativa++) {
                $pagina = $pCapa + $pFicha + $s;
                $entradas = [];
                $paginasConteudo = [];
                foreach ($corpos as $nome => $corpo) {
                    $marcar('gerando', 'Gerando o PDF (' . $nome . ')');
                    $titulos = [];
                    $n = $pdf($nome, $corpo, $pagina, $titulos);
                    array_push($entradas, ...$titulos);
                    $paginasConteudo[$nome] = $n;
                    $pagina += $n;
                }
                $sReal = $pdf('02b-sumario', $html->sumario($entradas), $pCapa + $pFicha);
                if ($sReal === $s) {
                    break;
                }
                $s = $sReal;
            }

            $marcar('gerando', 'Unindo o PDF');
            $partes = [$tmp . '/01-capa.pdf', $tmp . '/02-ficha.pdf', $tmp . '/02b-sumario.pdf'];
            foreach (array_keys($corpos) as $nome) {
                $partes[] = $tmp . '/' . $nome . '.pdf';
            }
            $destinoPdf = $pastaSaida ? $pastaSaida . '/caderno-' . $id . '.pdf' : self::arquivo($c, 'pdf');
            // União pelo qpdf, já linearizada: o pdfunite deixava o índice do
            // PDF inconsistente, e o leitor do navegador refazia o arquivo
            // inteiro antes de mostrar a primeira página (uns 10 segundos).
            self::rodar(['qpdf', '--empty', '--linearize', '--object-streams=generate', '--pages', ...$partes, '--', $destinoPdf]);

            $marcar('gerando', 'Planilhas');
            $destinoXlsx = $pastaSaida ? $pastaSaida . '/caderno-' . $id . '.xlsx' : self::arquivo($c, 'xlsx');
            $destinoCsv = $pastaSaida ? $pastaSaida . '/caderno-' . $id . '.csv' : self::arquivo($c, 'csv');
            self::planilhas($d, $destinoXlsx, $destinoCsv);

            $resultado = ['paginas' => self::paginas($destinoPdf), 'itens' => count($d['itens']), 'bytes' => filesize($destinoPdf),
                          'sha256' => hash_file('sha256', $destinoPdf)];
            $marcar('pronto', null, $resultado + ['gerado_em' => date('Y-m-d H:i:s'), 'erro' => null]);
            return $resultado + ['pdf' => $destinoPdf];
        } catch (Throwable $e) {
            $marcar('falhou', null, ['erro' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        } finally {
            foreach (glob($tmp . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmp);
        }
    }

    /**
     * A foto desfocada para o fundo da capa: reduz, amplia de volta e passa
     * o desfoque, que é o que o wkhtmltopdf não faz (não tem filter no CSS).
     */
    private static function desfocar(string $origem, string $destino): ?string
    {
        $img = @imagecreatefromjpeg($origem);
        if (!$img) {
            return null;
        }
        [$l, $a] = [imagesx($img), imagesy($img)];
        // Em degraus: cada redução e ampliação suave, com desfoque no meio, não deixa o quadriculado.
        $atual = $img;
        // Desfoque leve (Luiz, 21/11/2026): dá para reconhecer a cidade, e o título continua em destaque.
        foreach ([420] as $largura) {
            $prox = imagecreatetruecolor($largura, max(1, (int) round($largura * $a / max(1, $l))));
            imagecopyresampled($prox, $atual, 0, 0, 0, 0, imagesx($prox), imagesy($prox), imagesx($atual), imagesy($atual));
            for ($i = 0; $i < 2; $i++) {
                imagefilter($prox, IMG_FILTER_GAUSSIAN_BLUR);
            }
            $atual = $prox;
        }
        foreach ([1200] as $largura) {
            $prox = imagecreatetruecolor($largura, max(1, (int) round($largura * $a / max(1, $l))));
            imagecopyresampled($prox, $atual, 0, 0, 0, 0, imagesx($prox), imagesy($prox), imagesx($atual), imagesy($atual));
            for ($i = 0; $i < 2; $i++) {
                imagefilter($prox, IMG_FILTER_GAUSSIAN_BLUR);
            }
            $atual = $prox;
        }
        $saida = $atual;
        return imagejpeg($saida, $destino, 86) ? $destino : null;
    }

    private static function rodar(array $args): void
    {
        $cmd = implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
        exec($cmd, $saida, $codigo);
        // O wkhtmltopdf devolve 1 quando um recurso (uma foto) falha, mas o PDF sai.
        $saiu = end($args);
        if ($codigo !== 0 && !(is_file((string) $saiu) && filesize((string) $saiu) > 0)) {
            throw new RuntimeException($args[0] . ' falhou: ' . mb_substr(implode(' ', $saida), 0, 300));
        }
    }

    private static function paginas(string $pdf): int
    {
        exec('pdfinfo ' . escapeshellarg($pdf) . ' 2>/dev/null', $saida);
        foreach ($saida as $l) {
            if (preg_match('/^Pages:\s+(\d+)/', $l, $m)) {
                return (int) $m[1];
            }
        }
        throw new RuntimeException('Não consegui contar as páginas de ' . basename($pdf));
    }

    /** Os títulos de nível 1 e 2 do bloco, com a página: [['nivel', 'titulo', 'pagina']]. */
    private static function titulos(string $xml): array
    {
        $doc = @simplexml_load_file($xml);
        if (!$doc) {
            return [];
        }
        $doc->registerXPathNamespace('o', 'http://wkhtmltopdf.org/outline');
        $saida = [];
        $andar = function ($no, int $nivel) use (&$andar, &$saida): void {
            foreach ($no->children('http://wkhtmltopdf.org/outline') as $item) {
                // Depois de children(ns), o atributo sem namespace só vem por attributes().
                $at = $item->attributes();
                $titulo = trim((string) $at['title']);
                if ($nivel >= 1 && $titulo !== '') {
                    $saida[] = ['nivel' => $nivel, 'titulo' => $titulo, 'pagina' => (int) $at['page']];
                }
                if ($nivel < 2) {
                    $andar($item, $nivel + 1);
                }
            }
        };
        $andar($doc, 0);
        return $saida;
    }

    // ------------------------------------------------------------ dados

    /** Tudo o que o caderno imprime, lido de uma vez, só do destino do caderno. */
    public static function dados(array $c): array
    {
        $pdo = self::pdo();
        $eid = (int) $c['empresa_id'];
        $q = $pdo->prepare('SELECT e.*, m.nome AS municipio_nome, m.populacao, es.uf AS municipio_uf
                            FROM empresas e LEFT JOIN municipios m ON m.id = e.municipio_id LEFT JOIN estados es ON es.id = m.estado_id
                            WHERE e.id = ?');
        $q->execute([$eid]);
        $empresa = $q->fetch() ?: throw new RuntimeException('Destino não encontrado.');
        $q = $pdo->prepare('SELECT * FROM destino_governanca WHERE empresa_id = ?');
        $q->execute([$eid]);
        $governanca = $q->fetch() ?: [];

        $recorte = json_decode((string) $c['recorte'], true) ?: [];
        $categorias = $recorte['categorias'] ?? [] ?: array_keys(self::CATEGORIAS);
        $soFichas = array_map('intval', $recorte['fichas'] ?? []);
        if ($soFichas) {
            // Ficha pedida sozinha leva a categoria dela, mesmo sem a categoria marcada.
            $marcas = implode(',', array_fill(0, count($soFichas), '?'));
            $q = $pdo->prepare("SELECT DISTINCT categoria FROM inventario_fichas WHERE id IN ({$marcas})");
            $q->execute($soFichas);
            $categorias = array_values(array_unique(array_merge($recorte['categorias'] ?? [], $q->fetchAll(PDO::FETCH_COLUMN))));
            sort($categorias);
        }

        $fichas = [];
        foreach ($pdo->query('SELECT * FROM inventario_fichas WHERE ativo = 1 ORDER BY ordem') as $f) {
            if (!in_array($f['categoria'], $categorias, true)) {
                continue;
            }
            if ($soFichas && !in_array((int) $f['id'], $soFichas, true) && !in_array($f['categoria'], $recorte['categorias'] ?? [], true)) {
                continue;
            }
            $f['atributos'] = Inventario::atributos((int) $f['id']);
            $fichas[(int) $f['id']] = $f;
        }

        $situacoes = $c['previa'] ? "i.situacao <> 'arquivada'" : "i.situacao IN ('homologada', 'desatualizada')";
        $itens = [];
        if ($fichas) {
            $marcas = implode(',', array_keys($fichas));
            $q = $pdo->prepare("SELECT i.*, t.nome AS tipo_nome, p.nome AS tipo_pai
                                FROM inventario_itens i LEFT JOIN inventario_tipos t ON t.id = i.tipo_id LEFT JOIN inventario_tipos p ON p.id = t.pai_id
                                WHERE i.empresa_id = ? AND {$situacoes} AND i.ficha_id IN ({$marcas})
                                ORDER BY i.nome");
            $q->execute([$eid]);
            foreach ($q->fetchAll() as $i) {
                $itens[(int) $i['id']] = $i + ['valores' => [], 'acess' => [], 'acess_outro' => [], 'foto' => null];
            }
        }
        // Valores, acessibilidade e a foto de capa, em lotes (nada de uma consulta por item).
        foreach (array_chunk(array_keys($itens), 500) as $lote) {
            $marcas = implode(',', $lote);
            foreach ($pdo->query("SELECT * FROM inventario_item_atributos WHERE empresa_id = {$eid} AND item_id IN ({$marcas})") as $v) {
                $itens[(int) $v['item_id']]['valores'][(int) $v['atributo_id']] = $v;
            }
            foreach ($pdo->query("SELECT item_id, chave, opcoes, outro FROM inventario_item_acessibilidade WHERE empresa_id = {$eid} AND item_id IN ({$marcas})") as $a) {
                $itens[(int) $a['item_id']]['acess'][$a['chave']] = json_decode((string) $a['opcoes'], true) ?: [];
                if ($a['outro'] !== null) {
                    $itens[(int) $a['item_id']]['acess_outro'][$a['chave']] = $a['outro'];
                }
            }
            foreach ($pdo->query("SELECT item_id, arquivo, legenda, credito FROM inventario_midias WHERE empresa_id = {$eid} AND experiencia_id IS NULL
                                  AND item_id IN ({$marcas}) ORDER BY capa DESC, ordem, id") as $m) {
                $itens[(int) $m['item_id']]['foto'] ??= $m;
            }
        }

        $q = $pdo->prepare("SELECT * FROM destino_narrativas WHERE empresa_id = ? AND conteudo_html <> '' ORDER BY ordem");
        $q->execute([$eid]);
        $narrativas = $q->fetchAll();
        $q = $pdo->prepare('SELECT * FROM destino_linha_tempo WHERE empresa_id = ? ORDER BY data_marco, id');
        $q->execute([$eid]);
        $marcos = $q->fetchAll();
        $q = $pdo->prepare("SELECT x.*, i.nome AS item_nome FROM inventario_experiencias x JOIN inventario_itens i ON i.id = x.item_id
                            WHERE x.empresa_id = ? AND x.situacao " . ($c['previa'] ? "<> 'reprovada'" : "= 'aprovada'") . ' ORDER BY i.nome, x.titulo');
        $q->execute([$eid]);
        $experiencias = $q->fetchAll();

        $cfgApp = require __DIR__ . '/config.php';
        return [
            'empresa'      => $empresa,
            'governanca'   => $governanca,
            'config'       => self::config($eid),
            'portal'       => self::portal($empresa),
            'categorias'   => $categorias,
            'fichas'       => $fichas,
            'itens'        => $itens,
            'narrativas'   => $narrativas,
            'marcos'       => $marcos,
            'experiencias' => $experiencias,
            'pasta_fotos'  => rtrim((string) $cfgApp['caminhos']['midias'], '/') . '/inventario/' . $eid,
            'foto_capa'    => self::fotoDaCapa($eid),
            'raiz'         => dirname(__DIR__),
        ];
    }

    // ------------------------------------------------------------ planilhas

    /** As colunas de um item nas planilhas (XLSX e CSV). */
    private static function linhaDoItem(array $i, array $f): array
    {
        return [$f['codigo'], $f['nome'], (string) ($i['tipo_pai'] ? $i['tipo_pai'] . ' / ' . $i['tipo_nome'] : $i['tipo_nome']), $i['nome'], $i['nome_popular'],
                Inventario::SITUACOES[$i['situacao']][0] ?? $i['situacao'], $i['cnpj'], $i['cadastur'],
                trim(implode(', ', array_filter([$i['logradouro'], $i['numero'], $i['complemento']]))), $i['bairro'], $i['cep'],
                $i['telefone'], $i['whatsapp'], $i['email'], $i['site'], $i['plus_code'],
                $i['latitude'] !== null ? (float) $i['latitude'] : null, $i['longitude'] !== null ? (float) $i['longitude'] : null,
                $i['capacidade_uh'] !== null ? (int) $i['capacidade_uh'] : null, $i['capacidade_leitos'] !== null ? (int) $i['capacidade_leitos'] : null,
                $i['capacidade_assentos'] !== null ? (int) $i['capacidade_assentos'] : null, $i['capacidade_publico'] !== null ? (int) $i['capacidade_publico'] : null,
                Inventario::CONSERVACAO[$i['conservacao']] ?? $i['conservacao']];
    }

    private const COLUNAS = ['Código da ficha', 'Ficha', 'Tipo', 'Nome', 'Nome popular', 'Situação', 'CNPJ', 'Cadastur', 'Endereço', 'Bairro', 'CEP',
                             'Telefone', 'WhatsApp', 'E-mail', 'Site', 'Plus code', 'Latitude', 'Longitude', 'UH', 'Leitos', 'Assentos', 'Público',
                             'Conservação'];

    private static function planilhas(array $d, string $xlsx, string $csv): void
    {
        $porCategoria = ['A' => [], 'B' => [], 'C' => []];
        foreach ($d['itens'] as $i) {
            $f = $d['fichas'][(int) $i['ficha_id']] ?? null;
            if ($f) {
                $porCategoria[$f['categoria']][] = self::linhaDoItem($i, $f);
            }
        }
        $e = $d['empresa'];
        $resumo = [['Indicador', 'Valor'],
                   ['Destino', $e['nome_fantasia']], ['Município', trim(($e['municipio_nome'] ?? '') . '/' . ($e['municipio_uf'] ?? ''), '/')],
                   ['População (IBGE)', $e['populacao'] !== null ? (int) $e['populacao'] : null],
                   ['Itens no caderno', count($d['itens'])]];
        foreach (self::CATEGORIAS as $k => $nome) {
            if (in_array($k, $d['categorias'], true)) {
                $resumo[] = [$nome, count($porCategoria[$k])];
            }
        }
        $resumo[] = ['Unidades habitacionais (B1)', array_sum(array_map(fn ($i) => (int) $i['capacidade_uh'], array_filter($d['itens'], fn ($i) => (int) ($d['fichas'][(int) $i['ficha_id']]['ordem'] ?? 0) === 20)))];
        $resumo[] = ['Leitos (B1)', array_sum(array_map(fn ($i) => (int) $i['capacidade_leitos'], array_filter($d['itens'], fn ($i) => (int) ($d['fichas'][(int) $i['ficha_id']]['ordem'] ?? 0) === 20)))];
        $resumo[] = ['Experiências', count($d['experiencias'])];
        $resumo[] = [];
        $resumo[] = ['Ficha', 'Itens'];
        $conta = array_count_values(array_map(fn ($i) => (int) $i['ficha_id'], $d['itens']));
        foreach ($d['fichas'] as $fid => $f) {
            $resumo[] = [$f['codigo'] . ' ' . $f['nome'], $conta[$fid] ?? 0];
        }

        $x = new Xlsx();
        $x->aba('Resumo territorial', $resumo);
        foreach (['A' => 'A Infraestrutura de apoio', 'B' => 'B Serviços e equipamentos', 'C' => 'C Atrativos'] as $k => $nome) {
            if (in_array($k, $d['categorias'], true)) {
                $x->aba($nome, [self::COLUNAS, ...$porCategoria[$k]]);
            }
        }
        $linhasX = [['Experiência', 'Item', 'Situação', 'Dimensões (4 Es)', 'Duração (min)', 'Grupo mínimo', 'Grupo máximo', 'Faixa de preço', 'Valor de referência']];
        foreach ($d['experiencias'] as $xp) {
            $linhasX[] = [$xp['titulo'], $xp['item_nome'], $xp['situacao'], implode(', ', array_keys(json_decode((string) $xp['dimensoes'], true) ?: [])),
                          $xp['duracao_minutos'] !== null ? (int) $xp['duracao_minutos'] : null, (int) $xp['pessoas_min'],
                          $xp['pessoas_max'] !== null ? (int) $xp['pessoas_max'] : null, $xp['faixa_valor'], $xp['valor_texto']];
        }
        $x->aba('Experiências', $linhasX);
        $x->salvar($xlsx);

        // CSV com ponto e vírgula e BOM: abre direto no Excel em português.
        $h = fopen($csv, 'w');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, self::COLUNAS, ';', '"', '');
        foreach (['A', 'B', 'C'] as $k) {
            foreach ($porCategoria[$k] as $linha) {
                fputcsv($h, array_map(fn ($v) => is_float($v) ? str_replace('.', ',', (string) $v) : $v, $linha), ';', '"', '');
            }
        }
        fclose($h);
    }
}
