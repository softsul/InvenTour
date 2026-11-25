-- Os pontos de transporte em volta de cada destino (aeroporto, rodoviária,
-- estação ferroviária, marítima ou fluvial, metrô, ponto de ônibus e de
-- táxi), tirados do OpenStreetMap pela fila (job destino_transporte). Com
-- eles, a ficha do item calcula sozinha as distâncias a partir do ponto
-- marcado, no painel e no app de campo (que leva a lista para usar sem sinal).
-- Softsul Software

CREATE TABLE destino_transporte (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id  INT UNSIGNED NOT NULL,
    tipo        ENUM('aeroporto','rodoviaria','ferroviaria','maritima','metroviaria','ponto_onibus','ponto_taxi') NOT NULL,
    nome        VARCHAR(200) NULL,
    latitude    DECIMAL(9,6) NOT NULL,
    longitude   DECIMAL(9,6) NOT NULL,
    osm         VARCHAR(30)  NULL COMMENT 'n123, w456 ou r789',
    PRIMARY KEY (id),
    KEY ix_transporte_destino (empresa_id, tipo),
    CONSTRAINT fk_transporte_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='pontos de transporte do OpenStreetMap em volta do destino';

ALTER TABLE empresas
    ADD COLUMN transporte_em DATETIME NULL COMMENT 'quando os pontos de transporte foram buscados';
