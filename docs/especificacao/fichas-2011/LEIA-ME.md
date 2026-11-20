# Especificação completa das fichas MTur 2011 no InvenTour (Softsul Software)

Objetivo: cada item numerado de cada ficha oficial (PDF) precisa ter um destino
no sistema. Nada pode ficar de fora. A especificação de cada ficha é um JSON
(formato abaixo) que um script valida e transforma em migração.

## Onde um campo pode morar

1. **Núcleo** (colunas de inventario_itens, iguais em toda ficha). Use estas
   colunas quando o item da ficha for exatamente isto:

   | coluna | o que é |
   |---|---|
   | nome | nome fantasia / nome oficial / denominação |
   | nome_popular | nome popular |
   | razao_social | razão social |
   | cnpj | CNPJ |
   | cnae | código CNAE (NOVA) |
   | atividade_economica | atividade econômica (NOVA) |
   | rede | nome da rede/holding/franquia |
   | inscricao_municipal | inscrição municipal |
   | natureza | natureza: pública, privada, mista, outra; com natureza_outra (NOVA) para "Outra ___" |
   | tipo_organizacao | tipo de organização/instituição: associação, sindicato, cooperativa, sistema S, empresa, outros; com tipo_organizacao_outro (NOVA) |
   | jurisdicao | jurisdição: municipal, estadual, federal, não se aplica |
   | inicio_atividade | início da atividade: data completa ou só o ano (passa a aceitar data) |
   | funcionarios | funcionários (total) |
   | funcionarios_temporarios | funcionários temporários (nº) (NOVA); "permanentes" = funcionarios |
   | funcionarios_pcd | pessoas com deficiência entre os funcionários (%) (NOVA) |
   | zona | localização urbana/rural |
   | latitude, longitude | coordenadas |
   | logradouro, numero, complemento, bairro, distrito (NOVA), cep | endereço |
   | telefone, ramal (NOVA), whatsapp | telefone e ramal |
   | email, site, instagram | e-mail e site DO PRÓPRIO item |
   | sinalizacao_acesso, sinalizacao_turistica | sinalização (sim/não) |
   | referencia | pontos de referência |
   | horario | horário por dia (com segundo período), 24 horas, feriados |
   | formas_pagamento | formas de pagamento |
   | idiomas | idiomas de atendimento |
   | capacidade_publico | capacidade de público (atrativos, espaços de eventos) |
   | conservacao | estado geral de conservação |
   | acessibilidade_possui | "Possui alguma facilidade para pessoas com deficiência?" (NOVA) |
   | (bloco acessibilidade) | os 21 subitens da seção de acessibilidade |
   | descricao_tecnica, descricao_promocional | descrições |
   | observacoes | observações |
   | referencias | referências bibliográficas |
   | pesquisador_id, coordenador_id | equipe responsável |

   Fichas de forma `municipio` (A1, A6) passam a ter endereço, telefone,
   ramal, e-mail, site, CNPJ e coordenada do núcleo: na A1 são os da
   prefeitura; na A6, os da Secretaria Municipal de Educação.

2. **Campo específico da ficha** (inventario_atributos). Tudo o que não é
   núcleo. Tipos permitidos:

   - `texto`, `texto_longo`, `numero`, `sim_nao`
   - `escolha` (uma opção), `multipla` (várias)
   - `data` (NOVO): data completa dd/mm/aaaa. Use só para data moderna e
     exata (inauguração, aniversário do município, fundação, emancipação).
     Data histórica que pode ser aproximada (edificação, confecção) fica
     `texto` com ajuda "dd/mm/aaaa, ou o ano ou o século aproximado".
   - `tabela`: linhas livres; as colunas podem ter tipo (veja abaixo).

   Propriedades novas:
   - `outros: true` quando a lista tem "Outros ____" / "Outras ____" com
     linha para especificar. A opção "Outros"/"Outras" fica na lista e o
     sistema mostra um texto para especificar.
   - `maximo: N` quando a ficha diz "assinalar até N" / "até N".
   - coluna de tabela tipada: em vez de só o nome, um objeto
     `{"rotulo": "...", "tipo": "texto|numero|sim_nao|escolha|meses", "opcoes": [...]}`
     (`meses` = marcar meses de janeiro a dezembro dentro da célula).
   - `linhas`: lista de linhas sugeridas para tabela (quando a ficha traz
     linhas fixas, como Auditório, Salas modulares...).

