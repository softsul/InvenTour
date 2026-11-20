<?php
/**
 * O que toda lista do sistema tem: ordem, página e tamanho de página.
 * Softsul Software
 *
 * Cada tela escrevia o próprio ORDER BY fixo e o próprio LIMIT de cinquenta.
 * Ordenar por outra coluna não existia, e o número de linhas era o mesmo para
 * a tela de 27 polegadas e para o notebook. Aqui ficam as três coisas, uma
 * vez: a tela diz quais colunas podem ordenar e o resto é igual em todo lugar.
 *
 * A ordem vem por GET, e por isso a lista ordenada pode ser guardada nos
 * favoritos ou mandada para um colega, que verá exatamente a mesma tela.
 */
declare(strict_types=1);
require_once __DIR__ . '/Auth.php';

final class Lista
{
    /**
     * Lê a ordem pedida na URL e devolve o que a consulta precisa.
     *
     * $colunas é o mapa de nome público para SQL: só o que está aqui pode ser
     * ordenado. Sem essa lista, o parâmetro da URL entraria direto no ORDER BY,
     * que é injeção de SQL pela porta da frente.
     *
     * O valor de cada coluna é a expressão SQL, ou uma lista delas quando o
     * critério tem desempate ("cidade, depois UF"). Lista, e não texto com
     * vírgula: "COALESCE(a, b)" tem vírgula dentro e viraria dois pedaços,
     * com o DESC caindo no meio da função.
     *
     * @param array<string,string|list<string>> $colunas  chave da URL => SQL
     * @return array{chave:string,dir:string,sql:string}
     */
    /**
     * Filtro de múltipla escolha vindo do endereço: aceita a lista do
     * formulário (campo[]=a&campo[]=b) e o texto separado por barra
     * vertical (campo=a|b), que é como os links montados à mão o mandam.
     * Só fica o que está em $validos, na ordem de $validos.
     *
     * @param list<string> $validos
     * @return list<string>
     */
    public static function escolhas(string $campo, array $validos): array
    {
        $v = $_GET[$campo] ?? [];
        $v = is_array($v) ? array_map('strval', $v) : explode('|', (string) $v);
        return array_values(array_intersect($validos, $v));
    }

    public static function ordem(array $colunas, string $padrao, string $dirPadrao = 'asc'): array
    {
        $chave = (string) ($_GET['ord'] ?? '');
        if (!isset($colunas[$chave])) {
            $chave = $padrao;
        }
        $dir = strtolower((string) ($_GET['dir'] ?? '')) === 'desc' ? 'desc'
             : (strtolower((string) ($_GET['dir'] ?? '')) === 'asc' ? 'asc'
                : ($chave === $padrao ? $dirPadrao : 'asc'));

        $partes = (array) ($colunas[$chave] ?? reset($colunas));
        $sql = implode(', ', array_map(
            fn ($campo) => trim((string) $campo) . ' ' . strtoupper($dir),
            $partes
        ));

        return ['chave' => $chave, 'dir' => $dir, 'sql' => $sql];
    }

    /**
     * Como ordem(), mas sem coluna escolhida vale a ordem da própria tela
     * ("enviados e vencendo primeiro", "novos primeiro"), que é pensada para
     * quem precisa agir e não cabe numa coluna só. Aí nenhuma seta acende.
     *
     * @return array{chave:string,dir:string,sql:string}
     */
    public static function ordemOuPadrao(array $colunas, string $sqlPadrao): array
    {
        if (!isset($colunas[(string) ($_GET['ord'] ?? '')])) {
            return ['chave' => '', 'dir' => 'asc', 'sql' => $sqlPadrao];
        }
        return self::ordem($colunas, (string) $_GET['ord']);
    }

