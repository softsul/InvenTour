-- InvenTour: a regra da área do destino passa a ser de cada destino.
-- Softsul Software, 21/11/2026.
--
-- Até aqui GPS, plus code e foto fora da área entravam com alerta, para
-- todos. Agora cada destino escolhe, nos Dados do destino, se fora da área
-- só alerta ou se recusa, e ajusta a margem da divisa (area_margem_m, que já
-- existia). O padrão continua sendo alertar.
ALTER TABLE empresas
    ADD COLUMN area_regra ENUM('alertar','bloquear') NOT NULL DEFAULT 'alertar'
        COMMENT 'fora da área do destino: alertar (grava e avisa) ou bloquear (recusa)' AFTER area_margem_m;
