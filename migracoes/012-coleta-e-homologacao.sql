-- InvenTour, etapa 3: coleta de campo e homologação.
-- Softsul Software, 20/11/2026.
--
-- Os módulos coleta e homologacao nasceram na 002 desligados, com as
-- permissões por papel (pesquisador coleta; coordenador, gestor e consultor
-- homologam). Aqui eles ligam e ganham o que falta:
-- - o escopo do pesquisador por códigos de ficha (B1, C2...), no vínculo
--   dele com o destino: nulo é todas;
-- - o envio idempotente da tela de campo: cada coleta e cada foto chegam com
--   um identificador gerado no celular, e a repetição (sinal que volta no
--   meio do envio) devolve o mesmo resultado em vez de duplicar;
-- - a nota da devolução, que o pesquisador lê na próxima ida a campo.

UPDATE modulos SET ativo = 1 WHERE chave IN ('coleta', 'homologacao');

ALTER TABLE usuario_empresa
    ADD COLUMN escopo_fichas JSON NULL COMMENT 'códigos de ficha que o pesquisador coleta (["B1","C2"]); nulo: todas' AFTER registro_profissional;

ALTER TABLE inventario_itens
    ADD COLUMN enviado_em DATETIME NULL COMMENT 'última ida para a conferência' AFTER coletado_em,
    ADD COLUMN devolucao_nota VARCHAR(1000) NULL COMMENT 'o que a conferência pediu para acertar em campo' AFTER homologado_em;

CREATE TABLE inventario_coletas (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id  INT UNSIGNED NOT NULL,
    uuid        CHAR(36)     NOT NULL COMMENT 'gerado no celular, um por coleta ou por foto',
    tipo        ENUM('item','foto') NOT NULL,
    usuario_id  INT UNSIGNED NOT NULL,
    item_id     INT UNSIGNED NULL,
    resultado   JSON         NULL COMMENT 'a resposta devolvida, repetida se o mesmo envio chegar de novo',
    recebido_em DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_coletas_uuid (empresa_id, uuid),
    KEY ix_coletas_usuario (usuario_id, recebido_em),
    CONSTRAINT fk_coletas_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    CONSTRAINT fk_coletas_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='envios da tela de campo, para a repetição não duplicar';
