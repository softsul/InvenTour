-- InvenTour, etapa 4: o caderno oficial do inventário.
-- Softsul Software, 21/11/2026.
--
-- Cada geração é uma versão do caderno (cadernos): o recorte pedido, a
-- situação na fila, o código de verificação impresso no rodapé e os
-- arquivos (PDF, XLSX e CSV) guardados em storage/cadernos/<destino>.
-- caderno_leituras conta as leituras dos QR do PDF por dia, sem IP e sem
-- cookie: é o funil da prévia bloqueada no modo projeto.

UPDATE modulos SET ativo = 1, nome = 'Caderno oficial', icone = 'manual' WHERE chave = 'caderno';

CREATE TABLE cadernos (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id     INT UNSIGNED NOT NULL,
    titulo         VARCHAR(160) NOT NULL,
    ano            SMALLINT UNSIGNED NOT NULL,
    versao         SMALLINT UNSIGNED NOT NULL COMMENT 'sequência por destino',
    recorte        JSON NULL COMMENT '{"categorias":["A"],"fichas":[12]}; nulo é completo',
    previa         TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1: inclui o que ainda não foi homologado, com a marca de versão preliminar',
    situacao       ENUM('pendente','gerando','pronto','falhou') NOT NULL DEFAULT 'pendente',
    progresso      VARCHAR(120) NULL,
    codigo         CHAR(10) NOT NULL COMMENT 'código de verificação do rodapé',
    paginas        SMALLINT UNSIGNED NULL,
    itens          INT UNSIGNED NULL,
    bytes          INT UNSIGNED NULL,
    sha256         CHAR(64) NULL,
    erro           VARCHAR(500) NULL,
    pedido_por     INT UNSIGNED NULL,
    criado_em      DATETIME NOT NULL,
    gerado_em      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cadernos_codigo (codigo),
    KEY ix_cadernos_empresa (empresa_id, id),
    CONSTRAINT fk_cadernos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='versões geradas do caderno oficial do inventário';

CREATE TABLE caderno_leituras (
    empresa_id  INT UNSIGNED NOT NULL,
    caderno_id  INT UNSIGNED NOT NULL,
    item_id     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0: o QR da capa',
    dia         DATE NOT NULL,
    leituras    INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (caderno_id, item_id, dia),
    KEY ix_leituras_empresa (empresa_id, dia),
    CONSTRAINT fk_leituras_caderno FOREIGN KEY (caderno_id) REFERENCES cadernos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='leituras dos QR do caderno por dia, sem IP e sem cookie';
