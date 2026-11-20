-- InvenTour, etapa 1: as 54 fichas do Inventário da Oferta Turística (MTur,
-- 2011) e os tipos e subtipos de cada uma, com a definição do manual de 2006.
-- Softsul Software. Gerada por scripts/gerar_seed_fichas.php a partir de
-- docs/especificacao/fichas-2011.json em 20/11/2026. Não editar: corrigir
-- numa migração nova ou pela tela Fichas e tipos.

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (1, 'A1', 'A.1.1', 'A', 'Informações básicas do município', 'Caracterização do município', 'municipio', NULL, 1);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.1.1', 'Caracterização do município', 'Dados relativos aos aspectos geopolíticos e administrativos do município, tais como área, população, limites, altitude, clima, atividades econômicas e outras.', 1);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (2, 'A2', 'A.2.1.1', 'A', 'Meios de acesso ao município', 'Rodovia', 'linear', NULL, 13);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.2.1', 'Rodoviário', 'Os serviços rodoviários são constituídos por empresas que transportam passageiros e cargas por ônibus ou outro tipo de transporte terrestre.', 1),
  (@f, 'A.2.1.1', 'Rodovia', NULL, 2);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (3, 'A2', 'A.2.1.2, A.2.1.3', 'A', 'Meios de acesso ao município', 'Estação rodoviária e terminal rodoviário', 'pontual', NULL, 16);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.2.1', 'Rodoviário', 'Os serviços rodoviários são constituídos por empresas que transportam passageiros e cargas por ônibus ou outro tipo de transporte terrestre.', 1),
  (@f, 'A.2.1.2', 'Estação rodoviária', 'Estações de embarque e desembarque de passageiros de linhas de ônibus interurbanos, interestaduais e internacionais.', 2),
  (@f, 'A.2.1.3', 'Terminal rodoviário', 'Estações de embarque e desembarque de passageiros de linhas de ônibus interurbanos, interestaduais e internacionais.', 3);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (4, 'A2', 'A.2.2.1, A.2.2.2', 'A', 'Meios de acesso ao município', 'Ferrovia e metrovia', 'linear', NULL, 24);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.2.2', 'Ferroviário', NULL, 1),
  (@f, 'A.2.2.1', 'Ferrovia', NULL, 2),
  (@f, 'A.2.2.2', 'Metrovia', NULL, 3);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (5, 'A2', 'A.2.2.3, A.2.2.4', 'A', 'Meios de acesso ao município', 'Estação ferroviária e estação metroviária', 'pontual', NULL, 26);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.2.2', 'Ferroviário', NULL, 1),
  (@f, 'A.2.2.3', 'Estação ferroviária', 'Estações de embarque e desembarque de passageiros e cargas transportados sobre trilhos, em trens, litorinas etc.', 2),
  (@f, 'A.2.2.4', 'Estação metroviária', 'Estações de embarque e desembarque de passageiros e cargas transportados sobre trilhos, em trens, litorinas etc.', 3);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (6, 'A2', 'A.2.3.1, A.2.3.2', 'A', 'Meios de acesso ao município', 'Aeroporto e campo de pouso', 'pontual', NULL, 43);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.2.3', 'Aeroviário', 'Serviços aéreos ou aviação regular: sistema formado por empresas de transporte aéreo, com suas frotas, concessionárias de serviços de transporte regular de passageiros ou carga, em linhas e condições estabelecidas pela legislação.', 1),
  (@f, 'A.2.3.1', 'Aeroporto', 'Instalações e facilidades para o apoio de operações de aeronaves e de embarque e desembarque de passageiros e cargas.', 2),
  (@f, 'A.2.3.2', 'Campo de pouso', NULL, 3);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (7, 'A2', 'A.2.3.3, A.2.3.4', 'A', 'Meios de acesso ao município', 'Heliporto e outros', 'pontual', NULL, 35);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.2.3', 'Aeroviário', 'Serviços aéreos ou aviação regular: sistema formado por empresas de transporte aéreo, com suas frotas, concessionárias de serviços de transporte regular de passageiros ou carga, em linhas e condições estabelecidas pela legislação.', 1),
  (@f, 'A.2.3.3', 'Heliporto', NULL, 2),
  (@f, 'A.2.3.4', 'Outros', NULL, 3);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (8, 'A2', 'A.2.4.1', 'A', 'Meios de acesso ao município', 'Hidrovia', 'linear', NULL, 52);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.2.4', 'Aquaviário', NULL, 1),
  (@f, 'A.2.4.1', 'Hidrovia', NULL, 2);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (9, 'A2', 'A.2.4.2, A.2.4.3, A.2.4.4, A.2.4.5, A.2.4.6, A.2.4.7, A.2.4.8', 'A', 'Meios de acesso ao município', 'Porto organizado, píer, cais, marina, terminal portuário de uso privativo em turismo, clube náutico e outros', 'pontual', NULL, 54);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.2.4', 'Aquaviário', NULL, 1),
  (@f, 'A.2.4.2', 'Porto organizado', 'Abrigos naturais ou artificiais na costa marítima ou junto a rios e lagos para navios e outras embarcações, providos de instalações indispensáveis ao embarque e desembarque de passageiros e cargas.', 2),
  (@f, 'A.2.4.3', 'Píer', NULL, 3),
  (@f, 'A.2.4.4', 'Cais', NULL, 4),
  (@f, 'A.2.4.5', 'Marina', 'Portos de recreio e pontos de atracação construídos para abrigar embarcações de pequeno e médio porte e oferecer equipamentos de lazer e serviços mecânicos aos velejadores em trânsito.', 5),
  (@f, 'A.2.4.6', 'Terminal portuário de uso privativo em turismo', NULL, 6),
  (@f, 'A.2.4.7', 'Clube náutico', NULL, 7),
  (@f, 'A.2.4.8', 'Outros', NULL, 8);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (10, 'A3', 'A.3.1, A.3.2, A.3.3, A.3.4, A.3.5, A.3.6, A.3.7', 'A', 'Sistema de comunicação', 'Sistema de comunicação', 'pontual', NULL, 63);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.3.1', 'Agência postal', 'Repartições públicas ou privadas que recebem e expedem correspondências (cartas, cartões, telegramas, fax, sedex etc.) e encomendas.', 1),
  (@f, 'A.3.2', 'Posto telefônico', 'Repartições públicas e privadas ou dispositivos que facultam aos interessados a comunicação por telefone.', 2),
  (@f, 'A.3.3', 'Emissora de rádio', 'Centro produtor ou retransmissor de programas radiofônicos ou televisivos que possua escritório ou representantes na localidade.', 3),
  (@f, 'A.3.4', 'Emissora de TV', 'Centro produtor ou retransmissor de programas radiofônicos ou televisivos que possua escritório ou representantes na localidade.', 4),
  (@f, 'A.3.5', 'Jornal', 'Publicação periódica impressa sobre notícias, entrevistas, comentários, anúncios etc., de âmbito nacional, regional ou local, que possua escritório ou representantes na localidade.', 5),
  (@f, 'A.3.6', 'Revista', 'Publicação periódica impressa sobre notícias, entrevistas, comentários, anúncios etc., de âmbito nacional, regional ou local, que possua escritório ou representantes na localidade.', 6),
  (@f, 'A.3.7', 'Outros', NULL, 7);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (11, 'A4', 'A.4.1, A.4.2, A.4.3, A.4.4, A.4.5, A.4.6, A.4.7, A.4.8, A.4.9', 'A', 'Sistema de segurança', 'Sistema de segurança', 'pontual', NULL, 66);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.4.1', 'Polícia Civil', 'Órgãos policiais encarregados de fazer cumprir as leis ou regras impostas aos cidadãos, para assegurar a moral, a ordem e a segurança pública e reprimir o crime e a violência.', 1),
  (@f, 'A.4.2', 'Polícia Militar', 'Órgãos policiais encarregados de fazer cumprir as leis ou regras impostas aos cidadãos, para assegurar a moral, a ordem e a segurança pública e reprimir o crime e a violência.', 2),
  (@f, 'A.4.3', 'Polícia Rodoviária', 'Órgãos policiais localizados nas margens das rodovias, com a função de patrulhar e vigiar as estradas.', 3),
  (@f, 'A.4.4', 'Corpo de Bombeiros', 'Corporação militar encarregada de proteger a população de incêndios, outros acidentes e calamidades públicas.', 4),
  (@f, 'A.4.5', 'Serviços de busca e salvamento', 'Equipamentos e profissionais especializados em busca e salvamento em locais de difícil acesso.', 5),
  (@f, 'A.4.6', 'Serviços de Polícia Marítima/Aérea/de Fronteiras', 'Serviços de proteção e fiscalização efetuados habitualmente pelas capitanias dos portos, ANAC e Ministério da Aeronáutica, e destacamentos de fronteiras.', 6),
  (@f, 'A.4.7', 'Guarda Municipal', NULL, 7),
  (@f, 'A.4.8', 'Defesa Civil', NULL, 8),
  (@f, 'A.4.9', 'Outros', NULL, 9);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (12, 'A5', 'A.5.1, A.5.2, A.5.3, A.5.4, A.5.5, A.5.6, A.5.7, A.5.8, A.5.9', 'A', 'Sistemas de saúde', 'Sistemas de saúde', 'pontual', NULL, 69);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.5.1', 'Pronto-socorro', 'Hospitais de assistência pública e privada para atendimento de casos de emergência.', 1),
  (@f, 'A.5.2', 'Hospital', 'Estabelecimentos governamentais ou particulares onde se internam e tratam doentes.', 2),
  (@f, 'A.5.3', 'Clínica médica', 'Estabelecimentos de pequeno e médio porte destinados à prestação de serviços de saúde especializados.', 3),
  (@f, 'A.5.4', 'Maternidade', 'Estabelecimentos de assistência especializada para partos.', 4),
  (@f, 'A.5.5', 'Posto de saúde', 'Estabelecimento destinado ao atendimento público, com o objetivo de prestar serviços preventivos ou assistenciais da área de saúde.', 5),
  (@f, 'A.5.6', 'Farmácia/drogaria', 'Estabelecimento onde são preparados e/ou vendidos medicamentos.', 6),
  (@f, 'A.5.7', 'Clínica odontológica', 'Estabelecimento especializado em saúde bucal.', 7),
  (@f, 'A.5.8', 'Clínica veterinária', NULL, 8),
  (@f, 'A.5.9', 'Outros', NULL, 9);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (13, 'A6', 'A.6.1', 'A', 'Sistema educacional', 'Caracterização do Sistema de Educação', 'municipio', NULL, 73);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.6.1', 'Caracterização do Sistema de Educação', 'Conjunto de instituições educacionais de um município, responsáveis pelo nível de instrução da população residente e pela qualidade da mão de obra disponível para a prestação dos serviços turísticos.', 1);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (14, 'A7', 'A.7.1', 'A', 'Outros serviços e equipamentos de apoio', 'Locadoras de imóveis para temporada', 'pontual', 'servico_apoio', 80);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.7.1', 'Locadoras de imóveis para temporada', 'Empresas de aluguel de imóvel (imobiliária) para uso cedido por prazo e preço convencionados, destinado à habitação temporária em locais turísticos.', 1);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (15, 'A7', 'A.7.2', 'A', 'Outros serviços e equipamentos de apoio', 'Compras Especiais', 'pontual', 'servico_apoio', 83);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.7.2', 'Compras Especiais', NULL, 1),
  (@f, 'A.7.2.1', 'Feira/mercado', 'Locais de compra e venda de produtos tradicionais da região (alimentação, vestuário, utensílios, adornos etc.), instalados em local fechado ou ao ar livre.', 2),
  (@f, 'A.7.2.2', 'Galeria/rua comercial', NULL, 3),
  (@f, 'A.7.2.3', 'Shopping', 'Edificações onde se concentram lojas de departamentos, butiques e outros serviços para venda de produtos e artigos que atraiam o turista, com segurança, conforto, estacionamento, ponto de táxi etc.', 4),
  (@f, 'A.7.2.4', 'Plantas/flores/frutas', NULL, 5),
  (@f, 'A.7.2.5', 'Antiquário', 'Estabelecimentos que expõem, vendem e comercializam obras de arte e antiguidades.', 6),
  (@f, 'A.7.2.6', 'Cantina/cave', NULL, 7),
  (@f, 'A.7.2.7', 'Bodega/alambique', NULL, 8),
  (@f, 'A.7.2.8', 'Outras', NULL, 9);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (16, 'A7', 'A.7.3', 'A', 'Outros serviços e equipamentos de apoio', 'Comércio turístico', 'pontual', 'servico_apoio', 89);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.7.3', 'Comércio turístico', NULL, 1),
  (@f, 'A.7.3.1', 'Loja de artesanato/souvenir', 'Estabelecimentos que vendem artesanato (trabalhos resultantes de atividade manual vinculada a um ofício tradicional ou a uma arte popular) e artigos típicos para lembranças de viagens.', 2),
  (@f, 'A.7.3.2', 'Loja de artigos fotográficos', 'Estabelecimentos que vendem materiais para fotografias e/ou revelam filmes fotográficos.', 3),
  (@f, 'A.7.3.3', 'Antiquário/galeria de arte', 'Estabelecimentos que expõem, vendem e comercializam obras de arte e antiguidades.', 4),
  (@f, 'A.7.3.4', 'Outros', NULL, 5);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (17, 'A7', 'A.7.4', 'A', 'Outros serviços e equipamentos de apoio', 'Serviços bancários', 'pontual', 'servico_apoio', 95);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.7.4', 'Serviços bancários', 'Estabelecimentos particulares ou estatais cujas atividades consistem na prestação de serviços financeiros diversos e na compra e venda de moedas estrangeiras.', 1),
  (@f, 'A.7.4.1', 'Agência/posto bancário', 'Estabelecimentos particulares ou estatais cujas atividades consistem na prestação de serviços financeiros diversos.', 2),
  (@f, 'A.7.4.2', 'Casa de câmbio', 'Estabelecimentos que realizam a compra e venda de moedas estrangeiras.', 3);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (18, 'A7', 'A.7.5, A.7.6', 'A', 'Outros serviços e equipamentos de apoio', 'Serviços mecânicos e posto de combustível', 'pontual', 'servico_apoio', 98);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.7.5', 'Serviços mecânicos', 'Estabelecimentos e serviços de oficina, guincho e outros serviços de atendimento a veículos.', 1),
  (@f, 'A.7.6', 'Posto de combustível', 'Estabelecimentos equipados para a venda de combustíveis para veículos automotores (álcool, gasolina, diesel, gás natural etc.).', 2),
  (@f, 'A.7.5.1', 'Automóvel', NULL, 3),
  (@f, 'A.7.5.2', 'Motocicleta', NULL, 4),
  (@f, 'A.7.5.3', 'Ônibus/caminhão', NULL, 5),
  (@f, 'A.7.5.4', 'Embarcações náuticas', NULL, 6),
  (@f, 'A.7.5.5', 'Outros', NULL, 7);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (19, 'A7', 'A.7.7', 'A', 'Outros serviços e equipamentos de apoio', 'Representações diplomáticas', 'pontual', 'servico_apoio', 102);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'A.7.7', 'Representações diplomáticas', 'Corpo de representantes dos governos de outros países em um Estado ou organismo internacional.', 1),
  (@f, 'A.7.7.1', 'Embaixada', 'Missões de um país em outro país, com funções de representação, intermediação de negócios, proteção aos cidadãos de seus respectivos países e outros serviços.', 2),
  (@f, 'A.7.7.2', 'Consulado', 'Escritórios regionais de um país em outro país, onde são prestados serviços como expedição de vistos, intermediação de negócios e proteção aos cidadãos compatriotas.', 3),
  (@f, 'A.7.7.3', 'Outras', NULL, 4);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (20, 'B1', 'B.1.1', 'B', 'Meios de hospedagem', 'Hoteleira e de apoio', 'pontual', 'hospedagem', 105);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.1.1', 'Hoteleira e de apoio', 'Serviços remunerados prestados por estabelecimentos que oferecem alojamento e serviços necessários ao conforto do hóspede, como recepção, guarda de bagagem, conservação, arrumação e limpeza.', 1),
  (@f, 'B.1.1.1', 'Hotel', NULL, 2),
  (@f, 'B.1.1.2', 'Hotel histórico', NULL, 3),
  (@f, 'B.1.1.3', 'Hotel fazenda', NULL, 4),
  (@f, 'B.1.1.4', 'Resort', NULL, 5),
  (@f, 'B.1.1.5', 'Pousada', NULL, 6),
  (@f, 'B.1.1.6', 'Flat/apart-hotel', NULL, 7),
  (@f, 'B.1.1.7', 'Cama e café', NULL, 8),
  (@f, 'B.1.1.8', 'Albergue', NULL, 9),
  (@f, 'B.1.1.9', 'Alojamento de floresta', NULL, 10),
  (@f, 'B.1.1.10', 'Pensão', NULL, 11),
  (@f, 'B.1.1.11', 'Motel', NULL, 12),
  (@f, 'B.1.1.12', 'Colônia de férias', NULL, 13),
  (@f, 'B.1.1.13', 'Estalagem/hospedaria', NULL, 14),
  (@f, 'B.1.1.14', 'Alojamento coletivo', NULL, 15),
  (@f, 'B.1.1.15', 'Hospedagem conventual (mosteiro/seminário/convento)', NULL, 16),
  (@f, 'B.1.1.16', 'Hospedagem familiar', NULL, 17),
  (@f, 'B.1.1.17', 'Hotel de trânsito', NULL, 18),
  (@f, 'B.1.1.18', 'Spa', NULL, 19),
  (@f, 'B.1.1.19', 'Outros', NULL, 20);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (21, 'B1', 'B.1.2', 'B', 'Meios de hospedagem', 'Outro tipo de acomodação', 'pontual', 'hospedagem', 115);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.1.2', 'Outro tipo de acomodação', NULL, 1),
  (@f, 'B.1.2.1', 'Acampamento turístico/camping', NULL, 2);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (22, 'B2', 'B.2.1, B.2.2, B.2.3, B.2.4, B.2.5, B.2.6, B.2.7, B.2.8, B.2.9', 'B', 'Alimentos e bebidas', 'Alimentos e bebidas', 'pontual', 'gastronomia', 125);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.2.1', 'Restaurante', 'Estabelecimentos que se destinam à prestação de serviços de alimentação e que podem oferecer culinária internacional, regional, local ou típica.', 1),
  (@f, 'B.2.2', 'Bar', 'Estabelecimentos que servem bebidas, sanduíches, petiscos e refeições ligeiras no balcão ou em pequenas mesas.', 2),
  (@f, 'B.2.3', 'Lanchonete', 'Estabelecimentos que servem bebidas, sanduíches, petiscos e refeições ligeiras no balcão ou em pequenas mesas.', 3),
  (@f, 'B.2.4', 'Cafeteria', 'Estabelecimentos que servem bebidas, sanduíches, petiscos e refeições ligeiras no balcão ou em pequenas mesas.', 4),
  (@f, 'B.2.5', 'Quiosque', 'Estabelecimentos geralmente pequenos, nos quais são comercializados produtos alimentícios, artesanatos e conveniências.', 5),
  (@f, 'B.2.6', 'Barraca de praia', 'Estabelecimentos geralmente pequenos, nos quais são comercializados produtos alimentícios, artesanatos e conveniências.', 6),
  (@f, 'B.2.7', 'Sorveteria', 'Locais onde são vendidos sorvetes e outras iguarias.', 7),
  (@f, 'B.2.8', 'Confeitaria/padaria', 'Estabelecimentos onde são fabricados e comercializados doces e salgados.', 8),
  (@f, 'B.2.9', 'Outros', 'Outros tipos de serviços de alimentação que, por suas características ou peculiaridades, atendam à demanda turística.', 9);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (23, 'B3', 'B.3.1, B.3.2, B.3.3, B.3.4', 'B', 'Agências de turismo', 'Agências de turismo', 'pontual', 'servico_apoio', 132);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.3.1', 'Agência de viagem', 'Agências que prestam serviços de agenciamento dentro do território nacional.', 1),
  (@f, 'B.3.2', 'Operadora de turismo', 'Agências que prestam serviços no Brasil e no exterior, também chamadas de operadoras.', 2),
  (@f, 'B.3.3', 'Agência de receptivo', NULL, 3),
  (@f, 'B.3.4', 'Agência consolidadora', NULL, 4);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (24, 'B4', 'B.4.1, B.4.2, B.4.3', 'B', 'Transporte turístico', 'Transporte turístico', 'pontual', 'servico_apoio', 142);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.4.1', 'Transportadora turística e similares', 'Serviços e equipamentos para o deslocamento de pessoas por via terrestre e hidrovia, com a finalidade específica de realizar excursões, traslados e outras programações turísticas.', 1),
  (@f, 'B.4.2', 'Locadoras de veículos', 'Empresas que oferecem aluguel de automóveis, barcos, aeronaves etc., utilizados para fins turísticos.', 2),
  (@f, 'B.4.3', 'Outros tipos de transporte', 'Outros tipos de transportes que, por suas características ou peculiaridades, atendam à demanda turística.', 3);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (25, 'B5', 'B.5.1', 'B', 'Serviços e equipamentos para eventos', 'Espaços para eventos', 'pontual', 'espaco_eventos', 149);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.5.1', 'Espaços para eventos', 'Infraestrutura específica para a realização de congressos, convenções, exposições, feiras, shows e outros.', 1),
  (@f, 'B.5.1.1', 'Centro de convenções e feiras', 'Equipamentos com infraestrutura específica para a realização de congressos, convenções, exposições, feiras e outros.', 2),
  (@f, 'B.5.1.2', 'Parque/pavilhão/centro de exposições', 'Áreas permanentes, com instalações próprias, para a realização de exposições agrícolas, industriais, comerciais etc.', 3),
  (@f, 'B.5.1.3', 'Auditório/salão para reuniões', 'Salas ou conjuntos de salas acusticamente preparadas para a realização de palestras, conferências, cursos etc., normalmente integradas a meios de hospedagem.', 4),
  (@f, 'B.5.1.4', 'Outros', NULL, 5);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (26, 'B5', 'B.5.2', 'B', 'Serviços e equipamentos para eventos', 'Serviços para eventos', 'pontual', 'servico_apoio', 158);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.5.2', 'Serviços para eventos', 'Serviços específicos para a realização de congressos, convenções, exposições, feiras, shows e outros.', 1),
  (@f, 'B.5.2.1', 'Organizadora', 'Empresas que prestam serviços para a organização de congressos, convenções, seminários ou eventos congêneres.', 2),
  (@f, 'B.5.2.2', 'Promotora', 'Empresas que prestam serviços para a organização de congressos, convenções, seminários ou eventos congêneres.', 3),
  (@f, 'B.5.2.3', 'Outros serviços especializados', 'Outros tipos de serviços e equipamentos utilizados em eventos que, por suas características ou peculiaridades, atendam à demanda turística.', 4);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (27, 'B6', 'B.6.1', 'B', 'Serviços e equipamentos de lazer', 'Parques', 'pontual', 'atrativo', 162);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.6.1', 'Parques', 'Áreas delimitadas ou restritas, com serviços e equipamentos adequados para a animação e o lazer em uma localidade.', 1),
  (@f, 'B.6.1.1', 'Aquático', NULL, 2),
  (@f, 'B.6.1.2', 'Temático', 'Parques desenvolvidos em torno de um tema específico.', 3),
  (@f, 'B.6.1.3', 'Diversões', 'Áreas delimitadas ou restritas, com serviços e equipamentos adequados para a animação e o lazer em uma localidade.', 4),
  (@f, 'B.6.1.4', 'Outros', NULL, 5);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (28, 'B6', 'B.6.2', 'B', 'Serviços e equipamentos de lazer', 'Espaços livres e áreas verdes', 'pontual', 'atrativo', 170);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.6.2', 'Espaços livres e áreas verdes', 'Áreas destinadas ao lazer, com tratamento paisagístico, podendo ou não ter instalações desportivas.', 1),
  (@f, 'B.6.2.1', 'Praça', 'Áreas destinadas ao lazer, com tratamento paisagístico, podendo ou não ter instalações desportivas.', 2),
  (@f, 'B.6.2.2', 'Jardim', 'Áreas destinadas ao lazer, com tratamento paisagístico, podendo ou não ter instalações desportivas.', 3),
  (@f, 'B.6.2.3', 'Parque', 'Áreas destinadas ao lazer, com tratamento paisagístico, podendo ou não ter instalações desportivas.', 4),
  (@f, 'B.6.2.4', 'Mirante', 'Pavilhões situados em lugares altos e bastante desabrigados, de onde se podem apreciar vistas panorâmicas.', 5),
  (@f, 'B.6.2.5', 'Largo', NULL, 6),
  (@f, 'B.6.2.6', 'Outros', NULL, 7);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (29, 'B6', 'B.6.3', 'B', 'Serviços e equipamentos de lazer', 'Instalações esportivas', 'pontual', 'atrativo', 177);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.6.3', 'Instalações esportivas', NULL, 1),
  (@f, 'B.6.3.1', 'Estádio', 'Campos de jogos desportivos; locais onde se realizam competições e jogos.', 2),
  (@f, 'B.6.3.2', 'Ginásio', 'Locais cobertos para a prática de ginástica e outros esportes.', 3),
  (@f, 'B.6.3.3', 'Quadra', 'Locais para a prática de esportes, onde se realizam competições e jogos.', 4),
  (@f, 'B.6.3.4', 'Campo de golfe', 'Locais específicos para a prática de golfe.', 5),
  (@f, 'B.6.3.5', 'Campo de futebol', NULL, 6),
  (@f, 'B.6.3.6', 'Campo de paintball', NULL, 7),
  (@f, 'B.6.3.7', 'Autódromo', 'Espaços adequados para corridas de automóveis.', 8),
  (@f, 'B.6.3.8', 'Kartódromo', 'Espaços adequados para corridas de karts.', 9),
  (@f, 'B.6.3.9', 'Velódromo', NULL, 10),
  (@f, 'B.6.3.10', 'Hipódromo', 'Espaços adequados para corridas de cavalos.', 11),
  (@f, 'B.6.3.11', 'Pista de equitação', NULL, 12),
  (@f, 'B.6.3.12', 'Pista de boliche', 'Locais específicos para a prática de jogos de boliche.', 13),
  (@f, 'B.6.3.13', 'Pista de patinação', 'Locais, pavimentados ou não, com graus de dificuldade adequados para a prática de patinação.', 14),
  (@f, 'B.6.3.14', 'Pista de skate', NULL, 15),
  (@f, 'B.6.3.15', 'Rampa para voo livre', NULL, 16),
  (@f, 'B.6.3.16', 'Piscina', NULL, 17),
  (@f, 'B.6.3.17', 'Outras', NULL, 18);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (30, 'B6', 'B.6.4', 'B', 'Serviços e equipamentos de lazer', 'Instalações náuticas', 'pontual', 'atrativo', 186);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.6.4', 'Instalações náuticas', NULL, 1),
  (@f, 'B.6.4.1', 'Marina', 'Portos de recreio e pontos de atracação construídos para abrigar embarcações de pequeno e médio porte e oferecer equipamentos de lazer e serviços mecânicos aos velejadores em trânsito.', 2),
  (@f, 'B.6.4.2', 'Píer', NULL, 3),
  (@f, 'B.6.4.3', 'Garagem náutica', NULL, 4),
  (@f, 'B.6.4.4', 'Clube náutico', NULL, 5),
  (@f, 'B.6.4.5', 'Cais', NULL, 6),
  (@f, 'B.6.4.6', 'Outras', NULL, 7);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (31, 'B6', 'B.6.5', 'B', 'Serviços e equipamentos de lazer', 'Espaços de diversão e cultura', 'pontual', 'atrativo', 194);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.6.5', 'Espaços de diversão e cultura', NULL, 1),
  (@f, 'B.6.5.1', 'Boate/discoteca', 'Estabelecimentos de diversão que têm pista de dança e apresentam música ao vivo ou mecânica e, às vezes, shows artísticos.', 2),
  (@f, 'B.6.5.2', 'Casa de espetáculos/shows', 'Estabelecimentos de diversão noturna para shows artísticos.', 3),
  (@f, 'B.6.5.3', 'Casa de dança', 'Estabelecimentos de diversão noturna que têm pista de dança.', 4),
  (@f, 'B.6.5.4', 'Cinema', 'Salas de espetáculos onde se projetam filmes.', 5),
  (@f, 'B.6.5.5', 'Clube social', 'Associação, grêmio esportivo, cultural ou recreativo com instalações para prática esportiva, recreação e reuniões, franqueadas a seus associados; alguns podem ser utilizados também pelo turista.', 6),
  (@f, 'B.6.5.6', 'Centro de tradições', NULL, 7),
  (@f, 'B.6.5.7', 'Outros', NULL, 8);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (32, 'B6', 'B.6.6', 'B', 'Serviços e equipamentos de lazer', 'Outros espaços de recreação', 'pontual', 'atrativo', 201);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.6.6', 'Outros espaços de recreação', 'Outras instalações onde seja possível a prática de atividades desportivas ou recreacionais, desde que abertas ao público em geral.', 1),
  (@f, 'B.6.6.1', 'Pesque e pague', NULL, 2),
  (@f, 'B.6.6.2', 'Pesque e solte', NULL, 3),
  (@f, 'B.6.6.3', 'Colha e pague', NULL, 4),
  (@f, 'B.6.6.4', 'Sítios/chácaras de lazer', NULL, 5),
  (@f, 'B.6.6.5', 'Outros', NULL, 6);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (33, 'B7', 'B.7.1', 'B', 'Outros serviços e equipamentos turísticos', 'Informações turísticas', 'pontual', 'servico_apoio', 208);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.7.1', 'Informações turísticas', 'Dados e especificações compilados e sistematizados com o objetivo de orientar e informar os turistas sobre atrativos, serviços e equipamentos turísticos de determinado local.', 1),
  (@f, 'B.7.1.1', 'Posto', NULL, 2),
  (@f, 'B.7.1.2', 'Centro de atendimento', 'Local mantido por órgão oficial de turismo, associações ou particulares, com o objetivo de prestar informações ao turista, proporcionando-lhe melhor estada.', 3),
  (@f, 'B.7.1.3', 'Central', NULL, 4),
  (@f, 'B.7.1.4', 'Outros', NULL, 5);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (34, 'B7', 'B.7.2', 'B', 'Outros serviços e equipamentos turísticos', 'Entidades associativas e similares', 'pontual', 'servico_apoio', 214);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.7.2', 'Entidades associativas e similares', 'Entidades públicas ou privadas que prestam serviços de interesse para a atividade turística local, como Convention Bureau e associações de classe.', 1);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (35, 'B7', 'B.7.3', 'B', 'Outros serviços e equipamentos turísticos', 'Guiamento e condução turística', 'pontual', 'servico_apoio', 220);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'B.7.3', 'Guiamento e condução turística', NULL, 1),
  (@f, 'B.7.3.1', 'Guia de turismo', NULL, 2),
  (@f, 'B.7.3.2', 'Monitor', NULL, 3),
  (@f, 'B.7.3.3', 'Condutor', NULL, 4),
  (@f, 'B.7.3.4', 'Outros', NULL, 5);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (36, 'C1', 'C.1.1', 'C', 'Atrativos naturais', 'Relevo continental', 'pontual', 'atrativo', 223);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.1.1', 'Relevo continental', NULL, 1),
  (@f, 'C.1.1.1', 'Montanha', 'Grandes elevações naturais do terreno, constituídas por agrupamento de morros, serras, escarpas etc.', 2),
  (@f, 'C.1.1.2', 'Serra', 'Conjunto de elevações com escarpas íngremes, apresentando algumas vezes elevações consideráveis.', 3),
  (@f, 'C.1.1.3', 'Monte/morro/colina', 'Conjunto de elevações menores que sofreram erosão, podendo se apresentar como morros isolados ou formando um conjunto de morros; montes e colinas são similares.', 4),
  (@f, 'C.1.1.4', 'Pico/cume', 'Pontos proeminentes do relevo montanhoso, assim como as formas arredondadas de cabeços e pontões, íngremes e sem vegetação, conhecidos como pães de açúcar.', 5),
  (@f, 'C.1.1.5', 'Chapada', 'Formas de relevo tabular constituídas por uma cobertura sedimentar antiga; são divisores de águas, onde nascem rios de diversas bacias.', 6),
  (@f, 'C.1.1.6', 'Tabuleiro', 'Faixas descontínuas de terras baixas ao longo do litoral; podem ocorrer também em áreas continentais.', 7),
  (@f, 'C.1.1.7', 'Patamar', 'Degraus mais ou menos altos, localizados nas encostas dos planaltos, que permitem a apreciação de vistas panorâmicas.', 8),
  (@f, 'C.1.1.8', 'Matacão', 'Blocos de granito não decompostos, de forma arredondada, produzidos pela esfoliação tipo casca de cebola, que se destacam na paisagem.', 9),
  (@f, 'C.1.1.9', 'Vale', 'Depressões que ocorrem entre duas elevações, correspondendo também a planícies à beira de um rio.', 10),
  (@f, 'C.1.1.10', 'Planalto', 'Superfícies elevadas, delimitadas por escarpas, sem grandes desníveis de saliências e reentrâncias, a qualquer altitude acima de 300 m.', 11),
  (@f, 'C.1.1.11', 'Planície', 'Área extensa e plana na qual os processos de sedimentação superam os de erosão; podem ser costeiras ou continentais.', 12),
  (@f, 'C.1.1.12', 'Depressão', NULL, 13),
  (@f, 'C.1.1.13', 'Outros', NULL, 14);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (37, 'C1', 'C.1.2', 'C', 'Atrativos naturais', 'Zona costeira', 'pontual', 'atrativo', 235);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.1.2', 'Zona costeira', 'Zona de contato das águas (oceanos e mares) com a terra (continente e ilhas).', 1),
  (@f, 'C.1.2.1', 'Restinga', 'Estreitas faixas arenosas que as correntes marinhas constroem por acumulação (cordões litorâneos), na entrada de baías ou estuários de rios, formando lagoas costeiras.', 2),
  (@f, 'C.1.2.2', 'Duna', 'Elevações arenosas cuja evolução e forma dependem do vento, da quantidade de areia e de agentes de fixação; podem ser costeiras ou continentais.', 3),
  (@f, 'C.1.2.3', 'Barreira', 'Formas de relevo litorâneo abruptas ou escarpadas, como paredões, nas quais a destruição marinha é ativa, com valor paisagístico e ecológico.', 4),
  (@f, 'C.1.2.4', 'Praia', 'Costas baixas resultantes de acumulação de areias, finas ou grosseiras, que sofrem o processo de regressão do mar e apresentam características diversas.', 5),
  (@f, 'C.1.2.5', 'Mangue', 'Lodaçais com vegetação halófila (resistente ao sal), encontrados em planícies costeiras e sujeitos a inundações da maré; são áreas de proteção ambiental.', 6),
  (@f, 'C.1.2.6', 'Estuário', NULL, 7),
  (@f, 'C.1.2.7', 'Falésia', 'Formas de relevo litorâneo abruptas ou escarpadas, como paredões, nas quais a destruição marinha é ativa, com valor paisagístico e ecológico.', 8),
  (@f, 'C.1.2.8', 'Baía/enseada/saco', 'Reentrâncias da costa pelas quais o mar penetra o interior das terras; enseadas são curvaturas que formam uma baía aberta e sacos são pequenas reentrâncias de boca estreita.', 9),
  (@f, 'C.1.2.9', 'Península/cabo/ponta', 'Porções de terra que avançam sobre a água: penínsulas são cercadas de água por todos os lados menos um, cabos são menores que penínsulas e pontas são as menores.', 10),
  (@f, 'C.1.2.10', 'Recife/atol', 'Formações litorâneas próximas da costa, de origem mineral (arenito) ou biológica (corais); o atol tem formato circular, com uma laguna pouco profunda ao centro.', 11),
  (@f, 'C.1.2.11', 'Ilha', 'Porções de terras emersas, de tamanhos variados e de origens diferentes, circundadas por água doce ou salgada.', 12),
  (@f, 'C.1.2.12', 'Arquipélago', 'Agrupamento de ilhas concentradas em áreas de rios e oceanos ou formadas na foz deltaica dos rios por origem sedimentar.', 13),
  (@f, 'C.1.2.13', 'Barra', NULL, 14),
  (@f, 'C.1.2.14', 'Outros', NULL, 15);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (38, 'C1', 'C.1.3', 'C', 'Atrativos naturais', 'Relevo cárstico', 'pontual', 'atrativo', 247);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.1.3', 'Relevo cárstico', NULL, 1),
  (@f, 'C.1.3.1', 'Caverna', 'Cavidades de formas variadas que podem apresentar salões, galerias, degraus e sumidouros.', 2),
  (@f, 'C.1.3.2', 'Gruta', 'Cavidades de formas variadas que podem apresentar salões, galerias, degraus e sumidouros.', 3),
  (@f, 'C.1.3.3', 'Furna', 'Cavidades que aparecem em encostas de barrancos por desmoronamento ou dissolução das rochas.', 4),
  (@f, 'C.1.3.4', 'Dolina', NULL, 5);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (39, 'C1', 'C.1.4, C.1.5', 'C', 'Atrativos naturais', 'Hidrografia e queda d''água', 'pontual', 'atrativo', 258);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.1.4', 'Hidrografia', 'Conjunto das águas correntes ou estáveis de uma região.', 1),
  (@f, 'C.1.5', 'Queda d''água', 'Denominação genérica dos desnivelamentos do perfil de um rio, que dão origem a saltos, cachoeiras, cascatas, corredeiras e cataratas, conforme a altura da queda.', 2),
  (@f, 'C.1.4.1', 'Rio', 'Cursos de água doce existentes na superfície da terra, que se deslocam de um nível mais elevado para outro mais baixo.', 3),
  (@f, 'C.1.4.2', 'Riacho', NULL, 4),
  (@f, 'C.1.4.3', 'Córrego', NULL, 5),
  (@f, 'C.1.4.4', 'Arroio', 'Pequenos cursos d''água.', 6),
  (@f, 'C.1.4.5', 'Lago/lagoa/laguna', 'Grandes extensões de água concentradas em depressões do solo e cercadas de terra, incluindo açudes e reservatórios resultantes de represamento de rios.', 7),
  (@f, 'C.1.4.6', 'Alagado', 'Regiões inundadas formadas em terreno plano, constituindo baixadas inundáveis ou dessecadas.', 8),
  (@f, 'C.1.4.7', 'Fonte', 'Águas ricas em sais e minerais, caracterizadas por propriedades terapêuticas ou de revigoramento; são termais as que têm temperatura mais elevada que o ambiente.', 9),
  (@f, 'C.1.4.8', 'Outros', NULL, 10),
  (@f, 'C.1.5.1', 'Catarata', 'Queda d''água de grande extensão e grande volume de água.', 11),
  (@f, 'C.1.5.2', 'Cachoeira', 'Queda d''água vertical ou muito íngreme no curso de um rio.', 12),
  (@f, 'C.1.5.3', 'Salto', 'Queda d''água menor que forma saltos nas saliências das rochas, assemelhando-se a degraus.', 13),
  (@f, 'C.1.5.4', 'Cascata', 'Queda d''água de pouca altura e volume reduzido.', 14),
  (@f, 'C.1.5.5', 'Corredeira', 'Trechos acidentados dos rios onde as águas têm maior velocidade, aproveitados pelo turismo para a prática do rafting.', 15),
  (@f, 'C.1.5.6', 'Outras', NULL, 16);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (40, 'C1', 'C.1.6', 'C', 'Atrativos naturais', 'Unidades de conservação e similares', 'pontual', 'atrativo', 270);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.1.6', 'Unidades de conservação e similares', 'Áreas delimitadas e protegidas por lei, dotadas de atributos naturais excepcionais, para resguardar amostra representativa dos ecossistemas do país (SNUC).', 1),
  (@f, 'C.1.6.1', 'Área de proteção ambiental', NULL, 2),
  (@f, 'C.1.6.2', 'Área de relevante interesse ecológico', NULL, 3),
  (@f, 'C.1.6.3', 'Estação ecológica', NULL, 4),
  (@f, 'C.1.6.4', 'Floresta', NULL, 5),
  (@f, 'C.1.6.5', 'Monumento natural', NULL, 6),
  (@f, 'C.1.6.6', 'Parque', NULL, 7),
  (@f, 'C.1.6.7', 'Refúgio de vida silvestre', NULL, 8),
  (@f, 'C.1.6.8', 'Reserva biológica', NULL, 9),
  (@f, 'C.1.6.9', 'Reserva extrativista', NULL, 10),
  (@f, 'C.1.6.10', 'Reserva de desenvolvimento sustentável', NULL, 11),
  (@f, 'C.1.6.11', 'Reserva de fauna', NULL, 12),
  (@f, 'C.1.6.12', 'Reserva particular do patrimônio natural', NULL, 13),
  (@f, 'C.1.6.13', 'Zoológico', NULL, 14),
  (@f, 'C.1.6.14', 'Jardim botânico', NULL, 15),
  (@f, 'C.1.6.15', 'Outras', NULL, 16);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (41, 'C2', 'C.2.1', 'C', 'Atrativos culturais', 'Conjuntos arquitetônicos', 'pontual', 'atrativo', 281);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.1', 'Conjuntos arquitetônicos', NULL, 1),
  (@f, 'C.2.1.1', 'Urbano', NULL, 2),
  (@f, 'C.2.1.2', 'Rural', NULL, 3),
  (@f, 'C.2.1.3', 'Industrial', NULL, 4),
  (@f, 'C.2.1.4', 'Ferroviário', NULL, 5),
  (@f, 'C.2.1.5', 'Outros', NULL, 6);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (42, 'C2', 'C.2.2, C.2.3, C.2.4, C.2.5, C.2.6', 'C', 'Atrativos culturais', 'Comunidades tradicionais, sítios arqueológicos, sítios paleontológicos, itinerários culturais e parques históricos', 'pontual', 'atrativo', 290);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.2', 'Comunidades tradicionais', NULL, 1),
  (@f, 'C.2.3', 'Sítios arqueológicos', NULL, 2),
  (@f, 'C.2.4', 'Sítios paleontológicos', NULL, 3),
  (@f, 'C.2.5', 'Itinerários culturais', NULL, 4),
  (@f, 'C.2.6', 'Parques históricos', NULL, 5),
  (@f, 'C.2.2.1', 'Quilombola', NULL, 6),
  (@f, 'C.2.2.2', 'Indígena', NULL, 7),
  (@f, 'C.2.2.3', 'Ribeirinha', NULL, 8),
  (@f, 'C.2.2.4', 'De imigração', NULL, 9),
  (@f, 'C.2.2.5', 'Extrativista', NULL, 10),
  (@f, 'C.2.2.6', 'Outras', NULL, 11),
  (@f, 'C.2.3.1', 'Lítico', NULL, 12),
  (@f, 'C.2.3.2', 'Cerâmico', NULL, 13),
  (@f, 'C.2.3.3', 'Lítico-cerâmico', NULL, 14),
  (@f, 'C.2.3.4', 'Estrutura de pedra', NULL, 15),
  (@f, 'C.2.3.5', 'Estrutura de terra', NULL, 16),
  (@f, 'C.2.3.6', 'Arte rupestre', NULL, 17),
  (@f, 'C.2.3.7', 'Sambaqui', NULL, 18),
  (@f, 'C.2.3.8', 'Outros', NULL, 19),
  (@f, 'C.2.4.1', 'Floresta fóssil', NULL, 20),
  (@f, 'C.2.4.2', 'Restos fósseis ou em processo de fossilização', NULL, 21),
  (@f, 'C.2.4.3', 'Moldes, rastros, pegadas', NULL, 22),
  (@f, 'C.2.5.1', 'Histórico', NULL, 24),
  (@f, 'C.2.5.2', 'Religioso/espiritual', NULL, 25),
  (@f, 'C.2.5.3', 'Relacionado a lendas/mitos/narrativas associadas', NULL, 26),
  (@f, 'C.2.5.4', 'Relacionado a fatos históricos', NULL, 27),
  (@f, 'C.2.6.1', 'Arqueológico', NULL, 29),
  (@f, 'C.2.6.2', 'Geoparque', NULL, 30);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (43, 'C2', 'C.2.7, C.2.8, C.2.9', 'C', 'Atrativos culturais', 'Lugares de manifestações de fé, lugares de referências à memória e feiras/mercados de caráter cultural', 'pontual', 'atrativo', 299);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.7', 'Lugares de manifestações de fé', 'Locais onde se invocam ou cultuam divindades, em qualquer religião.', 1),
  (@f, 'C.2.8', 'Lugares de referências à memória', NULL, 2),
  (@f, 'C.2.9', 'Feiras/mercados de caráter cultural', 'Locais de compra e venda de produtos tradicionais da região (alimentação, vestuário, utensílios, adornos etc.), instalados em local fechado ou ao ar livre.', 3),
  (@f, 'C.2.7.1', 'Romaria e procissão', NULL, 4),
  (@f, 'C.2.7.2', 'Culto', NULL, 5),
  (@f, 'C.2.7.3', 'Encontro para manifestação de fé', NULL, 6),
  (@f, 'C.2.7.4', 'Referencial para mitos e narrativas de fé', NULL, 7),
  (@f, 'C.2.7.5', 'Visitação de cunho religioso', NULL, 8),
  (@f, 'C.2.7.6', 'Outros', NULL, 9),
  (@f, 'C.2.8.1', 'Acontecimento histórico', NULL, 10),
  (@f, 'C.2.8.2', 'Referencial para narrativa mítica', NULL, 11),
  (@f, 'C.2.8.3', 'Ritual e celebração', NULL, 12);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (44, 'C2', 'C.2.10', 'C', 'Atrativos culturais', 'Arquitetura civil', 'pontual', 'atrativo', 308);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.10', 'Arquitetura civil', 'Edificações públicas ou privadas, urbanas ou rurais, com funções de residência, ensino e pesquisa, serviço e comércio.', 1),
  (@f, 'C.2.10.1', 'Casa/casarão/sobrado/solar', NULL, 2),
  (@f, 'C.2.10.2', 'Casa de comércio', NULL, 3),
  (@f, 'C.2.10.3', 'Educandário/colégio/escola', NULL, 4),
  (@f, 'C.2.10.4', 'Liceu', NULL, 5),
  (@f, 'C.2.10.5', 'Universidade', NULL, 6),
  (@f, 'C.2.10.6', 'Coreto', NULL, 7),
  (@f, 'C.2.10.7', 'Palácio/palacete', NULL, 8),
  (@f, 'C.2.10.8', 'Chalé', NULL, 9),
  (@f, 'C.2.10.9', 'Chafariz/fonte/bica', NULL, 10),
  (@f, 'C.2.10.10', 'Hospital', NULL, 11),
  (@f, 'C.2.10.11', 'Orfanato/creche', NULL, 12),
  (@f, 'C.2.10.12', 'Asilo', NULL, 13),
  (@f, 'C.2.10.13', 'Quinta', NULL, 14),
  (@f, 'C.2.10.14', 'Outras', NULL, 15);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (45, 'C2', 'C.2.11, C.2.12, C.2.13', 'C', 'Atrativos culturais', 'Arquitetura oficial, arquitetura militar e arquitetura religiosa', 'pontual', 'atrativo', 318);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.11', 'Arquitetura oficial', NULL, 1),
  (@f, 'C.2.12', 'Arquitetura militar', 'Edificações construídas para suprir as funções de defesa e proteção do território nacional.', 2),
  (@f, 'C.2.13', 'Arquitetura religiosa', 'Edificações construídas para atender às funções de culto e a atividades assistenciais de proteção, amparo, serviço médico etc.', 3),
  (@f, 'C.2.11.1', 'Casa de câmara e cadeia', NULL, 4),
  (@f, 'C.2.11.2', 'Paço municipal', NULL, 5),
  (@f, 'C.2.11.3', 'Cadeia', NULL, 6),
  (@f, 'C.2.11.4', 'Casa de intendência', NULL, 7),
  (@f, 'C.2.11.5', 'Casa de fundição', NULL, 8),
  (@f, 'C.2.11.6', 'Casa de alfândega', NULL, 9),
  (@f, 'C.2.11.7', 'Fórum/tribunal', NULL, 10),
  (@f, 'C.2.11.8', 'Residência oficial', NULL, 11),
  (@f, 'C.2.11.9', 'Sede do Poder Executivo/Legislativo/Judiciário', NULL, 12),
  (@f, 'C.2.11.10', 'Outras', NULL, 13),
  (@f, 'C.2.12.1', 'Bateria', NULL, 14),
  (@f, 'C.2.12.2', 'Baluarte', NULL, 15),
  (@f, 'C.2.12.3', 'Bastião', NULL, 16),
  (@f, 'C.2.12.4', 'Fortim', NULL, 17),
  (@f, 'C.2.12.5', 'Forte', NULL, 18),
  (@f, 'C.2.12.6', 'Fortaleza', NULL, 19),
  (@f, 'C.2.12.7', 'Quartel', NULL, 20),
  (@f, 'C.2.12.8', 'Colégio', NULL, 21),
  (@f, 'C.2.12.9', 'Vila militar', NULL, 22),
  (@f, 'C.2.13.1', 'Igreja', NULL, 24),
  (@f, 'C.2.13.2', 'Basílica', NULL, 25),
  (@f, 'C.2.13.3', 'Catedral', NULL, 26),
  (@f, 'C.2.13.4', 'Sé', NULL, 27),
  (@f, 'C.2.13.5', 'Santuário', NULL, 28),
  (@f, 'C.2.13.6', 'Capela', NULL, 29),
  (@f, 'C.2.13.7', 'Ermida', NULL, 30),
  (@f, 'C.2.13.8', 'Abadia', NULL, 31),
  (@f, 'C.2.13.9', 'Oratório', NULL, 32),
  (@f, 'C.2.13.10', 'Casa paroquial', NULL, 33),
  (@f, 'C.2.13.11', 'Casa capitular', NULL, 34),
  (@f, 'C.2.13.12', 'Casa da providência', NULL, 35),
  (@f, 'C.2.13.13', 'Palácio arquiepiscopal', NULL, 36),
  (@f, 'C.2.13.14', 'Mosteiro', NULL, 37),
  (@f, 'C.2.13.15', 'Seminário', NULL, 38),
  (@f, 'C.2.13.16', 'Convento', NULL, 39);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (46, 'C2', 'C.2.14, C.2.15, C.2.16, C.2.17', 'C', 'Atrativos culturais', 'Arquitetura industrial/agrícola, arquitetura funerária, marcos históricos e obras de infraestrutura', 'pontual', 'atrativo', 328);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.14', 'Arquitetura industrial/agrícola', 'Edificações especialmente construídas para abrigar atividades industriais ou agrícolas.', 1),
  (@f, 'C.2.15', 'Arquitetura funerária', 'Monumentos erigidos para prestar homenagens fúnebres.', 2),
  (@f, 'C.2.16', 'Marcos históricos', NULL, 3),
  (@f, 'C.2.17', 'Obras de infraestrutura', NULL, 4),
  (@f, 'C.2.14.1', 'Engenho', NULL, 5),
  (@f, 'C.2.14.2', 'Moinho/usina', NULL, 6),
  (@f, 'C.2.14.3', 'Celeiro', NULL, 7),
  (@f, 'C.2.14.4', 'Alambique/vinícola', NULL, 8),
  (@f, 'C.2.14.5', 'Fábrica', NULL, 9),
  (@f, 'C.2.14.6', 'Casa de operários', NULL, 10),
  (@f, 'C.2.14.7', 'Fazenda', NULL, 11),
  (@f, 'C.2.14.8', 'Senzala', NULL, 12),
  (@f, 'C.2.14.9', 'Casa de chácara/sítio/fazenda/engenho', NULL, 13),
  (@f, 'C.2.14.10', 'Outras', NULL, 14),
  (@f, 'C.2.15.1', 'Panteão', NULL, 15),
  (@f, 'C.2.15.2', 'Mausoléu', NULL, 16),
  (@f, 'C.2.15.3', 'Cruzeiro', NULL, 17),
  (@f, 'C.2.15.4', 'Túmulo', NULL, 18),
  (@f, 'C.2.15.5', 'Memorial', NULL, 19),
  (@f, 'C.2.15.6', 'Cemitério', NULL, 20),
  (@f, 'C.2.16.1', 'Divisão territorial', NULL, 22),
  (@f, 'C.2.16.2', 'Referência à história', NULL, 23),
  (@f, 'C.2.16.3', 'Relativos a festas e rituais', NULL, 24),
  (@f, 'C.2.16.4', 'Outros', NULL, 25),
  (@f, 'C.2.17.1', 'Viaduto/ponte', NULL, 26),
  (@f, 'C.2.17.2', 'Túnel', NULL, 27),
  (@f, 'C.2.17.3', 'Caixa d''água', NULL, 28),
  (@f, 'C.2.17.4', 'Aqueduto', NULL, 29),
  (@f, 'C.2.17.5', 'Trapiche/píer', NULL, 30),
  (@f, 'C.2.17.6', 'Marina', NULL, 31),
  (@f, 'C.2.17.7', 'Porto', NULL, 32),
  (@f, 'C.2.17.8', 'Quebra-mar/molhe', NULL, 33),
  (@f, 'C.2.17.9', 'Barragem/represa', NULL, 34),
  (@f, 'C.2.17.10', 'Farol', NULL, 35),
  (@f, 'C.2.17.11', 'Estrutura ferroviária', NULL, 36),
  (@f, 'C.2.17.12', 'Estrutura rodoviária', NULL, 37),
  (@f, 'C.2.17.13', 'Estrutura aeroportuária', NULL, 38),
  (@f, 'C.2.17.14', 'Rotunda', NULL, 39),
  (@f, 'C.2.17.15', 'Elevador/funicular', NULL, 40),
  (@f, 'C.2.17.16', 'Viaduto', NULL, 41),
  (@f, 'C.2.17.17', 'Torre', NULL, 42);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (47, 'C2', 'C.2.18', 'C', 'Atrativos culturais', 'Obras de interesse artístico', 'pontual', 'atrativo', 339);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.18', 'Obras de interesse artístico', NULL, 1),
  (@f, 'C.2.18.1', 'Bens integrados à edificação', NULL, 2),
  (@f, 'C.2.18.2', 'Bens integrados à paisagem ou ao espaço urbano', NULL, 3),
  (@f, 'C.2.18.3', 'Outras', NULL, 4);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (48, 'C2', 'C.2.19, C.2.20, C.2.21, C.2.22, C.2.23, C.2.24', 'C', 'Atrativos culturais', 'Ruínas, museu/memorial, biblioteca, centros culturais/casas de cultura/galerias, teatros/anfiteatros e cines clube', 'pontual', 'atrativo', 348);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.19', 'Ruínas', 'Remanescentes de antigas obras (arquitetura ou escultura), recuperáveis ou não, com interesse para visitação.', 1),
  (@f, 'C.2.20', 'Museu/memorial', 'Instituições permanentes, particulares ou públicas, abertas à visitação, que conservam e apresentam coleções de objetos de caráter cultural ou científico.', 2),
  (@f, 'C.2.21', 'Biblioteca', 'Instituição permanente, particular ou pública, aberta à visitação, com coleções de livros e documentos para facultar a leitura, a pesquisa e o estudo.', 3),
  (@f, 'C.2.22', 'Centros culturais/casas de cultura/galerias', 'Instituições permanentes, públicas ou particulares, em edificações de interesse histórico ou artístico, com bibliotecas, galerias para exposições, salas de cinema e vídeo, espaços para shows e programação cultural.', 4),
  (@f, 'C.2.23', 'Teatros/anfiteatros', 'Espaços e edificações para apresentação de peças teatrais, musicais etc.', 5),
  (@f, 'C.2.24', 'Cines clube', NULL, 6);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (49, 'C2', 'C.2.25, C.2.26, C.2.27', 'C', 'Atrativos culturais', 'Gastronomia típica e preparação de alimentos, artesanato/trabalhos manuais e atividades tradicionais de trabalho', 'pontual', 'atrativo', 359);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.25', 'Gastronomia típica e preparação de alimentos', 'Conjunto de alimentos e bebidas e seus rituais peculiares de elaboração, modo de servir e de consumir, com ingredientes próprios de uma região e com base nas suas tradições.', 1),
  (@f, 'C.2.26', 'Artesanato/trabalhos manuais', 'Objetos produzidos manualmente ou com equipamentos rudimentares, em pequena escala, que refletem a concepção espacial e formal dos artistas populares de uma área ou região.', 2),
  (@f, 'C.2.27', 'Atividades tradicionais de trabalho', 'Manifestações de conhecimento empírico, práticas de trabalho e práticas sociais.', 3),
  (@f, 'C.2.25.1', 'Receitas típicas e tradicionais', NULL, 4),
  (@f, 'C.2.25.2', 'Técnicas de produção e processamento de alimentos', NULL, 5),
  (@f, 'C.2.25.3', 'Outras', NULL, 6),
  (@f, 'C.2.26.1', 'Cerâmica', 'Objetos que têm como matéria-prima fundamental a argila (barro).', 7),
  (@f, 'C.2.26.2', 'Escultura', 'Obras de arte representadas pela matéria-prima trabalhada em relevo, segundo técnicas especiais para cada tipo de material.', 8),
  (@f, 'C.2.26.3', 'Bordado', 'Técnicas variadas em tecidos.', 9),
  (@f, 'C.2.26.4', 'Cestaria', 'Trançados de fibras ou talas vegetais e de palha.', 10),
  (@f, 'C.2.26.5', 'Mosaico', NULL, 11),
  (@f, 'C.2.26.6', 'Tricô/crochê', NULL, 12),
  (@f, 'C.2.26.7', 'Entalhe', NULL, 13),
  (@f, 'C.2.26.8', 'Renda', 'Técnicas variadas tramadas com fios, como renda de bilros, de agulha (irlandesa, renascença), labirinto, crivo e frivolitê.', 14),
  (@f, 'C.2.26.9', 'Fotografia', NULL, 15),
  (@f, 'C.2.26.10', 'Tecelagem', 'Técnicas que utilizam o tear, como fiação, tapeçaria etc.', 16),
  (@f, 'C.2.26.11', 'Papel machê', NULL, 17),
  (@f, 'C.2.26.12', 'Macramê', NULL, 18),
  (@f, 'C.2.26.13', 'Plumária', 'Elaboração com penas de aves tropicais, praticamente restrita aos indígenas, para compor coifas, coroas, diademas, máscaras, adornos e outros objetos.', 19),
  (@f, 'C.2.26.14', 'Bijuteria', NULL, 20),
  (@f, 'C.2.26.15', 'Dobradura', NULL, 21),
  (@f, 'C.2.26.16', 'Marcenaria/marchetaria', NULL, 22),
  (@f, 'C.2.26.17', 'Gravura', NULL, 23),
  (@f, 'C.2.26.18', 'Pátina e texturização', NULL, 24),
  (@f, 'C.2.26.19', 'Cartonagem', NULL, 25),
  (@f, 'C.2.26.20', 'Pintura', 'Obras de arte com utilização de tintas, realizadas em quadros, painéis ou outras composições planas, para representar figuras ou formas.', 26),
  (@f, 'C.2.26.21', 'Découpage', NULL, 27),
  (@f, 'C.2.26.22', 'Topiaria/arranjos florais', NULL, 28),
  (@f, 'C.2.26.23', 'Outros', NULL, 29),
  (@f, 'C.2.27.1', 'Agricultor', NULL, 30),
  (@f, 'C.2.27.2', 'Pescador', NULL, 31),
  (@f, 'C.2.27.3', 'Seringueiro', NULL, 32),
  (@f, 'C.2.27.4', 'Garimpeiro', NULL, 33),
  (@f, 'C.2.27.5', 'Quebrador de coco', NULL, 34),
  (@f, 'C.2.27.6', 'Fotógrafo lambe-lambe', NULL, 35),
  (@f, 'C.2.27.7', 'Carpinteiro', NULL, 36),
  (@f, 'C.2.27.8', 'Peão', NULL, 37);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (50, 'C2', 'C.2.28', 'C', 'Atrativos culturais', 'Formas de expressão', 'pontual', 'atrativo', 365);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.28', 'Formas de expressão', NULL, 1),
  (@f, 'C.2.28.1', 'Música', 'Manifestações artísticas, folclóricas, profissionais, amadoras e outras que variam conforme a região.', 2),
  (@f, 'C.2.28.2', 'Dança', 'Manifestações artísticas, folclóricas, profissionais, amadoras e outras que variam conforme a região.', 3),
  (@f, 'C.2.28.3', 'Literária/oral', NULL, 4),
  (@f, 'C.2.28.4', 'Cênica/performática', NULL, 5),
  (@f, 'C.2.28.5', 'Outras', NULL, 6);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (51, 'C2', 'C.2.29', 'C', 'Atrativos culturais', 'Personalidades', 'pontual', 'atrativo', 371);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.2.29', 'Personalidades', NULL, 1);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (52, 'C3', 'C.3.1, C.3.2, C.3.3, C.3.4, C.3.5', 'C', 'Atividades econômicas', 'Atividades econômicas', 'pontual', 'atrativo', 375);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.3.1', 'Extrativista', NULL, 1),
  (@f, 'C.3.2', 'Agropecuária', 'Produções agropecuárias e outras culturas capazes de atrair turistas pela atividade em si, pelas técnicas de produção ou outros fatores.', 2),
  (@f, 'C.3.3', 'Comercial', NULL, 3),
  (@f, 'C.3.4', 'Industrial', 'Produções industriais que, pela atividade em si, pelas técnicas de produção ou outros fatores, são capazes de atrair turistas.', 4),
  (@f, 'C.3.5', 'Tecnológica', NULL, 5),
  (@f, 'C.3.1.1', 'Mineral', 'Minas produtivas cuja atratividade reside nas técnicas de produção e no inusitado da atividade em si.', 6),
  (@f, 'C.3.1.2', 'Vegetal', 'Produção extrativista permitida legalmente, cuja atratividade reside nas técnicas de produção e no inusitado da atividade em si.', 7),
  (@f, 'C.3.1.3', 'Animal', NULL, 8),
  (@f, 'C.3.2.1', 'Agricultura', NULL, 9),
  (@f, 'C.3.2.2', 'Pecuária', NULL, 10),
  (@f, 'C.3.2.3', 'Aquicultura', NULL, 11),
  (@f, 'C.3.2.4', 'Silvicultura', NULL, 12),
  (@f, 'C.3.2.5', 'Outras', NULL, 13),
  (@f, 'C.3.3.1', 'Atacadista', NULL, 14),
  (@f, 'C.3.3.2', 'Varejista', NULL, 15),
  (@f, 'C.3.4.1', 'Petrolífera', NULL, 16),
  (@f, 'C.3.4.2', 'Automobilística', NULL, 17),
  (@f, 'C.3.4.3', 'Têxtil', NULL, 18),
  (@f, 'C.3.4.4', 'Alimentícia', NULL, 19),
  (@f, 'C.3.4.5', 'Coureira', NULL, 20),
  (@f, 'C.3.4.6', 'Joalheira', NULL, 21),
  (@f, 'C.3.4.7', 'Madeireira', NULL, 22),
  (@f, 'C.3.4.8', 'Ceramista', NULL, 23);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (53, 'C4', 'C.4.1, C.4.2, C.4.3, C.4.4, C.4.5, C.4.6, C.4.7, C.4.8', 'C', 'Realizações técnicas e científicas contemporâneas', 'Realizações técnicas e científicas contemporâneas', 'pontual', 'atrativo', 379);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.4.1', 'Parque tecnológico', NULL, 1),
  (@f, 'C.4.2', 'Centro de pesquisa', NULL, 2),
  (@f, 'C.4.3', 'Usinas e outras estruturas de geração de energia', NULL, 3),
  (@f, 'C.4.4', 'Eclusa/açude', NULL, 4),
  (@f, 'C.4.5', 'Planetário', NULL, 5),
  (@f, 'C.4.6', 'Aquário', NULL, 6),
  (@f, 'C.4.7', 'Viveiro', NULL, 7),
  (@f, 'C.4.8', 'Outras', NULL, 8),
  (@f, 'C.4.3.1', 'Hidrelétrica', NULL, 9),
  (@f, 'C.4.3.2', 'Termoelétrica', NULL, 10),
  (@f, 'C.4.3.3', 'Nuclear', NULL, 11),
  (@f, 'C.4.3.4', 'Eólica', NULL, 12),
  (@f, 'C.4.3.5', 'Solar', NULL, 13);

