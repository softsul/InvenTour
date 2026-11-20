# InvenTour: plano estratégico e etapas de desenvolvimento

Softsul Software e Zapping Consultoria. Escrito em 20/11/2026 a partir do
pacote de especificação do Drive (documentos 00 a 07, resumidos em
`docs/especificacao/RESUMO-DRIVE.md`), do inventário de Pato Branco 2025 e
da base já pronta do InvenTour (fundação, herdada do EventCity).

## Autoria e divisão de responsabilidades

| Quem | Papel | Decide sobre |
|---|---|---|
| Anery Junior Baggio (Zapping Consultoria) | CEO, turismólogo, especialista em DTI | Metodologia do inventário, capitulação, textos de ajuda, curadoria de tribos e experiências, diagnóstico DTI |
| Luiz Carlos Piccinin (Softsul Software) | CTO | Arquitetura, segurança, infraestrutura, integração com o EventCity, prioridades de entrega |

Toda tela leva a assinatura "Produto Softsul Software e Zapping Consultoria".

---

## 1. O que é o InvenTour, em uma página

Plataforma SaaS multi-destino de **inventariação dinâmica da oferta
turística**, inteligência territorial e portal oficial do destino.

**As dores que resolve** (pitch):
- Inventário em "modelo prancheta": coleta em papel, entrega em PDF ou Word,
  sem cruzamento, obsoleto no ano seguinte, refeito do zero a cada gestão.
- Site municipal de turismo genérico, desatualizado e que sai do ar no
  período eleitoral.

**A resposta, em cinco pilares**:
1. **Coleta e auditoria** em campo, no celular, nas fichas oficiais do
   Ministério do Turismo (categorias A, B e C), com GPS e fotos.
2. **Governança do destino** (B2G): homologação pelo município, trade
   atualizando o próprio cadastro sob moderação, BI territorial.
3. **Web app do destino** (`cidade.inventour.com.br`), alimentado pelo
   inventário homologado, sem loja de aplicativos.
4. **Phygital**: QR Codes em placas, monumentos e estabelecimentos, com
   contagem de leituras sem dado pessoal.
5. **Infraestrutura multi-destino** com custódia compartilhada com o COMTUR,
   que blinda o portal contra troca de gestão e período eleitoral.

**Como ganha escala ("Cavalo de Troia")**: o turismólogo usa de graça para
coletar e gerar a **encadernação oficial em PDF**; o PDF entregue à
prefeitura leva QR Codes que abrem uma prévia bloqueada do portal; a
prefeitura ou o COMTUR assina para manter o banco vivo e ligar o portal; o
trade passa a atualizar os próprios dados; depois vêm planos pagos do trade
e a federação regional (IGRs).

**Clientes**: prefeitura, COMTUR, IGR e estado (assinatura anual mais taxa
de implantação); trade turístico (gratuito, planos de destaque na V2);
turismólogos e consultores (usuários gratuitos e canal de venda); turista
(portal sem login).

**V1** = especificação dos Módulos 1 a 5 (núcleo, fichas, coleta,
homologação, caderno PDF, portal e phygital). **Logo depois** o Módulo 6
(BI, radar de obsolescência e diagnóstico DTI). **Módulo 7** (monetização
do trade, vouchers, federação regional, roteirizador) só com os ganchos no
banco e chaves de liga e desliga.

---

## 2. Decisões de arquitetura

### 2.1 Stack e estrutura: a mesma do EventCity

O documento 02 foi escrito para um desenvolvedor genérico (UUID, Redis,
nomes em inglês nos enums, "Orbit Core"). A tradução para o que roda na
Softsul é a mesma que o EventCity fez, e a base já está no ar:

