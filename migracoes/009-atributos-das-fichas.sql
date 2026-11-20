-- InvenTour, etapa 2: campos específicos de cada ficha do MTur (seção 3 e
-- campos próprios das seções 1 e 2), mais as opções anacrônicas guardadas.
-- Softsul Software. Gerada por scripts/gerar_seed_atributos.php a partir de
-- docs/especificacao/atributos-2011.json em 20/11/2026. Não editar.

ALTER TABLE inventario_atributos
    ADD COLUMN opcoes_inativas JSON NULL COMMENT 'opções anacrônicas (fax, cheque, 3G): aceitas na importação, fora do formulário' AFTER opcoes;

-- Páginas iniciais conferidas no PDF de 2011.
UPDATE inventario_fichas SET pagina_2011 = 358 WHERE ordem = 49;
UPDATE inventario_fichas SET pagina_2011 = 374 WHERE ordem = 52;

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 1);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Prefeitura', 'municipios_limitrofes', 'Municípios limítrofes', 'texto_longo', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Prefeitura', 'distancia_capital', 'Distância da capital (km)', 'numero', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Prefeitura', 'funcionarios_total', 'Total de funcionários da prefeitura (nº)', 'numero', NULL, NULL, 0, 0, 1, 3),
  (@f, 'Prefeitura', 'funcionarios_pcd', 'Funcionários com deficiência (%)', 'numero', NULL, NULL, 0, 0, 1, 4),
  (@f, 'Prefeitura', 'prefeito', 'Nome do prefeito', 'texto', NULL, NULL, 0, 0, 1, 5),
  (@f, 'Prefeitura', 'secretarias', 'Secretarias, departamentos e outros', 'texto_longo', NULL, NULL, 0, 0, 1, 6),
  (@f, 'Órgão oficial de turismo', 'oot_nome', 'Nome do órgão oficial de turismo', 'texto', NULL, NULL, 0, 1, 1, 7),
  (@f, 'Órgão oficial de turismo', 'oot_endereco', 'Endereço do órgão de turismo', 'texto', NULL, NULL, 0, 1, 1, 8),
  (@f, 'Órgão oficial de turismo', 'oot_telefone', 'Telefone do órgão de turismo', 'texto', NULL, NULL, 0, 1, 1, 9),
  (@f, 'Órgão oficial de turismo', 'oot_fax', 'Fax do órgão de turismo', 'texto', NULL, NULL, 0, 0, 0, 10),
  (@f, 'Órgão oficial de turismo', 'oot_email', 'E-mail do órgão de turismo', 'texto', NULL, NULL, 0, 1, 1, 11),
  (@f, 'Órgão oficial de turismo', 'oot_site', 'Site do órgão de turismo', 'texto', NULL, NULL, 0, 1, 1, 12),
  (@f, 'Órgão oficial de turismo', 'oot_funcionarios', 'Número de funcionários do órgão de turismo', 'numero', NULL, NULL, 0, 0, 1, 13),
  (@f, 'Órgão oficial de turismo', 'oot_formacao_turismo', 'Funcionários com formação superior em Turismo (nº)', 'numero', NULL, NULL, 0, 0, 1, 14),
  (@f, 'Governança', 'instancias_governanca', 'Instâncias de governança, associativas, parcerias e outros', 'tabela', '["Âmbito (Municipal, Estadual, Regional, Nacional, Internacional, Outras)","Instância\\/entidade"]', NULL, 0, 0, 1, 15),
  (@f, 'Datas e história', 'aniversario', 'Aniversário do município', 'texto', NULL, NULL, 0, 1, 1, 16),
  (@f, 'Datas e história', 'padroeiro', 'Santo(a) padroeiro(a)', 'texto', NULL, NULL, 0, 1, 1, 17),
  (@f, 'Datas e história', 'padroeiro_dia', 'Dia do(a) padroeiro(a)', 'texto', NULL, NULL, 0, 1, 1, 18),
  (@f, 'Datas e história', 'feriados', 'Outros feriados municipais', 'tabela', '["Data","Feriado"]', NULL, 0, 1, 1, 19),
  (@f, 'Datas e história', 'origem_nome', 'Origem do nome', 'texto_longo', NULL, NULL, 0, 1, 1, 20),
  (@f, 'Datas e história', 'data_fundacao', 'Data da fundação', 'texto', NULL, NULL, 0, 1, 1, 21),
  (@f, 'Datas e história', 'data_emancipacao', 'Data da emancipação', 'texto', NULL, NULL, 0, 1, 1, 22),
  (@f, 'Datas e história', 'fundadores', 'Fundadores', 'texto', NULL, NULL, 0, 1, 1, 23),
  (@f, 'Datas e história', 'fatos_historicos', 'Outros fatos de importância histórica', 'texto_longo', NULL, NULL, 0, 1, 1, 24),
  (@f, 'Aspectos gerais', 'area_total', 'Área total do município (km²)', 'numero', NULL, NULL, 0, 1, 1, 25),
  (@f, 'Aspectos gerais', 'area_urbana', 'Área urbana (km²)', 'numero', NULL, NULL, 0, 0, 1, 26),
  (@f, 'Aspectos gerais', 'area_rural', 'Área rural (km²)', 'numero', NULL, NULL, 0, 0, 1, 27),
  (@f, 'Aspectos gerais', 'area_ano_base', 'Ano-base da área', 'numero', NULL, NULL, 0, 0, 1, 28),
  (@f, 'Aspectos gerais', 'populacao_total', 'População total (hab.)', 'numero', NULL, NULL, 0, 1, 1, 29),
  (@f, 'Aspectos gerais', 'populacao_urbana', 'População urbana (hab.)', 'numero', NULL, NULL, 0, 0, 1, 30),
  (@f, 'Aspectos gerais', 'populacao_rural', 'População rural (hab.)', 'numero', NULL, NULL, 0, 0, 1, 31),
  (@f, 'Aspectos gerais', 'populacao_ano_base', 'Ano-base da população', 'numero', NULL, NULL, 0, 0, 1, 32),
  (@f, 'Clima', 'temperatura_media', 'Temperatura média (°C)', 'numero', NULL, NULL, 0, 1, 1, 33),
  (@f, 'Clima', 'temperatura_minima', 'Temperatura mínima (°C)', 'numero', NULL, NULL, 0, 1, 1, 34),
  (@f, 'Clima', 'temperatura_maxima', 'Temperatura máxima (°C)', 'numero', NULL, NULL, 0, 1, 1, 35),
  (@f, 'Clima', 'meses_frios', 'Meses mais frios', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 1, 1, 36),
  (@f, 'Clima', 'meses_quentes', 'Meses mais quentes', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 1, 1, 37),
  (@f, 'Clima', 'meses_chuvosos', 'Meses mais chuvosos', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 1, 1, 38),
  (@f, 'Clima', 'meses_secos', 'Meses menos chuvosos', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 1, 1, 39),
  (@f, 'Clima', 'altitude_media', 'Altitude média (m)', 'numero', NULL, NULL, 0, 1, 1, 40),
  (@f, 'Economia', 'atividades_economicas', 'Principais atividades econômicas', 'texto_longo', NULL, NULL, 0, 1, 1, 41),
  (@f, 'Água e esgoto', 'agua_tipo', 'Tipo de abastecimento de água', 'multipla', '["Água não canalizada","Canalizada de poço","Canalizada de nascente","Canalizada de curso de água\\/barragem","Outros"]', NULL, 0, 0, 1, 42),
  (@f, 'Água e esgoto', 'agua_domicilios_pct', 'Domicílios atendidos por água (%)', 'numero', NULL, NULL, 0, 0, 1, 43),
  (@f, 'Água e esgoto', 'agua_empresa', 'Empresa responsável pela água', 'texto', NULL, NULL, 0, 0, 1, 44),
  (@f, 'Água e esgoto', 'esgoto', 'Esgoto: coleta e deposição', 'tabela', '["Forma (Rede de esgoto, Fossa séptica, Fossa rudimentar, Vala, Estação de tratamento, Esgoto tratado, Outros)","Existe (sim\\/não)","Total atendido (%)","Domicílios urbanos (%)","Domicílios rurais (%)","Entidade responsável"]', NULL, 0, 0, 1, 45),
  (@f, 'Energia', 'voltagem', 'Voltagem', 'escolha', '["110 Volts","220 Volts","110\\/220 Volts"]', NULL, 0, 0, 1, 46),
  (@f, 'Energia', 'capacidade_kva', 'Capacidade em KVA', 'numero', NULL, NULL, 0, 0, 1, 47),
  (@f, 'Energia', 'gerador_emergencia', 'Gerador de emergência', 'sim_nao', NULL, NULL, 0, 0, 1, 48),
  (@f, 'Energia', 'gerador_kva', 'Capacidade do gerador em KVA', 'numero', NULL, NULL, 0, 0, 1, 49),
  (@f, 'Energia', 'abastecimento_energia', 'Abastecimento de energia', 'tabela', '["Rede (urbana, rural, abastecimento próprio, outros)","Existe (sim\\/não)","Total abastecido (%)","Domicílios urbanos (%)","Domicílios rurais (%)","Entidade responsável"]', NULL, 0, 0, 1, 50),
  (@f, 'Lixo e reciclagem', 'coleta_lixo', 'Coleta de lixo', 'tabela', '["Coleta (seletiva, não seletiva, sem coleta)","Existe (sim\\/não)","Total (%)","Domicílios urbanos (%)","Domicílios rurais (%)","Entidade responsável"]', NULL, 0, 0, 1, 51),
  (@f, 'Lixo e reciclagem', 'deposicao_lixo', 'Deposição e tratamento do lixo', 'tabela', '["Forma (aterro sanitário, compostagem, a céu aberto\\/lixão, outros)","Existe (sim\\/não)","Total atendido (%)","Domicílios urbanos (%)","Domicílios rurais (%)","Entidade responsável"]', NULL, 0, 0, 1, 52),
  (@f, 'Lixo e reciclagem', 'reciclagem', 'Reciclagem', 'tabela', '["Material","Existe (sim\\/não)","Total reciclado (%)","Entidade responsável"]', NULL, 0, 0, 1, 53),
  (@f, 'Lixo e reciclagem', 'materiais_reciclados', 'Materiais reciclados', 'multipla', '["Aço","Alumínio","Ferro","Outro metal","Baterias e pilhas","Borracha","Eletrônicos","Embalagens longa vida","Entulho","Madeira","Papel","Plástico e embalagens","Vidro","Óleo de cozinha","Outros"]', NULL, 0, 0, 1, 54),
  (@f, 'Comunicação', 'acesso_internet', 'Acesso à internet', 'multipla', '["A rádio","A cabo","Banda larga","Discada","Wireless","3G"]', '["Discada","3G"]', 0, 1, 1, 55),
  (@f, 'Comunicação', 'telefonia_movel', 'Telefonia móvel', 'escolha', '["Não","Em todo município","Em parte do município"]', NULL, 0, 1, 1, 56),
  (@f, 'Comunicação', 'telefonia_fixa', 'Telefonia fixa', 'escolha', '["Não","Em todo município","Em parte do município"]', NULL, 0, 0, 1, 57),
  (@f, 'Promoção turística', 'divulgacao_impressa', 'Divulgação impressa', 'multipla', '["Folder","Revista","Jornal","Outros"]', NULL, 0, 0, 1, 58),
  (@f, 'Promoção turística', 'divulgacao_tv', 'Divulgação televisiva', 'sim_nao', NULL, NULL, 0, 0, 1, 59),
  (@f, 'Atendimento ao visitante', 'idiomas', 'Atendimento em língua estrangeira', 'multipla', '["Não","Inglês","Espanhol","Outras"]', NULL, 0, 1, 1, 60),
  (@f, 'Atendimento ao visitante', 'informativos', 'Informativos impressos', 'multipla', '["Não","Português","Inglês","Espanhol","Outras"]', NULL, 0, 1, 1, 61),
  (@f, 'Fluxo de visitantes', 'visitantes_ano', 'Visitantes por ano (nº)', 'numero', NULL, NULL, 0, 0, 1, 62),
  (@f, 'Fluxo de visitantes', 'visitantes_alta', 'Visitantes na alta temporada (nº)', 'numero', NULL, NULL, 0, 0, 1, 63),
  (@f, 'Fluxo de visitantes', 'meses_alta_temporada', 'Meses de alta temporada', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro","Ano inteiro"]', NULL, 0, 1, 1, 64),
  (@f, 'Fluxo de visitantes', 'origem', 'Origem dos visitantes/turistas', 'multipla', '["Entorno municipal","Estadual","Nacional","Internacional"]', NULL, 0, 0, 1, 65),
  (@f, 'Fluxo de visitantes', 'origem_nacional', 'Origem dos turistas nacionais (até 5 estados)', 'tabela', '["Estado"]', NULL, 0, 0, 1, 66),
  (@f, 'Fluxo de visitantes', 'origem_internacional', 'Origem dos turistas internacionais (até 5 países)', 'tabela', '["País"]', NULL, 0, 0, 1, 67),
  (@f, 'Fluxo de visitantes', 'ano_base', 'Ano-base', 'numero', NULL, NULL, 0, 0, 1, 68),
  (@f, 'Fluxo de visitantes', 'atrativos_mais_visitados', 'Atrativos mais visitados', 'texto_longo', NULL, NULL, 0, 1, 1, 69),
  (@f, 'Segmentos', 'segmentos', 'Segmentos ou tipos de turismo em que é especializado (até 3)', 'multipla', '["Aventura","Ecoturismo","Sol e praia","Rural","Estudos e intercâmbio","Negócios e eventos","Cultural (cívico, étnico, religioso, místico e esotérico)","Náutico","Esporte","Saúde (bem-estar e médico)","Pesca","Não é especializado em nenhum segmento"]', NULL, 0, 1, 1, 70),
  (@f, 'Legislação municipal', 'legislacao', 'Legislação municipal', 'tabela', '["Instrumento (Lei Orgânica, Ocupação do solo, Plano de desenvolvimento do turismo, Proteção ambiental, Apoio à cultura, Incentivos fiscais ao turismo, Plano Diretor, Fundo Municipal de Turismo, Outras)","Tipo\\/número\\/data"]', NULL, 0, 0, 1, 71);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 2);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Via', 'jurisdicao', 'Jurisdição', 'escolha', '["Federal","Estadual","Municipal"]', NULL, 0, 1, 1, 1),
  (@f, 'Via', 'extensao_municipio', 'Extensão da rodovia no âmbito do município (km)', 'numero', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Via', 'faixas', 'Faixas de rolamento', 'multipla', '["Duas","Três","Quatro","Mão única","Mão dupla","Acostamento"]', NULL, 0, 1, 1, 3),
  (@f, 'Via', 'pavimentacao', 'Pavimentação', 'multipla', '["Asfalto","Concreto","Paralelepípedo","Saibro","Asfalto ecológico","Chão batido"]', NULL, 0, 1, 1, 4),
  (@f, 'Via', 'pedagio', 'Pedágio', 'sim_nao', NULL, NULL, 0, 1, 1, 5),
  (@f, 'Via', 'municipios_interligados', 'Municípios vizinhos interligados pela rodovia', 'texto_longo', NULL, NULL, 0, 1, 1, 6),
  (@f, 'Serviços ao longo da via', 'combustiveis', 'Combustíveis nos postos ao longo da rodovia', 'multipla', '["Álcool","Gasolina","Diesel","Gás natural veicular"]', NULL, 0, 1, 1, 7),
  (@f, 'Serviços ao longo da via', 'servicos_via', 'Outros serviços ao longo da rodovia', 'multipla', '["Alimentação","Hospedagem","Posto de informação","Polícia Rodoviária","Polícia Militar","Telefone público","Serviços mecânicos","Socorro mecânico","Socorro médico","Loja de souvenir","Produtos típicos","Supermercado","Outros"]', '["Telefone público"]', 0, 1, 1, 8),
  (@f, 'Serviços ao longo da via', 'estruturas_via', 'Estruturas ao longo da via', 'multipla', '["Ponte","Passarela","Viaduto","Galeria\\/túnel","Atrativo turístico natural"]', NULL, 0, 1, 1, 9),
  (@f, 'Questões ambientais/sociais', 'questoes_ambientais', 'Questões ambientais/sociais observadas', 'multipla', '["Poluição","Lixo","Desmatamento","Queimadas","Insegurança","Extrativismo","Prostituição","Ocupações irregulares\\/invasão","Outras"]', NULL, 0, 0, 1, 10),
  (@f, 'Questões ambientais/sociais', 'questoes_especificacao', 'Especificação das questões ambientais/sociais', 'tabela', '["Questão","Especificação"]', NULL, 0, 0, 1, 11);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 3);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Autoridades', 'autoridades', 'Autoridades presentes', 'multipla', '["Polícia Federal","Receita Federal","Ministério da Agricultura","Anvisa","ANTT","Juizado de Menores"]', NULL, 0, 0, 1, 1),
  (@f, 'Cadastros e licenciamentos', 'licenciamentos', 'Cadastros, classificações, licenciamentos e outros', 'tabela', '["Âmbito","Entidade","Categoria\\/tipo\\/classificação\\/número"]', NULL, 0, 0, 1, 2),
  (@f, 'Fluxo de passageiros', 'passageiros_ano', 'Passageiros por ano (nº)', 'numero', NULL, NULL, 0, 0, 1, 3),
  (@f, 'Fluxo de passageiros', 'passageiros_alta', 'Passageiros na alta temporada (nº)', 'numero', NULL, NULL, 0, 0, 1, 4),
  (@f, 'Fluxo de passageiros', 'meses_alta_temporada', 'Meses de alta temporada', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 0, 1, 5),
  (@f, 'Fluxo de passageiros', 'embarque_nacional', 'Embarque nacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 6),
  (@f, 'Fluxo de passageiros', 'embarque_internacional', 'Embarque internacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 7),
  (@f, 'Fluxo de passageiros', 'desembarque_nacional', 'Desembarque nacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 8),
  (@f, 'Fluxo de passageiros', 'desembarque_internacional', 'Desembarque internacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 9),
  (@f, 'Fluxo de passageiros', 'origem_nacional', 'Origem dos passageiros nacionais (até 5 estados)', 'tabela', '["Estado"]', NULL, 0, 0, 1, 10),
  (@f, 'Fluxo de passageiros', 'origem_internacional', 'Origem dos passageiros internacionais (até 5 países)', 'tabela', '["País"]', NULL, 0, 0, 1, 11),
  (@f, 'Fluxo de passageiros', 'ano_base', 'Ano-base', 'numero', NULL, NULL, 0, 0, 1, 12),
  (@f, 'Equipamentos e serviços', 'equipamentos_servicos', 'Equipamentos e serviços', 'multipla', '["Banco ou caixa eletrônico","Bonbonnière","Guarda-volumes","Instalações sanitárias","Banca de jornais e revistas","Lanchonete\\/bar","Restaurante","Loja de souvenir","Capela","Posto médico","Posto policial","Serviço de informações","Serviço de informações turísticas","Serviço de som","Locadoras de veículos","Táxi","Correios","Sala vip","Casas de câmbio","Cinema","Free shop","Serviço de telefonia","Serviço de internet","Outros"]', NULL, 0, 1, 1, 13),
  (@f, 'Abrangência operacional', 'abrangencia', 'Abrangência operacional', 'multipla', '["Urbano","Interurbano","Internacional"]', NULL, 0, 1, 1, 14),
  (@f, 'Abrangência operacional', 'linhas_regulares', 'Linhas regulares', 'tabela', '["Abrangência","Empresa","Trecho","Frequência","Duração"]', NULL, 0, 1, 1, 15),
  (@f, 'Intermodalidade', 'intermodalidade', 'Facilidades de deslocamento/intermodalidade', 'multipla', '["Metrô","Trem","Avião","Helicóptero","Marítimo","Fluvial","Lacustre","Outras"]', NULL, 0, 1, 1, 16);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 4);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Via', 'jurisdicao', 'Jurisdição', 'escolha', '["Federal","Estadual","Municipal"]', NULL, 0, 1, 1, 1),
  (@f, 'Via', 'extensao_municipio', 'Extensão da ferrovia/metrovia no âmbito do município (km)', 'numero', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Via', 'tipo_servico', 'Tipo de serviço', 'multipla', '["Carga","Passageiros","Turismo","Outros"]', NULL, 0, 1, 1, 3),
  (@f, 'Via', 'municipios_interligados', 'Municípios vizinhos interligados', 'texto_longo', NULL, NULL, 0, 1, 1, 4),
  (@f, 'Estruturas', 'estruturas_via', 'Estruturas ao longo da via', 'multipla', '["Ponte","Passarela","Viaduto","Galeria\\/túnel","Atrativo turístico natural","Outras"]', NULL, 0, 1, 1, 5),
  (@f, 'Questões ambientais/sociais', 'questoes_ambientais', 'Questões ambientais/sociais observadas', 'multipla', '["Poluição","Lixo","Desmatamento","Queimadas","Insegurança","Extrativismo","Prostituição","Ocupações irregulares\\/invasão","Outras"]', NULL, 0, 0, 1, 6),
  (@f, 'Questões ambientais/sociais', 'questoes_especificacao', 'Especificação das questões ambientais/sociais', 'tabela', '["Questão","Especificação"]', NULL, 0, 0, 1, 7);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 5);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Autoridades', 'autoridades', 'Autoridades presentes', 'multipla', '["Polícia Federal","Receita Federal","Ministério da Agricultura","Anvisa","ANTT","Juizado de Menores","Outras"]', NULL, 0, 0, 1, 1),
  (@f, 'Cadastros e licenciamentos', 'licenciamentos', 'Cadastros, classificações, licenciamentos e outros', 'tabela', '["Âmbito","Entidade","Categoria\\/tipo\\/classificação\\/número"]', NULL, 0, 0, 1, 2),
  (@f, 'Fluxo de passageiros', 'passageiros_ano', 'Passageiros por ano (nº)', 'numero', NULL, NULL, 0, 0, 1, 3),
  (@f, 'Fluxo de passageiros', 'passageiros_alta', 'Passageiros na alta temporada (nº)', 'numero', NULL, NULL, 0, 0, 1, 4),
  (@f, 'Fluxo de passageiros', 'meses_alta_temporada', 'Meses de alta temporada', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 0, 1, 5),
  (@f, 'Fluxo de passageiros', 'embarque_nacional', 'Embarque nacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 6),
  (@f, 'Fluxo de passageiros', 'embarque_internacional', 'Embarque internacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 7),
  (@f, 'Fluxo de passageiros', 'desembarque_nacional', 'Desembarque nacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 8),
  (@f, 'Fluxo de passageiros', 'desembarque_internacional', 'Desembarque internacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 9),
  (@f, 'Fluxo de passageiros', 'origem_nacional', 'Origem dos passageiros nacionais (até 5 estados)', 'tabela', '["Estado"]', NULL, 0, 0, 1, 10),
  (@f, 'Fluxo de passageiros', 'origem_internacional', 'Origem dos passageiros internacionais (até 5 países)', 'tabela', '["País"]', NULL, 0, 0, 1, 11),
  (@f, 'Fluxo de passageiros', 'ano_base', 'Ano-base', 'numero', NULL, NULL, 0, 0, 1, 12),
  (@f, 'Equipamentos e serviços', 'equipamentos_servicos', 'Equipamentos e serviços', 'multipla', '["Banco ou caixa eletrônico","Bonbonnière","Guarda-volumes","Instalações sanitárias","Banca de jornais e revistas","Lanchonete\\/bar","Restaurante","Loja de souvenir","Posto policial","Posto médico","Serviço de internet","Serviço de informações","Serviço de informações turísticas","Serviço de som","Locadora de veículos","Táxi","Correios","Sala vip","Casa de câmbio","Cinema","Capela","Free shop","Serviço de telefonia","Outros"]', NULL, 0, 1, 1, 13),
  (@f, 'Abrangência operacional', 'abrangencia', 'Abrangência operacional', 'multipla', '["Urbano","Interurbano","Internacional"]', NULL, 0, 1, 1, 14),
  (@f, 'Abrangência operacional', 'linhas_regulares', 'Linhas regulares', 'tabela', '["Abrangência","Empresa","Trecho","Frequência","Duração"]', NULL, 0, 1, 1, 15),
  (@f, 'Intermodalidade', 'intermodalidade', 'Facilidades de deslocamento/intermodalidade', 'multipla', '["Rodoviário","Avião","Helicóptero","Marítimo","Fluvial","Lacustre","Outras"]', NULL, 0, 1, 1, 16);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 6);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Autoridades', 'autoridades', 'Autoridades presentes', 'multipla', '["Infraero","Polícia Federal","Receita Federal","Ministério da Agricultura","Anvisa","ANAC","Juizado de Menores","Outras"]', NULL, 0, 0, 1, 1),
  (@f, 'Cadastros e licenciamentos', 'licenciamentos', 'Cadastros, classificações, licenciamentos e outros', 'tabela', '["Âmbito","Entidade","Categoria\\/tipo\\/classificação\\/número"]', NULL, 0, 0, 1, 2),
  (@f, 'Fluxo de passageiros', 'passageiros_ano', 'Passageiros por ano (nº)', 'numero', NULL, NULL, 0, 0, 1, 3),
  (@f, 'Fluxo de passageiros', 'passageiros_alta', 'Passageiros na alta temporada (nº)', 'numero', NULL, NULL, 0, 0, 1, 4),
  (@f, 'Fluxo de passageiros', 'meses_alta_temporada', 'Meses de alta temporada', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 0, 1, 5),
  (@f, 'Fluxo de passageiros', 'embarque_nacional', 'Embarque nacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 6),
  (@f, 'Fluxo de passageiros', 'embarque_internacional', 'Embarque internacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 7),
  (@f, 'Fluxo de passageiros', 'desembarque_nacional', 'Desembarque nacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 8),
  (@f, 'Fluxo de passageiros', 'desembarque_internacional', 'Desembarque internacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 9),
  (@f, 'Fluxo de passageiros', 'origem_nacional', 'Origem dos passageiros nacionais (até 5 estados)', 'tabela', '["Estado"]', NULL, 0, 0, 1, 10),
  (@f, 'Fluxo de passageiros', 'origem_internacional', 'Origem dos passageiros internacionais (até 5 países)', 'tabela', '["País"]', NULL, 0, 0, 1, 11),
  (@f, 'Fluxo de passageiros', 'ano_base', 'Ano-base', 'numero', NULL, NULL, 0, 0, 1, 12),
  (@f, 'Equipamentos e serviços', 'equipamentos_servicos', 'Equipamentos e serviços', 'multipla', '["Banco ou caixa eletrônico","Bonbonnière","Guarda-volumes","Instalações sanitárias","Banca de jornais e revistas","Lanchonete\\/bar","Restaurante","Loja de souvenir","Capela","Posto médico","Polícia Civil","Serviço de informações","Serviço de informações turísticas","Serviço de som","Locadora de veículos","Táxi","Correios","Sala vip","Cinema","Serviço de telefonia","Free shop","Casa de câmbio","Táxi aéreo","Serviço de internet","Outros"]', NULL, 0, 1, 1, 13),
  (@f, 'Operação', 'servicos_pista', 'Serviços de pista', 'multipla', '["Auxílio luminoso de aproximação","Indicador de direção de pouso","Farol","Pista de instrumento","Pista de não instrumento","Sinalização luminosa","Sinalização horizontal","Sinalização vertical","Pista de taxiamento","Pátio de estacionamento","Outros"]', NULL, 0, 0, 1, 14),
  (@f, 'Operação', 'tipo_operacao', 'Tipo de operação', 'multipla', '["Carga","Passageiros","Outros"]', NULL, 0, 1, 1, 15),
  (@f, 'Operação', 'capacidade_operacional', 'Capacidade operacional', 'multipla', '["Avião de grande porte","Avião de médio porte","Avião de pequeno porte","Helicóptero"]', NULL, 1, 1, 1, 16),
  (@f, 'Operação', 'turnos_operacao', 'Turnos de operação', 'multipla', '["Operação diurna","Operação noturna"]', NULL, 0, 1, 1, 17),
  (@f, 'Operação', 'formas_operacao', 'Formas de operação', 'multipla', '["Operação visual","Operação por instrumento"]', NULL, 0, 0, 1, 18),
  (@f, 'Características operacionais', 'area_sitio', 'Área do sítio aeroportuário (m²)', 'numero', NULL, NULL, 0, 0, 1, 19),
  (@f, 'Características operacionais', 'area_patio', 'Área do pátio das aeronaves (m²)', 'numero', NULL, NULL, 0, 0, 1, 20),
  (@f, 'Características operacionais', 'area_terminal', 'Área do terminal de passageiros (m²)', 'numero', NULL, NULL, 0, 0, 1, 21),
  (@f, 'Características operacionais', 'posicoes_aeronaves', 'Nº de posições do estacionamento de aeronaves', 'numero', NULL, NULL, 0, 0, 1, 22),
  (@f, 'Características operacionais', 'balcoes_checkin', 'Nº de balcões de check-in', 'numero', NULL, NULL, 0, 0, 1, 23),
  (@f, 'Características operacionais', 'numero_pistas', 'Nº de pistas', 'numero', NULL, NULL, 0, 0, 1, 24),
  (@f, 'Abrangência operacional', 'abrangencia', 'Abrangência operacional', 'multipla', '["Regional","Nacional","Internacional"]', NULL, 0, 1, 1, 25),
  (@f, 'Abrangência operacional', 'linhas_regulares', 'Linhas regulares', 'tabela', '["Abrangência","Empresa","Trecho","Frequência","Duração"]', NULL, 0, 1, 1, 26),
  (@f, 'Intermodalidade', 'intermodalidade', 'Facilidades de deslocamento/intermodalidade', 'multipla', '["Rodoviário","Metrô","Trem","Marítimo","Fluvial","Lacustre","Outras"]', NULL, 0, 1, 1, 27);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 7);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Autoridades', 'autoridades', 'Autoridades presentes', 'multipla', '["Infraero","Polícia Federal","Receita Federal","Ministério da Agricultura","Anvisa","ANAC","Juizado de Menores","Outras"]', NULL, 0, 0, 1, 1),
  (@f, 'Cadastros e licenciamentos', 'licenciamentos', 'Cadastros, classificações, licenciamentos e outros', 'tabela', '["Âmbito","Entidade","Categoria\\/tipo\\/classificação\\/número"]', NULL, 0, 0, 1, 2),
  (@f, 'Fluxo de passageiros', 'passageiros_ano', 'Passageiros por ano (nº)', 'numero', NULL, NULL, 0, 0, 1, 3),
  (@f, 'Fluxo de passageiros', 'passageiros_alta', 'Passageiros na alta temporada (nº)', 'numero', NULL, NULL, 0, 0, 1, 4),
  (@f, 'Fluxo de passageiros', 'meses_alta_temporada', 'Meses de alta temporada', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 0, 1, 5),
  (@f, 'Fluxo de passageiros', 'embarque_nacional', 'Embarque nacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 6),
  (@f, 'Fluxo de passageiros', 'embarque_internacional', 'Embarque internacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 7),
  (@f, 'Fluxo de passageiros', 'desembarque_nacional', 'Desembarque nacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 8),
  (@f, 'Fluxo de passageiros', 'desembarque_internacional', 'Desembarque internacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 9),
  (@f, 'Fluxo de passageiros', 'origem_nacional', 'Origem dos passageiros nacionais (até 5 estados)', 'tabela', '["Estado"]', NULL, 0, 0, 1, 10),
  (@f, 'Fluxo de passageiros', 'origem_internacional', 'Origem dos passageiros internacionais (até 5 países)', 'tabela', '["País"]', NULL, 0, 0, 1, 11),
  (@f, 'Fluxo de passageiros', 'ano_base', 'Ano-base', 'numero', NULL, NULL, 0, 0, 1, 12),
  (@f, 'Equipamentos e serviços', 'equipamentos_servicos', 'Equipamentos e serviços', 'multipla', '["Banco ou caixa eletrônico","Bonbonnière","Guarda-volumes","Instalações sanitárias","Banca de jornais e revistas","Lanchonete\\/bar","Restaurante","Loja de souvenir","Cinema","Posto médico","Posto policial","Serviço de informações","Serviço de informações turísticas","Serviço de som","Locadora de veículos","Táxi","Correios","Sala vip","Casa de câmbio","Capela","Free shop","Serviço de telefonia","Serviço de internet","Outros"]', NULL, 0, 1, 1, 13),
  (@f, 'Operação', 'categoria', 'Categoria do heliponto', 'escolha', '["Ao nível do solo","Elevado"]', NULL, 0, 1, 1, 14),
  (@f, 'Operação', 'turnos_operacao', 'Turnos de operação', 'multipla', '["Operação diurna","Operação noturna"]', NULL, 0, 1, 1, 15),
  (@f, 'Operação', 'formas_operacao', 'Formas de operação', 'multipla', '["Operação visual","Operação por instrumento"]', NULL, 0, 0, 1, 16),
  (@f, 'Características operacionais', 'area_toque', 'Área de toque (m²)', 'numero', NULL, NULL, 0, 0, 1, 17),
  (@f, 'Características operacionais', 'area_pouso', 'Área de pouso e decolagem (m²)', 'numero', NULL, NULL, 0, 0, 1, 18),
  (@f, 'Características operacionais', 'carga_maxima', 'Carga máxima (t)', 'numero', NULL, NULL, 0, 0, 1, 19),
  (@f, 'Características operacionais', 'area_seguranca', 'Área de segurança (m²)', 'numero', NULL, NULL, 0, 0, 1, 20),
  (@f, 'Intermodalidade', 'intermodalidade', 'Facilidades de deslocamento/intermodalidade', 'multipla', '["Rodoviário","Metrô","Trem","Marítimo","Fluvial","Lacustre","Outras"]', NULL, 0, 1, 1, 21);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 8);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Via', 'jurisdicao', 'Jurisdição', 'escolha', '["Federal","Estadual","Municipal"]', NULL, 0, 1, 1, 1),
  (@f, 'Via', 'extensao_municipio', 'Extensão da hidrovia no âmbito do município (km)', 'numero', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Via', 'municipios_interligados', 'Municípios vizinhos interligados pela hidrovia', 'texto_longo', NULL, NULL, 0, 1, 1, 3),
  (@f, 'Serviços ao longo da via', 'combustiveis', 'Combustíveis nos postos ao longo da hidrovia', 'multipla', '["Álcool","Gasolina","Diesel","Gás natural"]', NULL, 0, 1, 1, 4),
  (@f, 'Serviços ao longo da via', 'servicos_via', 'Outros serviços ao longo da hidrovia', 'multipla', '["Alimentação","Meios de hospedagem","Posto de informação","Telefone público","Socorro médico","Socorro mecânico","Serviços mecânicos","Loja de souvenir","Produtos típicos"]', '["Telefone público"]', 0, 1, 1, 5),
  (@f, 'Serviços ao longo da via', 'estruturas_via', 'Estruturas ao longo da via', 'multipla', '["Ponte","Passarela","Atrativo turístico natural","Outras"]', NULL, 0, 1, 1, 6),
  (@f, 'Questões ambientais/sociais', 'questoes_ambientais', 'Questões ambientais/sociais observadas', 'multipla', '["Poluição","Lixo","Desmatamento","Queimadas","Insegurança","Extrativismo","Prostituição","Ocupações irregulares\\/invasão","Outras"]', NULL, 0, 0, 1, 7),
  (@f, 'Questões ambientais/sociais', 'questoes_especificacao', 'Especificação das questões ambientais/sociais', 'tabela', '["Questão","Especificação"]', NULL, 0, 0, 1, 8);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 9);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Autoridades', 'autoridades', 'Autoridades presentes', 'multipla', '["Capitania dos Portos","DOCAS","Polícia Federal","Receita Federal","Ministério da Agricultura","Anvisa","ANTAQ","Juizado de Menores","Outras"]', NULL, 0, 0, 1, 1),
  (@f, 'Cadastros e licenciamentos', 'licenciamentos', 'Cadastros, classificações, licenciamentos e outros', 'tabela', '["Âmbito","Entidade","Categoria\\/tipo\\/classificação\\/número"]', NULL, 0, 0, 1, 2),
  (@f, 'Fluxo de passageiros', 'passageiros_ano', 'Passageiros por ano (nº)', 'numero', NULL, NULL, 0, 0, 1, 3),
  (@f, 'Fluxo de passageiros', 'passageiros_alta', 'Passageiros na alta temporada (nº)', 'numero', NULL, NULL, 0, 0, 1, 4),
  (@f, 'Fluxo de passageiros', 'meses_alta_temporada', 'Meses de alta temporada', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 0, 1, 5),
  (@f, 'Fluxo de passageiros', 'embarque_nacional', 'Embarque nacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 6),
  (@f, 'Fluxo de passageiros', 'embarque_internacional', 'Embarque internacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 7),
  (@f, 'Fluxo de passageiros', 'desembarque_nacional', 'Desembarque nacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 8),
  (@f, 'Fluxo de passageiros', 'desembarque_internacional', 'Desembarque internacional (nº)', 'numero', NULL, NULL, 0, 0, 1, 9),
  (@f, 'Fluxo de passageiros', 'origem_nacional', 'Origem dos passageiros nacionais (até 5 estados)', 'tabela', '["Estado"]', NULL, 0, 0, 1, 10),
  (@f, 'Fluxo de passageiros', 'origem_internacional', 'Origem dos passageiros internacionais (até 5 países)', 'tabela', '["País"]', NULL, 0, 0, 1, 11),
  (@f, 'Fluxo de passageiros', 'ano_base', 'Ano-base', 'numero', NULL, NULL, 0, 0, 1, 12),
  (@f, 'Equipamentos e serviços', 'equipamentos_servicos', 'Equipamentos e serviços', 'multipla', '["Banco ou caixa eletrônico","Bonbonnière","Guarda-volumes","Instalações sanitárias","Banca de jornais e revistas","Lanchonete\\/bar","Restaurante","Loja de souvenir","Posto médico","Serviço de informações","Serviço de telefonia","Serviço de internet","Serviço de informações turísticas","Serviço de som","Locadora de veículos","Táxi","Correios","Sala vip","Casa de câmbio","Cinema","Capela","Free shop","Outros"]', NULL, 0, 1, 1, 13),
  (@f, 'Operação', 'tipo_operacao', 'Tipo de operação', 'multipla', '["Carga","Passageiros"]', NULL, 0, 1, 1, 14),
  (@f, 'Operação', 'calado', 'Profundidade/calado (m)', 'numero', NULL, NULL, 0, 0, 1, 15),
  (@f, 'Abrangência operacional', 'abrangencia', 'Abrangência operacional', 'multipla', '["Regional","Nacional","Internacional"]', NULL, 0, 1, 1, 16),
  (@f, 'Abrangência operacional', 'linhas_regulares', 'Linhas regulares', 'tabela', '["Abrangência","Empresa","Trecho","Frequência","Duração"]', NULL, 0, 1, 1, 17),
  (@f, 'Intermodalidade', 'intermodalidade', 'Facilidades de deslocamento/intermodalidade', 'multipla', '["Rodoviário","Metrô","Trem","Avião","Helicóptero","Outras"]', NULL, 0, 1, 1, 18);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 10);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'abrangencia', 'Abrangência/circulação/cobertura', 'texto', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Informações gerais', 'outras_informacoes', 'Outras informações', 'texto_longo', NULL, NULL, 0, 1, 1, 2);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 11);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'servicos_especializados', 'Serviços especializados', 'texto_longo', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Informações gerais', 'outras_informacoes', 'Outras informações', 'texto_longo', NULL, NULL, 0, 1, 1, 2);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 12);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'principais_servicos', 'Principais serviços', 'texto_longo', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Informações gerais', 'outras_informacoes', 'Outras informações', 'texto_longo', NULL, NULL, 0, 1, 1, 2);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 13);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Secretaria Municipal de Educação', 'secretaria_nome', 'Nome da Secretaria Municipal de Educação', 'texto', NULL, NULL, 0, 0, 1, 1),
  (@f, 'Secretaria Municipal de Educação', 'secretaria_endereco', 'Endereço da Secretaria', 'texto', NULL, NULL, 0, 0, 1, 2),
  (@f, 'Secretaria Municipal de Educação', 'secretaria_telefone', 'Telefone da Secretaria', 'texto', NULL, NULL, 0, 0, 1, 3),
  (@f, 'Secretaria Municipal de Educação', 'secretaria_email', 'E-mail da Secretaria', 'texto', NULL, NULL, 0, 0, 1, 4),
  (@f, 'Secretaria Municipal de Educação', 'secretaria_site', 'Site da Secretaria', 'texto', NULL, NULL, 0, 0, 1, 5),
  (@f, 'Níveis de ensino', 'niveis_ensino', 'Níveis de ensino', 'tabela', '["Nível (Fundamental, Médio, Graduação, Pós-graduação)","Escolas públicas (nº)","Alunos em escolas públicas (nº)","Fonte (públicas)","Escolas particulares (nº)","Alunos em escolas particulares (nº)","Fonte (particulares)"]', NULL, 0, 0, 1, 6),
  (@f, 'Ensino em turismo', 'tecnico_areas', 'Ensino técnico em turismo e áreas afins', 'multipla', '["Não","Turismo","Gastronomia","Hotelaria","Guia de Turismo","Outros"]', NULL, 0, 0, 1, 7),
  (@f, 'Ensino em turismo', 'bacharelado_areas', 'Bacharelado em turismo e áreas afins', 'multipla', '["Não","Turismo","Gastronomia","Hotelaria","Eventos","Outros"]', NULL, 0, 0, 1, 8),
  (@f, 'Ensino em turismo', 'licenciatura_areas', 'Licenciatura em turismo e áreas afins', 'multipla', '["Não","Turismo","Gastronomia","Hotelaria","Eventos","Outros"]', NULL, 0, 0, 1, 9),
  (@f, 'Ensino em turismo', 'tecnologo_areas', 'Tecnólogo em turismo e áreas afins', 'multipla', '["Não","Turismo","Gastronomia","Hotelaria","Eventos","Outros"]', NULL, 0, 0, 1, 10),
  (@f, 'Ensino em turismo', 'lato_sensu_areas', 'Pós-graduação lato sensu em turismo e áreas afins', 'multipla', '["Não","Turismo","Gastronomia","Hotelaria","Eventos","Outros"]', NULL, 0, 0, 1, 11),
  (@f, 'Ensino em turismo', 'stricto_sensu_areas', 'Pós-graduação stricto sensu em turismo e áreas afins', 'multipla', '["Não","Turismo","Gastronomia","Hotelaria","Eventos","Outros"]', NULL, 0, 0, 1, 12),
  (@f, 'Ensino em turismo', 'instituicoes', 'Instituições de ensino em turismo', 'tabela', '["Modalidade","Instituição","Endereço","Telefones","Endereço eletrônico (e-mail)","Sítio eletrônico (site\\/página web)"]', NULL, 0, 0, 1, 13);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 14);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'imoveis_servicos', 'Tipo de imóveis e serviços oferecidos', 'texto_longo', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Informações gerais', 'outras_informacoes', 'Outras informações', 'texto_longo', NULL, NULL, 0, 1, 1, 2);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 15);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'rede_franquia', 'Nome da rede/franquia', 'texto', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Características', 'total_lojas', 'Número total de lojas', 'numero', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Características', 'equipamentos_produtos', 'Equipamentos, serviços e produtos', 'multipla', '["Antiguidades","Loja de souvenir","Artigos para casa","Banco ou caixa eletrônico","Cinemas","Cine-foto","Farmácia","Floricultura","Joalheria","Acessórios","Ótica","Decoração","Playground","Espaço para shows","Banca de revistas e jornais","Supermercado","Lavanderia","Unidade médica","Laboratório de análises clínicas","Restaurante","Bar\\/lanchonete","Vestuário","Outros"]', NULL, 0, 1, 1, 3),
  (@f, 'Características', 'outros_servicos', 'Outros serviços', 'multipla', '["Posto de informações turísticas","Exposições","Fraldário","Disponibilidade de carrinhos para crianças","Disponibilidade de carros para deficientes","Disponibilidade de cadeira de rodas","Registro de visitantes","Histórico estatístico de registro de visitantes","Outros"]', NULL, 0, 1, 1, 4);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 16);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'rede_franquia', 'Nome da rede/franquia', 'texto', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Características', 'produtos', 'Produtos e serviços', 'multipla', '["Alimentos caseiros\\/regionais","Objetos de decoração","Utilidades para o lar","Artesanato","Arte sacra","Acessórios","Vestuário","Móveis","Esculturas","Pinturas","Gravuras","Desenhos","Fotografias","Loja de souvenirs","Equipamentos\\/produtos cine-fotos","Outros"]', NULL, 1, 1, 1, 2),
  (@f, 'Características', 'outros_servicos', 'Outros serviços', 'multipla', '["Entrega em domicílio","Exposições","Cursos\\/oficinas","Eventos","Leilão","Degustação","Visitas orientadas","Atendimento a estrangeiros","Registro de visitantes","Histórico estatístico de visitantes","Cafeteria\\/bar\\/lanchonete","Vendas pela internet","Outros"]', NULL, 0, 1, 1, 3);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 17);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'principais_servicos', 'Principais serviços', 'texto_longo', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Informações gerais', 'outras_informacoes', 'Outras informações', 'texto_longo', NULL, NULL, 0, 1, 1, 2);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 18);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'especialidade', 'Concessionária/rede/especialidade', 'texto', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Informações gerais', 'principais_servicos', 'Principais serviços', 'texto_longo', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Informações gerais', 'outras_informacoes', 'Outras informações', 'texto_longo', NULL, NULL, 0, 1, 1, 3);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 19);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'outras_informacoes', 'Outras informações', 'texto_longo', NULL, NULL, 0, 1, 1, 1);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 20);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Funcionamento', 'tipo_de_diaria', 'Tipo de diária', 'multipla', '["Sem alimentação","Café da manhã","Meia pensão","Pensão completa","Tudo incluído"]', NULL, 0, 1, 1, 1),
  (@f, 'Unidades habitacionais', 'total_uh', 'Total de UH', 'numero', NULL, NULL, 1, 1, 1, 2),
  (@f, 'Unidades habitacionais', 'total_leitos', 'Total de leitos', 'numero', NULL, NULL, 1, 1, 1, 3),
  (@f, 'Unidades habitacionais', 'uh_adaptadas_pcd', 'UH adaptadas para pessoas com deficiência', 'numero', NULL, NULL, 0, 1, 1, 4),
  (@f, 'Unidades habitacionais', 'produtos_higiene', 'Produtos de higiene pessoal', 'multipla', '["Shampoo","Toalha","Condicionador","Roupão de banho","Sabonete","Touca","Creme dental","Chinelo","Loção","Outros"]', NULL, 0, 1, 1, 5),
  (@f, 'Unidades habitacionais', 'equipamentos_uh', 'Equipamentos e serviços da UH', 'multipla', '["Aparelho de vídeo","DVD","TV com canal aberto","TV com canal por assinatura","Rádio","Telefone","Internet","Ar-condicionado","Ventilador","Calefação","Mesa","Poltrona","Cama king-size","Cama queen-size","Frigobar","Panelas e louças","Micro-ondas","Fogão","Forno elétrico","Cofre","Lareira","Academia","Ofurô","Massagem","Hidromassagem","Sauna seca","Sauna a vapor","Room-service","Room-service 24h","Troca diária de roupa de cama e banho","Controle de iluminação","Luminária para leitura","Outros"]', '["Aparelho de vídeo"]', 0, 1, 1, 6),
  (@f, 'Energia elétrica', 'voltagem', 'Voltagem', 'escolha', '["110 Volts","220 Volts","110\\/220 Volts"]', NULL, 0, 0, 1, 7),
  (@f, 'Energia elétrica', 'capacidade_kva', 'Capacidade em KVA', 'numero', NULL, NULL, 0, 0, 1, 8),
  (@f, 'Energia elétrica', 'gerador_emergencia', 'Gerador de emergência', 'sim_nao', NULL, NULL, 0, 0, 1, 9),
  (@f, 'Energia elétrica', 'gerador_kva', 'Capacidade do gerador em KVA', 'numero', NULL, NULL, 0, 0, 1, 10),
  (@f, 'Área de alimentação', 'restaurante', 'Restaurante', 'multipla', '["Não","Apenas para hóspedes","Aberto ao público","Adaptado para pessoas com deficiência"]', NULL, 0, 1, 1, 11),
  (@f, 'Área de alimentação', 'restaurante_capacidade_dia', 'Capacidade diária do restaurante (nº de pessoas)', 'numero', NULL, NULL, 0, 1, 1, 12),
  (@f, 'Área de alimentação', 'restaurante_capacidade_simultanea', 'Capacidade simultânea do restaurante (nº de pessoas)', 'numero', NULL, NULL, 0, 1, 1, 13),
  (@f, 'Área de alimentação', 'lanchonete', 'Lanchonete', 'multipla', '["Não","Apenas para hóspedes","Aberta ao público","Adaptada para pessoas com deficiência"]', NULL, 0, 1, 1, 14),
  (@f, 'Área de alimentação', 'lanchonete_capacidade_dia', 'Capacidade diária da lanchonete (nº de pessoas)', 'numero', NULL, NULL, 0, 1, 1, 15),
  (@f, 'Área de alimentação', 'lanchonete_capacidade_simultanea', 'Capacidade simultânea da lanchonete (nº de pessoas)', 'numero', NULL, NULL, 0, 1, 1, 16),
  (@f, 'Recreação e lazer', 'instalacoes_lazer', 'Instalações e espaços de lazer', 'multipla', '["Piscina","Piscina aquecida","Piscina infantil","Bar na piscina","Toboágua","Parque infantil","Clube infantil","Quadra poliesportiva","Quadra de tênis","Campo de futebol","Campo de golfe","Pista de cooper","Área verde","Lago panorâmico","Outros"]', NULL, 0, 1, 1, 17),
  (@f, 'Recreação e lazer', 'atividades', 'Outros espaços, equipamentos e atividades', 'multipla', '["Observação de fauna","Observação de flora","Visita a produção agrícola","Degustação gastronômica","Atividades pedagógicas","Atividades programadas para crianças","Atividades programadas para adultos","Atividades equestres","Passeios em barcos e lanchas","Passeios em embarcações alugadas","Realização de cursos, seminários, feiras","Realização de eventos (esportivos, sociais, culturais)","Competições de pesca","Pesca amadora","Equipamentos para mergulho","Pedalinhos","Tirolesa","Arvorismo","Cicloturismo\\/atividade com bicicleta","Caminhadas\\/trilhas interpretativas","Mergulho livre","Esqui aquático","Banana boat","Jet ski","Mini-zoo","Fazendinha","Horta","Centro equestre","Peregrinações e romarias","Retiros espirituais","Venda de souvenir e produtos típicos","Outros"]', NULL, 0, 1, 1, 18),
  (@f, 'Área para eventos', 'instalacoes_eventos', 'Instalações para eventos', 'tabela', '["Espaço","Quantidade","Área total (m²)","Capacidade (nº de pessoas)"]', NULL, 0, 1, 1, 19),
  (@f, 'Área para eventos', 'espacos_eventos_linhas', 'Espaços de eventos (linhas sugeridas)', 'multipla', '["Auditório","Salas modulares","Pavilhão de feiras","Área de exposição coberta","Área de exposição não coberta"]', NULL, 0, 0, 1, 20),
  (@f, 'Área para eventos', 'servicos_eventos', 'Serviços para eventos', 'multipla', '["Realização de eventos próprios","Aluguel de espaço para terceiros","Organização de eventos para terceiros","Aluguel de equipamentos","Serviços de alimentação","Serviços de som","Outros"]', NULL, 0, 1, 1, 21),
  (@f, 'Área para eventos', 'equipamentos_eventos', 'Equipamentos para eventos', 'multipla', '["Cadeiras com prancheta","Poltronas","Ar-condicionado central","Copa","TV com canal aberto","TV com canal por assinatura","Vídeo","DVD","Tela móvel","Tela fixa","Projetor","Retroprojetor","Telefonia","Computador","Internet","Internet sem fio","Equipamento de tradução simultânea","Videoconferência","Sonorização","Parabólica","Palco","Elevador","Outros"]', '["Vídeo","Retroprojetor"]', 0, 1, 1, 22),
  (@f, 'Facilidades e serviços', 'facilidades', 'Facilidades e serviços', 'multipla', '["Adaptador de voltagem","Ar-condicionado","Ventilador","Calefação","Central telefônica","Telefone","Telefones públicos","Internet","Internet sem fio","Impressora","Circuito interno de TV","Forno elétrico","Micro-ondas","Churrasqueira","Bar","Adega climatizada","Barbearia","Lojas","Loja de conveniência","Farmácia","Caixa eletrônico","Cofre","Guarda-volume","Informações turísticas","Agência de turismo","Locadora de veículos","Traslados","Heliponto","Embarcações","Manobrista","Lavanderia","Serviço de copa","Serviço bilíngue","Serviço de babá","Serviço médico","Elevador","Auditório","Sala de reuniões","Sala de convenções","Sala de imprensa","Sala de ginástica","Equipamentos de ginástica","Salão de beleza","Salão de jogos","Exposição de arte\\/artesanato","Passeios e guias","Câmbio","Sistema de segurança eletrônica","Outros"]', '["Telefones públicos"]', 0, 1, 1, 23),
  (@f, 'Facilidades para executivos', 'facilidades_executivos', 'Facilidades para executivos', 'multipla', '["Apartamentos em andares especiais","Check-in e check-out expresso, em ambiente diferenciado","Café da manhã em ambiente privativo","Early check-in e late check-out, conforme disponibilidade","Jornais diários no apartamento","Acesso à internet banda larga","Acesso à internet sem fio","Acesso à internet na UH","Acesso à internet em áreas comuns","Computador","Impressora","Fax","Datashow","Máquina de fotocópia","Sala de reunião","Serviço de chá","Serviço de café","Refrigerantes\\/sucos","Guarda de mala em consignação","Lavagem e passagem de roupa no período noturno","Lustro e graxa para sapatos","Outras"]', '["Fax"]', 0, 1, 1, 24);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 21);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Funcionamento', 'tipo_de_diaria', 'Tipo de diária', 'multipla', '["Sem alimentação","Café da manhã","Meia pensão","Pensão completa","Tudo incluído"]', NULL, 0, 1, 1, 1),
  (@f, 'Equipamentos de acomodação', 'equipamentos_acomodacao', 'Equipamentos de acomodação aceitos', 'tabela', '["Equipamento","Aceita (sim\\/não)","Capacidade de equipamentos (nº)","Capacidade de pessoas (nº)"]', NULL, 0, 1, 1, 2),
  (@f, 'Equipamentos de acomodação', 'tipos_equipamento', 'Tipos de equipamento aceitos', 'multipla', '["Barraca","Trailer","Motor-home","Carreta-barraca","Campers"]', NULL, 0, 1, 1, 3),
  (@f, 'Equipamentos de acomodação', 'capacidade_pessoas', 'Capacidade total de pessoas', 'numero', NULL, NULL, 1, 1, 1, 4),
  (@f, 'Área de acampamento', 'piso_area_acampamento', 'Piso da área de acampamento', 'multipla', '["Pavimentada","Brita","Gramada","Areia","Outras"]', NULL, 0, 1, 1, 5),
  (@f, 'Instalações básicas', 'instalacoes_basicas', 'Instalações básicas', 'tabela', '["Item","Quantidade"]', NULL, 0, 1, 1, 6),
  (@f, 'Instalações básicas', 'instalacoes_basicas_itens', 'Itens de instalações básicas (linhas sugeridas)', 'multipla', '["Sanitário","Chuveiro quente","Chuveiro frio","Pia para louça","Tanque para roupas","Lavatório","Lixeira","Cantina (abastecimento de gêneros básicos para campistas)"]', NULL, 0, 1, 1, 7),
  (@f, 'Energia elétrica', 'voltagem', 'Voltagem', 'escolha', '["110 Volts","220 Volts","110\\/220 Volts"]', NULL, 0, 0, 1, 8),
  (@f, 'Energia elétrica', 'capacidade_kva', 'Capacidade em KVA', 'numero', NULL, NULL, 0, 0, 1, 9),
  (@f, 'Energia elétrica', 'gerador_emergencia', 'Gerador de emergência', 'sim_nao', NULL, NULL, 0, 0, 1, 10),
  (@f, 'Energia elétrica', 'gerador_kva', 'Capacidade do gerador em KVA', 'numero', NULL, NULL, 0, 0, 1, 11),
  (@f, 'Energia elétrica', 'instalacoes_eletricas', 'Instalações elétricas', 'multipla', '["Instalações elétricas","Pontos de energia","Pontos de energia identificados nos módulos","Tomadas de energia identificadas na área de acampamento","Medidores individuais de energia"]', NULL, 0, 1, 1, 12),
  (@f, 'Água', 'agua', 'Água', 'multipla', '["Instalações hidráulicas","Pontos de água nos módulos","Água potável (reservatório ou rede pública)"]', NULL, 0, 1, 1, 13),
  (@f, 'Equipamentos e serviços', 'equipamentos_servicos', 'Equipamentos e serviços', 'multipla', '["Aluguel de barracas","Aluguel de equipamento de esporte e lazer","Aluguel de trailers","TV com canal por assinatura","Internet","Telefone público","Portaria 24h","Churrasqueira","Cozinha","Máquina de lavar pratos","Cofre","Equipamentos de combate a incêndios","Iluminação artificial nas áreas sociais","Iluminação artificial nas vias de acesso","Iluminação artificial nas instalações sanitárias","Iluminação artificial na área de acampamento","Registro e controle de campistas","Para-raio","Sinalização na entrada do acampamento com indicação de vagas","Sinalização nas vias internas","Serviço de coleta diária de lixo","Serviço de limpeza","Serviços e equipamentos de segurança","Serviço de vigilância e guarda do acampamento\\/camping","Ambulatório médico","Esgoto tratado","Coleta seletiva de resíduos","Coleta de esgoto nos módulos","Coleta de água servida para veículos","Coleta de detritos sanitários para veículos","Módulos demarcados para veículos","Módulos demarcados para barracas","Outros"]', '["Telefone público"]', 0, 1, 1, 14),
  (@f, 'Área de alimentação', 'restaurante', 'Restaurante', 'multipla', '["Não","Apenas para hóspedes","Aberto ao público","Adaptado para pessoas com deficiência"]', NULL, 0, 1, 1, 15),
  (@f, 'Área de alimentação', 'restaurante_capacidade_dia', 'Capacidade diária do restaurante (nº de pessoas)', 'numero', NULL, NULL, 0, 1, 1, 16),
  (@f, 'Área de alimentação', 'restaurante_capacidade_simultanea', 'Capacidade simultânea do restaurante (nº de pessoas)', 'numero', NULL, NULL, 0, 1, 1, 17),
  (@f, 'Área de alimentação', 'lanchonete', 'Lanchonete', 'multipla', '["Não","Apenas para hóspedes","Aberto ao público","Adaptado para pessoas com deficiência"]', NULL, 0, 1, 1, 18),
  (@f, 'Área de alimentação', 'lanchonete_capacidade_dia', 'Capacidade diária da lanchonete (nº de pessoas)', 'numero', NULL, NULL, 0, 1, 1, 19),
  (@f, 'Área de alimentação', 'lanchonete_capacidade_simultanea', 'Capacidade simultânea da lanchonete (nº de pessoas)', 'numero', NULL, NULL, 0, 1, 1, 20),
  (@f, 'Recreação e lazer', 'instalacoes_lazer', 'Instalações de lazer', 'multipla', '["Piscina","Piscina aquecida","Piscina infantil","Bar na piscina","Toboágua","Parque infantil","Quadra poliesportiva","Quadra de tênis","Quadra de areia","Campo de futebol","Campo de golfe","Pista de cooper","Área verde","Outras"]', NULL, 0, 1, 1, 21),
  (@f, 'Recreação e lazer', 'atividades', 'Outros espaços e atividades', 'multipla', '["Observação de fauna","Observação de flora","Visita à produção agrícola","Degustação gastronômica","Atividades pedagógicas","Atividades equestres\\/passeios em cavalo\\/cavalgadas","Passeios em barcos e lanchas","Passeios em embarcações alugadas","Passeios para cinemas e teatros","Passeios para compras","Realização de cursos, seminários, feiras","Recreação programada para crianças","Recreação programada para adultos","Recreação programada para participantes de convenções","Lago panorâmico","Competições de pesca","Pesca amadora","Mergulho livre\\/equipamentos para mergulho","Pedalinhos","Banana boat","Jet ski","Esqui aquático","Tirolesa","Arvorismo","Caminhadas\\/trilhas interpretativas","Mini-zoo","Fazendinha","Horta","Área verde para lazer","Aula de tênis","Organização de eventos esportivos","Peregrinações e romarias","Retiros espirituais","Venda de souvenir e produtos típicos","Passeios para festas, festivais, celebrações locais e manifestações populares","Passeios para estâncias hidrominerais, spas, balneários, resorts, clínicas naturalistas","Outros"]', NULL, 0, 1, 1, 22),
  (@f, 'Área para eventos', 'instalacoes_eventos', 'Instalações para eventos', 'tabela', '["Espaço","Quantidade","Área total (m²)","Capacidade (nº de pessoas)"]', NULL, 0, 1, 1, 23),
  (@f, 'Área para eventos', 'utilizacao_eventos', 'Utilização da área de eventos', 'multipla', '["Eventos promovidos","Aluguel de espaço para evento","Organização de eventos","Serviço de alimentação para evento","Aluguel de equipamentos","Outras"]', NULL, 0, 1, 1, 24);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 22);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Capacidade de atendimento', 'capacidade_dia', 'Capacidade instalada por dia (nº de pessoas)', 'numero', NULL, NULL, 1, 1, 1, 1),
  (@f, 'Capacidade de atendimento', 'capacidade_dia_sentadas', 'Pessoas atendidas sentadas por dia (nº)', 'numero', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Capacidade de atendimento', 'capacidade_simultanea', 'Capacidade simultânea (nº de pessoas)', 'numero', NULL, NULL, 1, 1, 1, 3),
  (@f, 'Capacidade de atendimento', 'capacidade_simultanea_sentadas', 'Pessoas atendidas sentadas simultaneamente (nº)', 'numero', NULL, NULL, 0, 1, 1, 4),
  (@f, 'Serviços e equipamentos', 'servicos_equipamentos', 'Serviços e equipamentos', 'multipla', '["Música ao vivo","Música ambiente","Espaço para eventos","Atendimento a grupos","Manobrista","Ar-condicionado","Ventilador","Calefação","Lareira","Adega","Internet sem fio","Área de lazer para crianças","Recreação para crianças","Área para fumantes","Cardápio em Braille","Cardápio em língua estrangeira","Carta de vinhos","Sanitário próprio","Outros"]', NULL, 0, 1, 1, 5),
  (@f, 'Gastronomia', 'cozinha_pais', 'Cozinha por país', 'multipla', '["Não","Alemã","Americana","Árabe","Argentina","Asiática","Brasileira","Chinesa","Coreana","Escandinava","Espanhola","Francesa","Grega","Indiana","Italiana","Japonesa","Judaica","Libanesa","Marroquina","Mexicana","Polonesa","Portuguesa","Suíça","Tailandesa","Turca","Uruguaia","Vietnamita","Outras"]', NULL, 1, 1, 1, 6),
  (@f, 'Gastronomia', 'cozinha_regiao', 'Cozinha brasileira por região', 'multipla', '["Não","Amazônica","Campeira gaúcha","Capixaba","Goiana","Mineira","Carioca","Nordestina","Pantaneira","Paulista","Outras"]', NULL, 0, 1, 1, 7),
  (@f, 'Gastronomia', 'especializacao', 'Especialização', 'multipla', '["Não","Cachaçaria","Cafeteria","Café colonial","Cervejaria","Churrascaria","Creperia","Tapiocaria","Doceria","Empaderia","Frutos do mar","Grelhados (grill)","Galeteria","Pastelaria","Petisqueria (snack bar)","Pizzaria","Sanduicheria","Sucos","Chás","Whiskeria","Sorveteria","Outros"]', NULL, 0, 1, 1, 8),
  (@f, 'Gastronomia', 'tipo_dieta', 'Tipo de dieta', 'multipla', '["Não","Macrobiótica","Vegetariana","Natural","Outros"]', NULL, 0, 1, 1, 9),
  (@f, 'Gastronomia', 'tipo_servico', 'Tipo de serviço', 'multipla', '["A la carte","Autosserviço (self service) por preço fixo","Autosserviço (self service) por quilo","Rodízio","Rodízio com bufê (buffet)","Atendimento no carro (drive-thru)","Rápida (fast food)","Entrega em domicílio (delivery)","Outros"]', NULL, 1, 1, 1, 10);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 23);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Fluxo de vendas', 'vendas_emissivo', 'Total de vendas emissivo (nº)', 'numero', NULL, NULL, 0, 0, 1, 1),
  (@f, 'Fluxo de vendas', 'destinos_emissivos_nacionais', 'Principais destinos emissivos nacionais (até 5)', 'tabela', '["Destino"]', NULL, 0, 0, 1, 2),
  (@f, 'Fluxo de vendas', 'destinos_emissivos_internacionais', 'Principais destinos emissivos internacionais (até 5)', 'tabela', '["Destino"]', NULL, 0, 0, 1, 3),
  (@f, 'Fluxo de vendas', 'vendas_receptivo', 'Total de vendas receptivo (nº)', 'numero', NULL, NULL, 0, 0, 1, 4),
  (@f, 'Fluxo de vendas', 'origem_receptivo_nacional', 'Origem do receptivo nacional (até 5)', 'tabela', '["Origem"]', NULL, 0, 0, 1, 5),
  (@f, 'Fluxo de vendas', 'origem_receptivo_internacional', 'Origem do receptivo internacional (até 5)', 'tabela', '["Origem"]', NULL, 0, 0, 1, 6),
  (@f, 'Fluxo de vendas', 'ano_base', 'Ano-base', 'numero', NULL, NULL, 0, 0, 1, 7),
  (@f, 'Fluxo de vendas', 'vendas_alta_temporada', 'Vendas na alta temporada (nº pax)', 'numero', NULL, NULL, 0, 0, 1, 8),
  (@f, 'Fluxo de vendas', 'meses_alta_temporada', 'Meses de alta temporada', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 0, 1, 9),
  (@f, 'Fluxo de vendas', 'vendas_baixa_temporada', 'Vendas na baixa temporada (nº pax)', 'numero', NULL, NULL, 0, 0, 1, 10),
  (@f, 'Fluxo de vendas', 'meses_baixa_temporada', 'Meses de baixa temporada', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 0, 1, 11),
  (@f, 'Serviços básicos', 'bilhetes_terrestres', 'Bilhetes terrestres', 'multipla', '["Reserva","Emissão","Alteração","Remarcação","Cancelamento","Tarifa net","Tarifa comissionada","Tarifa especial para agentes de viagem","Tarifa especial para empresas"]', NULL, 0, 1, 1, 12),
  (@f, 'Serviços básicos', 'bilhetes_aereos', 'Bilhetes aéreos', 'multipla', '["Reserva","Emissão","Alteração","Remarcação","Cancelamento","Tarifa net","Tarifa comissionada","Tarifa especial para agentes de viagem","Tarifa especial para empresas"]', NULL, 0, 1, 1, 13),
  (@f, 'Serviços básicos', 'pacotes_turisticos', 'Pacotes turísticos', 'multipla', '["Reserva","Emissão","Alteração","Remarcação","Cancelamento","Venda direta","Venda via agentes"]', NULL, 0, 1, 1, 14),
  (@f, 'Serviços básicos', 'cruzeiros_maritimos', 'Cruzeiros marítimos', 'multipla', '["Reserva","Emissão","Alteração","Remarcação","Cancelamento","Venda direta","Venda via agentes"]', NULL, 0, 1, 1, 15),
  (@f, 'Serviços básicos', 'meios_hospedagem', 'Meios de hospedagem', 'multipla', '["Reserva","Emissão","Alteração","Remarcação","Cancelamento","Venda direta","Venda via agentes","Tarifas especiais para agentes de viagem","Tarifas especiais para empresas"]', NULL, 0, 1, 1, 16),
  (@f, 'Serviços básicos', 'traslados_passeios', 'Traslados, passeios, visitas e outros', 'multipla', '["Reserva","Emissão","Alteração","Remarcação","Cancelamento","Venda direta","Venda via agentes","Tarifas especiais para agentes de viagem","Tarifas especiais para empresas"]', NULL, 0, 1, 1, 17),
  (@f, 'Serviços básicos', 'seguro_viagem', 'Seguro de viagem', 'multipla', '["Reserva","Emissão","Alteração","Remarcação","Cancelamento","Venda direta","Venda via agentes","Tarifas especiais para agentes de viagem","Tarifas especiais para empresas"]', NULL, 0, 1, 1, 18),
  (@f, 'Serviços básicos', 'locacao_automoveis', 'Locação de automóveis', 'multipla', '["Reserva","Emissão","Alteração","Remarcação","Cancelamento","Venda direta","Venda via agentes","Tarifas especiais para agentes de viagem","Tarifas especiais para empresas"]', NULL, 0, 1, 1, 19),
  (@f, 'Serviços básicos', 'cambio', 'Câmbio', 'sim_nao', NULL, NULL, 0, 1, 1, 20),
  (@f, 'Serviços básicos', 'despacho_documentos', 'Apoio a despachos de documentos', 'multipla', '["Passaporte","Visto","Outros"]', NULL, 0, 1, 1, 21),
  (@f, 'Serviços básicos', 'outros_servicos_basicos', 'Outros serviços básicos', 'texto', NULL, NULL, 0, 1, 1, 22),
  (@f, 'Serviços e atividades especializadas', 'atividades_especializadas', 'Serviços e atividades especializadas', 'multipla', '["Visita técnica e missões empresariais","Atividades pedagógicas","Viagens corporativas","Viagens para torcedores esportivos","Visitas a propriedades rurais","Cursos técnicos e estágios","Organização de eventos esportivos","Organização de viagens de equipes esportivas","Cruzeiros","Viagens para estâncias hidrominerais, spas, balneários, resorts, clínicas naturalistas","Intercâmbio estudantil e\\/ou universitário","Realização de cursos, seminários, feiras","Viagens para fins médico-hospitalares","Kitesurf e\\/ou Windsurf","Escalada","Observação da fauna","Acampamentos","Degustação gastronômica","Passeios para compras","Visitas a sítios históricos","Pesca amadora","Atividades equestres","Atividades em caverna (Espeleoturismo)","Atividades fora de estrada (veículos 4x4, buggys, motos)","Atividades com bicicleta","Atividades aquáticas (jet ski, banana boat, esqui-aquático)","Passeios náuticos","Passeios para festas, festivais, celebrações locais e manifestações populares","Safáris fotográficos","Visitas a sítios arqueológicos\\/paleontológicos","Visitas a locais e eventos religiosos","Visitas a monumentos e celebrações cívicas","Visitas a museus e casas de cultura","Visitas a comunidades tradicionais e\\/ou étnicas","Visitas a locais místicos e esotéricos","Visitas a feiras e mercados","Caminhada de curta duração (sem pernoite)","Caminhada de longa duração (com pernoite)","Arvorismo","Canoagem","Mergulho","Boia-cross","Flutuação","Rafting","Balonismo","Tirolesa","Surfe","Rapel","Canoismo","Montanhismo","Bungee-jump","Voo livre","Parapente","Paraquedismo","Cachoeirismo","Outros"]', NULL, 0, 1, 1, 23),
  (@f, 'Serviços de transporte', 'transporte_terrestre', 'Transporte terrestre', 'sim_nao', NULL, NULL, 0, 1, 1, 24),
  (@f, 'Serviços de transporte', 'veiculos_terrestres', 'Veículos terrestres', 'tabela', '["Tipo de veículo","Próprio ou de terceiros"]', NULL, 0, 1, 1, 25),
  (@f, 'Serviços de transporte', 'tipos_veiculo_terrestre', 'Tipos de veículo terrestre', 'multipla', '["Automóvel de passeio","Buggy","Motocicleta","Caminhão","Caminhonete","Ônibus","Utilitário","Trem","Outros"]', NULL, 0, 1, 1, 26),
  (@f, 'Serviços de transporte', 'total_veiculos_terrestres', 'Total de veículos terrestres', 'numero', NULL, NULL, 0, 0, 1, 27),
  (@f, 'Serviços de transporte', 'veiculos_terrestres_adaptados_pct', 'Veículos terrestres adaptados (%)', 'numero', NULL, NULL, 0, 1, 1, 28),
  (@f, 'Serviços de transporte', 'tipo_servico_terrestre', 'Tipo de serviço terrestre', 'multipla', '["Aluguel","Carga","Passeio"]', NULL, 0, 1, 1, 29),
  (@f, 'Serviços de transporte', 'transporte_aquatico', 'Transporte aquático', 'sim_nao', NULL, NULL, 0, 1, 1, 30),
  (@f, 'Serviços de transporte', 'embarcacoes', 'Embarcações', 'tabela', '["Tipo de embarcação","Própria ou de terceiros"]', NULL, 0, 1, 1, 31),
  (@f, 'Serviços de transporte', 'tipos_embarcacao', 'Tipos de embarcação', 'multipla', '["Iate","Chalana","Navio","Saveiro","Escuna","Jangada","Traineira","Catamarã","Veleiro","Ferry boat","Lancha","Outras"]', NULL, 0, 1, 1, 32),
  (@f, 'Serviços de transporte', 'total_embarcacoes', 'Total de embarcações', 'numero', NULL, NULL, 0, 0, 1, 33),
  (@f, 'Serviços de transporte', 'embarcacoes_adaptadas_pct', 'Embarcações adaptadas (%)', 'numero', NULL, NULL, 0, 1, 1, 34),
  (@f, 'Serviços de transporte', 'tipo_servico_aquatico', 'Tipo de serviço aquático', 'multipla', '["Aluguel","Carga","Passeio"]', NULL, 0, 1, 1, 35),
  (@f, 'Serviços de transporte', 'caracterizacao_aquatico', 'Caracterização do serviço aquático', 'multipla', '["Mar aberto","Interior"]', NULL, 0, 1, 1, 36),
  (@f, 'Serviços de transporte', 'transporte_aereo', 'Transporte aéreo', 'sim_nao', NULL, NULL, 0, 1, 1, 37),
  (@f, 'Serviços de transporte', 'aeronaves', 'Aeronaves', 'tabela', '["Tipo de aeronave","Própria ou de terceiros"]', NULL, 0, 1, 1, 38),
  (@f, 'Serviços de transporte', 'tipos_aeronave', 'Tipos de aeronave', 'multipla', '["Helicóptero","Avião","Outros"]', NULL, 0, 1, 1, 39),
  (@f, 'Serviços de transporte', 'total_aeronaves', 'Total de aeronaves', 'numero', NULL, NULL, 0, 0, 1, 40),
  (@f, 'Serviços de transporte', 'aeronaves_adaptadas_pct', 'Aeronaves adaptadas (%)', 'numero', NULL, NULL, 0, 1, 1, 41),
  (@f, 'Serviços de transporte', 'tipo_servico_aereo', 'Tipo de serviço aéreo', 'multipla', '["Aluguel","Carga","Passeio"]', NULL, 0, 1, 1, 42);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 24);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Licenciamentos e registros', 'licenciamentos', 'Licenciamentos, registros e outros', 'tabela', '["Âmbito","Entidade","Categoria\\/tipo\\/classificação\\/número"]', NULL, 0, 0, 1, 1),
  (@f, 'Fluxo de passageiros', 'media_anual_passageiros', 'Média anual de passageiros (nº)', 'numero', NULL, NULL, 0, 0, 1, 2),
  (@f, 'Abrangência', 'abrangencia', 'Abrangência', 'multipla', '["Municipal","Intermunicipal metropolitano","Intermunicipal estadual","Interestadual","Internacional continental","Internacional intercontinental"]', NULL, 0, 1, 1, 3),
  (@f, 'Perfil dos veículos', 'idade_media_veiculos', 'Idade média dos veículos (anos)', 'numero', NULL, NULL, 0, 0, 1, 4),
  (@f, 'Perfil dos veículos', 'veiculos_adaptados', 'Quantidade de veículos adaptados (nº)', 'numero', NULL, NULL, 0, 1, 1, 5),
  (@f, 'Serviços de transporte', 'transporte_terrestre', 'Transporte terrestre', 'sim_nao', NULL, NULL, 0, 1, 1, 6),
  (@f, 'Serviços de transporte', 'veiculos_terrestres', 'Veículos terrestres', 'tabela', '["Tipo de veículo","Próprio ou de terceiros"]', NULL, 0, 1, 1, 7),
  (@f, 'Serviços de transporte', 'tipos_veiculo_terrestre', 'Tipos de veículo terrestre', 'multipla', '["Automóvel de passeio","Buggy","Motocicleta","Caminhão","Caminhonete","Ônibus","Utilitário","Trem","Outros"]', NULL, 0, 1, 1, 8),
  (@f, 'Serviços de transporte', 'total_veiculos_terrestres', 'Total de veículos terrestres', 'numero', NULL, NULL, 0, 0, 1, 9),
  (@f, 'Serviços de transporte', 'veiculos_terrestres_adaptados_pct', 'Veículos terrestres adaptados (%)', 'numero', NULL, NULL, 0, 1, 1, 10),
  (@f, 'Serviços de transporte', 'tipo_servico_terrestre', 'Tipo de serviço terrestre', 'multipla', '["Aluguel","Carga","Passeio"]', NULL, 0, 1, 1, 11),
  (@f, 'Serviços de transporte', 'transporte_aquatico', 'Transporte aquático', 'sim_nao', NULL, NULL, 0, 1, 1, 12),
  (@f, 'Serviços de transporte', 'embarcacoes', 'Embarcações', 'tabela', '["Tipo de embarcação","Própria ou de terceiros"]', NULL, 0, 1, 1, 13),
  (@f, 'Serviços de transporte', 'tipos_embarcacao', 'Tipos de embarcação', 'multipla', '["Iate","Chalana","Navio","Saveiro","Escuna","Jangada","Traineira","Catamarã","Veleiro","Ferry boat","Lancha","Outras"]', NULL, 0, 1, 1, 14),
  (@f, 'Serviços de transporte', 'total_embarcacoes', 'Total de embarcações', 'numero', NULL, NULL, 0, 0, 1, 15),
  (@f, 'Serviços de transporte', 'embarcacoes_adaptadas_pct', 'Embarcações adaptadas (%)', 'numero', NULL, NULL, 0, 1, 1, 16),
  (@f, 'Serviços de transporte', 'tipo_servico_aquatico', 'Tipo de serviço aquático', 'multipla', '["Aluguel","Carga","Passeio"]', NULL, 0, 1, 1, 17),
  (@f, 'Serviços de transporte', 'caracterizacao_aquatico', 'Caracterização do serviço aquático', 'multipla', '["Mar aberto","Interior"]', NULL, 0, 1, 1, 18),
  (@f, 'Serviços de transporte', 'transporte_aereo', 'Transporte aéreo', 'sim_nao', NULL, NULL, 0, 1, 1, 19),
  (@f, 'Serviços de transporte', 'aeronaves', 'Aeronaves', 'tabela', '["Tipo de aeronave","Própria ou de terceiros"]', NULL, 0, 1, 1, 20),
  (@f, 'Serviços de transporte', 'tipos_aeronave', 'Tipos de aeronave', 'multipla', '["Helicóptero","Avião","Outros"]', NULL, 0, 1, 1, 21),
  (@f, 'Serviços de transporte', 'total_aeronaves', 'Total de aeronaves', 'numero', NULL, NULL, 0, 0, 1, 22),
  (@f, 'Serviços de transporte', 'aeronaves_adaptadas_pct', 'Aeronaves adaptadas (%)', 'numero', NULL, NULL, 0, 1, 1, 23),
  (@f, 'Serviços de transporte', 'tipo_servico_aereo', 'Tipo de serviço aéreo', 'multipla', '["Aluguel","Carga","Passeio"]', NULL, 0, 1, 1, 24),
  (@f, 'Outros serviços', 'outros_servicos', 'Outros serviços e modalidades', 'multipla', '["Motorista","Motorista bilíngue","Navegador GPS","Assistência 24h","Seguro para ocupantes e terceiros","Disponibilidade de cadeira de bebê","Folhetos\\/mapas","Cobrança de taxa de retorno","Hora\\/período","Diária","Mensal","Km livre","Km controlada","Entrega do veículo em local solicitado pelo cliente"]', NULL, 0, 1, 1, 25);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 25);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'area_construida', 'Área total construída (m²)', 'numero', NULL, NULL, 1, 1, 1, 1),
  (@f, 'Informações gerais', 'area_locavel', 'Área locável (m²)', 'numero', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Fluxo de eventos', 'eventos_ano', 'Eventos por ano (nº)', 'numero', NULL, NULL, 0, 0, 1, 3),
  (@f, 'Fluxo de eventos', 'eventos_alta_temporada', 'Eventos na alta temporada (nº)', 'numero', NULL, NULL, 0, 0, 1, 4),
  (@f, 'Fluxo de eventos', 'meses_alta_temporada', 'Meses de alta temporada', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 0, 1, 5),
  (@f, 'Instalações e espaços', 'espacos', 'Instalações e espaços', 'tabela', '["Espaço","Quantidade","Área total (m²)","Capacidade"]', NULL, 1, 1, 1, 6),
  (@f, 'Instalações e espaços', 'tipos_espaco', 'Tipos de espaço (linhas sugeridas)', 'multipla', '["Área de carga e descarga","Área de exposição coberta","Área de exposição não coberta","Área para cozinha","Auditório","Bar e lanchonete","Cabine de som","Elevador","Escaninho","Espaço multiuso","Instalações sanitárias","Pavilhão de feiras","Praça de alimentação","Restaurante","Sala","Salas modulares","Sistema de som","Teatro","Detector de metais","Saída de emergência","Outros"]', NULL, 0, 1, 1, 7),
  (@f, 'Mobiliário', 'mobiliario', 'Mobiliário', 'tabela', '["Item","Possui","Quantidade"]', NULL, 0, 1, 1, 8),
  (@f, 'Mobiliário', 'itens_mobiliario', 'Itens de mobiliário', 'multipla', '["Cadeira móvel","Cadeira com prancheta","Cadeira com braço","Cadeira sem braço","Mesa","Poltrona"]', NULL, 0, 1, 1, 9),
  (@f, 'Equipamentos e serviços', 'equipamentos_servicos', 'Equipamentos e serviços', 'multipla', '["Montagem de estandes","Bancada","Bandeiras","Bandeja para projeção de slides","Computador","Internet","Internet sem fio","Tela fixa","Tela móvel","Tela para projeção","Quadro lousa","Retroprojetor","Projetor multimídia","Aparelho de scanner","Mesa para projeção de slides","Apontador laser pointer","TV com canal aberto","TV com canal por assinatura","Circuito interno de TV","DVD","Vídeo","Videoconferência","Telefonia","Rede telefônica VLAN","Rede telefônica VOIP","Rede telefônica VPN","Fax","Máquina copiadora","Impressora","Ar-condicionado","Aquecedor","Climatização","Sonorização","Palco","Tablado","Camarins","Salão de beleza","Sala de leitura","Flip chart","Lojas","Lanchonete","Restaurante","Serviço de café","Copa","Serviço de limpeza","Coleta seletiva de lixo","Serviço de segurança","Guarda-volume","Achados e perdidos","Informações turísticas","Sistema de comunicação via satélite","Serviços de assistência tecnológica e mecânica","Equipamento de tradução simultânea","Parabólica","Púlpito","Ambulatório médico","Farmácia","Caixa eletrônico","Manobristas","Outros"]', '["Bandeja para projeção de slides","Retroprojetor","Mesa para projeção de slides","Vídeo","Fax"]', 0, 1, 1, 10),
  (@f, 'Energia elétrica', 'voltagem', 'Voltagem', 'escolha', '["110 Volts","220 Volts","110\\/220 Volts"]', NULL, 0, 0, 1, 11),
  (@f, 'Energia elétrica', 'capacidade_kva', 'Capacidade em KVA', 'numero', NULL, NULL, 0, 0, 1, 12),
  (@f, 'Energia elétrica', 'gerador_emergencia', 'Gerador de emergência', 'sim_nao', NULL, NULL, 0, 0, 1, 13),
  (@f, 'Energia elétrica', 'gerador_kva', 'Capacidade do gerador em KVA', 'numero', NULL, NULL, 0, 0, 1, 14);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 26);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Atividades', 'atividades_basicas', 'Atividades básicas', 'multipla', '["Eventos sociais (casamentos, formaturas, aniversários e outros)","Eventos corporativos, institucionais, científicos, profissionais, promocionais","Eventos esportivos","Eventos culturais e artísticos (shows, festivais, exposições e outros)"]', NULL, 1, 1, 1, 1),
  (@f, 'Atividades', 'outras_atividades', 'Outras atividades e serviços', 'multipla', '["Captação de eventos","Planejamento de eventos","Administração de eventos","Cerimonial","Secretaria preparatória","Secretaria operacional","Secretaria durante o evento","Secretaria pós-evento","Serviço de café\\/buffet","Manobrista","Recepcionista","Limpeza","Sinalização","Filmagem","Fotografia","Gravação","Recrutamento de pessoal","Recreação\\/animação","Efeitos especiais e pirotécnicos","Locação de telões","Gestão","Organização","Promoção","Coordenação","Operacionalização","Produção","Assessoria de eventos","Locação de projetor multimídia","Locação e montagem de equipamentos de som","Locação e montagem de tendas e estandes","Locação e montagem de equipamentos de iluminação","Locação e montagem de equipamento de tradução simultânea","Locação e montagem de estruturas para shows","Locação e montagem de banheiros químicos","Locação e montagem de brinquedos","Locação e montagem de equipamentos de videoconferência","Locação e montagem de camarins","Locação de espaços","Locação de mobiliário","Locação de utensílios"]', NULL, 0, 1, 1, 2);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 27);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'area_total', 'Área total do estabelecimento (m²)', 'numero', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Informações gerais', 'ambientacao_tematica', 'Ambientação temática principal', 'multipla', '["Aventura","Histórico","Infantil","Água","Multitemático"]', NULL, 1, 1, 1, 2),
  (@f, 'Instalações e serviços', 'instalacoes_servicos', 'Outras instalações, equipamentos e serviços', 'multipla', '["Área de exposição coberta","Área de exposição não coberta","Sistema de amplificação","Sala de descanso","Sala de imprensa","Som ambiente","Sinalização interna","Saída de emergência","Iluminação noturna","Instalações sanitárias","Piso antiderrapante","Palco para eventos","Quadra polivalente","Detector de metais","Grade ou proteção","Refletores","Telões","Bebedouro","Chafariz","Coreto","Fraldário","Guarda-volume","Loja de souvenir","Caixa eletrônico","Hospedagem","Restaurante","Bar\\/lanchonete","Telefone público","Achados e perdidos","Disponibilidade de carrinho de bebê","Disponibilidade de cadeira de rodas","Outros"]', '["Telefone público"]', 0, 1, 1, 3),
  (@f, 'Atrações', 'atracoes', 'Equipamentos, brinquedos e atrações', 'multipla', '["Arcade games","Evolution","Teatro","Barco viking","Jogos eletrônicos","Roda-gigante","Barco","Labirinto","Rotor","Brinquedo com reboque","Brinquedo educativo","Brinquedo giratório","Montanha-russa aquática","Montanha-russa","Quadra poliesportiva","Show temático","Carrossel","Simuladores diversos","Campo de futebol","Kartódromo","Carro de bate-bate","Casa assombrada","Parede de escalada","Teleférico","Cascata","Piscina adulto","Piscina olímpica","Piscina infantil","Piscina com ondas","Piscina semiolímpica","Tobogã","Toboágua altura alta","Toboágua altura média","Toboágua altura baixa","Torre aquática alta","Torre aquática média","Torre aquática baixa","Cataclisma","Catapulta","Cinema","Pula-pula aquático","Escorregadores","Playground aquático","Playground","Elevador (brinquedos)","Trem-fantasma","Trenzinho","Aquário","Zoológico","Galeria","Hidromassagem","Sauna","Outros"]', NULL, 0, 1, 1, 4);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 28);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Instalações', 'instalacoes', 'Outras instalações, equipamentos e espaços', 'multipla', '["Área de exposição coberta","Área de exposição não coberta","Área vip separada","Sinalização interna","Centro de convenções","Espaço para festas e eventos","Loja de souvenir","Museu","Shopping","Zoológico","Feiras permanentes","Feiras temporárias","Pista de patinação","Pista de skate","Restaurante","Bar\\/lanchonete","Saída de emergência","Iluminação noturna","Instalações sanitárias","Piso antiderrapante","Palco para eventos","Quadra poliesportiva","Detector de metais","Grade ou proteção","Ambulatório médico","Caixa eletrônico","Guarda-volume","Fraldário","Vestiário","Telefone público","Banca de jornal e revista","Anfiteatro","Refletor","Chafariz","Bebedouro","Coreto","Telões","Mesa","Cadeira","Churrasqueira","Lago","Outros"]', '["Telefone público"]', 0, 1, 1, 1),
  (@f, 'Serviços', 'servicos', 'Serviços', 'multipla', '["Achados e perdidos","Manobrista","Vendedores ambulantes","Serviço de som","Serviço de segurança","Circuito de monitoramento de segurança","Serviço de informações","Disponibilidade de carrinho de bebê","Disponibilidade de cadeira de rodas","Disponibilidade de bicicletas","Disponibilidade de cavalos","Disponibilidade de pedalinhos","Disponibilidade de boias","Disponibilidade de charrete","Outros"]', NULL, 0, 1, 1, 2),
  (@f, 'Informações complementares', 'data_inauguracao', 'Data de inauguração', 'texto', NULL, NULL, 0, 1, 1, 3),
  (@f, 'Informações complementares', 'autoria_projeto', 'Autoria do projeto', 'texto', NULL, NULL, 0, 1, 1, 4),
  (@f, 'Informações complementares', 'outras_informacoes', 'Outras informações relevantes', 'texto_longo', NULL, NULL, 0, 1, 1, 5),
  (@f, 'Monumentos', 'possui_monumentos', 'Possui monumentos', 'sim_nao', NULL, NULL, 0, 1, 1, 6),
  (@f, 'Monumentos', 'monumentos', 'Monumentos', 'tabela', '["Nome do monumento","Autor","Data de inauguração","Descrição"]', NULL, 0, 1, 1, 7);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 29);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Instalações', 'instalacoes', 'Outras instalações e equipamentos', 'multipla', '["Área de exposição coberta","Área de exposição não coberta","Bebedouro","Detector de metais","Iluminação noturna","Instalações sanitárias","Palco para eventos","Saída de emergência","Sala de imprensa","Sinalização interna","Sistema de amplificação","Som ambiente","Espaço para festas e eventos","Telões","Área vip","Centro de convenções","Fraldário","Outros"]', NULL, 0, 1, 1, 1),
  (@f, 'Serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Cafeteria","Chapelaria","Loja de souvenir","Museu","Parque infantil","Restaurante","Bar\\/lanchonete","Shopping","Achados e perdidos","Ambulatório médico","Banca de jornal","Caixa eletrônico","Sistema de monitoramento de segurança","Comercialização via web","Farmácia","Descontos especiais para grupos","Guarda-volume","Internet sem fio","Disponibilidade de carrinhos de bebê","Disponibilidade de cadeira de rodas","Disponibilidade de boias","Telefones públicos","Vendedores ambulantes","Manobrista","Vestiário","Serviço de som","Serviço de segurança","Serviço de informações","Disponibilidade de carrinho para pessoas com dificuldade de locomoção"]', '["Telefones públicos"]', 0, 1, 1, 2),
  (@f, 'Capacidade de espectadores', 'capacidade_total', 'Capacidade total de espectadores (nº)', 'numero', NULL, NULL, 1, 1, 1, 3),
  (@f, 'Capacidade de espectadores', 'capacidade_setores', 'Capacidade por setor', 'tabela', '["Setor","Capacidade (nº)"]', NULL, 0, 1, 1, 4),
  (@f, 'Capacidade de espectadores', 'setores', 'Setores de público', 'multipla', '["Arquibancada","Cadeiras especiais","Camarotes","Cadeiras cativas","Tribunas de honra","Arquibancadas cobertas","Outras categorias"]', NULL, 0, 1, 1, 5),
  (@f, 'Características específicas', 'tipo_piso', 'Tipo de piso', 'texto', NULL, NULL, 0, 1, 1, 6),
  (@f, 'Características específicas', 'comprimento', 'Comprimento da pista ou campo (m)', 'numero', NULL, NULL, 0, 1, 1, 7),
  (@f, 'Características específicas', 'area_pista', 'Área da pista (m²)', 'numero', NULL, NULL, 0, 1, 1, 8),
  (@f, 'Características específicas', 'largura', 'Largura (m)', 'numero', NULL, NULL, 0, 1, 1, 9),
  (@f, 'Características específicas', 'profundidade', 'Profundidade da piscina (m)', 'numero', NULL, NULL, 0, 1, 1, 10),
  (@f, 'Características específicas', 'area_coberta', 'Área coberta', 'sim_nao', NULL, NULL, 0, 1, 1, 11),
  (@f, 'Características específicas', 'poliesportivo', 'Poliesportivo', 'sim_nao', NULL, NULL, 0, 1, 1, 12),
  (@f, 'Características específicas', 'modalidades', 'Modalidades esportivas praticadas', 'multipla', '["Futebol","Vôlei","Basquete","Handebol","Atletismo","Ginástica olímpica","Outras"]', NULL, 0, 1, 1, 13),
  (@f, 'Características específicas', 'quantidade_pistas', 'Quantidade de campos ou pistas', 'numero', NULL, NULL, 0, 1, 1, 14),
  (@f, 'Características específicas', 'tipo_pista_patinacao', 'Tipo de pista de patinação/skate', 'escolha', '["Gelo","Cimento","Outros"]', NULL, 0, 1, 1, 15),
  (@f, 'Características específicas', 'trampolim', 'Trampolim', 'sim_nao', NULL, NULL, 0, 1, 1, 16),
  (@f, 'Características específicas', 'quantidade_trampolins', 'Quantidade de trampolins', 'numero', NULL, NULL, 0, 0, 1, 17),
  (@f, 'Características específicas', 'outro_equipamento_piscina', 'Outro equipamento da piscina', 'texto', NULL, NULL, 0, 1, 1, 18),
  (@f, 'Rampa para voo livre', 'voo_tipo', 'Tipo de voo', 'texto', NULL, NULL, 0, 1, 1, 19),
  (@f, 'Rampa para voo livre', 'voo_altitude', 'Altitude (m)', 'numero', NULL, NULL, 0, 1, 1, 20),
  (@f, 'Rampa para voo livre', 'voo_desnivel', 'Desnível (m)', 'numero', NULL, NULL, 0, 1, 1, 21),
  (@f, 'Rampa para voo livre', 'voo_quadrante', 'Quadrante', 'texto', NULL, NULL, 0, 0, 1, 22),
  (@f, 'Rampa para voo livre', 'voo_waypoint', 'Waypoint', 'texto', NULL, NULL, 0, 0, 1, 23),
  (@f, 'Rampa para voo livre', 'voo_decolagem', 'Decolagem', 'escolha', '["Rampa natural","Rampa de madeira"]', NULL, 0, 1, 1, 24),
  (@f, 'Rampa para voo livre', 'voo_pouso', 'Pouso', 'texto', NULL, NULL, 0, 1, 1, 25),
  (@f, 'Rampa para voo livre', 'voo_melhor_epoca', 'Melhor época', 'multipla', '["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"]', NULL, 0, 1, 1, 26),
  (@f, 'Características específicas', 'outras_caracteristicas', 'Outras características específicas', 'texto_longo', NULL, NULL, 0, 1, 1, 27);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 30);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Entrada', 'permissao_acesso', 'Permissão de acesso', 'escolha', '["Livre","Apenas para associados"]', NULL, 0, 1, 1, 1),
  (@f, 'Atracação', 'localizacao_nautica', 'Localização náutica', 'escolha', '["Litoral","Águas interiores (rios, represas, lagos e lagoas)"]', NULL, 0, 1, 1, 2),
  (@f, 'Atracação', 'abrangencia_operacional', 'Abrangência operacional', 'multipla', '["Nacional","Internacional"]', NULL, 0, 1, 1, 3),
  (@f, 'Atracação', 'porte_embarcacao', 'Porte da embarcação', 'multipla', '["Grande porte","Médio porte","Pequeno porte"]', NULL, 0, 1, 1, 4),
  (@f, 'Atracação', 'profundidade_media', 'Profundidade média (m)', 'numero', NULL, NULL, 0, 1, 1, 5),
  (@f, 'Atracação', 'capacidade_vagas', 'Capacidade máxima de vagas (nº)', 'numero', NULL, NULL, 1, 1, 1, 6),
  (@f, 'Atracação', 'vagas', 'Vagas por tipo', 'tabela', '["Tipo de vaga","Quantidade","Até (pés)"]', NULL, 0, 1, 1, 7),
  (@f, 'Atracação', 'tipos_vaga', 'Tipos de vaga', 'multipla', '["Vagas secas permanentes","Vagas secas rotativas","Vagas molhadas permanentes","Vagas molhadas rotativas"]', NULL, 0, 1, 1, 8),
  (@f, 'Estação de rádio', 'radio_codigo', 'Código da estação de rádio', 'texto', NULL, NULL, 0, 0, 1, 9),
  (@f, 'Estação de rádio', 'radio_frequencias', 'Frequências', 'texto', NULL, NULL, 0, 0, 1, 10),
  (@f, 'Estação de rádio', 'radio_alcance', 'Alcance', 'texto', NULL, NULL, 0, 0, 1, 11),
  (@f, 'Instalações e serviços', 'instalacoes_nauticas', 'Outras instalações e serviços náuticos', 'multipla', '["Aluguel de barcos","Boxe para guarda de materiais","Embarcação de apoio","Empilhadeira para barcos (fork-lift)","Mão de obra especializada","Hangares para guarda de embarcações (drystack)","Hangares para empilhamento e estacionamento para carretas, botes e jetski","Segurança 24h","Pátio seco com vaga permanente","Pátio seco com vaga rotativa","Píer de embarque e desembarque com escada negativa","Píer sistema fixo","Píer sistema flutuante","Quebra-mar flutuante","Rampa","Sala de rádio","Segurança especializada","Serviços de assistências técnicas autorizadas","Serviços de eletricidade","Serviços de laminação","Serviços de lingado em rampa","Serviços de limpeza e conservação de embarcações","Serviços de coleta de resíduos sólidos nas embarcações","Serviços de coleta de resíduos líquidos nas embarcações","Serviços de marcenaria","Serviços de mecânica","Serviços de pintura","Serviços de serralheria","Trailer hidráulico","Trator e guindaste para manobra de barcos","Pórtico com cintas (travel lift)","Posto de combustível náutico","Posto de combustível terrestre","Vestiário para clientes","Saída de emergência","Fornecimento de energia para vagas molhadas","Fornecimento de água para vagas molhadas","Previsão do tempo","Serviço de som","Outros"]', NULL, 0, 1, 1, 12),
  (@f, 'Instalações e serviços', 'outros_servicos', 'Outros serviços', 'multipla', '["Agência de turismo","Locadora de veículos","Passeios turísticos terrestres e náuticos","Banca de jornal e revista","Banco ou caixa eletrônico","Bar\\/lanchonete","Restaurante","Centro de eventos","Hospedagem","Cursos náuticos","Escola de vela","Área de convivência para tripulação","Heliponto","Guarda-volume","Lojas de conveniência","Loja de material náutico","Venda de barco","Lavanderia","Piscina","Sauna","Sala de jogos","Playground","Sala de TV","Posto policial","Fornecimento de gelo","Ambulatório médico","Auditório","Chuveiros","Instalações sanitárias","Serviço de informação turística","Farmácia","Outros"]', NULL, 0, 1, 1, 13);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 31);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Entrada', 'permissao_acesso', 'Permissão de acesso', 'escolha', '["Livre","Apenas para associados"]', NULL, 0, 1, 1, 1),
  (@f, 'Instalações', 'instalacoes', 'Outras instalações e equipamentos', 'multipla', '["Área de exposição coberta","Área de exposição não coberta","Loja de souvenir","Sinalização interna","Centro de convenções","Espaço para festas e eventos","Feiras","Camarotes","Pista de patinação","Pista de skate","Restaurante","Bar\\/lanchonete","Palco para eventos","Iluminação noturna","Mesas e cadeiras fixas","Mesas e cadeiras soltas","Refletores","Quadra poliesportiva","Bebedouros","Guarda-volume","Ambulatório médico","Caixa eletrônico","Banca de jornal","Fraldário","Telefone público","Saída de emergência","Sala vip","Telões","Detector de metais","Sistema de amplificação"]', '["Telefone público"]', 0, 1, 1, 2),
  (@f, 'Serviços', 'servicos', 'Serviços', 'multipla', '["Achados e perdidos","Manobrista","Sistema de monitoramento de segurança","Serviço de som","Serviço de segurança","Disponibilidade de boias","Serviço de informações","Disponibilidade de carrinho de bebê","Disponibilidade de cadeira de rodas","Disponibilidade de bicicletas","Disponibilidade de cavalos","Disponibilidade de pedalinhos","Disponibilidade de charrete","Disponibilidade de carrinho para pessoas com dificuldade de locomoção"]', NULL, 0, 1, 1, 3);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 32);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Instalações', 'instalacoes', 'Outras instalações, serviços e espaços', 'multipla', '["Hospedagem","Hospedagem de animais","Camping","Restaurante","Bar\\/lanchonete","Loja de conveniência","Loja de souvenir","Cinema","Espaço de exposição permanente","Espaço de exposição temporária","Parque de diversões","Campo de futebol","Salão de jogos","Quadra poliesportiva","Piscina adulto","Piscina infantil","Playground","Pista de caminhada","Ambulatório","Viveiro de animais","Viveiro de plantas","Parede de escalada","Torre aquática","Trenzinho","Teleférico","Toboágua","Tobogã","Pedalinho","Charrete","Pesqueiro","Mini-zoo","Sauna","Sinuca","Barco","Bicicletas","Brinquedos educativos","Horta","Pomar","Churrasqueira","Aluguel de cavalos","Venda de produtos rurais","Rio","Lago","Ambulatório médico","Fraldário","Sistema de monitoramento de segurança","Achados e perdidos","Outros"]', NULL, 0, 1, 1, 1),
  (@f, 'Atividades', 'atividades', 'Atividades', 'multipla', '["Atividade educativa","Degustação de produtos rurais","Fabricação de produtos rurais","Terapias alternativas","Recreação programada","Observação de fauna","Observação de flora","Trilha","Caminhada","Escalada","Mergulho","Cavalgada","Tirolesa","Arvorismo","Bungee-jump","Canoagem","Tiro ao alvo","Outras"]', NULL, 0, 1, 1, 2);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 33);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Serviços', 'servicos', 'Instalações e serviços de informação', 'multipla', '["Folhetos de atrativos","Folhetos de serviços","Mapa","Reserva de hotéis","Reserva de pacotes","Reserva de passeios","Reserva de transporte","Instalações sanitárias","Venda de souvenir","Atendimento telefônico","Atendimento online","Caixa eletrônico","Exposição de produtos","Outros"]', NULL, 0, 1, 1, 1);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 34);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Entidade', 'dirigente', 'Nome do dirigente', 'texto', NULL, NULL, 0, 0, 1, 1),
  (@f, 'Entidade', 'dirigente_cargo', 'Cargo do dirigente', 'texto', NULL, NULL, 0, 0, 1, 2),
  (@f, 'Entidade', 'setor_representado', 'Representação do setor de', 'texto', NULL, NULL, 0, 1, 1, 3),
  (@f, 'Entidade', 'numero_associados', 'Número de associados', 'numero', NULL, NULL, 0, 1, 1, 4),
  (@f, 'Entidade', 'abrangencia', 'Abrangência', 'escolha', '["Municipal","Regional","Estadual","Nacional"]', NULL, 0, 1, 1, 5),
  (@f, 'Entidade', 'representatividade', 'Representatividade', 'multipla', '["Patronal","Trabalhador","Setor","Outras"]', NULL, 0, 1, 1, 6),
  (@f, 'Entidade', 'filiacoes', 'Filiações (fórum, conselho, federação, associação)', 'tabela', '["Tipo (Fórum, Conselho, Federação, Associação)","Entidade","Categoria\\/tipo\\/classificação\\/número"]', NULL, 0, 0, 1, 7),
  (@f, 'Serviços e atividades', 'servicos', 'Serviços e atividades', 'multipla', '["Assessoria técnica","Assessoria jurídica","Oferta de cursos de capacitação","Realização de eventos setoriais","Realização de eventos abertos ao público","Promoção\\/divulgação do setor","Cadastro de oferta e demanda de pessoal","Intercâmbio de experiências","Central de compras","Captação e realização de congressos e eventos","Edição de boletins\\/revistas\\/jornais","Outros"]', NULL, 0, 1, 1, 8);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 35);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Profissional', 'nome_completo', 'Nome completo', 'texto', NULL, NULL, 1, 1, 1, 1),
  (@f, 'Profissional', 'cpf', 'CPF', 'texto', NULL, NULL, 0, 0, 1, 2),
  (@f, 'Profissional', 'endereco', 'Endereço', 'texto', NULL, NULL, 0, 0, 1, 3),
  (@f, 'Profissional', 'telefones', 'Telefones', 'texto', NULL, NULL, 0, 1, 1, 4),
  (@f, 'Profissional', 'email', 'Endereço eletrônico (e-mail)', 'texto', NULL, NULL, 0, 1, 1, 5),
  (@f, 'Profissional', 'escolaridade', 'Escolaridade', 'texto', NULL, NULL, 0, 0, 1, 6),
  (@f, 'Profissional', 'servicos_especializados', 'Serviços especializados', 'texto_longo', NULL, NULL, 0, 1, 1, 7),
  (@f, 'Profissional', 'cadastur', 'Nº do Cadastur', 'texto', NULL, NULL, 0, 1, 1, 8),
  (@f, 'Profissional', 'outros_cadastros', 'Outros cadastros e registros (tipo e nº)', 'texto', NULL, NULL, 0, 0, 1, 9),
  (@f, 'Profissional', 'outras_informacoes', 'Outras informações', 'texto_longo', NULL, NULL, 0, 1, 1, 10);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 36);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Instalações e serviços', 'instalacoes', 'Outras instalações e equipamentos', 'multipla', '["Ambulatório médico","Espaço para festas e eventos","Loja de souvenir","Sinalização interna","Quadra poliesportiva","Zoológico","Feiras","Museu","Anfiteatro","Iluminação","Instalações sanitárias","Caixa eletrônico","Grade ou proteção","Telefones públicos","Guarda-volumes","Bebedouros","Refletores","Churrasqueira","Outros"]', '["Telefones públicos"]', 0, 1, 1, 1),
  (@f, 'Instalações e serviços', 'servicos', 'Estruturas e serviços', 'multipla', '["Hospedagem","Restaurante","Bar\\/lanchonete","Serviço de informações","Vendedores ambulantes","Instalações sanitárias","Disponibilidade de bicicletas","Disponibilidade de cavalos","Disponibilidade de boias"]', NULL, 0, 1, 1, 2),
  (@f, 'Atividades', 'atividades', 'Atividades existentes', 'multipla', '["Arvorismo","Atividades culturais","Atividades pedagógicas","Boia-cross","Bungee-jump","Caminhada","Canoagem","Cavalgada","Ciclismo","Escalada","Ginástica","Kitesurf","Mergulho","Motocross","Mountain bike","Observação","Off road","Parapente\\/asa-delta","Pesca","Rafting","Rapel","Remo","Safári fotográfico","Skate","Vela","Voo livre","Windsurf","Trilha","Outras"]', NULL, 0, 1, 1, 3),
  (@f, 'Atividades', 'atividades_equipamentos', 'Atividades com disponibilidade de equipamentos', 'multipla', '["Arvorismo","Atividades culturais","Atividades pedagógicas","Boia-cross","Bungee-jump","Caminhada","Canoagem","Cavalgada","Ciclismo","Escalada","Ginástica","Kitesurf","Mergulho","Motocross","Mountain bike","Observação","Off road","Parapente\\/asa-delta","Pesca","Rafting","Rapel","Remo","Safári fotográfico","Skate","Vela","Voo livre","Windsurf","Trilha","Outras"]', NULL, 0, 1, 1, 4),
  (@f, 'Aspectos físicos', 'altura_maxima', 'Altura máxima (m)', 'numero', NULL, NULL, 0, 1, 1, 5),
  (@f, 'Aspectos físicos', 'extensao', 'Extensão (m ou km)', 'texto', NULL, NULL, 0, 1, 1, 6),
  (@f, 'Aspectos físicos', 'altitude', 'Altitude (m)', 'numero', NULL, NULL, 0, 1, 1, 7),
  (@f, 'Aspectos físicos', 'hidrografia', 'Hidrografia', 'multipla', '["Rio","Riacho","Córrego","Fonte","Lago\\/lagoa\\/laguna","Alagado","Outras"]', NULL, 0, 1, 1, 8),
  (@f, 'Aspectos físicos', 'quedas_dagua', 'Quedas d''água (tipos)', 'multipla', '["Catarata","Cachoeira","Salto","Cascata","Corredeira"]', NULL, 0, 1, 1, 9),
  (@f, 'Flora', 'vegetacao', 'Vegetação', 'multipla', '["Floresta amazônica","Mata atlântica","Mata de araucária","Cerrado","Caatinga","Campo","Complexo do Pantanal","Manguezal","Vegetação litorânea"]', NULL, 0, 1, 1, 10),
  (@f, 'Flora', 'flora_especies', 'Espécies da flora', 'tabela', '["Categoria (endêmica, rara, em extinção, exótica, outras)","Espécies","Melhores meses de observação"]', NULL, 0, 1, 1, 11),
  (@f, 'Fauna', 'fauna_especies', 'Espécies da fauna', 'tabela', '["Categoria (endêmica, rara, em extinção, exótica, outras)","Espécies","Melhores meses de observação"]', NULL, 0, 1, 1, 12),
  (@f, 'Atividade econômica do entorno', 'econ_agropecuaria', 'Agropecuária', 'multipla', '["Não","Agricultura","Pecuária","Aquicultura","Silvicultura","Outras"]', NULL, 0, 0, 1, 13),
  (@f, 'Atividade econômica do entorno', 'econ_industrial', 'Industrial', 'multipla', '["Não","Petrolífera","Automobilística","Têxtil","Alimentícia","Coureira","Joalheira","Madeireira","Ceramista","Outras"]', NULL, 0, 0, 1, 14),
  (@f, 'Atividade econômica do entorno', 'econ_extrativista', 'Extrativista', 'multipla', '["Não","Mineral","Vegetal","Animal"]', NULL, 0, 0, 1, 15),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 16);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 37);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Instalações e serviços', 'instalacoes', 'Outras instalações e equipamentos', 'multipla', '["Ambulatório médico","Espaço para festas e eventos","Loja de souvenir","Sinalização interna","Quadra poliesportiva","Zoológico","Feiras","Museu","Anfiteatro","Iluminação","Instalações sanitárias","Caixa eletrônico","Grade ou proteção","Telefones públicos","Guarda-volumes","Bebedouros","Refletores","Churrasqueira","Outros"]', '["Telefones públicos"]', 0, 1, 1, 1),
  (@f, 'Instalações e serviços', 'servicos', 'Estruturas e serviços', 'multipla', '["Hospedagem","Restaurante","Bar\\/lanchonete","Serviço de informações","Vendedores ambulantes","Instalações sanitárias","Disponibilidade de bicicletas","Disponibilidade de cavalos","Disponibilidade de boias"]', NULL, 0, 1, 1, 2),
  (@f, 'Atividades', 'atividades', 'Atividades existentes', 'multipla', '["Arvorismo","Atividades culturais","Atividades pedagógicas","Boia-cross","Bungee-jump","Caminhada","Canoagem","Cavalgada","Ciclismo","Escalada","Ginástica","Kitesurf","Mergulho","Motocross","Mountain bike","Observação","Off road","Parapente\\/asa-delta","Pesca","Rafting","Rapel","Remo","Safári fotográfico","Skate","Vela","Voo livre","Windsurf","Trilha","Outras"]', NULL, 0, 1, 1, 3),
  (@f, 'Atividades', 'atividades_equipamentos', 'Atividades com disponibilidade de equipamentos', 'multipla', '["Arvorismo","Atividades culturais","Atividades pedagógicas","Boia-cross","Bungee-jump","Caminhada","Canoagem","Cavalgada","Ciclismo","Escalada","Ginástica","Kitesurf","Mergulho","Motocross","Mountain bike","Observação","Off road","Parapente\\/asa-delta","Pesca","Rafting","Rapel","Remo","Safári fotográfico","Skate","Vela","Voo livre","Windsurf","Trilha","Outras"]', NULL, 0, 1, 1, 4),
  (@f, 'Aspectos físicos', 'extensao', 'Extensão (m ou km)', 'texto', NULL, NULL, 0, 1, 1, 5),
  (@f, 'Aspectos físicos', 'recife', 'Recife', 'multipla', '["Arenítico","Coralígeno"]', NULL, 0, 1, 1, 6),
  (@f, 'Orla', 'orla_tipo', 'Tipo de orla', 'multipla', '["Mar aberto","Mar abrigado","Correntes fortes","Piscinas naturais","Pequenas ondas","Ondas médias","Ondas fortes"]', NULL, 0, 1, 1, 7),
  (@f, 'Orla', 'orla_ocupacao', 'Ocupação da orla', 'multipla', '["Moradia de residentes","Moradia de veranistas","Sem ocupação"]', NULL, 0, 1, 1, 8),
  (@f, 'Orla', 'orla_forma', 'Forma de ocupação da orla', 'escolha', '["Urbanizada","Não urbanizada"]', NULL, 0, 1, 1, 9),
  (@f, 'Praia', 'praia_extensao', 'Extensão da praia (m ou km)', 'texto', NULL, NULL, 0, 1, 1, 10),
  (@f, 'Praia', 'praia_arenosa', 'Praia arenosa', 'sim_nao', NULL, NULL, 0, 1, 1, 11),
  (@f, 'Praia', 'areia', 'Areia', 'multipla', '["Fina","Grossa","Branca","Amarela","Escura","Outras"]', NULL, 0, 1, 1, 12),
  (@f, 'Praia', 'praia_rochosa', 'Praia rochosa', 'sim_nao', NULL, NULL, 0, 1, 1, 13),
  (@f, 'Água', 'agua_caracteristica', 'Característica da água', 'multipla', '["Transparente","Clara","Escura","Azulada","Esverdeada","Calma","Agitada","Fria","Morna","Pequenas ondas","Ondas médias","Ondas fortes","Piscinas naturais","Correntes fortes"]', NULL, 0, 1, 1, 14),
  (@f, 'Água', 'agua_utilizacao', 'Utilização da água', 'multipla', '["Banho","Ingestão","Navegação","Aventura","Outras"]', NULL, 0, 1, 1, 15),
  (@f, 'Água', 'agua_finalidade', 'Finalidade da água', 'multipla', '["Terapêutica","Lazer","Comercial","Outras"]', NULL, 0, 1, 1, 16),
  (@f, 'Água', 'agua_tipo', 'Tipo da água', 'multipla', '["Termal","Mineral"]', NULL, 0, 1, 1, 17),
  (@f, 'Água', 'agua_propriedade', 'Propriedade da água', 'multipla', '["Ferruginosa","Magnesiana","Sulforosa","Radioativa","Alcalina","Arsenical","Cloretada","Iodetada","Sulfatada","Sulfuretada","Outras"]', NULL, 0, 0, 1, 18),
  (@f, 'Água', 'agua_volume', 'Volume', 'multipla', '["Grande","Médio","Pequeno","Perene","Sazonal"]', NULL, 0, 1, 1, 19),
  (@f, 'Água', 'encontro_aguas', 'Encontro de águas', 'sim_nao', NULL, NULL, 0, 1, 1, 20),
  (@f, 'Água', 'encontro_rio', 'Encontro com o rio', 'texto', NULL, NULL, 0, 1, 1, 21),
  (@f, 'Água', 'foz', 'Foz', 'escolha', '["Estuário","Delta","Estuário deltaico"]', NULL, 0, 1, 1, 22),
  (@f, 'Água', 'profundidade_media', 'Profundidade média (m)', 'numero', NULL, NULL, 0, 1, 1, 23),
  (@f, 'Aspectos físicos', 'hidrografia', 'Hidrografia', 'multipla', '["Rio","Riacho","Córrego","Fonte","Lago\\/lagoa\\/laguna","Alagado","Outras"]', NULL, 0, 1, 1, 24),
  (@f, 'Aspectos físicos', 'quedas_dagua', 'Quedas d''água (tipos)', 'multipla', '["Catarata","Cachoeira","Salto","Cascata","Corredeira"]', NULL, 0, 1, 1, 25),
  (@f, 'Aspectos físicos', 'relevo', 'Relevo', 'multipla', '["Montanha","Serra","Monte\\/morro\\/colina","Pico\\/cume","Chapada\\/tabuleiro\\/matacão","Patamar","Dolina","Vale","Planalto","Planície","Depressão","Salina","Caverna","Gruta","Furna"]', NULL, 0, 1, 1, 26),
  (@f, 'Flora', 'vegetacao', 'Vegetação', 'multipla', '["Floresta amazônica","Mata atlântica","Mata de araucária","Cerrado","Caatinga","Campo","Complexo do Pantanal","Manguezal","Vegetação litorânea"]', NULL, 0, 1, 1, 27),
  (@f, 'Flora', 'flora_especies', 'Espécies da flora', 'tabela', '["Categoria (endêmica, rara, em extinção, exótica, outras)","Espécies","Melhores meses de observação"]', NULL, 0, 1, 1, 28),
  (@f, 'Fauna', 'fauna_especies', 'Espécies da fauna', 'tabela', '["Categoria (endêmica, rara, em extinção, exótica, outras)","Espécies","Melhores meses de observação"]', NULL, 0, 1, 1, 29),
  (@f, 'Atividade econômica do entorno', 'econ_agropecuaria', 'Agropecuária', 'multipla', '["Não","Agricultura","Pecuária","Aquicultura","Silvicultura","Outras"]', NULL, 0, 0, 1, 30),
  (@f, 'Atividade econômica do entorno', 'econ_industrial', 'Industrial', 'multipla', '["Não","Petrolífera","Automobilística","Têxtil","Alimentícia","Coureira","Joalheira","Madeireira","Ceramista","Outras"]', NULL, 0, 0, 1, 31),
  (@f, 'Atividade econômica do entorno', 'econ_extrativista', 'Extrativista', 'multipla', '["Não","Mineral","Vegetal","Animal"]', NULL, 0, 0, 1, 32),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 33);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 38);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Entidade mantenedora', 'esfera_administrativa', 'Esfera administrativa da mantenedora', 'escolha', '["Federal","Estadual","Municipal","Particular","Concessão","Outras"]', NULL, 0, 0, 1, 1),
  (@f, 'Entidade mantenedora', 'site_mantenedora', 'Sítio eletrônico da mantenedora', 'texto', NULL, NULL, 0, 0, 1, 2),
  (@f, 'Instalações e serviços', 'instalacoes', 'Outras instalações e equipamentos', 'multipla', '["Teleférico","Espaço para festas e eventos","Palco para eventos","Sinalização interna","Refletores","Ambulatório médico","Loja de souvenir","Museu","Saída de emergência","Vestiário","Instalações sanitárias","Grade ou proteção","Guarda-volumes","Bebedouros","Telefone público","Piso antiderrapante","Churrasqueira","Outros"]', '["Telefone público"]', 0, 1, 1, 3),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Achados e perdidos","Manobrista","Vendedores ambulantes","Serviço de som","Serviço de segurança","Circuito de monitoramento de segurança","Serviço de informações","Disponibilidade de carrinho de bebê","Disponibilidade de cadeira de rodas","Disponibilidade de bicicletas","Disponibilidade de cavalos","Disponibilidade de pedalinhos","Disponibilidade de boias","Disponibilidade de charrete","Hospedagem","Bar\\/lanchonete","Restaurante","Outros"]', NULL, 0, 1, 1, 4),
  (@f, 'Atividades', 'atividades', 'Atividades existentes', 'multipla', '["Arvorismo","Atividades culturais","Atividades pedagógicas","Boia-cross","Bungee-jump","Caminhada","Canoagem","Cavalgada","Ciclismo","Escalada","Ginástica","Kitesurf","Mergulho","Motocross","Mountain bike","Observação","Off road","Parapente\\/asa-delta","Pesca","Rafting","Rapel","Remo","Safári fotográfico","Skate","Vela","Voo livre","Windsurf","Trilha","Outras"]', NULL, 0, 1, 1, 5),
  (@f, 'Atividades', 'atividades_equipamentos', 'Atividades com disponibilidade de equipamentos', 'multipla', '["Arvorismo","Atividades culturais","Atividades pedagógicas","Boia-cross","Bungee-jump","Caminhada","Canoagem","Cavalgada","Ciclismo","Escalada","Ginástica","Kitesurf","Mergulho","Motocross","Mountain bike","Observação","Off road","Parapente\\/asa-delta","Pesca","Rafting","Rapel","Remo","Safári fotográfico","Skate","Vela","Voo livre","Windsurf","Trilha","Outras"]', NULL, 0, 1, 1, 6),
  (@f, 'Aspectos físicos', 'altura_media', 'Altura média (m)', 'numero', NULL, NULL, 0, 1, 1, 7),
  (@f, 'Aspectos físicos', 'largura_media', 'Largura média (m)', 'numero', NULL, NULL, 0, 1, 1, 8),
  (@f, 'Aspectos físicos', 'extensao', 'Extensão (m ou km)', 'texto', NULL, NULL, 0, 1, 1, 9),
  (@f, 'Aspectos físicos', 'altura_boca', 'Altura da boca da entrada (m)', 'numero', NULL, NULL, 0, 1, 1, 10),
  (@f, 'Aspectos físicos', 'facilidade_locomocao', 'Facilidade de locomoção dentro do atrativo', 'sim_nao', NULL, NULL, 0, 1, 1, 11),
  (@f, 'Aspectos físicos', 'pintura_rupestre', 'Pintura rupestre', 'sim_nao', NULL, NULL, 0, 1, 1, 12),
  (@f, 'Aspectos físicos', 'iluminacao', 'Iluminação', 'multipla', '["Natural","Artificial"]', NULL, 0, 1, 1, 13),
  (@f, 'Aspectos físicos', 'hidrografia', 'Hidrografia', 'multipla', '["Rio","Riacho","Córrego","Arroio","Lago\\/lagoa\\/laguna","Fonte","Outras"]', NULL, 0, 1, 1, 14),
  (@f, 'Aspectos físicos', 'formacao_rochosa', 'Formação rochosa', 'multipla', '["Estalactite","Estalagmite","Outras"]', NULL, 0, 1, 1, 15),
  (@f, 'Flora', 'vegetacao', 'Vegetação', 'multipla', '["Floresta amazônica","Mata atlântica","Mata de araucária","Cerrado","Caatinga","Campo","Complexo do Pantanal","Manguezal","Vegetação litorânea"]', NULL, 0, 1, 1, 16),
  (@f, 'Flora', 'flora_especies', 'Espécies da flora', 'tabela', '["Categoria (endêmica, rara, em extinção, exótica, outras)","Espécies","Melhores meses de observação"]', NULL, 0, 1, 1, 17),
  (@f, 'Fauna', 'fauna_especies', 'Espécies da fauna', 'tabela', '["Categoria (endêmica, rara, em extinção, exótica, outras)","Espécies","Melhores meses de observação"]', NULL, 0, 1, 1, 18),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 19);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 39);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Entidade mantenedora', 'esfera_administrativa', 'Esfera administrativa da mantenedora', 'escolha', '["Federal","Estadual","Municipal","Particular","Concessão","Outras"]', NULL, 0, 0, 1, 1),
  (@f, 'Entidade mantenedora', 'site_mantenedora', 'Sítio eletrônico da mantenedora', 'texto', NULL, NULL, 0, 0, 1, 2),
  (@f, 'Instalações e serviços', 'instalacoes', 'Outras instalações e equipamentos', 'multipla', '["Loja de souvenir","Bar\\/lanchonete","Restaurante","Hospedagem","Disponibilidade de boias","Disponibilidade de pedalinhos","Disponibilidade de embarcações náuticas","Instalações sanitárias","Iluminação"]', NULL, 0, 1, 1, 3),
  (@f, 'Atividades', 'atividades', 'Atividades existentes', 'multipla', '["Atividades culturais","Atividades pedagógicas","Boia-cross","Bungee-jump","Canoagem","Ginástica","Kitesurf","Mergulho","Observação","Pesca","Rafting","Remo","Vela","Windsurf","Trilha","Canionismo","Cachoeirismo","Outras"]', NULL, 0, 1, 1, 4),
  (@f, 'Atividades', 'atividades_equipamentos', 'Atividades com disponibilidade de equipamentos', 'multipla', '["Atividades culturais","Atividades pedagógicas","Boia-cross","Bungee-jump","Canoagem","Ginástica","Kitesurf","Mergulho","Observação","Pesca","Rafting","Remo","Vela","Windsurf","Trilha","Canionismo","Cachoeirismo","Outras"]', NULL, 0, 1, 1, 5),
  (@f, 'Aspectos físicos', 'extensao', 'Extensão (m ou km)', 'texto', NULL, NULL, 0, 1, 1, 6),
  (@f, 'Aspectos físicos', 'altura', 'Altura (m)', 'numero', NULL, NULL, 0, 1, 1, 7),
  (@f, 'Aspectos físicos', 'largura_media', 'Largura média (m)', 'numero', NULL, NULL, 0, 1, 1, 8),
  (@f, 'Aspectos físicos', 'profundidade_media', 'Profundidade média (m)', 'numero', NULL, NULL, 0, 1, 1, 9),
  (@f, 'Aspectos físicos', 'volume_m3', 'Volume (m³)', 'numero', NULL, NULL, 0, 0, 1, 10),
  (@f, 'Aspectos físicos', 'ilhas', 'Ilhas', 'sim_nao', NULL, NULL, 0, 1, 1, 11),
  (@f, 'Aspectos físicos', 'praias', 'Praias', 'sim_nao', NULL, NULL, 0, 1, 1, 12),
  (@f, 'Praia', 'praia_extensao', 'Extensão da praia (m ou km)', 'texto', NULL, NULL, 0, 1, 1, 13),
  (@f, 'Praia', 'praia_arenosa', 'Praia arenosa', 'sim_nao', NULL, NULL, 0, 1, 1, 14),
  (@f, 'Praia', 'areia', 'Areia', 'multipla', '["Fina","Grossa","Branca","Amarela","Escura","Outras"]', NULL, 0, 1, 1, 15),
  (@f, 'Praia', 'praia_rochosa', 'Praia rochosa', 'sim_nao', NULL, NULL, 0, 1, 1, 16),
  (@f, 'Água', 'agua_caracteristica', 'Característica da água', 'multipla', '["Transparente","Clara","Escura","Azulada","Esverdeada","Calma","Agitada","Fria","Morna","Pequenas ondas","Ondas médias","Ondas fortes","Piscinas naturais","Correntes fortes"]', NULL, 0, 1, 1, 17),
  (@f, 'Água', 'agua_utilizacao', 'Utilização da água', 'multipla', '["Banho","Ingestão","Navegação","Aventura","Outras"]', NULL, 0, 1, 1, 18),
  (@f, 'Água', 'agua_finalidade', 'Finalidade da água', 'multipla', '["Terapêutica","Lazer","Comercial","Outras"]', NULL, 0, 1, 1, 19),
  (@f, 'Água', 'agua_tipo', 'Tipo da água', 'multipla', '["Termal","Mineral"]', NULL, 0, 1, 1, 20),
  (@f, 'Água', 'agua_propriedade', 'Propriedade da água', 'multipla', '["Ferruginosa","Magnesiana","Sulforosa","Radioativa","Alcalina","Arsenical","Cloretada","Iodetada","Sulfatada","Sulfuretada","Outras"]', NULL, 0, 0, 1, 21),
  (@f, 'Água', 'agua_volume', 'Volume', 'multipla', '["Grande","Médio","Pequeno","Perene","Sazonal"]', NULL, 0, 1, 1, 22),
  (@f, 'Água', 'encontro_aguas', 'Encontro de águas', 'sim_nao', NULL, NULL, 0, 1, 1, 23),
  (@f, 'Água', 'encontro_rio', 'Encontro com o rio', 'texto', NULL, NULL, 0, 1, 1, 24),
  (@f, 'Água', 'foz', 'Foz', 'escolha', '["Estuário","Delta","Estuário deltaico"]', NULL, 0, 1, 1, 25),
  (@f, 'Aspectos físicos', 'relevo', 'Relevo', 'multipla', '["Montanha","Serra","Monte\\/morro\\/colina","Pico\\/cume","Chapada\\/tabuleiro\\/matacão","Patamar","Dolina","Vale","Planalto","Planície","Depressão","Salina","Caverna","Gruta","Furna"]', NULL, 0, 1, 1, 26),
  (@f, 'Flora', 'vegetacao', 'Vegetação', 'multipla', '["Floresta amazônica","Mata atlântica","Mata de araucária","Cerrado","Caatinga","Campo","Complexo do Pantanal","Manguezal","Vegetação litorânea"]', NULL, 0, 1, 1, 27),
  (@f, 'Flora', 'flora_especies', 'Espécies da flora', 'tabela', '["Categoria (endêmica, rara, em extinção, exótica, outras)","Espécies","Melhores meses de observação"]', NULL, 0, 1, 1, 28),
  (@f, 'Fauna', 'fauna_especies', 'Espécies da fauna', 'tabela', '["Categoria (endêmica, rara, em extinção, exótica, outras)","Espécies","Melhores meses de observação"]', NULL, 0, 1, 1, 29),
  (@f, 'Atividade econômica do entorno', 'econ_agropecuaria', 'Agropecuária', 'multipla', '["Não","Agricultura","Pecuária","Aquicultura","Silvicultura","Outras"]', NULL, 0, 0, 1, 30),
  (@f, 'Atividade econômica do entorno', 'econ_industrial', 'Industrial', 'multipla', '["Não","Petrolífera","Automobilística","Têxtil","Alimentícia","Coureira","Joalheira","Madeireira","Ceramista","Outras"]', NULL, 0, 0, 1, 31),
  (@f, 'Atividade econômica do entorno', 'econ_extrativista', 'Extrativista', 'multipla', '["Não","Mineral","Vegetal","Animal"]', NULL, 0, 0, 1, 32),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 33);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 40);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Entidade mantenedora', 'esfera_administrativa', 'Esfera administrativa da mantenedora', 'escolha', '["Federal","Estadual","Municipal","Particular","Concessão","Outras"]', NULL, 0, 0, 1, 1),
  (@f, 'Entidade mantenedora', 'site_mantenedora', 'Sítio eletrônico da mantenedora', 'texto', NULL, NULL, 0, 0, 1, 2),
  (@f, 'Gestão', 'plano_manejo', 'Plano de manejo', 'sim_nao', NULL, NULL, 0, 0, 1, 3),
  (@f, 'Instalações e serviços', 'instalacoes', 'Outras instalações e equipamentos', 'multipla', '["Área de exposições coberta","Área de exposições não coberta","Loja de souvenir","Sinalização interna","Centro de convenções","Espaço para festas e eventos","Anfiteatro","Museu","Palco para eventos","Feiras","Quadra poliesportiva","Grade ou proteção","Ambulatório médico","Iluminação","Vestiário","Guarda-volumes","Caixa eletrônico","Telefones públicos","Instalações sanitárias","Bebedouros","Teleférico","Churrasqueira","Outros"]', '["Telefones públicos"]', 0, 1, 1, 4),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Achados e perdidos","Bar\\/lanchonete","Restaurante","Hospedagem","Serviço de som","Serviço de informações","Vendedores ambulantes","Disponibilidade de carrinho de bebê","Disponibilidade de cadeira de rodas","Disponibilidade de bicicletas","Disponibilidade de cavalos","Disponibilidade de pedalinhos","Disponibilidade de boias","Outros"]', NULL, 0, 1, 1, 5),
  (@f, 'Atividades', 'atividades', 'Atividades existentes', 'multipla', '["Arvorismo","Atividades culturais","Atividades pedagógicas","Boia-cross","Bungee-jump","Caminhada","Canoagem","Cavalgada","Ciclismo","Escalada","Ginástica","Kitesurf","Mergulho","Motocross","Mountain bike","Observação","Off road","Parapente\\/asa-delta","Pesca","Rafting","Rapel","Remo","Safári fotográfico","Skate","Vela","Voo livre","Windsurf","Trilha","Outras"]', NULL, 0, 1, 1, 6),
  (@f, 'Atividades', 'atividades_equipamentos', 'Atividades com disponibilidade de equipamentos', 'multipla', '["Arvorismo","Atividades culturais","Atividades pedagógicas","Boia-cross","Bungee-jump","Caminhada","Canoagem","Cavalgada","Ciclismo","Escalada","Ginástica","Kitesurf","Mergulho","Motocross","Mountain bike","Observação","Off road","Parapente\\/asa-delta","Pesca","Rafting","Rapel","Remo","Safári fotográfico","Skate","Vela","Voo livre","Windsurf","Trilha","Outras"]', NULL, 0, 1, 1, 7),
  (@f, 'Aspectos físicos', 'extensao', 'Extensão (m ou km)', 'texto', NULL, NULL, 0, 1, 1, 8),
  (@f, 'Aspectos físicos', 'hidrografia', 'Hidrografia', 'multipla', '["Rio","Riacho","Córrego","Fonte","Lago\\/lagoa\\/laguna","Alagado","Outras"]', NULL, 0, 1, 1, 9),
  (@f, 'Aspectos físicos', 'quedas_dagua', 'Quedas d''água (tipos)', 'multipla', '["Catarata","Cachoeira","Salto","Cascata","Corredeira"]', NULL, 0, 1, 1, 10),
  (@f, 'Aspectos físicos', 'relevo', 'Relevo', 'multipla', '["Montanha","Serra","Monte\\/morro\\/colina","Pico\\/cume","Chapada\\/tabuleiro\\/matacão","Patamar","Dolina","Vale","Planalto","Planície","Depressão","Salina","Caverna","Gruta","Furna"]', NULL, 0, 1, 1, 11),
  (@f, 'Flora', 'vegetacao', 'Vegetação', 'multipla', '["Floresta amazônica","Mata atlântica","Mata de araucária","Cerrado","Caatinga","Campo","Complexo do Pantanal","Manguezal","Vegetação litorânea"]', NULL, 0, 1, 1, 12),
  (@f, 'Flora', 'flora_especies', 'Espécies da flora', 'tabela', '["Categoria (endêmica, rara, em extinção, exótica, outras)","Espécies","Melhores meses de observação"]', NULL, 0, 1, 1, 13),
  (@f, 'Fauna', 'fauna_especies', 'Espécies da fauna', 'tabela', '["Categoria (endêmica, rara, em extinção, exótica, outras)","Espécies","Melhores meses de observação"]', NULL, 0, 1, 1, 14),
  (@f, 'Atividade econômica do entorno', 'econ_agropecuaria', 'Agropecuária', 'multipla', '["Não","Agricultura","Pecuária","Aquicultura","Silvicultura","Outras"]', NULL, 0, 0, 1, 15),
  (@f, 'Atividade econômica do entorno', 'econ_industrial', 'Industrial', 'multipla', '["Não","Petrolífera","Automobilística","Têxtil","Alimentícia","Coureira","Joalheira","Madeireira","Ceramista","Outras"]', NULL, 0, 0, 1, 16),
  (@f, 'Atividade econômica do entorno', 'econ_extrativista', 'Extrativista', 'multipla', '["Não","Mineral","Vegetal","Animal"]', NULL, 0, 0, 1, 17),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 18);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 41);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'descricao_conjunto', 'Descrição do conjunto', 'multipla', '["Centro histórico","Cidade histórica","Bairro histórico","Vila","Fazenda","Chácara\\/sítio"]', NULL, 0, 1, 1, 1),
  (@f, 'Histórico', 'data_edificacao', 'Data da edificação', 'texto', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Instalações e serviços', 'instalacoes', 'Instalações e equipamentos', 'multipla', '["Centro de convenções","Shopping","Zoológico","Feiras","Anfiteatro","Lago","Chafariz","Loja de souvenir","Churrasqueira","Píer","Área de exposição","Área vip","Sala de descanso","Espaço para festas e eventos","Instalações sanitárias","Caixa eletrônico","Telefone público","Piso antiderrapante","Vestiário","Guarda-volumes","Detector de metais","Sinalização interna","Equipamentos comunitários esportivos (quadra poliesportiva, pista de skate, pista de patinação, etc.)","Equipamentos culturais de exposição (museu, centros culturais, etc.)"]', '["Telefone público"]', 0, 1, 1, 3),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Achados e perdidos","Manobrista","Vendedores ambulantes","Serviço de som","Serviço de segurança","Serviço de informações","Hospedagem","Bar\\/lanchonete","Restaurante","Disponibilidade de carrinho de bebê","Disponibilidade de cadeira de rodas","Circuito de monitoramento de segurança","Outros"]', NULL, 0, 1, 1, 4),
  (@f, 'Usos', 'atividades_atuais', 'Atividades atuais', 'multipla', '["Cultural","Esportiva","Educativa","Religiosa","Comercial","Promocional","De aventura"]', NULL, 0, 1, 1, 5),
  (@f, 'Arquitetura', 'estilo_arquitetonico', 'Estilo arquitetônico predominante', 'multipla', '["Colonial","Barroco","Neoclássico","Neogótico","Art-nouveau","Neocolonial","Moderno","Art-deco","Arquitetura da imigração","Eclético","Contemporâneo","Outros"]', NULL, 0, 1, 1, 6),
  (@f, 'Arquitetura', 'estilo_imigracao', 'Arquitetura da imigração (especificar)', 'texto', NULL, NULL, 0, 1, 1, 7),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 8);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 42);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Histórico', 'data_edificacao', 'Data da edificação', 'texto', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Instalações e serviços', 'instalacoes', 'Instalações e equipamentos', 'multipla', '["Centro de convenções","Shopping","Zoológico","Feiras","Anfiteatro","Lago","Chafariz","Loja de souvenir","Churrasqueira","Píer","Área de exposição","Área vip","Sala de descanso","Espaço para festas e eventos","Instalações sanitárias","Caixa eletrônico","Telefone público","Piso antiderrapante","Vestiário","Guarda-volumes","Detector de metais","Sinalização interna","Equipamentos comunitários esportivos (quadra poliesportiva, pista de skate, pista de patinação, etc.)","Equipamentos culturais de exposição (museu, centros culturais, etc.)"]', '["Telefone público"]', 0, 1, 1, 2),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Achados e perdidos","Manobrista","Vendedores ambulantes","Serviço de som","Serviço de segurança","Serviço de informações","Hospedagem","Bar\\/lanchonete","Restaurante","Disponibilidade de carrinho de bebê","Disponibilidade de cadeira de rodas","Circuito de monitoramento de segurança","Outros"]', NULL, 0, 1, 1, 3),
  (@f, 'Usos', 'atividades_atuais', 'Atividades atuais', 'multipla', '["Cultural","Esportiva","Educativa","Religiosa","Comercial","Promocional","De aventura"]', NULL, 0, 1, 1, 4),
  (@f, 'Arquitetura', 'estilo_arquitetonico', 'Estilo arquitetônico predominante', 'multipla', '["Colonial","Barroco","Neoclássico","Neogótico","Art-nouveau","Neocolonial","Moderno","Art-deco","Arquitetura da imigração","Eclético","Contemporâneo","Outros"]', NULL, 0, 1, 1, 5),
  (@f, 'Arquitetura', 'estilo_imigracao', 'Arquitetura da imigração (especificar)', 'texto', NULL, NULL, 0, 1, 1, 6),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 7);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 43);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Histórico', 'data_edificacao', 'Data da edificação', 'texto', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Instalações e serviços', 'instalacoes', 'Instalações e equipamentos', 'multipla', '["Centro de convenções","Shopping","Zoológico","Feiras","Anfiteatro","Lago","Chafariz","Loja de souvenir","Churrasqueira","Píer","Área de exposição","Área vip","Sala de descanso","Espaço para festas e eventos","Instalações sanitárias","Caixa eletrônico","Telefone público","Piso antiderrapante","Vestiário","Guarda-volumes","Detector de metais","Sinalização interna","Equipamentos comunitários esportivos (quadra poliesportiva, pista de skate, pista de patinação, etc.)","Equipamentos culturais de exposição (museu, centros culturais, etc.)"]', '["Telefone público"]', 0, 1, 1, 2),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Achados e perdidos","Manobrista","Vendedores ambulantes","Serviço de som","Serviço de segurança","Serviço de informações","Hospedagem","Bar\\/lanchonete","Restaurante","Disponibilidade de carrinho de bebê","Disponibilidade de cadeira de rodas","Circuito de monitoramento de segurança","Outros"]', NULL, 0, 1, 1, 3),
  (@f, 'Usos', 'atividades_atuais', 'Atividades', 'multipla', '["Cultural","Esportiva","Educativa","Religiosa","Comercial","Promocional","De aventura"]', NULL, 0, 1, 1, 4),
  (@f, 'Arquitetura', 'estilo_arquitetonico', 'Estilo arquitetônico predominante', 'multipla', '["Colonial","Barroco","Neoclássico","Neogótico","Art-nouveau","Neocolonial","Moderno","Art-deco","Arquitetura da imigração","Eclético","Contemporâneo","Outros"]', NULL, 0, 1, 1, 5),
  (@f, 'Arquitetura', 'estilo_imigracao', 'Arquitetura da imigração (especificar)', 'texto', NULL, NULL, 0, 1, 1, 6),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 7);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 44);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Histórico', 'data_edificacao', 'Data da edificação', 'texto', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Instalações e serviços', 'instalacoes', 'Instalações e equipamentos', 'multipla', '["Área de exposição coberta","Área de exposição não coberta","Área vip","Sinalização interna","Espaço para festas e eventos","Caixa eletrônico","Guarda-volumes","Loja de souvenir","Museu","Sala de descanso","Anfiteatro","Feiras","Instalações sanitárias","Ponto de táxi","Fraldário","Detector de metais","Piso antiderrapante","Telefone público","Bebedouros","Outros"]', '["Telefone público"]', 0, 1, 1, 2),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Bar\\/lanchonete","Restaurante","Manobrista","Serviço de informações","Serviço de segurança","Circuito de monitoramento de segurança","Disponibilidade de cadeira de rodas","Disponibilidade de carrinho de bebê","Outros"]', NULL, 0, 1, 1, 3),
  (@f, 'Usos', 'atividades_atuais', 'Atividades', 'multipla', '["Cultural","Esportiva","Educativa","Religiosa","Comercial","Promocional","De aventura"]', NULL, 0, 1, 1, 4),
  (@f, 'Acervo', 'acervo_artes', 'Artes visuais/cinematográficas', 'multipla', '["Desenho","Escultura","Filme","Estampa","Mural","Vitral","Pintura","Gravura","Fotografia","Arte popular","Outros"]', NULL, 0, 1, 1, 5),
  (@f, 'Acervo', 'acervo_caca_guerra', 'Caça e guerra', 'multipla', '["Arma e acessórios","Equipamento de defesa","Munição e acessórios","Apetrechos de caça"]', NULL, 0, 1, 1, 6),
  (@f, 'Acervo', 'acervo_pecuniarios', 'Objetos pecuniários', 'multipla', '["Moeda","Selo","Postal","Outros"]', NULL, 0, 1, 1, 7),
  (@f, 'Acervo', 'acervo_interiores', 'Interiores', 'multipla', '["Equipamento de serviços","Luminárias","Mobiliário","Utensílios domésticos","Outros"]', NULL, 0, 1, 1, 8),
  (@f, 'Acervo', 'acervo_trabalho', 'Trabalho', 'multipla', '["Equipamento agrícola","Equipamento de artes","Equipamento de atividades comerciais","Equipamento de pecuária","Equipamento de mineração","Instrumento musical","Equipamento de pesca","Equipamento de fiação\\/tecelagem","Outros"]', NULL, 0, 1, 1, 9),
  (@f, 'Acervo', 'acervo_lazer', 'Lazer/desporto', 'multipla', '["Brinquedos","Objetos ligados à prática esportiva","Outros"]', NULL, 0, 1, 1, 10),
  (@f, 'Acervo', 'acervo_religiao', 'Religião/cerimônia', 'multipla', '["Insígnias","Objetos de culto","Objetos funerários","Objetos cerimoniais de instituições"]', NULL, 0, 1, 1, 11),
  (@f, 'Acervo', 'acervo_comunicacao', 'Comunicação', 'multipla', '["Documento","Equipamento de documentação escrita","Equipamento de documentação sonora\\/visual","Material de propaganda","Outros"]', NULL, 0, 1, 1, 12),
  (@f, 'Acervo', 'acervo_transporte', 'Transporte', 'multipla', '["Trem","Bonde","Charrete\\/carruagem","Automóvel","Embarcação","Teleférico","Caminhão","Ônibus","Acessórios de transporte","Outros"]', NULL, 0, 1, 1, 13),
  (@f, 'Acervo', 'acervo_pessoais', 'Objetos pessoais', 'multipla', '["Indumentária","Artigos de tabagismo","Artigos de toalete","Artigos de viagem\\/campanha","Objeto de adorno","Objeto de auxílio\\/conforto","Objeto de devoção pessoal","Outros"]', NULL, 0, 1, 1, 14),
  (@f, 'Acervo', 'acervo_castigo', 'Castigo/penitência', 'multipla', '["Instrumento de autopenitência","Instrumento de castigo","Outros"]', NULL, 0, 1, 1, 15),
  (@f, 'Acervo', 'acervo_arqueologicos', 'Objetos arqueológicos', 'multipla', '["Lítico","Cerâmico","Sambaquiano","Outros"]', NULL, 0, 1, 1, 16),
  (@f, 'Arquitetura', 'estilo_arquitetonico', 'Estilo arquitetônico predominante', 'multipla', '["Colonial","Barroco","Neoclássico","Neogótico","Art-nouveau","Neocolonial","Moderno","Art-deco","Arquitetura da imigração","Eclético","Contemporâneo","Outros"]', NULL, 0, 1, 1, 17),
  (@f, 'Arquitetura', 'estilo_imigracao', 'Arquitetura da imigração (especificar)', 'texto', NULL, NULL, 0, 1, 1, 18),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 19);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 45);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Histórico', 'data_edificacao', 'Data da edificação', 'texto', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Instalações e serviços', 'instalacoes', 'Instalações e equipamentos', 'multipla', '["Área de exposição coberta","Área de exposição não coberta","Área vip","Sinalização interna","Espaço para festas e eventos","Caixa eletrônico","Guarda-volumes","Loja de souvenir","Museu","Sala de descanso","Anfiteatro","Feiras","Instalações sanitárias","Fraldário","Detector de metais","Piso antiderrapante","Telefone público","Bebedouros","Outros"]', '["Telefone público"]', 0, 1, 1, 2),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Bar\\/lanchonete","Restaurante","Manobrista","Serviço de informações","Serviço de segurança","Circuito de monitoramento de segurança","Disponibilidade de cadeira de rodas","Disponibilidade de carrinho de bebê","Outros"]', NULL, 0, 1, 1, 3),
  (@f, 'Usos', 'atividades_atuais', 'Atividades', 'multipla', '["Cultural","Esportiva","Educativa","Religiosa","Comercial","Promocional","De aventura"]', NULL, 0, 1, 1, 4),
  (@f, 'Acervo', 'acervo_artes', 'Artes visuais/cinematográficas', 'multipla', '["Desenho","Escultura","Filme","Estampa","Mural","Vitral","Pintura","Gravura","Fotografia","Arte popular","Outros"]', NULL, 0, 1, 1, 5),
  (@f, 'Acervo', 'acervo_caca_guerra', 'Caça e guerra', 'multipla', '["Arma e acessórios","Equipamento de defesa","Munição e acessórios","Apetrechos de caça"]', NULL, 0, 1, 1, 6),
  (@f, 'Acervo', 'acervo_pecuniarios', 'Objetos pecuniários', 'multipla', '["Moeda","Selo","Postal","Outros"]', NULL, 0, 1, 1, 7),
  (@f, 'Acervo', 'acervo_interiores', 'Interiores', 'multipla', '["Equipamento de serviços","Luminárias","Mobiliário","Utensílios domésticos","Outros"]', NULL, 0, 1, 1, 8),
  (@f, 'Acervo', 'acervo_trabalho', 'Trabalho', 'multipla', '["Equipamento agrícola","Equipamento de artes","Equipamento de atividades comerciais","Equipamento de pecuária","Equipamento de mineração","Instrumento musical","Equipamento de pesca","Equipamento de fiação\\/tecelagem","Outros"]', NULL, 0, 1, 1, 9),
  (@f, 'Acervo', 'acervo_lazer', 'Lazer/desporto', 'multipla', '["Brinquedos","Objetos ligados à prática esportiva","Outros"]', NULL, 0, 1, 1, 10),
  (@f, 'Acervo', 'acervo_religiao', 'Religião/cerimônia', 'multipla', '["Insígnias","Objetos de culto","Objetos funerários","Objetos cerimoniais de instituições"]', NULL, 0, 1, 1, 11),
  (@f, 'Acervo', 'acervo_comunicacao', 'Comunicação', 'multipla', '["Documento","Equipamento de documentação escrita","Equipamento de documentação sonora\\/visual","Material de propaganda","Outros"]', NULL, 0, 1, 1, 12),
  (@f, 'Acervo', 'acervo_transporte', 'Transporte', 'multipla', '["Trem","Bonde","Charrete\\/carruagem","Automóvel","Embarcação","Teleférico","Caminhão","Ônibus","Acessórios de transporte","Outros"]', NULL, 0, 1, 1, 13),
  (@f, 'Acervo', 'acervo_pessoais', 'Objetos pessoais', 'multipla', '["Indumentária","Artigos de tabagismo","Artigos de toalete","Artigos de viagem\\/campanha","Objeto de adorno","Objeto de auxílio\\/conforto","Objeto de devoção pessoal","Outros"]', NULL, 0, 1, 1, 14),
  (@f, 'Acervo', 'acervo_castigo', 'Castigo/penitência', 'multipla', '["Instrumento de autopenitência","Instrumento de castigo","Outros"]', NULL, 0, 1, 1, 15),
  (@f, 'Acervo', 'acervo_arqueologicos', 'Objetos arqueológicos', 'multipla', '["Lítico","Cerâmico","Sambaquiano","Outros"]', NULL, 0, 1, 1, 16),
  (@f, 'Arquitetura', 'estilo_arquitetonico', 'Estilo arquitetônico predominante', 'multipla', '["Colonial","Barroco","Neoclássico","Neogótico","Art-nouveau","Neocolonial","Moderno","Art-deco","Arquitetura da imigração","Eclético","Contemporâneo","Outros"]', NULL, 0, 1, 1, 17),
  (@f, 'Arquitetura', 'estilo_imigracao', 'Arquitetura da imigração (especificar)', 'texto', NULL, NULL, 0, 1, 1, 18),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 19);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 46);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Histórico', 'data_edificacao', 'Data da edificação', 'texto', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Instalações e serviços', 'instalacoes', 'Instalações e equipamentos', 'multipla', '["Área de exposição coberta","Área de exposição não coberta","Área vip","Sinalização interna","Espaço para festas e eventos","Caixa eletrônico","Guarda-volumes","Loja de souvenir","Museu","Sala de descanso","Anfiteatro","Feiras","Instalações sanitárias","Fraldário","Detector de metais","Piso antiderrapante","Telefone público","Bebedouros","Outros"]', '["Telefone público"]', 0, 1, 1, 2),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Bar\\/lanchonete","Restaurante","Manobrista","Serviço de informações","Serviço de segurança","Circuito de monitoramento de segurança","Disponibilidade de cadeira de rodas","Disponibilidade de carrinho de bebê","Outros"]', NULL, 0, 1, 1, 3),
  (@f, 'Usos', 'atividades_atuais', 'Atividades', 'multipla', '["Cultural","Esportiva","Educativa","Religiosa","Comercial","Promocional","De aventura"]', NULL, 0, 1, 1, 4),
  (@f, 'Acervo', 'acervo_artes', 'Artes visuais/cinematográficas', 'multipla', '["Desenho","Escultura","Filme","Estampa","Mural","Vitral","Pintura","Gravura","Fotografia","Arte popular","Outros"]', NULL, 0, 1, 1, 5),
  (@f, 'Acervo', 'acervo_caca_guerra', 'Caça e guerra', 'multipla', '["Arma e acessórios","Equipamento de defesa","Munição e acessórios","Apetrechos de caça"]', NULL, 0, 1, 1, 6),
  (@f, 'Acervo', 'acervo_pecuniarios', 'Objetos pecuniários', 'multipla', '["Moeda","Selo","Postal","Outros"]', NULL, 0, 1, 1, 7),
  (@f, 'Acervo', 'acervo_interiores', 'Interiores', 'multipla', '["Equipamento de serviços","Luminárias","Mobiliário","Utensílios domésticos","Outros"]', NULL, 0, 1, 1, 8),
  (@f, 'Acervo', 'acervo_trabalho', 'Trabalho', 'multipla', '["Equipamento agrícola","Equipamento de artes","Equipamento de atividades comerciais","Equipamento de pecuária","Equipamento de mineração","Instrumento musical","Equipamento de pesca","Equipamento de fiação\\/tecelagem","Outros"]', NULL, 0, 1, 1, 9),
  (@f, 'Acervo', 'acervo_lazer', 'Lazer/desporto', 'multipla', '["Brinquedos","Objetos ligados à prática esportiva","Outros"]', NULL, 0, 1, 1, 10),
  (@f, 'Acervo', 'acervo_religiao', 'Religião/cerimônia', 'multipla', '["Insígnias","Objetos de culto","Objetos funerários","Objetos cerimoniais de instituições"]', NULL, 0, 1, 1, 11),
  (@f, 'Acervo', 'acervo_comunicacao', 'Comunicação', 'multipla', '["Documento","Equipamento de documentação escrita","Equipamento de documentação sonora\\/visual","Material de propaganda","Outros"]', NULL, 0, 1, 1, 12),
  (@f, 'Acervo', 'acervo_transporte', 'Transporte', 'multipla', '["Trem","Bonde","Charrete\\/carruagem","Automóvel","Embarcação","Teleférico","Caminhão","Ônibus","Acessórios de transporte","Outros"]', NULL, 0, 1, 1, 13),
  (@f, 'Acervo', 'acervo_pessoais', 'Objetos pessoais', 'multipla', '["Indumentária","Artigos de tabagismo","Artigos de toalete","Artigos de viagem\\/campanha","Objeto de adorno","Objeto de auxílio\\/conforto","Objeto de devoção pessoal","Outros"]', NULL, 0, 1, 1, 14),
  (@f, 'Acervo', 'acervo_castigo', 'Castigo/penitência', 'multipla', '["Instrumento de autopenitência","Instrumento de castigo","Outros"]', NULL, 0, 1, 1, 15),
  (@f, 'Acervo', 'acervo_arqueologicos', 'Objetos arqueológicos', 'multipla', '["Lítico","Cerâmico","Sambaquiano","Outros"]', NULL, 0, 1, 1, 16),
  (@f, 'Arquitetura', 'estilo_arquitetonico', 'Estilo arquitetônico predominante', 'multipla', '["Colonial","Barroco","Neoclássico","Neogótico","Art-nouveau","Neocolonial","Moderno","Art-deco","Arquitetura da imigração","Eclético","Contemporâneo","Outros"]', NULL, 0, 1, 1, 17),
  (@f, 'Arquitetura', 'estilo_imigracao', 'Arquitetura da imigração (especificar)', 'texto', NULL, NULL, 0, 1, 1, 18),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 19);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 47);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Obra', 'autor_renome', 'Autor de renome', 'sim_nao', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Obra', 'autor', 'Nome do autor/fabricante', 'texto', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Obra', 'motivo_criacao', 'Motivo da criação', 'multipla', '["Homenagem","Comemoração","Adorno","Devoção","Utilitário","Outros"]', NULL, 0, 1, 1, 3),
  (@f, 'Obra', 'representacao', 'Representação', 'multipla', '["Monumento","Obelisco","Chafariz","Fonte","Pintura mural","Painel de azulejo","Vitral"]', NULL, 0, 1, 1, 4),
  (@f, 'Obra', 'material', 'Material', 'multipla', '["Pedra","Metal","Argila","Concreto","Outros"]', NULL, 0, 1, 1, 5),
  (@f, 'Obra', 'data_execucao', 'Data da confecção/execução', 'texto', NULL, NULL, 0, 1, 1, 6),
  (@f, 'Instalações e serviços', 'instalacoes', 'Instalações e equipamentos', 'multipla', '["Centro de convenções","Shopping","Zoológico","Feiras","Anfiteatro","Lago","Chafariz","Loja de souvenir","Churrasqueira","Píer","Área de exposição","Área vip","Sala de descanso","Espaço para festas e eventos","Instalações sanitárias","Caixa eletrônico","Telefone público","Piso antiderrapante","Vestiário","Guarda-volumes","Detector de metais","Sinalização interna","Equipamentos comunitários esportivos (quadra poliesportiva, pista de skate, pista de patinação, etc.)","Equipamentos culturais de exposição (museu, centros culturais, etc.)"]', '["Telefone público"]', 0, 1, 1, 7),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Achados e perdidos","Manobrista","Vendedores ambulantes","Serviço de som","Serviço de segurança","Serviço de informações","Hospedagem","Bar\\/lanchonete","Restaurante","Disponibilidade de carrinho de bebê","Disponibilidade de cadeira de rodas","Circuito de monitoramento de segurança","Outros"]', NULL, 0, 1, 1, 8),
  (@f, 'Usos', 'atividades_atuais', 'Atividades', 'multipla', '["Cultural","Esportiva","Educativa","Religiosa","Comercial","Promocional","De aventura"]', NULL, 0, 1, 1, 9),
  (@f, 'Arquitetura', 'estilo_arquitetonico', 'Estilo arquitetônico predominante', 'multipla', '["Colonial","Barroco","Neoclássico","Neogótico","Art-nouveau","Neocolonial","Moderno","Art-deco","Arquitetura da imigração","Eclético","Contemporâneo","Outros"]', NULL, 0, 1, 1, 10),
  (@f, 'Arquitetura', 'estilo_imigracao', 'Arquitetura da imigração (especificar)', 'texto', NULL, NULL, 0, 1, 1, 11),
  (@f, 'Dimensão da obra', 'altura', 'Altura (m)', 'numero', NULL, NULL, 0, 1, 1, 12),
  (@f, 'Dimensão da obra', 'largura', 'Largura (m)', 'numero', NULL, NULL, 0, 1, 1, 13),
  (@f, 'Dimensão da obra', 'profundidade', 'Profundidade (m)', 'numero', NULL, NULL, 0, 1, 1, 14),
  (@f, 'Dimensão da obra', 'escala', 'Escala do objeto', 'escolha', '["Pequeno","Médio","Grande"]', NULL, 0, 1, 1, 15),
  (@f, 'Inscrições', 'definicao_obra', 'Definição da obra', 'texto_longo', NULL, NULL, 0, 1, 1, 16),
  (@f, 'Inscrições', 'informacoes_historicas', 'Informações históricas e estilísticas', 'texto_longo', NULL, NULL, 0, 1, 1, 17),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 18);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 48);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Histórico', 'data_edificacao', 'Data da edificação', 'texto', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Instalações e serviços', 'instalacoes', 'Instalações e equipamentos', 'multipla', '["Área de exposição coberta","Área de exposição não coberta","Área vip","Sinalização interna","Espaço para festas e eventos","Caixa eletrônico","Guarda-volumes","Loja de souvenir","Museu","Sala de descanso","Anfiteatro","Feiras","Instalações sanitárias","Fraldário","Detector de metais","Piso antiderrapante","Telefone público","Bebedouros","Outros"]', '["Telefone público"]', 0, 1, 1, 2),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Bar\\/lanchonete","Restaurante","Manobrista","Serviço de informações","Serviço de segurança","Circuito de monitoramento de segurança","Disponibilidade de cadeira de rodas","Disponibilidade de carrinho de bebê","Outros"]', NULL, 0, 1, 1, 3),
  (@f, 'Usos', 'atividades_atuais', 'Atividades', 'multipla', '["Cultural","Esportiva","Educativa","Religiosa","Comercial","Promocional","De aventura"]', NULL, 0, 1, 1, 4),
  (@f, 'Acervo', 'acervo_artes', 'Artes visuais/cinematográficas', 'multipla', '["Desenho","Escultura","Filme","Estampa","Mural","Vitral","Pintura","Gravura","Fotografia","Arte popular","Outros"]', NULL, 0, 1, 1, 5),
  (@f, 'Acervo', 'acervo_caca_guerra', 'Caça e guerra', 'multipla', '["Arma e acessórios","Equipamento de defesa","Munição e acessórios","Apetrechos de caça"]', NULL, 0, 1, 1, 6),
  (@f, 'Acervo', 'acervo_pecuniarios', 'Objetos pecuniários', 'multipla', '["Moeda","Selo","Postal","Outros"]', NULL, 0, 1, 1, 7),
  (@f, 'Acervo', 'acervo_interiores', 'Interiores', 'multipla', '["Equipamento de serviços","Luminárias","Mobiliário","Utensílios domésticos","Outros"]', NULL, 0, 1, 1, 8),
  (@f, 'Acervo', 'acervo_trabalho', 'Trabalho', 'multipla', '["Equipamento agrícola","Equipamento de artes","Equipamento de atividades comerciais","Equipamento de pecuária","Equipamento de mineração","Instrumento musical","Equipamento de pesca","Equipamento de fiação\\/tecelagem","Outros"]', NULL, 0, 1, 1, 9),
  (@f, 'Acervo', 'acervo_lazer', 'Lazer/desporto', 'multipla', '["Brinquedos","Objetos ligados à prática esportiva","Outros"]', NULL, 0, 1, 1, 10),
  (@f, 'Acervo', 'acervo_religiao', 'Religião/cerimônia', 'multipla', '["Insígnias","Objetos de culto","Objetos funerários","Objetos cerimoniais de instituições"]', NULL, 0, 1, 1, 11),
  (@f, 'Acervo', 'acervo_comunicacao', 'Comunicação', 'multipla', '["Documento","Equipamento de documentação escrita","Equipamento de documentação sonora\\/visual","Material de propaganda","Outros"]', NULL, 0, 1, 1, 12),
  (@f, 'Acervo', 'acervo_transporte', 'Transporte', 'multipla', '["Trem","Bonde","Charrete\\/carruagem","Automóvel","Embarcação","Teleférico","Caminhão","Ônibus","Acessórios de transporte","Outros"]', NULL, 0, 1, 1, 13),
  (@f, 'Acervo', 'acervo_pessoais', 'Objetos pessoais', 'multipla', '["Indumentária","Artigos de tabagismo","Artigos de toalete","Artigos de viagem\\/campanha","Objeto de adorno","Objeto de auxílio\\/conforto","Objeto de devoção pessoal","Outros"]', NULL, 0, 1, 1, 14),
  (@f, 'Acervo', 'acervo_castigo', 'Castigo/penitência', 'multipla', '["Instrumento de autopenitência","Instrumento de castigo","Outros"]', NULL, 0, 1, 1, 15),
  (@f, 'Acervo', 'acervo_arqueologicos', 'Objetos arqueológicos', 'multipla', '["Lítico","Cerâmico","Sambaquiano","Outros"]', NULL, 0, 1, 1, 16),
  (@f, 'Arquitetura', 'estilo_arquitetonico', 'Estilo arquitetônico predominante', 'multipla', '["Colonial","Barroco","Neoclássico","Neogótico","Art-nouveau","Neocolonial","Moderno","Art-deco","Arquitetura da imigração","Eclético","Contemporâneo","Outros"]', NULL, 0, 1, 1, 17),
  (@f, 'Arquitetura', 'estilo_imigracao', 'Arquitetura da imigração (especificar)', 'texto', NULL, NULL, 0, 1, 1, 18),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 19);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 49);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Produto', 'nome', 'Nome', 'texto', NULL, NULL, 1, 1, 1, 1),
  (@f, 'Produto', 'historico', 'Histórico do produto', 'texto_longo', NULL, NULL, 0, 1, 1, 2),
  (@f, 'Produto', 'modo_preparo', 'Modo de preparo/fabricação', 'texto_longo', NULL, NULL, 0, 1, 1, 3),
  (@f, 'Artistas e executantes', 'executantes', 'Artistas/executantes', 'tabela', '["Nome","Endereço completo do ateliê","Ateliê aberto a visitação","Telefones","Endereço eletrônico (e-mail)","Ano de início da atividade","Premiação","Outras informações"]', NULL, 0, 1, 1, 4),
  (@f, 'Comercialização', 'locais_comercializacao', 'Locais de comercialização', 'multipla', '["Restaurante","Bar\\/lanchonete","Cafeteria","Hospedagem","Feira","Casa de chá","Quiosque\\/barraca","Outras"]', NULL, 0, 1, 1, 5),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades', 'texto_longo', NULL, NULL, 1, 1, 1, 6);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 50);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Executantes', 'executantes', 'Executantes', 'tabela', '["Nome","Endereço de apresentação","Telefones","Endereço eletrônico (e-mail)","Sítio eletrônico (site\\/página web)","Tipo de execução (individual ou grupo)","Tipo de atuação (amador ou profissional)","Ano de início da atividade","Premiação"]', NULL, 0, 1, 1, 1),
  (@f, 'Música e dança', 'genero_musical', 'Gênero musical', 'multipla', '["Arrocha","Axé","Baião","Bambuco","Blues","Boi-bumbá","Bossa-nova","Brega","Caipira","Chamamé","Choro","Clássico","Fado","Folk","Forró","Frevo","Funk","Gospel","Gótico","Hardcore","Heavy metal","Hip-hop","Jazz","Marchinha de carnaval","Metal","Milonga","Música Popular Brasileira","Pagode","Samba de Partido Alto","Pop","Pop rock","Popular","Punk rock","Rap","Rasqueado","Reggae","Religioso","Rock","Rock alternativo","Romântico","Salsa","Samba","Samba de breque","Samba de roda","Samba-enredo","Samba-canção","Sertanejo","Swing","Tango","Twist","Vanerão","Xote","Outros"]', NULL, 0, 1, 1, 2),
  (@f, 'Literária/oral', 'atuacao_literaria', 'Atuação literária/oral', 'multipla', '["Cordel","Cantoria","Narrativa","Contação de causos e histórias","Outras"]', NULL, 0, 1, 1, 3),
  (@f, 'Cênica/performática', 'cenica', 'Expressão cênica/performática', 'multipla', '["Teatro","Circo","Escola de samba","Cortejo","Espetáculo de rua","Folguedo","Outras"]', NULL, 0, 1, 1, 4),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do atrativo', 'texto_longo', NULL, NULL, 1, 1, 1, 5);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 51);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Personalidade', 'premiacao', 'Premiação', 'escolha', '["Não","Nacional","Internacional"]', NULL, 0, 1, 1, 1),
  (@f, 'Personalidade', 'reconhecimento', 'Reconhecimento', 'escolha', '["Não","Nacional","Internacional"]', NULL, 0, 1, 1, 2),
  (@f, 'Personalidade', 'descritivo', 'Descritivo do personagem', 'texto_longo', NULL, NULL, 1, 1, 1, 3);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 52);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Atividade', 'nome_atividade', 'Nome da atividade', 'texto', NULL, NULL, 1, 1, 1, 1),
  (@f, 'Atividade', 'principais_empresas', 'Principais empresas', 'tabela', '["Nome","Endereço","Telefones","Endereço eletrônico (e-mail)","Sítio eletrônico (site\\/página web)"]', NULL, 0, 1, 1, 2),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades da atividade econômica', 'texto_longo', NULL, NULL, 1, 1, 1, 3);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 53);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Informações gerais', 'ano_criacao', 'Ano de criação/instalação', 'numero', NULL, NULL, 0, 1, 1, 1),
  (@f, 'Entidade mantenedora', 'esfera_administrativa', 'Esfera administrativa da mantenedora', 'escolha', '["Federal","Estadual","Municipal","Particular","Concessão","Outras"]', NULL, 0, 0, 1, 2),
  (@f, 'Instalações e serviços', 'instalacoes', 'Outras instalações e equipamentos', 'multipla', '["Área de exposições coberta","Área de exposições não coberta","Loja de souvenir","Sinalização interna","Instalações sanitárias","Centro de convenções","Espaço para festas e eventos","Anfiteatro","Museu","Palco para eventos","Feiras","Quadra poliesportiva","Grade ou proteção","Ambulatório médico","Iluminação","Vestiário","Guarda-volumes","Caixa eletrônico","Telefone público","Área para camping","Bebedouros","Teleférico","Outros"]', '["Telefone público"]', 0, 1, 1, 3),
  (@f, 'Instalações e serviços', 'servicos', 'Outros equipamentos e serviços', 'multipla', '["Achados e perdidos","Bar\\/lanchonete","Restaurante","Hospedagem","Serviço de som","Serviço de informações","Limpeza pública","Coleta seletiva de lixo","Disponibilidade de carrinho de bebê","Disponibilidade de bicicletas","Disponibilidade de cavalos","Disponibilidade de cadeira de rodas","Disponibilidade de boias","Disponibilidade de pedalinhos","Outros"]', NULL, 0, 1, 1, 4),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades das realizações técnicas e científicas contemporâneas', 'texto_longo', NULL, NULL, 1, 1, 1, 5);