    /**
     * Um cabeçalho de tabela que ordena ao ser clicado.
     *
     * A seta mostra o que está valendo, e clicar na coluna já ordenada inverte
     * a direção: é o que todo mundo espera de uma tabela, e não precisa de
     * explicação em lugar nenhum.
     *
     * $link recebe os parâmetros novos e devolve o endereço da tela.
     *
     * @param array{chave:string,dir:string} $ordem
     */
    public static function th(string $rotulo, string $chave, array $ordem, callable $link,
                              string $classe = ''): string
    {
        $atual = $ordem['chave'] === $chave;
        $vai   = $atual && $ordem['dir'] === 'asc' ? 'desc' : 'asc';
        $seta  = self::setas($atual ? $ordem['dir'] : null);
        $titulo = $atual
            ? 'Ordenado por ' . $rotulo . '. Clique para inverter.'
            : 'Ordenar por ' . $rotulo;

        return '<th class="' . htmlspecialchars(trim($classe . ' ordenavel'
                 . ($atual ? ' ordenada' : '')), ENT_QUOTES) . '">'
             . '<a href="' . htmlspecialchars($link(['ord' => $chave, 'dir' => $vai, 'p' => null]), ENT_QUOTES)
             . '" title="' . htmlspecialchars($titulo, ENT_QUOTES) . '">'
             . htmlspecialchars($rotulo) . $seta . '</a></th>';
    }

    /**
     * O construtor de link que Lista::th pede, para a tela que não tem o
     * seu: o endereço atual com os mesmos filtros, trocando só o que vier.
     * Trocar a ordem volta à primeira página.
     */
    public static function link(string $base): callable
    {
        return function (array $troca) use ($base): string {
            $q = array_filter(array_merge($_GET, $troca), fn ($v) => $v !== null && $v !== '');
            return $base . ($q ? '?' . http_build_query($q) : '');
        };
    }

    /**
     * As duas setas, a de cima e a de baixo, com a da ordem que está valendo
     * acesa. As duas sempre à vista dizem que a coluna ordena antes do
     * clique; a acesa diz para que lado. O mesmo desenho é feito pelo
     * inventour.js nas tabelas que ordenam na própria tela.
     */
    public static function setas(?string $dir): string
    {
        return '<span class="setas-ordem' . ($dir ? ' ' . $dir : '') . '" aria-hidden="true">'
             . '<i class="s-cima"></i><i class="s-baixo"></i></span>';
    }

    /**
     * Guarda o tamanho de página pedido por "?pp=" e tira o parâmetro da URL.
     *
     * Sai do endereço de propósito: guardado na pessoa, ele não precisa viajar
     * em todo link, e um "?pp=200" esquecido num favorito mandaria de volta
     * uma escolha antiga toda vez que aquele link fosse aberto.
     *
     * Chamar no começo da tela, antes de qualquer saída.
     */
    public static function ouvirTamanho(string $base): void
    {
        if (!isset($_GET['pp'])) {
            return;
        }
        Auth::guardarPorPagina((int) $_GET['pp']);
        $q = $_GET;
        unset($q['pp'], $q['p']);
        header('Location: ' . $base . ($q ? '?' . http_build_query($q) : ''));
        exit;
    }

    /** O seletor de quantas linhas por página, ao lado da paginação. */
    public static function seletorTamanho(callable $link): string
    {
        $atual = Auth::porPagina();
        $html = '<span class="por-pagina"><span class="rot">por página</span>';
        foreach (Auth::POR_PAGINA_OPCOES as $n) {
            $html .= $n === $atual
                ? '<span class="pg atual">' . $n . '</span>'
                : '<a class="pg" href="' . htmlspecialchars($link(['pp' => $n]), ENT_QUOTES) . '">'
                  . $n . '</a>';
        }
        return $html . '</span>';
    }

    /**
     * Busca por palavras: cada palavra digitada precisa aparecer em alguma
     * das colunas, em qualquer ordem ("maria dilly" acha "Maria Oro Dilly").
     * Devolve [sql, argumentos] para juntar ao WHERE.
     */
    public static function porPalavras(string $busca, array $colunas): array
    {
        $partes = [];
        $args = [];
        foreach (array_slice(preg_split('/\s+/', trim($busca)) ?: [], 0, 6) as $palavra) {
            if ($palavra === '') {
                continue;
            }
            $partes[] = '(' . implode(' OR ', array_map(fn ($c) => "{$c} LIKE ?", $colunas)) . ')';
            foreach ($colunas as $c) {
                $args[] = '%' . $palavra . '%';
            }
        }
        return [$partes ? '(' . implode(' AND ', $partes) . ')' : '1 = 1', $args];
    }
}
