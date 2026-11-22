-- InvenTour: métricas do portal sem rastrear o visitante, como no EventCity
-- (migração 013 de lá). Contadores diários incrementados pelo portal
-- (/m/<ação>/<lugar>, portal/m.php), sem cookie, sem IP e sem nada que
-- identifique quem visitou: não precisa do aviso de cookies (LGPD).
-- Softsul Software, 22/11/2026.
--
-- O portal grava com o usuário inventour_portal: os GRANTs estão em
-- docs/servidor/mysql-usuario-portal.sql e são aplicados como root.

CREATE TABLE metricas_lugares (
    item_id             INT UNSIGNED NOT NULL,
    dia                 DATE NOT NULL,
    empresa_id          INT UNSIGNED NOT NULL,
    visualizacoes       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'abriu a ficha do lugar',
    cliques_rota        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Como chegar',
    cliques_whatsapp    INT UNSIGNED NOT NULL DEFAULT 0,
    cliques_ligar       INT UNSIGNED NOT NULL DEFAULT 0,
    cliques_site        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'site ou Instagram do lugar',
    guardados           INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'guardou no meu roteiro',
    PRIMARY KEY (item_id, dia),
    KEY ix_metricas_lugares_empresa (empresa_id, dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Páginas vistas no portal do destino por dia (todas as páginas).
CREATE TABLE metricas_portal (
    empresa_id  INT UNSIGNED NOT NULL,
    dia         DATE NOT NULL,
    paginas     INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (empresa_id, dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