| No documento | No InvenTour | Por quê |
|---|---|---|
| "Satélite do Orbit Core" | Sistema próprio com o núcleo copiado do EventCity (que veio do Centurion e do Orbit) | Um `git pull` atualiza tudo; nenhum sistema depende de outro estar no ar para funcionar |
| UUID e JSON para tudo | `INT UNSIGNED AUTO_INCREMENT`; JSON só onde o dado é de fato um documento (horário semanal, diff de revisão) | Padrão da casa; consultas por índice |
| Enums em inglês e maiúsculas | Catálogos e enums em português, minúsculas (`homologado`, `em_conferencia`) | Padrão da casa; o contrato com o EventCity traduz na borda |
| Redis para cache | Cache em arquivo por host e por rota em `storage/cache` | Um servidor só |
| Front-end separado (PWA SPA) | Telas PHP com HTML pronto; JavaScript puro; a coleta de campo é a única tela com modo sem conexão (service worker e IndexedDB) | Sem build; portal leve e indexável |
| Feature flags em tabela própria | `modulos.ativo` mais `ajustes` por destino, como no EventCity | Ligar recurso é dado, não código |
| PDF por biblioteca genérica | wkhtmltopdf (já no servidor, usado pelo EventCity), em blocos e unidos em disco | Caderno de centenas de páginas sem estourar memória |
| Sanitização do texto colado do Word | Allowlist com DOMDocument, imagens em base64 extraídas para o storage | Previsto no documento 02 |

Estrutura de pastas igual à do EventCity: `app/`, `public/` (painel),
`portal/` (público), `migracoes/`, `scripts/`, `storage/`, `docs/`.

### 2.2 O destino (tenant)

Tabela `empresas`, termo "destino" na interface. Já existe, com
`municipio_id` (código IBGE, a chave comum com o EventCity), `slug`,
domínio próprio e `eventcity_id`. Acrescenta:

- `tipo` passa a ser `municipio`, `regiao` (consórcio ou IGR) ou `estado`.
- `modo_operacao`: `projeto` (consultoria gratuita), `avaliacao` (30 dias de
  prévia para a prefeitura), `ativo` (licenciado), `suspenso` (inadimplente
  ou sem renovação; dado nunca é apagado, portal mostra aviso
  institucional, leitura continua para COMTUR e consultor).
- `consultor_id`: o turismólogo dono do projeto na fase de consultoria.
- Regra: só um destino `ativo` por município. Projetos de consultores
  diferentes para o mesmo município convivem em endereços provisórios
  (`proj-<id>.inventour.com.br`) até a licença; aí o vencedor assume o slug
  oficial, com mesclagem seletiva de fichas homologadas se for o caso.

**Governança** (`destino_governanca`, 1:1): órgão oficial de turismo e lei
de criação, COMTUR (nome, lei, ativo), FUMTUR, presidente do COMTUR,
brasão, marca turística, cor, e `custodia` (`consultoria`, `prefeitura`,
`comtur`, `compartilhada`). Com o COMTUR na custódia, exclusão em lote de
fichas homologadas exige dupla confirmação (gestor e presidente do COMTUR)
ou a Softsul mediante ata.

### 2.3 Acesso e papéis

O núcleo é o do EventCity (convite, segundo fator, digital, certificado,
papéis por destino, módulos como dado). Papéis do InvenTour (a migração da
etapa 1 ajusta os da fundação):

| Papel | Quem | Alcance |
|---|---|---|
| `plataforma_admin`, `plataforma_suporte` | Equipe Softsul | Todos os destinos |
| `consultor` | Turismólogo dono de um projeto | Tudo no próprio projeto, inclusive gerar o caderno; perde a administração quando o destino é licenciado (passa a gestor ou coordenador, conforme o contrato) |
| `gestor` | Secretaria ou COMTUR | Tudo no destino |
| `coordenador` | Coordenador do inventário | Confere e homologa fichas, modera revisões do trade |
| `pesquisador` | Pesquisador de campo, estagiário | Coleta; pode ficar restrito a códigos de ficha (`escopo_fichas`) |
| `leitor` | Secretarias, conselho | Consulta |
| `prestador` | Empresa do trade (só com destino `ativo`) | A ficha do próprio negócio, por reivindicação aprovada; fora do segundo fator obrigatório |

O vínculo pessoa x destino ganha `registro_profissional` (vai para a ficha
catalográfica do PDF, sem telefone nem e-mail, por LGPD).

