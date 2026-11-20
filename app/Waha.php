<?php
/**
 * O WAHA (servidor de WhatsApp da casa) no InvenTour, no molde do Orbit.
 * Softsul Software
 *
 * Duas sessões: a de consulta (config.php, a da Softsul, que a equipe e os
 * parceiros têm nos contatos: número tem WhatsApp e foto do perfil) e a de
 * envio, escolhida em Configurações da plataforma (ajustes do grupo
 * whatsapp, empresa 0). O envio roda sempre na fila (jobs), nunca na
 * requisição de quem salvou. Com "número de teste" preenchido, toda
 * mensagem vai para ele, com o destinatário real no começo do texto.
 */
declare(strict_types=1);
require_once __DIR__ . '/Ajustes.php';

final class Waha
{
    private const TEMPO_LIMITE = 15;

    private static function config(): array
    {
        return (require __DIR__ . '/config.php')['waha'] ?? [];
    }

    public static function configurado(): bool
    {
        $c = self::config();
        return !empty($c['url']) && !empty($c['api_key']);
    }

    public static function sessao(): string
    {
        return (string) (self::config()['sessao'] ?? 'default');
    }

    /** Ajustes do envio (plataforma): sessao_envio, ativo, numero_teste. */
    public static function ajustes(): array
    {
        $a = Ajustes::grupo('whatsapp', 0);
        return [
            'sessao_envio' => (string) ($a['sessao_envio'] ?? ''),
            'ativo'        => ($a['ativo'] ?? '0') === '1',
            'numero_teste' => preg_replace('/\D/', '', (string) ($a['numero_teste'] ?? '')),
        ];
    }

    public static function sessaoEnvio(): string
    {
        return self::ajustes()['sessao_envio'];
    }

    /** Envio ligado e com sessão escolhida. */
    public static function envioLigado(): bool
    {
        $a = self::ajustes();
        return self::configurado() && $a['ativo'] && $a['sessao_envio'] !== '';
    }

    /** As sessões do servidor WAHA, para escolher a de envio. */
    public static function sessoes(): array
    {
        $r = self::chamar('/sessions', ['all' => 'true']);
        if (!$r['ok']) {
            return $r;
        }
        $lista = [];
        foreach ($r['dados'] as $d) {
            $lista[] = ['nome' => (string) ($d['name'] ?? ''), 'status' => (string) ($d['status'] ?? ''),
                        'numero' => preg_replace('/@.*/', '', (string) ($d['me']['id'] ?? '')), 'perfil' => (string) ($d['me']['pushName'] ?? '')];
        }
        return ['ok' => true, 'sessoes' => $lista];
    }

    /** Estado de uma sessão: WORKING, SCAN_QR_CODE, STOPPED, FAILED ou AUSENTE. */
    public static function estadoDaSessao(string $sessao): array
    {
        $r = self::chamar('/sessions/' . rawurlencode($sessao));
        if (!$r['ok']) {
            return ($r['codigo'] ?? 0) === 404 ? ['ok' => true, 'existe' => false, 'status' => 'AUSENTE'] : $r;
        }
        $d = $r['dados'];
        return ['ok' => true, 'existe' => true, 'status' => (string) ($d['status'] ?? 'DESCONHECIDO'),
                'numero' => preg_replace('/@.*/', '', (string) ($d['me']['id'] ?? '')), 'nome' => (string) ($d['me']['pushName'] ?? '')];
    }

    /** Nome e foto do número conectado. */
    public static function perfil(string $sessao): array
    {
        $r = self::chamar('/' . rawurlencode($sessao) . '/profile');
        if (!$r['ok']) {
            return $r;
        }
        return ['ok' => true, 'nome' => (string) ($r['dados']['name'] ?? ''), 'foto' => (string) ($r['dados']['picture'] ?? '')];
    }

    /** Ligar ou reiniciar a sessão. Desconectar e parar ficam fora de propósito: a sessão pode ser de outro sistema. */
    public static function acaoNaSessao(string $sessao, string $acao): array
    {
        if (!in_array($acao, ['start', 'restart'], true)) {
            return ['ok' => false, 'erro' => 'Ação desconhecida.'];
        }
        return self::chamar('/sessions/' . rawurlencode($sessao) . '/' . $acao, [], []);
    }

