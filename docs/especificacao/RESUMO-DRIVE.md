# Resumo dos documentos do Google Drive (InvenTour)

Pasta de origem: https://drive.google.com/drive/folders/1M23bEDVPdoYpxv9ifbPgVgFzgiKU0P2g
Leitura feita em 2026-11-20 pelo navegador (somente leitura).

## O que ficou sem ler ou foi lido parcialmente

- **06c (xlsx da nota 71,63)**: as 4 abas foram lidas por completo no complemento (seção 7.9). Não existe fórmula nem "valor intermediário por célula": o total é texto digitado. A composição exata de 71,63 continua sem reconstituição (depende do cálculo interno da ferramenta do Sebrae).
- **06e (cartilha DTI)**: PDF só com imagens, sem camada de texto; visto parcialmente.
- **03 e 04**: lidos por extração e análise de estrutura (sumários, fichas, seções, orientações), não transcritos página a página.
- **06d (ABNT)**: lido o sumário completo e a apresentação, não o texto integral de cada requisito.
- **Formulários 2019**: lidos o índice completo, A.1, A.2, B.1 e C.1.4; os demais formulários só pelo título e lista de tipos.
- **Subpastas e arquivos restantes**: abertos no complemento (seção 7). Ficaram sem leitura de conteúdo, só pelo título ou pela primeira página:
  - `Smart Tour`: `Turismo & COVID-19.pdf`, `Clipagem.pdf`, `Apresentação smart Tracking.pdf` (fora do tema).
  - `Projetos concorrentes - Centelha`: 46 PDFs lidos só pela primeira página (identificação do projeto); 068 e 066 sem identificação clara.
  - `Metodologia Inventariação`: 21 arquivos `.doc` (fichas A1 a C5 de 2011) e `Manual A/B/C.pdf` só pela lista (mesmo conteúdo dos documentos 03 e 04).
  - `Inventariação 2011` (PDFs por ficha) e `Inventariação 2006` (subpastas Formulários A, B, C e manuais a, b, c): só pela lista; duplicam 03 e 04.
  - `Exemplos`: de Ilhabela, só o sumário do Volume II; Volumes I, III, IV, V, mapa e o `.docx` do link T4 (deu erro ao abrir) não lidos. Angra: só a primeira página.
  - `EventCity`: subpastas `registro de marca`, `pitchdeck`, `papelaria`, `logo`, `master prompt` (3 prompts: Notícias, Eventos, Cards), `publicações` e `centelha` (só busca por "InvenTour", sem ocorrência).
  - `39 - CARTILHA TURISMO 360.pdf`: só apresentação e sumário (64 páginas).
  - `Catálogo de Soluções DTI Brasil`: lido o índice e a primeira linha de cada uma das ~55 fichas; detalhe só das relevantes.
- **05 Pato Branco Inventário 2025**: pulado de propósito (há cópia local).
- O documento 02 foi lido do ponto "REGRAS DE NEGÓCIO & CASOS DE BORDA" do Módulo 2 até o fim; a parte anterior já tinha sido lida.

## Índice
1. Documento 02 (Módulos 2 a 7 e anotações finais)
2. Documento 01 (Pitch Deck)
3. Documento 07 (Tribos de consumo)
4. Subpastas WebApp e InvenTour (manual da marca, formulários MTur 2019, notas)
5. Documentos metodológicos (06a a 06f, 03, 04)
6. Documento 00 (guia do pacote de especificação)
7. Complemento: subpastas e arquivos restantes (Smart Tour, concorrentes Centelha, mais ideias, EventCity, centelha, exemplos, metodologia, Canvas, Catálogo DTI, Turismo 360, abas da 06c)

---

## 1. Documento 02: Plano Estratégico, Módulos, Panorama e Funcionalidades (parte 2: Módulo 2 a partir das regras, até o fim)

Arquivo: `02 InvenTour - Plano Estratégico Módulos Panorama e Funcionalidades.docx` (ID 1_fV9LwJy7ttcIUX783vPQ3MkZZULR20w), cerca de 132.000 caracteres no total.
A primeira parte (visão geral, relatório de consistência, Módulo 1 e Módulo 2 até "REGRAS DE NEGÓCIO & CASOS DE BORDA") já tinha sido lida antes e não está aqui.

Convenções que se repetem em todos os módulos:
- Multi-tenant por `empresa_id` (FK `empresas.id`) em todas as tabelas. Tabela base de usuários da Softsul: `usuarios`.
- PKs `INT UNSIGNED AUTO_INCREMENT`, MySQL, back-end PHP 8.3 com workers assíncronos (fila de jobs).
- `empresas.modo_operacao` ENUM: `PROJETO_CONSULTORIA`, `TRIAL_B2G`, `ATIVO_B2G`, `SUSPENSO`. Derivado: `portal_publico_ativo` (1 em TRIAL_B2G e ATIVO_B2G).
- `inventario_itens.status_homologacao` (visto nos módulos): `PRE_CADASTRO_OFICIAL`, `RASCUNHO_CAMPO`, `EM_CONFERENCIA`, `HOMOLOGADO`, `DESATUALIZADO`, `ARQUIVADO`.
- Arquivos sempre em storage privado fora do DocumentRoot: `/storage/tenants/{empresa_id}/...` (reivindicacoes, entregaveis, portal, b2b).
- Webhooks para o EventCity assinados com HMAC-SHA256.
- `estado_conservacao` usa `OTIMO`, `BOM`, `REGULAR`, `RUIM`.
- Taxonomia MTur: categorias A (Infraestrutura de Apoio), B (Equipamentos e Serviços Turísticos), C (Atrativos). Fichas citadas: B1 Meios de Hospedagem, B2 Gastronomia, B5 Espaços para Eventos, B6 Lazer, C1 Naturais, C2 Culturais. Cada tipo tem `eventcity_type` (ex.: B1 = `LODGING`).

### 1.1 Módulo 2 (final): regras de negócio, casos de borda e payloads

Regras:
- **Integridade da capitulação MTur**: nenhuma customização altera os códigos raiz A, B, C nem suprime campos obrigatórios do núcleo mínimo (`mtur_atributos_catalogo.obrigatorio_mtur = 1`), para que a Encadernação Oficial (Módulo 4) seja aceita em editais, convênios e certificação DTI. Campos obsoletos da metodologia antiga (posto telefônico público, sinal 3G) ficam no catálogo com `ativo_formulario = 0` (preserva importação legada sem aparecer no formulário).
- **Sincronização de capacidade com o EventCity (zero divergência)**: quando item B1 ou B5 vira `HOMOLOGADO` ou muda `capacidade_leitos`, `capacidade_uhs` ou `capacidade_publico_eventos`, um observer dispara job assíncrono que recalcula a soma territorial do `empresa_id` e envia por webhook HMAC-SHA256 ao EventCity.
  - Caso de borda: hotel marcado `DESATUALIZADO` ou `ARQUIVADO` (reforma, encerramento) tem os leitos subtraídos imediatamente da capacidade ativa enviada, evitando falso positivo no Painel de Conflitos do EventCity.
- **Privacidade técnico x turista (LGPD)**: `observacoes_pesquisador`, `razao_social`, `cnpj` (quando MEI ou CPF equiparado), `referencias_bibliograficas` e atributos com `exibir_portal_publico = 0` (ex.: potência do gerador em KVA, tipo de esgotamento sanitário) nunca saem nos DTOs públicos do Portal; só aparecem no painel administrativo e no PDF oficial.
- **Promoção de notícia para a linha do tempo**: ao publicar notícia relevante, o gestor aciona "Adicionar à Linha do Tempo do Destino", criando registro em `destino_linha_tempo` com `noticia_origem_id`.

Payload de referência "Ficha Completa do Inventário" (API interna, painel de gestão e auditoria), estrutura:
- Raiz: `inventour_id`, `empresa_id`, `status_homologacao`.
- `taxonomia`: `categoria`, `codigo_ficha`, `tipo`, `subtipo`, `eventcity_type`.
- `identificacao`: `nome_oficial`, `razao_social`, `cnpj`, `codigo_cadastur`, `cadastur_validade`.
- `localizacao`: `zona` (URBANA/RURAL), `endereco_logradouro`, `endereco_bairro_distrito`, `latitude`, `longitude`, `google_maps_url`.
- `capacidade_instalada`: `capacidade_uhs`, `capacidade_leitos`, `capacidade_assentos_mesas`, `capacidade_publico_eventos`.
- `qualificacao_transversal`: `estado_conservacao`, `acessibilidade` (lista: `RAMPA_ACESSO`, `BANHEIRO_ADAPTADO`, `ESTACIONAMENTO_PCD`...), `idiomas_atendimento` (`PORTUGUES`, `ESPANHOL`...), `tribos_consumo` [{`tribo_slug`, `tribo_nome`, `facilidades_comprovadas` (slugs como `bancada_ferramentas`, `bomba_pressao`, `guarda_bicicletas_coberto`)}].
- `atributos_dinamicos_mtur` [{`chave_slug`, `rotulo`, `valor` (booleano, número ou lista), `exibir_portal_publico`}] (modelo EAV).
- `experiencia_turistica`: `possui_experiencia`, `experiencia_id`, `status_curadoria` (`APROVADA`...), `titulo_experiencia`, `dimensoes_4e` {`EDUCACIONAL`, `ESTETICA`, `ESCAPISMO`, `ENTRETENIMENTO`}, `duracao_minutos`, `capacidade_min_pessoas`, `capacidade_max_pessoas`, `exige_agendamento`, `faixa_valor_brl` (ex.: `DE_81_A_200`).
- `governanca_pesquisa`: `observacoes_pesquisador`, `ultima_auditoria_em`.

Payload "Narrativas Editoriais e Linha do Tempo" (API pública do portal e gerador de PDF):
- `narrativas_oficiais` [{`secao_codigo` (ex.: `ECONOMIA_TURISMO`), `titulo_exibicao`, `conteudo_html` (sanitizado), `indicadores_economicos` {`total_acts_inventariadas`, `cadastur_ativos`, `empregos_formais_turismo`, `vaf_alojamento_alimentacao_brl`, `nivel_maturidade_dti`}}].
- `linha_do_tempo` [{`id`, `data_marco`, `precisao_data` (`DIA`...), `categoria_marco` (ex.: `LEGISLACAO_GOVERNANCA`, `GRANDE_EVENTO`), `titulo`, `descricao`, `noticia_origem_id`}].

### 1.2 Módulo 3: Coleta em Campo (auditoria técnica) e Alimentação Colaborativa (crowdsourcing moderado)

Objetivo: RBAC/ABAC por destino, coleta mobile-first com offline leve (4G instável) e sincronização idempotente, reivindicação de ficha pelo trade (CNPJ/CADASTUR), trilha de auditoria e sincronização automática com o EventCity a cada homologação.

Tabelas:

**`usuarios_destino_vinculos`** (vincula `usuarios` da Softsul ao tenant)
- `id`, `empresa_id`, `usuario_id`.
- `perfil_papel` ENUM(`SOFTSUL_ADMIN`, `GESTOR_DESTINO`, `COORDENADOR_INVENTARIO`, `PESQUISADOR_CAMPO`, `REPRESENTANTE_TRADE`). Visitante não tem registro.
- `registro_profissional` VARCHAR(60) (Bacharel em Turismo, conselho, Lattes; vai para a ficha catalográfica do PDF).
- `escopo_categorias_json` JSON: restringe estagiários e bolsistas a fichas (ex.: `["B1","B2"]`); NULL = irrestrito.
- `nivel_confianca_trade` ENUM(`MODERACAO_PADRAO`, `EDICAO_EXPRESSA_PARCIAL`, `BLOQUEADO`): para REPRESENTANTE_TRADE; EDICAO_EXPRESSA_PARCIAL libera alteração direta de horários e contatos (nunca CNPJ ou taxonomia).
- `ativo` TINYINT (0 = revogado, preserva histórico de autoria).

**`inventario_reivindicacoes`** (quem responde legalmente pela ficha)
- `id`, `empresa_id`, `item_id` (FK `inventario_itens`), `usuario_solicitante_id`.
- `cnpj_informado` CHAR(18) (validado contra a ficha ou base Receita/CADASTUR), `codigo_cadastur_informado` VARCHAR(30) (acelera validação).
- `documento_comprobatorio_path` (cartão CNPJ, certificado CADASTUR, procuração) em `/storage/tenants/{empresa_id}/reivindicacoes/`.
- `aceite_termo_responsabilidade_em` DATETIME + IP do aceite (Regulamento Municipal ou Edital de Cadastramento).
- `status` ENUM(`PENDENTE`, `APROVADA`, `REJEITADA`, `REVOGADA`). Só uma APROVADA ativa por `item_id`.
- `analisado_por_usuario_id` (GESTOR_DESTINO ou COORDENADOR_INVENTARIO), `motivo_rejeicao` (obrigatório se REJEITADA).

**`inventario_revisoes_pendentes`** (propostas de alteração sem sobrescrever o dado homologado: "shadow diff")
- `id`, `empresa_id`, `item_id`, `autor_usuario_id`.
- `origem_submissao` ENUM(`CAMPO_MOBILE`, `PORTAL_TRADE`, `CAMPANHA_WHATSAPP`, `SINCRONIZACAO_OFFLINE`).
- `payload_diff_json` JSON só com os campos alterados: `{"campo": {"antes": X, "depois": Y}}`.
- `status_moderacao` ENUM(`AGUARDANDO_CONFERENCIA`, `APROVADA`, `DEVOLVIDA_AJUSTE`, `REJEITADA`).
- `observacao_moderador` (instrução quando DEVOLVIDA_AJUSTE), `moderado_por_usuario_id`, `criado_em`, `moderado_em`.

**`inventario_auditoria_logs`** (append-only, imutável, prestação de contas)
- `id`, `empresa_id`, `item_id`, `usuario_id`.
- `acao` ENUM(`CRIACAO_CAMPO`, `IMPORTACAO_OFICIAL`, `SUBMISSAO_CONFERENCIA`, `HOMOLOGACAO`, `ATUALIZACAO_TRADE`, `MARCACAO_OBSOLETO`, `ARQUIVAMENTO`).
- `latitude_coleta` DECIMAL(10,8), `longitude_coleta` DECIMAL(11,8) (prova de visita in loco).
- `client_sync_uuid` CHAR(36) (idempotência de sincronização offline), `criado_em`.

Workflows:
1. **Coleta em campo pelo turismólogo**: PESQUISADOR_CAMPO abre no celular a lista de fichas pré-carregadas (`PRE_CADASTRO_OFICIAL`, vindas de CADASTUR/CNPJ no Módulo 1) ou cria nova (`RASCUNHO_CAMPO`).
   - Offline leve: formulário e fotos comprimidas guardados no IndexedDB com `client_sync_uuid`; quando a conexão volta, um worker sincroniza; o PHP valida o uuid para idempotência.
   - EXIF das fotos lido no servidor; se a ficha não tem lat/long, sugere preencher pelo EXIF ou GPS do aparelho.
   - Júnior conclui e muda para `EM_CONFERENCIA`; COORDENADOR_INVENTARIO revisa obrigatórios MTur e ortografia e muda para `HOMOLOGADO` (`ultima_auditoria_em = NOW()`).
   - Gatilho: se `modo_operacao = ATIVO_B2G`, HOMOLOGADO dispara na hora o webhook HMAC para o EventCity (organizadores/locais + recálculo de leitos).
2. **Reivindicação e edição pelo trade (pós-venda B2G)**: na ficha pública, botão "Sou proprietário / Atualizar dados"; informa CNPJ/CADASTUR, anexa comprovante, aceita termo.
   - Atalho de confiança: se o CADASTUR informado bate exatamente com a base oficial do tenant e o domínio do e-mail ou telefone bate com o registro oficial, sinaliza "Match Oficial CADASTUR" para aprovação em 1 clique.
   - Aprovado como REPRESENTANTE_TRADE, edita horários, WhatsApp, fotos, leitos, facilidades de tribos e submete Experiências (Módulo 2).
   - Regra de ouro: portal continua mostrando a versão HOMOLOGADO; alterações ficam em `inventario_revisoes_pendentes` (AGUARDANDO_CONFERENCIA). Ao aprovar, o back-end aplica o diff em `inventario_itens`, renova `ultima_auditoria_em` (zera alerta de obsolescência do Módulo 6) e avisa o EventCity se mudou leitos ou endereço.

Regras e casos de borda:
- **Bloqueio de crowdsourcing em PROJETO_CONSULTORIA e TRIAL_B2G**: auto-cadastro e reivindicação pelo trade ficam desabilitados; só consultor e equipe escrevem. Abertura ao trade é benefício exclusivo de `ATIVO_B2G`.
- **Campos protegidos**: mesmo com EDICAO_EXPRESSA_PARCIAL, o trade nunca altera direto `taxonomia_id`, `cnpj`, `razao_social` nem `estado_conservacao` (parecer técnico exclusivo do inventariante); exigem moderação humana.
- **Troca de titularidade (venda do ponto)**: com nova reivindicação e contrato social/CNPJ atualizado, o gestor aciona "Transferir Titularidade": anterior vira REVOGADA, acesso antigo encerrado, novo ativado, histórico técnico intacto.
- **LGPD**: dados pessoais de login do trade (CPF, e-mail privado) ficam só em `usuarios`, nunca misturados com contatos comerciais públicos da ficha (`telefone_fixo`, `whatsapp`, `email_contato`). Fotos têm EXIF sensível (modelo, serial) removido após extrair o GPS e são gravadas fora do DocumentRoot.

Payloads:
- `POST /api/v1/inventario/campo/sync`: `empresa_id`, `client_sync_uuid`, `item_id`, `acao_solicitada` (ex.: `SUBMISSAO_CONFERENCIA`), `telemetria_coleta` {`latitude_dispositivo`, `longitude_dispositivo`, `precisao_metros`, `coletado_em`}, `dados_atualizados` {`zona`, `estado_conservacao`, `capacidade_uhs`, `capacidade_leitos`, `acessibilidade_json`, `observacoes_pesquisador`, `atributos_dinamicos` [{`atributo_id`, `valor_booleano` | `valor_numerico` | ...}]}.
- `GET /api/v1/moderacao/revisoes/{id}`: `revisao_id`, `status_moderacao`, `origem_submissao`, `criado_em`, `empreendimento` {`inventour_id`, `codigo_ficha`, `nome_oficial`, `cnpj`}, `autor` {`usuario_id`, `nome`, `perfil_papel`, `reivindicacao_aprovada_em`}, `alteracoes_propostas` {campo: {`antes`, `depois`, `impacta_eventcity` (bool)}} (ex.: `capacidade_leitos` e `whatsapp` impactam o EventCity; `horario_funcionamento_json` não).

