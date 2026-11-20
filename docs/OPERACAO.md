# InvenTour: manual de operação

Para quem cuida do InvenTour no dia a dia (equipe Softsul). Escrito em
20/11/2026, na fundação. Produto Softsul Software e Zapping Consultoria.

## 1. Onde está cada coisa

| Peça | Onde |
| --- | --- |
| Painel | `https://app.inventour.com.br` (DocumentRoot `public/`) |
| Portal de cada destino | `https://<slug>.inventour.com.br` ou domínio próprio (DocumentRoot `portal/`) |
| Código | `/home/www/app.inventour.com.br`, repositório `softsul/InvenTour` no GitHub (chave de deploy `~/.ssh/deploy_inventour`, alias `github-inventour`) |
| Banco | MySQL 8, banco `inventour`; usuários `inventour_app` (painel e fila) e `inventour_portal` (portal, permissões em `docs/servidor/mysql-usuario-portal.sql`) |
| Configuração | `app/config.php` (fora do Git, dono `ubuntu:www-data`, modo 640) |
| Registros | `logs/seguranca.log` (lido pelo fail2ban, jail `inventour`); erros do PHP em `/var/log/apache2/*inventour*_error.log` |
| Servidor | o mesmo do EventCity: AWS EC2 us-east-1, Ubuntu 24.04, Apache 2.4 com mod_php 8.3 |
| Cópias de configuração | `docs/servidor/` (vhosts, timer da fila, fail2ban, usuário do portal) |

## 2. DNS e certificados

A zona `inventour.com.br` está no Route 53 desde 20/11/2026, com o curinga
`*.inventour.com.br` apontando para este servidor; o Registro.br é só o
registrador. Detalhes, nameservers, política IAM e o comando do certificado
em `docs/servidor/route53/README.md`.

- Painel e portais usam o mesmo certificado curinga `inventour.com.br`
  (`*.inventour.com.br`, Google Trust Services, DNS-01), que renova sozinho.
- Destino novo não exige DNS nem certificado.
- O site de lançamento `inventour.com.br` e o `www` continuam no CentOS 8.

## 3. O que roda sozinho (fila)

`inventour-fila.timer` chama `scripts/processar_jobs.php` a cada minuto, como
`www-data`.

| Job | Quando | O que faz |
| --- | --- | --- |
| `faxina` | 03:00 | tentativas de login (2 dias), limites (1 dia), jobs concluídos (30 dias), cache velho |
| `importar_cadastur` | domingo, 04:00 | planilha nova do Cadastur, quando houver |
| `importar_mapa_turismo` | dia 1, 05:00 | regiões e categorias do Mapa do Turismo |

Rodar na hora: `sudo -u www-data php scripts/processar_jobs.php`.

## 4. Publicar uma versão

```bash
cd /home/www/app.inventour.com.br
git pull
sudo -u www-data php scripts/migrar.php
php scripts/conferir_telas.php
sudo -u www-data php scripts/conferir_escopo.php
```

## 5. Novo destino

1. Plataforma, Destinos, novo: nome, CNPJ (a Receita preenche), município,
   subdomínio (o mesmo slug do EventCity, quando o destino estiver lá).
2. Convidar o gestor (Configurações, Usuários).

## 6. Primeiro acesso da equipe Softsul

```bash
sudo -u www-data php scripts/criar_usuario.php "Nome Completo" email@softsul.com
```

Imprime um link de uso único, válido por 7 dias, para a pessoa escolher a
senha e cadastrar o autenticador.