    /** O QR de conexão em PNG; só existe em SCAN_QR_CODE e vale poucos segundos. */
    public static function qr(string $sessao): array
    {
        return self::binario('/' . rawurlencode($sessao) . '/auth/qr?format=image', 'image/png');
    }

    /** Uma imagem externa (a foto do perfil), trazida pelo servidor: o CSP do painel só aceita imagem do próprio domínio. */
    public static function baixarImagem(string $url): array
    {
        if (!preg_match('#^https://[a-z0-9.-]+\.(whatsapp\.net|fbcdn\.net)/#i', $url)) {
            return ['ok' => false, 'erro' => 'Endereço de foto inesperado.'];
        }
        return self::binario($url, 'image/jpeg', false);
    }

    /**
     * Manda um texto pela sessão de envio. Aceita telefone (resolve o
     * chatId real antes, lição do Orbit) ou um chatId já conhecido. Com
     * $teste, vai ao número pedido mesmo com o envio desligado.
     *
     * @return array{ok:bool, chat_id?:string, erro?:string}
     */
    public static function enviarTexto(string $telefoneOuChat, string $texto, string $nomeDestinatario = '', bool $teste = false): array
    {
        $a = self::ajustes();
        // O teste da tela vai para o número digitado mesmo com o envio
        // desligado: é como se confere a sessão antes de ligar.
        if ($teste ? ($a['sessao_envio'] === '' || !self::configurado()) : !self::envioLigado()) {
            return ['ok' => false, 'erro' => $teste ? 'Escolha e salve a sessão de envio antes.' : 'O envio por WhatsApp está desligado.'];
        }
        if (!$teste && $a['numero_teste'] !== '') {
            $texto = '[Teste. Para: ' . ($nomeDestinatario ?: $telefoneOuChat) . "]\n\n" . $texto;
            $telefoneOuChat = $a['numero_teste'];
        }
        $chatId = str_contains($telefoneOuChat, '@') ? $telefoneOuChat : null;
        if (!$chatId) {
            $r = self::resolverChatId(self::comPais($telefoneOuChat), $a['sessao_envio']);
            if (!$r['ok']) {
                return $r;
            }
            if (!$r['existe'] || !$r['chat_id']) {
                return ['ok' => false, 'erro' => 'O número não tem WhatsApp.'];
            }
            $chatId = (string) $r['chat_id'];
        }
        $r = self::chamar('/sendText', [], ['session' => $a['sessao_envio'], 'chatId' => $chatId, 'text' => $texto]);
        return $r['ok'] ? ['ok' => true, 'chat_id' => $chatId] : $r;
    }

    /** Telefone brasileiro sem DDI ganha o 55. */
    public static function comPais(string $telefone): string
    {
        $d = preg_replace('/\D/', '', $telefone);
        return strlen((string) $d) <= 11 ? '55' . $d : (string) $d;
    }

    /**
     * O chatId REAL do número, perguntando ao WhatsApp. Não é o telefone com
     * "@c.us" colado: o nono dígito do celular é normalizado de forma
     * irregular, e com o chatId errado a foto volta nula sem erro nenhum.
     * Lição do Orbit.
     *
     * @return array{ok:bool, existe?:bool, chat_id?:string|null, erro?:string}
     */
    public static function resolverChatId(string $telefone, ?string $sessao = null): array
    {
        $d = preg_replace('/\D/', '', $telefone);
        if (strlen((string) $d) < 12) {
            return ['ok' => false, 'erro' => 'Telefone incompleto.'];
        }
        $r = self::chamar('/contacts/check-exists', ['phone' => $d, 'session' => $sessao ?? self::sessao()]);
        if (!$r['ok']) {
            return $r;
        }
        return ['ok' => true, 'existe' => (bool) ($r['dados']['numberExists'] ?? false), 'chat_id' => $r['dados']['chatId'] ?? null];
    }

