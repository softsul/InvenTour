-- InvenTour, etapa 5: portal do destino, roteiros e pontos de QR.
-- Softsul Software, 21/11/2026.
--
-- Roteiros do gestor (permanentes, de evento ou de uma tribo), com os
-- lugares do inventário em ordem, para o portal. Pontos de QR (phygital):
-- o cartaz com o QR fica num lugar da cidade e leva a uma página do portal
-- (início, ficha, roteiro, um pilar ou a agenda). O apelido do ponto é
-- imutável: o cartaz impresso continua valendo se o destino do QR mudar. A
-- leitura é contada por dia, sem IP e sem cookie.

INSERT INTO modulos (chave, nome, icone, grupo, grupo_ordem, ordem, no_menu, apenas_softsul, contexto, ativo) VALUES
('roteiros', 'Roteiros', 'mapa', 'Destino', 3, 2, 1, 0, 'destino', 1);
UPDATE modulos SET ativo = 1, nome = 'Pontos de QR' WHERE chave = 'phygital';

INSERT INTO permissoes (modulo_id, chave, verbo)
SELECT m.id, CONCAT(m.chave, '.', v.verbo), v.verbo
FROM modulos m
CROSS JOIN (SELECT 'ver' AS verbo UNION ALL SELECT 'criar' UNION ALL SELECT 'editar' UNION ALL SELECT 'excluir' UNION ALL SELECT 'exportar') v
WHERE m.chave = 'roteiros';

-- Roteiros: as mesmas permissões do inventário, por papel e nas exceções.
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT pp.papel_id, nova.id
FROM papel_permissao pp
JOIN permissoes inv ON inv.id = pp.permissao_id AND inv.chave LIKE 'inventario.%'
JOIN permissoes nova ON nova.chave = CONCAT('roteiros.', inv.verbo);
INSERT INTO usuario_permissao (usuario_id, empresa_id, permissao_id, concedida)
SELECT up.usuario_id, up.empresa_id, nova.id, up.concedida
FROM usuario_permissao up
JOIN permissoes inv ON inv.id = up.permissao_id AND inv.chave LIKE 'inventario.%'
JOIN permissoes nova ON nova.chave = CONCAT('roteiros.', inv.verbo);

CREATE TABLE roteiros (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id  INT UNSIGNED NOT NULL,
    titulo      VARCHAR(150) NOT NULL,
    tipo        ENUM('permanente','evento','tribo') NOT NULL DEFAULT 'permanente',
    tribo_id    INT UNSIGNED NULL,
    descricao   TEXT NULL,
    duracao     VARCHAR(60) NULL COMMENT 'texto livre: meio dia, 2 dias',
    publicado   TINYINT(1) NOT NULL DEFAULT 0,
    ordem       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    criado_em   DATETIME NOT NULL,
    criado_por  INT UNSIGNED NULL,
    atualizado_em DATETIME NULL,
    PRIMARY KEY (id),
    KEY ix_roteiros_empresa (empresa_id, publicado, ordem),
    CONSTRAINT fk_roteiros_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='roteiros do gestor no portal';

CREATE TABLE roteiro_itens (
    roteiro_id  INT UNSIGNED NOT NULL,
    item_id     INT UNSIGNED NOT NULL,
    empresa_id  INT UNSIGNED NOT NULL,
    ordem       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    nota        VARCHAR(300) NULL COMMENT 'dica do roteiro para esta parada',
    PRIMARY KEY (roteiro_id, item_id),
    KEY ix_roteiro_itens_item (item_id),
    CONSTRAINT fk_roteiro_itens_roteiro FOREIGN KEY (roteiro_id) REFERENCES roteiros (id) ON DELETE CASCADE,
    CONSTRAINT fk_roteiro_itens_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qr_pontos (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id  INT UNSIGNED NOT NULL,
    apelido     VARCHAR(40) NOT NULL COMMENT 'imutável: é o que está impresso no cartaz',
    titulo      VARCHAR(150) NOT NULL,
    destino_tipo ENUM('inicio','ficha','roteiro','pilar','agenda','destino') NOT NULL DEFAULT 'inicio',
    destino_ref VARCHAR(60) NULL COMMENT 'id do item ou do roteiro, ou a chave do pilar',
    local_texto VARCHAR(200) NULL COMMENT 'onde o cartaz está afixado',
    ativo       TINYINT(1) NOT NULL DEFAULT 1,
    criado_em   DATETIME NOT NULL,
    criado_por  INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_qr_apelido (empresa_id, apelido),
    CONSTRAINT fk_qr_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='pontos de QR do destino (phygital)';

CREATE TABLE qr_leituras (
    ponto_id    INT UNSIGNED NOT NULL,
    empresa_id  INT UNSIGNED NOT NULL,
    dia         DATE NOT NULL,
    leituras    INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (ponto_id, dia),
    KEY ix_qr_leituras_empresa (empresa_id, dia),
    CONSTRAINT fk_qr_leituras_ponto FOREIGN KEY (ponto_id) REFERENCES qr_pontos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='leituras por dia, sem IP e sem cookie';
