-- InvenTour: deslocamento entre as paradas do roteiro.
-- Softsul Software, 21/11/2026.
--
-- A hora de chegada passa a ser calculada: a primeira parada tem a hora de
-- início, e cada uma das seguintes chega na hora da anterior + o tempo nela +
-- o deslocamento até aqui. O deslocamento vem estimado pela distância (a pé
-- ou de carro) e o redator ajusta; a hora fica gravada já calculada.

ALTER TABLE roteiro_itens
    ADD COLUMN deslocamento_min SMALLINT UNSIGNED NULL COMMENT 'minutos da parada anterior até esta' AFTER duracao_min;
