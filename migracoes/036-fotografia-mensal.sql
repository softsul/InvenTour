-- InvenTour, etapa 7: a fotografia mensal do destino (PLANEJAMENTO 2.9).
-- Softsul Software, 22/11/2026.
--
-- Um retrato por destino e por mês, tirado pela fila (job
-- fotografia_mensal) no primeiro dia do mês e, para o mês corrente, na
-- primeira vez que a fila roda nele. Imutável: o retrato do mês não é
-- refeito, é o registro de como o destino estava (base do relatório de
-- evolução, do painel executivo e do diagnóstico DTI).

CREATE TABLE destino_fotografias (
    empresa_id  INT UNSIGNED NOT NULL,
    mes         DATE NOT NULL COMMENT 'primeiro dia do mês do retrato',
    dados       JSON NOT NULL,
    tirada_em   DATETIME NOT NULL,
    PRIMARY KEY (empresa_id, mes),
    CONSTRAINT fk_fotografia_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='retrato mensal e imutável do inventário e do portal de cada destino';
