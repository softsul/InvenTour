<?php
/**
 * A ficha completa do item no formato do formulário, a mesma no painel
 * (public/item.php) e na coleta de campo (public/api/coleta.php e o app
 * InvenTour Campo). Softsul Software
 *
 * O formato é o do POST do item.php: as colunas do núcleo pelo nome
 * (nome, cnpj, logradouro...), o funcionamento em h24, h_abre[dia],
 * h_fecha[dia], h_feriados e h_temporada, pagamentos[] e idiomas[], os
 * campos específicos da ficha em at[id] com a ausência em aus[id], a
 * acessibilidade em acess[chave][] e as tribos em facilidades[].
 *
 * O painel manda a ficha inteira. A coleta manda só o que mudou no
 * aparelho (parcial): o que não veio fica como está no banco, para não
 * apagar o que alguém acertou no painel enquanto o celular estava sem sinal.
 */
declare(strict_types=1);
require_once __DIR__ . '/Inventario.php';
require_once __DIR__ . '/Cnpj.php';
require_once __DIR__ . '/PlusCode.php';

final class FichaFormulario
{
    /** Teclas do funcionamento: chegando qualquer uma, o horário inteiro é regravado. */
    private const CHAVES_HORARIO = ['h24', 'h_abre', 'h_fecha', 'h_abre2', 'h_fecha2', 'h_feriados', 'h_temporada'];

