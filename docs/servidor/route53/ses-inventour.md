# E-mail transacional do InvenTour pelo Amazon SES

Softsul Software, 22/11/2026. Mesmo modelo do EventCity
(`docs/servidor/route53/ses-eventcity.md` de lá).

O InvenTour manda convite, redefinição de senha e avisos a partir de
`naoresponda@inventour.com.br`, pelo Amazon SES em São Paulo (sa-east-1).
O código já está pronto (app/Email.php, app/SesAvisos.php,
public/api/ses-avisos.php); sem a credencial SMTP, o convite e o "esqueci
a senha" continuam funcionando com o link na tela, para mandar pelo
WhatsApp.

## O que já está feito (22/11/2026)

1. **Identidade** `inventour.com.br` no SES de São Paulo, com Easy DKIM
   RSA 2048 e MAIL FROM `bounce.inventour.com.br` (se o MX falhar, usa o
   padrão da Amazon). Os registros foram publicados pelo próprio SES no
   Route 53: três CNAME `*._domainkey`, o MX e o TXT de SPF de `bounce`.
2. **DMARC**: TXT `_dmarc.inventour.com.br` com `"v=DMARC1; p=none;"`.
   Começa em `none` para só observar; depois de um mês limpo, pode passar
   para `p=quarantine`.
3. **Avisos de devolução e reclamação**: tópico SNS
   `arn:aws:sns:sa-east-1:605245836571:inventour-ses-avisos`, assinado por
   HTTPS em `https://app.inventour.com.br/api/ses-avisos` (confirmado
   sozinho pelo painel) e gravado em `mail.topico_sns` do config.
4. **Conjunto de configuração** `inventour`, com o destino `avisos-sns`
   (rejeições, devoluções definitivas e reclamações para o tópico acima),
   gravado em `mail.ses_configuracao`. Métricas de reputação no
   CloudWatch ficaram desligadas (custo à parte).
5. A conta do SES em São Paulo já está fora do sandbox (50 mil e-mails por
   dia, 14 por segundo), a mesma do EventCity.

## O que falta: a credencial SMTP

Fica com o Luiz, porque cria uma chave de acesso nova na AWS e a senha só
aparece uma vez:

1. Console da AWS, **Amazon SES**, região São Paulo, **Configurações
   SMTP > Criar credenciais SMTP**. O nome sugerido serve; se quiser,
   `ses-smtp-inventour`.
2. No terminal do servidor, como `ubuntu`:

   ```bash
   cd /home/www/app.inventour.com.br && php scripts/configurar_smtp.php
   ```

   O script pergunta o usuário (começa com AKIA) e a senha (sem mostrar),
   grava em `app/config.php` e manda um e-mail de teste, se pedido.
3. Conferir em Configurações > E-mail, no painel (equipe Softsul).

## Não apagar

Os CNAME de DKIM, o MX e o TXT de `bounce`, o `_dmarc` e o TXT
`google-site-verification` da raiz (Search Console, ver o README desta
pasta).
