-- InvenTour, etapa 1: taxonomia do Inventário da Oferta Turística (MTur).
-- Softsul Software, 20/11/2026.
--
-- As 54 fichas de 2011 não viram 54 tabelas (docs/PLANEJAMENTO.md, 2.4):
-- aqui ficam só o catálogo das fichas e dos tipos e subtipos de cada uma,
-- global e sem destino. O conteúdo vem na migração 006, gerada de
-- docs/especificacao/fichas-2011.json por scripts/gerar_seed_fichas.php.

CREATE TABLE inventario_fichas (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem         SMALLINT UNSIGNED NOT NULL COMMENT 'posição na capitulação oficial (1 a 54)',
    codigo        VARCHAR(4)   NOT NULL COMMENT 'código curto: A1 a A7, B1 a B7, C1 a C5 (várias fichas dividem o mesmo)',
    codigos_mtur  VARCHAR(160) NOT NULL COMMENT 'códigos finos cobertos, separados por vírgula: A.2.1.2, A.2.1.3',
    categoria     ENUM('A','B','C') NOT NULL COMMENT 'A infraestrutura de apoio, B serviços e equipamentos, C atrativos',
    grupo         VARCHAR(120) NOT NULL COMMENT 'nome do código curto: Meios de hospedagem',
    nome          VARCHAR(160) NOT NULL COMMENT 'nome da ficha',
    forma         ENUM('municipio','pontual','linear') NOT NULL DEFAULT 'pontual'
                  COMMENT 'municipio: ficha do município todo; linear: rodovia, ferrovia, hidrovia (extensão, sem endereço)',
    eventcity     ENUM('atrativo','hospedagem','gastronomia','espaco_eventos','servico_apoio') NULL
                  COMMENT 'tipo enviado ao EventCity na API v1; nulo não vai',
    pagina_2011   SMALLINT UNSIGNED NULL COMMENT 'página inicial no PDF oficial de 2011',
    ajuda         TEXT         NULL COMMENT 'orientação de preenchimento mostrada ao pesquisador',
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fichas_ordem (ordem),
    KEY ix_fichas_codigo (categoria, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='as 54 fichas do Inventário da Oferta Turística (MTur, 2011)';

CREATE TABLE inventario_tipos (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ficha_id     INT UNSIGNED NOT NULL,
    codigo_mtur  VARCHAR(20)  NULL COMMENT 'código fino quando a ficha mostra: B.1.1.1',
    nome         VARCHAR(160) NOT NULL COMMENT 'hotel, pousada, cachoeira, museu...',
    definicao    TEXT         NULL COMMENT 'definição do manual de 2006, vira ajuda na tela',
    ordem        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ativo        TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tipos_ficha_nome (ficha_id, nome),
    CONSTRAINT fk_tipos_ficha FOREIGN KEY (ficha_id) REFERENCES inventario_fichas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='tipos e subtipos de cada ficha (o campo Tipo da seção de identificação)';

-- O módulo da plataforma que mantém o catálogo passa a ser "Fichas e tipos"
-- (tela public/fichas.php), ligado.
UPDATE modulos SET chave = 'fichas', nome = 'Fichas e tipos', icone = 'manual', ativo = 1 WHERE chave = 'catalogos';
UPDATE permissoes SET chave = REPLACE(chave, 'catalogos.', 'fichas.') WHERE chave LIKE 'catalogos.%';
