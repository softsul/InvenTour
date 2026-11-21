-- InvenTour: horário livre nas paradas dos roteiros.
-- Softsul Software, 21/11/2026.
--
-- As etapas fixas do Um dia aqui (manhã, almoço...) não servem a todos os
-- destinos: no Rio cabem cinco pontos numa manhã, noutro roteiro um lugar
-- toma o dia. Cada parada passa a ter a hora de chegada e o tempo de visita
-- que a equipe quiser; as regras viram blocos livres (em ajustes, um_dia).

ALTER TABLE roteiro_itens
    ADD COLUMN hora CHAR(5) NULL COMMENT 'hora de chegada sugerida, HH:MM' AFTER ordem,
    ADD COLUMN duracao_min SMALLINT UNSIGNED NULL COMMENT 'tempo sugerido no lugar, em minutos' AFTER hora,
    DROP COLUMN etapa;