    /**
     * A URL da foto de perfil. Sem foto, ou com foto restrita pela pessoa,
     * volta "sem foto", e não erro: é a privacidade dela, não falha.
     *
     * @return array{ok:bool, url?:string|null, chat_id?:string|null, existe?:bool, erro?:string}
     */
    public static function fotoDePerfil(string $telefone, ?string $chatIdConhecido = null, ?string $sessao = null): array
    {
        $chatId = $chatIdConhecido;
        if (!$chatId) {
            $r = self::resolverChatId($telefone, $sessao);
            if (!$r['ok']) {
                return $r;
            }
            if (!$r['existe']) {
                return ['ok' => true, 'url' => null, 'existe' => false, 'chat_id' => null];
            }
            $chatId = $r['chat_id'];
        }
        if (!$chatId) {
            return ['ok' => false, 'erro' => 'Não consegui identificar o número no WhatsApp.'];
        }
        $r = self::chamar('/contacts/profile-picture', ['contactId' => $chatId, 'session' => $sessao ?? self::sessao()]);
        if (!$r['ok']) {
            return $r;
        }
        $url = $r['dados']['profilePictureURL'] ?? ($r['dados']['url'] ?? null);
        return ['ok' => true, 'url' => $url ?: null, 'chat_id' => $chatId, 'existe' => true];
    }

    private static function chamar(string $caminho, array $query = [], ?array $corpo = null): array
    {
        $c = self::config();
        if (!self::configurado()) {
            return ['ok' => false, 'erro' => 'O WhatsApp ainda não está configurado no InvenTour.'];
        }
        $ch = curl_init(rtrim($c['url'], '/') . $caminho . ($query ? '?' . http_build_query($query) : ''));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TEMPO_LIMITE,
            CURLOPT_CONNECTTIMEOUT => 5,
            // O Apache na frente do WAHA recusa com 403 quem não manda
            // User-Agent, e o cURL do PHP não manda nenhum (lição do Orbit).
            CURLOPT_USERAGENT      => 'InvenTour (app.inventour.com.br)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Content-Type: application/json', 'X-Api-Key: ' . $c['api_key']],
        ]);
        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo ?: new stdClass(), JSON_UNESCAPED_UNICODE));
        }
        $resposta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $falha = curl_error($ch);
        curl_close($ch);
        if ($resposta === false) {
            return ['ok' => false, 'erro' => 'Não consegui falar com o WhatsApp: ' . $falha];
        }
        if ($codigo === 401) {
            return ['ok' => false, 'codigo' => 401, 'erro' => 'A chave do WhatsApp foi recusada.'];
        }
        if ($codigo < 200 || $codigo >= 300) {
            return ['ok' => false, 'codigo' => $codigo, 'erro' => 'O WhatsApp respondeu ' . $codigo . '.'];
        }
        $dados = json_decode((string) $resposta, true);
        return ['ok' => true, 'dados' => is_array($dados) ? $dados : []];
    }

    /** Um GET binário (QR, foto): no WAHA com a chave, ou externo sem ela. */
    private static function binario(string $caminhoOuUrl, string $tipoPadrao, bool $doWaha = true): array
    {
        $c = self::config();
        if ($doWaha && !self::configurado()) {
            return ['ok' => false, 'erro' => 'O WhatsApp ainda não está configurado no InvenTour.'];
        }
        $ch = curl_init($doWaha ? rtrim($c['url'], '/') . $caminhoOuUrl : $caminhoOuUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TEMPO_LIMITE,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'InvenTour (app.inventour.com.br)',
            CURLOPT_HTTPHEADER     => $doWaha ? ['X-Api-Key: ' . $c['api_key']] : [],
        ]);
        $corpo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $tipo = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($corpo === false || $codigo < 200 || $codigo >= 300 || !str_starts_with($tipo ?: $tipoPadrao, 'image/')) {
            return ['ok' => false, 'erro' => 'A imagem não veio agora (' . $codigo . ').'];
        }
        return ['ok' => true, 'tipo' => $tipo ?: $tipoPadrao, 'dados' => (string) $corpo];
    }
}
