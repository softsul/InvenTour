# Revisão das fichas oficiais de 2011 contra o painel

Softsul Software. Revisão de 20/11/2026: as 55 fichas em PDF do pacote do Drive
(docs/material-original, "Inventariação 2011") comparadas, campo a campo, com o
que o painel e o app de campo têm (núcleo comum, campos específicos de cada
ficha em inventario_atributos, acessibilidade e tribos).

## Situação depois da correção (20/11/2026)

As faltas desta revisão foram corrigidas com um levantamento novo, feito do
zero: cada uma das 54 fichas foi especificada item a item a partir do PDF
(docs/especificacao/fichas-2011, um JSON por ficha), e um script confere que
todo número do PDF tem destino. Daí saíram as migrações 015 (núcleo e tipos
novos), 016 (catálogo: 1.320 campos novos, 361 ajustados, 41 desligados,
nada apagado) e 017.

Uma segunda revisão, independente (revisores que não viram a especificação,
comparando cada PDF com o formulário final), não achou nenhum campo de ficha
faltando. Achou faltas no núcleo comum, corrigidas na migração 017:

- o subitem de acessibilidade "Outras" (6.1.21) não tinha campo; o "Outras"
  da rota externa não tinha texto; a sinalização de atendimento preferencial
  (Sim/Não) aceitava as duas respostas;
- o idioma "Outras" não tinha texto para dizer qual;
- "Funcionamento em feriados" não tinha Sim/Não próprio;
- premiação e reconhecimento (C.2.29) eram escolha única;
- datas exatas dentro de tabela (feriados da A1, inauguração dos monumentos
  da B.6.2);
- a esfera ou o instrumento "Outros" da proteção não tinha onde dizer qual;
- veículos "próprio ou de terceiros" não aceitavam os dois;
- a linha "MTur: outros" faltava nos cadastros das fichas B6.

### Decisões tomadas que vale o coordenador confirmar

1. Data da edificação e da confecção (fichas C2) ficam como texto que aceita
   data, ano ou século, porque bem histórico raramente tem data exata.
2. Entrada paga: o PDF só marca Inteira e Meia; o sistema guarda o valor em
   R$ de cada uma (marcar continua possível, basta pôr o valor).
3. B.6.3 Instalações esportivas: a ficha repete piso, área e quantidade por
   tipo de instalação; todos os blocos aparecem, e a pessoa preenche o do
   seu subtipo.
4. Opções que a Softsul já tinha desligado continuam desligadas: fax,
   cheque, telefone público, internet discada e 3G, aparelho de vídeo e
   retroprojetor.
5. Dia do padroeiro é data completa, como as outras datas da A1.
6. A Hidrovia não tem entidade mantenedora e a C.2.14 a .17 não tem
   transporte fretado, como no PDF oficial.
7. NA, NI e NE valem para todo campo da ficha; os campos do núcleo (nome,
   endereço, contato) ficam vazios quando não há resposta.

## Resumo

- Tipos e subtipos: completos nas 54 fichas.
- Acessibilidade: idêntica à oficial em todas as fichas.
- Completas nos campos específicos: 15 fichas (A3, A4, A5, A7.1, A7.4, A7.5/6,
  A7.7, B.2, B.6.4, B.6.6, B.7.1, B.7.3, C.2.25 a .27, C.2.28, C.2.29).
- Com faltas: 39 fichas.

## O que mais falta (vale para muitas fichas)

1. **Seções comuns sem tela nem gravação.** Estão descritas em
   app/dados/blocos-comuns-2011.json, mas só a acessibilidade virou campo:
   segmentos turísticos (até 3), caracterização do fluxo turístico (origem e
   meses de alta temporada), proximidades e distâncias, tipo de
   organização, CNAE e atividade econômica, distrito, proteção,
   qualificação, certificação e premiação (seção 4), estacionamento com
   capacidade, informativos impressos, período de funcionamento por mês,
   restrições e outras regras, e, nos atrativos (B6 e C), visitação,
   entrada, instalações de entrada, público frequentador, apoio à
   comercialização e acesso ao atrativo.
2. **Opção "Outros/Outras" esquecida** em várias listas, e o texto do
   "Outros ____" não é guardado em nenhuma.
3. **"Ano inteiro"** falta nos meses de alta temporada.
4. **Entidade mantenedora** sem e-mail e site (fichas C e B.6.1); sem campo
   nenhum na Rodovia e na Ferrovia.
5. **A1 (município)** sem os dados da prefeitura: endereço, telefone,
   e-mail, site, CNPJ e coordenada.
6. **Campos soltos:** capacidade total de veículos (C1, C4), pessoas
   atendidas sentadas em restaurante e lanchonete (B1), nome original
   (B.6.2), ramal do realizador (C5).
7. **Tipos:** início da atividade é data completa na ficha (o painel guarda o
   ano); datas históricas estão como texto (defensável); endereços do órgão
   de turismo (A1) e da Secretaria de Educação (A6) estão num texto só.

Fax e pagamento em cheque foram desligados de propósito e não contam como falta.

## Detalhe por ficha


### Auditoria das fichas A (ordem 01 a 19) contra as fichas oficiais MTur 2011

Comparação item a item entre o texto das fichas oficiais (pdf/A*.txt) e o que o sistema tem: núcleo comum, acessibilidade e campos específicos (catalogo/00 e catalogo/01 a 19).

## Premissas verificadas no código

1. **Dos blocos comuns, só a acessibilidade vira campo.** `app/FichaFormulario.php` (linha 133) e `public/item.php` (linha 87) leem de `Inventario::blocosComuns()` apenas a chave `acessibilidade`. Os outros blocos do JSON (`informacoes_gerais.tipo_organizacao`, `proximidades`, `distancias`, `protecao`, `instalacoes.estacionamento`, `funcionamento.informativos_impressos`, `funcionamento.periodo`, `funcionamento.outras_regras`, `acessibilidade_possui`, `equipe`) existem só como definição: não há input, coluna nem gravação. Por isso aparecem abaixo como faltas.
2. **A forma `municipio` (A1 e A6) não recebe o núcleo comum.** Em `FichaFormulario::ler` tudo, de razão social a conservação, fica dentro de `if (!$municipio)`. Sobram nome, tipo, nome popular, descrições, observações e referências.
3. **Cobertos e por isso não listados:** UF, região turística e município (vêm do destino/empresa); Equipe responsável (colunas `pesquisador_id` e `coordenador_id` do item, migração 008, com telefone e e-mail do usuário); Observações e Referências; Estado geral de conservação; os 21 subitens de acessibilidade com as opções idênticas às da ficha; idiomas de atendimento; horário por dia, 24 horas e feriados.
4. **Desativados de propósito, sem contar como falta:** Fax/ramal do fax (marcado INATIVO no catálogo) e as formas de pagamento Cheque e Cheque de outra praça (`formas_pagamento_inativas`).