INSERT INTO inventario_fichas (ordem, codigo, codigos_mtur, categoria, grupo, nome, forma, eventcity, pagina_2011) VALUES (54, 'C5', 'C.5.1, C.5.2, C.5.3, C.5.4, C.5.5, C.5.6, C.5.7, C.5.8, C.5.9, C.5.10, C.5.11', 'C', 'Eventos programados', 'Eventos programados', 'pontual', NULL, 388);
SET @f = LAST_INSERT_ID();
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) VALUES
  (@f, 'C.5.1', 'Feiras/exposições', NULL, 1),
  (@f, 'C.5.2', 'Congressos', NULL, 2),
  (@f, 'C.5.3', 'Convenções', NULL, 3),
  (@f, 'C.5.4', 'Festivais/shows', NULL, 4),
  (@f, 'C.5.5', 'Seminários', NULL, 5),
  (@f, 'C.5.6', 'Oficinas/workshops', NULL, 6),
  (@f, 'C.5.7', 'Competições', NULL, 7),
  (@f, 'C.5.8', 'Desfiles/passeatas', NULL, 8),
  (@f, 'C.5.9', 'Encontros temáticos', NULL, 9),
  (@f, 'C.5.10', 'Festas/celebrações', NULL, 10),
  (@f, 'C.5.11', 'Outros', NULL, 11),
  (@f, 'C.5.10.1', 'Religiosa/manifestação de fé', NULL, 12),
  (@f, 'C.5.10.2', 'Popular/folclórica', NULL, 13),
  (@f, 'C.5.10.3', 'Referente ao trabalho ou ciclo produtivo', NULL, 14),
  (@f, 'C.5.10.4', 'Festa cívica', NULL, 15),
  (@f, 'C.5.10.5', 'Outras', NULL, 16);

