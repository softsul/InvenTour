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

if ($problemas) {
    echo "\n{$problemas} problema(s).\n";
    exit(1);
}
echo "Nenhum formulário aninhado ou sem fechar, nenhum travessão, marca igual no portal, upload só por arrastar e soltar.\n";
