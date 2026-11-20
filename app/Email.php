<?php
/**
 * Envio de e-mail por SMTP autenticado.
 * Softsul Software
 *
 * Cliente SMTP próprio, como no Orbit: mail() entrega ao sendmail local, que
 * não existe neste servidor e não autentica em lugar nenhum.
 */
declare(strict_types=1);

final class Email
{
    public static function configurado(): bool
    {
        $m = (require __DIR__ . '/config.php')['mail'] ?? [];
        return ($m['smtp_host'] ?? '') !== '' && ($m['smtp_usuario'] ?? '') !== '' && ($m['smtp_senha'] ?? '') !== ''
            && self::remetente($m) !== '';
    }

    /**
     * O endereço que aparece como remetente. No Google Workspace o usuário
     * SMTP é o próprio endereço; no Amazon SES o usuário é uma chave
     * (AKIA...), e o remetente vem de remetente_email, de um domínio
     * verificado no SES. Sem nenhum dos dois, o envio fica desligado.
     *
     * @param array<string,mixed> $m
     */
    private static function remetente(array $m): string
    {
        foreach ([(string) ($m['remetente_email'] ?? ''), (string) ($m['smtp_usuario'] ?? '')] as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }
        return '';
    }

    /**
     * O molde de todo e-mail que sai do InvenTour. Tabela e estilo em
     * atributo porque cliente de e-mail ignora folha de estilo.
     *
     * @param array{texto:string,url:string}|null $botao
     */
    public static function molde(string $titulo, string $corpo, ?array $botao = null): string
    {
        $marca = '#182633';
        $tinta = '#182633';
        $fraca = '#5A6673';
        $cfg   = require __DIR__ . '/config.php';
        // PNG só aqui: a maioria dos leitores de e-mail não mostra SVG. O
        // arquivo fica com os demais assets, servido pelo painel.
        $logo  = rtrim($cfg['app']['url'], '/') . '/assets/logo-email.png';
        $nome  = (string) ($cfg['app']['nome'] ?? 'InvenTour');
        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
              . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($titulo) . '</title></head>'
              . '<body style="margin:0;padding:0;background:#F2F4F6;">'
              . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F2F4F6;padding:28px 12px;"><tr><td align="center">'
              . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:560px;max-width:100%;background:#FFFFFF;'
              . 'border-radius:14px;border:1px solid #D8DDE2;font-family:Open Sans,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
              . '<tr><td style="padding:18px 28px;background:' . $marca . ';border-radius:14px 14px 0 0;"><img src="' . e($logo) . '" width="150" alt="' . e($nome) . '" style="display:block;border:0;width:150px;height:auto;"></td></tr>'
              . '<tr><td style="padding:16px 28px 0;"><h1 style="margin:0;font-size:19px;line-height:1.3;color:' . $tinta . ';">' . e($titulo) . '</h1></td></tr>'
              . '<tr><td style="padding:12px 28px 0;font-size:15px;line-height:1.6;color:' . $tinta . ';">' . $corpo . '</td></tr>';
        if ($botao) {
            $html .= '<tr><td style="padding:22px 28px 0;"><table role="presentation" cellpadding="0" cellspacing="0"><tr>'
                  . '<td style="border-radius:10px;background:' . $marca . ';"><a href="' . e($botao['url']) . '" '
                  . 'style="display:inline-block;padding:12px 22px;font-size:15px;font-weight:700;color:#FFFFFF;text-decoration:none;">'
                  . e($botao['texto']) . '</a></td></tr></table>'
                  . '<p style="margin:10px 0 0;font-size:12px;color:' . $fraca . ';">Se o botão não funcionar, copie este endereço:<br>'
                  . e($botao['url']) . '</p></td></tr>';
        }
        return $html . '<tr><td style="padding:24px 28px 26px;"><div style="border-top:1px solid #D8DDE2;padding-top:14px;font-size:12px;color:' . $fraca . ';">'
              . e($nome) . '. Produto <a href="https://softsul.com" style="color:' . $fraca . ';">Softsul Software</a> e <a href="https://azapping.com.br" style="color:' . $fraca . ';">Zapping Consultoria</a>.</div></td></tr></table></td></tr></table></body></html>';
    }

    public static function paragrafos(string $texto): string
    {
        $html = '';
        foreach (preg_split("/\n{2,}/", trim($texto)) ?: [] as $bloco) {
            $html .= '<p style="margin:0 0 12px;">' . nl2br(e(trim($bloco))) . '</p>';
        }
        return $html;
    }