## Faltas transversais

Faltas que se repetem em várias fichas. Cada ficha abaixo indica quais se aplicam a ela.

| Código | Rótulo na ficha oficial (seção) | Problema | Fichas |
|---|---|---|---|
| T1 | 1.4 Código CNAE e 1.5 Atividade econômica (1. Informações gerais) | Campo inexistente | 03, 05, 06, 07, 09, 15, 16 |
| T2 | Tipo de organização/instituição: Associação, Sindicato, Cooperativa, Sistema S, Empresa, Outros (1.5 nas lineares, 1.8 ou 1.9 nas pontuais) | Campo inexistente | 02, 03, 04, 05, 06, 07, 09, 15, 16 |
| T3 | 1.12.3 Distrito (1.12 Endereço) | Campo inexistente | 03, 05, 06, 07, 09, 15, 16 |
| T4 | 1.13.1.1 Ramal (1.13 Telefone/ramal) | Campo inexistente | 03, 05, 06, 07, 09, 15, 16 |
| T5 | 1.18 Proximidades: Restaurante, Bar/lanchonete, Meio de hospedagem, Shopping, Galeria/rua comercial, Centro de convenções/exposições, Posto de combustível, Outras | Campo inexistente | 03, 05, 06, 07, 09, 15 (na A7.2 a lista não tem Galeria/rua comercial) |
| T6 | 1.19 Distâncias (km): Aeroporto, Estação rodoviária, Estação ferroviária, Estação marítima/fluvial, Estação metroviária, Ponto de ônibus, Ponto de táxi, Outras (cada ficha omite o próprio modal) | Campo inexistente (deveria ser um número por destino) | 03, 05, 06, 07, 09 |
| T7 | 2.1.2 Informativos impressos: Não, Português, Inglês, Espanhol, Outras (2.1 Atendimento ao público) | Campo inexistente (o núcleo só tem idiomas de atendimento) | 03, 05, 06, 07, 09, 15, 16 |
| T8 | 2.2.1 Período: Janeiro a Dezembro e Ano inteiro (2.2 Regras de funcionamento) | Tipo errado: na ficha é múltipla escolha de meses; no sistema é o texto livre "temporada/agendamento" (`h_temporada`, 200 caracteres) | 03, 05, 06, 07, 09, 15, 16 |
| T9 | 2.2.3 Outras regras e informações (2.2 Regras de funcionamento) | Campo inexistente (só cabe em Observações) | 03, 05, 06, 07, 09, 15, 16 |
| T10 | 3.1.1 Estacionamento: Pago, Gratuito, Coberto, Descoberto; 3.1.1.1 Capacidade de veículos (nº), 3.1.1.1.1 Automóveis (nº), 3.1.1.1.2 Ônibus (nº) (3.1 ou 3.2 Instalações) | Campo inexistente | 03, 05, 06, 07, 09, 15, 16 |
| T11 | 4. Proteção, qualificação, certificação, premiação, destaques e outros: 4.1 Do equipamento/espaço e 4.2 Da área ou edificação (Sim/Não e tabela Esfera Municipal, Estadual/Distrital, Federal, Internacional, Outras por Instrumento Lei/Decreto, Portaria/Instrução/Deliberação, Norma/Ato, Outros, com Instrumento (nº), Nome/Título/Certificação/Licenciamento, Entidade declaratória/Tipo de declaração) | Seção inteira inexistente | 03, 05, 06, 07, 09, 15, 16 |
| T12 | 1.9 Início da atividade `__/__/__` | Tipo errado: a ficha pede data completa; o sistema guarda só o ano (`inicio_atividade` inteiro entre 1500 e o ano atual) | 03, 05, 06, 07, 09 |
| T13 | 6.1 Possui alguma facilidade para pessoas com deficiência ou mobilidade reduzida? Não/Sim | Pergunta-filtro não gravada (`acessibilidade_possui` só existe no JSON). Dá para deduzir dos subitens, mas não há como registrar "Não" explícito | 03, 05, 06, 07, 09, 15, 16 |
| T14 | Especificação de "Outros ____" / "Outras ____" em toda múltipla escolha, e "Natureza: ( 3 ) Outra ____" | Tipo incompleto: `valorDoAtributo` grava só as opções marcadas; o texto da linha "Outros" não tem onde ser guardado | Todas as fichas com múltipla escolha (01 a 09, 13, 15, 16) |

Observação sobre T2, T5, T6, T7, T10 e T11: as definições já estão prontas em `app/dados/blocos-comuns-2011.json`; falta apenas transformá-las em campo.

---

## 01 · A1 Informações básicas do município (A.1.1 Caracterização do município)

Com faltas. Forma `municipio`, sem núcleo comum.

**1. Campos inexistentes**
- 1.1 Endereço da Prefeitura: 1.1.1 Avenida/rua/travessa/caminho/outro, 1.1.2 Bairro, 1.1.3 CEP.
- 1.2 Telefone/ramal: 1.2.1 Telefone e 1.2.1.1 Ramal (fax 1.3 não conta, porque está desativado de propósito; no órgão de turismo ele aparece como INATIVO).
- 1.4 Endereço eletrônico (e-mail) da prefeitura.
- 1.5 Sítio eletrônico (site/página web) da prefeitura.
- 1.6 CNPJ da prefeitura.
- 1.7 Coordenadas geográficas: 1.7.1 Latitude e 1.7.2 Longitude. Só existe o centroide do IBGE na tabela `municipios`, que não é campo da ficha nem pode ser editado.
- 1.13.2.1.1 Ramal do órgão oficial de turismo.

**3. Tipo errado**
- 1.13.1 Endereço do órgão oficial de turismo: a ficha tem 1.13.1.1 Avenida/rua/travessa/caminho/outro, 1.13.1.2 Bairro/localidade, 1.13.1.3 Distrito e 1.13.1.4 CEP; o sistema tem um único texto, "Endereço do órgão de turismo".
- 1.15 Aniversário do município, 1.16.2 Dia do(a) Santo(a) Padroeiro(a), 1.18.2 Data da fundação e 1.18.3 Data da emancipação: são datas `__/__/__` na ficha e texto livre no sistema (sem validação de data).
- 2.3.1.1 Divulgação impressa: na ficha é Sim/Não seguido de uma linha de especificação para cada item (2.3.1.1.1 Folder, 2.3.1.1.2 Revista, 2.3.1.1.3 Jornal, 2.3.1.1.4 Outros). O sistema só marca os tipos (múltipla) e perde o texto de cada um.
- Também se aplica T14 (por exemplo 2.2.1.1 Tipo de abastecimento "( 5 ) Outros ____", 2.3.2.1 e 2.3.2.2 "Outras ____").