## Chaves padrão dos blocos que se repetem

Quando o item da ficha for um destes conceitos, use EXATAMENTE a chave e o
tipo daqui (as opções seguem a variante da ficha, copiadas do PDF). Isso
deixa o mesmo dado comparável entre fichas.

| chave | conceito | tipo |
|---|---|---|
| proximidades | Proximidades | multipla, outros |
| dist_aeroporto, dist_rodoviaria, dist_ferroviaria, dist_maritima, dist_metroviaria, dist_ponto_onibus, dist_ponto_taxi | Distâncias (km), uma por destino | numero |
| dist_outras | Distâncias: Outras (nome e km) | texto |
| dist_sede_km | distância da sede do município (km) | numero |
| localidade_proxima, dist_localidade_km | localidade mais próxima e distância (km) | texto, numero |
| cadastros | Cadastros, classificações e outros | tabela |
| segmentos | Segmentos ou tipos de turismo | multipla, maximo 3 |
| vendas_reservas | Vendas e reservas | multipla, outros |
| informativos_impressos | Informativos impressos | multipla, outros |
| periodo_meses | Regras de funcionamento: Período (meses) | multipla (12 meses + Ano inteiro) |
| restricoes | Restrições | multipla, outros |
| outras_regras | Outras regras e informações | texto_longo |
| estacionamento | Estacionamento (pago, gratuito, coberto, descoberto) | multipla |
| estac_capacidade, estac_automoveis, estac_onibus | capacidade de veículos total, automóveis, ônibus | numero |
| fluxo_ano, fluxo_alta | ocupação/visitantes no ano e na alta temporada | numero |
| fluxo_meses_alta | meses de alta temporada | multipla (12 meses + Ano inteiro) |
| fluxo_meses_baixa | meses de baixa temporada | multipla (12 meses + Ano inteiro) |
| fluxo_origem | origem dos visitantes/turistas | multipla |
| fluxo_estados | origem dos turistas nacionais (até 5 estados) | multipla com as 27 UFs, maximo 5 |
| fluxo_paises | origem dos turistas internacionais (até 5 países) | tabela de 1 coluna "País", ou texto |
| fluxo_ano_base | ano-base | numero |
| publico_frequentador | principal público frequentador | multipla |
| protecao_equip_possui, protecao_equip | proteção do equipamento/espaço: sim/não e tabela | sim_nao, tabela |
| protecao_area_possui, protecao_area | proteção da área ou edificação: sim/não e tabela | sim_nao, tabela |
| mantenedora, mantenedora_esfera, mantenedora_email, mantenedora_site | entidade mantenedora | texto, escolha, texto, texto |
| visitacao_possui, visitacao_finalidade, visitacao_agendada, visitacao_autoguiada, visitacao_guiada | visitação | sim_nao, multipla outros, escolha, escolha, escolha |
| entrada_gratuita, entrada_inteira, entrada_meia, permissao_acesso | entrada | sim_nao, numero (R$), numero (R$), escolha |
| instalacoes_entrada | instalações de entrada | multipla, outros |
| roteiros_integra, roteiros | integra roteiros comercializados; tabela | sim_nao, tabela |
| guias_integra, guias_impressos | integra guias impressos; tabela | sim_nao, tabela |
| trilha_acesso, trilha_extensao_m, grau_dificuldade | acesso ao atrativo por trilha | escolha, numero, escolha |
| transporte_regular, transporte_fretado | transporte até o atrativo | tabela, tabela |

As colunas da tabela de proteção: Esfera (escolha: Municipal, Estadual/Distrital,
Federal, Internacional, Outras), Instrumento (escolha: Lei/Decreto,
Portaria/Instrução/Deliberação, Norma/Ato, Outros), Instrumento (nº),
Nome/Título/Certificação/Licenciamento/outro, Entidade declaratória/Tipo de
declaração.

