<?php
/**
 * Fale conosco: a mensagem que o público manda pelo portal ou pelo site.
 * Softsul Software
 *
 * Nada de mailto: em nenhuma tela (decisão do Luiz): o link abre o programa
 * de e-mail de quem visita, quando abre, e expõe o endereço a robôs. Toda
 * conversa começa num modal com reCAPTCHA, e a mensagem chega por e-mail
 * (SES) a quem atende, com o Responder apontando para quem escreveu.
 *
 * Por enquanto tudo vai para contato.email no config.php (luiz@softsul.com).
 */
declare(strict_types=1);
require_once __DIR__ . '/Email.php';
require_once __DIR__ . '/Recaptcha.php';
require_once __DIR__ . '/Limite.php';
require_once __DIR__ . '/svg.php';

final class Contato
{
    /** assunto => rótulo, na ordem do combo. */
    public const ASSUNTOS = [
        'duvida'         => 'Dúvida sobre o destino',
        'evento'         => 'Quero incluir o meu negócio no guia',
        'acessibilidade' => 'Acessibilidade',
        'correcao'       => 'Correção de informação',
        'lancamento'     => 'Quero acompanhar o lançamento',
        'demonstracao'   => 'Quero uma demonstração',
        'outro'          => 'Outro assunto',
    ];

    /**
     * Confere e envia. $dados: nome, email, assunto, mensagem, pagina,
     * g-recaptcha-response e o campo isca "site". $onde: de que portal ou
     * site veio. Devolve null quando enviou, ou o motivo para mostrar.
     */
    public static function enviar(array $dados, string $onde, string $produto = 'InvenTour'): ?string
    {
        // Isca: campo escondido que gente não vê e robô preenche. Responde
        // como se tivesse enviado, para o robô não aprender.
        if (trim((string) ($dados['site'] ?? '')) !== '') {
            return null;
        }
        if (!Recaptcha::configurado()) {
            return 'O formulário de contato ainda está sendo ativado. Tente de novo em breve.';
        }
        $nome = trim(preg_replace('/\s+/u', ' ', (string) ($dados['nome'] ?? '')) ?? '');
        $email = trim((string) ($dados['email'] ?? ''));
        $assunto = (string) ($dados['assunto'] ?? '');
        $mensagem = trim(str_replace("\r\n", "\n", (string) ($dados['mensagem'] ?? '')));
        $pagina = mb_substr(trim((string) ($dados['pagina'] ?? '')), 0, 300);
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
            return 'Digite o seu nome.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
            return 'Digite um e-mail válido, para podermos responder.';
        }
        if (!isset(self::ASSUNTOS[$assunto])) {
            $assunto = 'outro';
        }
        // "Avise-me no lançamento" (site) não precisa de texto: o pedido é o recado.
        if ($assunto === 'lancamento' && $mensagem === '') {
            $mensagem = 'Quero ser avisado(a) no lançamento do ' . $produto . '.';
        }
        if (mb_strlen($mensagem) < 10) {
            return 'Escreva a sua mensagem.';
        }
        if (mb_strlen($mensagem) > 4000) {
            return 'A mensagem passa de 4.000 caracteres. Resuma um pouco.';
        }
        if (!Recaptcha::conferir((string) ($dados['g-recaptcha-response'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? null)) {
            return 'Marque a caixa "Não sou um robô" e tente de novo.';
        }
        $para = (string) ((require __DIR__ . '/config.php')['contato']['email'] ?? '');
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return 'O contato ainda não está configurado. Tente de novo em breve.';
        }
        $rotulo = self::ASSUNTOS[$assunto];
        $linhas = '<p><b>De:</b> ' . e($nome) . ' &lt;' . e($email) . '&gt;<br>'
                . '<b>Assunto:</b> ' . e($rotulo) . '<br>'
                . '<b>Veio de:</b> ' . e($onde) . ($pagina !== '' ? '<br><b>Página:</b> ' . e($pagina) : '') . '<br>'
                . '<b>Quando:</b> ' . date('d/m/Y H:i') . '<br>'
                . '<b>IP:</b> ' . e(Limite::ipAgrupado()) . '</p>'
                . Email::paragrafos($mensagem)
                . '<p style="color:#6B7280;font-size:13px">Para responder, use o Responder do seu e-mail: vai direto para quem escreveu.</p>';
        try {
            Email::enviar($para, '[' . $produto . '] ' . $rotulo . ' · ' . $nome, Email::molde('Mensagem pelo ' . $onde, $linhas), null, $email);
        } catch (Throwable $e) {
            error_log('contato: ' . $e->getMessage());
            return 'Não conseguimos enviar agora. Tente de novo em alguns minutos.';
        }
        return null;
    }

    /**
     * O modal do Fale conosco (portal). Abre por qualquer [data-abrir-contato];
     * data-assunto escolhe o assunto. O reCAPTCHA só carrega quando o modal
     * abre, para quem não escreve não baixar nada do Google.
     */
    public static function modal(string $acao = '/api/contato'): string
    {
        $opcoes = '';
        foreach (self::ASSUNTOS as $k => $r) {
            // Lançamento e demonstração são do site institucional, não do portal.
            if ($k === 'lancamento' || $k === 'demonstracao') {
                continue;
            }
            $opcoes .= '<option value="' . $k . '">' . e($r) . '</option>';
        }
        $chave = Recaptcha::chaveDoSite();
        return '<dialog class="contato-modal" id="modal-contato" aria-labelledby="contato-titulo">'
             . '<form method="post" action="' . e($acao) . '" class="contato-form" novalidate>'
             . '<div class="contato-cab"><h2 id="contato-titulo">Fale conosco</h2>'
             . '<button type="button" class="contato-fechar" data-fechar-contato aria-label="Fechar">&times;</button></div>'
             . '<p class="contato-sub">Respondemos no e-mail que você informar.</p>'
             . '<label for="contato-nome">Nome</label><div class="entrada"><input id="contato-nome" name="nome" autocomplete="name" maxlength="120" required></div>'
             . '<label for="contato-email">E-mail</label><div class="entrada"><input id="contato-email" name="email" type="email" autocomplete="email" maxlength="160" required></div>'
             . '<label for="contato-assunto">Assunto</label><div class="entrada"><select id="contato-assunto" name="assunto">' . $opcoes . '</select></div>'
             . '<label for="contato-mensagem">Mensagem</label><div class="entrada"><textarea id="contato-mensagem" name="mensagem" rows="5" maxlength="4000" required></textarea></div>'
             . '<div class="contato-isca" aria-hidden="true"><label for="contato-site">Não preencha</label><input id="contato-site" name="site" tabindex="-1" autocomplete="off"></div>'
             . '<input type="hidden" name="pagina" value="">'
             . '<div class="contato-captcha" data-chave="' . e($chave) . '"></div>'
             . '<p class="contato-aviso" role="alert" hidden></p>'
             . '<div class="contato-acoes"><button type="button" class="contato-cancelar" data-fechar-contato>Cancelar</button>'
             . '<button type="submit" class="contato-enviar">Enviar mensagem</button></div>'
             . '</form></dialog>';
    }
}