Observação (não é falta): 2.2.6 Reciclagem aparece duas vezes, como tabela "Reciclagem" e como múltipla "Materiais reciclados".

## 02 · A2.1.1 Rodoviário: Rodovia

Com faltas.

**1. Campos inexistentes**
- 1.5 Tipo de organização/instituição (T2).
- 1.12 Entidade mantenedora: não há campo próprio. O 1.12.1 e-mail e o 1.12.2 site entram nos campos e-mail e site do núcleo, mas o nome da mantenedora fica sem lugar.

**2. Opções faltando**
- 1.8 Pavimentação: falta "( 7 ) Outras".
- 2.2 Estruturas ao longo da via: falta "( 6 ) Outras".

Observação: a 1.3 Jurisdição está duplicada (o núcleo tem `jurisdicao` com Municipal, Estadual, Federal e Não se aplica, e o específico tem "[Via] Jurisdição").

## 03 · A2.1.2 e A2.1.3 Rodoviário: Estação rodoviária e Terminal rodoviário

Com faltas. Transversais: T1, T2, T3, T4, T5, T6, T7, T8, T9, T10, T11, T12, T13, T14.

**2. Opções faltando**
- 1.21 Autoridades rodoviárias e outras: falta "( 7 ) Outras" (o sistema para em Juizado de Menores).
- 2.3.1.1.1 Meses de alta temporada: falta "( 13 ) Ano inteiro".

## 04 · A2.2.1 e A2.2.2 Ferroviário: Ferrovia e Metrovia

Com faltas.

**1. Campos inexistentes**
- 1.5 Tipo de organização/instituição (T2).
- 1.10 Entidade mantenedora: não há campo próprio (o 1.10.1 e-mail e o 1.10.2 site vão para o núcleo).

Observação: a 1.3 Jurisdição está duplicada entre o núcleo e o campo específico.

## 05 · A2.2.3 e A2.2.4 Ferroviário: Estação ferroviária e Estação metroviária

Com faltas. Transversais: T1, T2, T3, T4, T5, T6, T7, T8, T9, T10, T11, T12, T13, T14.

**2. Opções faltando**
- 2.3.1.1.1 Meses de alta temporada: falta "( 13 ) Ano inteiro".

## 06 · A2.3.1 e A2.3.2 Aeroviário: Aeroporto e Campo de pouso

Com faltas. Transversais: T1, T2, T3, T4, T5, T6, T7, T8, T9, T10, T11, T12, T13, T14.

**2. Opções faltando**
- 2.3.1.1.1 Meses de alta temporada: falta "( 13 ) Ano inteiro".

Batem com a ficha: os serviços de pista, o tipo, a capacidade, os turnos e as formas de operação, as características operacionais (numéricas), a abrangência e a intermodalidade.

## 07 · A2.3.3 e A2.3.4 Aeroviário: Heliporto e Outros

Com faltas. Transversais: T1, T2, T3, T4, T5, T6, T7, T8, T9, T10, T11, T12, T13, T14.

**2. Opções faltando**
- 2.3.1.1.1 Meses de alta temporada: falta "( 13 ) Ano inteiro".

## 08 · A2.4.1 Aquaviário: Hidrovia

Com faltas.

**2. Opções faltando**
- 2.1.2 Outros serviços (ao longo da hidrovia): falta "( 10 ) Outros".

Observação: a 1.3 Jurisdição está duplicada entre o núcleo e o campo específico. Esta ficha não tem Natureza nem Tipo de organização, então T2 não se aplica.

## 09 · A2.4.2 a A2.4.8 Aquaviário: Porto organizado, Píer, Cais, Marina, Terminal portuário de uso privativo em turismo, Clube náutico e Outros

Com faltas. Transversais: T1, T2, T3, T4, T5, T6, T7, T8, T9, T10, T11, T12, T13, T14.

**2. Opções faltando**
- 2.3.1.1.1 Meses de alta temporada: falta "( 13 ) Ano inteiro".

## 10 · A3 Sistema de comunicação

Completa. Os 7 tipos e os campos 1.1 a 1.1.8 estão cobertos pelo núcleo, por "Abrangência/circulação/cobertura" e por "Outras informações".

## 11 · A4 Sistema de segurança

Completa. Os 9 tipos e os campos 1.1 a 1.1.6 estão cobertos, incluindo "Serviços especializados".

## 12 · A5 Sistemas de saúde

Completa. Os 9 tipos, o 1.1.7 Principais serviços, o 1.1.8 Horário de funcionamento (núcleo) e o 1.1.9 Formas de pagamento (núcleo) estão cobertos. A opção "( 2 ) Cheque" não está disponível, mas foi desativada de propósito.

## 13 · A6 Sistema educacional (A.6.1 Caracterização do Sistema de Educação)

Com faltas. Forma `municipio`.

**1. Campos inexistentes**
- 1.1.3.1.1 Ramal da Secretaria Municipal de Educação.

**3. Tipo errado**
- 1.1.2 Endereço da Secretaria: a ficha tem 1.1.2.1 Avenida/rua/travessa/caminho/outro, 1.1.2.2 Bairro/localidade, 1.1.2.3 Distrito e 1.1.2.4 CEP; o sistema tem um único texto, "Endereço da Secretaria".
- 1.2.1, 1.2.2, 1.2.3.1 e 1.2.3.2 (Ensino Fundamental, Médio, Graduação e Pós-graduação, com Sim/Não): a tabela "Níveis de ensino" não tem a coluna Sim/Não. A resposta só se deduz dos números.
- Também se aplica T14 ("( 6 ) Outros ____" nas modalidades de 1.3).

## 14 · A7.1 Locadoras de imóveis para temporada

Completa.

## 15 · A7.2 Compras especiais (8 subtipos)

Com faltas. Transversais: T1, T2, T3, T4, T5 (a lista desta ficha é Restaurante, Bar/lanchonete, Meio de hospedagem, Shopping, Centro de convenções/exposições, Posto de combustível, Outras), T7, T8, T9, T10, T11, T13, T14.

Observação: 1.7 Nome da rede/franquia está duplicado (o núcleo tem `rede` e o específico tem "Nome da rede/franquia"). Para 2.1.1 Formas de pagamento, Cheque e Cheque de outra praça estão desativados de propósito.

## 16 · A7.3 Comércio turístico (4 subtipos)

Com faltas. Transversais: T1, T2, T3, T4, T7, T8, T9, T10, T11, T13, T14 (esta ficha não tem Proximidades nem Distâncias).

Observação: 1.7 Nome da rede/franquia está duplicado com o `rede` do núcleo.

## 17 · A7.4 Serviços bancários (Agência/posto bancário, Casa de câmbio)

Completa.