Campos desligados de propósito (não contam como falta, destino `ignorar`):
fax e ramal do fax; pagamento em cheque e cheque de outra praça; cabeçalho
UF, região turística e município (vêm do destino); número da ficha; equipe
responsável (núcleo).

## Formato da especificação (um arquivo por ficha: spec/NN.json)

```json
{
  "ordem": 20,
  "codigo": "B1",
  "pdf": "B.1.1 - Meios de hospedagem.pdf",
  "itens": [
    {"secao": "1.12.3", "rotulo_oficial": "Distrito", "destino": "nucleo", "coluna": "distrito"},
    {"secao": "3.2.1", "rotulo_oficial": "Total de UH", "destino": "atributo", "atributo_id": 231},
    {"secao": "2.3.1.2.1", "rotulo_oficial": "Meses de alta temporada", "destino": "atributo", "atributo_id": 300,
     "ajustes": {"chave": "fluxo_meses_alta", "opcoes": ["Janeiro", "...", "Dezembro", "Ano inteiro"]}},
    {"secao": "3.4.1.1.1.1", "rotulo_oficial": "Pessoas atendidas sentadas (nº)", "destino": "novo",
     "novo": {"chave": "restaurante_sentados_dia", "grupo": "Restaurante", "rotulo": "Pessoas atendidas sentadas por dia (nº)",
              "tipo": "numero", "apos": 245}},
    {"secao": "1.5", "rotulo_oficial": "Fax", "destino": "ignorar", "motivo": "fax desligado"}
  ],
  "desativar": [{"atributo_id": 999, "motivo": "duplica a coluna jurisdicao do núcleo"}],
  "notas": "o que for preciso explicar"
}
```

Regras:
- `secao` é a numeração exata do PDF. TODO número que aparece no PDF (1, 1.1,
  1.1.1, ...) entra uma vez em `itens`. Título de seção sem campo próprio
  (por exemplo "2. Características" ou "1.12 Endereço" que só agrupa) entra
  com `"destino": "titulo"`.
- Opção de lista não é item: vai em `opcoes`. Mas cada pergunta com lista é
  um item.
- `atributo`: o campo já existe (cite o id). Se algo nele está errado ou
  incompleto, ponha só o que muda em `ajustes` (chave, rotulo, grupo, tipo,
  opcoes completas na ordem do PDF, outros, maximo, ajuda, colunas, linhas).
- `novo`: campo que não existe. Dê chave (padrão da tabela acima quando
  couber; senão snake_case curta e única na ficha), grupo (o subtítulo da
  ficha), rótulo (o da ficha, em português correto, sem a numeração),
  tipo, opções na ordem do PDF, e `apos` = id do atributo depois do qual ele
  deve aparecer (ou `null` para o início do grupo).
- Use os rótulos e as opções exatamente como no PDF (corrija só erro óbvio de
  digitação). Não invente opção. Não traga a numeração "( 3 )" para a opção.
- `desativar`: atributo existente que não corresponde a nenhum item da ficha
  ou duplica o núcleo.
- Todo atributo ativo da ficha precisa aparecer em algum item ou em
  `desativar`.

## Como usar

1. Extraia o texto dos PDFs (fora do Git, porque o texto do MTur traz travessões):
   `cd "docs/material-original/drive/InvenTour/inventário metodologia/Inventariação 2011" && for f in *.pdf; do pdftotext -layout "$f" "../../../../../especificacao/fichas-2011/texto-dos-pdfs/${f%.pdf}.txt"; done`.
2. Confira: `php scripts/validar_fichas.php docs/especificacao/fichas-2011 docs/especificacao/fichas-2011/texto-dos-pdfs todas`.
3. Gere a migração: `php scripts/gerar_migracao_fichas.php docs/especificacao/fichas-2011 migracoes/NNN-nome.sql`.

A migração 016 saiu destas especificações em 20/11/2026 (1.320 campos novos,
361 ajustados, 41 desligados; nada apagado). Depois dela o validador acusa os
campos novos como "não citados", porque as especificações citam os ids de
antes: para uma revisão nova, exporte o catálogo de novo e escreva as
especificações a partir dele.