### 1.3 Módulo 4: Gerador de Encadernação Oficial (PDF) e motor "Cavalo de Troia"

Objetivo: worker assíncrono que compila o banco (narrativas, linha do tempo, fichas A/B/C, experiências) na Encadernação Oficial em PDF e exporta CSV/XLSX. Estratégia comercial "Cavalo de Troia": o consultor entrega PDF gratuito com chancela InvenTour e QR Codes que apontam para o Portal (bloqueado até a ativação B2G).

Tabelas:

**`inventario_exportacoes_documentos`** (fila e histórico de gerações)
- `id`, `empresa_id`, `solicitado_por_usuario_id` (consultor, COORDENADOR_INVENTARIO ou GESTOR_DESTINO).
- `tipo_entregavel` ENUM(`ENCADERNACAO_PDF_COMPLETA`, `ENCADERNACAO_PDF_RECORTE`, `FICHA_INDIVIDUAL_PDF`, `PLANILHA_BRUTA_XLSX`, `PLANILHA_BRUTA_CSV`).
- `escopo_filtros_json` (ex.: `{"categorias":["B","C"],"codigos_ficha":["B1","C1"],"apenas_homologados":true,"incluir_experiencias":true}`).
- `modo_operacao_no_ato` ENUM (snapshot do modo comercial; define marca d'água de demonstração ou chancela oficial B2G).
- `codigo_verificacao_hash` CHAR(32) (hash truncado impresso no rodapé e ficha catalográfica para validar autenticidade).
- `total_itens_compilados`, `arquivo_path` (ex.: `/storage/tenants/{empresa_id}/entregaveis/inv_2026_42.pdf`), `tamanho_bytes` (quota).
- `status_job` ENUM(`NA_FILA`, `PROCESSANDO`, `CONCLUIDO`, `ERRO`), `mensagem_erro`, `criado_em`, `concluido_em`.

**`inventario_config_encadernacao`** (1:1 com empresa)
- `titulo_capa` VARCHAR(200) (padrão "Inventário da Oferta Turística - {Município}/{UF}"), `subtitulo_capa`, `ano_referencia` YEAR.
- `empresa_consultoria_nome`, `empresa_consultoria_logo_path` (logo do consultor na contracapa durante PROJETO_CONSULTORIA).
- `texto_apresentacao_institucional` (prefácio do Prefeito, Secretário, Presidente do COMTUR).
- `equipe_tecnica_json` (obrigatório): `[{"nome","cargo","registro"}]`.
- `exibir_qr_code_fichas` TINYINT padrão 1 (QR individual em cada ficha B e C).

**`inventario_qr_conversoes_logs`** (conversão dos QR do PDF)
- `id`, `empresa_id`, `exportacao_id` (qual versão do PDF), `item_id` (NULL = QR da capa), `modo_operacao_destino` ENUM, `solicitou_demonstracao_b2g` TINYINT (clicou em "Ativar Portal Oficial do Destino"), `criado_em`. Sem PII do visitante.

Workflows:
1. **Pipeline do PDF**: painel dispara `POST /api/v1/inventario/exportar` (o payload de referência usa `/api/v1/inventario/entregaveis/gerar`); cria registro `NA_FILA` e despacha job. O worker monta na ordem da capitulação MTur:
   - Bloco 0, pré-textuais: capa (brasão, logo turismo, cor primária do destino), ficha catalográfica (equipe técnica, hash, nota técnica InvenTour), sumário dinâmico com paginação calculada, narrativas (`destino_narrativas`: Apresentação, Histórico, Economia do Turismo/Indicadores DTI) e linha do tempo (`destino_linha_tempo`).
   - Bloco 1, Categoria A (A1 a An). Bloco 2, Categoria B (B1 com quadro resumo de UHs e leitos, B2, B5...). Bloco 3, Categoria C (C1, C2...).
   - Bloco 4, anexo: catálogo de Experiências homologadas (matriz dos 4 Es) e resumo do calendário oficial de eventos (métricas do EventCity).
   - Cada ficha: foto de capa (`inventario_midias.eh_capa = 1`), GPS, conservação, checklist de acessibilidade, selos de tribos, atributos dinâmicos e QR dinâmico. Arquivo salvo fora do DocumentRoot, status CONCLUIDO.
2. **Conversão "Cavalo de Troia"**: QR das fichas aponta para `https://{subdominio}.inventour.com.br/ficha/{item_id}?src=pdf_{exportacao_id}`.
   - Cenário A (PROJETO_CONSULTORIA): o servidor renderiza landing de "preview bloqueado": cabeçalho do destino, foto e título do item, detalhes com blur, aviso de ativação da licença e botão para pedir TRIAL_B2G (30 dias) ou contato comercial. Rodapé do PDF: "Documento auditado e estruturado via InvenTour".
   - Cenário B (ATIVO_B2G): o mesmo QR (sem reimprimir) passa a abrir a ficha pública viva (Módulo 5), com horários atualizados e aba "Próximos Eventos neste Local" do EventCity.

Regras e casos de borda:
- **Imutabilidade da capitulação**: mesmo no recorte (ex.: só Categoria B), mantém numeração e nomenclatura oficial (B1, B2, B5) e inclui ficha catalográfica com data de extração e aviso de recorte parcial.
- **PDF mostra dados técnicos**: diferente do portal, o PDF renderiza tudo (`potencia_kva`, `observacoes_pesquisador`, `referencias_bibliograficas`, `razao_social`, `cnpj`), exceto CPF de pessoa física ou produtor rural, mascarado (`***.456.789-**`).
- **XLSX multi-abas**: Aba 1 Resumo Territorial e Capacidade Instalada; Aba 2 Categoria A; Aba 3 Categoria B; Aba 4 Categoria C; Aba 5 Experiências. Atributos EAV achatados em colunas nomeadas.
- **PDF volumoso (+500 itens)**: gerar antes miniaturas em cache (800 px de largura, JPEG 80%), renderizar por blocos/capítulos e fazer merge em disco, mantendo RAM abaixo de 128 MB.
- **Fichas não homologadas**: padrão só HOMOLOGADO. Com `incluir_em_conferencia = true` em PROJETO_CONSULTORIA, fichas não homologadas recebem tarja diagonal "MINUTA TÉCNICA EM CONFERÊNCIA".

Payloads:
- `POST /api/v1/inventario/entregaveis/gerar`: `empresa_id`, `tipo_entregavel`, `escopo_filtros` {`categorias`, `apenas_homologados`, `incluir_narrativas_editoriais`, `incluir_linha_do_tempo`, `incluir_experiencias_turisticas`, `incluir_resumo_eventos_eventcity`}, `configuracao_capa` {`titulo_capa`, `ano_referencia`}.
- `GET /api/v1/public/qr-resolve/ficha/{id}?src=pdf_{exportacao}`: `empresa_id`, `subdominio`, `modo_operacao`, `acesso_liberado`, `paywall_b2g` {`titulo_aviso`, `mensagem`, `consultoria_responsavel`, `cta_trial_url` (com token), `qr_conversao_log_id`}, `preview_parcial_item` {`inventour_id`, `categoria_mtur`, `codigo_ficha`, `tipo`, `nome_oficial`, `zona`, `foto_capa_preview_url` (versão com blur em `/media/public-blur/...`), `tribos_badges`, `possui_experiencia_aprovada`}.

### 1.4 Módulo 5: Portal Público do Destino (Web App DTI) e ecossistema Phygital

Objetivo: PWA público "Zero Barrier" (sem instalação nem login) em `cidade.inventour.com.br` ou CNAME institucional. Vitrine por pilares de experiência, programação viva do EventCity dentro de cada ficha e malha de QR Codes urbanos fixos com telemetria anônima (LGPD).

Tabelas:

**`portal_configuracoes`** (1:1 com empresa)
- `slogan_destino` VARCHAR(180), `banner_hero_path`, `og_imagem_padrao_path` (fallback Open Graph).
- `modulos_menu_json` (obrigatório; ordem e visibilidade das abas): `ONDE_DORMIR`, `ONDE_COMER`, `ATRACTIVOS` (grafia do documento), `EXPERIENCIAS`, `ROTEIROS`, `EVENTOS_EVENTCITY`, `DESTINO_INSTITUCIONAL`, `NOTICIAS`.
- `exibir_categoria_a_publico` TINYINT padrão 0 (aba "Serviços e Infraestrutura de Apoio": hospitais, bancos, rodoviária, CAT).
- `whatsapp_central_turista` (CAT no rodapé), `google_analytics_id` (GA4 opcional).
- Identidade white-label vem de `empresas` (`cor_primaria_hex`, `logo_turismo_url`).

**`portal_roteiros`**
- `tipo_roteiro` ENUM(`PERMANENTE`, `EVENTUAL_PROGRAMACAO`, `TEMATICO_TRIBO`), `titulo`, `slug` (único por empresa), `descricao_resumida`, `duracao_estimada_texto`, `publico_alvo_tribo_id` (FK `tribos_consumo`), `validade_inicio`, `validade_fim` (oculta o eventual após o fim), `publicado`.

**`portal_roteiro_paradas`**
- `roteiro_id` (ON DELETE CASCADE), `item_id` (item homologado), `ordem_parada` SMALLINT, `horario_sugerido` TIME, `nota_orientacao` VARCHAR(255).

**`portal_noticias`**
- `titulo`, `slug` (único por empresa), `resumo` VARCHAR(300) (cards e og:description), `conteudo_html_sanitizado` MEDIUMTEXT (mesmo pipeline de sanitização do Módulo 2), `imagem_capa_path`, `promovida_linha_tempo` TINYINT, `publicado_em`.

**`phygital_pontos_acesso`** (placas, totens, displays de recepção, adesivos de mesa)
- `slug_fixo` VARCHAR(80) único no sistema (ex.: `qr-pb-praca-presidente`), **imutável após criação**.
- `nome_identificacao`, `tipo_destino_alvo` ENUM(`HOME_PORTAL`, `FICHA_ITEM`, `ROTEIRO_ESPECIFICO`, `AGENDA_EVENTOS_HOJE`), `item_alvo_id`, `roteiro_alvo_id`, `ativo`.

**`phygital_telemetria_diaria`** (agregação diária sem PII)
- `ponto_id` (NULL = tráfego orgânico), `item_id`, `data_referencia`; índice único (`empresa_id`, `ponto_id`, `item_id`, `data_referencia`).
- Contadores: `leituras_qr_count`, `visualizacoes_ficha_count`, `cliques_rota_maps_count`, `cliques_whatsapp_count`, `cliques_eventos_eventcity_count`.

Workflows:
1. **Gatekeeper B2G (middleware)**: resolve o host (subdomínio ou CNAME), carrega `empresas` + `portal_configuracoes`. ATIVO_B2G ou TRIAL_B2G: portal completo white-label. PROJETO_CONSULTORIA ou SUSPENSO: bloqueia navegação e mostra a landing de paywall (Módulo 4) quando vem de QR de PDF.
2. **Pilares (taxonomia MTur traduzida em UX)**: Onde Dormir = B1 homologado; Onde Comer = B2; O Que Fazer = toda a Categoria C + B6 Lazer; Experiências = `possui_experiencia_turistica = 1` com `inventario_experiencias.status_curadoria = APROVADA`; Agenda da Cidade = programação do subdomínio pareado no EventCity (`cidade.eventcity.com.br`).
3. **Carrinho de roteiro do visitante (zero login)**: "+ Adicionar ao Meu Roteiro" grava IDs no localStorage; "Meu Roteiro" organiza por turno/dia, gera link compartilhável `?itens=1084,1102,1150` e abre a rota sequencial no Google Maps.
4. **Phygital**: QR urbano `https://{sub}.inventour.com.br/qr/{slug_fixo}`; controller acha o ponto, incrementa `leituras_qr_count` com `INSERT ... ON DUPLICATE KEY UPDATE` (sem IP nem cookies) e faz HTTP 302 para o destino configurado.
5. **Aba EventCity na ficha**: consulta `GET /api/v1/public/equipment/{inventour_id}/events` do EventCity, com cache (Redis ou arquivo) de 5 minutos, e mostra "Próximos Eventos neste Local".

Regras e casos de borda:
- Nenhuma consulta pública retorna `status_homologacao != 'HOMOLOGADO'` (rascunho, conferência e arquivado invisíveis). DTO público remove `razao_social`, `cnpj`, `observacoes_pesquisador`, `referencias_bibliograficas` e atributos com `exibir_portal_publico = 0`.
  - Atenção: o Módulo 6 diz que DESATUALIZADO continua visível com selo "Dados em atualização"; há conflito a decidir (ver nota no fim).
- **Ordenação orgânica** (sem B2B): (1) com experiência aprovada; (2) com CADASTUR válido; (3) alfabética ou aleatória diária (seed por data) para isonomia.
- **Circuit breaker EventCity**: timeout de 1,5 s; usa último cache válido ou esconde o bloco silenciosamente, sem erro ao turista.
- **SSR de Open Graph**: PHP renderiza `/explorar/{slug-item}` e `/roteiros/{slug}` com `og:title`, `og:description`, `og:image` no `<head>` (preview no WhatsApp sem JS).
- **Slugs phygital imutáveis**: para mudar o destino, altera só `tipo_destino_alvo` e `item_alvo_id`.

Payloads:
- `GET /api/v1/public/portal/itens/{id}`: `empresa_id`, `destino`, `item` {`inventour_id`, `pilar_portal`, `categoria_mtur`, `subtipo`, `nome_oficial`, `cadastur_verificado`, `zona`, `descricao_promocional`, `localizacao` {`endereco_completo`, `latitude`, `longitude`, `google_maps_url`}, `contatos_cta` {`whatsapp_url` (api.whatsapp.com com texto pronto "Vi ... no Portal Oficial de Turismo"), `telefone_fixo`, `instagram`, `site_url`}, `qualificacao_turista` {`acessibilidade`, `idiomas`, `formas_pagamento` (`PIX`, `CREDITO`, `DEBITO`), `tribos_badges` [{`slug`, `nome`, `facilidades` (rótulos legíveis)}], `comodidades_publicas`}, `galeria_midias` [{`url`, `legenda` (com crédito da foto), `eh_capa`}], `experiencia_exclusiva` {`disponivel`, `titulo`, `narrativa_sensorial`, `duracao_minutos`, `faixa_valor_brl`, `valor_referencia_texto`}}, `integracao_eventcity_proximos_eventos` {`sincronizado`, `total_eventos`, `eventos` [{`event_id`, `titulo`, `data_inicio`, `gratuito`, `eventcity_url`}]}.
- `GET /api/v1/public/portal/roteiros/{slug}`: `roteiro` {`id`, `tipo_roteiro`, `titulo`, `descricao_resumida`, `validade_inicio`, `validade_fim`, `paradas` [{`ordem`, `horario_sugerido`, `inventour_id`, `nome_oficial`, `pilar`, `nota_orientacao`, `google_maps_url`}]}.

### 1.5 Módulo 6: Inteligência Territorial, Diagnóstico DTI e BI (B2G/B2B)

Objetivo: sistema de suporte à decisão para Prefeitura/COMTUR e investidores: capacidade instalada em tempo real, Radar de Obsolescência ("Saúde do Banco"), maturidade DTI e cruzamento da telemetria do portal com os eventos do EventCity.

Tabelas:

**`bi_snapshots_territoriais`** (fotografia mensal)
- `competencia_mes` DATE (sempre dia 1); índice único (`empresa_id`, `competencia_mes`).
- `total_itens_a`, `total_itens_b`, `total_itens_c` (homologados).
- `total_uhs_ativas` e `total_leitos_ativos` (soma B1 homologados; leitos sincronizados com o EventCity), `total_assentos_gastronomia` (B2), `total_capacidade_eventos` (B5).
- `taxa_formalizacao_cadastur_pct` (itens B com `codigo_cadastur` e `cadastur_validade >= competencia_mes`), `taxa_acessibilidade_pcd_pct` (itens B e C com pelo menos 2 itens válidos em `acessibilidade_json`), `taxa_conservacao_adequada_pct` (B e C com OTIMO ou BOM), todas DECIMAL(5,2).
- `total_experiencias_aprovadas`.

**`bi_revalidacao_campanhas`**
- `titulo`, `prazo_obsolescencia_dias` SMALLINT padrão 180, `status` ENUM(`ATIVA`, `ENCERRADA`), `criado_em`.

**`bi_revalidacao_itens`** (magic link sem senha ao empresário)
- `campanha_id`, `empresa_id`, `item_id`, `token_magico_hash` CHAR(64) (SHA-256, válido 15 dias).
- `canal_envio` ENUM(`WHATSAPP`, `EMAIL`) (prioriza `inventario_itens.whatsapp`, fallback `email_contato`).
- `status_resposta` ENUM(`PENDENTE`, `CONFIRMADO_SEM_ALTERACAO`, `EDITADO_AGUARDANDO_MODERACAO`, `EXPIRADO`).
- `enviado_em`, `respondido_em`.

**`dti_diagnostico_respostas`**
- `ano_ciclo` YEAR, `eixo_dti` ENUM com 9 eixos: `GOVERNANCA`, `INOVACAO`, `TECNOLOGIA`, `ACESSIBILIDADE`, `SUSTENTABILIDADE`, `CREATIVIDADE` (grafia do documento), `MOBILIDADE`, `PROMOCAO_MARKETING`, `SEGURANCA`.
- `nota_autoavaliacao` DECIMAL(4,2) de 0,00 a 10,00 (parte automática, parte do gestor), `evidencias_sistema_json` (métricas extraídas do InvenTour/EventCity), `plano_acao_texto`, `atualizado_em`.
- Atenção: os PDFs metodológicos 06 (ver seção 5) trazem a metodologia oficial DTI; a escala e o número de eixos devem ser conferidos com eles.

Workflows:
1. **Radar de obsolescência (cron 02:00)**: varre itens HOMOLOGADO com `DATEDIFF(CURRENT_DATE, ultima_auditoria_em) > 180` ou `cadastur_validade < CURRENT_DATE`; muda para `DESATUALIZADO` (continua visível com selo discreto "Dados em atualização"), incrementa alerta no dashboard.
   - Em ATIVO_B2G, gera token de 15 dias e envia por WhatsApp/e-mail: "Olá! Para manter o [Estabelecimento] em destaque no Portal Oficial de Turismo de [Município], confirme se seus horários, WhatsApp e capacidade continuam os mesmos: [Link]".
   - Caminho A "Está tudo igual": grava log em `inventario_auditoria_logs`, `ultima_auditoria_em = NOW()`, volta para HOMOLOGADO, sem trabalho do moderador.
   - Caminho B "Preciso atualizar": formulário pré-preenchido gera diff em `inventario_revisoes_pendentes`.
2. **Consolidação cruzada InvenTour x EventCity**: BI lê `phygital_telemetria_diaria` + `bi_snapshots_territoriais` e consome `GET /api/v1/analytics/conflict-dashboard` do EventCity (HMAC). Compara `total_leitos_ativos` com o pico de `total_expected_attendance` dos eventos dos próximos 90 dias e sinaliza datas em que a demanda supera a capacidade hoteleira.

Regras e casos de borda:
- **Índice DTI híbrido**: 4 dos 9 eixos com piso ou cálculo automático:
  - Governança: `lei_criacao_oot`, `lei_criacao_comtur`, `comtur_ativo = 1` (Módulo 1).
  - Acessibilidade: percentual de atrativos e hospedagens com itens PCD auditados.
  - Tecnologia e Promoção: portal ativo, quantidade de pontos phygital ativos e integração ativa com o EventCity.
- **Degradação gradual**: quem demora (ex.: 45 dias) para responder não sai do portal; perde prioridade na ordenação do Módulo 5 e gera alerta vermelho no painel do COMTUR para busca ativa.
- **Sigilo comercial**: agregados municipais (leitos, taxa CADASTUR, total de leituras QR) podem ir a relatórios públicos; dados granulares por estabelecimento (cliques de WhatsApp do Hotel A x Hotel B) só para GESTOR_DESTINO e o próprio REPRESENTANTE_TRADE.
- **Snapshot imutável**: todo dia 1º às 03:00 grava linha em `bi_snapshots_territoriais`; a série histórica não muda se o item fechar depois.

Payload `GET /api/v1/bi/dashboard-executivo?periodo=AAAA-MM`:
- `empresa_id`, `destino`, `codigo_ibge`, `categoria_mapa_turismo` (ex.: "B").
- `capacidade_instalada_atual` {`total_itens_homologados`, `por_categoria` {A, B, C}, `hospedagem_b1` {`estabelecimentos`, `total_uhs`, `total_leitos`}, `gastronomia_b2` {`estabelecimentos`, `total_assentos`}, `espacos_eventos_b5` {`espacos`, `capacidade_simultanea_pessoas`}, `experiencias_turisticas_aprovadas`}.
- `qualidade_e_dti` {`taxa_formalizacao_cadastur_pct`, `cadastur_vencidos_alerta`, `taxa_acessibilidade_pcd_pct`, `estado_conservacao_atrativos` {OTIMO, BOM, REGULAR, RUIM}, `indice_maturidade_dti_geral`}.
- `radar_obsolescencia` {`saude_banco_pct`, `itens_em_dia`, `itens_desatualizados_mais_180d`, `campanha_ativa` {`campanha_id`, `enviados`, `confirmados_1_clique`, `pendentes`}}.
- `telemetria_portal_e_phygital_30d` {`leituras_qr_urbanos`, `top_pontos_phygital` [{`slug`, `nome`, `leituras`}], `visualizacoes_fichas`, `cliques_rota_maps`, `cliques_whatsapp_trade`}.
- `cruzamento_eventcity_proximos_90d` {`eventos_programados`, `dias_com_risco_overbooking_hoteleiro` [{`data`, `evento_principal`, `publico_estimado`, `leitos_disponiveis_destino`, `deficit_leitos_estimado`}]}.

### 1.6 Módulo 7: Monetização B2B, Federação Regional (IGRs) e Roadmap V2

Objetivo: feature flags por tenant, monetização recorrente junto ao trade (destaques, banners nativos, clube de vouchers), federação regional (IGRs, consórcios, estados) com consulta agregada cross-tenant sem quebrar isolamento, Data Hub e roteirizador inteligente.

Tabelas:

**`destino_feature_flags`**
- `plano_comercial` ENUM(`V1_CONSULTORIA_FREE`, `V1_B2G_MUNICIPAL`, `V2_B2G_PRO_B2B`, `V2_FEDERACAO_IGR`).
- `codigo_feature` ENUM(`MONETIZACAO_TRADE_B2B`, `CLUBE_VOUCHERS`, `FEDERACAO_REGIONAL_IGR`, `ROTEIRIZADOR_IA_V2`, `DATA_HUB_INVESTIDORES`, `ADS_PATROCINADOS`); índice único (`empresa_id`, `codigo_feature`).
- `ativo` TINYINT, `config_json` (ex.: limite de banners, split comercial).

**`trade_assinaturas_b2b`**
- `item_id`, `nivel_plano` ENUM(`GRATUITO_COLABORATIVO` (padrão V1), `DESTAQUE_MENSAL`, `PARCEIRO_PREMIUM_ANUAL`).
- `status_assinatura` ENUM(`ATIVA`, `INADIMPLENTE`, `CANCELADA`, `TRIAL`); INADIMPLENTE por mais de 10 dias rebaixa para GRATUITO_COLABORATIVO sem remover a ficha.
- `peso_patrocinio` TINYINT: 0 gratuito, 50 destaque, 100 premium. `valor_mensal_brl` DECIMAL(10,2), `vigencia_ate`.

**`portal_banners_b2b`**
- `assinatura_id`, `posicao_slot` ENUM(`HOME_CARROSSEL_PARCEIROS`, `TOPO_PILAR_ONDE_DORMIR`, `TOPO_PILAR_ONDE_COMER`, `RODAPE_ROTEIRO`) com selo obrigatório "Parceiro Oficial / Patrocinado".
- `imagem_banner_path` (`/storage/tenants/{empresa_id}/b2b/`), `url_destino`, `status_moderacao` ENUM(`PENDENTE_COMTUR`, `APROVADO`, `REJEITADO`), `impressoes_count`, `cliques_count`.

**`trade_vouchers_ofertas`**
- `item_id`, `eventcity_event_id` VARCHAR(64) opcional (voucher válido durante um evento), `titulo_beneficio`, `tipo_desconto` ENUM(`PERCENTUAL`, `VALOR_FIXO`, `COMBO_CORTESIA`), `quantidade_total` INT (-1 ilimitado), `quantidade_resgatada`, `valido_ate`, `ativo`.

**`trade_vouchers_resgates`**
- `oferta_id`, `visitante_identificador_hash` CHAR(64) (SHA-256 do e-mail ou telefone validado por OTP), `codigo_voucher` VARCHAR(24) único (ex.: `INV-PB-2026-9A4F`), `token_qr_hash` CHAR(64), `status` ENUM(`EMITIDO`, `UTILIZADO`, `EXPIRADO`), `validado_por_usuario_id` (REPRESENTANTE_TRADE), `validado_em`.

**`federacao_vinculos_territoriais`**
- `tenant_agregador_id` (empresa com `tipo_destino IN ('CONSORCIO_IGR','ESTADUAL')`), `tenant_membro_id` (`tipo_destino = 'MUNICIPAL'`), `status_adesao` ENUM(`ATIVO`, `SUSPENSO`), `destaque_regional`, `criado_em`.
- Implica coluna `empresas.tipo_destino` ENUM(`MUNICIPAL`, `CONSORCIO_IGR`, `ESTADUAL`).

Workflows:
1. **Federação**: turista acessa `valesdoiguacu.inventour.com.br` (ou portal estadual); middleware vê `tipo_destino = CONSORCIO_IGR` e flag `FEDERACAO_REGIONAL_IGR = 1`, carrega membros ativos com `modo_operacao = 'ATIVO_B2G'`; consulta `empresa_id IN (:membros) AND status_homologacao = 'HOMOLOGADO'`; filtros por município e raio em km a partir do visitante; roteiros intermunicipais com CTA para a ficha oficial do município de origem; eventos pelo endpoint federado do EventCity (`regiao.eventcity.com.br`).
2. **Autenticação progressiva**: navegação continua 100% anônima; ao "Resgatar Voucher", "Salvar Roteiro na Nuvem" ou "Avaliar Experiência", salva a intenção no localStorage, pede Magic Link por e-mail ou OTP por WhatsApp, executa e devolve à tela original.

Regras e casos de borda:
- **Isonomia institucional**: município `V1_B2G_MUNICIPAL` pode operar para sempre sem cobrar o trade (`MONETIZACAO_TRADE_B2B = 0`).
- Com a flag ativa, ordenação: `Score = PesoPatrocinio x 0,40 + PossuiExperienciaAprovada x 0,30 + CadasturValido x 0,20 + SeedAleatoriaDiaria x 0,10`. Item impulsionado exibe tag "Destaque"; 100% dos gratuitos continuam visíveis em filtros e buscas.
- **Efeito rede na federação**: municípios ativos mostram tudo; os não licenciados aparecem só como pin institucional no mapa com selo "Inventário em fase de estruturação".
- **Voucher offline**: PWA do lojista valida pelo código curto ou guarda a baixa localmente e sincroniza depois.
- **Data Hub (V2)**: séries de `bi_snapshots_territoriais` + déficit de leitos em datas de eventos + buscas sem resultado no portal (ex.: "hotel fazenda", "camping") gerando relatórios de oportunidade de investimento. Implica registrar buscas sem resultado no portal.

Payloads:
- `GET /api/v1/destino/capacidades`: `empresa_id`, `subdominio`, `tipo_destino`, `plano_comercial`, `features_ativas` {flag: bool}, `federacao_vinculada` {`integrado`, `tenant_agregador_id`, `nome_regiao`, `portal_regional_url`}.
- `GET /api/v1/public/federacao/{agregador}/explorar?pilar=EXPERIENCIAS&raio_km=60`: `tenant_agregador_id`, `regiao_turistica`, `municipios_ativos_count`, `total_resultados`, `resultados` [{`inventour_id`, `municipio` {`empresa_id`, `nome`, `uf`, `subdominio`}, `pilar`, `nome_oficial`, `titulo_experiencia`, `faixa_valor_brl`, `patrocinado_b2b`, `voucher_disponivel` {`oferta_id`, `titulo_beneficio`}, `url_ficha_oficial`}].

### 1.7 Seção final "FUNCIONALIDADES" (anotações do Luiz, texto livre)

Necessidades levantadas pelo próprio turismólogo, que originaram partes dos módulos:
- **Experiências turísticas**: não basta uma tag; marcar "experiência" no cadastro deve abrir formulário específico com curadoria (evidências, validação) para não virar "porteira aberta". Aba exclusiva no portal e formulário exclusivo no painel. Precisa servir de hotel e restaurante a visita técnica e vinícola, com os 4 Es da Economia da Experiência. Não está na metodologia do inventário, mas é indispensável.
- **Tribos de consumo e nichos**: menu de tribos nos cartões; ao escolher a tribo, oferece tags. Exemplos: Cicloturismo (bike friendly: sala e bancada com ferramentas, hidratação, Wi-Fi, bomba de pressão); MotorHome e campistas (estacionamento, abastecimento, energia, descarte de água suja e detritos, fire pit, lavanderia).
- **Textos longos do inventário** (apresentação, histórico, origens): precisam de editor com negrito, itálico, bullets, tabelas, cartões; provavelmente o usuário vai colar do Word com formatação e conteúdo "poluído" (imagens, tabelas), então é preciso sanitizar a colagem. Esses textos servem ao gestor (copiar para materiais) e como "discurso oficial" do destino em abas do portal.
- Pode ultrapassar a metodologia, mas sem faltar o cerne nem mexer na capitulação.
- **Economia do Turismo** indispensável: número de ACTs, CADASTUR e evolução, empregos no turismo, VAF de Alojamento e Alimentação (IPARDES), indicadores estaduais como o IDAT-PR.
- **Linha do tempo do destino**: marcos (lei, conselho, eventos, voo comercial, número de turistas, nível DTI), vinculável a notícias.
- **A1 Informações Básicas**: incluir campo para lei de instituição do OOT e do COMTUR; vários campos antigos parecem inúteis (reciclagem de resíduos, telecomunicação ainda em 3G); acoplar indicadores DTI.
- **Roteiro personalizado do gestor** (sequência de pontos com horários, publicado como Roteiro Exclusivo, ex.: "Roteiro do Campeonato de Bocha" em 3 locais com 5 pontos de refeição e apoio). Referência: destinationbook.com.
- **Roteiro personalizado do usuário**: escolher quantos dias, filtrar interesses (outdoor, lazer, eventos, entretenimento, parques, música ao vivo), "carrinho" e roteirizar, compartilhando.
- **DTI**: anexar manuais metodológicos DTI do MTur, norma ABNT e material dos eixos; acesso à ferramenta online de Diagnóstico DTI nacional e ao formulário de avaliação.

### 1.8 Pontos de atenção encontrados na leitura (para decidir no plano)
- Visibilidade de `DESATUALIZADO`: Módulo 5 diz que o portal só mostra HOMOLOGADO; Módulo 6 diz que DESATUALIZADO continua visível com selo. Sugestão: público = HOMOLOGADO ou DESATUALIZADO (este com selo e menor prioridade).
- Rota de geração do PDF aparece com dois caminhos (`/api/v1/inventario/exportar` e `/api/v1/inventario/entregaveis/gerar`).
- Grafias a padronizar: `ATRACTIVOS` (Módulo 5) e `CREATIVIDADE` (Módulo 6).
- TRIAL_B2G: portal público ativo (Módulo 5), mas crowdsourcing do trade bloqueado (Módulo 3).
- Os 9 eixos e a escala 0 a 10 do Módulo 6 devem ser conferidos com a metodologia oficial DTI (PDFs 06).
- O perfil "CONSULTOR" aparece no Módulo 4, mas não está no ENUM `perfil_papel` do Módulo 3.


---

## 2. Documento 01: Pitch Deck (PDF, 17 páginas)

Arquivo: `01 InvenTour_PitchDeck.pdf` (ID 1hGjh9h1ACUu5h9rFHb9y46KzXZs6TdP6). Lido pela camada de texto do visualizador do Drive (todas as 17 páginas).

### Identidade visual
- Logo: pino de localização (marcador de mapa) com rosa dos ventos dentro de uma lupa; abaixo, palavra "inventour" em minúsculas, "inven" em azul petróleo escuro e "tour" em verde-água; tagline em caixa alta: "INTELIGÊNCIA EM INFORMAÇÕES TURÍSTICAS".
- Capa: grande lupa estilizada em degradê, com rosa dos ventos e pontos em arco, fundo branco/cinza muito claro.
- Paleta (amostrada dos pixels da capa, valores aproximados): azul petróleo escuro ~`#0B4A6B`; petróleo/teal ~`#187880` a `#188888`; verde-água ~`#2BB898` a `#3AA898`; verde claro/limão ~`#89BF7A` (ponta do degradê). Fundo claro ~`#F4F4F5` a `#FAFAFA`.
- Nome de marca no texto: "inventour, Plataforma de Inteligência em Informações Turísticas". Site: www.inventour.com.br.

### Dores (O Problema)
- **Premissa 1**: municípios precisam de inventário da oferta para planejar.
  - Modelo "prancheta": coleta analógica, impressos sem conexão; dados entregues em listagens PDF/Word, sem cruzamento nem análise de evolução.
  - Custo, demora e obsolescência: processo centrado no turismólogo, que gasta tempo em dados básicos em vez de avaliar qualidade; o inventário fica obsoleto e o ciclo recomeça, somando custos; gestores abandonam a ferramenta.
  - Troca de mandato: arquivos "somem" e a nova gestão recomeça do zero.
- **Premissa 2**: municípios precisam de site oficial de turismo.
  - Sites sem base de inventário ativa ficam genéricos ou incompletos; viram "cemitério de dados antigos"; turista perde confiança.
  - "Apagão do período eleitoral": a legislação eleitoral obriga a congelar ou tirar sites municipais do ar.

### Oportunidade
- Corrida por DTI: o MTur exige indicadores de gestão de dados e tecnologia; há demanda reprimida.
- Migração de verbas: prefeituras já pagam inventários e sites; converter em assinatura SaaS mais barata.
- "Blindagem jurídica do COMTUR": custódia dos dados pelo COMTUR (órgão permanente e tripartite) protege o portal das transições políticas e do período eleitoral.
- Interesse do trade (B2B): BI para investimentos; empreendimentos invisíveis no PDF passam a ter interesse em manter dados atualizados (alimentação espontânea). Turismólogos viram promotores da plataforma.

### Solução e os 5 pilares
SaaS multi-tenant de Inteligência Territorial e Governança Pública, com subdomínios `cidade.inventour.com.br`.
1. **Módulo de Coleta e Auditoria**: freemium técnico móvel segundo as diretrizes do MTur; formulários alinhados às matrizes oficiais (atrativos, equipamentos e serviços, infraestrutura de apoio); coleta em nuvem com geolocalização e fotos; "Efeito Cavalo de Troia": licença gratuita para profissionais gerarem a Encadernação Oficial em PDF para prefeituras.
2. **Dashboard de Governança Municipal (B2G)**: crowdsourcing moderado (trade atualiza capacidade e leitos), validação rápida pelo gestor ou turismólogo, BI territorial, validação de cadastros como CADASTUR, Vigilância Sanitária SIM/SUSAF/SIF, normas de segurança.
3. **Web App do Destino**: front-end público responsivo, alimentado em tempo real, sem download em loja.
4. **Ecossistema QR Code Urbano (Phygital)**: placas, monumentos, praças e estabelecimentos com QR padronizado; cada leitura alimenta o BI.
5. **Infraestrutura SaaS Multi-Tenant**: subdomínios isolados, blindagem eleitoral (dados custodiados pelo COMTUR e alimentados pelo trade), custo marginal reduzido.

### Público
- B2G: governo, prefeituras, IGRs (e estados), COMTUR.
- B2B: trade turístico (hotéis, restaurantes, agências, atrativos).
- Turismólogos e consultores (canal de venda e usuários da coleta).
- Turista/visitante (portal sem login).

### Modelo de negócio
- Receita B2G (GovTech): **taxa de setup** única (configuração do tenant, banco, módulos de BI, identidade visual do município, treinamento, mapeamento inicial dos QR Codes, configuração do Web App) e **assinatura anual** (licença SaaS) com preço segmentado pelo porte do município; pode ser custeada ou rateada via COMTUR e parceiros privados.
- Receita B2B (TravelTech/Mídia): freemium para o trade atualizar dados de graça; planos premium com posições prioritárias, anúncios nativos e pop-ups no Web App.
- Não há valores de preço no deck.

### Estratégia de escala
- Efeito Cavalo de Troia: ferramenta gratuita para consultores; o PDF entregue cria a necessidade do SaaS; consultores como canal de venda comissionado.
- Custo marginal zero por município (subdomínio replicável).
- Efeito de rede regional: dados consolidados em portal regional ou estadual.
- Cauda longa: mídia nativa no destino (grandes marcas) e Data Hub Territorial (ativo para investidores).

### Impacto social
Salvaguarda de patrimônio cultural, gastronômico e artesanal com visibilidade gratuita; governança tripartite via COMTUR; desmaterialização (menos impressão); dados de QR como subsídio para verbas federais e ISS turístico; alinhamento com ODS 8 e ODS 11.

### Equipe
- Luiz Carlos Piccinin, cofundador e CTO (back-end, multi-tenant, segurança; Softsul, 56 clientes governamentais ativos).
- Anery Junior Baggio, cofundador e CEO (turismólogo, especialista em DTI, governança e fit com o trade).
- Marca e patente submetidos ao INPI; Pato Branco, PR.

### Roadmap
O deck não traz roadmap datado. A evolução aparece só como sequência: inventário e PDF gratuito (consultoria), assinatura B2G com portal e QR, monetização B2B, federação regional, Data Hub. O roadmap V1 e V2 detalhado está no Módulo 7 do documento 02.

---

## 3. Documento 07: Tribos de Consumo e Nichos Comportamentais

Arquivo: `07 InvenTour - Tribos de Consumo e Nichos Comportamentais.docx` (ID 1QfXYv0GZT3javnTb2Xx256hI4zQ4ZpzT). Lido por completo.
Estrutura: 5 macrocategorias, 21 tribos; cada tribo tem "Perfil" (texto) e "Tags" (facilidades que o empreendimento comprova). Serve de semente para `tribos_consumo` e suas facilidades (no payload do Módulo 2 aparecem como `facilidades_comprovadas` com slug).

### Macrocategoria 1: Mobilidade, Estrada e Esportes de Resistência
1. **Cicloturistas / Bike Friendly**. Perfil: bicicleta como transporte ou esporte (MTB, Speed), precisam de segurança para o equipamento e apoio mecânico. Tags: Bicicletário coberto e monitorado; Permite bicicleta no quarto; Oficina com bancada e ferramentas; Bomba de ar de pé; Área de lavagem de bikes; Café da manhã antecipado (antes das 6h); Kit de primeiros socorros; Transfer de bagagens; Venda de câmaras e suplementos.
2. **Motociclistas / Estradeiros (Moto Friendly)**. Perfil: grupo ou solo em rodovias panorâmicas, estacionamento seguro e lugar para secar equipamento. Tags: Estacionamento coberto e fechado; Vagas exclusivas para motos; Espaço para lavar a moto; Armário ou cabideiro reforçado para jaquetas e capacetes; Secadora de roupas e botas; Lubrificante de corrente disponível; Oficina mecânica parceira próxima; Bar ou área de convivência noturna.
3. **Corredores e Triatletas (Running / Triathlon Friendly)**. Perfil: provas de rua, trail, treinos; horários flexíveis e alimentação específica. Tags: Café da manhã pré-prova (madrugada); Late check-out garantido no dia da prova; Rotas de treino mapeadas na recepção; Banheira de gelo / crioterapia; Lavanderia expressa para roupas esportivas; Acesso a piscina de 25 m ou 50 m; Cardápio rico em carboidratos; Massagem desportiva.
4. **Motorhome, Kombi e Campistas (Overlanders)**. Perfil: nômades rodoviários e campistas que precisam de pontos de apoio seguros para pernoite. Tags: Ponto de energia elétrica (110V/220V); Abastecimento de água potável; Descarte de águas cinzas e negras; Área gramada e nivelada; Vestiários com chuveiro quente 24h; Cozinha comunitária equipada; Segurança/portaria 24h; Área para fogueira permitida; Aceita barracas de teto.
5. **Peregrinos e Caminhantes de Longa Distância**. Perfil: rotas religiosas ou contemplativas (Caminho da Fé, Cora Coralina, Passos de Anchieta), orçamento controlado, recuperação física. Tags: Carimbo oficial da rota/passaporte; Diária individual econômica; Tanque para lavar roupas e varal amplo; Bacia para escalda-pés; Massagem para pés e pernas; Jantar caseiro incluído (meia-pensão); Check-in flexível a pé; Venda de curativos para bolhas.

### Macrocategoria 2: Natureza, Aventura e Estilo de Vida Outdoor
6. **Trilheiros, Montanhistas e Ecoturistas**. Perfil: parques, travessias, cachoeiras; saída cedo e limpeza de equipamento. Tags: Lanche de trilha (kit piquenique); Lava-botas na entrada; Guarda-volumes para mochilas cargueiras; Contato com guias locais credenciados (Cadastur); Transfer 4x4 até a portaria do parque; Secador de botas e roupas úmidas; Aluguel de bastões e lanternas.
7. **Observadores de Aves e Vida Selvagem (Birdwatchers)**. Perfil: público especializado, acorda antes do amanhecer, exige silêncio e preservação. Tags: Trilhas privativas em mata nativa; Comedouros e bebedouros de aves; Torres ou mirantes de observação; Lista de espécies catalogadas no local; Café da manhã às 4h30/5h; Guia ornitólogo parceiro; Política rigorosa de silêncio; Iluminação externa sem poluição luminosa.
8. **Surfistas e Esportes Náuticos (Kite, Wind, SUP)**. Perfil: condições de mar e vento; pranchas e velas volumosas. Tags: Guarda-volumes seguro para pranchas e velas; Ducha externa de água doce; Varal para neoprene; Pé na areia ou a menos de 200 m do pico; Boletim diário de ondas e vento; Aluguel de equipamentos; Oficina de reparo de pranchas próxima; Transfer para picos vizinhos.
9. **Pescadores Esportivos (Pesque e Solte)**. Perfil: grupos em rios e represas, estrutura náutica completa. Tags: Rampa de acesso para barcos; Píer ou trapiche iluminado; Aluguel de barcos, motores e piloteiros; Freezer para armazenamento (quando permitido); Venda de iscas vivas e tralhas; Licença de pesca facilitada; Limpeza e preparo do pescado.

### Macrocategoria 3: Gastronomia e Cultura Sensorial
10. **Amantes de Cafés Especiais (Coffee Lovers)**. Perfil: cafeterias de terceira onda, torrefações e fazendas. Tags: Grãos 100% Arábica de origem rastreada; Métodos filtrados (V60, Aeropress, Chemex, Prensa); Torrefação própria; Barista certificado; Venda em grãos ou moídos na hora; Opções de leites vegetais; Tour guiado pela lavoura de café.
11. **Enoturistas e Apreciadores de Cervejas Artesanais**. Perfil: regiões produtoras, degustações e harmonizações. Tags: Degustação guiada com sommelier; Visita à vinícola ou fábrica; Carta de rótulos locais e premiados; Taças de cristal adequadas; Menu harmonizado; Clube de assinaturas ou envio para todo o Brasil; Transfer seguro (motorista da rodada).
12. **Veganos, Vegetarianos e Restrições Alimentares**. Perfil: escolhem destino pela oferta gastronômica inclusiva. Tags: Cardápio 100% vegano; Opções vegetarianas sinalizadas; Cozinha livre de contaminação cruzada (sem glúten); Opções sem lactose; Café da manhã com itens plant-based; Cosméticos veganos nos quartos.

### Macrocategoria 4: Inclusão, Família e Perfil Social
13. **Pessoas com Deficiência e Mobilidade Reduzida (PCD)**. Perfil: precisam de informação técnica precisa, não selo genérico. Tags: Rampa com inclinação adequada (NBR 9050); Elevador com sinalização em Braille e sonora; Banheiro adaptado com barras e cadeira de banho; Porta com vão livre mínimo de 80 cm; Piso tátil direcional e de alerta; Cardápio em Braille e QR Code acessível; Atendimento em Libras; Audiodescrição disponível; Vaga demarcada próxima à entrada.
   - Observação para o sistema: sobrepõe-se ao campo transversal `acessibilidade_json` (RAMPA_ACESSO, BANHEIRO_ADAPTADO, ESTACIONAMENTO_PCD...); convém unificar o catálogo.
14. **Tutores de Pets (Pet Friendly Real)**. Perfil: pet como membro da família. Tags: Aceita pets de grande porte; Sem restrição de raça; Pet circula nas áreas comuns e restaurante; Caminha, comedouro e tapete higiênico no quarto; Área gramada cercada (parcão); Pet sitter ou passeio; Menu exclusivo para pets; Sem taxa extra de limpeza.
15. **Famílias com Bebês e Crianças Pequenas**. Perfil: infraestrutura para reduzir bagagem e entretenimento seguro. Tags: Copa do bebê 24h; Berço e banheira infantil no quarto; Protetor de tomadas e telas nas janelas; Monitoria infantil por faixa etária; Piscina infantil aquecida e rasa; Trocador nos banheiros masculino e feminino; Menu kids saudável.
16. **Público 60+ (Melhor Idade Ativa)**. Perfil: viajam fora da alta temporada, conforto e passeios sem pressa. Tags: Quartos no térreo ou com elevador; Piso antiderrapante no banheiro; Apoio para banho; Passeios de baixo esforço físico; Descontos para terceira idade / baixa temporada; Atendimento humanizado; Climatização quente/frio.
17. **Viajantes LGBTQIA+ (Queer Friendly)**. Perfil: estabelecimentos comprovadamente acolhedores. Tags: Equipe treinada em diversidade e inclusão; Cama de casal sem questionamentos; Banheiros sem gênero nas áreas comuns; Ambiente seguro (Selo Safe Space); Curadoria de roteiros e festas LGBTQIA+; Propriedade liderada por pessoas LGBTQIA+.
18. **Mulheres que Viajam Sozinhas (Solo Female Travelers)**. Perfil: segurança, iluminação, privacidade. Tags: Quartos ou dormitórios exclusivamente femininos; Recepção e segurança 24h; Fechadura eletrônica ou tranca interna reforçada; Rua bem iluminada e movimentada; Transfer seguro do aeroporto/rodoviária; Anfitriã mulher; Passeios em grupo para mulheres.

### Macrocategoria 5: Trabalho, Economia e Bem-Estar
19. **Nômades Digitais e Trabalho Remoto (Workation)**. Tags: Wi-Fi acima de 200 Mbps comprovado; Internet redundante (duas operadoras ou Starlink); Mesa ergonômica e cadeira de escritório no quarto; Coworking integrado; Cabines acústicas para videochamadas; Gerador ou nobreak; Descontos para estadias longas.
20. **Mochileiros e Viajantes Low Cost**. Tags: Quartos compartilhados com tomada e luz individual; Lockers grandes; Cozinha compartilhada completa; Próximo a metrô/rodoviária; Free walking tour ou eventos sociais; Guarda de bagagem gratuita antes/depois do check-in; Preços econômicos.
21. **Bem-Estar, Retiros e Espiritualidade (Wellness)**. Tags: Espaço coberto para ioga e meditação; Terapias holísticas (massagem, reiki, ayurveda); Alimentação orgânica e agroecológica; Áreas livres de Wi-Fi (detox digital); Bioconstrução e sustentabilidade; Trilhas contemplativas e banhos de cachoeira; Silêncio absoluto após as 22h.

Modelagem sugerida: `tribos_macrocategorias` (5) > `tribos_consumo` (21, com slug como `CICLOTURISMO`, `MOTORHOME_CAMPING`, perfil e ícone) > `tribos_facilidades` (tags com slug) > tabela de vínculo item x facilidade comprovada. O Módulo 5 usa `publico_alvo_tribo_id` em roteiros temáticos.

---

## 4. Subpastas "WebApp" e "InvenTour"

### 4.1 Estrutura encontrada
A pasta "InvenTour" (ID 1BOyDVahhMrW0RlK86CfAAhng44_Die-O) fica dentro de "WebApp" (ID 14lliuSCz4QtVfByWhXfMZMYf-rJhW9cv), e a própria pasta "#Pacote de Especificação" está dentro de "InvenTour".

WebApp (raiz):
- Subpastas: `Smart Tour`, `Projetos concorrentes - Centelha`, `Metodologia Inventariação`, `mais ideias`, `InvenTour`, `EventCity`.
- Arquivos: `Notas Start Point` (Google Doc), `CatalogoSoluesTecnolgicas_DTIBrasil_230317_225005.pdf`, `CanvasTurismo.pptx`, `18022023 - Start point.jpg`.

InvenTour:
- `pitchdeck`: `inventour.pptx`, `InvenTour_PitchDeck.pdf` (mesmo conteúdo do 01) e um arquivo temporário do PowerPoint (`~$inventour.pptx`).
- `logo`: `InvenTour_v.png` (vertical), `InvenTour_h.png` (horizontal), `InvenTour_v_branca.svg`, `InvenTour_h_branca.svg` (versões brancas para fundo escuro) e `InvenTour_logo_manual.pdf`.
- `inventário metodologia`: subpastas `Inventariação 2019`, `Inventariação 2011`, `Inventariação 2006`, `Exemplos Inventário Pesquisa Demanda Plano`.
  - `Inventariação 2019`: `Inventário da Oferta Turística - Formulários (2019).docx` (121 páginas, lido) e `39 - CARTILHA TURISMO 360.pdf`.
- `centelha`: não aberta (material do edital Centelha).
- `#Pacote de Especificação`: a pasta principal desta leitura.

### 4.2 Manual da marca (`InvenTour_logo_manual.pdf`, 1 página, lido por imagem)
Paleta oficial (valores exatos do manual):

| Cor | HEX | RGB | CMYK | Uso provável |
|---|---|---|---|---|
| Azul petróleo escuro | `#0B426B` | 11, 66, 107 | 90/38/0/58 | "inven", textos, primária escura |
| Verde-água | `#20B295` | 32, 178, 149 | 82/0/16/30 | "tour", primária de destaque |
| Amarelo/âmbar | `#F3B137` | 243, 177, 55 | 4/32/87/0 | acento (rosa dos ventos) |
| Azul médio | `#1E6EA7` | 30, 110, 167 | 84/49/3/6 | secundária |
| Azul claro | `#63B6E5` | 99, 182, 229 | 56/14/0/10 | secundária clara |
| Verde claro (código web) | `#65C48A` | 101, 196, 138 | 51/0/50/23 | fundo de aplicação, sucesso |

- Tipografia: **Plus Jakarta Sans** (Google Fonts).
- Versões do logo: "degradê" e "flat"; vertical (símbolo acima do nome) e horizontal (símbolo à esquerda); versão sobre fundo verde `#65C48A`.
- Símbolo: pino de mapa com rosa dos ventos dentro de uma lupa; elementos isolados (lupa, rosa dos ventos de 8 pontas em degradê azul, verde e amarelo).
- Tagline: "INTELIGÊNCIA EM INFORMAÇÕES TURÍSTICAS".

### 4.3 Formulários oficiais do Inventário da Oferta Turística (MTur, 2019)
Arquivo `Inventário da Oferta Turística - Formulários (2019).docx` (ID 1ma5TTHOtUTepz3awIZmHGAtgR_gP6F9z), anexos da cartilha "Turismo em 360°: Planejando o Turismo no Município". O link mobilebasic deu erro; lido pela visualização do Drive (121 páginas, cerca de 200 mil caracteres). É a versão mais recente publicada, mas o documento 00 trata as 54 fichas de 2011 como a estrutura vigente (é a numeração usada no documento 02). Decisão a tomar: manter os códigos de 2011 e conferir os campos com a versão 2019.

Lista completa de formulários (página de início):
- **Grupo 1, Categoria A (Infraestrutura de Apoio)**
  - A.1 Informações Básicas do Município (p2)
  - A.2 Características do Município: água e esgoto, energia, lixo e reciclagem (p4)
  - A.3 Acesso ao Município: rodoviário (adaptável a ferroviário, aquaviário, aeroviário) (p5)
  - A.4 Acessos, Equipamentos: estação/terminal rodoviário (adaptável a aeroporto, ferrovia, heliporto) (p6)
  - A.5 Sistema de Comunicação (p9)
  - A.6 Sistema de Segurança (p10)
  - A.7 Sistema de Saúde (p11)
  - A.8 Sistema Educacional (p12)
  - A.9 Outros Serviços e Equipamentos de Apoio: A.9.1 Compras Especiais (p13), A.9.2 Comércio Turístico (p15), A.9.3 Serviços Bancários (p16), A.9.4 Serviços Mecânicos (p17), A.9.5 Representações Diplomáticas (p18)
- **Grupo 2, Categoria B (Serviços e Equipamentos Turísticos)**
  - B.1 Hospedagem (p19)
  - B.2 Alimentos e Bebidas (p24)
  - B.3 Agenciamento de Viagens e Operações Turísticas (p27)
  - B.4 Transporte Turístico (p31)
  - B.5 Eventos: B.5.1 Espaços para Eventos (p33), B.5.2 Serviços para Eventos (p36)
  - B.6 Lazer: B.6.1 Parques (p39), B.6.2 Espaços livres e áreas verdes (p41), B.6.3 Instalações esportivas (p43 e p47, repetido), B.6.4 Espaços de diversão e cultura (p50), B.6.5 Outros espaços de recreação (p52)
  - B.7 Outros serviços: B.7.1 Informações turísticas (p55), B.7.2 Entidades associativas (p57), B.7.3 Guiamento e condução turística (p59)
- **Grupo 3, Categoria C (Atrativos Turísticos)**
  - C.1 Naturais: C.1.1 Relevo continental (p60), C.1.2 Zona Costeira (p65), C.1.3 Relevo Cárstico (p71), C.1.4 Hidrografia e Queda d'água (p77), C.1.5 Unidades de conservação (p83)
  - C.2 Culturais: C.2.1 Conjuntos arquitetônicos, sítios, parques históricos (p89), C.2.2 Comunidades tradicionais e itinerários culturais (p93), C.2.3 Lugares de manifestação de fé, de memória, feiras e mercados culturais (p97), C.2.4 Arquitetura civil, oficial, militar, sacra, industrial/agrícola, funerária, marcos (p101), C.2.5 Bens integrados a edificações (p105), C.2.6 Gastronomia típica, artesanato, atividades tradicionais de trabalho (p109), C.2.7 Formas de expressão (p110), C.2.8 Personalidades (p111)
  - C.3.1 Atividades econômicas: extrativista, agropecuária, comercial, industrial, tecnológica (p112)
  - C.4.1 Realizações técnicas e científicas: parque tecnológico, centro de pesquisa, usinas, planetário, aquário, viveiro (p113)
  - C.5.1 Eventos programados: feiras, congressos, festivais, competições, festas e celebrações (p117)

Cada formulário abre com "Este formulário pode ser adequado a outros equipamentos, tais como: ..." e a instrução "Preencher um formulário para cada equipamento". A lista de "tais como" é exatamente o catálogo de **tipos/subtipos** de cada ficha (ex.: B.1: Hotel, Hotel histórico, Hotel fazenda, Resort, Pousada, Flat/apart-hotel, Cama e café, Albergue, Alojamento de floresta, Pensão, Motel, Colônia de férias, Estalagem/hospedaria, Alojamento coletivo, Hospedagem conventual, Hospedagem familiar, Hotel de trânsito, Spa, Outros; B.2: Restaurante, Bar, Lanchonete, Cafeteria, Quiosque, Barraca de praia, Sorveteria...; C.1.4: Rio, Riacho, Córrego, Arroio, Lago/lagoa/laguna, Alagado, Fonte, Catarata, Cachoeira, Salto, Cascata, Corredeira).

**A.1 Informações Básicas** (campos): nome do município, UF, endereço da prefeitura, bairro, CEP, telefone, e-mail, site, CNPJ, municípios limítrofes, distância da capital, quantidade de funcionários, % de pessoas com deficiência, prefeito, secretarias; órgão oficial de turismo (nome, gestor responsável, endereço, contatos, nº de funcionários, nº com formação superior em Turismo); instâncias de governança e parcerias (municipais a internacionais); aniversário do município, santo padroeiro e dia, feriados municipais; histórico, origem do nome, data de fundação, data de emancipação, outros fatos históricos; área total, urbana e rural (km²); população total, urbana e rural; temperatura média, mínima e máxima; meses mais frios, mais quentes, mais e menos chuvosos; altitude média; principais atividades econômicas; observações. (O Luiz pede acrescentar leis do OOT e do COMTUR.)

**A.2 Características**: abastecimento de água (tipo, % domicílios, empresa), esgoto (rede, % total, urbano, rural, entidade), energia (voltagem, KVA, gerador, rede urbana e rural com % e entidade), lixo (coleta seletiva, % urbano e rural, entidade) e reciclagem (aço, entulho, alumínio, madeira, ferro, papel, baterias, plástico, borracha, óleo, eletrônicos, longa vida). São os campos que o Luiz considera de pouca utilidade.

**Seções comuns às fichas B e C** (blocos reaproveitáveis no modelo de dados e no formulário):
1. Identificação: nome (oficial e popular quando cabe), rede/holding, tipo (subtipo), endereço, telefones, e-mail, site, início da atividade, quantidade de funcionários, CNPJ e inscrição municipal (em A.4 e outros), natureza (pública, privada, outra), tipo de organização (associação, sindicato, cooperativa, Sistema S, empresa), responsável pela administração e entidade mantenedora (atrativos).
2. Localização: urbana ou rural; sinalização de acesso e turística (sim/não); proximidades (restaurante, bar/lanchonete, meio de hospedagem, shopping, galeria/rua comercial, posto de combustível, caixa eletrônico, banco, centro de convenções); "necessidades visíveis / apontadas"; distâncias em km (aeroporto, estação ferroviária, marítima/fluvial, metroviária, ponto de ônibus, ponto de táxi, outras); pontos de referência.
3. Funcionamento: atendimento em língua estrangeira (não, inglês, espanhol, outras); informativos impressos (idiomas); período de funcionamento (12 meses ou ano inteiro); horários e dias; 24 horas; feriados; outras regras.
4. Cadastros e classificações: categoria/estrelas, MTur/CADASTUR (sim/não), associações e sindicatos, guias turísticos, outros.
5. Segmentos em que é especializado (até 3): aventura, cultural, ecoturismo, náutico, sol e praia, esporte, rural, saúde, estudos e intercâmbio, pesca, negócios e eventos, nenhum.
6. Caracterização do fluxo: ocupação ano, ocupação alta temporada, meses de alta temporada, origem (entorno, estadual, nacional, internacional), até 5 estados e até 5 países de origem.
7. Instalações: estacionamento (pago, gratuito, coberto, descoberto, capacidade por tipo de veículo), energia (voltagem, KVA, gerador), área de alimentação, recreação e lazer, área de eventos (espaço, quantidade, área m², capacidade), facilidades e serviços (longas listas de checkbox).
8. Estado geral de conservação: **Muito bom, Bom, Ruim** (atenção: o documento 02 usa OTIMO, BOM, REGULAR, RUIM).
9. Acessibilidade (bloco padronizado e extenso, igual em todas as fichas): possui facilidade? pessoal capacitado (física, auditiva, visual, mental, múltipla); rota externa acessível; símbolo internacional de acesso; embarque e desembarque; vaga em estacionamento; circulação interna; escada; rampa; piso; elevador; equipamento motorizado; sinalização visual; sinalização tátil; alarme de emergência (sonoro, visual, vibratório); comunicação (Braille, fonte ampliada, Libras); balcão; mobiliário; sanitário; telefone; sinalização de atendimento preferencial. Cada item é uma lista de opções (multi-seleção com "Não").
10. Observações.

**Específicos de B.1 Hospedagem**: tipo de diária (sem alimentação, café da manhã, meia pensão, pensão completa, tudo incluído), formas de pagamento, reservas (balcão, telefone, internet, agência), restrições (crianças, fumantes, animais), UH (total de UH, total de leitos, UH adaptadas PCD), produtos de higiene, equipamentos e serviços da UH (cerca de 35 itens), restaurante e lanchonete (aberto ao público ou só hóspedes, capacidade simultânea e diária), recreação (piscina, quadras etc.), outras atividades (observação de fauna e flora, cicloturismo, trilhas...), área para eventos, facilidades para executivos.

**Específicos de atrativos (ex.: C.1.4 Hidrografia)**: estrutura de visitação (finalidade, agendada/autoguiada/guiada: não, opcional, obrigatória), entrada (gratuita, paga inteira/meia), instalações de entrada (centro de recepção, posto de informação, portaria, guarita, bilheteria), apoio à comercialização (integra roteiros comercializados, até 5 roteiros com site; integra guia turístico, até 5 guias), outras instalações e serviços, **atividades existentes** (tabela sim/não + disponibilidade de equipamento: arvorismo, boia-cross, caminhada, canoagem, cavalgada, ciclismo, escalada, mergulho, mountain bike, rafting, rapel, trilha etc.), aspectos físicos (extensão, orla, praia, água: característica, utilização, finalidade, termal/mineral, propriedade, volume, profundidade), relevo, flora (bioma; espécies endêmicas, raras, em extinção, exóticas com melhores meses de observação), fauna (idem), atividade econômica do entorno, descritivo das especificidades.

Implicação para o sistema: os formulários são muito extensos e cheios de listas de checkbox, o que confirma a estratégia do documento 02 de **núcleo fixo + atributos dinâmicos (EAV) via `mtur_atributos_catalogo`**, com blocos comuns reaproveitados (localização, funcionamento, cadastros, fluxo, conservação, acessibilidade) e blocos específicos por código de ficha.

### 4.4 Outros itens das subpastas
- `Notas Start Point` (Google Doc, lido): anotações do início do projeto (2023): importar dados vindos de Google Forms; blindar o sistema contra cópia; estagiária montaria os formulários; "abrir abas a partir do CNPJ" (pré-cadastro por CNPJ, que virou o PRE_CADASTRO_OFICIAL); usar dados fictícios para teste; página de rosto com instruções e tipos de equipamentos que podem ser inseridos, com logo da Prefeitura; textos de LGPD e rodapé com número do contrato/convênio (SEBRAE); divulgação por modal no site da prefeitura e por parceiros (SBHRS, ACEPB, Câmara).
- `18022023 - Start point.jpg`: foto da reunião de início do projeto (fevereiro de 2023), dois sócios numa mesa de trabalho com notebooks e documentos. Não é referência visual de interface.
- Não abertos por limite de tempo (registrar para leitura futura, se necessário): subpastas `Smart Tour`, `Projetos concorrentes - Centelha`, `Metodologia Inventariação`, `mais ideias`, `EventCity` (dentro de WebApp); `centelha`, `Inventariação 2011`, `Inventariação 2006`, `Exemplos Inventário Pesquisa Demanda Plano` (dentro de InvenTour); arquivos `CanvasTurismo.pptx`, `CatalogoSoluesTecnolgicas_DTIBrasil_230317_225005.pdf`, `inventour.pptx` e `39 - CARTILHA TURISMO 360.pdf`.

---

## 5. Documentos metodológicos

### 5.1 Arquivos 06: Diagnóstico DTI (base do módulo de diagnóstico)

Há **duas metodologias diferentes** na pasta, e o sistema precisa decidir qual (ou ambas) suportar:
1. **Sebrae** (06a, 06b, 06c): "Sistema de Avaliação e Desenvolvimento de Destinos Turísticos Inteligentes, Hierarquia dos Destinos Turísticos", indicadores objetivos (sim/não e faixas percentuais), 4 dimensões (Tecnologia, Governança, Experiência do Turista, Sustentabilidade com 4 subdimensões), níveis 1 a 5. É a que gerou a nota de Pato Branco.
2. **MTur DTI Brasil** (06f, 06d e 06e): sistema de gestão no estilo ISO, **9 eixos** e **105 requisitos**, avaliados por auditoria de conformidade em escala 1 a 5. É a que o Módulo 6 do documento 02 cita ("9 eixos oficiais").

#### 06a: Diagnóstico Maturidade DTI, Orientações 2022 (Sebrae, 17 páginas, lido)
- Manual de uso da "Ferramenta de Diagnósticos" do Sebrae (diagnostico.sebrae.com.br): adesão, integração com o SAS do Sebrae, cadastro de consultor, cadastro do município como "empresa" pelo CNPJ da prefeitura (CEP correto é obrigatório), aplicação da avaliação "Maturidade de Destinos Turísticos Inteligentes" (salva a cada resposta, pode ser retomada; acesso também por link e QR Code).
- Orientação de preenchimento: coletar com poder público, empresas e terceiro setor e bases estaduais e nacionais; registrar **evidências** de cada resposta; usar o formulário padrão da pesquisa.
- Devolutiva: relatório por município com índices e orientações; **níveis de maturidade de 1 a 5 por dimensão**; painéis por índice geral e por dimensão, por município, grupo de municípios (rota), estado, região e país.
- Quatro dimensões: experiência, tecnologia, sustentabilidade, governança.

#### 06b: DTI Pato Branco, Diagnóstico versão 2 (Sebrae SC/PR, 79 páginas, lido por extração)
Relatório preenchido por Anery Junior Baggio em 25/10/2022. Cada indicador tem: código (ex.: "DTI Tec 1"), tabela Sim/Não por item, campos descritivos, **fonte de informações**, **observação**, **data da informação**. Isso mapeia bem para `dti_diagnostico_respostas` com evidência.

Indicadores (lista completa):
- **Tecnologia** (9): Tec1 software de gestão de dados do turismo; Tec2 website do destino responsivo e atualizado (menos de 90 dias); Tec3 página oficial em rede social exclusiva para turista; Tec4 aplicativo ou PWA; Tec5 pelo menos 80% dos atrativos com Wi-Fi gratuito ou bom sinal 3G/4G; Tec6 CAT com Wi-Fi, computador ou sinal (100%); Tec7 sinalização interpretativa com QR Code em pelo menos 20% dos atrativos; Tec8 uso turístico de geolocalização; Tec9 realidade virtual ou aumentada.
- **Governança** (12): Gov1 Conselho Municipal de Turismo; Gov2 Fundo Municipal de Turismo ativo; Gov3 Plano ou Política Municipal de Turismo; Gov4 órgão municipal de turismo; Gov5 participação em projetos/planos regionais (24 meses); Gov6 cooperação para acesso a recursos estaduais/federais (24 meses); Gov7 participa de IGR reconhecida pelo MTur; Gov8 consórcio intermunicipal de turismo; Gov9 núcleos empresariais de turismo; Gov10 turismólogos no órgão de turismo; Gov11 capacitação dos colaboradores (12 meses); Gov12 hospedagens no CADASTUR em relação ao cadastro da prefeitura (mais de 50%).
- **Experiência do Turista** (7): Exp1 experiências turísticas e gastronômicas diferenciadas; Exp2 apps/sites para promover e comercializar experiências; Exp3 economia compartilhada (Uber, AirBnb...); Exp4 polo de inovação tecnológica a até 100 km; Exp5 eventos de fomento à inovação com impacto no turismo (24 meses); Exp6 incentivos públicos municipais à inovação; Exp7 reputação online (ex.: 20% mais bem avaliados com nota 8,5 ou mais no Booking, 4,5 em atrativos).
- **Sustentabilidade Ambiental**: SA1 coleta de lixo; SA2 coleta seletiva; SA3 % domicílios com água; SA4 % domicílios com esgoto; SA5 iluminação LED nas áreas turísticas; SA6 sensor de movimento (EXCLUÍDO); SA7 transporte público com combustível alternativo; SA8 (lei de gestão ambiental; áreas do SNUC com visitação; cooperativa de reciclagem).
- **Sustentabilidade Social**: SSoc1 IDHM; SSoc2 parcela da população ocupada; SSoc3 empregos em alojamento e alimentação sobre o total (CAGED); SSoc4 atrativos culturais tombados.
- **Sustentabilidade Econômica**: SE1 PIB per capita; SE2 % empresas de alojamento e alimentação; SE3 IPTU (EXCLUÍDO); SE4 % ISS das empresas de turismo; SE5 % do orçamento do órgão de turismo no orçamento municipal; SE6 participação do turismo no PIB; SE7 participação de MEI e pequenas empresas no turismo.
- **Acessibilidade** (10): Acess1 transporte público adaptado; Acess2 sinais sonoros de trânsito; Acess3 calçadas adaptadas no entorno do CAT e dos 10 principais atrativos; Acess4 atrativos adaptados; Acess5 empresas de A&B adaptadas; Acess6 meios de hospedagem adaptados; Acess7 passeios adaptados; Acess8 website adaptado; Acess9 CAT adaptado com Libras; Acess10 sanitários públicos adaptados.

**Sistema de pontuação (capítulo 4 do 06b)**:
- Tecnologia: conta os "sim" dos 9 itens. Até 2 = Nível 1; mais de 2 até 4 = 2; mais de 4 até 6 = 3; mais de 6 até 8 = 4; mais de 8 = 5.
- Governança: conta os "sim" dos 12 itens. Até 3 = 1; mais de 3 até 5 = 2; mais de 5 até 7 = 3; mais de 7 até 9 = 4; mais de 9 = 5.
- Experiência do Turista: conta os "sim". Até 2 = 1; mais de 2 até 3 = 2; mais de 3 até 4 = 3; mais de 4 até 5 = 4; mais de 5 até 7 = 5.
- Ambiental: cada indicador vira 1 a 5 pontos por faixas (ex.: coleta de lixo e coleta seletiva, LED, frota alternativa: até 20% = 1, até 40% = 2, até 60% = 3, até 80% = 4, acima = 5; água: até 60% = 1, até 70% = 2, até 80% = 3, até 90% = 4, acima de 90% = 5; esgoto: faixas de 20%); SA8 composto: 1 sim = 3 pontos, 2 sim = 4, 3 sim = 5. Média = soma de 7 indicadores / 7. Nível: até 1 = 1; até 2 = 2; até 3 = 3; até 4 = 4; acima de 4 = 5.
- Social: IDHM (até 0,68 = 1; até 0,72 = 2; até 0,76 = 3; até 0,80 = 4; acima = 5); população ocupada (até 30% = 1; até 35% = 2; até 40% = 3; até 45% = 4; acima = 5); empregos em alojamento e alimentação (faixas de 2%: até 2% = 1 ... acima de 8% = 5); tombados (até 1 = 1; até 4 = 2; até 6 = 3; até 8 = 4; acima = 5). Média / 4, mesmos cortes de nível.
- Econômica: PIB per capita (faixas de R$ 10 mil: até 10 mil = 1 ... acima de 40 mil = 5); % empresas de A&A (faixas de 5%: até 5% = 1 ... acima de 20% = 5); ISS (faixas de 2%); orçamento do órgão de turismo (faixas de 1%: até 1% = 1 ... acima de 4% = 5); turismo no PIB (faixas de 2%); MEI/pequenas (acima de 99% = 1; 98 a 99% = 2; 97 a 98% = 3; 96 a 97% = 4; abaixo de 96% = 5). Média / 6. Nível: até 2 = 1; até 3 = 2; até 4 = 3; até 5 = 4; acima = 5 (cortes do documento, que parecem inconsistentes com a escala 1 a 5).
- Acessibilidade: Acess1 a Acess7 por faixas de 20% (1 a 5 pontos); Acess8 a Acess10 composto (0 sim = 1; 1 sim = 3; 2 sim = 4; 3 sim = 5). Média / 8. Nível: até 3 = 1; até 5 = 2; até 6 = 3; até 7 = 4; até 8 = 5 (cortes também inconsistentes).
- **Pesos** (capítulo 6): Tecnologia 2, Governança 2, Experiência 2, Ambiental 2, Social 1, Econômica 2, Acessibilidade 1 (soma 12).
- **Fórmula**: `Resultado = (Tec x 2 + Gov x 2 + Exp x 2 + SAmb x 2 + SSoc x 1 + SEco x 2 + Acess x 1) / 12`, onde cada termo é o indicador da dimensão (o texto mistura a contagem de "sim" com o nível; a leitura coerente é usar o **nível 1 a 5** de cada dimensão, o que dá resultado final de 1 a 5).
- Critérios de validação de atrativo (cap. 5): enquadramento na hierarquização CICATUR/OEA, vias de acesso, grau de conservação, qualidade do entorno, sinalização de identificação, serviços e equipamentos no local, segurança. "Área turística": parcela do território onde se concentram atrativos e serviços.
- Plano de ações (cap. 7): vertentes pública e privada, por dimensão, com responsável (ex.: "Consultor de TI"), custos e prazos; inclui dimensionamento de investimentos em QR Code, beacons e Wi-Fi nos atrativos.

#### 06c: planilha "Diagnóstico DTI, Pato Branco, Nota 71,63, 08 de dezembro" (xlsx)
- Lida pela visualização do Drive (só as primeiras ~40 linhas têm camada de texto; o restante foi lido por amostragem com zoom em imagem). Abrir no Planilhas para leitura completa não foi possível sem converter o arquivo.
- **4 abas, nomeadas por data**: `12 12 2022` (aberta), `05 12 2022`, `28 11 2022`, `27 09 2022`; são versões sucessivas do mesmo diagnóstico.
- Colunas: **Tema** (dimensão), **Pergunta**, **Tipo** (Texto curto, Sim/Não, Resposta única, Múltiplas respostas, Grade de múltipla escolha), **Resposta**; em várias linhas há o bloco "Evidência: ..." com a fonte (ex.: "Conselho Municipal de Turismo e Divisão de Turismo da Prefeitura..."). Colunas E a H praticamente vazias.
- Temas na ordem: IDENTIFICAÇÃO DO DESTINO (município, UF, região turística "Vales do Iguaçu, Sudoeste do Paraná", está no Mapa do Turismo, categorização "Categoria B"), Dimensão Tecnologia, Dimensão Governança, Dimensão Experiência do Turista, Dimensão Sustentabilidade Ambiental, Social, Econômica e Acessibilidade. Cerca de 230 linhas; cada indicador ocupa várias linhas (pergunta principal Sim/Não + campos de detalhe + faixa escolhida).
- Respostas de faixa já vêm como opções (ex.: "80% ou mais", "+40% até 60% das empresas", "Até 2,5%", "+0,76 até 0,80").
- **Composição da nota 71,63**: a planilha só traz "total 71,63" em vermelho na última linha (linha 232), **sem fórmula nem quebra por dimensão visível**. A nota está numa escala de 0 a 100, enquanto a fórmula do 06b dá 1 a 5; portanto ela vem do cálculo da Ferramenta de Diagnósticos do Sebrae (06a), que pontua as respostas internamente. Hipótese a confirmar com o Anery: 71,63 = percentual da pontuação ponderada máxima (por exemplo, resultado de 1 a 5 convertido para percentual). Não deu para reconstituir a conta só com o que está na planilha.

#### 06f: DTI Brasil, Manual Metodológico 2022 (MTur, 73 páginas, lido por extração)
- Parceria MTur com o Instituto Ciudades del Futuro (Argentina) e a SEGITTUR (Espanha), testado em 10 destinos piloto; baseado na norma espanhola UNE 178.501/178.502.
- Estrutura de sistema de gestão (estilo ISO): cap. 1 a 3 (propósito, referências, termos e definições); cap. 4 Contexto (escopo, partes interessadas, **diagnóstico de situação**, processos); cap. 5 Liderança (política DTI, papéis); cap. 6 Planejamento (riscos, objetivos); cap. 7 Apoio (recursos, competências, comunicação, informação documentada); cap. 8 Operação (requisitos dos eixos); cap. 9 Avaliação de desempenho (satisfação de turistas e prestadores, variáveis de mercado, rastreamento dos eixos, **app ou plataforma de monitoramento**, auditoria interna, revisão gerencial); cap. 10 Aperfeiçoamento; Anexo 1 (metodologia de avaliação de conformidade); Anexo 2 (Matriz de Avaliação de Requisitos para o Plano de Transformação, com colunas Capítulo, Subcapítulo, Eixo, Subeixo, Requisito).
- **9 eixos e subeixos com os números dos requisitos**: Governança (31 a 37: governança, gestão digital, planejamento urbano e usos do solo, participação e gestão aberta); Inovação (38 a 41); Tecnologia (42 a 49: infraestrutura de telecomunicações; aplicativos e plataformas, incluindo o 46 "digitalização da oferta de serviços e recursos turísticos" e o 49 proteção de dados pessoais); Acessibilidade (50 a 53); Sustentabilidade (54 a 68: economia, ambiente, social); Criatividade (69 a 73: criatividade, cultura); Mobilidade e Transporte (74 a 78); Promoção e Marketing (79 a 82, incluindo publicizar a acessibilidade); Segurança (83 a 89: sanitária, pública, prevenção da exploração de crianças e adolescentes). Requisitos 1 a 30 são de gestão (cap. 4 a 8.1) e 90 a 105 de avaliação e melhoria. Total: **105 requisitos**.
- **Escala de avaliação (Anexo 1)**: 1 = requisito não conceituado (desconhecido); 2 = não conformidade; 3 = observação (não conformidade menor, cumprimento parcial); 4 = conformidade estrita; 5 = conformidade plena (excelência). Pontuação mínima para distinção: média 3, com esforço comprovado de chegar a 4. Auditor validado pelo MTur, diretrizes ISO 19.011.
- Para o InvenTour: o requisito 46 (digitalização da oferta), o 44/45/47 (plataformas), o 50/82 (informação de acessibilidade), o 91/92 (satisfação de turistas e prestadores) e o 96 (plataforma de monitoramento) são diretamente atendidos pelo sistema; a matriz do Anexo 2 é um bom modelo de tabela para o diagnóstico DTI (requisito, eixo, subeixo, nota 1 a 5, evidência, plano de ação).

#### 06d: Projeto ABNT NBR 17259 (consulta nacional, agosto de 2025, 31 páginas, lido o sumário)
- "Turismo e serviços relacionados: Sistema de gestão para destinos turísticos inteligentes, Requisitos e recomendações". Sem valor normativo (projeto).
- Mesma estrutura do manual MTur, agora com **9 eixos** com nomes levemente diferentes: governança (governança, gestão digital, planejamento urbano, participação e gestão aberta); inovação; tecnologia (telecomunicações, aplicativos e plataformas, **segurança cibernética e proteção de dados**); acessibilidade; sustentabilidade (economia, ambiente, social); criatividade (criatividade, cultura); **mobilidade**; **marketing**; segurança (sanitária, pública, exploração de crianças e adolescentes, **segurança para as mulheres**, **segurança para atividades de turismo de aventura**). Novo item 8.3 (atividades, produtos ou serviços novos ou modificados) e 9.1.1.2 "convém que o sistema de gestão de DTI seja uma plataforma integrada".
- Usa "organização" no lugar de "órgão gestor".

#### 06e: Cartilha DTI (MTur, 28 páginas)
- PDF sem camada de texto (só imagens). Visto parcialmente: material de divulgação ("O que é um Destino Turístico Inteligente?", "Como surgiu o conceito", SEGITTUR 2012...). Útil como conteúdo explicativo para o gestor, não traz regra de cálculo. Não lido por completo.

**Conclusões para o módulo de diagnóstico DTI**:
- O ENUM do documento 02 (`GOVERNANCA`, `INOVACAO`, `TECNOLOGIA`, `ACESSIBILIDADE`, `SUSTENTABILIDADE`, `CREATIVIDADE`, `MOBILIDADE`, `PROMOCAO_MARKETING`, `SEGURANCA`) corresponde ao MTur/ABNT (corrigir para `CRIATIVIDADE`).
- A escala real do MTur é 1 a 5 por requisito (não 0 a 10 por eixo); a do Sebrae é nível 1 a 5 por dimensão com nota final em percentual na ferramenta.
- Sugestão de modelagem: catálogo de metodologias (`dti_metodologias`: SEBRAE_2022, MTUR_2022, ABNT_17259), catálogo de indicadores/requisitos com eixo, subeixo, tipo de resposta, faixas de pontuação e peso, e respostas por ciclo com evidência, fonte, data da informação e plano de ação. Vários indicadores Sebrae podem ser calculados automaticamente pelo InvenTour (Tec2, Tec4, Tec7, Gov1 a Gov4, Gov12, Exp1, Exp2, Acess4 a Acess6, Acess8, SSoc4).

### 5.2 Documento 03: Inventário da Oferta Turística (MTur, 2011), ABC, 54 fichas
Arquivo `03 Inventour - Inventário (2011) ABC 54 Fichas.pdf` (ID 1UPAbITIkuwuyJW0TN9KvEdp-BDwE9X0c), 393 páginas, lido por extração de texto (cerca de 700 mil caracteres, analisado por estrutura). Segundo o documento 00, é a estrutura vigente usada pelos inventariantes (sem manual próprio), embora exista a versão 2019 dos formulários (seção 4.3); a numeração das fichas é a que o documento 02 usa (A1, B1, B5, C1...). Cabeçalho de toda ficha: UF, região turística, município; título "CATEGORIA Xn - NOME"; bloco "IDENTIFICAÇÃO / Tipo:" com as opções numeradas do tipo/subtipo.

**As 54 fichas (página inicial, código, tipos cobertos)**:

| # | Pág. | Cat. | Ficha (tipos) |
|---|---|---|---|
| 1 | 1 | A1 | A.1.1 Caracterização do município |
| 2 | 13 | A2 | A.2.1.1 Rodovia |
| 3 | 16 | A2 | A.2.1.2 Estação rodoviária, A.2.1.3 Terminal rodoviário |
| 4 | 24 | A2 | A.2.2.1 Ferrovia, A.2.2.2 Metrovia |
| 5 | 26 | A2 | A.2.2.3 Estação ferroviária, A.2.2.4 Estação metroviária |
| 6 | 35 | A2 | A.2.3.1 Aeroporto, A.2.3.3 Heliporto, A.2.3.4 Outros |
| 7 | 43 | A2 | A.2.3.2 Campo de pouso |
| 8 | 52 | A2 | A.2.4.1 Hidrovia |
| 9 | 54 | A2 | A.2.4.2 a A.2.4.8 Porto, píer, cais, marina, terminal turístico, clube náutico, outros |
| 10 | 63 | A3 | Sistema de comunicação: agência postal, posto telefônico, rádio, TV, jornal, revista, outros |
| 11 | 66 | A4 | Sistema de segurança: Polícia Civil, Militar, Rodoviária, Bombeiros, busca e salvamento, polícia marítima/aérea/fronteiras, Guarda Municipal, Defesa Civil, outros |
| 12 | 69 | A5 | Sistema de saúde: pronto-socorro, hospital, clínica, maternidade, posto de saúde, farmácia, odontológica, veterinária, outros |
| 13 | 73 | A6 | Caracterização do sistema de educação |
| 14 | 80 | A7 | A.7.1 Locadoras de imóveis para temporada |
| 15 | 83 | A7 | A.7.2 Compras especiais: feira/mercado, galeria/rua comercial, shopping, plantas/flores/frutas, antiquário, cantina/cave, bodega/alambique, outras |
| 16 | 89 | A7 | A.7.3 Comércio turístico: artesanato/souvenir, artigos fotográficos, antiquário/galeria de arte, outros |
| 17 | 95 | A7 | A.7.4 Serviços bancários: agência/posto, casa de câmbio |
| 18 | 98 | A7 | A.7.5 Serviços mecânicos (automóvel, moto, ônibus/caminhão, embarcações) e A.7.6 Posto de combustível |
| 19 | 102 | A7 | A.7.7 Representações diplomáticas: embaixada, consulado, outras |
| 20 | 105 | B1 | B.1.1 Hoteleira e de apoio: hotel, hotel histórico, hotel fazenda, resort, pousada, flat/apart-hotel, cama e café, albergue, alojamento de floresta, pensão, motel, colônia de férias, estalagem/hospedaria, alojamento coletivo, hospedagem conventual, hospedagem familiar, hotel de trânsito, spa, outros (19 subtipos) |
| 21 | 115 | B1 | B.1.2 Outro tipo de acomodação: acampamento turístico/camping |
| 22 | 125 | B2 | Alimentos e bebidas: restaurante, bar, lanchonete, cafeteria, quiosque, barraca de praia, sorveteria, confeitaria/padaria, outros |
| 23 | 132 | B3 | Agências: de viagem, operadora, receptivo, consolidadora |
| 24 | 142 | B4 | Transporte turístico: transportadora turística, locadora de veículos, outros |
| 25 | 149 | B5 | B.5.1 Espaços para eventos: centro de convenções e feiras, parque/pavilhão de exposições, auditório/salão, outros |
| 26 | 158 | B5 | B.5.2 Serviços para eventos: organizadora, promotora, outros especializados |
| 27 | 162 | B6 | B.6.1 Parques: aquático, temático, diversões, outros |
| 28 | 170 | B6 | B.6.2 Espaços livres e áreas verdes: praça, jardim, parque, mirante, largo, outros |
| 29 | 177 | B6 | B.6.3 Instalações esportivas (17 subtipos: estádio, ginásio, quadra, golfe, futebol, paintball, autódromo, kartódromo, velódromo, hipódromo, equitação, boliche, patinação, skate, rampa de voo livre, piscina, outras) |
| 30 | 186 | B6 | B.6.4 Instalações náuticas: marina, píer, garagem náutica, clube náutico, cais, outras |
| 31 | 194 | B6 | B.6.5 Espaços de diversão e cultura: boate, casa de shows, casa de dança, cinema, clube social, centro de tradições, outros |
| 32 | 201 | B6 | B.6.6 Outros espaços de recreação: pesque e pague, pesque e solte, colha e pague, sítios/chácaras de lazer, outros |
| 33 | 208 | B7 | B.7.1 Informações turísticas: posto, centro de atendimento, central, outros |
| 34 | 214 | B7 | B.7.2 Entidades associativas e similares |
| 35 | 220 | B7 | B.7.3 Guiamento e condução turística: guia de turismo, outros |
| 36 | 223 | C1 | C.1.1 Relevo continental (13 subtipos: montanha, serra, morro, pico, chapada, tabuleiro, patamar, matacão, vale, planalto, planície, depressão, outros) |
| 37 | 235 | C1 | C.1.2 Zona costeira (14 subtipos: restinga, duna, barreira, praia, mangue, estuário, falésia, baía, península, recife, ilha, arquipélago, barra, outros) |
| 38 | 247 | C1 | C.1.3 Relevo cárstico: caverna, gruta, furna, dolina |
| 39 | 258 | C1 | C.1.4 Hidrografia (rio, riacho, córrego, arroio, lago, alagado, fonte, outros) e C.1.5 Queda d'água (catarata, cachoeira, salto, cascata, corredeira, outras) |
| 40 | 270 | C1 | C.1.6 Unidades de conservação e similares (15 subtipos, incluindo zoológico e jardim botânico) |
| 41 | 281 | C2 | C.2.1 Conjuntos arquitetônicos: urbano, rural, industrial, ferroviário, outros |
| 42 | 290 | C2 | C.2.2 a C.2.6: comunidades tradicionais (quilombola, indígena, ribeirinha, de imigração, extrativista), sítios arqueológicos, sítios paleontológicos, itinerários culturais, parques históricos (inclui geoparque) |
| 43 | 299 | C2 | C.2.7 Lugares de manifestação de fé, C.2.8 Lugares de referência à memória (e C.2.9 Feiras e mercados de caráter cultural) |
| 44 | 308 | C2 | C.2.10 Arquitetura civil (14 subtipos) |
| 45 | 318 | C2 | C.2.11 Arquitetura oficial, C.2.12 militar, C.2.13 religiosa |
| 46 | 328 | C2 | C.2.14 Arquitetura industrial/agrícola, C.2.15 funerária, C.2.16 marcos históricos, C.2.17 obras de infraestrutura |
| 47 | 339 | C2 | C.2.18 Obras de interesse artístico (bens integrados) |
| 48 | 348 | C2 | C.2.19 Ruínas, C.2.20 Museu/memorial, C.2.21 Biblioteca, C.2.22 Centros culturais/galerias, C.2.23 Teatros, C.2.24 Cineclubes |
| 49 | 359 | C2 | C.2.25 Gastronomia típica, C.2.26 Artesanato (22 técnicas), C.2.27 Atividades tradicionais de trabalho |
| 50 | 365 | C2 | C.2.28 Formas de expressão: música, dança, literária/oral, cênica, outras |
| 51 | 371 | C2 | C.2.29 Personalidades |
| 52 | 375 | C3 | Atividades econômicas: extrativista, agropecuária, comercial, industrial, tecnológica (com subtipos) |
| 53 | 379 | C4 | Realizações técnicas e científicas: parque tecnológico, centro de pesquisa, usinas (hidrelétrica, termo, nuclear, eólica, solar), eclusa/açude, planetário, aquário, viveiro |
| 54 | 388 | C5 | Eventos programados: feiras, congressos, convenções, festivais, seminários, oficinas, competições, desfiles, encontros temáticos, festas e celebrações (religiosa, popular, ciclo produtivo, cívica) |

No total são cerca de 500 opções de tipo/subtipo, que formam a **tabela de taxonomia** (`codigo_ficha` > tipo > subtipo).

**Seções comuns** (presentes em 33 a 54 fichas, mesma ordem):
1. INFORMAÇÕES GERAIS (54 fichas)
2. FUNCIONAMENTO (41)
3. CARACTERÍSTICAS (41)
4. PROTEÇÃO, QUALIFICAÇÃO, CERTIFICAÇÃO, PREMIAÇÃO, DESTAQUES E OUTROS (36)
5. ESTADO GERAL DE CONSERVAÇÃO (34): opções **(1) Muito bom (2) Bom (3) Ruim**
6. ACESSIBILIDADE (34): 21 subitens (pessoal capacitado, rota externa, símbolo de acesso, embarque, vaga, circulação interna, escada, rampa, piso, elevador, equipamento motorizado, sinalização visual e tátil, alarme, comunicação, balcão, mobiliário, sanitário, telefone, sinalização de atendimento preferencial, outras)
7. OBSERVAÇÕES
8. REFERÊNCIAS (fontes bibliográficas)
9. EQUIPE RESPONSÁVEL: responsável pelo preenchimento (pesquisador) com telefone e e-mail; responsável pela conferência (coordenador) com telefone e e-mail. Isso já é o fluxo pesquisador > coordenador do Módulo 3.

Fichas mais simples (A3 a A7, B7.2, C2.29 etc.) usam versões reduzidas (Informações gerais, Características, Observações, Referências, Equipe).

**Exemplo de estrutura detalhada (B2 Alimentos e Bebidas)**:
- 1 Informações gerais: razão social, nome fantasia, CNPJ, código CNAE, atividade econômica, rede/holding, inscrição municipal, natureza, tipo de organização, início da atividade, funcionários (permanentes, temporários, % PCD), localização (urbana/rural), coordenadas (lat/long), endereço (logradouro, bairro, distrito, CEP), telefone/ramal, fax, e-mail, site, sinalização (acesso, turística), proximidades, distâncias em km (aeroporto, rodoviária, ferroviária, marítima, metrô, ônibus, táxi), pontos de referência, cadastros (MTur CADASTUR, associações e sindicatos do setor, associações de turismo, associações comerciais, guias, outros).
- 2 Funcionamento: formas de pagamento; vendas e reservas; atendimento em língua estrangeira; informativos impressos; período; horário (abertura, encerramento, 24h, feriados); restrições (crianças, fumantes, animais); outras regras; capacidade de atendimento (por dia e simultânea, pessoas sentadas).
- 3 Características: estacionamento (automóveis, ônibus), serviços e equipamentos, especificação da gastronomia (por país, região brasileira, especialização, tipo de dieta, tipo de serviço).
- 4 Proteção, qualificação, certificação: do equipamento e da área/edificação, por esfera (municipal, estadual, federal, internacional), cada uma com Lei/Decreto, Portaria/Instrução, Norma/Ato, Outros.
- 5 a 9 comuns.

Para o sistema: (a) a lista acima é a semente de `inventario_taxonomia`; (b) as seções 1, 2, 4, 5, 6, 7, 8 e 9 são o "núcleo comum" de `inventario_itens` e tabelas satélites; a seção 3 (Características) é a parte específica que vai para o EAV; (c) "Referências" e "Equipe responsável" alimentam o PDF oficial (Módulo 4) e a trilha de auditoria (Módulo 3).

### 5.3 Documento 04: Inventário (2006), Estrutura, Metodologia e Descrições (Manual do Pesquisador)
Arquivo `04 Inventour - Inventário (2006) Estrutura Metodologia e Descrições.pdf` (ID 1McNDar4d4jFxprHCkU_Cwxz07-O5VZT4), 237 páginas, MTur, Programa de Regionalização do Turismo "Roteiros do Brasil", dezembro de 2006 (projeto piloto no RS). Lido por extração de texto (estrutura e trechos de orientação).

Estrutura do manual:
- Apresentação: contextualização, base conceitual (território, sustentabilidade, Plano Nacional de Turismo), terminologia da oferta turística.
- I. Classificação da Oferta Turística: tabelas Categoria > Tipo > Subtipo para A (infraestrutura de apoio), B (serviços e equipamentos) e C (atrativos), com "características relevantes" nos atrativos naturais.
- II. Formulários e instruções, com **19 formulários** (na versão de 2006): A1 Form. 01 Informações Básicas do Município; A2 02 Meios de Acesso; A3 03 Comunicações; A4 04 Segurança; A5 05 Médico-Hospitalar; A6 06 Educacional; A7 07 Outros Serviços de Apoio; B1 08 Hospedagem; B2 09 Gastronomia; B3 10 Agenciamento; B4 11 Transporte; B5 12 Eventos; B6 13 Lazer e Entretenimento; B7 14 Outros Serviços Turísticos; C1 15 Atrativos Naturais; C2 16 Atrativos Culturais; C3 17 Atividades Econômicas; C4 18 Realizações Técnicas, Científicas ou Artísticas; C5 19 Eventos Permanentes.
- Para cada categoria: página de abertura, **definição de cada tipo e subtipo** (ex.: "A.1.2 Aspectos Históricos: fatos ocorridos em determinado tempo, que fazem parte da memória ou do cotidiano de uma comunidade"; "A.1.3.2 Gestão do turismo: parte da administração municipal responsável pela política municipal de turismo") e **instrução campo a campo** (ex.: "1.3 Telefones importantes: no máximo dez números: polícia, bombeiro, ambulância, centro de informações turísticas, transporte"; "1.4 Site: no máximo 3"; "2.5 Temperaturas: média, máxima e mínima do último ano"; "2.7 Clima: descrever de forma completa").
- Bibliografia e equipe técnica.

**Orientações ao pesquisador** (viram ajuda na tela e regras de validação):
- Levantamento em duas etapas: **pesquisa de laboratório** (dados secundários: publicações, documentos, internet) e **pesquisa de campo** (verificação in loco, complementação e atualização). Dados de laboratório pré-preenchidos e confirmados em campo (equivale ao PRE_CADASTRO_OFICIAL > RASCUNHO_CAMPO).
- Siglas obrigatórias quando não houver resposta: **NA** (não se aplica), **NI** (não informou/recusou), **NE** (não existe, apesar de buscado). Nenhum campo fica em branco sem justificativa nas observações. Sugestão: o formulário digital deve oferecer esses três estados em qualquer campo.
- Gastronomia e espaços de eventos dentro de meios de hospedagem: se exclusivos para hóspedes, registrar só na ficha de hospedagem; se abertos ao público, registrar também na ficha própria (B2 ou B5). Regra útil para vincular itens (item pai e item filho).
- Usar GPS para latitude e longitude dos atrativos naturais.
- Inventariar **todos os estabelecimentos abertos ao público, mesmo sem CNPJ**.
- Formulários extras como "Continuação" quando faltar espaço (no digital: campos repetíveis).

Diferenças relevantes entre as versões: 2006 tem 19 formulários e classificações próprias (ex.: B1 dividido em "com necessidade de cadastro", "sem necessidade", "extra-hoteleiros"); 2011 tem 54 fichas com tipos mais granulares e seções padronizadas; 2019 reorganiza em formulários A.1 a C.5.1 mantendo os blocos comuns. O Luiz quer preservar a capitulação (códigos A, B, C) e aproveitar descrições úteis da metodologia antiga.

---

## 6. Documento 00: Pacote de Especificação Técnica e Negócio (guia de leitura)
Arquivo `00 InvenTour - Pacote de Especificação Técnica e Negócio.docx` (ID 1h7eSbxICBiL_KpqioH6U4492Q_2WL1b_), cerca de 14 mil caracteres, lido por completo. Não estava na lista da tarefa, mas está na pasta e orienta como usar os demais.

- Destinatário: Luiz e equipe Softsul. InvenTour é "sistema satélite nativo do ecossistema **Orbit** (Softsul)", multi-tenant, com integração bidirecional com o EventCity.
- Ordem de leitura e uso de cada arquivo no código:
  - 01 Pitch: norte do produto e identidade visual (paleta azul-petróleo, verde-esmeralda, amarelo-ouro; logo rosa dos ventos com lupa e pin). Fluxo de conversão: freemium do consultor > paywall do PDF > assinatura B2G (Prefeitura/COMTUR) > upsell B2B do trade.
  - 02: os 7 módulos e o **Relatório de Análise Arquitetural e Consolidação Técnica**; as regras das **Seções C e D do relatório sobrescrevem** divergências dos módulos (tipagem de IDs na ponte EventCity, sub-tabelas repetitivas, checklist de acessibilidade em 20 grupos, LGPD no PDF, expurgo de campos obsoletos).
  - 03 (54 fichas, 2011): **não virar 54 tabelas**; blocos comuns em `inventario_itens`; perguntas específicas da Seção 3 de cada ficha viram **seeder** de `mtur_atributos_catalogo`. É a estrutura vigente, mas sem manual próprio.
  - 04 (manual 2006): base dos **textos de ajuda (tooltips, "como preencher")** para pesquisador e trade.
  - 05 (Pato Branco 2025): o PDF final **não despeja todos os campos**; omite vazios e é editorial, mas **mantém toda a capitulação**, marcando capítulos sem conteúdo como "inexistente" (ex.: não há marinas).
  - 06b: fórmulas de pontuação; priorizar a estrutura das fórmulas para validar a lógica que gerou **71,63** (houve pequenos ajustes manuais na digitação no sistema online).
  - 06c: respostas inseridas na plataforma e "**valores intermediários por célula**" usados na composição de 71,63 (na leitura pela visualização do Drive esses valores intermediários não ficaram visíveis; ver 5.1).
  - 06d, 06e: norma ABNT e cartilha como referência.
  - 06f: 9 eixos, 105 requisitos, escala 1 a 5, média mínima 3 para distinção, saída "Plano de Transformação DTI"; base do questionário, do **gráfico de radar** e do gerador do Plano de Transformação.
  - **Pato Branco é o caso de teste oficial**: as mesmas entradas devem reproduzir exatamente 71,63 (teste de regressão do algoritmo).
  - 07 Tribos: 5 macrocategorias, 21 perfis, mais de 160 tags; **não é lista fechada**, precisa de curadoria; alimenta tags do trade, filtros e roteirização do portal e o BI.
- **Observatório territorial (V1.5/V2)**: referência ao painel SITU-PR; reaproveitar conectores que a Softsul já tem no EventCity/Orbit (CADASTUR, Mapa do Turismo) para trazer RAIS/Novo CAGED (empregos por CNAE das ACTs), IBGE (população, PIB), ANAC/rodoviárias (passageiros), IPARDES; permitir dado municipal manual em paralelo ao oficial; tratar defasagem RAIS (anual) x CAGED (mensal); futuramente ISSQN e alvarás da prefeitura. Deixar o Módulo 6 preparado para esses conectores.
- **Diretrizes de implementação**: isolamento por `empresa_id` do Orbit e mesma stack do EventCity; webhook HMAC enviando `inventour_id` compatível com `inventour_equipment_id` do EventCity e mapeamento de categorias **ATTRACTION, LODGING, GASTRONOMY, EVENT_CENTER, SUPPORT_SERVICE**; **V1 = Módulos 1 a 5**, Módulo 6 na sequência imediata, Módulo 7 só com ganchos no banco e feature flags.

---

## 7. Complemento: subpastas e arquivos restantes

Leitura feita em 2026-11-20, somente leitura, pelo navegador. Estrutura real: `WebApp` contém `Smart Tour`, `Projetos concorrentes - Centelha`, `Metodologia Inventariação`, `mais ideias`, `EventCity`, `InvenTour` e os arquivos `CanvasTurismo.pptx` e o Catálogo DTI. `InvenTour` contém `pitchdeck` (com `inventour.pptx`), `inventário metodologia` (`Inventariação 2019`, `2011`, `2006`, `Exemplos...`) e `centelha`.

### 7.1 Subpasta `Smart Tour` (concorrente direto mais próximo)

Arquivos: `smart tour descrição.pdf` (caso de sucesso Sebrae/SC, 2019), `Produtos Smart Tour 4.pdf` (tabela de preços), `Apresentação 2 Smart Tour.pdf` (22 slides), `Contrato - Parceria Empresarial - modelo.docx`, `Apresentação smart Tracking.pdf`, `Turismo & COVID-19.pdf`, `Clipagem.pdf` (os três últimos não lidos).

O que fazem:
- Startup de Florianópolis (2017), foco em "geração e inteligência de dados para a gestão pública do turismo". Prêmios: finalista do Prêmio Nacional do Turismo (2018 e 2019), seleção OMT, Wakalua, Nest Portugal; incubada/acelerada por Sinapse da Inovação, StartupSC, Sebrae, InovAtiva, ReadWrite Labs, incubadora de Aveiro.
- Tecnologia: **beacons Bluetooth (IoT)** instalados nos atrativos, alcance de 70 m, app próprio (TagCity) que entrega conteúdo multi-idioma e recurso para deficientes visuais; coleta localização, perfil e tempo de permanência.
- Entregas ao gestor: pesquisa de demanda em tempo real (substitui a pesquisa com prancheta), cruzamento de dados (permanência, perfil), gestão de reputação online (reviews), "mapa turístico de deslocamento de fluxo em real time", relatórios por local e da região, redirecionamento de fluxo para locais menos visitados.
- Casos: projeto DTI da AMFRI/Costa Verde & Mar com Sebrae/SC (10 cidades, 80 beacons, cerca de 400 mil pessoas impactadas); Lei de Inovação de Florianópolis (100 pontos). Alegam mais de 500 atrativos monitorados e mais de 10 milhões de visitantes impactados.

**Preços (tabela "Produtos Smart Tour")**:
| Produto | O que é | Preço | Mínimo | Implantação | Contratação |
|---|---|---|---|---|---|
| Smart Tour (Rotas Inteligentes) | beacons + conteúdo mobile multi-idioma + consultoria de turismólogos | R$ 4.500 por atrativo/ano | 10 atrativos (R$ 45 mil/ano) | 40 dias | inexigibilidade |
| Smart Destination | o anterior + protocolo TOURQUAL 100% digital (qualidade de serviços) + coleta e análise de reviews | R$ 8.000 por atrativo/ano | 5 atrativos (R$ 40 mil/ano) | 30 dias | inexigibilidade |
| Smart Survey | pesquisa de demanda por IoT, atrativo vira coletor de dados | R$ 1.500 por atrativo/ano | 5 atrativos (R$ 7,5 mil/ano) | 15 dias | inexigibilidade ou dispensa por valor |
| Smart Tracking | rastreamento de contatos de Covid-19 | R$ 17.000/ano | | imediata | dispensa por valor |

- Contrato de parceria (modelo): a Smart Tour fecha parceria com turismóloga como representante comercial, **remuneração de até 10% do valor do contrato fechado por ela**, não exclusividade, confidencialidade e propriedade intelectual da Smart Tour. É o mesmo canal "turismólogo vende para a prefeitura" do cavalo de Troia do InvenTour.
- Diferença para o InvenTour: a Smart Tour mede a **demanda** (fluxo de visitantes, com hardware no atrativo); **não faz inventário da oferta** nem portal da oferta homologada. O InvenTour mede a demanda pelo phygital (QR Code) sem hardware a manter. Preço por atrativo encarece rápido; o InvenTour pode cobrar por porte do município.

### 7.2 Subpasta `Projetos concorrentes - Centelha`

46 PDFs numerados (061 a 120, com falhas na numeração), pitch decks de outros proponentes do **Programa Centelha Paraná 2026** (concorrentes no edital, não no mercado). Lidos pela primeira página.
- **Nenhum projeto de turismo, inventário turístico ou DTI**. Busca por "turis" na primeira página não achou nada.
- Mais próximos do espaço do InvenTour (B2G ou cidade):
  - 067 **Guarapuava Digital**: "sistema operacional de cidades inteligentes", app único cidadão x prefeitura; módulo 1 Educação Conectada (merenda, notas, rotina escolar); alvarás e licenças.
  - 100 **Gestão de Serviços Municipais via WhatsApp** (cidadão x prefeitura).
  - 095 **MonteSeuEvento** (Curitiba): marketplace de fornecedores de eventos (buffet, DJ, decoração, espaços) com pagamento em custódia. Vizinho do EventCity, não do InvenTour.
  - 069 **App Tudo Cidade**: marketplace do comércio local do interior; 113 **Preço Local**: compare preços do comércio local.
- Demais: saúde (clínicas, diabetes, obesidade, pets, dengue, auditoria médica, parto), indústria e agro (manufatura distribuída, tilápia, rastreabilidade animal, AgroMesh, ETAs/ETEs, segurança elétrica), trânsito (Noxfort), RH, beleza, social, esporte, economia criativa (Dreamz), ESG com dados públicos (062), segurança de PME.
- Leitura para o plano: no Centelha PR 2026 o InvenTour não disputa com outra solução de turismo; o argumento de inovação deve ser comparado com o mercado nacional (Smart Tour, catálogo DTI), não com esses projetos.

### 7.3 Subpasta `mais ideias`

- **`Apps Turismo - lista ideias.docx`**: três ideias de produto.
  1. Agenda cultural para bares, restaurantes e casas de entretenimento, com monetização por percentual de cupons e reservas, banners e destaque (é o embrião do EventCity).
  2. **Pesquisa de demanda** em pontos de movimentação turística (hospedagem, bilheteria de parques, restaurantes, receptivo de visita técnica, eventos): origem, classe social, instrução, motivação, permanência, gastos, frequência, próxima viagem, meio de locomoção, tipo de hospedagem, quem paga a viagem, "o que não encontrou".
  3. **Pesquisa de satisfação** nos mesmos pontos, em 5 aspectos (tangíveis; cumprimento de contrato e prazos; cordialidade; segurança técnica; empatia), monetizada pela venda de relatórios de BI para empresas, grupos ou instituições. Observação: 2 e 3 poderiam ser um só.
- **`Pato Branco Meta City.docx`** (Anery): cidade inteira no metaverso, com fachadas, zoneamento, sistema viário, locais turísticos (cardápios, visita a hotéis), eventos com check-in, avatares, apontamento de problemas urbanos, gamificação, NFTs de projetos de arquitetura, maratonas com universidades; receita por PPP, anúncios em fachadas e outdoors virtuais. Canvas incompleto (custos e recursos "a definir"). Fora do escopo do InvenTour; ideias reaproveitáveis: gamificação do cidadão que alimenta dados e apontamento de carências.

### 7.4 Subpasta `EventCity` (o que interessa à integração)

Conteúdo: subpastas `registro de marca`, `publicações` (artes e `EventCity - Publicações.docx`), `pitchdeck`, `papelaria`, `master prompt` (prompts de Notícias, Eventos e Cards), `logo`, `centelha` (inscrição e pitch do EventCity no Centelha); documentos `Proposta Comercial Chapecó`, `proposta comercial sem mudar o conceito`, `Níveis de Pacote de Valor`, `Formulário de Avaliação de Eventos`, `Conflitos de Sazonalidade`, `Ajustes`.

**Pontos de integração com o InvenTour citados**:
- `Ajustes` (capacidade hoteleira): "Na integração com o InvenTour pode puxar direto" leitos e UHs. Hoje o EventCity usa a soma do Cadastur ou parâmetros digitados (leitos, UHs, leitos ocupados por outros segmentos).
- `Ajustes` (locais de evento): campos de ambientes, layouts, acústica, dimensões, pé direito, mobiliário, capacidade em auditório/banquete/show **"ficam para a V2, com o InvenTour"**. Hoje o EventCity calcula auditório = capacidade dos bombeiros x 0,4 e banquete = x 0,25, e tem as marcas "Assentos fixos" e "Espaço aberto". Isso é a ficha B5 (Espaços para Eventos) do InvenTour.
- `Ajustes` ("Insights para futura integração InvenTour", referência caragua.tur.br/explorar): prever o EventCity como **aba integrada do futuro site de turismo**, com tag por tipo sob a imagem (evento, hospedagem, atrativo, gastronomia).
- `Ajustes` (módulo Documentos do EventCity, desligado por padrão): categorias previstas incluem **"Inventário da Oferta Turística"**, leis do turismo, instruções de alvarás e modelo de projeto de evento.
- `Proposta Chapecó`: o painel de conflitos calcula saturação "a partir do Cadastur, **ou de inserção de dados vindos do Inventário da Oferta Turística local**". O inventário já é vendido como fonte.
- `Conflitos de Sazonalidade` (feito em 02/11 no EventCity): ocupação orgânica por perfil de demanda (Negócios, Lazer, Linear, Misto) em três blocos (segunda a quinta, sexta e sábado, domingo), meses de alta e baixa com ajuste em pontos, margem de segurança de 0% a 50% (sugestão 10%), ocupação final limitada a 95%; o sistema **avisa e não bloqueia**. Exemplo de Pato Branco: 1.671 leitos totais, dia útil a 85%. O InvenTour deve enviar leitos e UHs **homologados**; os parâmetros de ocupação continuam no EventCity.
- Outros dados que o EventCity já tem e o InvenTour pode reaproveitar: público por perfil (residente, turista, excursionista) e gasto por perfil, impacto econômico estimado (alíquota de ISS sobre 30% da injeção econômica, chamado de "estimativa"), Mapa do Turismo já importado, consulta Cadastur, período eleitoral (tira do ar notícias e agenda institucional até a data informada, mantém a agenda de eventos), botão "Melhorar com IA" travado até o destino informar chave própria (Claude, GPT ou Gemini), domínio próprio (`dominio_proprio`), apps únicos nas lojas compartilhados por todos os destinos.

**Comercial do EventCity (referência direta para precificar o InvenTour)**:
- Faixa validada em Pato Branco e Chapecó: de cerca de R$ 4 mil/mês a R$ 150 mil/ano, "absorvida com naturalidade" pelos gestores.
- Proposta real a Chapecó (12/11/2026): plataforma completa, sem limite de usuários nem de eventos; **R$ 4.500/mês, R$ 54.000/ano**, implantação de R$ 12.000 isenta (primeira fase); 12 meses prorrogáveis (Lei 14.133), reajuste IPCA, pagamento mensal, dados pertencem à prefeitura e são exportáveis; enquadramento por dispensa por valor (art. 75, II). Portal de demonstração no ar antes da venda (`chapeco.eventcity.com.br`).
- `Níveis de Pacote de Valor`: o Gemini propôs 3 pacotes por corte de função (Essencial R$ 3.000 a 3.800/mês; DTI & Turismo R$ 5.500/mês até 100 mil hab. e R$ 8.500 a 10.000/mês de 100 a 300 mil; Platinum/Ghost City R$ 15.000 a 22.000/mês). A resposta da Softsul (documento "sem mudar o conceito") **rejeita cortar funções** (login único, conflitos fora do básico, brasão só no topo, white label, kit de placas, "software se paga com ISS", prefeitura vendendo anúncio, "custo zero") e propõe:
  - assinatura completa por porte: até 50 mil hab. R$ 2.500/mês (R$ 30 mil/ano, dispensa); 50 a 150 mil R$ 4.000 (R$ 48 mil, dispensa); 150 a 300 mil R$ 7.500 (R$ 90 mil, pregão ou CPSI); acima de 300 mil R$ 12.500 (R$ 150 mil). Municípios categoria A e B do Mapa do Turismo sobem uma faixa;
  - implantação única de R$ 8 mil a R$ 15 mil (cadastro inicial, carga do Cadastur, treinamento, cartazes de QR, domínio próprio);
  - níveis como **serviço da Zapping**: Plataforma; Plataforma + Inteligência (Boletim de Inteligência mensal ou trimestral, hoje feito à mão pelo Anery); Plataforma + Gestão assistida (Zapping opera a curadoria);
  - pacote regional para IGR ou consórcio (teto de dispensa dobrado, art. 75, §2º); opcionais (IA, WhatsApp oficial, kit físico por gráfica parceira, capacitações); receita do trade na V2 (destaque pago, assinatura do organizador);
  - alertas jurídicos: teto de dispensa de cerca de R$ 62,7 mil em 2025, somando despesas de mesma natureza no ano; inexigibilidade é frágil com Smart Tour e outros no mercado; melhores caminhos são **CPSI (LC 182/2021)**, pregão com ata de registro de preços (adesão "carona") e consórcio público.
- `Formulário de Avaliação de Eventos`: pesquisa de satisfação com 10 questões em 5 dimensões do modelo **ServQual** (tangibilidade, confiabilidade, responsividade, segurança, empatia), 2 por dimensão, termos genéricos para servir de feira a congresso. Mesmo modelo da ideia 3 de 7.3; reaproveitável no InvenTour (requisitos 91/92 do DTI MTur: satisfação de turistas e prestadores).
- Outras decisões do `Ajustes` úteis ao InvenTour: termos de adesão editáveis por destino com nova versão e novo aceite; Anery quer 3 modelos de termo (rígido, moderado, suave); exigência de Cadastur pode entrar no termo; "Lei do Calendário" (o executivo é obrigado a divulgar o calendário até certa data) como gancho de venda; vender também para associações comerciais, grandes empresas e universidades; implantação básica x assistida x assessoria ao COMTUR; o Anery destaca que a página de cada organizador vira uma "mini página web" de quem nunca teve, argumento que vale igual para cada empreendimento inventariado.

### 7.5 Subpasta `InvenTour/centelha`

`inscrição/Centelha_InvenTour_ Inteligência em Informações Turísticas.pdf` (versão 1.0 postada em 21/06/2026, 2 páginas, área "TI e Telecom", maturidade declarada: **Ideia**):
- Problema: "apagão de dados"; inventários entregues em Word/PDF começam a morrer no dia seguinte; troca de mandato apaga arquivos e a nova gestão leva até 2 anos para recomeçar; sites municipais saem do ar em ano eleitoral.
- Solução em 3 camadas: back-office técnico (coleta em campo com geolocalização, trade insere e o município modera), Web App oficial, ecossistema phygital (QR Codes com BI, heatmaps e cliques).
- Clientes: prefeituras por assinatura anual; alternativamente COMTUR e patrocinadores. Concorrentes "focam em inventários analógicos e sites comuns, não na governança de dados".
- Diferencial: freemium técnico (cavalo de Troia) com licenças de coleta gratuitas a turismólogos; **custódia dos dados atrelada ao COMTUR** (órgão permanente) para blindar a continuidade do site; multi-tenant `cidade.inventour.com.br`; consolidação automática de portais regionais para IGRs.
- Equipe: Anery (turismo, DTI, COMTUR, IGRs) e Luiz (TI B2G, LGPD, infraestrutura). Pretende usar bolsas CNPq para contratar um desenvolvedor full-stack.
- A subpasta `EventCity/centelha` tem o equivalente do EventCity; não cita o InvenTour.

### 7.6 Exemplos de inventário e pesquisa (`Exemplos Inventário Pesquisa Demanda Plano`)

- **Ilhabela (SP), Plano Gestor de Turismo 2005** (T4 Consultoria, supervisão de Mário Carlos Beni), 5 volumes e um mapa: I Caracterização Geral; **II Inventariado da Oferta (355 páginas)**; III Estudo da Demanda; IV Planejamento Estratégico e Projetos; V Anexos e Glossário. Equipe com dezenas de estagiários (mostra o custo do modelo analógico).
  - Sumário do Volume II: 1. Oferta Turística Original: 1.1 Natural (praias, trilhas, quedas d'água e poços, áreas de mergulho autônomo e livre, **capacidade de carga**, **atratividade**); 1.2 Cultural (santo padroeiro, lendas, eventos, bens materiais). 2. Oferta Turística Agregada: meios de hospedagem 1 e 2, alimentação, equipamentos de apoio, marinas, operadoras, **empregabilidade**, **qualificação da mão de obra**.
  - Indicadores por segmento (índice de figuras): tipologia e evolução dos meios de hospedagem, estabelecimentos que fecham parte do ano e meses de fechamento, **leitos ofertados por mês**, taxa de ocupação percebida pelos proprietários, canais de distribuição, atratividade das áreas comuns e das UHs; na alimentação: tipologia, evolução, tipo de cozinha, clientes atendidos, atratividade; treinamento de funcionários.
  - Para o sistema: o inventário "bom" tem camada analítica além da ficha (evolução no tempo, sazonalidade de oferta, percepção do proprietário, atratividade). Leitos ofertados por mês casa com a sazonalidade do EventCity.
- **Angra dos Reis (RJ)**: `Pesquisa de Demanda Tur.pdf` (71 páginas), projeto "Caminhos Singulares do Turismo e Artesanato da Costa Verde", Instituto Genesis PUC-Rio. Modelo de relatório de pesquisa de demanda (não lido além da introdução).

### 7.7 Metodologia de inventariação (`Metodologia Inventariação`, `Inventariação 2011`, `Inventariação 2006`)

Lidas só as listas; o conteúdo é o mesmo dos documentos 03 (2011) e 04 (2006), já resumidos.
- `Metodologia Inventariação`: `0_APRESENTAÇÃO_CLASSIFICAÇÃO.doc` e um `.doc` editável por ficha (A1 Informações básicas do município, A2 Meios de acesso, A3 Comunicações, A4 Segurança, A5 Médico-hospitalar, A6 Educacional, A7 Outros serviços de apoio; B1 Hospedagem, B2 Gastronomia, B3 Agenciamento, B4 Transportes, B5 Eventos, B6 Lazer e entretenimento, B7 Outros serviços turísticos; C1 Atrativos naturais, C2 Atividades culturais, C3 Atividades econômicas, C4 Atrações técnicas, científicas e artísticas, C5 Eventos permanentes) e `Manual A/B/C.pdf`.
- `Inventariação 2011`: um PDF por ficha ou grupo (ex.: C.2.10 Arquitetura civil, C.2.11 a 13 Arquitetura oficial, militar e religiosa, C.2.29 Personalidades, C.3 Atividades econômicas, C.4 Atrações técnicas e científicas, C.5 Eventos programados).
- `Inventariação 2006`: subpastas `Formulários A`, `B`, `C`, `manual_a/b/c` e `#Inventário Manual_do_Pesquisador ABC.pdf`.
- Utilidade: os `.doc` editáveis por ficha são a fonte mais fácil para montar o seeder de `mtur_atributos_catalogo` (texto copiável, ao contrário dos PDFs).

### 7.8 Arquivos soltos

- **`CanvasTurismo.pptx`** (setembro de 2022, Anery e Luiz, 3 slides): Canvas do produto "**Best Guide**: Informação Turística, Inventário Turístico, BI Turismo", antecessor do InvenTour. Proposta: informação turística atualizada num só lugar (web e app), notificações por proximidade e interesse, check-ins e vantagens; ao gestor, "ferramenta de alimentação dinâmica do Inventário Turístico", **salvaguarda do inventário** e campos extras para planejamento; dados de demanda (acessos, buscas, check-ins, avaliações, origem e perfil). Público em Pato Branco: turismo de negócios, eventos, polo regional de compras, educação e saúde, visitas técnicas. Turista não paga. Receitas: prefeitura (taxa de implantação e renovação, anuidade, **Boletins BI Tour**, customizações, elaboração de inventário, produção de mapas, fotos e vídeos) e empresas (destaques, banners, anúncios, pop-ups). Parceiros: turismólogos (elaboram inventários e vendem), COMTUR, ACEs, IGRs.
- **`Catálogo de Soluções Tecnológicas para DTI` (MTur, março de 2023, 81 páginas)**: catálogo de cerca de 55 soluções e casos, cada ficha com tipo de solução, tipo de destino e **eixo DTI** a que se aplica (governança, sustentabilidade, segurança, inovação, acessibilidade, mobilidade, criatividade, promoção e marketing, tecnologia).
  - Relevantes ao InvenTour: **Smart Tour** (2 fichas); **Mabrian Technologies** (inteligência turística com big data e IA, Espanha); **Ciclops DTI** (consultoria espanhola com "primeiro software de gestão do sistema DTI", certificou destino na UNE 178501): concorrente do módulo de diagnóstico DTI; **Destinos 4.0** (Expert: guia turístico + controle de visitação por capacidade de carga); **Inguia** (acessibilidade da informação turística); **eTrilhas** (app de trilhas); **TrekkBR** (guia virtual/rede social de atrativos); **Glocal** (audioguias); **Natíz/DFP Tecnologia** (São Miguel do Oeste, SC: conecta aventureiros a destinos e empreendimentos); **Janoo** (Lençóis, BA: ingressos, portaria e gestão de atrativos, "ordenamento turístico"); **Hospeda Eventos** (hospedagem para participantes de eventos); **TOURQUAL** (avaliação de qualidade); Fator BLE (beacons), TemWiFi e Wifi Wiz (conteúdo por Wi-Fi); casos públicos (Santur/SC com o programa Inovatur, Prefeitura de São Luís, Treviso, AMAVI com "Caminhos do Campo").
  - Achado: **nenhuma ficha oferece inventário da oferta digital e contínuo** (a palavra "inventário" só aparece no Inovatur). O espaço do InvenTour está vazio no catálogo oficial; vale inscrever o InvenTour (e o EventCity) na próxima edição.
- **`inventour.pptx`** (pasta `pitchdeck`, 17 slides): versão editável do Pitch Deck (documento 01, já resumido na seção 2). Começa por "Destinos Turísticos 'Não Tão' Inteligentes" e o "modelo prancheta". Não traz conteúdo novo.
- **`39 - CARTILHA TURISMO 360.pdf`** ("Turismo em 360°: Planejando o turismo no Município", CNM, gestão 2015 a 2018, 64 páginas, lido o sumário): roteiro do **Plano Municipal de Turismo** em passos: 1) Inventário da Oferta (Grupos A, B, C); 2) Conhecer a demanda; 3) Concorrência e competitividade; 4) Potencialidades, diagnóstico e metodologia; 5) Planejar (prognóstico); 6) Estratégias (objetivos, metas, planos, programas, projetos); 7) Aprovação do plano; 8) Implantação e operacionalização. Serve de roteiro para um futuro módulo "Plano Municipal de Turismo" que nasce do inventário, da pesquisa de demanda e do diagnóstico DTI, e de texto de ajuda para o gestor.

