<?php
/**
 * A origem de uma importação, como ela aparece nas fichas (histórico, Outras
 * regras, observações e auditoria).
 * Softsul Software
 *
 * Vem do parâmetro obrigatório --origem="Importado do Inventário 2025": a
 * frase inteira, com o artigo certo para o documento de cada destino ("do
 * Inventário...", "da Pesquisa...", "de levantamento..."). Nada fixo no
 * código, para nenhum destino herdar a fonte de outro.
 *
 * O documento importado é só fonte de dados, não método: a frase não pode
 * chamá-lo de caderno (o caderno é o PDF que o InvenTour gera das fichas
 * homologadas). Regra do Luiz, 21/11/2026.
 */
declare(strict_types=1);

function origemDaImportacao(array $argv, string $uso): string
{
    $origem = '';
    foreach ($argv as $a) {
        if (str_starts_with($a, '--origem=')) {
            $origem = trim((string) preg_replace('/\s+/u', ' ', substr($a, 9)), " \t\"'.:");
        }
    }
    $erro = match (true) {
        $origem === ''                          => 'Falta --origem, a frase que vai para as fichas. Exemplo: --origem="Importado do Inventário 2025".',
        mb_strlen($origem) > 120                => 'A origem passa de 120 caracteres.',
        (bool) preg_match('/caderno/iu', $origem) => 'A origem não pode chamar o documento de caderno: ele é só fonte da importação.',
        default                                 => null,
    };
    if ($erro !== null) {
        fwrite(STDERR, $erro . "\n" . $uso . "\n");
        exit(1);
    }
    return $origem;
}
