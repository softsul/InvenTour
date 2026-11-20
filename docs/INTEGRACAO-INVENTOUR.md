# EventCity e InvenTour: integração

Softsul Software. Documento vivo, mantido pelas duas sessões de desenvolvimento
(EventCity em `/home/www/app.eventcity.com.br`, InvenTour em
`/home/www/app.inventour.com.br`). Toda decisão que afeta o outro sistema entra
aqui antes de virar código, com data e quem decidiu.

## Os dois produtos

- **EventCity**: agenda oficial de eventos do destino (painel, curadoria,
  portal, apps, redes sociais, phygital, BI). No ar em app.eventcity.com.br.
- **InvenTour**: inventariação dinâmica da oferta turística para Destinos
  Turísticos Inteligentes. Back-office técnico (coleta de campo com
  geolocalização; o trade alimenta e o município homologa; gera o caderno
  oficial do inventário), web app oficial do destino
  (cidade.inventour.com.br, portais regionais para IGRs) e ecossistema
  phygital (QR Codes urbanos, mapa de calor, BI). Site de lançamento em
  inventour.com.br (CentOS 8).

Um depende do outro: o inventário é a base de locais, atrativos e prestadores
que o EventCity usa nos eventos, e os eventos são a agenda viva que o
InvenTour mostra no destino.

## Base comum (decisão do Luiz, 20/11/2026)

O InvenTour nasce com a mesma base do EventCity: PHP 8.3 sem framework, MySQL
8, Apache com o par de vhosts do servidor, mesma estrutura de pastas (`app/`,
`public/`, `portal/`, `migracoes/`, `scripts/`, `storage/`), mesmo layout e
regras de tela, mesmo login (senha, segundo fator, digital), multi-destino
(empresas), papéis e permissões por módulo, auditoria, fila de jobs e
padrão de commits.

## Pontos de contato a definir

1. **Destino (tenant)**: o mesmo destino nos dois sistemas. Chave comum
   (código IBGE do município? CNPJ? slug?) e quem é a fonte.
2. **Usuários**: login único entre os dois ou contas separadas com vínculo.
3. **Cadastros compartilhados**: locais e espaços, atrativos, prestadores
   (Cadastur), organizadores. Quem é dono de cada um e como o outro lê.
4. **API entre os sistemas**: autenticação (token por destino), formato,
   versão, eventos de mudança (webhook ou fila).
5. **Portal do destino**: cidade.inventour.com.br e cidade.eventcity.com.br,
   o que cada um mostra e como se ligam.
6. **Phygital e BI**: pontos de QR, leituras e métricas, sem duplicar.

## Decisões

| Data | Tema | Decisão | Quem |
|---|---|---|---|
| 20/11/2026 | Base | InvenTour com a mesma base técnica do EventCity | Luiz |
| 20/11/2026 | Lugar | InvenTour em `/home/www/app.inventour.com.br`, no mesmo servidor | Luiz (via sessão EventCity) |
| 20/11/2026 | Destino | Cada sistema tem a sua tabela `empresas`. Chave natural comum: código IBGE (`municipios.id`) para município e id da região do Mapa do Turismo para região. Slug igual nos dois (`patobranco.eventcity` e `patobranco.inventour`). Cada lado grava o id do outro (`empresas.eventcity_id` no InvenTour, `empresas.inventour_id` no EventCity, este criado pela sessão EventCity quando a API for definida) | Sessões EventCity e InvenTour |
| 20/11/2026 | Usuários | Contas separadas em cada sistema na fase 1, mesmo código de login. "Entrar pelo EventCity" com token assinado de uso único fica para a fase 2 | Sessões EventCity e InvenTour |
| 20/11/2026 | Donos dos cadastros | InvenTour é dono da oferta (categorias A, B e C do Inventário do Ministério, inclusive equipamentos para eventos e atrativos). EventCity é dono de eventos, organizadores, curadoria e artistas; o item "Eventos programados" do caderno vem do EventCity. `locais.inventour_id` e `organizadores.inventour_id` (já existem no EventCity) apontam para o InvenTour, com cópia local para um não depender do outro no ar | Sessões EventCity e InvenTour |
| 20/11/2026 | Cadastur | Cópia própria em cada sistema, com o mesmo script (`app/Cadastur.php`, `scripts/importar_cadastur.php`) | Sessões EventCity e InvenTour |
| 20/11/2026 | API | JSON assinado com HMAC, segredo por destino em `ajustes` cifrados, rotas `/api/v1/...` nos dois lados, aviso de mudança por webhook que entra na fila de jobs do outro, com reenvio. O InvenTour propõe aqui o formato do primeiro par de endpoints; a sessão EventCity implementa o lado dela | Sessões EventCity e InvenTour |
| 20/11/2026 | Phygital e BI | QR de atrativos e equipamentos no InvenTour; QR de eventos e pontos da agenda no EventCity. Cada lado guarda só as próprias leituras e troca números agregados pela API, sem duplicar leitura | Sessões EventCity e InvenTour |
| 20/11/2026 | Portais | Separados, com links cruzados: `<cidade>.inventour.com.br` mostra a oferta e uma aba Eventos lida da API do EventCity, cada evento levando ao portal do EventCity; `<cidade>.eventcity.com.br` mostra os rótulos de hospedagem, atrativo e gastronomia vindos do InvenTour, levando ao portal do InvenTour. Os apps do EventCity nas lojas seguem presos ao domínio dele | Luiz |
| 20/11/2026 | DNS do InvenTour | Zona inventour.com.br no Route 53 (Z050184266MTTSZZLJML), como o EventCity: curinga *.inventour.com.br para este servidor e certificado curinga por DNS-01; destino novo não mexe em DNS. Nameservers trocados no Registro.br em 20/11/2026. A política IAM eventcity-certbot-dns ganhou um bloco só para o TXT _acme-challenge.inventour.com.br (mesma chave do certbot); cópia da política nos dois repositórios. Substitui a decisão anterior de certificado por subdomínio | Luiz, sessões EventCity e InvenTour |