### 7.9 Planilha 06c: as 4 abas e a nota

Aberta no Google Planilhas em modo de compatibilidade com .xlsx (sem conversão, sem cópia, nada editado). Leitura pelos dados exportados de cada aba.

| Aba | Linhas | Total registrado |
|---|---|---|
| `27 09 2022` | 216 | **62,25** |
| `28 11 2022` | 229 | **76,60** |
| `05 12 2022` | 231 | **72,34** |
| `12 12 2022` | 232 | **71,63** (a do nome do arquivo) |

- O total é **texto digitado** ("total 71,63" na célula A232; "Total: 72,34"; "TOTAL: 76,60"; "TOTAL: 62,25"), **sem fórmula**. Nas abas de 28/11 e 27/09 há linhas "Nota" vazias ao redor do total. Não existem valores intermediários, pesos ou notas por dimensão em nenhuma aba. Ou seja: a planilha é o registro das respostas digitadas na ferramenta do Sebrae, e o número é o resultado que a ferramenta devolveu.
- Mudanças entre versões (comparação linha a linha): 27/09 para 28/11, 37 linhas mudaram (+14,35 pontos); 28/11 para 05/12, 18 linhas (-4,26: sinalização com QR "menos de 20%", Fundo Municipal "conselho recém instituído, sem recursos", cooperação regional detalhada); 05/12 para 12/12, 8 linhas (-0,71: itens do website, população ocupada passou de "45%" para a faixa "+40% a 45%", empregos em alojamento e alimentação de 1.074/38.610 para 1.013/31.651, percentual de empresas de A&A).
- Respostas da versão final (base para o caso de teste), na leitura das regras do 06b:
  - Tecnologia: "Não" em software de gestão, app/PWA, CAT com Wi-Fi, geolocalização e VR/AR; website e redes com detalhamento; QR em menos de 20% dos atrativos. Poucos "sim": nível 1 ou 2.
  - Governança: "Sim" em COMTUR (deliberativo, mensal), Fundo, órgão de turismo, projetos regionais, cooperação, IGR, núcleos empresariais, turismólogos e capacitação; "Não" em consórcio. 9 ou mais "sim": nível 4 ou 5.
  - Experiência: "Sim" nos 6 primeiros indicadores e reputação online marcada: nível 5.
  - Ambiental: lixo, coleta seletiva, água, esgoto e LED nas faixas mais altas; frota alternativa "até 20%"; lei ambiental, áreas naturais e cooperativa "Sim" (SA8 = 5). Média cerca de 4,4.
  - Social: IDHM "+0,76 até 0,80"; ocupados "+40% a 45%"; empregos A&A "+2% até 4%".
  - Econômica: PIB per capita "+R$ 40.000"; empresas de A&A "+5% até 10%"; ISS "até 2,5%"; orçamento do turismo "até 1%"; turismo no PIB "até 2%".
  - Acessibilidade: transporte adaptado +80%; sinais sonoros +20% a 40%; calçadas, atrativos, A&B e passeios +80%; hospedagem +40% a 60%; website adaptado "Não", CAT com Libras "Não", sanitários "Sim". Média cerca de 4,1.
