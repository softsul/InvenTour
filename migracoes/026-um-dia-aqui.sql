-- InvenTour: o "Um dia aqui" do portal passa a ser definido pela equipe.
-- Softsul Software, 21/11/2026.
--
-- Duas formas, que o portal sorteia entre si: roteiros do tipo "Um dia aqui"
-- montados pela equipe (cada parada numa etapa do dia) e as regras por etapa
-- (que tipos de lugar valem de manhã, no almoço, à tarde, no fim da tarde, no
-- jantar e no pouso), guardadas em ajustes (grupo um_dia). Sem nenhum dos
-- dois, o item sai do menu do portal.

ALTER TABLE roteiros MODIFY tipo ENUM('permanente','evento','tribo','um_dia') NOT NULL DEFAULT 'permanente';
ALTER TABLE roteiro_itens ADD COLUMN etapa ENUM('manha','almoco','tarde','fim_tarde','jantar','pouso') NULL
    COMMENT 'etapa do dia, no roteiro do tipo um_dia' AFTER ordem;