    /**
     * Envia uma mensagem. Lança RuntimeException com o que o servidor respondeu.
     *
     * $extra (opcional): 'ics' => texto iCalendar e 'metodo' => REQUEST ou
     * CANCEL, que vira convite (o Gmail e o Outlook mostram Sim, Não e
     * Talvez); 'descadastro' => URL de saída em um clique (List-Unsubscribe).
     */
    public static function enviar(string $para, string $assunto, string $html, ?string $paraNome = null, ?string $responderA = null, array $extra = []): void
    {
        $a = (require __DIR__ . '/config.php')['mail'];
        if (!self::configurado()) {
            throw new RuntimeException('O envio de e-mail ainda não está configurado.');
        }
        // Endereço que não é e-mail válido não chega ao servidor: um \r\n
        // no destinatário viraria comando SMTP ou cabeçalho injetado.
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('endereço de destino inválido');
        }
        // Devolução definitiva ou reclamação: não insiste (SesAvisos).
        require_once __DIR__ . '/SesAvisos.php';
        if (SesAvisos::bloqueado($para)) {
            throw new RuntimeException('endereço bloqueado: ' . (SesAvisos::motivo($para) ?? 'devolução ou reclamação'));
        }
        $de = self::remetente($a);
        $contexto = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $con = @stream_socket_client($a['smtp_host'] . ':' . (int) $a['smtp_porta'], $erro, $recado, 20, STREAM_CLIENT_CONNECT, $contexto);
        if (!$con) {
            throw new RuntimeException("não consegui falar com {$a['smtp_host']}: {$recado}");
        }
        stream_set_timeout($con, 20);
        $ler = function () use ($con): string {
            $tudo = '';
            while (($linha = fgets($con, 515)) !== false) {
                $tudo .= $linha;
                if (strlen($linha) < 4 || $linha[3] !== '-') {
                    break;
                }
            }
            return $tudo;
        };
        $dizer = function (string $comando, string $esperado) use ($con, $ler): void {
            fwrite($con, $comando . "\r\n");
            $r = $ler();
            if (!str_starts_with($r, $esperado)) {
                fclose($con);
                // O comando some do erro quando carrega credencial.
                $mostrar = str_starts_with($comando, 'AUTH') || strlen($comando) > 200 ? '(credencial)' : $comando;
                throw new RuntimeException("SMTP recusou {$mostrar}: " . trim($r));
            }
        };
        $ler();
        $eu = gethostname() ?: 'app.inventour.com.br';
        $dizer("EHLO {$eu}", '250');
        $dizer('STARTTLS', '220');
        if (!stream_socket_enable_crypto($con, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($con);
            throw new RuntimeException('não consegui iniciar o TLS com o servidor de e-mail');
        }
        $dizer("EHLO {$eu}", '250');
        $dizer('AUTH LOGIN', '334');
        $dizer(base64_encode($a['smtp_usuario']), '334');
        $dizer(base64_encode($a['smtp_senha']), '235');
        $dizer('MAIL FROM:<' . $de . '>', '250');
        $dizer('RCPT TO:<' . $para . '>', '250');
        $dizer('DATA', '354');
        $dominio = substr((string) strrchr($de, '@'), 1) ?: 'inventour.com.br';
        $cabeca = [
            'Date: ' . date('r'),
            'From: ' . self::codificar((string) $a['remetente_nome']) . ' <' . $de . '>',
            'To: ' . ($paraNome ? self::codificar($paraNome) . ' <' . $para . '>' : $para),
            'Subject: ' . self::codificar($assunto),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $dominio . '>',
            'MIME-Version: 1.0',
        ];
        $ics = (string) ($extra['ics'] ?? '');
        if ($ics === '') {
            $cabeca[] = 'Content-Type: text/html; charset=UTF-8';
            $cabeca[] = 'Content-Transfer-Encoding: base64';
            $corpo = chunk_split(base64_encode($html));
        } else {
            // Convite: o HTML e o calendário como alternativas (é assim que
            // o cliente de e-mail mostra os botões de resposta) e o .ics
            // também anexo, para quem abre noutro programa.
            $metodo = ($extra['metodo'] ?? 'REQUEST') === 'CANCEL' ? 'CANCEL' : 'REQUEST';
            $misto = 'mx' . bin2hex(random_bytes(8));
            $alt = 'al' . bin2hex(random_bytes(8));
            $cabeca[] = 'Content-Type: multipart/mixed; boundary="' . $misto . '"';
            $corpo = "--{$misto}\r\nContent-Type: multipart/alternative; boundary=\"{$alt}\"\r\n\r\n"
                   . "--{$alt}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
                   . "--{$alt}\r\nContent-Type: text/calendar; charset=UTF-8; method={$metodo}\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($ics))
                   . "--{$alt}--\r\n"
                   . "--{$misto}\r\nContent-Type: application/ics; name=\"convite.ics\"\r\nContent-Disposition: attachment; filename=\"convite.ics\"\r\n"
                   . "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($ics))
                   . "--{$misto}--";
        }
        $saida = (string) ($extra['descadastro'] ?? '');
        if (str_starts_with($saida, 'https://')) {
            $cabeca[] = 'List-Unsubscribe: <' . $saida . '>';
            $cabeca[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
        }
        // Quem escreveu pelo Fale conosco: o Responder vai direto para ele.
        $responder = (string) ($responderA ?? ($a['responder_para'] ?? ''));
        if (filter_var($responder, FILTER_VALIDATE_EMAIL)) {
            $cabeca[] = 'Reply-To: ' . $responder;
        }
        // Conjunto de configuração do SES: é por ele que devolução e
        // reclamação de spam voltam para o sistema.
        $conjunto = (string) ($a['ses_configuracao'] ?? '');
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $conjunto)) {
            $cabeca[] = 'X-SES-CONFIGURATION-SET: ' . $conjunto;
        }
        $mensagem = implode("\r\n", $cabeca) . "\r\n\r\n" . $corpo;
        fwrite($con, $mensagem . "\r\n.\r\n");
        $r = $ler();
        if (!str_starts_with($r, '250')) {
            fclose($con);
            throw new RuntimeException('o servidor não aceitou a mensagem: ' . trim($r));
        }
        fwrite($con, "QUIT\r\n");
        fclose($con);
    }

    private static function codificar(string $texto): string
    {
        $texto = str_replace(["\r", "\n"], ' ', $texto);
        return preg_match('/[\x80-\xFF]/', $texto) ? '=?UTF-8?B?' . base64_encode($texto) . '?=' : $texto;
    }
}
