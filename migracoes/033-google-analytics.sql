-- InvenTour: Google Analytics 4 dos portais, no molde do EventCity
-- (migrações 052 e 053 de lá). Softsul Software, 22/11/2026.
--
-- Uma propriedade só para a plataforma; cada destino sai do filtro pelo
-- host do portal (hostName). O job puxar_analytics lê a Data API uma vez
-- por dia; a tela Visitas lê na hora, com cache de uma hora. O script do
-- Google só carrega no portal depois que o visitante aceita os cookies.

CREATE TABLE analytics_diaria (
    empresa_id    INT UNSIGNED NOT NULL,
    dia           DATE NOT NULL,
    usuarios      INT UNSIGNED NOT NULL DEFAULT 0,
    sessoes       INT UNSIGNED NOT NULL DEFAULT 0,
    visualizacoes INT UNSIGNED NOT NULL DEFAULT 0,
    engajadas     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'sessões engajadas (10 s, conversão ou 2 páginas)',
    engajamento   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'segundos de engajamento somados',
    PRIMARY KEY (empresa_id, dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Os 28 últimos dias por canal, origem, página, cidade e aparelho,
-- trocados inteiros a cada dia. tipo 'total' guarda os usuários únicos do
-- período (a soma dos dias conta a mesma pessoa várias vezes).
CREATE TABLE analytics_partes (
    empresa_id    INT UNSIGNED NOT NULL,
    tipo          ENUM('total','canal','origem','pagina','cidade','aparelho') NOT NULL,
    chave         VARCHAR(500) NOT NULL,
    usuarios      INT UNSIGNED NOT NULL DEFAULT 0,
    sessoes       INT UNSIGNED NOT NULL DEFAULT 0,
    visualizacoes INT UNSIGNED NOT NULL DEFAULT 0,
    KEY ix_analytics_partes (empresa_id, tipo, sessoes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visitas no menu (grupo Gestão): na plataforma, todos os portais; dentro
-- de um destino, só o portal dele. O prestador e o pesquisador não veem.
INSERT INTO modulos (chave, nome, icone, grupo, grupo_ordem, ordem, no_menu, apenas_softsul, contexto, ativo)
VALUES ('visitas', 'Visitas', 'olho', 'Gestão', 5, 2, 1, 0, 'ambos', 1);

INSERT INTO permissoes (modulo_id, chave, verbo)
SELECT m.id, 'visitas.ver', 'ver' FROM modulos m WHERE m.chave = 'visitas';

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p JOIN permissoes pe
WHERE p.chave IN ('plataforma_admin', 'plataforma_suporte', 'gestor', 'consultor', 'coordenador', 'leitor') AND pe.chave = 'visitas.ver';
