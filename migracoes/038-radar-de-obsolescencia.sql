-- InvenTour, etapa 7: radar de obsolescência (PLANEJAMENTO 2.9).
-- Softsul Software, 22/11/2026.
--
-- Todo dia (job radar_obsolescencia, 2h), o lugar homologado sem
-- conferência há mais de 180 dias, ou com o Cadastur vencido, vira
-- desatualizado: continua no portal, com o selo discreto "Dados em
-- atualização". Em destino ativo, o estabelecimento recebe por e-mail (e
-- por WhatsApp, quando o envio estiver ligado) um link de uso único por 15
-- dias: "Está tudo igual" renova a conferência sem trabalho da equipe;
-- "Preciso atualizar" leva ao acesso do responsável (Meus lugares) ou ao
-- pedido de acesso. Só o resumo do token vai ao banco.

CREATE TABLE radar_avisos (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id    INT UNSIGNED NOT NULL,
    item_id       INT UNSIGNED NOT NULL,
    token_hash    CHAR(64) NOT NULL,
    canais        VARCHAR(40) NULL COMMENT 'email, whatsapp: por onde saiu',
    enviado_para  VARCHAR(160) NULL COMMENT 'o contato público da ficha usado no envio',
    expira_em     DATETIME NOT NULL,
    respondido_em DATETIME NULL,
    resposta      ENUM('igual','atualizar') NULL,
    criado_em     DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_radar_token (token_hash),
    KEY ix_radar_item (item_id, criado_em),
    KEY ix_radar_destino (empresa_id, criado_em),
    CONSTRAINT fk_radar_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    CONSTRAINT fk_radar_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='links de uso único do radar de obsolescência ("está tudo igual" ou "preciso atualizar")';
