-- InvenTour: números de busca dos portais, no molde do EventCity
-- (migrações 020 e 050 de lá). Softsul Software, 22/11/2026.
--
-- O job puxar_busca lê o Google Search Console (propriedade de domínio
-- sc-domain:inventour.com.br, que cobre todos os portais) e o puxar_bing
-- lê o Bing Webmaster; as telas leem daqui, sem esperar a API. Só os
-- portais abertos (licença ativa ou avaliação no prazo) entram.

CREATE TABLE busca_diaria (
    empresa_id  INT UNSIGNED NOT NULL,
    dia         DATE NOT NULL,
    cliques     INT UNSIGNED NOT NULL DEFAULT 0,
    impressoes  INT UNSIGNED NOT NULL DEFAULT 0,
    posicao     DECIMAL(6,2) NULL COMMENT 'posição média do dia',
    PRIMARY KEY (empresa_id, dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Google Search Console por portal e dia';

CREATE TABLE busca_termos (
    empresa_id  INT UNSIGNED NOT NULL,
    tipo        ENUM('termo','pagina') NOT NULL,
    chave       VARCHAR(500) NOT NULL,
    cliques     INT UNSIGNED NOT NULL DEFAULT 0,
    impressoes  INT UNSIGNED NOT NULL DEFAULT 0,
    posicao     DECIMAL(6,2) NULL,
    KEY ix_busca_termos (empresa_id, tipo, cliques)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='termos e páginas do Google nos últimos 28 dias';

CREATE TABLE bing_diaria (
    empresa_id  INT UNSIGNED NOT NULL,
    dia         DATE NOT NULL,
    cliques     INT UNSIGNED NOT NULL DEFAULT 0,
    impressoes  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (empresa_id, dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bing Webmaster por portal e dia';

CREATE TABLE bing_termos (
    empresa_id  INT UNSIGNED NOT NULL,
    tipo        ENUM('termo','pagina') NOT NULL,
    chave       VARCHAR(500) NOT NULL,
    cliques     INT UNSIGNED NOT NULL DEFAULT 0,
    impressoes  INT UNSIGNED NOT NULL DEFAULT 0,
    posicao     DECIMAL(6,2) NULL,
    KEY ix_bing_termos (empresa_id, tipo, cliques)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='termos e páginas do Bing nos últimos 28 dias';

CREATE TABLE bing_sites (
    empresa_id   INT UNSIGNED NOT NULL PRIMARY KEY,
    url          VARCHAR(190) NOT NULL,
    verificado   TINYINT(1) NOT NULL DEFAULT 0,
    sitemap      TINYINT(1) NOT NULL DEFAULT 0,
    conferido_em DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='cada portal no Bing: cadastrado, verificado, sitemap enviado';