SET @f = (SELECT id FROM inventario_fichas WHERE ordem = 54);
INSERT INTO inventario_atributos (ficha_id, grupo, chave, rotulo, tipo, opcoes, opcoes_inativas, obrigatorio, portal, ativo, ordem) VALUES
  (@f, 'Realizador', 'realizador_nome', 'Nome do realizador/entidade', 'texto', NULL, NULL, 1, 1, 1, 1),
  (@f, 'Realizador', 'realizador_cnpj', 'CNPJ do realizador/entidade', 'texto', NULL, NULL, 0, 0, 1, 2),
  (@f, 'Realizador', 'realizador_email', 'E-mail do realizador', 'texto', NULL, NULL, 0, 1, 1, 3),
  (@f, 'Realizador', 'realizador_site', 'Site do realizador', 'texto', NULL, NULL, 0, 1, 1, 4),
  (@f, 'Realizador', 'realizador_telefone', 'Telefone do realizador', 'texto', NULL, NULL, 0, 1, 1, 5),
  (@f, 'Realizador', 'realizador_fax', 'Fax do realizador', 'texto', NULL, NULL, 0, 0, 0, 6),
  (@f, 'Público', 'visitantes_por_evento', 'Total de visitantes por evento (nº)', 'numero', NULL, NULL, 0, 0, 1, 7),
  (@f, 'Realização', 'periodo_realizacao', 'Período de realização', 'escolha', '["Data fixa","Data móvel"]', NULL, 1, 1, 1, 8),
  (@f, 'Realização', 'periodicidade', 'Periodicidade', 'escolha', '["Mensal","Semestral","Anual","Bienal","Outros"]', NULL, 1, 1, 1, 9),
  (@f, 'Realização', 'carater', 'Caráter do evento', 'multipla', '["Social","Comercial","Promocional","Cultural","Técnico-científico"]', NULL, 0, 1, 1, 10),
  (@f, 'Descrição', 'descritivo', 'Descritivo das especificidades do evento', 'texto_longo', NULL, NULL, 1, 1, 1, 11);