- **Como 1 a 5 vira 71,63 (hipótese, não confirmada)**: se o resultado do 06b (`(Tec x 2 + Gov x 2 + Exp x 2 + SAmb x 2 + SSoc + SEco x 2 + Acess) / 12`, de 1 a 5) for apresentado como percentual de 5, então 71,63% equivale a resultado 3,58 e soma ponderada de cerca de 43 pontos de 60. Com níveis inteiros a soma seria inteira e daria 71,67, não 71,63; logo a ferramenta deve usar médias brutas (não arredondadas) em algumas dimensões ou outra normalização (por exemplo, (R - 1) / 4, que daria R = 3,87). Não há dado na planilha para decidir. **Ação**: pedir ao Anery o relatório de devolutiva da Ferramenta de Diagnósticos do Sebrae de 12/12/2022 (que traz índice geral e níveis por dimensão) ou o PDF de resultado; sem ele, o teste de regressão "reproduzir 71,63" do documento 00 não é viável.

### 7.10 Achados que mudam o plano de desenvolvimento

1. **Preço e contratação já têm referência real**: a proposta de Chapecó (R$ 4.500/mês, R$ 54 mil/ano, implantação de R$ 12 mil) e a tabela por porte "sem cortar funções" (R$ 2.500 a R$ 12.500/mês) valem como base do InvenTour; os níveis viram serviço (Boletim de Inteligência, gestão assistida), não corte de módulo. Isso contraria a ideia de liberar módulos por plano e pede **feature flags por serviço/opcional, não por tier**. Caminho de contratação: dispensa até cerca de R$ 62,7 mil/ano somando tudo do mesmo objeto (atenção a vender InvenTour e EventCity à mesma prefeitura no mesmo ano), CPSI, ata de registro de preços e consórcio.
2. **A integração com o EventCity tem dois contratos concretos**: (a) leitos e UHs homologados do InvenTour substituindo a soma do Cadastur no alerta de hospedagem (os parâmetros de sazonalidade ficam no EventCity); (b) ficha rica de **Local de Evento (B5)** no InvenTour, com ambientes, layouts, capacidades por formato, acústica e infraestrutura, consumida pelo EventCity no lugar dos multiplicadores 0,4 e 0,25. A B5 e a B1 devem ser prioridade no V1.
3. **Concorrência**: no catálogo oficial do MTur e no Centelha PR não há inventário digital contínuo; o concorrente mais próximo (Smart Tour) mede demanda com beacons e cobra por atrativo, e o Ciclops DTI disputa o módulo de diagnóstico. Posicionar o InvenTour como "oferta homologada + phygital sem hardware" e prever inscrição no catálogo DTI.
4. **Pesquisa de demanda e de satisfação (ServQual) deveriam entrar no plano**, não só o inventário da oferta: aparecem no Canvas Best Guide, na lista de ideias, no formulário do EventCity, no requisito 91/92 do DTI e no exemplo de Ilhabela/Angra. Um módulo único de formulários de pesquisa (demanda e satisfação, via QR no estabelecimento) reaproveita o phygital e alimenta o BI.
5. **O caso de teste 71,63 não é reproduzível com o que está no Drive**: a 06c não tem fórmula. O motor de diagnóstico deve implementar a regra do 06b com testes próprios e tratar 71,63 como meta a confirmar após obter a devolutiva do Sebrae; até lá, não bloquear o V1 por isso.