O escopo do prestador fica na consulta, como o `Escopo` do EventCity: em
toda tela, busca do topo, comandos e contadores (lição do EventCity em
20/11/2026, quando a busca vazava nomes de outros organizadores).

### 2.4 Modelo do inventário: núcleo comum mais atributos por ficha

As 54 fichas de 2011 não viram 54 tabelas.

**Taxonomia** (global, sem destino):
- `inventario_fichas`: as 54 fichas (código `A1` a `C5` e o código fino
  `B.1.1`, nome, categoria A, B ou C, ordem na capitulação, tipo
  `pontual` ou `linear` para rodovia, ferrovia, hidrovia, trilha e rio, que
  não têm CNPJ nem horário mas têm extensão e jurisdição, e o tipo do
  EventCity: atrativo, hospedagem, gastronomia, espaço de eventos ou
  serviço de apoio).
- `inventario_tipos`: cerca de 500 tipos e subtipos das fichas (hotel,
  pousada, cachoeira, museu...), com a definição do manual de 2006 como
  texto de ajuda.

**Item** (`inventario_itens`, com `empresa_id`): uma linha por equipamento,
atrativo ou serviço inventariado, com o núcleo comum das seções 1, 2, 4,
5, 6, 7, 8 e 9 das fichas:
- identificação: nome oficial e popular, razão social, CNPJ, CADASTUR e
  validade, natureza jurídica, jurisdição, rede ou holding;
- localização: zona urbana ou rural, endereço, CEP, latitude e longitude,
  link do mapa, sinalização de acesso e turística, pontos de referência;
- contato público: telefone, WhatsApp, e-mail, site, Instagram (separado
  dos dados pessoais do responsável, que ficam em `usuarios`);
- funcionamento: horário semanal (segunda a domingo, 24 horas, feriados,
  temporada), formas de pagamento, idiomas;
- capacidade: UHs e leitos (hospedagem), assentos (gastronomia), público
  (eventos);
- estado de conservação, observações do pesquisador, referências;
- textos: descrição técnica (caderno) e descrição promocional (portal);
- `situacao` e `ultima_auditoria_em`;
- `item_pai_id`: restaurante ou salão de eventos dentro de hotel, quando
  aberto ao público (regra do manual de 2006: aparece nas duas fichas).

**Satélites do item**: acessibilidade (os 21 grupos da seção 6, com mapa
para 5 selos simples no portal), mídias (capa, ordem, legenda com crédito),
proteção legal e certificações (esfera, instrumento, ato), cadastros e
associações, equipe responsável (pesquisador e coordenador, ligados aos
usuários).

**Atributos específicos** (seção 3, "Características", de cada ficha):
`inventario_atributos` (catálogo: ficha, grupo, chave, rótulo, tipo
`texto`, `numero`, `sim_nao`, `escolha`, `multipla`, `tabela`, opções,
obrigatório no núcleo mínimo do MTur, aparece no portal, ativo no
formulário, ordem e ajuda) e `inventario_item_atributos` (valor em texto,
número ou sim e não; o tipo `tabela` guarda linhas repetidas, como linhas
de ônibus, salas de eventos e leis de tombamento). Campos anacrônicos (fax,
telex, 3G, retroprojetor) entram com `ativo = 0`.

**Ausência de resposta**: qualquer campo aceita NA (não se aplica), NI (não
informou) e NE (não existe), como manda a metodologia. Nenhum vazio sem
justificativa.

**Do destino** (sem ficha): `destino_narrativas` (apresentação, histórico,
economia do turismo, aspectos geográficos, infraestrutura básica; HTML
sanitizado; indicadores econômicos com fonte e ano) e
`destino_linha_tempo` (marcos com precisão de dia, mês ou ano, categoria e
ligação opcional a notícia).

**Diferenciais além da metodologia** (sem mexer na capitulação):
- **Tribos de consumo** (documento 07: 5 macrocategorias, 21 perfis, mais
  de 160 facilidades; lista aberta, com curadoria): o selo da tribo só
  aparece com pelo menos 2 facilidades marcadas.