    /**
     * Lê o formulário e devolve o que gravar, já validado.
     *
     * @return array{nucleo: array<string,mixed>, especificos: array<int,array{texto:?string,numero:?float,ausencia:?string}>,
     *               acess: array<string,list<string>>, facilidades: ?list<int>, erros: array<string,string>}
     */
    public static function ler(array $p, array $ficha, bool $parcial = false): array
    {
        $fichaId = (int) $ficha['id'];
        $municipio = $ficha['forma'] === 'municipio';
        $veio = fn (string $c): bool => !$parcial || array_key_exists($c, $p);
        $texto = fn (string $c, int $max = 255): ?string => ($v = mb_substr(trim((string) (is_scalar($p[$c] ?? null) ? $p[$c] : '')), 0, $max)) !== '' ? $v : null;
        $numero = function (string $c) use ($p): ?float {
            $v = str_replace(',', '.', trim((string) (is_scalar($p[$c] ?? null) ? $p[$c] : '')));
            return is_numeric($v) ? (float) $v : null;
        };
        $doCatalogo = fn (string $c, array $validos): ?string => is_scalar($p[$c] ?? null) && array_key_exists((string) $p[$c], $validos) ? (string) $p[$c] : null;
        $simNao = fn (string $c): ?int => !is_scalar($p[$c] ?? null) || (string) $p[$c] === '' ? null : (int) ((string) $p[$c] === '1');

        $n = [];
        $set = function (string $col, callable $valor, ?string $chave = null) use (&$n, $veio): void {
            if ($veio($chave ?? $col)) {
                $n[$col] = $valor();
            }
        };
        $set('nome', fn () => (string) $texto('nome', 180));
        $set('tipo_id', function () use ($p, $fichaId) {
            $t = (int) (is_scalar($p['tipo_id'] ?? null) ? $p['tipo_id'] : 0);
            return $t && in_array($t, array_map('intval', array_column(Inventario::tipos($fichaId), 'id')), true) ? $t : null;
        });
        $set('nome_popular', fn () => $texto('nome_popular', 180));
        if (!$municipio) {
            $set('razao_social', fn () => $texto('razao_social', 180));
            $set('cnpj', fn () => Cnpj::normalizar((string) $texto('cnpj', 30)) ?: null);
            $set('inscricao_municipal', fn () => $texto('inscricao_municipal', 30));
            $set('cadastur', fn () => $texto('cadastur', 30));
            $set('cadastur_validade', fn () => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $texto('cadastur_validade', 10)) ? $texto('cadastur_validade', 10) : null);
            $set('natureza', fn () => $doCatalogo('natureza', Inventario::NATUREZAS));
            $set('jurisdicao', fn () => $doCatalogo('jurisdicao', Inventario::JURISDICOES));
            $set('rede', fn () => $texto('rede', 120));
            $set('inicio_atividade', fn () => ($a = (int) $numero('inicio_atividade')) >= 1500 && $a <= (int) date('Y') ? $a : null);
            $set('funcionarios', fn () => ($f = (int) $numero('funcionarios')) > 0 ? min($f, 65000) : null);
            $set('zona', fn () => $doCatalogo('zona', Inventario::ZONAS));
            $set('cep', fn () => strlen($cep = soDigitos((string) $texto('cep', 12))) === 8 ? $cep : null);
            $set('logradouro', fn () => $texto('logradouro', 200));
            $set('numero', fn () => $texto('numero', 20));
            $set('complemento', fn () => $texto('complemento', 80));
            $set('bairro', fn () => $texto('bairro', 120));
            $set('latitude', fn () => ($la = $numero('latitude')) !== null && abs($la) <= 90 ? round($la, 7) : null);
            $set('longitude', fn () => ($lo = $numero('longitude')) !== null && abs($lo) <= 180 ? round($lo, 7) : null);
            $set('plus_code', fn () => PlusCode::valido($pc = strtoupper((string) $texto('plus_code', 16))) ? $pc : null);
            $set('maps_url', fn () => $texto('maps_url', 500));
            $set('referencia', fn () => $texto('referencia', 255));
            $set('sinalizacao_acesso', fn () => $simNao('sinalizacao_acesso'));
            $set('sinalizacao_turistica', fn () => $simNao('sinalizacao_turistica'));
            if ($ficha['forma'] === 'linear') {
                $set('extensao_km', fn () => $numero('extensao_km'));
            }
            $set('telefone', fn () => telefoneParaBanco($texto('telefone', 30)));
            $set('whatsapp', fn () => telefoneParaBanco($texto('whatsapp', 30)));
            $set('email', fn () => ($em = mb_strtolower((string) $texto('email', 150))) !== '' ? $em : null);
            $set('site', fn () => $texto('site', 255));
            $set('instagram', fn () => ($ig = ltrim((string) $texto('instagram', 100), '@')) !== '' ? $ig : null);
            $horarioVeio = !$parcial || array_intersect(self::CHAVES_HORARIO, array_keys($p));
            if ($horarioVeio) {
                $h = ['24h' => !empty($p['h24']), 'feriados' => $texto('h_feriados', 200), 'temporada' => $texto('h_temporada', 200)];
                foreach (Inventario::DIAS as $dia => $_) {
                    $abre = (string) ($p['h_abre'][$dia] ?? '');
                    $fecha = (string) ($p['h_fecha'][$dia] ?? '');
                    if (preg_match('/^\d{2}:\d{2}$/', $abre) && preg_match('/^\d{2}:\d{2}$/', $fecha)) {
                        $h[$dia] = [[$abre, $fecha]];
                        // O segundo período do dia (volta da pausa do almoço).
                        $abre2 = (string) ($p['h_abre2'][$dia] ?? '');
                        $fecha2 = (string) ($p['h_fecha2'][$dia] ?? '');
                        if (preg_match('/^\d{2}:\d{2}$/', $abre2) && preg_match('/^\d{2}:\d{2}$/', $fecha2)) {
                            $h[$dia][] = [$abre2, $fecha2];
                        }
                    }
                }
                $n['horario'] = json_encode($h, JSON_UNESCAPED_UNICODE);
            }
            $set('formas_pagamento', fn () => json_encode(array_values(array_intersect(Inventario::PAGAMENTOS, (array) ($p['pagamentos'] ?? []))), JSON_UNESCAPED_UNICODE), 'pagamentos');
            $set('idiomas', fn () => json_encode(array_values(array_intersect(Inventario::IDIOMAS, (array) ($p['idiomas'] ?? []))), JSON_UNESCAPED_UNICODE));
            $set('conservacao', fn () => $doCatalogo('conservacao', Inventario::CONSERVACAO));
            $set('possui_experiencia', fn () => empty($p['possui_experiencia']) ? 0 : 1);
        }
        if (in_array($ficha['eventcity'], ['espaco_eventos', 'atrativo'], true)) {
            $set('capacidade_publico', fn () => ($cp = (int) $numero('capacidade_publico')) > 0 ? $cp : null);
        }
        $set('descricao_tecnica', fn () => $texto('descricao_tecnica', 20000));
        $set('descricao_promocional', fn () => $texto('descricao_promocional', 5000));
        $set('observacoes', fn () => $texto('observacoes', 10000));
        $set('referencias', fn () => $texto('referencias', 10000));

