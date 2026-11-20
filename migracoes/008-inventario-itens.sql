-- InvenTour, etapa 2: o inventário do destino.
-- Softsul Software, 20/11/2026.
--
-- Modelo de docs/PLANEJAMENTO.md, 2.4 e 2.5: uma linha por equipamento,
-- atrativo ou serviço inventariado (inventario_itens), com o núcleo comum
-- das seções 1, 2, 4, 5, 6, 7, 8 e 9 das fichas do MTur; satélites para o
-- que se repete (acessibilidade, fotos, proteção legal); e o catálogo de
-- atributos da seção 3 de cada ficha (EAV tipado), com o valor por item.
-- Mais o que é do destino e não de uma ficha: narrativas e linha do tempo.
-- Tribos de consumo e experiências turísticas, os diferenciais além da
-- metodologia. Tudo que é do destino leva empresa_id.
--
-- Ausência de resposta (metodologia do MTur): NA não se aplica, NI não
-- informou, NE não existe. Nas colunas do núcleo vai em `ausencias`
-- ({"campo": "na"}); nos atributos, na coluna `ausencia` do valor.

-- ---------------------------------------------------------------------------
-- O item
-- ---------------------------------------------------------------------------

CREATE TABLE inventario_itens (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'o inventour_id enviado ao EventCity',
    empresa_id            INT UNSIGNED NOT NULL,
    ficha_id              INT UNSIGNED NOT NULL,
    tipo_id               INT UNSIGNED NULL COMMENT 'tipo ou subtipo da ficha (hotel, pousada, cachoeira)',
    situacao              ENUM('pre_cadastro','em_campo','em_conferencia','homologada','desatualizada','arquivada')
                          NOT NULL DEFAULT 'em_campo' COMMENT 'esteira: só homologada e desatualizada vão ao portal e ao EventCity',
    origem                ENUM('painel','campo','cadastur','cnpj','eventcity','importacao') NOT NULL DEFAULT 'painel',
    item_pai_id           INT UNSIGNED NULL COMMENT 'restaurante ou salão dentro do hotel, quando aberto ao público',
    -- 1. Informações gerais
    nome                  VARCHAR(180) NOT NULL COMMENT 'nome fantasia ou denominação oficial',
    nome_popular          VARCHAR(180) NULL,
    razao_social          VARCHAR(180) NULL,
    cnpj                  CHAR(14)     NULL COMMENT 'letras maiúsculas e números, sem pontuação',
    inscricao_municipal   VARCHAR(30)  NULL,
    cadastur              VARCHAR(30)  NULL,
    cadastur_validade     DATE         NULL,
    cadastur_prestador_id INT UNSIGNED NULL COMMENT 'registro da cópia local do Cadastur que originou ou confere a ficha',
    natureza              ENUM('publica','privada','mista','outra') NULL,
    jurisdicao            ENUM('municipal','estadual','federal','nao_aplicavel') NULL COMMENT 'fichas lineares e públicas',
    rede                  VARCHAR(120) NULL COMMENT 'rede, holding ou franquia',
    inicio_atividade      SMALLINT UNSIGNED NULL COMMENT 'ano',
    funcionarios          SMALLINT UNSIGNED NULL,
    -- Localização
    zona                  ENUM('urbana','rural') NULL,
    cep                   CHAR(8)      NULL,
    logradouro            VARCHAR(200) NULL COMMENT 'rua, rodovia, linha rural ou comunidade',
    numero                VARCHAR(20)  NULL,
    complemento           VARCHAR(80)  NULL,
    bairro                VARCHAR(120) NULL COMMENT 'bairro ou distrito',
    latitude              DECIMAL(9,6) NULL,
    longitude             DECIMAL(9,6) NULL,
    maps_url              VARCHAR(500) NULL,
    referencia            VARCHAR(255) NULL COMMENT 'pontos de referência para chegar',
    sinalizacao_acesso    TINYINT(1)   NULL,
    sinalizacao_turistica TINYINT(1)   NULL,
    extensao_km           DECIMAL(9,2) NULL COMMENT 'fichas lineares: rodovia, ferrovia, hidrovia, trilha',
    -- Contato público (nunca o dado pessoal de quem responde, que fica em usuarios)
    telefone              VARCHAR(20)  NULL COMMENT 'E.164 sem o +',
    whatsapp              VARCHAR(20)  NULL COMMENT 'E.164 sem o +',
    email                 VARCHAR(150) NULL,
    site                  VARCHAR(255) NULL,
    instagram             VARCHAR(100) NULL,
    -- 2. Funcionamento
    horario               JSON         NULL COMMENT '{"seg":[["08:00","18:00"]],...,"24h":false,"feriados":"...","temporada":"..."}',
    formas_pagamento      JSON         NULL,
    idiomas               JSON         NULL,
    -- Capacidade instalada (vai ao EventCity e ao BI)
    capacidade_uh         INT UNSIGNED NULL COMMENT 'B1: unidades habitacionais',
    capacidade_leitos     INT UNSIGNED NULL COMMENT 'B1: leitos',
    capacidade_assentos   INT UNSIGNED NULL COMMENT 'B2: assentos simultâneos',
    capacidade_publico    INT UNSIGNED NULL COMMENT 'B5 e atrativos: público simultâneo',
    -- 5. Estado de conservação (escala da ficha oficial; decisão pendente no PLANEJAMENTO, 4.2)
    conservacao           ENUM('muito_bom','bom','ruim','nao_aplicavel') NULL,
    -- Textos
    descricao_tecnica     TEXT         NULL COMMENT 'caracterização para o caderno oficial',
    descricao_promocional TEXT         NULL COMMENT 'texto convidativo para o portal',
    observacoes           TEXT         NULL COMMENT 'seção 7: só painel e caderno',
    referencias           TEXT         NULL COMMENT 'seção 8: fontes bibliográficas',
    ausencias             JSON         NULL COMMENT 'NA, NI, NE por campo do núcleo',
    possui_experiencia    TINYINT(1)   NOT NULL DEFAULT 0,
    -- 9. Equipe responsável e trilha
    pesquisador_id        INT UNSIGNED NULL,
    coordenador_id        INT UNSIGNED NULL,
    coletado_em           DATETIME     NULL,
    homologado_em         DATETIME     NULL,
    ultima_auditoria_em   DATETIME     NULL COMMENT 'última confirmação em campo ou pelo trade: radar de obsolescência',
    criado_em             DATETIME     NOT NULL,
    criado_por            INT UNSIGNED NULL,
    atualizado_em         DATETIME     NULL,
    atualizado_por        INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY ix_itens_situacao (empresa_id, situacao, ficha_id),
    KEY ix_itens_nome (empresa_id, nome),
    KEY ix_itens_cnpj (empresa_id, cnpj),
    KEY ix_itens_geo (empresa_id, situacao, latitude, longitude),
    KEY ix_itens_auditoria (empresa_id, ultima_auditoria_em),
    CONSTRAINT fk_itens_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    CONSTRAINT fk_itens_ficha FOREIGN KEY (ficha_id) REFERENCES inventario_fichas (id),
    CONSTRAINT fk_itens_tipo FOREIGN KEY (tipo_id) REFERENCES inventario_tipos (id),
    CONSTRAINT fk_itens_pai FOREIGN KEY (item_pai_id) REFERENCES inventario_itens (id),
    CONSTRAINT fk_itens_pesquisador FOREIGN KEY (pesquisador_id) REFERENCES usuarios (id),
    CONSTRAINT fk_itens_coordenador FOREIGN KEY (coordenador_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='o inventário: atrativos, equipamentos e serviços do destino';

-- Seção 6: os subitens de acessibilidade da ficha oficial, cada um com as
-- opções marcadas (o catálogo dos subitens fica no código, a partir de
-- docs/especificacao/blocos-comuns-2011.json).
CREATE TABLE inventario_item_acessibilidade (
    item_id     INT UNSIGNED NOT NULL,
    empresa_id  INT UNSIGNED NOT NULL,
    chave       VARCHAR(40)  NOT NULL,
    opcoes      JSON         NOT NULL,
    PRIMARY KEY (item_id, chave),
    KEY ix_acess_empresa (empresa_id, chave),
    CONSTRAINT fk_acess_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seção 4: proteção, qualificação, certificação, premiação.
CREATE TABLE inventario_item_protecoes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id      INT UNSIGNED NOT NULL,
    empresa_id   INT UNSIGNED NOT NULL,
    esfera       ENUM('municipal','estadual','federal','internacional') NOT NULL,
    instrumento  VARCHAR(40)  NOT NULL COMMENT 'lei, decreto, portaria, norma, certificação, premiação...',
    descricao    VARCHAR(255) NOT NULL COMMENT 'número e do que trata: tombamento, selo, prêmio',
    PRIMARY KEY (id),
    KEY ix_protecoes_item (item_id),
    CONSTRAINT fk_protecoes_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fotos: reprocessadas com GD e servidas por /midia/inventario/.
CREATE TABLE inventario_midias (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id  INT UNSIGNED NOT NULL,
    item_id     INT UNSIGNED NOT NULL,
    arquivo     VARCHAR(160) NOT NULL COMMENT 'nome em storage/midias/inventario/<empresa_id>/',
    largura     SMALLINT UNSIGNED NULL,
    altura      SMALLINT UNSIGNED NULL,
    legenda     VARCHAR(200) NULL,
    credito     VARCHAR(120) NULL COMMENT 'autor da foto: obrigatório para publicar',
    capa        TINYINT(1)   NOT NULL DEFAULT 0,
    ordem       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    latitude    DECIMAL(9,6) NULL COMMENT 'do EXIF, antes de apagar o resto dos metadados',
    longitude   DECIMAL(9,6) NULL,
    criado_em   DATETIME     NOT NULL,
    criado_por  INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY ix_midias_item (item_id, ordem),
    CONSTRAINT fk_midias_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Atributos específicos de cada ficha (seção 3, Características)
-- ---------------------------------------------------------------------------

CREATE TABLE inventario_atributos (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ficha_id     INT UNSIGNED NOT NULL,
    grupo        VARCHAR(120) NOT NULL COMMENT 'subtítulo no formulário: Unidades habitacionais, Instalações',
    chave        VARCHAR(80)  NOT NULL,
    rotulo       VARCHAR(200) NOT NULL,
    tipo         ENUM('texto','texto_longo','numero','sim_nao','escolha','multipla','tabela') NOT NULL,
    opcoes       JSON         NULL COMMENT 'opções da escolha ou da múltipla; colunas da tabela',
    ajuda        VARCHAR(500) NULL,
    obrigatorio  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'núcleo mínimo do MTur',
    portal       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'interessa ao turista; 0 é técnico (KVA, esgoto)',
    ativo        TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0: anacrônico (fax, telex, 3G), guardado para importação antiga',
    ordem        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_atributos_ficha_chave (ficha_id, chave),
    CONSTRAINT fk_atributos_ficha FOREIGN KEY (ficha_id) REFERENCES inventario_fichas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='campos específicos de cada ficha do MTur';

CREATE TABLE inventario_item_atributos (
    item_id       INT UNSIGNED NOT NULL,
    atributo_id   INT UNSIGNED NOT NULL,
    empresa_id    INT UNSIGNED NOT NULL,
    valor_texto   TEXT          NULL COMMENT 'texto, escolha, e JSON da múltipla e da tabela',
    valor_numero  DECIMAL(14,2) NULL COMMENT 'número e sim_nao (1/0): agregável no BI',
    ausencia      ENUM('na','ni','ne') NULL,
    PRIMARY KEY (item_id, atributo_id),
    KEY ix_item_atributos_bi (empresa_id, atributo_id, valor_numero),
    CONSTRAINT fk_item_atributos_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id) ON DELETE CASCADE,
    CONSTRAINT fk_item_atributos_atributo FOREIGN KEY (atributo_id) REFERENCES inventario_atributos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Linha do tempo do item (só acréscimo): criação, alterações, mudanças de
-- situação, com o GPS de quem estava em campo.
CREATE TABLE inventario_item_historico (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id     INT UNSIGNED NOT NULL,
    empresa_id  INT UNSIGNED NOT NULL,
    usuario_id  INT UNSIGNED NULL,
    acao        VARCHAR(30)  NOT NULL COMMENT 'criou, alterou, enviou, devolveu, homologou, desatualizou, arquivou, importou',
    de          VARCHAR(30)  NULL,
    para        VARCHAR(30)  NULL,
    nota        VARCHAR(500) NULL,
    latitude    DECIMAL(9,6) NULL,
    longitude   DECIMAL(9,6) NULL,
    criado_em   DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_historico_item (item_id, id),
    CONSTRAINT fk_historico_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Do destino: narrativas e linha do tempo
-- ---------------------------------------------------------------------------

CREATE TABLE destino_narrativas (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id     INT UNSIGNED NOT NULL,
    secao          ENUM('apresentacao','historico','economia','geografia','infraestrutura','outra') NOT NULL,
    titulo         VARCHAR(150) NOT NULL,
    conteudo_html  MEDIUMTEXT   NOT NULL COMMENT 'HTML sanitizado (allowlist): colado do Word sem estilo nem imagem embutida',
    indicadores    JSON         NULL COMMENT 'economia do turismo: ACTs, Cadastur, empregos, VAF, IDAT, nível DTI, com fonte e ano',
    no_portal      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0: só no caderno',
    ordem          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    atualizado_em  DATETIME     NULL,
    atualizado_por INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY ix_narrativas_empresa (empresa_id, ordem),
    CONSTRAINT fk_narrativas_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='discurso oficial do destino: capítulos do caderno e abas do portal';

CREATE TABLE destino_linha_tempo (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id  INT UNSIGNED NOT NULL,
    data_marco  DATE         NOT NULL COMMENT 'com precisão de ano, grava 01/01',
    precisao    ENUM('dia','mes','ano') NOT NULL DEFAULT 'dia',
    categoria   ENUM('legislacao','acesso','evento','reconhecimento','mercado') NOT NULL,
    titulo      VARCHAR(150) NOT NULL,
    descricao   TEXT         NULL,
    imagem      VARCHAR(160) NULL,
    no_portal   TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em   DATETIME     NOT NULL,
    criado_por  INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY ix_linha_empresa (empresa_id, data_marco),
    CONSTRAINT fk_linha_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='marcos do turismo no destino: leis, voos, eventos, reconhecimentos';

-- ---------------------------------------------------------------------------
-- Tribos de consumo (catálogo global com curadoria) e experiências
-- ---------------------------------------------------------------------------

CREATE TABLE tribos (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    macro       VARCHAR(80)  NOT NULL COMMENT 'macrocategoria do documento 07',
    chave       VARCHAR(60)  NOT NULL,
    nome        VARCHAR(100) NOT NULL,
    descricao   VARCHAR(300) NULL,
    icone       VARCHAR(30)  NOT NULL DEFAULT 'etiqueta',
    ordem       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ativo       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tribos_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tribo_facilidades (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tribo_id    INT UNSIGNED NOT NULL,
    chave       VARCHAR(80)  NOT NULL,
    nome        VARCHAR(160) NOT NULL,
    ordem       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ativo       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_facilidades (tribo_id, chave),
    CONSTRAINT fk_facilidades_tribo FOREIGN KEY (tribo_id) REFERENCES tribos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O selo da tribo só aparece com pelo menos 2 facilidades marcadas.
CREATE TABLE inventario_item_facilidades (
    item_id         INT UNSIGNED NOT NULL,
    facilidade_id   INT UNSIGNED NOT NULL,
    empresa_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (item_id, facilidade_id),
    KEY ix_item_facilidades (empresa_id, facilidade_id),
    CONSTRAINT fk_item_facilidades_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id) ON DELETE CASCADE,
    CONSTRAINT fk_item_facilidades_facilidade FOREIGN KEY (facilidade_id) REFERENCES tribo_facilidades (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventario_experiencias (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id         INT UNSIGNED NOT NULL,
    item_id            INT UNSIGNED NOT NULL COMMENT 'empreendimento ou atrativo anfitrião',
    situacao           ENUM('rascunho','aguardando','aprovada','ajuste','reprovada') NOT NULL DEFAULT 'rascunho'
                       COMMENT 'curadoria: só aprovada vai ao portal',
    titulo             VARCHAR(160) NOT NULL,
    dimensoes          JSON         NOT NULL COMMENT '4 Es: {"entretenimento": "...", "educacional": "...", "escapismo": "...", "estetica": "..."}',
    narrativa          TEXT         NOT NULL COMMENT 'o que o visitante vê, ouve, prova, toca e aprende',
    duracao_minutos    SMALLINT UNSIGNED NOT NULL,
    pessoas_min        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    pessoas_max        SMALLINT UNSIGNED NOT NULL,
    agendamento        TINYINT(1)   NOT NULL DEFAULT 1,
    antecedencia_horas SMALLINT UNSIGNED NULL,
    faixa_valor        ENUM('gratuita','ate_80','de_81_a_200','de_201_a_500','acima_500') NOT NULL,
    valor_texto        VARCHAR(150) NULL,
    parecer            TEXT         NULL,
    curado_por         INT UNSIGNED NULL,
    curado_em          DATETIME     NULL,
    criado_em          DATETIME     NOT NULL,
    criado_por         INT UNSIGNED NULL,
    atualizado_em      DATETIME     NULL,
    PRIMARY KEY (id),
    KEY ix_experiencias (empresa_id, situacao),
    KEY ix_experiencias_item (item_id),
    CONSTRAINT fk_experiencias_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='experiências turísticas (os 4 Es), com curadoria anti porteira aberta';

-- As evidências (fotos da vivência acontecendo) usam inventario_midias com
-- esta ligação.
ALTER TABLE inventario_midias
    ADD COLUMN experiencia_id INT UNSIGNED NULL AFTER item_id,
    ADD CONSTRAINT fk_midias_experiencia FOREIGN KEY (experiencia_id) REFERENCES inventario_experiencias (id) ON DELETE CASCADE;

-- ---------------------------------------------------------------------------
-- Módulos e permissões
-- ---------------------------------------------------------------------------

-- O inventário entra no menu. O prestador perde o acesso até a etapa 6, que
-- traz o escopo dele na consulta (só a ficha do próprio negócio).
UPDATE modulos SET ativo = 1 WHERE chave = 'inventario';
DELETE pp FROM papel_permissao pp
JOIN papeis p ON p.id = pp.papel_id AND p.chave = 'prestador'
JOIN permissoes pe ON pe.id = pp.permissao_id
JOIN modulos m ON m.id = pe.modulo_id AND m.chave = 'inventario';

-- O destino (narrativas, linha do tempo) mora num módulo próprio.
INSERT INTO modulos (chave, nome, icone, grupo, grupo_ordem, ordem, no_menu, apenas_softsul, contexto, ativo)
VALUES ('destino-textos', 'O destino', 'manual', 'Inventário', 2, 5, 1, 0, 'destino', 1);
INSERT INTO permissoes (modulo_id, chave, verbo)
SELECT m.id, CONCAT(m.chave, '.', v.verbo), v.verbo FROM modulos m
CROSS JOIN (SELECT 'ver' AS verbo UNION ALL SELECT 'criar' UNION ALL SELECT 'editar' UNION ALL SELECT 'excluir' UNION ALL SELECT 'exportar') v
WHERE m.chave = 'destino-textos';
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p JOIN permissoes pe ON pe.chave LIKE 'destino-textos.%'
WHERE p.chave IN ('plataforma_admin', 'gestor', 'consultor')
   OR (p.chave = 'coordenador' AND pe.verbo <> 'excluir')
   OR (p.chave IN ('plataforma_suporte', 'pesquisador', 'leitor') AND pe.verbo = 'ver');

-- Tribos é catálogo global, mantido pela plataforma.
INSERT INTO modulos (chave, nome, icone, grupo, grupo_ordem, ordem, no_menu, apenas_softsul, contexto, ativo)
VALUES ('tribos', 'Tribos de consumo', 'etiqueta', 'Plataforma', 9, 3, 1, 1, 'plataforma', 1);
INSERT INTO permissoes (modulo_id, chave, verbo)
SELECT m.id, CONCAT(m.chave, '.', v.verbo), v.verbo FROM modulos m
CROSS JOIN (SELECT 'ver' AS verbo UNION ALL SELECT 'criar' UNION ALL SELECT 'editar' UNION ALL SELECT 'excluir' UNION ALL SELECT 'exportar') v
WHERE m.chave = 'tribos';
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p JOIN permissoes pe ON pe.chave LIKE 'tribos.%'
WHERE p.chave = 'plataforma_admin' OR (p.chave = 'plataforma_suporte' AND pe.verbo = 'ver');