- **Experiências turísticas** (os 4 Es da economia da experiência): marcar
  "possui experiência" abre um formulário próprio com narrativa, duração,
  capacidade, faixa de preço e evidências obrigatórias; só aparece no
  portal depois da curadoria (rascunho, aguardando curadoria, aprovada,
  ajuste solicitado, reprovada).

### 2.5 Situação da ficha (a esteira)

```
pre_cadastro (Cadastur, CNPJ, locais do EventCity)
   -> em_campo (rascunho do pesquisador)
   -> em_conferencia (enviada ao coordenador)
   -> homologada (vale para caderno, portal e EventCity)
   -> desatualizada (sem revisão há 180 dias ou Cadastur vencido; continua
      no portal com selo "dados em atualização" e menor prioridade)
   -> arquivada (fechou; sai do portal; leitos saem da soma do EventCity)
```

Edição do trade nunca grava direto na ficha homologada: vira uma revisão
pendente (diff campo a campo) que o coordenador aprova, devolve ou
rejeita; aprovada, aplica o diff e renova `ultima_auditoria_em`. Campos
protegidos (tipo, CNPJ, razão social, estado de conservação) sempre exigem
moderação. Trilha de auditoria por item, só de acréscimo, com GPS da coleta.

### 2.6 Coleta de campo (a única tela que funciona sem sinal)

- Tela própria do pesquisador, celular primeiro, instalável como PWA.
- Baixa as fichas atribuídas a ele; preenche e fotografa sem sinal
  (IndexedDB); ao voltar o sinal, envia em lote com um identificador por
  envio, e o servidor ignora repetição.
- Fotos reduzidas no aparelho; no servidor, o GPS do EXIF sugere a
  coordenada e o restante do EXIF é apagado; reprocessamento com GD, como
  todo upload da casa.
- Formulário gerado pelo catálogo de atributos, com a ajuda do manual de
  2006 em cada campo e as opções NA, NI e NE.

### 2.7 Caderno oficial do inventário (PDF)

- Gerado na fila, por destino, completo ou por recorte (A, B, C, códigos).
- Ordem fixa da capitulação: pré-textuais (capa, ficha catalográfica com a
  equipe técnica, sumário, narrativas, linha do tempo), categoria A,
  categoria B (com quadro de UHs e leitos), categoria C, anexos
  (experiências e resumo da agenda do EventCity).
- Editorial como o de Pato Branco 2025: omite campo vazio, mas mantém todos
  os capítulos e marca "inexistente" onde não há item.
- Mostra dado técnico (CNPJ, KVA, observações) e esconde CPF e contato
  pessoal de pesquisador.
- Código de verificação no rodapé; QR por ficha e na capa.
- No modo `projeto`: chancela "Documento estruturado via InvenTour"; QR
  abre a prévia bloqueada (foto e título, resto desfocado, convite para a
  avaliação de 30 dias). No modo `ativo`: o mesmo QR abre a ficha viva, sem
  reimprimir.
- Também exporta XLSX (resumo territorial, A, B, C, experiências) e CSV.

### 2.8 Portal do destino

`<slug>.inventour.com.br` ou domínio próprio; sem login, sem cookie, SEO
com HTML e Open Graph prontos no servidor, como o portal do EventCity.

- Pilares: Onde dormir (B1), Onde comer (B2), O que fazer (C e B6),
  Experiências, Roteiros, Agenda (EventCity), O destino (narrativas e linha
  do tempo), Notícias, Serviços (categoria A, opcional).
- Filtros por zona, acessibilidade (5 selos), tribo, faixa de preço.
- Ficha com fotos, mapa, WhatsApp com texto pronto, selos e a aba "Próximos
  eventos neste local" lida do EventCity (cache de 5 minutos, 1,5 s de
  limite; se falhar, o bloco some).
- Roteiros do gestor (permanentes, de evento, de tribo) e "Meu roteiro" do
  visitante no próprio navegador, com link para compartilhar e rota no
  Google Maps.
