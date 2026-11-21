<?php
/**
 * Auxiliares próprios do InvenTour, por cima das funções de tela herdadas
 * do Orbit (app/svg.php).
 * Softsul Software
 */
declare(strict_types=1);
require_once __DIR__ . '/svg.php';

/** "1 dia", "3 dias": o número com a palavra no singular ou no plural, nunca "dia(s)". */
function plural(int $n, string $um, string $varios): string
{
    return milhar($n) . ' ' . ($n === 1 ? $um : $varios);
}

/** Número inteiro com o ponto de milhar: 153.674, 1.234.567 (regra do Luiz, 21/11/2026). */
function milhar(int|float $n, int $casas = 0): string
{
    return number_format((float) $n, $casas, ',', '.');
}

/** Valor em reais: R$ 1.234,56 (sem centavos acima de mil, que só poluem a estimativa). */
function dinheiro(float $v): string
{
    return 'R$ ' . number_format($v, $v >= 1000 ? 0 : 2, ',', '.');
}

/** Só os dígitos. CPF, CNPJ, CEP e telefone são gravados assim. */
function soDigitos(?string $texto): string
{
    return preg_replace('/\D/', '', (string) $texto);
}

/**
 * CPF pelos dois dígitos verificadores. O organizador pode ser pessoa
 * física (promotor com CPF), e CPF digitado errado só apareceria depois, no
 * credenciamento.
 */
function cpfValido(?string $cpf): bool
{
    $c = soDigitos($cpf);
    if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $c[$i] * ($t + 1 - $i);
        }
        if ((int) $c[$t] !== ((10 * $soma) % 11) % 10) {
            return false;
        }
    }
    return true;
}

/** CPF com máscara, só para exibir. No banco fica só o número. */
function formatarCpf(?string $cpf): string
{
    $d = soDigitos($cpf);
    return strlen($d) === 11
        ? substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9)
        : (string) $cpf;
}

/**
 * Como a pessoa prefere ser chamada: o "Como prefere ser chamado" de Meus
 * dados, inteiro, e só sem ele o primeiro nome. É o que vai na saudação e no
 * menu do usuário.
 */
function comoChamar(array $u): string
{
    $apelido = trim((string) ($u['apelido'] ?? ''));
    return $apelido !== '' ? $apelido : primeiroNome((string) ($u['nome'] ?? ''));
}

/**
 * Combobox de múltipla escolha (o comportamento está no inventour.js, em
 * [data-multi-escolha]). As caixas são as de verdade e vão no formulário.
 *
 * @param array<int,array{id:int|string,nome:string,descricao?:?string}> $opcoes
 * @param array<int,int> $marcados
 */
function multiEscolha(string $campo, string $idRotulo, array $opcoes, array $marcados, string $vazio, bool $travado = false): string
{
    $html = '<div class="multi-escolha" data-multi-escolha>'
          . '<button type="button" class="entrada multi-alvo" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="' . e($idRotulo) . '"'
          . ($travado ? ' disabled' : '') . '><span class="multi-fichas" data-vazio="' . e($vazio) . '"></span>' . icone('seta', null, 'chevron') . '</button>'
          . '<div class="multi-opcoes" role="listbox" aria-multiselectable="true" hidden>';
    foreach ($opcoes as $o) {
        $marcado = in_array((int) $o['id'], $marcados, true);
        $html .= '<label class="check multi-opcao"><input type="checkbox" name="' . e($campo) . '[]" value="' . (int) $o['id'] . '" data-nome="' . e($o['nome']) . '"'
               . ($marcado ? ' checked' : '') . ($travado ? ' disabled' : '') . '><i></i><span><b>' . e($o['nome']) . '</b>'
               . (!empty($o['descricao']) ? '<small>' . e((string) $o['descricao']) . '</small>' : '') . '</span></label>';
    }
    return $html . '</div></div>';
}

/** Aviso que sobrevive ao redirecionamento e aparece na tela seguinte. */
function avisar(string $texto, string $tom = 'bom'): void
{
    $_SESSION['aviso'] = ['tom' => $tom, 'texto' => $texto];
}

/**
 * O aviso guardado, como parágrafo da casa, e apagado da sessão. Com
 * data-push, o inventour.js o leva para o canto superior direito como
 * notificação de 3 segundos; sem JavaScript, fica no alto da página.
 */
function avisoGuardado(): string
{
    // A prévia da aba vizinha (o app busca para mostrar por baixo ao virar a página) não gasta o aviso.
    if (empty($_SESSION['aviso']) || ($_SERVER['HTTP_X_INVENTOUR_PREVIA'] ?? '') === '1') {
        return '';
    }
    $a = $_SESSION['aviso'];
    unset($_SESSION['aviso']);
    $tom = in_array($a['tom'] ?? '', ['bom', 'ruim', 'atencao', 'info'], true) ? $a['tom'] : 'bom';
    return '<p class="aviso ' . $tom . '" data-push' . ($tom === 'ruim' ? ' role="alert"' : ' role="status"') . '>'
         . icone($tom === 'ruim' || $tom === 'atencao' ? 'alerta' : 'check') . '<span>' . e($a['texto']) . '</span></p>';
}

function redirecionar(string $para): never
{
    header('Location: ' . $para);
    exit;
}

/**
 * Cabeça comum às telas: folhas, script, ícone e marcas de aplicativo.
 *
 * Duas folhas: tema.css, que é o do Orbit sem mudança de desenho, e
 * inventour.css, que troca o acento e as fontes e acrescenta o que só o
 * InvenTour tem. As fontes vêm do próprio servidor: a CSP não carrega nada
 * de fora.
 */
function cabecaComum(string $titulo, ?bool $escuro = false): void
{
    require_once __DIR__ . '/Marca.php';
    ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titulo) ?> · <?= e(Marca::nome()) ?></title>
<link rel="preload" href="/assets/fontes/open-sans-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/tema.css?v=<?= VERSAO_ASSETS ?>">
<link rel="stylesheet" href="/assets/inventour.css?v=<?= VERSAO_ASSETS ?>">
<link rel="stylesheet" href="/assets/marca/marca.css?v=<?= VERSAO_ASSETS ?>">
<script src="/assets/inventour.js?v=<?= VERSAO_ASSETS ?>" defer></script>
<link rel="icon" href="/assets/icone.svg?v=<?= VERSAO_ASSETS ?>" type="image/svg+xml">
<?= cabecaAplicativo($escuro) ?>
<?php
}
