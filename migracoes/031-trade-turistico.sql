-- InvenTour, etapa 6: trade turístico.
-- Softsul Software, 21/11/2026.
--
-- Só em destino com modo_operacao = 'ativo' (o licenciado). O dono, sócio
-- ou gerente de um lugar do inventário pede, pela ficha pública do portal,
-- para responder por ele: informa CNPJ ou Cadastur, anexa o comprovante e
-- aceita o termo. A equipe do destino aprova (em um clique quando o
-- Cadastur confere), rejeita com motivo ou, quando o lugar já tem
-- responsável, transfere a titularidade: o anterior fica revogado, o
-- histórico fica.
--
-- Aprovado, o responsável entra no painel com o papel prestador e só vê
-- "Meus lugares". O que ele muda (contato, horário, pagamento, idiomas,
-- capacidade, texto para o turista, tribos e fotos) não vai direto para a
-- ficha: vira uma revisão com o antes e o depois, que a equipe aprova,
-- devolve com nota ou rejeita. O portal continua mostrando o homologado.

-- ------------------------------------------------------------- MÓDULOS
UPDATE modulos SET ordem = ordem + 1 WHERE grupo = 'Destino' AND ordem >= 3;
UPDATE modulos SET ordem = ordem + 1 WHERE grupo = 'Geral' AND ordem >= 2;

INSERT INTO modulos (chave, nome, icone, grupo, grupo_ordem, ordem, no_menu, apenas_softsul, contexto, ativo) VALUES
('trade', 'Trade turístico', 'negocios', 'Destino', 3, 3, 1, 0, 'destino', 1),
('meus-lugares', 'Meus lugares', 'predio', 'Geral', 1, 2, 1, 0, 'destino', 1);

INSERT INTO permissoes (modulo_id, chave, verbo)
SELECT m.id, CONCAT(m.chave, '.', v.verbo), v.verbo
FROM modulos m
CROSS JOIN (SELECT 'ver' AS verbo UNION ALL SELECT 'criar' UNION ALL SELECT 'editar' UNION ALL SELECT 'excluir' UNION ALL SELECT 'exportar') v
WHERE m.chave IN ('trade', 'meus-lugares');

-- Quem homologa a ficha é quem confere o trade (gestor, consultor,
-- coordenador e a plataforma), com os mesmos verbos.
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT pp.papel_id, nova.id
FROM papel_permissao pp
JOIN permissoes h ON h.id = pp.permissao_id AND h.chave LIKE 'homologacao.%'
JOIN permissoes nova ON nova.chave = CONCAT('trade.', h.verbo);

-- O prestador vê e atualiza os próprios lugares (a revisão espera a equipe).
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p JOIN permissoes pe ON pe.chave IN ('meus-lugares.ver', 'meus-lugares.editar')
WHERE p.chave = 'prestador';

-- ------------------------------------------------------------- PEDIDOS
CREATE TABLE inventario_reivindicacoes (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id        INT UNSIGNED NOT NULL,
    item_id           INT UNSIGNED NOT NULL,
    usuario_id        INT UNSIGNED NULL COMMENT 'a conta do responsável, ligada na aprovação',
    nome              VARCHAR(120) NOT NULL COMMENT 'quem pede (vai para a conta, nunca para a ficha)',
    email             VARCHAR(150) NOT NULL,
    whatsapp          VARCHAR(20)  NULL,
    vinculo           ENUM('proprietario','socio','gerente','procurador','outro') NOT NULL,
    cnpj              CHAR(14)     NULL,
    cadastur          VARCHAR(30)  NULL,
    conferencia       JSON         NULL COMMENT 'o que confere: cnpj_ficha, cadastur_base, contato_base',
    comprovante       VARCHAR(80)  NULL COMMENT 'arquivo em storage/trade/<destino>/, fora do DocumentRoot',
    comprovante_tipo  VARCHAR(40)  NULL,
    termo_versao      VARCHAR(20)  NOT NULL,
    aceite_em         DATETIME     NOT NULL,
    aceite_ip         VARCHAR(45)  NULL,
    situacao          ENUM('pendente','aprovada','rejeitada','revogada') NOT NULL DEFAULT 'pendente',
    motivo            VARCHAR(500) NULL COMMENT 'da rejeição ou da revogação',
    analisado_por     INT UNSIGNED NULL,
    analisado_em      DATETIME     NULL,
    revogado_por      INT UNSIGNED NULL,
    revogado_em       DATETIME     NULL,
    criado_em         DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_reiv_destino (empresa_id, situacao, criado_em),
    KEY ix_reiv_item (item_id, situacao),
    KEY ix_reiv_usuario (usuario_id, situacao),
    CONSTRAINT fk_reiv_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    CONSTRAINT fk_reiv_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id),
    CONSTRAINT fk_reiv_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_reiv_analisou FOREIGN KEY (analisado_por) REFERENCES usuarios (id),
    CONSTRAINT fk_reiv_revogou FOREIGN KEY (revogado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='quem responde por um lugar do inventário; só uma aprovada por item';

-- ------------------------------------------------------------- REVISÕES
CREATE TABLE inventario_revisoes (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id        INT UNSIGNED NOT NULL,
    item_id           INT UNSIGNED NOT NULL,
    usuario_id        INT UNSIGNED NOT NULL COMMENT 'o responsável que propôs',
    origem            ENUM('trade','radar') NOT NULL DEFAULT 'trade',
    alteracoes        JSON         NOT NULL COMMENT '{campo: {antes, depois}} só com o que mudou',
    fotos             JSON         NULL COMMENT 'fotos novas, guardadas em storage/trade/<destino>/ até a aprovação',
    situacao          ENUM('aguardando','aprovada','devolvida','rejeitada') NOT NULL DEFAULT 'aguardando',
    nota              VARCHAR(1000) NULL COMMENT 'da equipe, na devolução ou na rejeição',
    moderado_por      INT UNSIGNED NULL,
    moderado_em       DATETIME     NULL,
    criado_em         DATETIME     NOT NULL,
    atualizado_em     DATETIME     NULL,
    PRIMARY KEY (id),
    KEY ix_rev_destino (empresa_id, situacao, criado_em),
    KEY ix_rev_item (item_id, situacao),
    CONSTRAINT fk_rev_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    CONSTRAINT fk_rev_item FOREIGN KEY (item_id) REFERENCES inventario_itens (id),
    CONSTRAINT fk_rev_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_rev_moderou FOREIGN KEY (moderado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='o que o responsável quer mudar na ficha, à espera da equipe; o portal segue com o homologado';