## 18 · A7.5 e A7.6 Serviços mecânicos (5 subtipos) e Posto de combustível

Completa.

## 19 · A7.7 Representações diplomáticas (Embaixada, Consulado, Outras)

Completa.

---

## Tipos e subtipos

Nenhum tipo ou subtipo falta nas fichas 01 a 19. Todos os listados nas fichas oficiais estão no catálogo, com os mesmos nomes.

## Placar

- Completas: 7 (A3, A4, A5, A7.1, A7.4, A7.5/6, A7.7).
- Com faltas: 12 (A1, as oito fichas A2, A6, A7.2, A7.3).

### Auditoria das fichas B (ordem 20 a 35) contra o cadastro do InvenTour

Base: texto das fichas oficiais MTur 2011 (pasta `pdf/`), comparado com `catalogo/00-nucleo-comum.txt` e `catalogo/20-B1.txt` a `catalogo/35-B7.txt`. As opções de múltipla escolha foram conferidas por script (normalização de acentos e pontuação) e os campos foram conferidos manualmente, item a item. Diferenças só de grafia (por exemplo "VLAIN" na ficha e "VLAN" no sistema, "aberto" e "abertos") e opções a mais no sistema (por exemplo subtipo "Outros" acrescentado em B.5.1 e B.6.1) não são listadas.

## Pendências transversais (valem para várias fichas)

- **T1. Formas de pagamento: "Cheque" e "Cheque de outra praça" (ou "Cheque de outras praças")** aparecem nas fichas B.1.1, B.1.2, B.2, B.3, B.4, B.5.1, B.5.2 e B.6.1 a B.6.6, mas no sistema estão em `formas_pagamento_inativas` e fora da lista do núcleo (Dinheiro, PIX, Cartão de crédito, Cartão de débito, Tíquete, Gratuito). Parece decisão consciente; registro só para constar como opção oficial desativada.
- **T2. Fax e Ramal do fax (item "Fax/ramal")** existem em todas as fichas B menos B.7.3, e estão em `campos_inativos`. Mesma observação de T1.
- **T3. "Início da atividade" é data (`___/___/___`)** na ficha oficial (B.1.1 a B.7.2); o núcleo guarda só o ano. Tipo diferente (perde dia e mês).
- **T4. "Meses de alta temporada" sem a opção "( 13 ) Ano inteiro"**: as fichas trazem 13 opções, mas `fluxo.meses` do núcleo tem só os 12 meses. Afeta B.1.1 (2.3.1.2.1), B.1.2 (2.3.1.2.1) e B.6.1 (2.3.1.2.1). Nas fichas em que o campo é específico a falta está registrada na própria ficha (B.3, B.5.1, B.6.3).
- **T5. "Vendas e Reserva" das fichas B.6**: B.6.1, B.6.3 e B.6.6 trazem Balcão, Telefone, Fax, Internet, Agência de turismo, Antecipado, Outras; B.6.5 traz ainda "Com sinal". Nenhuma das duas listas do núcleo cobre isso: `vendas_reservas` não tem Agência de turismo, Com sinal nem Antecipado; `vendas_reservas_variante_b1` não tem "Outras". Se o sistema usa uma das duas para B.6, faltam opções.
- **T6. Cabeçalho "região turística"** (linha "uf / região turística / município" de todas as fichas B): UF e município saem do município do inventário, mas "região turística" não aparece no núcleo comum nem nos específicos.

## 20 · B.1.1 Hoteleira e de apoio (Meios de hospedagem)

Tipos e subtipos: completos (19 subtipos).

1. Campos faltando:
   - Seção 3.4.1.1 (Restaurante, Capacidade de atendimento): "3.4.1.1.1.1. Pessoas atendidas sentadas (nº)" (dentro da capacidade instalada por dia) e "3.4.1.1.2.1. Pessoas atendidas sentadas (nº)" (dentro da capacidade simultânea). O sistema só tem capacidade diária e simultânea.
   - Seção 3.4.2.1 (Lanchonete, Capacidade de atendimento): os mesmos dois campos "3.4.2.1.1.1. Pessoas atendidas sentadas (nº)" e "3.4.2.1.2.1. Pessoas atendidas sentadas (nº)".
2. Opções: "( 13 ) Ano inteiro" em 2.3.1.2.1 Meses de alta temporada (ver T4).
3. Tipos de dado: ver T3.

## 21 · B.1.2 Outro tipo de acomodação (Acampamento turístico/camping)

Tipos e subtipos: completos.

1. Campos faltando:
   - Seção 3.6.1.1 (Capacidade do restaurante): "3.6.1.1.1.1. Pessoas atendidas sentadas (nº)" e "3.6.1.1.2.1. Pessoas atendidas sentadas (nº)".
   - Seção 3.6.2.1 (Capacidade da lanchonete): "3.6.2.1.1.1. Pessoas atendidas sentadas (nº)" e "3.6.2.1.2.1. Pessoas atendidas sentadas (nº)".
2. Opções:
   - "( 13 ) Ano inteiro" em 2.3.1.2.1 Meses de alta temporada (ver T4).
   - 3.8.1 Instalações (área para eventos): a tabela existe, mas o sistema não traz as linhas oficiais sugeridas (3.8.1.1 Auditório, 3.8.1.2 Salas modulares, 3.8.1.3 Pavilhão de feiras, 3.8.1.4 Área de exposição coberta, 3.8.1.5 Área de exposição não coberta), ao contrário da ficha 20, que tem "Espaços de eventos (linhas sugeridas)". Falta menor, só de padronização.
3. Tipos de dado: ver T3.

## 22 · B.2 Alimentos e bebidas

Completa (fora as pendências transversais T1, T2, T3 e T6).

## 23 · B.3 Agências de turismo

Tipos: completos.

2. Opções:
   - 2.3.3.1 Meses de alta temporada: falta "( 13 ) Ano inteiro".
   - 2.3.4.1 Meses de baixa temporada: falta "( 13 ) Ano inteiro".

Fora isso, completa (mais T1, T2, T3 e T6).

## 24 · B.4 Transporte turístico

Tipos: completos.

2. Opções:
   - 3.4 Outros serviços: falta "( 15 ) Outros". A lista do sistema termina em "Entrega do veículo em local solicitado pelo cliente".

Fora isso, completa (mais T1, T2, T3 e T6).

## 25 · B.5.1 Espaços para eventos

Tipos e subtipos: completos (o sistema acrescenta "Outros").

2. Opções:
   - 2.3.1.2.1 Meses de alta temporada: falta "( 13 ) Ano inteiro".

Observação: a ficha oficial chama os campos de "2.3.1.1. Ocupação ano (nº)" e "2.3.1.2. Ocupação na alta temporada (nº)"; o sistema usa "Eventos por ano (nº)" e "Eventos na alta temporada (nº)". O sentido é o mesmo (seção 2.3 é "Caracterização do fluxo de eventos"), só o rótulo difere.

