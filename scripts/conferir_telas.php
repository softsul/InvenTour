<?php
/**
 * Confere problemas estruturais nas telas do painel e do portal.
 * Softsul Software
 *
 *   php scripts/conferir_telas.php
 *
 * Herdado do Orbit, por causa de um defeito real: um cartão movido para
 * dentro do formulário principal fez o navegador fechar o form de fora no
 * primeiro aninhado, e todo campo depois disso deixou de ser enviado. O PHP
 * não acusa nada: a sintaxe está certa, o erro é de estrutura.
 *
 * Confere também travessão em qualquer arquivo do projeto, porque a regra da
 * casa é não usar e o grep manual sempre esquece uma pasta.
 */
declare(strict_types=1);

$raiz = dirname(__DIR__);
$problemas = 0;

foreach (array_merge(glob("{$raiz}/public/*.php") ?: [], glob("{$raiz}/portal/*.php") ?: []) as $arquivo) {
    $nome = substr($arquivo, strlen($raiz) + 1);
    $html = (string) file_get_contents($arquivo);

    // Tira o que está dentro de <script>: lá dentro pode haver <form> como
    // texto de template, e não é markup da página.
    $limpo = (string) preg_replace('#<script\b.*?</script>#is', '', $html);

    $profundidade = 0;
    $linha = 1;
    foreach (preg_split('/(<form\b[^>]*>|<\/form>)/i', $limpo, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $pedaco) {
        if (preg_match('#^<form\b#i', $pedaco)) {
            $profundidade++;
            if ($profundidade > 1) {
                echo "{$nome}: formulário aninhado por volta da linha {$linha}\n";
                echo "  Tudo que vier depois deixa de ser enviado pelo formulário de fora.\n";
                $problemas++;
            }
        } elseif (preg_match('#^</form>#i', $pedaco)) {
            $profundidade--;
        }
        $linha += substr_count($pedaco, "\n");
    }
    if ($profundidade !== 0) {
        echo "{$nome}: {$profundidade} formulário(s) sem fechar\n";
        $problemas++;
    }
}

// Travessão (U+2013 e U+2014) em qualquer arquivo de texto versionado.
$iterador = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
    static fn ($atual) => !in_array($atual->getFilename(), ['.git', 'storage', 'logs', 'vendor', 'node_modules', 'build', 'material-original', 'fontes'], true)
));
foreach ($iterador as $arquivo) {
    if (!preg_match('/\.(php|js|css|sql|md|html|txt|conf|json|webmanifest|svg|htaccess|local)$/', $arquivo->getFilename())) {
        continue;
    }
    $linhas = file((string) $arquivo) ?: [];
    foreach ($linhas as $n => $texto) {
        if (preg_match('/[\x{2013}\x{2014}]/u', $texto)) {
            echo substr((string) $arquivo, strlen($raiz) + 1) . ':' . ($n + 1) . ": travessão\n";
            $problemas++;
        }
    }
}

// A marca existe nos dois DocumentRoots (painel e portal), porque o portal
// não serve nada de public/. As cópias precisam ser iguais.
foreach (['marca/marca.css', 'marca/emblema.svg', 'icone.svg'] as $arquivo) {
    $a = "{$raiz}/public/assets/{$arquivo}";
    $b = "{$raiz}/portal/assets/{$arquivo}";
    if (!is_file($b) || hash_file('sha256', $a) !== hash_file('sha256', $b)) {
        echo "portal/assets/{$arquivo}: diferente de public/assets/{$arquivo}; copie de novo\n";
        $problemas++;
    }
}

// Upload é sempre arrastar e soltar (zonaDeUpload, em app/svg.php): campo
// de arquivo solto numa tela é regressão. O recorte da foto (Foto.php) usa
// campos internos escondidos e fica de fora.
foreach (array_merge(glob("{$raiz}/public/*.php") ?: [], glob("{$raiz}/portal/*.php") ?: []) as $arquivo) {
    foreach (file($arquivo) ?: [] as $n => $linha) {
        if (stripos($linha, 'type="file"') !== false) {
            echo substr((string) $arquivo, strlen($raiz) + 1) . ':' . ($n + 1) . ": campo de arquivo solto; use zonaDeUpload()\n";
            $problemas++;
        }
    }
}

// Regra do Luiz (20/11/2026): nenhum campo cru. Todo input de texto,
// select e textarea fica dentro de .entrada (ou de um componente da casa:
// multi-escolha, busca viva, paleta). Campos escondidos, de marcar, de
// arquivo e de faixa (zoom) ficam de fora, e o que leva hidden também.
$camposDaCasa = ['campo-paleta'];
foreach (array_merge(glob("{$raiz}/public/*.php") ?: [], glob("{$raiz}/portal/*.php") ?: [], glob("{$raiz}/app/*.php") ?: []) as $arquivo) {
    $linhas = file($arquivo) ?: [];
    foreach ($linhas as $n => $linha) {
        if (!preg_match_all('/<(input|select|textarea)\b([^>]*)/i', $linha, $achados, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($achados as $m) {
            $attrs = $m[2][0];
            $tipo = preg_match('/type=["\']?(\w+)/i', $attrs, $t) ? strtolower($t[1]) : strtolower($m[1][0]);
            // O resto da tag: o [^>]* para no fecha-PHP de dentro do value.
            $tag = (string) preg_replace('/<\?=.*?\?>/', '', substr($linha, (int) $m[0][1], 600));
            $tag = substr($tag, 0, (int) (strpos($tag, '>') ?: strlen($tag)) + 1);
            if (in_array($tipo, ['hidden', 'checkbox', 'radio', 'file', 'submit', 'button', 'range'], true)
                || preg_match('/\shidden[\s>=]/', $tag)
                // select.ausencia: o NA/NI/NE que vai dentro da .entrada do campo, no item.php
                || str_contains($tag, 'class="ausencia"')) {
                continue;
            }
            $id = preg_match('/id=["\']([^"\']+)/', $attrs, $i) ? $i[1] : '';
            if (in_array($id, $camposDaCasa, true)) {
                continue;
            }
            $antes = implode(' ', array_slice($linhas, max(0, $n - 2), min(2, $n))) . ' ' . substr($linha, 0, (int) $m[0][1]);
            if (preg_match('/class=["\'][^"\']*\b(entrada|multi-[a-z]+|busca-viva|pegar-paleta)\b/', $antes)) {
                continue;
            }
            echo substr((string) $arquivo, strlen($raiz) + 1) . ':' . ($n + 1) . ": campo cru ({$tipo}); ponha dentro de .entrada\n";
            $problemas++;
        }
    }
}

// Regra do Luiz (20/11/2026): foto e PDF abrem em modal, sempre (data-visualizar).
foreach (array_merge(glob("{$raiz}/public/*.php") ?: [], glob("{$raiz}/portal/*.php") ?: []) as $arquivo) {
    foreach (file($arquivo) ?: [] as $n => $linha) {
        if (preg_match('/<a\b[^>]*target="_blank"[^>]*>\s*<img|<a\b[^>]*href="[^"]*\.pdf[^"]*"[^>]*target="_blank"/i', $linha)) {
            echo substr((string) $arquivo, strlen($raiz) + 1) . ':' . ($n + 1) . ": foto ou PDF em aba nova; use data-visualizar (abre em modal)\n";
            $problemas++;
        }
    }
}

if ($problemas) {
    echo "\n{$problemas} problema(s).\n";
    exit(1);
}
echo "Nenhum formulário aninhado ou sem fechar, nenhum travessão, marca igual no portal, upload só por arrastar e soltar, nenhum campo cru, foto e PDF em modal.\n";
