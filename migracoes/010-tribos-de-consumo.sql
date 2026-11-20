-- InvenTour, etapa 2: tribos de consumo e nichos comportamentais (documento 07
-- do pacote de especificação: 5 macrocategorias, 21 tribos e as facilidades
-- verificáveis de cada uma). Lista aberta: a curadoria acrescenta e desliga
-- pela tela Tribos de consumo. O selo da tribo no portal pede pelo menos 2
-- facilidades marcadas no item.
-- Softsul Software, 20/11/2026.

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Mobilidade, Estrada e Esportes de Resistência', 'cicloturistas_bike_friendly', 'Cicloturistas / Bike Friendly', 'Viajantes que usam a bicicleta como meio de transporte ou esporte (MTB e Speed) e precisam de segurança para equipamentos caros e apoio mecânico.', 'esporte', 1);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'bicicletario_coberto_e_monitorado', 'Bicicletário coberto e monitorado', 1),
  (@t, 'permite_bicicleta_no_quarto', 'Permite bicicleta no quarto', 2),
  (@t, 'oficina_com_bancada_e_ferramentas', 'Oficina com bancada e ferramentas', 3),
  (@t, 'bomba_de_ar_de_pe', 'Bomba de ar de pé', 4),
  (@t, 'area_de_lavagem_de_bikes', 'Área de lavagem de bikes (mangueira/lava-jato)', 5),
  (@t, 'cafe_da_manha_antecipado', 'Café da manhã antecipado (antes das 6h)', 6),
  (@t, 'kit_de_primeiros_socorros', 'Kit de primeiros socorros', 7),
  (@t, 'transfer_de_bagagens', 'Transfer de bagagens', 8),
  (@t, 'venda_de_camaras_e_suplementos', 'Venda de câmaras e suplementos', 9);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Mobilidade, Estrada e Esportes de Resistência', 'motociclistas_estradeiros', 'Motociclistas / Estradeiros (Moto Friendly)', 'Viajam em grupo ou solo por rodovias panorâmicas (como Serra do Rio do Rastro ou Estrada Real), valorizam estacionamento seguro e espaço para secar equipamentos.', 'carro', 2);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'estacionamento_coberto_e_fechado', 'Estacionamento coberto e fechado', 1),
  (@t, 'vagas_exclusivas_para_motos', 'Vagas exclusivas para motos', 2),
  (@t, 'espaco_para_lavar_a_moto', 'Espaço para lavar a moto', 3),
  (@t, 'armario_ou_cabideiro_reforcado_para_jaquetas_capacetes', 'Armário ou cabideiro reforçado para jaquetas/capacetes', 4),
  (@t, 'secadora_de_roupas_botas', 'Secadora de roupas/botas', 5),
  (@t, 'lubrificante_de_corrente_disponivel', 'Lubrificante de corrente disponível', 6),
  (@t, 'oficina_mecanica_parceira_proxima', 'Oficina mecânica parceira próxima', 7),
  (@t, 'bar_ou_area_de_convivencia_noturna', 'Bar ou área de convivência noturna', 8);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Mobilidade, Estrada e Esportes de Resistência', 'corredores_e_triatletas', 'Corredores e Triatletas (Running / Triathlon Friendly)', 'Viajam para provas de rua, trail run ou treinos em altitude/litoral, exigindo horários flexíveis e alimentação específica.', 'esporte', 3);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'cafe_da_manha_pre_prova', 'Café da manhã pré-prova (madrugada)', 1),
  (@t, 'late_check_out_garantido_no_dia_da_prova', 'Late check-out garantido no dia da prova', 2),
  (@t, 'rotas_de_treino_mapeadas_na_recepcao', 'Rotas de treino mapeadas na recepção', 3),
  (@t, 'banheira_de_gelo_crioterapia', 'Banheira de gelo / crioterapia', 4),
  (@t, 'servico_de_lavanderia_expressa_para_roupas_esportivas', 'Serviço de lavanderia expressa para roupas esportivas', 5),
  (@t, 'acesso_a_piscina_de_25m_ou_50m', 'Acesso a piscina de 25m ou 50m', 6),
  (@t, 'cardapio_rico_em_carboidratos', 'Cardápio rico em carboidratos (massa/batata-doce)', 7),
  (@t, 'massagem_desportiva', 'Massagem desportiva', 8);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Mobilidade, Estrada e Esportes de Resistência', 'viajantes_de_motorhome_kombi_e_campistas', 'Viajantes de Motorhome, Kombi e Campistas (Overlanders)', 'Nômades rodoviários ou adeptos do campismo que precisam de pontos de apoio estruturados e seguros para pernoite.', 'carro', 4);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'ponto_de_energia_eletrica', 'Ponto de energia elétrica (110V/220V)', 1),
  (@t, 'abastecimento_de_agua_potavel', 'Abastecimento de água potável', 2),
  (@t, 'descarte_de_aguas_cinzas_e_negras', 'Descarte de águas cinzas e negras (detritos)', 3),
  (@t, 'area_gramada_e_nivelada', 'Área gramada e nivelada', 4),
  (@t, 'vestiarios_com_chuveiro_quente_24h', 'Vestiários com chuveiro quente 24h', 5),
  (@t, 'cozinha_comunitaria_equipada', 'Cozinha comunitária equipada', 6),
  (@t, 'seguranca_portaria_24h', 'Segurança/portaria 24h', 7),
  (@t, 'area_para_fogueira_permitida', 'Área para fogueira permitida', 8),
  (@t, 'aceita_barracas_de_teto', 'Aceita barracas de teto', 9);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Mobilidade, Estrada e Esportes de Resistência', 'peregrinos_e_caminhantes_de_longa_distancia', 'Peregrinos e Caminhantes de Longa Distância', 'Percorrem rotas religiosas ou contemplativas (Caminho da Fé, Caminho de Cora Coralina, Passos de Anchieta) com orçamento controlado e foco em recuperação física.', 'religioso', 5);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'carimbo_oficial_da_rota_passaporte', 'Carimbo oficial da rota/passaporte', 1),
  (@t, 'diaria_individual_economica', 'Diária individual econômica', 2),
  (@t, 'tanque_para_lavar_roupas_e_varal_amplo', 'Tanque para lavar roupas e varal amplo', 3),
  (@t, 'bacia_para_escalda_pes', 'Bacia para escalda-pés', 4),
  (@t, 'massagem_para_pes_e_pernas', 'Massagem para pés e pernas', 5),
  (@t, 'jantar_caseiro_incluido', 'Jantar caseiro incluído (meia-pensão)', 6),
  (@t, 'check_in_flexivel_a_pe', 'Check-in flexível a pé', 7),
  (@t, 'venda_de_curativos_esparadrapos_para_bolhas', 'Venda de curativos/esparadrapos para bolhas', 8);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Natureza, Aventura e Estilo de Vida Outdoor', 'trilheiros_montanhistas_e_ecoturistas', 'Trilheiros, Montanhistas e Ecoturistas', 'Buscam parques nacionais, travessias e cachoeiras, precisando de logística para saída cedo e limpeza de equipamentos enlameados.', 'natureza', 6);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'lanche_de_trilha', 'Lanche de trilha (kit piquenique para levar)', 1),
  (@t, 'lava_botas_na_entrada', 'Lava-botas na entrada', 2),
  (@t, 'guarda_volumes_para_mochilas_cargueiras', 'Guarda-volumes para mochilas cargueiras', 3),
  (@t, 'contato_direto_com_guias_locais_credenciados', 'Contato direto com guias locais credenciados (Cadastur)', 4),
  (@t, 'transfer_4x4_ate_a_portaria_do_parque', 'Transfer 4x4 até a portaria do parque', 5),
  (@t, 'secador_de_botas_e_roupas_umidas', 'Secador de botas e roupas úmidas', 6),
  (@t, 'aluguel_de_bastoes_de_caminhada_lanternas', 'Aluguel de bastões de caminhada/lanternas', 7);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Natureza, Aventura e Estilo de Vida Outdoor', 'observadores_de_aves_e_vida_selvagem', 'Observadores de Aves e Vida Selvagem (Birdwatchers)', 'Turistas especializados (muitos estrangeiros e brasileiros de alta renda) que acordam antes do amanhecer e exigem silêncio e preservação ambiental.', 'natureza', 7);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'trilhas_privativas_em_mata_nativa', 'Trilhas privativas em mata nativa', 1),
  (@t, 'comedouros_e_bebedouros_de_aves_na_propriedade', 'Comedouros e bebedouros de aves na propriedade', 2),
  (@t, 'torres_ou_mirantes_de_observacao', 'Torres ou mirantes de observação', 3),
  (@t, 'lista_de_especies_catalogadas_no_local', 'Lista de espécies catalogadas no local', 4),
  (@t, 'cafe_da_manha_as_4h30_5h', 'Café da manhã às 4h30/5h', 5),
  (@t, 'guia_ornitologo_parceiro', 'Guia ornitólogo parceiro', 6),
  (@t, 'politica_rigorosa_de_silencio', 'Política rigorosa de silêncio', 7),
  (@t, 'iluminacao_externa_sem_poluicao_luminosa', 'Iluminação externa sem poluição luminosa', 8);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Natureza, Aventura e Estilo de Vida Outdoor', 'surfistas_e_praticantes_de_esportes_nauticos', 'Surfistas e Praticantes de Esportes Náuticos (Kite, Wind, SUP)', 'Focados nas condições do mar e do vento no litoral brasileiro, carregam pranchas e velas volumosas.', 'lazer', 8);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'guarda_volumes_seguro_para_pranchas_e_velas', 'Guarda-volumes seguro para pranchas e velas', 1),
  (@t, 'ducha_externa_de_agua_doce', 'Ducha externa de água doce', 2),
  (@t, 'varal_especifico_para_roupas_de_borracha', 'Varal específico para roupas de borracha (neoprene)', 3),
  (@t, 'pe_na_areia_ou_a_menos_de_200m_do_pico', 'Pé na areia ou a menos de 200m do pico', 4),
  (@t, 'boletim_diario_de_ondas_e_vento', 'Boletim diário de ondas e vento', 5),
  (@t, 'aluguel_de_equipamentos', 'Aluguel de equipamentos', 6),
  (@t, 'oficina_de_reparo_de_pranchas_proxima', 'Oficina de reparo de pranchas próxima', 7),
  (@t, 'transfer_para_picos_vizinhos', 'Transfer para picos vizinhos', 8);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Natureza, Aventura e Estilo de Vida Outdoor', 'pescadores_esportivos', 'Pescadores Esportivos (Pesque e Solte)', 'Viajam em grupos para rios do Pantanal, Amazônia, Araguaia ou represas, buscando estrutura náutica completa.', 'natureza', 9);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'rampa_de_acesso_para_barcos', 'Rampa de acesso para barcos', 1),
  (@t, 'pier_ou_trapiche_iluminado', 'Píer ou trapiche iluminado', 2),
  (@t, 'aluguel_de_barcos_motores_e_piloteiros', 'Aluguel de barcos, motores e piloteiros', 3),
  (@t, 'freezer_para_armazenamento', 'Freezer para armazenamento (quando permitido)', 5),
  (@t, 'venda_de_iscas_vivas_e_tralhas', 'Venda de iscas vivas e tralhas', 6),
  (@t, 'licenca_de_pesca_facilitada', 'Licença de pesca facilitada', 7),
  (@t, 'limpeza_e_preparo_do_pescado', 'Limpeza e preparo do pescado', 8);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Gastronomia e Cultura Sensorial', 'amantes_de_cafes_especiais', 'Amantes de Cafés Especiais (Coffee Lovers)', 'Buscam cafeterias de terceira onda, torrefações locais e fazendas produtoras (Sul de Minas, Mogiana, Espírito Santo).', 'gastronomia', 10);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'graos_100_arabica_de_origem_rastreada', 'Grãos 100% Arábica de origem rastreada', 1),
  (@t, 'metodos_filtrados', 'Métodos filtrados (V60, Aeropress, Chemex, Prensa)', 2),
  (@t, 'torrefacao_propria', 'Torrefação própria', 3),
  (@t, 'barista_certificado', 'Barista certificado', 4),
  (@t, 'venda_em_graos_ou_moidos_na_hora', 'Venda em grãos ou moídos na hora', 5),
  (@t, 'opcoes_de_leites_vegetais', 'Opções de leites vegetais', 6),
  (@t, 'tour_guiado_pela_lavoura_de_cafe', 'Tour guiado pela lavoura de café', 7);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Gastronomia e Cultura Sensorial', 'enoturistas_e_apreciadores_de_cervejas_artesanais', 'Enoturistas e Apreciadores de Cervejas Artesanais', 'Visitam regiões produtoras (Serra Gaúcha, Vale do São Francisco, Serra da Mantiqueira) em busca de degustações e harmonizações.', 'gastronomia', 11);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'degustacao_guiada_com_sommelier', 'Degustação guiada com sommelier', 1),
  (@t, 'visita_a_vinicola_ou_fabrica', 'Visita à vinícola ou fábrica', 2),
  (@t, 'carta_de_rotulos_locais_e_premiados', 'Carta de rótulos locais e premiados', 3),
  (@t, 'tacas_de_cristal_adequadas', 'Taças de cristal adequadas', 4),
  (@t, 'menu_harmonizado', 'Menu harmonizado', 5),
  (@t, 'clube_de_assinaturas_ou_envio_para_todo_o_brasil', 'Clube de assinaturas ou envio para todo o Brasil', 6),
  (@t, 'transfer_seguro', 'Transfer seguro (motorista da rodada)', 7);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Gastronomia e Cultura Sensorial', 'veganos_vegetarianos_e_restricoes_alimentares', 'Veganos, Vegetarianos e Restrições Alimentares', 'Viajantes que enfrentam dificuldade para encontrar comida segura fora das capitais e escolhem o destino com base na oferta gastronômica inclusiva.', 'gastronomia', 12);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'cardapio_100_vegano', 'Cardápio 100% vegano', 1),
  (@t, 'opcoes_vegetarianas_sinalizadas', 'Opções vegetarianas sinalizadas', 2),
  (@t, 'cozinha_livre_de_contaminacao_cruzada', 'Cozinha livre de contaminação cruzada (sem glúten/celíacos)', 3),
  (@t, 'opcoes_sem_lactose', 'Opções sem lactose', 4),
  (@t, 'cafe_da_manha_com_itens_plant_based', 'Café da manhã com itens plant-based', 5),
  (@t, 'cosmeticos_veganos_e_nao_testados_em_animais_nos_quartos', 'Cosméticos veganos e não testados em animais nos quartos', 6);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Inclusão, Família e Perfil Social', 'pessoas_com_deficiencia_e_mobilidade_reduzida', 'Pessoas com Deficiência e Mobilidade Reduzida (PCD)', 'Necessitam de informações técnicas precisas (não apenas o selo genérico) para garantir autonomia e segurança real.', 'cadeirante', 13);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'rampa_de_acesso_com_inclinacao_adequada', 'Rampa de acesso com inclinação adequada (NBR 9050)', 1),
  (@t, 'elevador_com_sinalizacao_em_braille_e_sonora', 'Elevador com sinalização em Braille e sonora', 2),
  (@t, 'banheiro_adaptado_com_barras_de_apoio_e_cadeira_de_banho', 'Banheiro adaptado com barras de apoio e cadeira de banho', 3),
  (@t, 'porta_com_vao_livre_minimo_de_80cm', 'Porta com vão livre mínimo de 80cm', 4),
  (@t, 'piso_tatil_direcional_e_de_alerta', 'Piso tátil direcional e de alerta', 5),
  (@t, 'cardapio_em_braille_e_qr_code_acessivel', 'Cardápio em Braille e QR Code acessível', 6),
  (@t, 'atendimento_em_libras', 'Atendimento em Libras', 7),
  (@t, 'audiodescricao_disponivel', 'Audiodescrição disponível', 8),
  (@t, 'vaga_demarcada_proxima_a_entrada', 'Vaga demarcada próxima à entrada', 9);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Inclusão, Família e Perfil Social', 'tutores_de_pets', 'Tutores de Pets (Pet Friendly Real)', 'Tratam o animal como membro da família e rejeitam locais que apenas "toleram" o pet preso no quarto.', 'pet', 14);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'aceita_pets_de_grande_porte', 'Aceita pets de grande porte', 1),
  (@t, 'sem_restricao_de_raca', 'Sem restrição de raça', 2),
  (@t, 'pet_pode_circular_nas_areas_comuns_e_restaurante', 'Pet pode circular nas áreas comuns e restaurante', 3),
  (@t, 'caminha_comedouro_e_tapete_higienico_no_quarto', 'Caminha, comedouro e tapete higiênico no quarto', 4),
  (@t, 'area_gramada_cercada', 'Área gramada cercada (parcão)', 6),
  (@t, 'pet_sitter_ou_servico_de_passeio', 'Pet sitter ou serviço de passeio', 7),
  (@t, 'menu_exclusivo_para_pets', 'Menu exclusivo para pets', 8),
  (@t, 'sem_taxa_extra_de_limpeza', 'Sem taxa extra de limpeza', 9);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Inclusão, Família e Perfil Social', 'familias_com_bebes_e_criancas_pequenas', 'Famílias com Bebês e Crianças Pequenas', 'Pais que buscam infraestrutura para reduzir a bagagem e garantir entretenimento seguro para os filhos.', 'kids', 15);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'copa_do_bebe_24h', 'Copa do bebê 24h (micro-ondas, liquidificador, esterilizador)', 1),
  (@t, 'berco_e_banheira_infantil_disponiveis_no_quarto', 'Berço e banheira infantil disponíveis no quarto', 2),
  (@t, 'protetor_de_tomadas_e_telas_de_protecao_nas_janelas', 'Protetor de tomadas e telas de proteção nas janelas', 3),
  (@t, 'monitoria_infantil_por_faixa_etaria', 'Monitoria infantil por faixa etária', 4),
  (@t, 'piscina_infantil_aquecida_e_rasa', 'Piscina infantil aquecida e rasa', 5),
  (@t, 'trocador_nos_banheiros_masculino_e_feminino', 'Trocador nos banheiros masculino e feminino', 6),
  (@t, 'menu_kids_saudavel', 'Menu kids saudável', 7);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Inclusão, Família e Perfil Social', 'publico_60', 'Público 60+ (Melhor Idade Ativa)', 'Aposentados ativos que viajam fora da alta temporada, valorizam conforto térmico, acessibilidade leve e passeios culturais sem pressa.', 'coracao', 16);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'quartos_no_terreo_ou_com_elevador', 'Quartos no térreo ou com elevador', 1),
  (@t, 'piso_antiderrapante_no_banheiro', 'Piso antiderrapante no banheiro', 2),
  (@t, 'apoio_para_banho', 'Apoio para banho', 3),
  (@t, 'passeios_com_baixo_esforco_fisico', 'Passeios com baixo esforço físico', 4),
  (@t, 'descontos_para_terceira_idade_baixa_temporada', 'Descontos para terceira idade / baixa temporada', 5),
  (@t, 'atendimento_humanizado_e_sem_pressa', 'Atendimento humanizado e sem pressa', 6),
  (@t, 'climatizacao', 'Climatização (ar-condicionado quente/frio)', 7);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Inclusão, Família e Perfil Social', 'viajantes_lgbtqia', 'Viajantes LGBTQIA+ (Queer Friendly)', 'Buscam destinos e estabelecimentos comprovadamente acolhedores, livres de constrangimentos e com equipe treinada em diversidade.', 'coracao', 17);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'equipe_treinada_em_diversidade_e_inclusao', 'Equipe treinada em diversidade e inclusão', 1),
  (@t, 'cama_de_casal_padrao_sem_questionamentos', 'Cama de casal padrão sem questionamentos', 2),
  (@t, 'banheiros_sem_genero_nas_areas_comuns', 'Banheiros sem gênero nas áreas comuns', 3),
  (@t, 'ambiente_seguro', 'Ambiente seguro (Selo Safe Space)', 4),
  (@t, 'curadoria_de_roteiros_e_festas_lgbtqia_locais', 'Curadoria de roteiros e festas LGBTQIA+ locais', 5),
  (@t, 'propriedade_liderada_por_pessoas_lgbtqia', 'Propriedade liderada por pessoas LGBTQIA+', 6);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Inclusão, Família e Perfil Social', 'mulheres_que_viajam_sozinhas', 'Mulheres que Viajam Sozinhas (Solo Female Travelers)', 'Buscam segurança, localização bem iluminada, privacidade e redes de apoio ou hospedagens exclusivas.', 'pessoas', 18);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'quartos_ou_dormitorios_exclusivamente_femininos', 'Quartos ou dormitórios exclusivamente femininos', 1),
  (@t, 'recepcao_e_seguranca_24h', 'Recepção e segurança 24h', 2),
  (@t, 'fechadura_eletronica_ou_tranca_interna_reforcada', 'Fechadura eletrônica ou tranca interna reforçada', 3),
  (@t, 'rua_bem_iluminada_e_movimentada', 'Rua bem iluminada e movimentada', 4),
  (@t, 'transfer_seguro_do_aeroporto_rodoviaria', 'Transfer seguro do aeroporto/rodoviária', 5),
  (@t, 'anfitria_mulher', 'Anfitriã mulher', 6),
  (@t, 'passeios_em_grupo_para_mulheres', 'Passeios em grupo para mulheres', 7);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Trabalho, Economia e Bem-Estar', 'nomades_digitais_e_trabalho_remoto', 'Nômades Digitais e Trabalho Remoto (Workation)', 'Profissionais que trabalham enquanto viajam e dependem de infraestrutura de escritório de alta performance.', 'negocios', 19);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'wi_fi_de_alta_velocidade_comprovada', 'Wi-Fi de alta velocidade comprovada (acima de 200 Mbps)', 1),
  (@t, 'internet_redundante', 'Internet redundante (duas operadoras ou Starlink)', 2),
  (@t, 'mesa_ergonomica_e_cadeira_de_escritorio_no_quarto', 'Mesa ergonômica e cadeira de escritório no quarto', 3),
  (@t, 'espaco_de_coworking_integrado', 'Espaço de coworking integrado', 4),
  (@t, 'cabines_acusticas_para_videochamadas', 'Cabines acústicas para videochamadas', 5),
  (@t, 'gerador_de_energia_ou_nobreak', 'Gerador de energia ou nobreak', 6),
  (@t, 'descontos_para_estadias_longas', 'Descontos para estadias longas (mensal/semanal)', 7);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Trabalho, Economia e Bem-Estar', 'mochileiros_e_viajantes_low_cost', 'Mochileiros e Viajantes Low Cost', 'Jovens ou viajantes de longo curso que priorizam socialização, localização próxima ao transporte público e economia máxima.', 'mapa', 20);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'quartos_compartilhados_com_tomadas_e_luz_individual', 'Quartos compartilhados com tomadas e luz individual', 1),
  (@t, 'armarios_grandes_com_chave_cadeado', 'Armários (lockers) grandes com chave/cadeado', 2),
  (@t, 'cozinha_compartilhada_completa', 'Cozinha compartilhada completa', 3),
  (@t, 'proximo_ao_metro_rodoviaria', 'Próximo ao metrô/rodoviária', 4),
  (@t, 'free_walking_tour_ou_eventos_sociais_noturnos', 'Free walking tour ou eventos sociais noturnos', 5),
  (@t, 'guarderia_de_bagagem_gratuita_antes_depois_do_check_in', 'Guarderia de bagagem gratuita antes/depois do check-in', 6),
  (@t, 'precos_economicos', 'Preços econômicos', 7);

INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Trabalho, Economia e Bem-Estar', 'buscadores_de_bem_estar_retiros_e_espiritualidade', 'Buscadores de Bem-Estar, Retiros e Espiritualidade (Wellness)', 'Viajantes focados em desconexão digital, saúde mental, ioga, meditação e terapias integrativas (Chapada dos Veadeiros, Serra da Mantiqueira, Alto Paraíso).', 'natureza', 21);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'espaco_coberto_para_ioga_e_meditacao', 'Espaço coberto para ioga e meditação', 1),
  (@t, 'terapias_holisticas', 'Terapias holísticas (massagem, reiki, ayurveda)', 2),
  (@t, 'alimentacao_organica_e_agroecologica', 'Alimentação orgânica e agroecológica', 3),
  (@t, 'areas_livres_de_wi_fi', 'Áreas livres de Wi-Fi (detox digital)', 4),
  (@t, 'bioconstrucao_e_sustentabilidade', 'Bioconstrução e sustentabilidade', 5),
  (@t, 'trilhas_contemplativas_e_banhos_de_cachoeira', 'Trilhas contemplativas e banhos de cachoeira', 6),
  (@t, 'silencio_absoluto_apos_as_22h', 'Silêncio absoluto após as 22h', 7);

