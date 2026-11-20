<?php
/**
 * Consulta de CNPJ em fontes públicas.
 * Softsul Software
 *
 * A BrasilAPI é a fonte primária: é gratuita, não exige chave e lê da base
 * da Receita. A ReceitaWS entra quando a primeira falha, porque limita a 3
 * consultas por minuto e não serve como fonte principal.
 *
 * A resposta crua é devolvida junto (chave 'cru') para quem chamou poder
 * guardar e auditar de onde veio cada campo preenchido sozinho. Numa
 * prefeitura, "o sistema preencheu" não é resposta aceitável.
 */
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/svg.php';

class Cnpj
{
    /**
     * Valida pelos dois dígitos verificadores, já no CNPJ alfanumérico da
     * Receita (IN RFB 2.229/2024, em vigor desde julho de 2026): as 12
     * primeiras posições podem ter letras de A a Z, e as 2 últimas são os
     * verificadores, sempre números. Cada caractere vale o código ASCII
     * menos 48 ("0" a "9" seguem valendo 0 a 9; "A" vale 17), com os mesmos
     * pesos e o mesmo módulo 11 de antes, então o CNPJ só de números continua
     * dando o mesmo resultado.
     */
    public static function valido(string $cnpj): bool
    {
        $c = self::normalizar($cnpj);
        if (!preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $c) || preg_match('/^(.)\1{13}$/', $c)) {
            return false;
        }
        foreach ([12, 13] as $posicao) {
            $soma = 0;
            $peso = $posicao - 7;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (ord($c[$i]) - 48) * $peso;
                $peso = ($peso - 1) < 2 ? 9 : $peso - 1;
            }
            $digito = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
            if ((int) $c[$posicao] !== $digito) {
                return false;
            }
        }
        return true;
    }

    /** Só letras maiúsculas e números: é assim que o CNPJ vai para o banco. */
    public static function normalizar(string $cnpj): string
    {
        return (string) preg_replace('/[^0-9A-Z]/', '', strtoupper($cnpj));
    }

    /** Nome antigo de normalizar(), de quando o CNPJ era só de dígitos. */
    public static function apenasDigitos(string $cnpj): string
    {
        return self::normalizar($cnpj);
    }

    /**
     * Busca os dados do CNPJ.
     *
     * @return array{ok:bool, erro?:string, fonte?:string, dados?:array, cru?:array}
     */
    public static function consultar(string $cnpj): array
    {
        $c = self::apenasDigitos($cnpj);
        if (!self::valido($c)) {
            return ['ok' => false, 'erro' => 'CNPJ inválido. Confira os dígitos.'];
        }

        $cfg = require __DIR__ . '/config.php';

        $r = self::buscar($cfg['cnpj']['brasilapi'] . $c, $cfg['cnpj']['timeout_s']);
        if ($r !== null && !isset($r['message'])) {
            return ['ok' => true, 'fonte' => 'BrasilAPI', 'dados' => self::daBrasilApi($r), 'cru' => $r];
        }

        $r = self::buscar($cfg['cnpj']['receitaws'] . $c, $cfg['cnpj']['timeout_s']);
        if ($r !== null && ($r['status'] ?? '') !== 'ERROR') {
            return ['ok' => true, 'fonte' => 'ReceitaWS', 'dados' => self::daReceitaWs($r), 'cru' => $r];
        }

        return ['ok' => false, 'erro' => 'Não foi possível consultar agora. Preencha à mão ou tente de novo em instantes.'];
    }

    private static function buscar(string $url, int $timeout): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'InvenTour (app.inventour.com.br)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $corpo   = curl_exec($ch);
        $codigo  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($corpo === false || $codigo < 200 || $codigo >= 300) {
            return null;
        }
        $dados = json_decode((string) $corpo, true);
        return is_array($dados) ? $dados : null;
    }

    /** Normaliza a resposta da BrasilAPI para os campos de endereço e cadastro usados nas fichas. */
    private static function daBrasilApi(array $r): array
    {
        return [
            'razao_social'       => self::limpar($r['razao_social'] ?? null),
            'nome_fantasia'      => self::limpar($r['nome_fantasia'] ?? null),
            'email'              => self::limpar($r['email'] ?? null),
            'telefone'           => self::telefone($r['ddd_telefone_1'] ?? null),
            'cep'                => self::digitos($r['cep'] ?? null),
            'logradouro'         => self::limpar(trim(($r['descricao_tipo_de_logradouro'] ?? '') . ' ' . ($r['logradouro'] ?? ''))),
            'numero'             => self::limpar($r['numero'] ?? null),
            'complemento'        => self::limpar($r['complemento'] ?? null),
            'bairro'             => self::limpar($r['bairro'] ?? null),
            'cidade'             => self::limpar($r['municipio'] ?? null),
            'uf'                 => self::limpar($r['uf'] ?? null),
            'codigo_ibge'        => isset($r['codigo_municipio_ibge']) ? (string) $r['codigo_municipio_ibge'] : null,
            'natureza_juridica'  => self::limpar($r['natureza_juridica'] ?? null),
            'cnae_principal'     => trim(((string) ($r['cnae_fiscal'] ?? '')) . ' ' . ($r['cnae_fiscal_descricao'] ?? '')) ?: null,
            'situacao_cadastral' => self::limpar($r['descricao_situacao_cadastral'] ?? null),
            'inicio_atividade'   => preg_match('/^(\d{4})-/', (string) ($r['data_inicio_atividade'] ?? ''), $m) ? (int) $m[1] : null,
        ];
    }

    private static function daReceitaWs(array $r): array
    {
        return [
            'razao_social'       => self::limpar($r['nome'] ?? null),
            'nome_fantasia'      => self::limpar($r['fantasia'] ?? null),
            'email'              => self::limpar($r['email'] ?? null),
            'telefone'           => self::telefone($r['telefone'] ?? null),
            'cep'                => self::digitos($r['cep'] ?? null),
            'logradouro'         => self::limpar($r['logradouro'] ?? null),
            'numero'             => self::limpar($r['numero'] ?? null),
            'complemento'        => self::limpar($r['complemento'] ?? null),
            'bairro'             => self::limpar($r['bairro'] ?? null),
            'cidade'             => self::limpar($r['municipio'] ?? null),
            'uf'                 => self::limpar($r['uf'] ?? null),
            'codigo_ibge'        => null,
            'natureza_juridica'  => self::limpar($r['natureza_juridica'] ?? null),
            'cnae_principal'     => isset($r['atividade_principal'][0])
                ? trim(($r['atividade_principal'][0]['code'] ?? '') . ' ' . ($r['atividade_principal'][0]['text'] ?? ''))
                : null,
            'situacao_cadastral' => self::limpar($r['situacao'] ?? null),
            'inicio_atividade'   => preg_match('#/(\d{4})$#', (string) ($r['abertura'] ?? ''), $m) ? (int) $m[1] : null,
        ];
    }

    private static function limpar(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim(preg_replace('/\s+/u', ' ', $v));
        return $v === '' ? null : $v;
    }

    private static function digitos(?string $v): ?string
    {
        $d = preg_replace('/\D/', '', (string) $v);
        return $d === '' ? null : $d;
    }

    /**
     * Telefone normalizado, pela mesma função que o resto do sistema usa.
     *
     * Uma regra só: duas cópias divergem, e bastaria alguém corrigir o zero
     * de operadora num lugar e não no outro para o telefone vindo da Receita
     * ficar diferente do digitado à mão.
     */
    private static function telefone(?string $v): ?string
    {
        return telefoneParaBanco($v);
    }
}