- Ordem orgânica: com experiência aprovada, com Cadastur válido, depois
  alfabética ou sorteio diário.
- Nunca mostra rascunho nem dado técnico.
- Modo `projeto` ou `suspenso`: portal fechado (só a prévia do QR).
- Phygital: `/qr/<apelido>` com apelido imutável, destino configurável
  (início, ficha, roteiro, agenda do dia), contagem diária sem IP e sem
  cookie, cartaz em PDF (como os pontos de QR do EventCity).

### 2.9 Inteligência e diagnóstico DTI

- Fotografia mensal do destino (itens por categoria, UHs, leitos, assentos,
  capacidade de eventos, formalização Cadastur, acessibilidade,
  conservação, experiências), imutável.
- Radar de obsolescência: diário, marca desatualizada e, com destino
  `ativo`, manda ao estabelecimento um link de uso único por 15 dias
  ("está tudo igual" renova sem trabalho do coordenador; "preciso
  atualizar" gera revisão pendente).
- Cruzamento com o EventCity: leitos do destino x público esperado dos
  eventos dos próximos 90 dias, apontando risco de falta de leitos.
- **Diagnóstico DTI** com catálogo de metodologias, porque há duas:
  - **Sebrae 2022**: 7 dimensões (tecnologia, governança, experiência,
    sustentabilidade ambiental, social e econômica, acessibilidade), níveis
    1 a 5, pesos 2, 2, 2, 2, 1, 2, 1. Gerou a nota 71,63 de Pato Branco,
    que vira teste de regressão.
  - **MTur DTI Brasil 2022 e ABNT NBR 17259**: 9 eixos, 105 requisitos,
    conformidade de 1 a 5, média mínima 3, Plano de Transformação.
  - Cada resposta com evidência, fonte e data; parte dos indicadores sai
    sozinha do InvenTour (site, PWA, QR, COMTUR, FUMTUR, Cadastur,
    experiências, acessibilidade) e do EventCity.
  - Gráfico de radar e plano de ação.
- Observatório (V1.5 ou V2): conectores para RAIS e Novo CAGED, IBGE, ANAC
  e IPARDES, no mesmo molde das importações de Cadastur e Mapa do Turismo,
  com dado municipal informado em paralelo ao oficial.

### 2.10 Integração com o EventCity

Já combinado (`docs/INTEGRACAO-INVENTOUR.md`): destino pelo código IBGE e
pelo slug, contas separadas, InvenTour dono da oferta e EventCity dono dos
eventos, HMAC com segredo por destino, aviso de mudança pela fila,
portais separados com links cruzados. Proposta do primeiro par de
endpoints, a combinar com a sessão do EventCity antes da etapa 5:

Formato combinado com a sessão do EventCity em 20/11/2026 (linha "API v1"
da tabela de decisões), implementado na etapa 5:

- Rotas no painel do EventCity: `POST /api/v1/inventour/aviso`,
  `GET /api/v1/inventour/eventos?local={inventour_id}` e
  `GET /api/v1/inventour/agenda?ibge={ibge}&de=&ate=`.
- Assinatura HMAC-SHA256 por destino nos cabeçalhos `X-Destino`,
  `X-Momento` e `X-Assinatura`, janela de 5 minutos, segredo cifrado em
  `ajustes` dos dois lados.
- Aviso em envelope com `aviso_id` (descarta repetição), `tipo` (`item` ou
  `totais`) e `versao` (descarta fora de ordem); enviado pela fila do
  InvenTour com reenvio.
- No EventCity o item vai para um espelho (`inventour_itens`); local ou
  organizador só nasce quando alguém escolhe o item num evento. Arquivar
  não apaga nada.
- Totais de leitos, UHs e capacidade de eventos alimentam o painel de
  conflitos do EventCity, ao lado do número do Cadastur.
- Eventos de um local e agenda do destino voltam só com o que o portal do
  EventCity mostra, com cache de 5 minutos dos dois lados.
- Ids numéricos nos dois lados (o documento 02 falava em UUID; o EventCity
  usa INT, então não há conversão).

### 2.11 Segurança e LGPD (desde a primeira etapa)

As mesmas da base: CSP sem `unsafe-inline` no painel, portal sem cookie,
limites por IP, `conferir_escopo` antes de cada entrega (com alvos do
prestador quando as fichas chegarem), uploads reprocessados, storage fora
do DocumentRoot, auditoria. Específicos do InvenTour: CPF de produtor rural
mascarado no PDF, contato pessoal de pesquisador fora do PDF, contatos
comerciais separados dos dados de login, EXIF apagado das fotos, dado
granular por estabelecimento (cliques de WhatsApp de cada hotel) só para o
gestor e o próprio prestador.

---

## 3. Etapas

Cada etapa termina no ar, com `conferir_telas` e `conferir_escopo` sem
falha, resumo no Início (regra do Luiz), commit e push.

### Etapa 0. Fundação (concluída em 20/11/2026)

Núcleo do EventCity, banco `inventour`, papéis e módulos iniciais,
Cadastur e Mapa do Turismo, portal provisório, DNS no Route 53 com
curinga, Pato Branco cadastrado. Falta só o certificado curinga e o
primeiro acesso do Luiz, que dependem da delegação do Registro.br.

### Etapa 1. Destino, governança e taxonomia (1 semana)

- Migração: `empresas.tipo` (município, região, estado), `modo_operacao`,
  `consultor_id`; `destino_governanca`; papéis `consultor` e `coordenador`
  (o `tecnico` da fundação vira `coordenador`).
- Ficha do destino com a aba Governança e a máquina de estados do modo de
  operação; projeto de consultoria criado pelo próprio turismólogo, em
  endereço provisório.
- Seed das 54 fichas e dos cerca de 500 tipos e subtipos, com as
  definições do manual de 2006.
- Tela Catálogos (plataforma): fichas, tipos, tribos.

### Etapa 2. Fichas do inventário no painel (2 a 3 semanas)

- `inventario_itens` e satélites (acessibilidade, mídias com arrastar e
  soltar, proteção legal, cadastros, equipe), catálogo de atributos e
  valores.
- Seed da seção 3 de cada ficha a partir do PDF de 2011, conferido com os
  formulários de 2019 (trabalho grande: começa pelas fichas de maior uso,
  B1, B2, B5, B6, C1, C2, e completa as demais na etapa 3).
- Lista do inventário por categoria, ficha e situação; ficha em modal
  (regra de tela), formulário gerado pelo catálogo, NA, NI e NE.
- Pré-cadastro em lote pelo Cadastur do município (cópia local) e por CNPJ.
- Narrativas do destino com editor e sanitização da colagem do Word; linha
  do tempo.
- Tribos e facilidades (seed do documento 07) e experiências com curadoria.

### Etapa 3. Coleta de campo e homologação (2 semanas)

- Tela do pesquisador, celular primeiro, com modo sem sinal e envio
  idempotente; fotos com GPS do EXIF.
- Esteira: em campo, em conferência, homologada; conferência pelo
  coordenador com o núcleo mínimo do MTur; trilha de auditoria.
- Escopo do pesquisador por códigos de ficha.
- Resumo no Início: fichas por situação e por categoria, fila de
  conferência no menu.

Feito em 20/11/2026: módulos Coleta de campo (/coleta, com fila no
IndexedDB, uuid por envio e service worker /sw-coleta.js) e Homologação
(/homologacao, fila com o núcleo mínimo); esteira no topo do item (enviar,
homologar, devolver com nota) em app/Esteira.php; escopo de fichas no
vínculo do usuário com o destino (usuario_empresa.escopo_fichas); números no
menu e no Início. Núcleo mínimo para homologar: tipo, endereço ou
referência, coordenada, conservação e uma foto (ficha de município só pede o
nome). A confirmar com o Baggio se o núcleo mínimo é esse.

### Etapa 4. Caderno oficial (2 semanas)

- Configuração da encadernação (capa, apresentação, equipe técnica, ano).
- Geração na fila, por blocos com wkhtmltopdf, completo ou por recorte;
  capitulação integral com "inexistente"; código de verificação; QR por
  ficha.
- XLSX e CSV.
- Prévia bloqueada do QR em modo `projeto` e pedido de avaliação de 30
  dias (contagem de leituras do QR do PDF para o funil comercial).
- Aceite: gerar o caderno de Pato Branco com fichas reais e comparar com o
  inventário 2025.

### Etapa 5. Portal do destino, phygital e ponte com o EventCity (2 a 3 semanas)

- Portal por pilares, ficha pública, roteiros, "Meu roteiro", notícias,
  narrativas, acessibilidade (barra de contraste e VLibras do EventCity),
  sitemap e Open Graph.
- Pontos de QR com cartaz e contagem diária.
- API combinada com o EventCity: aviso de item homologado e de leitos (na
  fila, com reenvio), aba de eventos na ficha, pilar Agenda; o EventCity
  cria `empresas.inventour_id` e os endpoints do lado dele.
- Links cruzados entre os portais.

### Etapa 6. Trade turístico (1 a 2 semanas)

Só para destinos `ativo`:
- Reivindicação da ficha pelo CNPJ ou Cadastur, com comprovante e termo;
  aprovação em um clique quando o Cadastur confere.
- Revisões pendentes com diff, aprovação, devolução e rejeição; transferência
  de titularidade.
- Prestador mantém horários, contatos, fotos, capacidade, tribos e submete
  experiências.

### Etapa 7. Inteligência e diagnóstico DTI (2 a 3 semanas)

- Fotografia mensal, painel executivo e relatórios no molde do Orbit.
- Radar de obsolescência com o link de uso único por WhatsApp e e-mail.
- Cruzamento de leitos com os eventos do EventCity.
- Diagnóstico DTI com as metodologias Sebrae e MTur, evidências, radar,
  plano de ação; teste de regressão da nota 71,63 de Pato Branco.

### Etapa 8. Piloto em Pato Branco e endurecimento (2 semanas)

Carga real do inventário 2025 (pré-cadastro e conferência), treinamento de
coordenador e pesquisadores, revisão de segurança e LGPD, manual de
operação, política de privacidade revisada pelo destino.

### Depois da V1 (ganchos já previstos)

Planos do trade com destaque e banners moderados pelo COMTUR, clube de
vouchers, federação regional (portal de IGR e estadual agregando destinos
`ativo`), roteirizador para o turista, Data Hub com buscas sem resultado,
observatório com RAIS, CAGED, IBGE e IPARDES. Todos entram por
`modulos.ativo` e ajustes por destino, sem refazer o banco da V1.

---

## 4. Pontos que precisam de decisão do Luiz ou do Baggio

1. **Versão das fichas**: 2011 (54 fichas, a que o pacote manda usar) com
   os campos conferidos pela versão 2019, ou 2019 direto? Proposta: códigos
   e capitulação de 2011, conferindo campos com a de 2019.
2. **Estado de conservação**: a ficha oficial tem 3 níveis (muito bom, bom,
   ruim); o documento 02 usa 4 (ótimo, bom, regular, ruim). Proposta: os 3
   oficiais, mais "não se aplica".
3. **Ficha desatualizada no portal**: some ou fica com selo? Proposta: fica,
   com selo e menor prioridade (Módulo 6), porque tirar do ar castiga o
   turista.
4. **Diagnóstico DTI**: confirmar com o Baggio como a nota 1 a 5 da fórmula
   do Sebrae vira 71,63 (a planilha só tem o total) e se o sistema começa
   pela metodologia Sebrae, pela do MTur ou pelas duas.
5. **Papel do consultor depois da licença**: continua com acesso
   administrativo, vira coordenador ou só leitura?
6. **Preço**: o pitch não traz valores; definir a taxa de implantação e a
   assinatura por porte de município antes da etapa 4 (o funil do QR
   precisa do texto do convite).
7. **Leitura pendente do Drive**: subpastas de concorrentes, metodologia,
   exemplos e "mais ideias" estão sendo lidas; o que mudar o plano entra
   aqui.
