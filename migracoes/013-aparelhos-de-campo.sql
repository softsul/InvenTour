-- InvenTour: aparelhos do aplicativo InvenTour Campo.
-- Softsul Software, 20/11/2026.
--
-- O app de campo funciona sem sinal e não carrega o painel: fala só com a
-- API da coleta. Para não pedir senha e código do autenticador num celular
-- no meio da estrada, a pessoa conecta o aparelho com um código curto gerado
-- no painel (Coleta de campo, já logada com os dois fatores). O app troca o
-- código por um token do aparelho, guardado só nele; aqui fica o hash.
-- O token vale para uma pessoa num destino, cai se a pessoa trocar a senha
-- ou perder o acesso (versao_sessao) e pode ser desligado no painel.
CREATE TABLE campo_aparelhos (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id     INT UNSIGNED NOT NULL,
    empresa_id     INT UNSIGNED NOT NULL,
    nome           VARCHAR(80)  NULL COMMENT 'o que o app informa (modelo do aparelho)',
    codigo_hash    CHAR(64)     NULL COMMENT 'sha256 do código de conexão, até ser usado',
    codigo_expira  DATETIME     NULL,
    token_hash     CHAR(64)     NULL COMMENT 'sha256 do token do aparelho',
    versao_sessao  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'a do usuário na conexão: mudou, o token cai',
    criado_em      DATETIME     NOT NULL,
    conectado_em   DATETIME     NULL,
    ultimo_uso_em  DATETIME     NULL,
    revogado_em    DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_aparelhos_token (token_hash),
    UNIQUE KEY uk_aparelhos_codigo (codigo_hash),
    KEY ix_aparelhos_usuario (usuario_id, empresa_id),
    CONSTRAINT fk_aparelhos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_aparelhos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='aparelhos conectados ao aplicativo InvenTour Campo';

-- Coordenada e precisão do GPS do aparelho na hora da foto (o app manda
-- junto; o EXIF da câmera nativa nem sempre traz).
ALTER TABLE inventario_midias
    ADD COLUMN precisao_m SMALLINT UNSIGNED NULL COMMENT 'precisão do GPS em metros, quando veio do aparelho' AFTER longitude;
