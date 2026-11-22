# Site institucional (inventour.com.br)

Softsul Software e Zapping Consultoria. Página única de apresentação do
InvenTour para prefeituras, COMTUR, IGR, turismólogos e consultores.

- `index.php`: a página publicada (HTML e CSS em um arquivo, sem PHP).
- Onde está: servidor CentOS 8 (44.209.239.73), em
  `/home/www/inventour.com.br/index.php`, com `assets/` (icone.svg é o
  emblema, igual a `public/assets/marca/emblema.svg`). Acesso:
  `sudo ssh -i /root/chaves/centos8.pem centos@44.209.239.73`.
- Versões anteriores no CentOS: `/root/inventour-antes-20261114` (o "em
  breve") e `/root/inventour-antes-20261122` (a do Avise-me no lançamento).

Publicar:

```bash
sudo ssh -i /root/chaves/centos8.pem centos@44.209.239.73 'sudo tee /home/www/inventour.com.br/index.php >/dev/null' < docs/site-institucional/index.php
```

O logo animado da abertura é o mesmo "mapa vivo" da entrada do painel
(`explosaoDaMarca()` em app/svg.php e marca.css), com as cores ajustadas
para fundo claro. Mudou lá, muda aqui também.

O Fale conosco é um modal com reCAPTCHA v2 (chave do InvenTour, domínio
inventour.com.br) que posta em `https://app.inventour.com.br/api/contato`
com o assunto `demonstracao` ou `outro`; a mensagem chega a `contato.email`
do config. Nada de mailto. As escolhas são pílulas, sem lista nativa.
