-- InvenTour, etapa 1: subtipo ligado ao tipo (pai_id) e os 12 subtipos que a
-- migração 006 deixou de fora.
-- Softsul Software, 20/11/2026.
--
-- A 006 tratou o nome como único dentro da ficha, mas a ficha oficial repete
-- "Outros" e "Histórico" em tipos diferentes (C.2.4.4 e C.2.5.5, por
-- exemplo). A hierarquia está no código do MTur: C.2.5.5 é subtipo de C.2.5.
-- A chave única passa a ser ficha, código e nome, e cada subtipo aponta para
-- o tipo de que faz parte.

ALTER TABLE inventario_tipos
    ADD COLUMN pai_id INT UNSIGNED NULL COMMENT 'tipo de que este subtipo faz parte (pelo código do MTur)' AFTER ficha_id,
    DROP INDEX uk_tipos_ficha_nome,
    ADD UNIQUE KEY uk_tipos_ficha_codigo_nome (ficha_id, codigo_mtur, nome),
    ADD CONSTRAINT fk_tipos_pai FOREIGN KEY (pai_id) REFERENCES inventario_tipos (id);

INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.2.4.4', 'Outros', NULL, 23 FROM inventario_fichas WHERE ordem = 42;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.2.5.5', 'Outros', NULL, 28 FROM inventario_fichas WHERE ordem = 42;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.2.6.3', 'Histórico', NULL, 31 FROM inventario_fichas WHERE ordem = 42;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.2.6.4', 'Outros', NULL, 32 FROM inventario_fichas WHERE ordem = 42;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.2.8.4', 'Outros', NULL, 13 FROM inventario_fichas WHERE ordem = 43;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.2.12.10', 'Outras', NULL, 23 FROM inventario_fichas WHERE ordem = 45;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.2.13.17', 'Outras', NULL, 40 FROM inventario_fichas WHERE ordem = 45;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.2.15.7', 'Outras', NULL, 21 FROM inventario_fichas WHERE ordem = 46;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.2.17.18', 'Outras', NULL, 43 FROM inventario_fichas WHERE ordem = 46;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.2.27.9', 'Outras', NULL, 38 FROM inventario_fichas WHERE ordem = 49;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.3.4.9', 'Outras', NULL, 24 FROM inventario_fichas WHERE ordem = 52;
INSERT INTO inventario_tipos (ficha_id, codigo_mtur, nome, definicao, ordem) SELECT id, 'C.4.3.6', 'Outras', NULL, 14 FROM inventario_fichas WHERE ordem = 53;

-- Subtipo aponta para o tipo da mesma ficha cujo código é o seu menos o
-- último nível (B.1.1.5 Pousada aponta para B.1.1 Hoteleira e de apoio).
UPDATE inventario_tipos t
JOIN inventario_tipos p ON p.ficha_id = t.ficha_id
 AND p.codigo_mtur = SUBSTRING(t.codigo_mtur, 1, CHAR_LENGTH(t.codigo_mtur) - CHAR_LENGTH(SUBSTRING_INDEX(t.codigo_mtur, '.', -1)) - 1)
SET t.pai_id = p.id;
