-- InvenTour: de qual aparelho veio cada envio da coleta.
-- Softsul Software, 20/11/2026.
--
-- O histórico de sincronização da equipe (Coleta de campo, aba Aplicativo)
-- mostra quem enviou o quê, de qual celular. Envio pelo painel fica nulo.
ALTER TABLE inventario_coletas
    ADD COLUMN aparelho_id INT UNSIGNED NULL COMMENT 'campo_aparelhos.id quando veio do app' AFTER usuario_id,
    ADD KEY ix_coletas_empresa_data (empresa_id, recebido_em);
