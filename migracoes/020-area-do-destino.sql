-- InvenTour: fotos e GPS só dentro da área do destino.
-- Softsul Software, 21/11/2026.
--
-- A divisa oficial de cada município (malha municipal do IBGE, baixada uma
-- vez por app/AreaDestino.php) e a margem de tolerância do destino: perto da
-- divisa o GPS varia e há atrativos na própria linha (rio, rodovia, ponte).
-- A foto de fora da área entra marcada, para quem homologa decidir.
CREATE TABLE municipio_areas (
    municipio_id  INT UNSIGNED NOT NULL,
    geojson       MEDIUMTEXT   NOT NULL COMMENT 'geometria GeoJSON (Polygon ou MultiPolygon), lng e lat',
    lat_min       DECIMAL(9,6) NOT NULL,
    lat_max       DECIMAL(9,6) NOT NULL,
    lng_min       DECIMAL(9,6) NOT NULL,
    lng_max       DECIMAL(9,6) NOT NULL,
    fonte         VARCHAR(160) NOT NULL,
    atualizado_em DATETIME     NOT NULL,
    PRIMARY KEY (municipio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE empresas
    ADD COLUMN area_margem_m SMALLINT UNSIGNED NOT NULL DEFAULT 2000 COMMENT 'tolerância além da divisa, em metros';

ALTER TABLE inventario_midias
    ADD COLUMN fora_area TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'GPS da foto fora da área do destino (com a margem)' AFTER precisao_m,
    ADD COLUMN distancia_area_m INT UNSIGNED NULL COMMENT 'distância até a divisa, quando fora' AFTER fora_area;
