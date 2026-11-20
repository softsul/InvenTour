-- InvenTour: base para a ficha completa do MTur 2011.
-- Softsul Software, 20/11/2026.
--
-- A revisão das 54 fichas oficiais (docs/revisao-fichas-2011.md) mostrou
-- itens que se repetem em quase toda ficha e que o núcleo não tinha:
-- CNAE e atividade econômica, tipo de organização, distrito, ramal,
-- funcionários temporários e com deficiência, o "Outra" da natureza, a
-- data completa do início da atividade e a pergunta "possui facilidade para
-- pessoas com deficiência". Eles entram como colunas do núcleo.
--
-- Nos campos específicos: o tipo data, a configuração do campo (config:
-- "outros" abre um texto para especificar o "Outros ____", "maximo" limita
-- quantas opções marcar, "colunas" dá tipo às colunas da tabela e "linhas"
-- sugere as linhas fixas da ficha) e, no valor, o texto do "Outros".

ALTER TABLE inventario_itens
    ADD COLUMN cnae                    VARCHAR(20)  NULL COMMENT 'código CNAE' AFTER cnpj,
    ADD COLUMN atividade_economica     VARCHAR(255) NULL COMMENT 'descrição da atividade econômica' AFTER cnae,
    ADD COLUMN natureza_outra          VARCHAR(120) NULL COMMENT 'quando a natureza é outra' AFTER natureza,
    ADD COLUMN tipo_organizacao        ENUM('associacao','sindicato','cooperativa','sistema_s','empresa','outros') NULL AFTER natureza_outra,
    ADD COLUMN tipo_organizacao_outro  VARCHAR(120) NULL AFTER tipo_organizacao,
    ADD COLUMN inicio_atividade_data   DATE NULL COMMENT 'quando se sabe o dia; inicio_atividade guarda o ano' AFTER inicio_atividade,
    ADD COLUMN funcionarios_temporarios SMALLINT UNSIGNED NULL AFTER funcionarios,
    ADD COLUMN funcionarios_pcd        DECIMAL(5,2) NULL COMMENT 'pessoas com deficiência, em %' AFTER funcionarios_temporarios,
    ADD COLUMN distrito                VARCHAR(120) NULL AFTER bairro,
    ADD COLUMN ramal                   VARCHAR(10)  NULL AFTER telefone,
    ADD COLUMN acessibilidade_possui   TINYINT(1)   NULL COMMENT 'possui facilidade para pessoas com deficiência (seção 6)' AFTER conservacao;

ALTER TABLE inventario_atributos
    MODIFY COLUMN tipo ENUM('texto','texto_longo','numero','sim_nao','escolha','multipla','tabela','data') NOT NULL,
    ADD COLUMN config JSON NULL COMMENT 'outros, maximo, colunas tipadas, linhas sugeridas' AFTER opcoes_inativas;

ALTER TABLE inventario_item_atributos
    ADD COLUMN valor_outro VARCHAR(255) NULL COMMENT 'o texto do Outros ____' AFTER valor_numero;
