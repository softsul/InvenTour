# InvenTour

Inventário dinâmico da oferta turística para Destinos Turísticos
Inteligentes. Painel em `app.inventour.com.br`; portal público de cada
destino em `<slug>.inventour.com.br` ou no domínio próprio da prefeitura.

Coautoria: Softsul Software (tecnologia, segurança, infraestrutura e
suporte) e Zapping Consultoria (produto, turismo e governança).

## Base

O InvenTour nasceu em 20/11/2026 com a mesma base técnica do EventCity
(`/home/www/app.eventcity.com.br`, repositório `softsul/eventcity`), que por
sua vez veio do Centurion e do Orbit: PHP 8.3 sem framework, MySQL 8, Apache
com mod_php, JavaScript puro e CSS sem build.

O que veio do EventCity: login com senha, segundo fator (TOTP), digital
(passkey) e certificado A1; multi-destino pela tabela `empresas` (visão 0 é
a plataforma Softsul); papéis e permissões por módulo, com módulo como dado;
auditoria; fila de jobs; ajustes cifrados; limites por IP; layout, tema e
regras de tela; consultas ao Cadastur e ao Mapa do Turismo; scripts de
migração e de conferência. Nada do domínio da agenda (eventos, curadoria,
artistas) foi trazido.

A integração com o EventCity (destino comum pelo código IBGE, quem é dono
de cada cadastro, API, portais e phygital) está em
`docs/INTEGRACAO-INVENTOUR.md`, cópia igual à do EventCity.

## Módulos

| Módulo | Situação |
| --- | --- |
| Fundação (acesso, destinos, usuários, auditoria, fila, consultas) | pronta |
| Inventário (fichas A, B e C do Ministério do Turismo) | a fazer |
| Coleta de campo (web app com geolocalização e fotos) | a fazer |
| Homologação pelo município | a fazer |
| Caderno oficial do inventário em PDF | a fazer |
| Web app do destino (portal) | página provisória |
| Phygital (QR nos atrativos) e BI | a fazer |

Os módulos ainda não feitos já estão cadastrados e desligados na tabela
`modulos` (migração 002).

## Estrutura

```
app/          classes e configuração (fora dos DocumentRoots)
app/vistas/   pedaços de tela reaproveitados
app/vendor/   lbuchs/WebAuthn e FPDF
public/       DocumentRoot do PAINEL (app.inventour.com.br)
portal/       DocumentRoot do PORTAL PÚBLICO (*.inventour.com.br), sem login
migracoes/    NNN-descricao.sql, imutáveis, aplicadas por scripts/migrar.php
scripts/      CLI: migrar, criar_usuario, processar_jobs, conferências, importadores
storage/      sessões, mídias, cache, fotos (www-data, fora do Git)
docs/         integração, operação e cópias da configuração do servidor
```

A operação do dia a dia está em `docs/OPERACAO.md`.

Produto Softsul Software e Zapping Consultoria.