        // Campos específicos da ficha, com a ausência (NA, NI, NE) quando não há resposta.
        $esp = [];
        $at = (array) ($p['at'] ?? []);
        $aus = (array) ($p['aus'] ?? []);
        foreach (Inventario::atributos($fichaId) as $lista) {
            foreach ($lista as $a) {
                $aid = (int) $a['id'];
                if ($parcial && !array_key_exists($aid, $at) && !array_key_exists($aid, $aus)
                    && !array_key_exists((string) $aid, $at) && !array_key_exists((string) $aid, $aus)) {
                    continue;
                }
                $esp[$aid] = self::valorDoAtributo($a, $at[$aid] ?? null, $aus[$aid] ?? null);
            }
        }

        $acess = [];
        if (!$municipio) {
            $vindos = (array) ($p['acess'] ?? []);
            foreach ((array) (Inventario::blocosComuns()['acessibilidade'] ?? []) as $s) {
                if ($parcial && !array_key_exists($s['chave'], $vindos)) {
                    continue;
                }
                $acess[$s['chave']] = array_values(array_intersect((array) $s['opcoes'], (array) ($vindos[$s['chave']] ?? [])));
            }
        }
        $facilidades = !$municipio && $veio('facilidades') ? array_map('intval', (array) ($p['facilidades'] ?? [])) : null;

