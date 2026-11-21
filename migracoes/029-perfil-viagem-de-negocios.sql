-- InvenTour: perfil de viajante "Viagem de Negócios", que faltava.
-- Softsul Software, 21/11/2026.
--
-- Entra no grupo Trabalho, Economia e Bem-Estar, antes dos nômades digitais,
-- com as facilidades que esse viajante procura (o selo do perfil aparece no
-- lugar que marca pelo menos duas).

UPDATE tribos SET ordem = ordem + 1 WHERE ordem >= 19;
INSERT INTO tribos (macro, chave, nome, descricao, icone, ordem) VALUES ('Trabalho, Economia e Bem-Estar', 'viagem_de_negocios', 'Viagem de Negócios (Business Travel)',
  'Viajam a trabalho, para reuniões, feiras, eventos e visitas a empresas: precisam de internet boa, lugar para trabalhar, horários flexíveis e nota fiscal para a empresa.', 'negocios', 19);
SET @t = LAST_INSERT_ID();
INSERT INTO tribo_facilidades (tribo_id, chave, nome, ordem) VALUES
  (@t, 'wifi_rapido_e_estavel', 'Wi-Fi rápido e estável', 1),
  (@t, 'mesa_de_trabalho_no_quarto', 'Mesa de trabalho no quarto', 2),
  (@t, 'sala_de_reuniao', 'Sala de reunião', 3),
  (@t, 'cafe_da_manha_antecipado_negocios', 'Café da manhã antecipado (antes das 6h30)', 4),
  (@t, 'recepcao_24h', 'Recepção 24 horas', 5),
  (@t, 'nota_fiscal_para_empresa', 'Nota fiscal em nome da empresa', 6),
  (@t, 'tarifa_corporativa', 'Tarifa ou convênio corporativo', 7),
  (@t, 'transfer_aeroporto_rodoviaria', 'Transfer para aeroporto ou rodoviária', 8),
  (@t, 'lavanderia_expressa', 'Lavanderia expressa', 9),
  (@t, 'tomadas_ao_lado_da_cama', 'Tomadas e carregadores ao lado da cama', 10);
