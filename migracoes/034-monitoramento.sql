-- InvenTour: grupo Monitoramento no menu, igual ao EventCity (migrações
-- 050, 051 e 053 de lá). Softsul Software, 22/11/2026.
--
-- Visitas (Google Analytics) sai de Gestão e vai para Monitoramento, ao
-- lado dos Acessos do servidor: os logs do Apache resumidos em contadores
-- pelo leitor scripts/ler_logs_apache.php (timer inventour-logs, a cada 10
-- minutos). Nenhum IP é gravado: o visitante único entra só como um resumo
-- cifrado do dia (o sal muda todo dia), apagado depois de 35 dias.

UPDATE modulos SET grupo = 'Monitoramento', grupo_ordem = 10, ordem = 1 WHERE chave = 'visitas';

INSERT INTO modulos (chave, nome, icone, grupo, grupo_ordem, ordem, no_menu, apenas_softsul, contexto, ativo)
VALUES ('acessos', 'Acessos do servidor', 'painel', 'Monitoramento', 10, 2, 1, 1, 'plataforma', 1);

INSERT INTO permissoes (modulo_id, chave, verbo)
SELECT m.id, 'acessos.ver', 'ver' FROM modulos m WHERE m.chave = 'acessos';

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p JOIN permissoes pe
WHERE p.chave IN ('plataforma_admin', 'plataforma_suporte') AND pe.chave = 'acessos.ver';

-- Contadores por hora: site (portal, painel), destino (0 quando o host não
-- é de um destino), quem pediu (pessoa ou o nome do robô) e a resposta:
-- 200, 300, 400, 500 para a classe inteira, e 404 e 429 à parte.
CREATE TABLE acessos_hora (
    dia         DATE NOT NULL,
    hora        TINYINT UNSIGNED NOT NULL,
    site        VARCHAR(16) NOT NULL,
    empresa_id  INT UNSIGNED NOT NULL DEFAULT 0,
    grupo       ENUM('pessoa','busca','ia','social','outro') NOT NULL,
    agente      VARCHAR(40) NOT NULL,
    classe      SMALLINT UNSIGNED NOT NULL,
    pedidos     INT UNSIGNED NOT NULL DEFAULT 0,
    paginas     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'pedidos que não são arquivo (css, js, imagem, mídia, mapa)',
    bytes       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (dia, hora, site, empresa_id, grupo, agente, classe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Caminhos por dia, sem a query string: as páginas e, de arquivo, só os
-- que deram erro.
CREATE TABLE acessos_caminhos (
    dia         DATE NOT NULL,
    site        VARCHAR(16) NOT NULL,
    empresa_id  INT UNSIGNED NOT NULL DEFAULT 0,
    grupo       ENUM('pessoa','busca','ia','social','outro') NOT NULL,
    classe      SMALLINT UNSIGNED NOT NULL,
    caminho     VARCHAR(255) NOT NULL,
    pedidos     INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (dia, site, empresa_id, grupo, classe, caminho)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visitantes únicos (pessoas) por dia: um resumo do IP com sal do dia.
CREATE TABLE acessos_visitantes (
    dia         DATE NOT NULL,
    site        VARCHAR(16) NOT NULL,
    empresa_id  INT UNSIGNED NOT NULL DEFAULT 0,
    marca       BINARY(8) NOT NULL,
    PRIMARY KEY (dia, site, empresa_id, marca)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Onde a leitura parou em cada arquivo de log (o inode muda no rodízio).
CREATE TABLE acessos_leitura (
    arquivo     VARCHAR(190) NOT NULL PRIMARY KEY,
    inode       BIGINT UNSIGNED NOT NULL,
    posicao     BIGINT UNSIGNED NOT NULL,
    lido_em     DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
