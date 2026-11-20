-- InvenTour, etapa 1: tipo e modo de operação do destino, consultor
-- responsável, governança (órgão de turismo, COMTUR, FUMTUR, custódia) e os
-- papéis consultor e coordenador.
-- Softsul Software, 20/11/2026.
--
-- Modo de operação (docs/PLANEJAMENTO.md, 2.2):
--   projeto    consultoria gratuita do turismólogo; portal fechado, o QR do
--              caderno abre a prévia bloqueada
--   avaliacao  prévia de 30 dias para a prefeitura ou o COMTUR (avaliacao_ate)
--   ativo      licenciado; portal aberto, trade e EventCity ligados
--   suspenso   sem licença; portal mostra aviso, nada é apagado
-- Só um destino ativo por município (conferido na tela).

UPDATE empresas SET tipo = 'municipio' WHERE tipo = 'corporativo';

ALTER TABLE empresas
    MODIFY tipo ENUM('municipio','regiao','estado') NOT NULL DEFAULT 'municipio'
        COMMENT 'município, região turística (IGR ou consórcio) ou estado',
    ADD COLUMN modo_operacao ENUM('projeto','avaliacao','ativo','suspenso') NOT NULL DEFAULT 'projeto'
        COMMENT 'ciclo comercial: consultoria gratuita, prévia, licenciado, suspenso' AFTER tipo,
    ADD COLUMN avaliacao_ate DATE NULL COMMENT 'fim da prévia de 30 dias (modo avaliacao)' AFTER modo_operacao,
    ADD COLUMN consultor_id INT UNSIGNED NULL COMMENT 'turismólogo responsável pelo projeto de consultoria' AFTER avaliacao_ate,
    ADD KEY ix_empresas_modo (modo_operacao, municipio_id),
    ADD CONSTRAINT fk_empresas_consultor FOREIGN KEY (consultor_id) REFERENCES usuarios (id);

-- Pato Branco é o piloto, licenciado.
UPDATE empresas SET modo_operacao = 'ativo' WHERE slug = 'patobranco';

CREATE TABLE destino_governanca (
    empresa_id            INT UNSIGNED NOT NULL,
    orgao_turismo_nome    VARCHAR(150) NULL COMMENT 'órgão oficial de turismo (OOT): secretaria, departamento, divisão',
    orgao_turismo_lei     VARCHAR(120) NULL COMMENT 'lei que criou o OOT (ficha A1)',
    comtur_nome           VARCHAR(150) NULL COMMENT 'conselho municipal de turismo',
    comtur_lei            VARCHAR(120) NULL COMMENT 'lei de criação do COMTUR (ficha A1)',
    comtur_ativo          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'reúne-se regularmente; vale para o diagnóstico DTI',
    fumtur_lei            VARCHAR(120) NULL COMMENT 'lei do fundo municipal de turismo',
    plano_turismo_lei     VARCHAR(120) NULL COMMENT 'plano ou política municipal de turismo',
    custodia              ENUM('consultoria','prefeitura','comtur','compartilhada') NOT NULL DEFAULT 'consultoria'
                          COMMENT 'com o COMTUR na custódia, exclusão em lote exige o gestor e o presidente do COMTUR',
    presidente_comtur_id  INT UNSIGNED NULL COMMENT 'co-guardião: confirma exclusões e destrava a troca de gestão',
    atualizado_em         DATETIME     NULL,
    atualizado_por        INT UNSIGNED NULL,
    PRIMARY KEY (empresa_id),
    CONSTRAINT fk_governanca_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    CONSTRAINT fk_governanca_presidente FOREIGN KEY (presidente_comtur_id) REFERENCES usuarios (id),
    CONSTRAINT fk_governanca_autor FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='custódia institucional do destino: blindagem contra troca de gestão e período eleitoral';

INSERT INTO destino_governanca (empresa_id, custodia)
SELECT id, IF(modo_operacao = 'ativo', 'compartilhada', 'consultoria') FROM empresas;

-- Registro profissional (bacharel em turismo, conselho, Lattes): vai para a
-- ficha catalográfica do caderno, sem telefone nem e-mail (LGPD).
ALTER TABLE usuario_empresa
    ADD COLUMN registro_profissional VARCHAR(80) NULL COMMENT 'formação e registro que aparecem no caderno' AFTER papel_id;

-- Papéis: o técnico da fundação vira coordenador do inventário, e entra o
-- consultor (dono do projeto de consultoria), com o alcance do gestor.
UPDATE papeis SET chave = 'coordenador', nome = 'Coordenador',
       descricao = 'Coordenador do inventário: confere e homologa as fichas, modera as revisões do trade.'
 WHERE chave = 'tecnico';
INSERT INTO papeis (chave, nome, escopo, descricao, ordem) VALUES
('consultor', 'Consultor', 'destino', 'Turismólogo ou consultoria responsável pelo projeto de inventário: tudo no projeto, inclusive gerar o caderno.', 10);
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT c.id, pp.permissao_id FROM papeis c
JOIN papeis g ON g.chave = 'gestor'
JOIN papel_permissao pp ON pp.papel_id = g.id
WHERE c.chave = 'consultor';

-- O portal lê o modo para abrir ou fechar. O GRANT das colunas novas fica
-- em docs/servidor/mysql-usuario-portal.sql e é aplicado como root (o
-- usuário da aplicação não concede permissões).