## 26 · B.5.2 Serviços para eventos

Tipos e subtipos: completos.

2. Opções:
   - 3.1 Atividades básicas: falta "( 5 ) Outras".
   - 3.1 Atividades básicas, opção 2: o rótulo oficial é "Eventos corporativos, institucionais, científico, profissionais, promocionais (oficinas, seminários, confraternizações, congressos, simpósios, salões, feiras, exposições e outros)"; o sistema cortou o parêntese explicativo.
   - 3.2 Outras atividades e serviços: falta "( 41 ) Outras". A lista do sistema termina em "Locação de utensílios".

## 27 · B.6.1 Parques

Tipos e subtipos: completos.

1. Campos faltando:
   - 1.26 Entidade mantenedora: o nome existe (bloco `atrativos.identificacao`), mas faltam "1.26.1. Endereço eletrônico (e-mail)" e "1.26.2. Sítio eletrônico (site/página web)" da entidade mantenedora.
2. Opções:
   - 1.9 Ambientação temática principal: falta "( 6 ) Outras".
   - 2.1.3 Vendas e Reserva: ver T5.
   - 2.3.1.2.1 Meses de alta temporada: falta "( 13 ) Ano inteiro" (T4).

## 28 · B.6.2 Espaços livres e áreas verdes

Tipos e subtipos: completos.

1. Campos faltando:
   - 1.3 "Nome original": o núcleo tem nome e nome popular, mas não nome original.
3. Tipos de dado:
   - 3.4.1 "Data de inauguração" (`___/___/___`) está como texto livre.
   - 3.5.3 "Data de inauguração" do monumento está como coluna de texto na tabela Monumentos.

## 29 · B.6.3 Instalações esportivas

Tipos e subtipos: completos (17).

2. Opções:
   - 3.3 Outros equipamentos e serviços: falta "( 30 ) Outros". A lista do sistema termina em "Disponibilidade de carrinho para pessoas com dificuldade de locomoção".
   - 3.5.7.8 Melhor época (Rampa para voo livre): falta "( 13 ) Ano inteiro".
   - 2.1.3 Vendas e Reserva: ver T5.

Observação: a seção 3.5 oficial repete os campos por grupo de subtipo (3.5.1 Ginásio/estádio/quadra, 3.5.2 Campos, 3.5.3 Autódromo etc.); o sistema usa um conjunto único de campos (Tipo de piso, Comprimento, Área, Largura, Quantidade de campos ou pistas). Como cada registro tem um só subtipo, a cobertura é equivalente.

## 30 · B.6.4 Instalações náuticas

Completa (fora T1, T2, T3 e T6).

## 31 · B.6.5 Espaços de diversão e cultura

Tipos e subtipos: completos.

2. Opções:
   - 3.2 Outras instalações e equipamentos: falta "( 31 ) Outras". A lista do sistema termina em "Sistema de amplificação".
   - 3.3 Serviços: falta "( 15 ) Outros". A lista do sistema termina em "Disponibilidade de carrinho para pessoas com dificuldade de locomoção".
   - 2.1.3 Vendas e Reserva (Balcão, Telefone, Fax, Internet, Agência de turismo, Com sinal, Antecipado, Outras): ver T5.

## 32 · B.6.6 Outros espaços de recreação

Completa nos campos específicos e subtipos. Única pendência: 2.1.4 Vendas e Reserva (ver T5), mais T1, T2, T3 e T6.

## 33 · B.7.1 Informações turísticas

Completa (fora T2, T3 e T6).

## 34 · B.7.2 Entidades associativas e similares

Tipo: completo.

2. Opções:
   - 1.23 Cadastros, classificações, filiação e outros: as linhas oficiais são 1.23.1 Fórum, 1.23.2 Conselho, 1.23.3 Federação, 1.23.4 Associação e 1.23.5 Outros; a coluna "Tipo" da tabela de filiações do sistema lista só Fórum, Conselho, Federação e Associação (falta "Outros").

Fora isso, completa (mais T2, T3 e T6).

## 35 · B.7.3 Guiamento e condução turística

Completa. Observação: a ficha oficial é uma lista (várias linhas de profissionais numa só folha, colunas 1.1 a 1.1.9); o sistema registra um profissional por item, com todos os campos (Nome completo, CPF, Endereço, Telefones, E-mail, Escolaridade, Serviços especializados, Nº do Cadastur, Outros cadastros e registros, Outras informações). Só T6 se aplica.

## Resumo

- Fichas completas nos campos específicos: 5 (B.2, B.6.4, B.6.6, B.7.1, B.7.3).
- Fichas com faltas específicas: 11 (B.1.1, B.1.2, B.3, B.4, B.5.1, B.5.2, B.6.1, B.6.2, B.6.3, B.6.5, B.7.2).
- Faltas mais relevantes: os 4 campos "Pessoas atendidas sentadas (nº)" de restaurante e lanchonete em B.1.1 e B.1.2; e-mail e site da entidade mantenedora em B.6.1; "Nome original" em B.6.2; opção "Ano inteiro" ausente em todos os seletores de meses (alta e baixa temporada, melhor época); opção "Outros/Outras" ausente em 7 listas (B.4 3.4, B.5.2 3.1 e 3.2, B.6.1 1.9, B.6.3 3.3, B.6.5 3.2 e 3.3); listas de "Vendas e Reserva" das B.6 sem correspondência exata no núcleo; "Início da atividade" e datas de inauguração sem tipo data.

### Auditoria das fichas C1 e C2 (ordens 36 a 46) contra as fichas MTur 2011

Base de comparação: `catalogo/00-nucleo-comum.txt` (núcleo, acessibilidade e blocos comuns, inclusive o bloco `atrativos`) mais o `catalogo/NN-COD.txt` de cada ficha.

Cabeçalhos administrativos (UF, região turística, município) estão cobertos: UF e município vêm do cadastro do município e a região turística existe no sistema (tabela de regiões, `migracoes/003-estados-municipios-e-regioes.sql`). Equipe responsável (seção 9 ou 7) está coberta pelo bloco `equipe`. Seções de proteção (4), estado de conservação (5), acessibilidade (6), observações e referências estão cobertas pelos blocos comuns em todas as fichas abaixo.

## Faltas comuns a várias fichas (vêm do núcleo ou do bloco `atrativos`)

Valem para todas as fichas 36 a 46 e não são repetidas por extenso em cada seção, só citadas pelo código.

