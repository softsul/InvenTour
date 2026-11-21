-- InvenTour: o item novo gravado no aparelho e editado antes de sincronizar.
-- Softsul Software, 21/11/2026.
--
-- O aparelho dá a cada item novo uma identificação local (local_id) até o
-- servidor devolver o número. As gravações seguintes do mesmo item chegavam
-- sem o número e eram recusadas ("falta o nome"). Guardando o local_id do
-- envio, o servidor acha o item que aquela identificação já criou.
ALTER TABLE inventario_coletas
    ADD COLUMN local_id CHAR(36) NULL COMMENT 'identificação do item no aparelho, antes de ter número' AFTER uuid,
    ADD KEY ix_coletas_local (empresa_id, local_id);
