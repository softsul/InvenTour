<?php
/**
 * Configuração central do InvenTour.
 * Softsul Software
 *
 * O arquivo real é app/config.php, que não vai para o repositório e é 640.
 * Fica em app/, fora dos dois DocumentRoots (public/ e portal/), e o vhost
 * ainda nega /app/ como defesa em profundidade.
 *
 * O que estiver em `ajustes` no banco vale por cima do que está aqui: este
 * arquivo é o valor de partida de uma instalação nova.
 */

return [
    // Painel e fila: leitura e escrita em tudo.
    'db' => [
        'host'    => '127.0.0.1',
        'nome'    => 'inventour',
        'usuario' => 'inventour_app',
        'senha'   => 'COLOQUE_A_SENHA_AQUI',
        'charset' => 'utf8mb4',
    ],

    // Portal público: só leitura nas tabelas públicas e escrita só nas
    // métricas. Uma falha no portal não alcança usuários nem auditoria.
    'db_portal' => [
        'host'    => '127.0.0.1',
        'nome'    => 'inventour',
        'usuario' => 'inventour_portal',
        'senha'   => 'COLOQUE_A_SENHA_AQUI',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        'nome'                 => 'InvenTour',
        'url'                  => 'https://app.inventour.com.br',
        // Domínio raiz do portal público: cada destino responde em
        // <slug>.inventour.com.br, ou no domínio próprio cadastrado.
        'dominio_portal'       => 'inventour.com.br',
        'ambiente'             => 'producao',
        // Mesmos critérios do Orbit e do Centurion: 8 horas sem uso derrubam
        // a sessão, e 5 erros por e-mail (ou 20 por IP) em 15 minutos bloqueiam.
        'timeout_sessao_min'   => 480,
        'max_tentativas_login' => 5,
        'bloqueio_login_min'   => 15,
        // 32 bytes em hexadecimal: php -r 'echo bin2hex(random_bytes(32));'
        // Trocar esta chave torna ilegível o que já foi cifrado com ela,
        // inclusive os segredos do autenticador de todo mundo.
        'chave_secreta'        => 'GERE_COM_php_-r_echo_bin2hex_random_bytes_32',
    ],

    'caminhos' => [
        // Pasta de sessão própria: gc_maxlifetime é global do servidor, e
        // dividir a pasta com outro site deixa o mais apressado derrubar a
        // sessão de todos.
        'sessoes' => __DIR__ . '/../storage/sessoes',
        'tmp'     => __DIR__ . '/../storage/tmp',
        // Fotos de pessoas ficam fora do DocumentRoot e são servidas por
        // /foto, que exige login e confere o vínculo com o destino.
        'fotos'   => __DIR__ . '/../storage/fotos',
        // Mídias públicas dos eventos (capa, galeria, cards gerados): o
        // Apache serve direto por Alias /midia/, sem passar pelo PHP.
        'midias'  => __DIR__ . '/../storage/midias',
        // Cache de página do portal, por host e rota.
        'cache'   => __DIR__ . '/../storage/cache',
        'logs'    => __DIR__ . '/../logs',
    ],

    // Consulta de CNPJ e de CEP, feita pelo servidor (o navegador não fala
    // com terceiros: CSP). BrasilAPI primeiro, ReceitaWS de reserva para CNPJ.
    'cnpj' => [
        'brasilapi' => 'https://brasilapi.com.br/api/cnpj/v1/',
        'receitaws' => 'https://receitaws.com.br/v1/cnpj/',
        'timeout_s' => 12,
    ],
    'cep' => [
        'brasilapi' => 'https://brasilapi.com.br/api/cep/v2/',
        'timeout_s' => 8,
    ],

    // WAHA: o servidor de WhatsApp da casa. Sem api_key, as notificações por
    // WhatsApp só avisam que não estão configuradas; o e-mail continua.
    // WAHA: por enquanto a sessão da Softsul, só para consultas (número tem
    // WhatsApp e foto do perfil). É o número que a equipe e os parceiros têm
    // nos contatos: quem mostra a foto só para contatos aparece por ela, e
    // não aparecia pela do Canta Caroba. Envio de mensagem fica para a
    // sessão própria do InvenTour, quando ela existir.
    'waha' => [
        'url'     => 'https://chat.venatio.com.br/api',
        'sessao'  => 'softsul',
        'api_key' => '',
    ],

    // Entrada com certificado digital A1, pelo certificado.softsul.com do
    // Orbit. O segredo é o mesmo gravado no Orbit para o sistema 'inventour'
    // e nunca trafega por chat ou e-mail. 'ligado' só vira true depois de o
    // Orbit cadastrar o InvenTour.
    'certificado' => [
        'ligado'  => false,
        'segredo' => '',
    ],

    // E-mail transacional pelo Amazon SES, em São Paulo, com o domínio
    // inventour.com.br verificado (docs/servidor/route53/ses-inventour.md).
    // Usuário e senha SMTP se gravam com php scripts/configurar_smtp.php,
    // que pergunta sem mostrar. Sem eles, convite e "esqueci a senha"
    // continuam funcionando: o link aparece na tela para mandar por WhatsApp.
    // Fale conosco do portal e do site: por enquanto tudo para o Luiz.
    'contato' => [
        'email' => 'luiz@softsul.com',
    ],

    // reCAPTCHA v2 (caixa "Não sou um robô"), domínio inventour.com.br (vale
    // para os subdomínios). Sem as duas chaves o contato não envia.
    // Gerar em https://www.google.com/recaptcha/admin.
    'recaptcha' => [
        'site_key'   => '',
        'secret_key' => '',
    ],

    'mail' => [
        'smtp_host'        => 'email-smtp.sa-east-1.amazonaws.com',
        // Tópico do SNS que traz devoluções e reclamações (api/ses-avisos.php).
        'topico_sns'       => '',
        'smtp_porta'       => 587,
        'smtp_usuario'     => '',
        'smtp_senha'       => '',
        'remetente_nome'   => 'InvenTour',
        'remetente_email'  => 'naoresponda@inventour.com.br',
        'responder_para'   => '',
        // Só preencher depois de criar o conjunto no SES: nome que não existe
        // faz o SES recusar a mensagem.
        'ses_configuracao' => 'inventour',
    ],
];
