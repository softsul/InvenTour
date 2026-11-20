<?php
/**
 * Cadastur: cópia local dos dados abertos do Ministério do Turismo.
 * Softsul Software
 *
 * O Ministério publica a cada trimestre, em dados.turismo.gov.br (portal
 * CKAN), uma planilha XLSX por atividade com a foto completa do cadastro.
 * Esta classe acha o arquivo mais recente de cada atividade, baixa, lê em
 * fluxo (LeitorXlsx) e grava em cadastur_prestadores só as colunas
 * permitidas (migrações 008 e 060: endereço e contatos do prestador entram;
 * CPF e nome do responsável, e-mail do administrador, nascimento, RG, sexo
 * e tipo sanguíneo ficam de fora). A ficha do organizador consulta a cópia
 * local, sem depender do portal na hora do cadastro.
 *
 * O portal às vezes responde vazio; toda chamada a ele é repetida algumas
 * vezes antes de desistir, e uma atividade que falha não impede as outras.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/LeitorXlsx.php';
require_once __DIR__ . '/Lista.php';

final class Cadastur
{
    private const API = 'https://dados.turismo.gov.br/api/3/action/';
    private const AGENTE = 'InvenTour/1.0 (Softsul Software; importacao de dados abertos do Cadastur)';
    private const TAMANHO_MAXIMO = 120 * 1024 * 1024;
    private const LOTE = 500;

    /** Colunas da planilha que juntas descrevem o que o prestador faz. */
    private const COLUNAS_DE_CATEGORIA = [
        'categoria evento (atuacao principal)', 'tipo de eventos', 'tipo', 'especialidade',
        'tipo de hospedagem', 'categoria(s)', 'segmento(s)', 'tipo de servico', 'segmentos',
    ];

    /** Os conjuntos do Cadastur no portal (um por atividade). @return list<string> */
    public static function pacotes(): array
    {
        $r = self::api('package_search?q=cadastur&rows=100');
        return array_values(array_map(static fn (array $p): string => (string) $p['name'], $r['results'] ?? []));
    }

    /**
     * O arquivo XLSX mais recente de um conjunto, ou null se não houver.
     *
     * @return array{id:string,nome:string,url:string,publicado_em:?string}|null
     */
    public static function recursoMaisRecente(string $pacote): ?array
    {
        $p = self::api('package_show?id=' . rawurlencode($pacote));
        $recursos = array_filter($p['resources'] ?? [], static fn (array $r): bool => strtoupper((string) ($r['format'] ?? '')) === 'XLSX');
        usort($recursos, static fn (array $a, array $b): int
            => strcmp((string) ($b['last_modified'] ?? $b['created'] ?? ''), (string) ($a['last_modified'] ?? $a['created'] ?? '')));
        $r = $recursos[0] ?? null;
        if (!$r) {
            return null;
        }
        $quando = (string) ($r['last_modified'] ?? $r['created'] ?? '');
        return ['id' => (string) $r['id'], 'nome' => (string) ($r['name'] ?? ''), 'url' => (string) $r['url'],
                'publicado_em' => $quando !== '' ? date('Y-m-d H:i:s', (int) strtotime($quando)) : null];
    }

    /**
     * Importa todas as atividades. Devolve uma linha de resumo por conjunto.
     *
     * @return list<string>
     */
    public static function importarTudo(bool $forcar = false): array
    {
        $resumo = [];
        foreach (self::pacotes() as $pacote) {
            try {
                $resumo[] = $pacote . ': ' . self::importar($pacote, $forcar);
            } catch (Throwable $e) {
                $resumo[] = $pacote . ': FALHOU, ' . $e->getMessage();
            }
        }
        return $resumo;
    }

    /** Importa um conjunto, se o arquivo mais recente ainda não entrou. */
    public static function importar(string $pacote, bool $forcar = false): string
    {
        $recurso = self::recursoMaisRecente($pacote);
        if (!$recurso) {
            return 'sem planilha XLSX no portal';
        }
        $pdo = Database::conexao();
        $ja = $pdo->prepare('SELECT id FROM cadastur_importacoes WHERE recurso_id = ? AND status = "concluida" LIMIT 1');
        $ja->execute([$recurso['id']]);
        if ($ja->fetchColumn() && !$forcar) {
            return 'já importado (' . $recurso['nome'] . ')';
        }

        $pdo->prepare('INSERT INTO cadastur_importacoes (pacote, recurso_id, recurso_nome, publicado_em, iniciada_em)
                       VALUES (?, ?, ?, ?, NOW())')
            ->execute([$pacote, $recurso['id'], mb_substr($recurso['nome'], 0, 200), $recurso['publicado_em']]);
        $importacao = (int) $pdo->lastInsertId();

        $cfg = require __DIR__ . '/config.php';
        $arquivo = $cfg['caminhos']['tmp'] . '/cadastur-' . $importacao . '.xlsx';
        try {
            self::baixar($recurso['url'], $arquivo);
            [$linhas, $atividades] = self::gravar($arquivo, $importacao);
            // A planilha é a foto completa: o que não veio nela saiu do
            // cadastro e sai daqui também.
            if ($atividades) {
                $ph = implode(',', array_fill(0, count($atividades), '?'));
                $pdo->prepare("DELETE FROM cadastur_prestadores WHERE atividade IN ({$ph}) AND importacao_id <> ?")
                    ->execute([...$atividades, $importacao]);
            }
            $pdo->prepare('UPDATE cadastur_importacoes SET status = "concluida", linhas = ?, concluida_em = NOW() WHERE id = ?')
                ->execute([$linhas, $importacao]);
            return $linhas . ' prestadores (' . $recurso['nome'] . ')';
        } catch (Throwable $e) {
            $pdo->prepare('UPDATE cadastur_importacoes SET status = "falhou", erro = ?, concluida_em = NOW() WHERE id = ?')
                ->execute([mb_substr($e->getMessage(), 0, 500), $importacao]);
            throw $e;
        } finally {
            @unlink($arquivo);
        }
    }

    /**
     * Os registros de um documento (CNPJ ou CPF), um por atividade.
     *
     * @return list<array<string,mixed>>
     */
    public static function consultar(string $documento): array
    {
        $doc = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $documento));
        if ($doc === '') {
            return [];
        }
        $s = Database::leitura()->prepare(
            'SELECT documento, tipo_documento, atividade, nome_juridico, nome_fantasia, situacao_cadastral,
                    situacao_atividade, uf, municipio, certificado, validade, categorias
             FROM cadastur_prestadores WHERE documento = ? ORDER BY atividade'
        );
        $s->execute([$doc]);
        return $s->fetchAll();
    }

    /** Regular e com certificado dentro da validade. */
    public static function vigente(array $registro): bool
    {
        return mb_strtolower((string) $registro['situacao_cadastral']) === 'regular'
            && ($registro['validade'] === null || (string) $registro['validade'] >= date('Y-m-d'));
    }

    /**
     * A situação que vale hoje. A planilha é a foto do trimestre e continua
     * dizendo "Regular" depois que o certificado vence: validade passada é
     * "Vencido", seja qual for a situação que veio nela.
     */
    public static function situacao(array $registro): string
    {
        if ($registro['validade'] !== null && (string) $registro['validade'] < date('Y-m-d')) {
            return 'Vencido';
        }
        return (string) ($registro['situacao_cadastral'] ?? '');
    }

    /**
     * Prestadores, leitos e unidades do país inteiro. Contar as 153 mil
     * linhas leva meio segundo e o número só muda quando uma importação
     * termina: fica guardado num arquivo, com a data da última importação
     * concluída como chave.
     *
     * @return array{0:int,1:int,2:int}
     */
    public static function totais(): array
    {
        $pdo = Database::conexao();
        $chave = (string) $pdo->query('SELECT MAX(concluida_em) FROM cadastur_importacoes WHERE status = "concluida"')->fetchColumn();
        $cfg = require __DIR__ . '/config.php';
        $arquivo = $cfg['caminhos']['tmp'] . '/cadastur-totais.json';
        $guardado = is_file($arquivo) ? json_decode((string) @file_get_contents($arquivo), true) : null;
        if (is_array($guardado) && ($guardado['chave'] ?? null) === $chave && isset($guardado['totais'])) {
            return array_map('intval', $guardado['totais']);
        }
        $totais = array_map('intval', $pdo->query('SELECT COUNT(*), COALESCE(SUM(leitos), 0), COALESCE(SUM(unidades), 0) FROM cadastur_prestadores')->fetch(PDO::FETCH_NUM));
        @file_put_contents($arquivo, json_encode(['chave' => $chave, 'totais' => $totais]), LOCK_EX);
        return $totais;
    }

    /** As palavras que o índice de texto do MySQL ignora (lista padrão do InnoDB). */
    private const PALAVRAS_VAZIAS = ['about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this', 'was', 'what', 'when',
        'where', 'who', 'will', 'with', 'und', 'www'];

    /** A mesma regra de situacao(), para filtrar e ordenar no banco. */
    public const SITUACAO_SQL = "(CASE WHEN c.validade < CURDATE() THEN 'Vencido' ELSE c.situacao_cadastral END)";

    /** Situações do filtro: chave => [rótulo, condição sobre SITUACAO_SQL]. */
    public const SITUACOES = [
        'regular'     => ['Regular', self::SITUACAO_SQL . " = 'Regular'"],
        'implantacao' => ['Em implantação', self::SITUACAO_SQL . " = 'Em Implantação'"],
        'vencido'     => ['Vencido', self::SITUACAO_SQL . " = 'Vencido'"],
        'sem'         => ['Sem situação', self::SITUACAO_SQL . ' IS NULL'],
    ];

    /**
     * O recorte dos filtros da tela Cadastur, sobre o alias c. A lista e o
     * mapa passam por aqui, para contarem exatamente a mesma coisa.
     *
     * Município manda sozinho no lugar; sem ele, região turística (do Mapa
     * do Turismo mais recente, em municipios.regiao_id) e UF se somam.
     *
     * @param array{q?:string,municipio?:list<int>|int,regiao?:list<int>,uf?:list<string>,atividade?:list<string>,situacao?:list<string>} $f
     * @return array{0:list<string>,1:list<mixed>} condições e argumentos
     */
    public static function recorte(array $f): array
    {
        $onde = [];
        $args = [];
        $lista = static fn (array $v): string => implode(',', array_fill(0, count($v), '?'));
        if (!empty($f['municipio'])) {
            $mun = array_map('intval', (array) $f['municipio']);
            $onde[] = 'c.municipio_id IN (' . $lista($mun) . ')';
            array_push($args, ...$mun);
        } else {
            if (!empty($f['regiao'])) {
                $reg = array_map('intval', (array) $f['regiao']);
                $onde[] = 'c.municipio_id IN (SELECT id FROM municipios WHERE regiao_id IN (' . $lista($reg) . '))';
                array_push($args, ...$reg);
            }
            if (!empty($f['uf'])) {
                $onde[] = 'c.uf IN (' . $lista($f['uf']) . ')';
                array_push($args, ...$f['uf']);
            }
        }
        if (!empty($f['atividade'])) {
            $onde[] = 'c.atividade IN (' . $lista($f['atividade']) . ')';
            array_push($args, ...$f['atividade']);
        }
        if (!empty($f['situacao'])) {
            $onde[] = '(' . implode(' OR ', array_map(fn ($k) => self::SITUACOES[$k][1], $f['situacao'])) . ')';
        }
        $busca = trim((string) ($f['q'] ?? ''));
        if ($busca !== '') {
            // Cada palavra em nome fantasia, razão social, atividade,
            // categoria ("restaurante" acha os restaurantes) ou município
            // ("foz do iguaçu" acha todos de lá), pelo índice de texto
            // (ft_cadastur_busca): palavra por prefixo, todas obrigatórias.
            // Palavra curta ("do") e as de ligação não entram no índice e
            // ficam de fora da conta. CNPJ, CPF e certificado vão por
            // prefixo.
            $palavras = [];
            // Só número (CNPJ, CPF, certificado digitados com pontuação):
            // nada de índice de texto, que obrigaria a ler a tabela toda.
            $soNumero = !preg_match('/\p{L}/u', $busca);
            foreach ($soNumero ? [] : (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($busca)) ?: []) as $p) {
                if (mb_strlen($p) >= 3 && !in_array($p, self::PALAVRAS_VAZIAS, true)) {
                    $palavras[] = '+' . $p . '*';
                }
            }
            $doc = preg_replace('/[^0-9A-Z]/', '', strtoupper($busca));
            $partes = [];
            if ($palavras) {
                $partes[] = 'MATCH(c.nome_fantasia, c.nome_juridico, c.atividade, c.categorias, c.municipio) AGAINST (? IN BOOLEAN MODE)';
                $args[] = implode(' ', array_slice($palavras, 0, 8));
            }
            if (strlen((string) $doc) >= 5 && ctype_alnum((string) $doc) && preg_match('/\d/', (string) $doc)) {
                $partes[] = 'c.documento LIKE ?';
                $partes[] = 'c.certificado LIKE ?';
                array_push($args, $doc . '%', $doc . '%');
            }
            if (!$partes) {
                // Só palavras curtas ("bb"): o índice não ajuda, vai pelo nome.
                $partes[] = 'c.nome_exibicao LIKE ?';
                $args[] = '%' . addcslashes($busca, '%_\\') . '%';
            }
            $onde[] = '(' . implode(' OR ', $partes) . ')';
        }
        return [$onde, $args];
    }

    /** Leitos cadastrados no Cadastur num município (painel de conflitos). */
    public static function leitosDoMunicipio(int $municipioId): int
    {
        $s = Database::leitura()->prepare(
            'SELECT COALESCE(SUM(leitos), 0) FROM cadastur_prestadores
             WHERE municipio_id = ? AND atividade = "Meio de Hospedagem" AND situacao_cadastral = "Regular"'
        );
        $s->execute([$municipioId]);
        return (int) $s->fetchColumn();
    }

    /** De quando é a base local: o nome do trimestre e a data, ou null. */
    public static function base(): ?array
    {
        $r = Database::leitura()->query(
            'SELECT recurso_nome, publicado_em, concluida_em FROM cadastur_importacoes
             WHERE status = "concluida" ORDER BY publicado_em DESC, id DESC LIMIT 1'
        )->fetch();
        return $r ?: null;
    }

    // ------------------------------------------------------------- interno

    /** @return array<string,mixed> */
    private static function api(string $caminho): array
    {
        $erro = '';
        for ($tentativa = 1; $tentativa <= 4; $tentativa++) {
            $corpo = self::pedir(self::API . $caminho, 30);
            $json = json_decode((string) $corpo, true);
            if (is_array($json) && ($json['success'] ?? false)) {
                return (array) $json['result'];
            }
            $erro = $corpo === null ? 'sem resposta' : 'resposta que não é JSON';
            sleep(3 * $tentativa);
        }
        throw new RuntimeException('portal de dados abertos do Turismo: ' . $erro);
    }

    private static function pedir(string $url, int $tempo): ?string
    {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $tempo, CURLOPT_USERAGENT => self::AGENTE, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        $r = curl_exec($c);
        $codigo = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        curl_close($c);
        return is_string($r) && $codigo === 200 ? $r : null;
    }

    private static function baixar(string $url, string $destino): void
    {
        // Só do próprio portal do Ministério: o endereço vem da resposta da
        // API, e não deve levar o servidor a baixar de qualquer lugar.
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'dados.turismo.gov.br') {
            throw new RuntimeException('endereço de arquivo fora do portal do Turismo: ' . $url);
        }
        @mkdir(dirname($destino), 02770, true);
        for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
            $f = fopen($destino, 'wb');
            $c = curl_init($url);
            curl_setopt_array($c, [CURLOPT_FILE => $f, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 600, CURLOPT_USERAGENT => self::AGENTE, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => static fn ($c, $total, $baixado): int => $baixado > self::TAMANHO_MAXIMO ? 1 : 0]);
            $ok = curl_exec($c);
            $codigo = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
            curl_close($c);
            fclose($f);
            if ($ok && $codigo === 200 && filesize($destino) > 1000) {
                return;
            }
            sleep(5 * $tentativa);
        }
        throw new RuntimeException('não consegui baixar a planilha');
    }

    /**
     * Lê a planilha e grava em lotes. Devolve [linhas gravadas, atividades].
     *
     * @return array{0:int,1:list<string>}
     */
    private static function gravar(string $arquivo, int $importacao): array
    {
        $pdo = Database::conexao();
        $municipios = self::mapaDeMunicipios();
        $cabecalho = null;
        $lote = [];
        $total = 0;
        $atividades = [];

        foreach (LeitorXlsx::linhas($arquivo) as $linha) {
            if ($cabecalho === null) {
                if (count(array_filter($linha, static fn ($v) => $v !== null && $v !== '')) >= 3) {
                    $cabecalho = array_map(static fn ($v): string => self::chave((string) $v), $linha);
                }
                continue;
            }
            $l = [];
            foreach ($cabecalho as $i => $nome) {
                $v = isset($linha[$i]) ? trim((string) $linha[$i]) : '';
                $l[$nome] = ($v === '' || $v === '-') ? null : $v;
            }
            $registro = self::registro($l, $municipios, $importacao);
            if ($registro === null) {
                continue;
            }
            $atividades[$registro[2]] = true;
            $lote[] = $registro;
            if (count($lote) >= self::LOTE) {
                $total += self::gravarLote($pdo, $lote);
                $lote = [];
            }
        }
        if ($lote) {
            $total += self::gravarLote($pdo, $lote);
        }
        if ($cabecalho === null) {
            throw new RuntimeException('a planilha não tem cabeçalho');
        }
        return [$total, array_keys($atividades)];
    }

    /**
     * Uma linha da planilha virando os campos da tabela, ou null se não der
     * para identificar o prestador. Só as colunas da migração 008 saem daqui.
     *
     * @param array<string,?string> $l
     * @param array<string,int> $municipios
     * @return list<mixed>|null
     */
    private static function registro(array $l, array $municipios, int $importacao): ?array
    {
        $atividade = (string) ($l['atividade turistica'] ?? '');
        $cnpj = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) ($l['numero de inscricao do cnpj'] ?? '')));
        $cpf = (string) preg_replace('/\D/', '', (string) ($l['cpf'] ?? ''));
        // O guia de turismo é registrado como pessoa: o CPF é a identificação
        // dele no Cadastur. Nas demais atividades o prestador é a empresa, e
        // o CPF da planilha é do responsável, que não entra aqui; só vale como
        // documento quando a linha não tem CNPJ (prestador pessoa física).
        $ehPessoa = str_contains(self::chave($atividade), 'guia de turismo');
        if ($ehPessoa && strlen($cpf) === 11) {
            [$doc, $tipo] = [$cpf, 'cpf'];
        } elseif (strlen($cnpj) === 14) {
            [$doc, $tipo] = [$cnpj, 'cnpj'];
        } elseif (strlen($cpf) === 11) {
            [$doc, $tipo] = [$cpf, 'cpf'];
        } else {
            return null;
        }
        if ($atividade === '') {
            return null;
        }
        $uf = strtoupper(substr((string) ($l['uf'] ?? ''), 0, 2)) ?: null;
        $municipio = $l['municipio'] ?? null;
        $municipioId = ($uf && $municipio) ? ($municipios[$uf . '|' . self::chave($municipio)] ?? null) : null;

        $categorias = [];
        foreach (self::COLUNAS_DE_CATEGORIA as $coluna) {
            foreach (preg_split('/\s*\|\s*/', (string) ($l[$coluna] ?? '')) ?: [] as $c) {
                if ($c !== '') {
                    $categorias[$c] = true;
                }
            }
        }
        $inteiro = static fn (?string $v): ?int => $v !== null && is_numeric($v) ? max(0, (int) $v) : null;

        return [
            $doc, $tipo, mb_substr($atividade, 0, 100),
            self::nome($l['nome da pessoa juridica'] ?? null),
            self::nome($l['nome fantasia'] ?? null),
            self::cortar($l['situacao cadastral'] ?? null, 40),
            self::cortar($l['situacao da atividade'] ?? null, 40),
            self::cortar($l['natureza juridica'] ?? null, 100),
            $uf, self::cortar($municipio, 100), $municipioId,
            self::cortar($l['numero do certificado'] ?? null, 30),
            LeitorXlsx::data($l['validade do certificado'] ?? null),
            $inteiro($l['leitos'] ?? null),
            $inteiro($l['unidade habitacionais'] ?? $l['unidades habitacionais'] ?? null),
            $categorias ? mb_substr(implode(' | ', array_keys($categorias)), 0, 500) : null,
            self::cortar(self::primeiro($l, ['endereco completo comercial', 'endereco comercial', 'endereco completo receita federal', 'endereco']), 300),
            self::cortar(self::primeiro($l, ['telefone comercial', 'telefone institucional', 'telefone']), 80),
            self::cortar(self::primeiro($l, ['e-mail comercial', 'e-mail institucional', 'e-mail', 'email']), 160),
            self::cortar(self::primeiro($l, ['website', 'site']), 200),
            self::cortar($l['porte'] ?? null, 60),
            self::cortar($l['idiomas'] ?? null, 200),
            $inteiro($l['uhs acessiveis'] ?? null),
            $inteiro($l['leitos acessiveis'] ?? null),
            $importacao,
        ];
    }

    /** @param list<list<mixed>> $lote */
    private static function gravarLote(PDO $pdo, array $lote): int
    {
        $uma = '(' . implode(',', array_fill(0, 25, '?')) . ')';
        $sql = 'INSERT INTO cadastur_prestadores
                (documento, tipo_documento, atividade, nome_juridico, nome_fantasia, situacao_cadastral,
                 situacao_atividade, natureza_juridica, uf, municipio, municipio_id, certificado, validade,
                 leitos, unidades, categorias, endereco, telefone, email, site, porte, idiomas,
                 unidades_acessiveis, leitos_acessiveis, importacao_id)
                VALUES ' . implode(',', array_fill(0, count($lote), $uma)) . '
                ON DUPLICATE KEY UPDATE tipo_documento = VALUES(tipo_documento), nome_juridico = VALUES(nome_juridico),
                  nome_fantasia = VALUES(nome_fantasia), situacao_cadastral = VALUES(situacao_cadastral),
                  situacao_atividade = VALUES(situacao_atividade), natureza_juridica = VALUES(natureza_juridica),
                  uf = VALUES(uf), municipio = VALUES(municipio), municipio_id = VALUES(municipio_id),
                  certificado = VALUES(certificado), validade = VALUES(validade), leitos = VALUES(leitos),
                  unidades = VALUES(unidades), categorias = VALUES(categorias), endereco = VALUES(endereco),
                  telefone = VALUES(telefone), email = VALUES(email), site = VALUES(site), porte = VALUES(porte),
                  idiomas = VALUES(idiomas), unidades_acessiveis = VALUES(unidades_acessiveis),
                  leitos_acessiveis = VALUES(leitos_acessiveis), importacao_id = VALUES(importacao_id)';
        $pdo->prepare($sql)->execute(array_merge(...$lote));
        return count($lote);
    }

    /** "UF|nome sem acento" => código IBGE. @return array<string,int> */
    private static function mapaDeMunicipios(): array
    {
        $mapa = [];
        foreach (Database::conexao()->query('SELECT m.id, m.nome, e.uf FROM municipios m JOIN estados e ON e.id = m.estado_id') as $m) {
            $mapa[$m['uf'] . '|' . self::chave((string) $m['nome'])] = (int) $m['id'];
        }
        return $mapa;
    }

    /** Minúsculo, sem acento e com espaço simples: cabeçalho e nome comparáveis. */
    private static function chave(string $texto): string
    {
        $t = mb_strtolower(trim($texto));
        $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e',
            'í' => 'i', 'î' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c', "'" => ' ']);
        return (string) preg_replace('/\s+/', ' ', $t);
    }

    /**
     * Nome como a planilha traz, sem a sujeira que ela tem: "*", "--" e "."
     * no lugar do nome fantasia viram vazio (a tela mostra o nome jurídico),
     * e aspas, asteriscos e traços nas pontas saem.
     */
    private static function nome(?string $v): ?string
    {
        if ($v === null || !preg_match('/[\p{L}\p{N}]/u', $v)) {
            return null;
        }
        $v = trim((string) preg_replace(['/^[\s"\'*.,:;_~`´-]+/u', '/[\s"\'*,:;_~`´-]+$/u'], '', $v));
        return $v === '' ? null : mb_substr($v, 0, 200);
    }

    /** O primeiro valor preenchido entre as colunas, na ordem de preferência. */
    private static function primeiro(array $l, array $colunas): ?string
    {
        foreach ($colunas as $c) {
            if (($l[$c] ?? null) !== null) {
                return (string) preg_replace('/\s+/', ' ', (string) $l[$c]);
            }
        }
        return null;
    }

    private static function cortar(?string $v, int $max): ?string
    {
        return $v === null ? null : mb_substr($v, 0, $max);
    }
}