- **G1. "Entidade mantenedora: Endereço eletrônico (e-mail)"** (item 1.12.1 ou 1.12.2 nas C1; 1.16.1, 1.17.1 ou 1.18.1 nas C2). Não existe e-mail da mantenedora. O núcleo tem e-mail do próprio atrativo, que é outro dado: a ficha C.2.7 a .9 pede os dois separados (1.12 e-mail do atrativo e 1.18.1 e-mail da mantenedora). As fichas 38, 39 e 40 têm "Sítio eletrônico da mantenedora", mas não o e-mail.
- **G2. "Entidade mantenedora: Sítio eletrônico (site/página web)"**. Também falta nas fichas 36, 37, 41, 42, 43, 44, 45 e 46 (só 38, 39 e 40 têm).
- **G3. 2.1.3.6 "Instalações de entrada: Outras ___"**. A lista `atrativos.instalacoes_entrada` tem só Centro de recepção, Posto de informação, Portaria principal, Guarita, Bilheteria; falta a opção "Outras" com especificação.
- **G4. 2.3.1.2.1 "Meses de alta temporada"**: a ficha tem a opção "(13) Ano inteiro"; a lista `fluxo.meses` vai só de Janeiro a Dezembro.
- **G5. Tipo (seção 2.3.1 e 2.4): "Total anual de visitantes (nº)" e "Total de visitantes alta temporada (nº)"** aparecem em `fluxo_campos` como rótulos, sem tipo declarado. Conferir se são numéricos (a ficha pede número).
- **G6. Tipo nas tabelas de flora e fauna (só C1): "Melhores meses de observação"**. Na ficha é uma grade de marcar Janeiro a Dezembro por categoria; no sistema é uma coluna da tabela sem tipo declarado (provavelmente texto). Se for texto livre, o dado deixa de ser filtrável por mês.

Observação, não contada como falta: várias listas do sistema têm a opção "Outras/Outros", mas o catálogo não mostra o campo de especificação que a ficha tem ("Outras ___"). Vale conferir na tela se a opção "Outras" abre um texto.

---

## 36 · C.1.1 Relevo continental

Tipos e subtipos: completos (13 subtipos).

Faltas:
- G1, G2, G3, G4, G6.
- **Seção 3.1.1.1 "Capacidade de veículos (nº)"**: o sistema tem Automóveis (nº) e Ônibus (nº), mas não o total. Pequeno, pode ser calculado.
- **Seção 3.3 "Estruturas e serviços"**: falta a opção "(10) Outros ___".
- **Seção 3.5.4 Hidrografia, tipo errado (estrutura achatada)**: a ficha pergunta, para cada curso d'água (3.5.4.1 Rio, 3.5.4.2 Riacho, 3.5.4.3 Córrego), "Quedas d'água Sim/Não" e "Tipo (Catarata, Cachoeira, Salto, Cascata, Corredeira)". O sistema tem uma única lista "Quedas d'água (tipos)" solta, sem vínculo com Rio, Riacho ou Córrego, e sem o "Quedas d'água Sim/Não" (inferível só se algum tipo for marcado).
- **3.5.4.7 "Outras ___"** da hidrografia: o sistema tem a opção "Outras", sem especificação visível.

## 37 · C.1.2 Zona costeira

Tipos e subtipos: completos (14 subtipos).

