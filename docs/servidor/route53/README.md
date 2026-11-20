# DNS do inventour.com.br no Route 53

Softsul Software, 20/11/2026. Mesmo modelo do eventcity.com.br
(`docs/servidor/route53` do EventCity), a pedido do Luiz.

O Registro.br continua sendo o registrador; a zona vive no Route 53
(hosted zone `Z050184266MTTSZZLJML`), porque o InvenTour precisa do curinga
`*.inventour.com.br` (cada destino ganha o portal sem mexer em DNS) e do
certificado curinga, que só sai pelo desafio DNS-01.

Nameservers (gravados no Registro.br em 20/11/2026):

```
ns-1594.awsdns-07.co.uk
ns-87.awsdns-10.com
ns-861.awsdns-43.net
ns-1201.awsdns-22.org
```

## Arquivos

- `inventour.com.br.zone`: a zona importada no console (site e www no
  CentOS 8, painel e curinga neste servidor, CAA).
- `politica-iam-certbot.json`: a política `eventcity-certbot-dns` inteira,
  como ficou depois de 20/11/2026. É uma política só para os dois sistemas,
  porque o certbot lê apenas o perfil `[default]` de `/root/.aws/credentials`
  (usuário IAM `eventcity-certbot`). O bloco `SoODesafioDoCertificadoInvenTour`
  libera só o TXT `_acme-challenge.inventour.com.br` desta zona: mesmo vazada,
  a chave não aponta o domínio para outro lugar. Combinado com a sessão do
  EventCity, que guarda a mesma cópia no repositório dela. A política está
  na versão 4 de 5 permitidas: na próxima edição, apagar uma versão antiga.

## Certificado

Um certificado curinga serve o painel e todos os portais:

```bash
sudo certbot certonly --dns-route53 --cert-name inventour.com.br -d '*.inventour.com.br' --server https://dv.acme-v02.api.pki.goog/directory --key-type ecdsa
```

Renova sozinho pelo timer do certbot. O domínio raiz `inventour.com.br` (site
de lançamento) continua no CentOS e não entra neste certificado.

## Destino novo

Nada a fazer em DNS: o curinga já aponta para este servidor e o Tenant acha
o destino pelo host. Domínio próprio de prefeitura é CNAME criado pela
prefeitura para `<slug>.inventour.com.br`, mais certificado individual
HTTP-01 (certbot com webroot `/var/www/acme`) no vhost do portal.

## Custo

Zona hospedada: US$ 0,50 por mês, mais US$ 0,40 por milhão de consultas.
Aceito pelo Luiz ao pedir a mudança para a AWS (20/11/2026).