        $erros = [];
        if (!empty($n['cnpj']) && !Cnpj::valido($n['cnpj'])) {
            $erros['cnpj'] = 'CNPJ inválido. Confira os dígitos.';
        }
        if (!empty($n['email']) && !filter_var($n['email'], FILTER_VALIDATE_EMAIL)) {
            $erros['email'] = 'E-mail inválido.';
        }
        foreach (['telefone' => 'Telefone', 'whatsapp' => 'WhatsApp'] as $c => $rotulo) {
            if (array_key_exists($c, $n) && $n[$c] === null && $texto($c, 30) !== null) {
                $erros[$c] = $rotulo . ' incompleto. Use DDD e número.';
            }
        }
        return ['nucleo' => $n, 'especificos' => $esp, 'acess' => $acess, 'facilidades' => $facilidades, 'erros' => $erros];
    }

    /** Um campo específico: o valor bruto do formulário vira texto ou número. */
    public static function valorDoAtributo(array $a, mixed $bruto, mixed $ausencia): array
    {
        $aus = is_scalar($ausencia) && array_key_exists((string) $ausencia, Inventario::AUSENCIAS) ? (string) $ausencia : null;
        $v = ['texto' => null, 'numero' => null, 'ausencia' => $aus];
        switch ($a['tipo']) {
            case 'numero':
                $n = str_replace(',', '.', trim(is_scalar($bruto) ? (string) $bruto : ''));
                $v['numero'] = is_numeric($n) ? (float) $n : null;
                break;
            case 'sim_nao':
                $v['numero'] = $bruto === '1' || $bruto === 1 ? 1.0 : ($bruto === '0' || $bruto === 0 ? 0.0 : null);
                break;
            case 'multipla':
                $marcadas = array_values(array_intersect($a['opcoes'], array_map('strval', (array) $bruto)));
                $v['texto'] = $marcadas ? json_encode($marcadas, JSON_UNESCAPED_UNICODE) : null;
                break;
            case 'escolha':
                $v['texto'] = is_scalar($bruto) && in_array((string) $bruto, $a['opcoes'], true) ? (string) $bruto : null;
                break;
            case 'tabela':
                $linhas = [];
                foreach ((array) $bruto as $linha) {
                    $linha = array_map(fn ($c) => mb_substr(trim(is_scalar($c) ? (string) $c : ''), 0, 200), array_slice(array_values((array) $linha), 0, count($a['opcoes'])));
                    if (implode('', $linha) !== '') {
                        $linhas[] = $linha;
                    }
                }
                $v['texto'] = $linhas ? json_encode($linhas, JSON_UNESCAPED_UNICODE) : null;
                break;
            default:
                $v['texto'] = ($t = mb_substr(trim(is_scalar($bruto) ? (string) $bruto : ''), 0, $a['tipo'] === 'texto_longo' ? 10000 : 500)) !== '' ? $t : null;
        }
        if ($v['texto'] !== null || $v['numero'] !== null) {
            $v['ausencia'] = null;
        }
        return $v;
    }

    /**
     * Grava só os campos específicos, a acessibilidade e as tribos que
     * vieram (a coleta parcial), sem apagar os outros. Roda dentro da
     * transação de quem chama. Devolve as colunas de capacidade a atualizar.
     */
    public static function gravarParcial(int $itemId, array $ficha, array $especificos, array $acess, ?array $facilidades): array
    {
        $pdo = Database::conexao();
        $eid = Empresa::id();
        $del = $pdo->prepare('DELETE FROM inventario_item_atributos WHERE item_id = ? AND atributo_id = ? AND empresa_id = ?');
        $ins = $pdo->prepare('INSERT INTO inventario_item_atributos (item_id, atributo_id, empresa_id, valor_texto, valor_numero, ausencia) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($especificos as $aid => $v) {
            $del->execute([$itemId, $aid, $eid]);
            if ($v['texto'] !== null || $v['numero'] !== null || $v['ausencia'] !== null) {
                $ins->execute([$itemId, $aid, $eid, $v['texto'], $v['numero'], $v['ausencia']]);
            }
        }
        $delA = $pdo->prepare('DELETE FROM inventario_item_acessibilidade WHERE item_id = ? AND chave = ? AND empresa_id = ?');
        $insA = $pdo->prepare('INSERT INTO inventario_item_acessibilidade (item_id, empresa_id, chave, opcoes) VALUES (?, ?, ?, ?)');
        foreach ($acess as $chave => $opcoes) {
            $delA->execute([$itemId, $chave, $eid]);
            if ($opcoes) {
                $insA->execute([$itemId, $eid, $chave, json_encode(array_values($opcoes), JSON_UNESCAPED_UNICODE)]);
            }
        }
        if ($facilidades !== null) {
            Inventario::salvarFacilidades($itemId, $facilidades);
        }
        return Inventario::colunasDeCapacidade($ficha, $especificos);
    }

    // ------------------------------------------------------------ para o aparelho

    /** O catálogo que o aparelho leva para montar a ficha sem sinal. */
    public static function catalogo(array $fichaIds): array
    {
        $atributos = [];
        foreach ($fichaIds as $fid) {
            $lista = [];
            foreach (Inventario::atributos((int) $fid) as $grupo => $itens) {
                foreach ($itens as $a) {
                    $lista[] = ['id' => (int) $a['id'], 'grupo' => (string) $grupo, 'rotulo' => (string) $a['rotulo'], 'tipo' => (string) $a['tipo'],
                                'opcoes' => array_values($a['opcoes']), 'obrigatorio' => (bool) $a['obrigatorio'], 'ajuda' => $a['ajuda'] ?: null];
                }
            }
            if ($lista) {
                $atributos[(string) $fid] = $lista;
            }
        }
        $tribos = [];
        foreach (Inventario::tribos() as $t) {
            $fac = [];
            foreach ($t['facilidades'] as $id => $nome) {
                $fac[] = [$id, $nome];
            }
            $tribos[] = ['macro' => $t['macro'], 'nome' => $t['nome'], 'icone' => $t['icone'], 'facilidades' => $fac];
        }
        return ['atributos' => $atributos,
                'acessibilidade' => array_map(fn ($s) => ['chave' => $s['chave'], 'rotulo' => $s['rotulo'], 'opcoes' => array_values((array) $s['opcoes'])],
                    (array) (Inventario::blocosComuns()['acessibilidade'] ?? [])),
                'tribos' => $tribos];
    }

    /**
     * Os itens no formato do formulário, para o aparelho preencher a ficha
     * (só o que tem valor, para a lista baixar leve).
     *
     * @param list<array> $itens linhas de inventario_itens
     * @return array<int,array<string,mixed>> por id do item
     */
    public static function paraOAparelho(array $itens): array
    {
        if (!$itens) {
            return [];
        }
        $pdo = Database::conexao();
        $eid = Empresa::id();
        $ids = array_map(fn ($i) => (int) $i['id'], $itens);
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $tipos = $pdo->query('SELECT id, tipo FROM inventario_atributos')->fetchAll(PDO::FETCH_KEY_PAIR);
        $vals = $acs = $facs = [];
        $q = $pdo->prepare("SELECT item_id, atributo_id, valor_texto, valor_numero, ausencia FROM inventario_item_atributos WHERE empresa_id = ? AND item_id IN ({$marcas})");
        $q->execute([$eid, ...$ids]);
        foreach ($q as $l) {
            $vals[(int) $l['item_id']][] = $l;
        }
        $q = $pdo->prepare("SELECT item_id, chave, opcoes FROM inventario_item_acessibilidade WHERE empresa_id = ? AND item_id IN ({$marcas})");
        $q->execute([$eid, ...$ids]);
        foreach ($q as $l) {
            $acs[(int) $l['item_id']][$l['chave']] = json_decode((string) $l['opcoes'], true) ?: [];
        }
        $q = $pdo->prepare("SELECT item_id, facilidade_id FROM inventario_item_facilidades WHERE empresa_id = ? AND item_id IN ({$marcas})");
        $q->execute([$eid, ...$ids]);
        foreach ($q as $l) {
            $facs[(int) $l['item_id']][] = (string) $l['facilidade_id'];
        }

        $saida = [];
        foreach ($itens as $i) {
            $id = (int) $i['id'];
            $f = [];
            foreach (['nome', 'nome_popular', 'razao_social', 'inscricao_municipal', 'cadastur', 'cadastur_validade', 'natureza', 'jurisdicao', 'rede',
                      'inicio_atividade', 'funcionarios', 'zona', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'plus_code', 'maps_url',
                      'referencia', 'sinalizacao_acesso', 'sinalizacao_turistica', 'email', 'site', 'capacidade_publico', 'conservacao',
                      'descricao_tecnica', 'descricao_promocional', 'referencias', 'tipo_id', 'latitude', 'longitude'] as $c) {
                if (isset($i[$c]) && $i[$c] !== '') {
                    $f[$c] = (string) $i[$c];
                }
            }
            if (isset($f['cep']) && strlen($f['cep']) === 8) {
                $f['cep'] = substr($f['cep'], 0, 5) . '-' . substr($f['cep'], 5);
            }
            if (!empty($i['cnpj'])) {
                $f['cnpj'] = strlen((string) $i['cnpj']) === 14 ? formatarCnpj((string) $i['cnpj']) : (string) $i['cnpj'];
            }
            foreach (['telefone', 'whatsapp'] as $c) {
                if (!empty($i[$c])) {
                    $f[$c] = ctype_digit((string) $i[$c]) ? formatarTelefone((string) $i[$c]) : (string) $i[$c];
                }
            }
            if (!empty($i['instagram'])) {
                $f['instagram'] = '@' . $i['instagram'];
            }
            if ($i['extensao_km'] !== null) {
                $f['extensao_km'] = rtrim(rtrim(number_format((float) $i['extensao_km'], 2, ',', ''), '0'), ',');
            }
            if (!empty($i['possui_experiencia'])) {
                $f['possui_experiencia'] = '1';
            }
            $h = json_decode((string) ($i['horario'] ?? ''), true) ?: [];
            if (!empty($h['24h'])) {
                $f['h24'] = '1';
            }
            if (!empty($h['feriados'])) {
                $f['h_feriados'] = (string) $h['feriados'];
            }
            if (!empty($h['temporada'])) {
                $f['h_temporada'] = (string) $h['temporada'];
            }
            foreach (Inventario::DIAS as $dia => $_) {
                if (!empty($h[$dia][0][0])) {
                    $f['h_abre'][$dia] = (string) $h[$dia][0][0];
                    $f['h_fecha'][$dia] = (string) $h[$dia][0][1];
                    if (!empty($h[$dia][1][0])) {
                        $f['h_abre2'][$dia] = (string) $h[$dia][1][0];
                        $f['h_fecha2'][$dia] = (string) $h[$dia][1][1];
                    }
                }
            }
            foreach (['formas_pagamento' => 'pagamentos', 'idiomas' => 'idiomas'] as $col => $chave) {
                if ($lista = json_decode((string) ($i[$col] ?? ''), true)) {
                    $f[$chave] = array_values($lista);
                }
            }
            foreach ($vals[$id] ?? [] as $v) {
                $aid = (string) $v['atributo_id'];
                $tipo = $tipos[(int) $aid] ?? 'texto';
                if ($v['ausencia']) {
                    $f['aus'][$aid] = (string) $v['ausencia'];
                }
                if ($v['valor_numero'] !== null) {
                    $f['at'][$aid] = $tipo === 'sim_nao' ? ((float) $v['valor_numero'] === 1.0 ? '1' : '0')
                        : rtrim(rtrim(number_format((float) $v['valor_numero'], 2, ',', ''), '0'), ',');
                } elseif ($v['valor_texto'] !== null) {
                    $f['at'][$aid] = in_array($tipo, ['multipla', 'tabela'], true) ? (json_decode((string) $v['valor_texto'], true) ?: []) : (string) $v['valor_texto'];
                }
            }
            if (!empty($acs[$id])) {
                $f['acess'] = $acs[$id];
            }
            if (!empty($facs[$id])) {
                $f['facilidades'] = $facs[$id];
            }
            $saida[$id] = $f;
        }
        return $saida;
    }
}