Faltas:
- G1, G2, G3, G4, G6.
- **3.1.1.1 "Capacidade de veículos (nº)"** total (mesma observação da 36).
- **3.3 "Estruturas e serviços"**: falta "(10) Outros ___".
- **3.5.6 Hidrografia**: mesma estrutura achatada da 36 (Quedas d'água Sim/Não e Tipo por Rio, Riacho e Córrego viram uma lista única).
- Observação de tipo: **3.5.1 "Extensão (m ou km)"** e **3.5.4.1 Extensão da praia** estão como texto. Aceitável porque a ficha mistura unidade, mas impede soma ou filtro. Vale também para 36, 38, 39 e 40.

## 38 · C.1.3 Relevo cárstico

Tipos e subtipos: completos (Caverna, Gruta, Furna, Dolina).

Faltas:
- G1 ("1.12.2 Endereço eletrônico (e-mail)" da mantenedora; o sítio 1.12.3 existe), G3, G4, G6.
- **3.1.1.1 "Capacidade de veículos (nº)"** total.

Demais itens da seção 3 (3.2, 3.3, 3.4, 3.5.1 a 3.5.11, 3.6, 3.7) conferem.

## 39 · C.1.4 e C.1.5 Hidrografia e Queda d'água

Tipos e subtipos: completos (Hidrografia com 8 subtipos, Queda d'água com 6).

Faltas:
- G1 (1.12.2 e-mail da mantenedora), G3, G4, G6.
- **3.1.1.1 "Capacidade de veículos (nº)"** total.
- **3.2 "Outras instalações e equipamentos"**: falta a opção "(10) Outras ___" (a lista do sistema termina em Iluminação).

Demais itens (3.3 Atividades com Canionismo e Cachoeirismo, 3.4.1 a 3.4.11, 3.5, 3.6, 3.7) conferem.

## 40 · C.1.6 Unidades de conservação e similares

Tipos e subtipos: completos (15 subtipos, inclusive Zoológico e Jardim botânico).

Faltas:
- G1 (1.12.2 e-mail da mantenedora), G3, G4, G6.
- **3.1.1.1 "Capacidade de veículos (nº)"** total.
- **3.5.2 Hidrografia**: mesma estrutura achatada da 36 (3.5.2.1.1, 3.5.2.2.1 e 3.5.2.3.1 "Quedas d'água Sim/Não" e respectivos "Tipo" por curso d'água).

1.13 Plano de manejo, 3.2, 3.3, 3.4, 3.5.3 Relevo, 3.6 Atividade econômica conferem.

## 41 · C.2.1 Conjuntos arquitetônicos

Tipos e subtipos: completos (Urbano, Rural, Industrial, Ferroviário, Outros).

Faltas:
- G1, G2, G3, G4.
- **1.3 "Descrição"**: falta a opção "(7) Outros ___" (sistema tem Centro histórico, Cidade histórica, Bairro histórico, Vila, Fazenda, Chácara/sítio).
- **3.1 "Instalações e equipamentos"**: falta a opção "(25) Outros ___".
- **3.3 "Atividades atuais"**: falta a opção "(8) Outras ___".
- Observação de tipo: **1.4 "Data da edificação"** é campo de data na ficha (`__/__/____`) e está como texto. Aceitável para datas históricas parciais (só ano ou século), mas impede ordenar e filtrar. Vale para 41 a 46.

## 42 · C.2.2 a C.2.6 Comunidades tradicionais, sítios arqueológicos, sítios paleontológicos, itinerários culturais e parques históricos

Tipos e subtipos: completos (5 tipos, todos os subtipos, inclusive os "Outros").

Faltas:
- G1, G2, G3, G4.
- **3.1 "Instalações e equipamentos"**: falta "(25) Outros ___".
- **3.3 "Atividades atuais"**: falta "(8) Outras ___".

## 43 · C.2.7 a C.2.9 Lugares de manifestações de fé, lugares de referências à memória e feiras/mercados de caráter cultural

Tipos e subtipos: completos (C.2.9 Feiras/mercados não tem subtipos na ficha).

Faltas:
- G1 ("1.18.1 Endereço eletrônico (e-mail)" da mantenedora), G2 ("1.18.2 Sítio eletrônico" da mantenedora), G3, G4. Aqui a ficha deixa claro que são campos distintos do e-mail e site do atrativo (1.12 e 1.13), que o núcleo cobre.
- **3.1 "Instalações e equipamentos"**: falta "(25) Outros ___".
- **3.3 "Atividades"**: falta "(8) Outras ___".

## 44 · C.2.10 Arquitetura civil

Tipos e subtipos: completos (14 subtipos).

Faltas:
- G1, G2, G3, G4.
- **3.3 "Atividades"**: falta "(8) Outras ___".
- **3.4.2 "Caça e guerra"**: falta "(5) Outros ___".
- **3.4.7 "Religião/cerimônia"**: falta "(5) Outros ___".

## 45 · C.2.11 a C.2.13 Arquitetura oficial, militar e religiosa

Tipos e subtipos: completos (oficial 10, militar 10, religiosa 17).

Faltas:
- G1, G2, G3, G4.
- **3.3 "Atividades"**: falta "(8) Outras ___".
- **3.4.2 "Caça e guerra"**: falta "(5) Outros ___".
- **3.4.7 "Religião/cerimônia"**: falta "(5) Outros ___".

## 46 · C.2.14 a C.2.17 Arquitetura industrial/agrícola, funerária, marcos históricos e obras de infraestrutura

(Ordem 46 está no meu bloco; o PDF correspondente é "C.2.14 a .17", conferido também.)

Tipos e subtipos: completos (industrial/agrícola 10, funerária 7, marcos 4, infraestrutura 18).

Faltas:
- G1, G2, G3, G4.
- **3.3 "Atividades"**: falta "(8) Outras ___".
- **3.4.2 "Caça e guerra"**: falta "(5) Outros ___".
- **3.4.7 "Religião/cerimônia"**: falta "(5) Outros ___".

---

## Resumo

- Fichas completas: 0 de 11.
- Fichas com faltas: 11 de 11. Nenhuma falta de tipo ou subtipo; as faltas são campos da mantenedora, opções "Outros/Outras" esquecidas e duas questões de estrutura.
- Mais relevantes:
  1. E-mail da entidade mantenedora ausente em todas (G1) e site da mantenedora ausente em 8 das 11 (G2).
  2. Hidrografia das fichas 36, 37 e 40: "Quedas d'água Sim/Não" e "Tipo" por Rio, Riacho e Córrego viraram uma lista única solta.
  3. Opções "Outros/Outras" ausentes: 3.3 Estruturas e serviços (36, 37), 3.2 Outras instalações (39), 1.3 Descrição (41), 3.1 Instalações e equipamentos (41, 42, 43), 3.3 Atividades (41 a 46), 3.4.2 Caça e guerra e 3.4.7 Religião/cerimônia (44, 45, 46), Instalações de entrada (todas).
  4. "Ano inteiro" ausente nos meses de alta temporada (todas) e "Capacidade de veículos (nº)" total ausente (36 a 40).

### Auditoria das fichas MTur 2011: C.2.14 em diante, C.3, C.4 e C.5

Comparação item a item do texto das fichas oficiais (pdf/*.txt) com o núcleo comum, a acessibilidade, os blocos comuns (catalogo/00-nucleo-comum.txt) e os campos específicos de cada ficha (catalogo/46 a 54).

Observação de escopo: a ficha C.2.14 a .17 corresponde à ordem 46 do catálogo (e não à 47); ela foi incluída porque o PDF está no bloco. A ordem 47 é a C.2.18.

A seção de acessibilidade de todas as fichas deste bloco foi comparada opção por opção com a de referência (C.2.14) e é idêntica; ela bate 100% com o bloco de acessibilidade do sistema. Os tipos e subtipos de todas as fichas do bloco estão completos no sistema.

## Faltas comuns (afetam várias fichas deste bloco)

Estas faltas estão nos blocos comuns e aparecem em todas as fichas indicadas; não são repetidas em detalhe em cada seção.

| # | Ficha oficial (rótulo exato) | Situação no sistema | Fichas afetadas |
|---|---|---|---|
| G1 | "Meses de alta temporada", opção "( 13 ) Ano inteiro" (2.3.1.2.1 / 2.1.1.2.1) | `fluxo.meses` só tem janeiro a dezembro; falta "Ano inteiro" (a lista de `periodo` tem, a de fluxo não) | C.2.14, C.2.18, C.2.19, C.3, C.4 |
| G2 | "Instalações de entrada", item "2.1.3.6. Outras ____" (C.5: 2.1.2.6) | `atrativos.instalacoes_entrada` tem só Centro de recepção, Posto de informação, Portaria principal, Guarita, Bilheteria; falta "Outras" com especificação | C.2.14, C.2.18, C.2.19, C.4, C.5 |
| G3 | "Entidade mantenedora", subitens "Endereço eletrônico (e-mail)" e "Sítio eletrônico (site/página web)" (1.16.1/1.16.2; C.2.18: 1.14.1/1.14.2; C.4: 1.21.2/1.21.3) | O sistema tem só o nome da "Entidade mantenedora" (`atrativos.identificacao`); o e-mail e o site do núcleo são do próprio atrativo, não há e-mail nem site próprios da mantenedora | C.2.14, C.2.18, C.2.19, C.4 |
| G4 | "3.3. Atividades", opção "( 8 ) Outras ____" | O campo específico "Atividades" termina em "De aventura"; falta "Outras" | C.2.14, C.2.18, C.2.19 |

## C.2.14 a C.2.17 (ordem 46): Arquitetura industrial/agrícola, arquitetura funerária, marcos históricos e obras de infraestrutura

Tipos e subtipos: completos (C.2.14.1 a .10, C.2.15.1 a .7, C.2.16.1 a .4, C.2.17.1 a .18).

Faltas:
1. Opções faltando, seção 3.4 Acervo:
   - "3.4.2. Caça e guerra": falta "( 5 ) Outros ____" (sistema: Arma e acessórios, Equipamento de defesa, Munição e acessórios, Apetrechos de caça).
   - "3.4.7. Religião/cerimônia": falta "( 5 ) Outros ____" (sistema: Insígnias, Objetos de culto, Objetos funerários, Objetos cerimoniais de instituições).
2. Opção faltando: "3.3. Atividades", "( 8 ) Outras" (ver G4).
3. Tipo: "1.6. Data da edificação" é data no formulário (`__/__/____`); no sistema é texto livre. Aceitável para datas históricas aproximadas, mas sem validação de data.
4. Faltas comuns G1, G2, G3.

## C.2.18 (ordem 47): Obras de interesse artístico

Tipos e subtipos: completos (C.2.18.1 Bens integrados à edificação, C.2.18.2 Bens integrados à paisagem ou ao espaço urbano, C.2.18.3 Outras).

Faltas:
1. Opção faltando: "1.3.3. Representação", "( 8 ) Outras ____" (sistema termina em "Vitral").
2. Opção faltando: "3.3. Atividades", "( 8 ) Outras" (ver G4).
3. Tipo: "1.4. Data da confecção/execução" é data no formulário (`__/__/____`); no sistema é texto.
4. Faltas comuns G1, G2, G3.

## C.2.19 a C.2.24 (ordem 48): Ruínas, museu/memorial, biblioteca, centros culturais/casas de cultura/galerias, teatros/anfiteatros e cines clube

Tipos: completos (Ruínas, Museu/memorial, Biblioteca, Centros culturais/casas de cultura/galerias, Teatros/anfiteatros, Cines clube).

O conteúdo da ficha é idêntico ao da C.2.14 a .17 (conferido por diff), e o catálogo 48 também é igual ao 46, portanto as faltas são as mesmas:
1. "3.4.2. Caça e guerra": falta "( 5 ) Outros ____".
2. "3.4.7. Religião/cerimônia": falta "( 5 ) Outros ____".
3. "3.3. Atividades": falta "( 8 ) Outras" (ver G4).
4. Tipo: "1.6. Data da edificação" é data no formulário; no sistema é texto.
5. Faltas comuns G1, G2, G3.

## C.2.25 a C.2.27 (ordem 49): Gastronomia típica e preparação de alimentos, artesanato/trabalhos manuais e atividades tradicionais de trabalho

Tipos e subtipos: completos (C.2.25.1 a .3, C.2.26.1 a .23, C.2.27.1 a .9).

Completa quanto a campos e opções. Um ponto a verificar (não é falta comprovada):
- "1.4. Artista/executante": a coluna "Ateliê aberto a visitação" é naturalmente Sim/Não e "Ano de início da atividade" é número; o catálogo não informa o tipo das colunas da tabela, então convém confirmar que não são texto livre.

## C.2.28 (ordem 50): Formas de expressão

Tipos e subtipos: completos (Música, Dança, Literária/oral, Cênica/performática, Outras).

Completa quanto a campos e opções (as 53 opções de "3.1.1.1. Gênero musical", as 5 de "3.1.2.1. Atuação" e as 7 de "3.1.3. Cênica/performática" batem). Um ponto a verificar:
- "1.3. Executantes": as colunas "Tipo de execução (individual ou grupo)" e "Tipo de atuação (amador ou profissional)" são escolhas de duas opções e "Ano de início da atividade" é número; o catálogo não informa o tipo das colunas, convém confirmar que não são texto livre.

## C.2.29 (ordem 51): Personalidades

Tipo: completo.

Completa. Itens 1.2 (endereço para apresentação), 1.3 telefone/ramal, 1.5 e-mail, 1.6 site, 1.7 proximidades, 1.8 distâncias, 1.9 pontos de referência, 2.1.1 visitação, 2.2 período, horário, 24 horas e outras regras estão no núcleo e nos blocos comuns; 1.4 Fax/ramal está coberto pelo campo Fax (inativo). Ponto a avaliar:
- "3.1. Premiação" e "3.2. Reconhecimento" estão como escolha única (Não | Nacional | Internacional). A ficha não diz se é única, mas uma personalidade pode ter premiação nacional e internacional ao mesmo tempo; múltipla escolha (com "Não" exclusivo) seria mais fiel ao uso.

## C.3 (ordem 52): Atividades econômicas

Tipos e subtipos: completos (C.3.1.1 a .3, C.3.2.1 a .5, C.3.3.1 a .2, C.3.4.1 a .9, C.3.5).

Faltas:
1. Falta comum G1 ("2.1.1.2.1. Meses de alta temporada", opção "( 13 ) Ano inteiro").

Fora isso, completa.

## C.4 (ordem 53): Realizações técnicas e científicas contemporâneas

Tipos e subtipos: completos (C.4.1 a C.4.8, C.4.3.1 a .6).

Faltas:
1. Campo faltando: "3.1.1.1. Capacidade de veículos (nº)" (total). O sistema tem só "Automóveis (nº)" e "Ônibus (nº)" (`instalacoes.estacionamento_capacidade`); o total não existe (pode ser derivado da soma, se for essa a intenção).
2. Faltas comuns G1, G2 e G3 ("1.21.2. Endereço eletrônico (e-mail)" e "1.21.3. Sítio eletrônico (site/página web)" da entidade mantenedora; a "1.21.1. Esfera administrativa" está coberta).

Observação: "1.7. Ano de criação/instalação" (numero), "1.6. Inscrição municipal", "Funcionamento em feriados", as 23 opções de "3.2. Outras instalações e equipamentos" e as 15 de "3.3. Outros equipamentos e serviços" batem.

## C.5 (ordem 54): Eventos programados

Tipos e subtipos: completos (C.5.1 a C.5.11, C.5.10.1 a .5).

Faltas:
1. Opção faltando: "3.3. Caráter do evento", "( 6 ) Outros ____" (sistema: Social, Comercial, Promocional, Cultural, Técnico-científico).
2. Campo faltando: "1.12.4.1.1. Ramal" do telefone do realizador/entidade. O sistema tem "Telefone do realizador" mas não ramal (o ramal do fax, 1.12.5.1.1, pode ficar de fora junto com o fax inativo).
3. Falta comum G2 ("2.1.2.6. Outras" em Instalações de entrada).
4. Detalhe de rótulo: as opções de "3.3. Caráter do evento" trazem na ficha uma definição entre parênteses (por exemplo "Social (envolve assuntos próprios da sociedade...)"); o sistema guarda só o nome curto. Não é falta de dado, mas a definição poderia aparecer como ajuda no formulário.

## Resumo

- Fichas analisadas: 9 (C.2.14 a .17, C.2.18, C.2.19 a .24, C.2.25 a .27, C.2.28, C.2.29, C.3, C.4, C.5).
- Completas: 3 (C.2.25 a .27, C.2.28 e C.2.29), com pontos de verificação sobre tipo de colunas de tabela e escolha única de Premiação/Reconhecimento.
- Com faltas: 6 (C.2.14 a .17, C.2.18, C.2.19 a .24, C.3, C.4, C.5).
- Nenhum tipo ou subtipo faltando; acessibilidade idêntica em todas.
