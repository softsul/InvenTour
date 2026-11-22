-- InvenTour, etapa 7: diagnóstico DTI (PLANEJAMENTO 2.9), começando pela
-- metodologia oficial do Ministério do Turismo: DTI Brasil 2022, o Manual
-- Metodológico com os 105 requisitos do Referencial Técnico (capítulos 4 a
-- 10, com os 9 eixos do 8.2) e a avaliação de conformidade do Anexo 1:
--   1 requisito não conceituado, 2 não conformidade, 3 observação,
--   4 conformidade estrita, 5 conformidade plena; distinção com média 3 ou
--   mais, desde que haja esforço comprovado de ações no nível 4.
-- A Matriz do Anexo 2 vira a resposta de cada requisito: situação atual
-- (a evidência, com fonte e data) e as ações para alcançar ou melhorar a
-- conformidade, que formam o Plano de Transformação.
--
-- Catálogo de metodologias: a do Sebrae (2022, 7 dimensões com pesos) entra
-- depois como outra linha de dti_metodologias, sem mudar as tabelas.
-- Cada diagnóstico é um ciclo do destino; fechado, não muda mais (a nota e
-- as médias por eixo ficam gravadas), e o próximo ciclo pode partir dele.
-- Softsul Software, 22/11/2026.

-- ------------------------------------------------------------- MÓDULO
INSERT INTO modulos (chave, nome, icone, grupo, grupo_ordem, ordem, no_menu, apenas_softsul, contexto, ativo) VALUES
('diagnostico', 'Diagnóstico DTI', 'emblema', 'Gestão', 5, 2, 1, 0, 'destino', 1);
INSERT INTO permissoes (modulo_id, chave, verbo)
SELECT m.id, CONCAT(m.chave, '.', v.verbo), v.verbo
FROM modulos m
CROSS JOIN (SELECT 'ver' AS verbo UNION ALL SELECT 'criar' UNION ALL SELECT 'editar' UNION ALL SELECT 'excluir' UNION ALL SELECT 'exportar') v
WHERE m.chave = 'diagnostico';
-- Quem tem os relatórios tem o diagnóstico, com os mesmos verbos.
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT pp.papel_id, nova.id
FROM papel_permissao pp
JOIN permissoes r ON r.id = pp.permissao_id AND r.chave LIKE 'relatorios.%'
JOIN permissoes nova ON nova.chave = CONCAT('diagnostico.', r.verbo);

-- ------------------------------------------------------------- CATÁLOGO
CREATE TABLE dti_metodologias (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    chave         VARCHAR(30)   NOT NULL,
    nome          VARCHAR(120)  NOT NULL,
    orgao         VARCHAR(120)  NOT NULL COMMENT 'quem publicou',
    ano           SMALLINT      NOT NULL,
    escala        JSON          NOT NULL COMMENT '{"1": "Requisito não conceituado", ...}',
    nota_minima   DECIMAL(3,1)  NULL COMMENT 'média mínima para a distinção',
    descricao     TEXT          NULL,
    fonte         VARCHAR(255)  NULL COMMENT 'documento de origem',
    ativo         TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_dti_met_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='metodologias de diagnóstico de destino turístico inteligente';

CREATE TABLE dti_requisitos (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    metodologia_id  INT UNSIGNED  NOT NULL,
    numero          SMALLINT      NOT NULL,
    capitulo        VARCHAR(80)   NOT NULL,
    subcapitulo     VARCHAR(120)  NOT NULL,
    eixo            VARCHAR(30)   NULL COMMENT 'só os do 8.2: governanca, inovacao, tecnologia, acessibilidade, sustentabilidade, criatividade, mobilidade, promocao, seguranca',
    subeixo         VARCHAR(80)   NULL,
    texto           TEXT          NOT NULL COMMENT 'o requisito, com os itens a), b), c) em linhas',
    observacao      TEXT          NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_dti_req (metodologia_id, numero),
    CONSTRAINT fk_dti_req_met FOREIGN KEY (metodologia_id) REFERENCES dti_metodologias (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='requisitos de cada metodologia, na ordem do documento';

-- ------------------------------------------------------------- DIAGNÓSTICOS
CREATE TABLE dti_ciclos (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    empresa_id      INT UNSIGNED  NOT NULL,
    metodologia_id  INT UNSIGNED  NOT NULL,
    titulo          VARCHAR(120)  NOT NULL,
    situacao        ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
    nota            DECIMAL(4,2)  NULL COMMENT 'média geral no fechamento',
    resultado       JSON          NULL COMMENT 'médias e contagens por eixo no fechamento',
    origem_id       INT UNSIGNED  NULL COMMENT 'ciclo de onde as respostas vieram',
    criado_em       DATETIME      NOT NULL,
    criado_por      INT UNSIGNED  NULL,
    fechado_em      DATETIME      NULL,
    fechado_por     INT UNSIGNED  NULL,
    PRIMARY KEY (id),
    KEY ix_dti_ciclo_destino (empresa_id, situacao, criado_em),
    CONSTRAINT fk_dti_ciclo_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    CONSTRAINT fk_dti_ciclo_met FOREIGN KEY (metodologia_id) REFERENCES dti_metodologias (id),
    CONSTRAINT fk_dti_ciclo_origem FOREIGN KEY (origem_id) REFERENCES dti_ciclos (id),
    CONSTRAINT fk_dti_ciclo_criou FOREIGN KEY (criado_por) REFERENCES usuarios (id),
    CONSTRAINT fk_dti_ciclo_fechou FOREIGN KEY (fechado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='cada diagnóstico DTI de um destino; fechado, não muda mais';

CREATE TABLE dti_respostas (
    ciclo_id        INT UNSIGNED  NOT NULL,
    requisito_id    INT UNSIGNED  NOT NULL,
    empresa_id      INT UNSIGNED  NOT NULL,
    nivel           TINYINT       NULL COMMENT '1 a 5 na escala da metodologia',
    situacao_atual  TEXT          NULL COMMENT 'a evidência: o que existe hoje',
    fonte           VARCHAR(255)  NULL COMMENT 'lei, documento, link, sistema',
    evidencia_em    DATE          NULL COMMENT 'data da evidência',
    acoes           TEXT          NULL COMMENT 'ações para alcançar ou melhorar a conformidade (Plano de Transformação)',
    responsavel     VARCHAR(120)  NULL,
    prazo           DATE          NULL,
    atualizado_em   DATETIME      NOT NULL,
    atualizado_por  INT UNSIGNED  NULL,
    PRIMARY KEY (ciclo_id, requisito_id),
    KEY ix_dti_resp_destino (empresa_id),
    CONSTRAINT fk_dti_resp_ciclo FOREIGN KEY (ciclo_id) REFERENCES dti_ciclos (id),
    CONSTRAINT fk_dti_resp_req FOREIGN KEY (requisito_id) REFERENCES dti_requisitos (id),
    CONSTRAINT fk_dti_resp_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    CONSTRAINT fk_dti_resp_autor FOREIGN KEY (atualizado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='a resposta de cada requisito num ciclo, com a evidência e o plano';

-- ------------------------------------------------------------- MTur DTI Brasil 2022
INSERT INTO dti_metodologias (chave, nome, orgao, ano, escala, nota_minima, descricao, fonte) VALUES
('mtur2022', 'DTI Brasil 2022', 'Ministério do Turismo', 2022,
 '{"1": "Requisito não conceituado", "2": "Não conformidade", "3": "Observação", "4": "Conformidade estrita", "5": "Conformidade plena"}',
 3.0,
 'Referencial Técnico dos Sistemas de Gestão de Destinos Turísticos Inteligentes: 105 requisitos nos capítulos 4 a 10, com os 9 eixos (governança, inovação, tecnologia, acessibilidade, sustentabilidade, criatividade, mobilidade e transporte, promoção e marketing, segurança). A distinção pede média 3 ou mais, com esforço comprovado de ações no nível 4.',
 'Manual Metodológico DTI Brasil 2022, Anexo 1 (avaliação de conformidade) e Anexo 2 (Matriz de Avaliação de Requisitos para a Elaboração do Plano de Transformação)');
SET @met = LAST_INSERT_ID();

INSERT INTO dti_requisitos (metodologia_id, numero, capitulo, subcapitulo, eixo, subeixo, texto, observacao) VALUES
(@met, 1, 'Capítulo 4. Contexto do destino turístico', '4.1 Delimitação do escopo do sistema de gestão DTI', NULL, NULL, 'O órgão gestor deve determinar e documentar que o alcance territorial, no qual se implanta o modelo, é um destino turístico, com base nos seguintes aspectos:
a) que o destino turístico seja geograficamente delimitado;
b) que tenha organizações que prestam serviços turísticos;
c) que esteja dotado de uma estrutura para a gestão do destino turístico.', NULL),
(@met, 2, 'Capítulo 4. Contexto do destino turístico', '4.1 Delimitação do escopo do sistema de gestão DTI', NULL, NULL, 'O órgão gestor deve determinar os limites e aplicabilidade do sistema de gestão do DTI para estabelecer seu escopo. Para isso, o órgão gestor deve considerar:
a) os limites geográficos onde está implementado o sistema de gestão do DTI;
b) a autoridade e capacidade de exercer controle e influência sobre o DTI;
c) as atividades, produtos e serviços turísticos que possam ter aspectos significativos relacionados à implementação de um DTI.', NULL),
(@met, 3, 'Capítulo 4. Contexto do destino turístico', '4.1 Delimitação do escopo do sistema de gestão DTI', NULL, NULL, 'O escopo deve estar disponível como informação documentada.', NULL),
(@met, 4, 'Capítulo 4. Contexto do destino turístico', '4.2 Compreensão do órgão gestor e do seu contexto no DTI', NULL, NULL, 'O órgão gestor deve determinar as questões externas e internas que são relevantes para o seu propósito e que afetam sua capacidade de alcançar os resultados pretendidos do seu sistema de gestão de DTI, considerando, pelo menos, aspectos relativos a:
a) o mercado turístico;
b) o marco legal e regulatório;
c) os aspectos relativos aos eixos estabelecidos no subcapítulo 8.2.', 'A compreensão do contexto externo pode ser facilitada ao considerar questões decorrentes de ambientes jurídicos, tecnológicos, competitivos, econômicos e do mercado, sejam internacional, nacional, regional ou local. A do contexto interno, ao considerar questões decorrentes dos valores, da cultura, do conhecimento e desempenho do órgão gestor. O órgão gestor deve considerar a análise do contexto do DTI para identificar e gerenciar fatores positivos e negativos.'),
(@met, 5, 'Capítulo 4. Contexto do destino turístico', '4.3 Compreensão das necessidades e expectativas das partes interessadas', NULL, NULL, 'O órgão gestor deve determinar e documentar:
a) as partes interessadas que são relevantes para o sistema de gestão do DTI;
b) os requisitos pertinentes às partes interessadas identificadas, considerando os dados e informações que eles demandem.', 'Exemplos de partes interessadas: autoridades locais, prefeitura, vereadores; órgãos oficiais de turismo; cidadãos; turistas; prestadores de serviços turísticos e organizações de turismo; organizações da sociedade civil; organizações de fomento; operadores de transporte público e privado; organismos de infraestrutura rodoviária e de conectividade; órgãos de segurança pública; organismos com competência laboral, sanitária, cultural, ambiental e de inovação; organizações externas ao destino, como operadoras, governos estaduais e federal.'),
(@met, 6, 'Capítulo 4. Contexto do destino turístico', '4.4 Diagnóstico de situação do DTI', NULL, NULL, 'O órgão gestor deve realizar um diagnóstico de situação documentado do DTI em relação aos aspectos identificados e determinados nos pontos 4.1, 4.2 e 4.3, e com os eixos identificados no ponto 8.2.', NULL),
(@met, 7, 'Capítulo 4. Contexto do destino turístico', '4.5 Sistema de gestão do DTI e seus processos', NULL, NULL, 'O órgão gestor deve determinar os processos necessários para o sistema de gestão do DTI, considerando:
a) as entradas necessárias e as saídas esperadas desses processos;
b) a sequência e interação desses processos.', NULL),
(@met, 8, 'Capítulo 5. Liderança', '5.1 Política do DTI', NULL, NULL, 'As mais altas autoridades do órgão gestor devem estabelecer, implementar, manter e rever uma política de gestão do DTI que:
a) seja adequada ao contexto do DTI;
b) considere cada um dos eixos na seção 8.2;
c) forneça um marco de referência para o estabelecimento dos objetivos do DTI;
d) inclua o compromisso de cumprir os requisitos aplicáveis;
e) inclua o compromisso de fornecer os recursos necessários para atender aos objetivos;
f) inclua o compromisso de melhoria contínua do sistema de gestão do DTI.', NULL),
(@met, 9, 'Capítulo 5. Liderança', '5.1 Política do DTI', NULL, NULL, 'A política de DTI deve estar disponível como informação documentada, e comunicar-se com as partes interessadas conforme aplicável.', NULL),
(@met, 10, 'Capítulo 5. Liderança', '5.2 Funções, responsabilidades e autoridades do órgão gestor', NULL, NULL, 'O órgão gestor deve determinar, atribuir, documentar e comunicar as responsabilidades e autoridades para funções relevantes para a gestão do DTI.', 'A atribuição pode incluir: garantir que o sistema de gestão do DTI esteja em conformidade com os requisitos; coordenar a sua implementação; assegurar que os processos geram os resultados pretendidos; reportar às mais altas autoridades o desempenho e as oportunidades de melhoria.'),
(@met, 11, 'Capítulo 6. Planejamento', '6.1 Ações para tratar riscos e oportunidades', NULL, NULL, 'Ao planejar o sistema de gestão do DTI, o órgão gestor deve considerar as questões referidas no item 4.2 e os requisitos mencionados no item 4.3, e determinar os riscos e oportunidades que podem afetar a gestão do DTI para efeitos de:
a) garantir a realização dos resultados esperados;
b) aumentar os efeitos desejáveis e aproveitar as oportunidades;
c) prevenir ou reduzir efeitos indesejados;
d) alcançar a melhoria contínua.', NULL),
(@met, 12, 'Capítulo 6. Planejamento', '6.1 Ações para tratar riscos e oportunidades', NULL, NULL, 'O órgão gestor deve planejar e documentar:
a) as ações para lidar com riscos e oportunidades;
b) a forma de integrar e implementar as ações nos processos do sistema de gestão do DTI;
c) a forma de avaliar a eficácia das ações.', 'As opções para lidar com os riscos podem incluir evitar, aceitar para buscar oportunidades, eliminar a origem, alterar a probabilidade ou as consequências, compartilhar ou reter riscos por decisões informadas. As oportunidades podem levar a novas práticas, produtos, atrativos, perfis de turistas, parcerias e tecnologias.'),
(@met, 13, 'Capítulo 6. Planejamento', '6.2.1 Objetivos de um DTI', NULL, NULL, 'O órgão gestor deve definir os objetivos do DTI em funções e níveis relevantes, atribuindo-lhes responsabilidades. Objetivos vinculados a cada um dos eixos de um DTI devem ser determinados de acordo com a seção 8.2.', NULL),
(@met, 14, 'Capítulo 6. Planejamento', '6.2.1 Objetivos de um DTI', NULL, NULL, 'Os objetivos do DTI devem:
a) ser consistentes com a política do DTI;
b) ser mensuráveis e limitados no tempo;
c) levar em conta os requisitos aplicáveis;
d) ser rastreados;
e) comunicar e atualizar-se, conforme apropriado.', NULL),
(@met, 15, 'Capítulo 6. Planejamento', '6.2.1 Objetivos de um DTI', NULL, NULL, 'O órgão gestor deve manter informações documentadas sobre os objetivos do DTI.', NULL),
(@met, 16, 'Capítulo 6. Planejamento', '6.2.2 Plano para alcançar os objetivos do DTI', NULL, NULL, 'O órgão gestor deve planejar ações que atendam às metas estabelecidas. O órgão gestor deve determinar para cada ação contida no plano:
a) iniciativas a serem tomadas;
b) forma específica em que serão realizados;
c) recursos que serão necessários;
d) responsabilidades e autoridades para sua realização;
e) datas e prazos para execução;
f) método e frequência de monitoramento.', NULL),
(@met, 17, 'Capítulo 7. Apoio', '7.1 Recursos', NULL, NULL, 'O órgão gestor deve determinar e fornecer os recursos necessários para o estabelecimento, implementação, manutenção e melhoria contínua do sistema de gestão do DTI.', NULL),
(@met, 18, 'Capítulo 7. Apoio', '7.1.1 Pessoas', NULL, NULL, 'A organização deve determinar e fornecer as pessoas necessárias para uma implementação eficaz do seu sistema de gestão do DTI e pela operação e controle de seus processos.', NULL),
(@met, 19, 'Capítulo 7. Apoio', '7.1.2 Infraestrutura', NULL, NULL, 'O órgão gestor deve determinar, fornecer e manter a infraestrutura necessária para a operação de seus processos e dos sistemas de gestão do DTI.', 'A infraestrutura pode incluir edifícios e serviços associados; equipamentos, incluindo hardware e software; recursos de transporte; TIC.'),
(@met, 20, 'Capítulo 7. Apoio', '7.1.3 Conhecimentos do órgão gestor', NULL, NULL, 'O órgão gestor deve determinar e manter o conhecimento necessário para atingir os objetivos do DTI, o funcionamento de seus processos e a conformidade dos requisitos.', 'São conhecimentos específicos que a organização adquire geralmente com a experiência, de fontes internas (lições aprendidas, resultados de melhorias) e externas (normas, academia, conferências, artigos técnicos, partes interessadas).'),
(@met, 21, 'Capítulo 7. Apoio', '7.1.3 Conhecimentos do órgão gestor', NULL, NULL, 'Estes conhecimentos devem ser colocados à disposição das partes interessadas sempre que possível.', NULL),
(@met, 22, 'Capítulo 7. Apoio', '7.2 Competências', NULL, NULL, 'O órgão gestor deve:
a) determinar a competência necessária das pessoas que realizam, nas áreas e níveis relevantes, um trabalho que afeta o desempenho do DTI. Para isso, devem ser considerados os eixos de 8.2;
b) garantir a competência dos indivíduos para cumprir suas responsabilidades, com base na educação, treinamento ou experiência adequadas;
c) treinar funcionários para adquirir a competência necessária e avaliar a eficácia das ações tomadas;
d) manter informações documentadas apropriadas como uma evidência da sua competência.', NULL),
(@met, 23, 'Capítulo 7. Apoio', '7.3 Consciência', NULL, NULL, 'O órgão gestor deve garantir que os funcionários envolvidos no escopo do sistema de gerenciamento do DTI tomem consciência:
a) da política do DTI;
b) dos objetivos relevantes do DTI;
c) de sua contribuição para a eficácia do sistema de gestão do DTI, incluindo os benefícios da melhoria do desempenho do DTI;
d) dos riscos associados ao não cumprimento dos requisitos.', NULL),
(@met, 24, 'Capítulo 7. Apoio', '7.4 Comunicação', NULL, NULL, 'O órgão gestor deve determinar e documentar as comunicações internas e externas necessárias ao cumprimento dos requisitos do 8.2 e seus indicadores associados, incluindo:
a) o quê comunicar;
b) quando comunicar;
c) a quem comunicar;
d) como comunicar;
e) quem comunica.', NULL),
(@met, 25, 'Capítulo 7. Apoio', '7.5.1 Sistema documental', NULL, NULL, 'O órgão gestor deve incluir, dentro do sistema de gestão do DTI, as informações documentadas exigidas pelo presente referencial técnico, bem como as necessárias para o cumprimento dos objetivos.', NULL),
(@met, 26, 'Capítulo 7. Apoio', '7.5.1 Sistema documental', NULL, NULL, 'O órgão gestor deve determinar para os documentos:
a) a maneira de identificá-los e controlar sua validade;
b) o formato e seus meios de apoio;
c) a revisão e aprovação do documento antes de sua implementação.', NULL),
(@met, 27, 'Capítulo 7. Apoio', '7.5.1 Sistema documental', NULL, NULL, 'As informações documentadas exigidas pelo sistema de gerenciamento DTI devem ser monitoradas para garantir que se encontrem:
a) disponíveis para uso;
b) adequadamente protegidas.', NULL),
(@met, 28, 'Capítulo 7. Apoio', '7.5.1 Sistema documental', NULL, NULL, 'O órgão gestor deve determinar e controlar as informações documentadas de origem externa necessárias para o sistema de gestão de DTI.', NULL),
(@met, 29, 'Capítulo 8. Operação', '8.1 Planejamento operacional e controle', NULL, NULL, 'O órgão gestor deve planificar, planejar, implementar e controlar os processos necessários para o DTI determinados no subcapítulo 4.5, e implementar as ações determinadas no capítulo 6.', NULL),
(@met, 30, 'Capítulo 8. Operação', '8.1 Planejamento operacional e controle', NULL, NULL, 'A implementação de processos e ações deve considerar os eixos definidos no subcapítulo 8.2.', NULL),
(@met, 31, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'governanca', 'Governança', 'O órgão gestor deve determinar as áreas municipais e outras partes interessadas que serão fornecedores externos de insumos para os processos e/ou informações para o sistema de gestão do DTI, e/ou que sejam responsáveis por gerenciar os processos necessários para implementar um DTI, considerando os eixos definidos na seção 8.2.', NULL),
(@met, 32, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'governanca', 'Gestão digital', 'O órgão gestor deve promover a digitalização dos processos incluídos no âmbito do sistema de gestão do DTI.', NULL),
(@met, 33, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'governanca', 'Gestão digital', 'Da mesma forma, o órgão gestor deve possibilitar que moradores, turistas e prestadores de serviços turísticos realizem seus trâmites de maneira digital.', NULL),
(@met, 34, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'governanca', 'Planejamento urbano e usos do solo', 'O órgão gestor deve determinar os usos turísticos do solo nos documentos legais, regulamentares ou institucionais relacionados ao ordenamento territorial, ao zoneamento do espaço e aos usos do solo.', 'As categorias podem ser: uso turístico do solo, áreas de uso turístico, áreas de serviço turístico, áreas de lazer, áreas de circulação turística, áreas de preservação patrimonial, áreas de maior densidade turística, áreas turísticas, entre outras.'),
(@met, 35, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'governanca', 'Planejamento urbano e usos do solo', 'O órgão gestor deve determinar as responsabilidades nas áreas encarregadas do tratamento sistematizado de dados georreferenciados definidos como fornecedores externos para planejar e articular ações relacionadas aos usos turísticos do solo no destino.', NULL),
(@met, 36, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'governanca', 'Participação e gestão aberta', 'O órgão gestor deve avaliar e promover mecanismos que facilitem a participação das partes interessadas no planejamento e implementação de políticas e ações para o turismo, associadas aos requisitos constantes no subcapítulo 8.2.', NULL),
(@met, 37, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'governanca', 'Participação e gestão aberta', 'O órgão gestor deve garantir e facilitar o acesso às informações públicas, tanto para o cidadão como para as partes interessadas.', NULL),
(@met, 38, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'inovacao', 'Inovação', 'O órgão gestor deve analisar e promover a implementação de ideias de inovação para ser capaz de responder aos riscos e oportunidades conforme mencionados no subcapítulo 6.1, e aos objetivos definidos nos requisitos do item 6.2.1.', NULL),
(@met, 39, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'inovacao', 'Inovação', 'O órgão gestor deve identificar inovações científicas, técnicas, metodológicas ou institucionais relevantes para os sistemas de gestão do DTI e/ou para implementar as ideias de inovação.', NULL),
(@met, 40, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'inovacao', 'Inovação', 'O órgão gestor deve identificar programas que promovam a inovação, gerenciados pelas partes interessadas e que sejam relevantes ao DTI.', NULL),
(@met, 41, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'inovacao', 'Inovação', 'O órgão gestor deve promover a inovação nos processos, produtos e serviços das partes interessadas do DTI.', 'Considerar aspectos como propriedade intelectual e outros requisitos legais, bem como a promoção e divulgação de ações que gerem consciência de mudança efetiva e estimulem a transformação das empresas de turismo.'),
(@met, 42, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'tecnologia', 'Infraestrutura de telecomunicações', 'O órgão gestor deve determinar as infraestruturas de telecomunicações necessárias para cumprir com os requisitos das partes interessadas e promover a melhoria e/ou adaptação das referidas infraestruturas.', NULL),
(@met, 43, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'tecnologia', 'Infraestrutura de telecomunicações', 'O órgão gestor deve incentivar e/ou promover o desenvolvimento de infraestruturas, dispositivos, aplicativos e/ou plataformas que permitam aos turistas conhecer o DTI e todos seus recursos e componentes.', NULL),
(@met, 44, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'tecnologia', 'Aplicativos e/ou plataformas tecnológicas', 'O órgão gestor deve implantar aplicativos e/ou plataformas tecnológicas para os sistemas de gestão do DTI.', NULL),
(@met, 45, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'tecnologia', 'Aplicativos e/ou plataformas tecnológicas', 'As partes interessadas devem promover e/ou facilitar aplicativos e/ou plataformas tecnológicas para sua própria gestão.', NULL),
(@met, 46, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'tecnologia', 'Aplicativos e/ou plataformas tecnológicas', 'O órgão gestor deve realizar a digitalização da oferta de serviços e recursos turísticos no DTI.', NULL),
(@met, 47, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'tecnologia', 'Aplicativos e/ou plataformas tecnológicas', 'O órgão gestor deve promover a implementação de aplicativos e/ou plataformas tecnológicas para efetuar a monitorização de capacidade de carga nos principais atrativos turísticos do DTI, especialmente os sítios de patrimônio cultural e/ou natural.', NULL),
(@met, 48, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'tecnologia', 'Segurança informática', 'O órgão gestor deve garantir a acessibilidade, disponibilidade e integridade de aplicativos e informações dos sistemas de gestão do DTI.', NULL),
(@met, 49, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'tecnologia', 'Segurança informática', 'O órgão gestor deve implementar ações para proteger os dados pessoais das partes interessadas.', NULL),
(@met, 50, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'acessibilidade', 'Acessibilidade', 'O órgão gestor deve fornecer informações sobre as condições de acessibilidade do DTI aos turistas e demais interessados.', NULL),
(@met, 51, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'acessibilidade', 'Acessibilidade', 'O órgão gestor deve promover a sensibilização e a formação das partes interessadas no DTI sobre a necessidade de atender aos requisitos de acessibilidade e do desenho universal.', NULL),
(@met, 52, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'acessibilidade', 'Acessibilidade', 'O órgão gestor deve promover o cumprimento dos requisitos aplicáveis à acessibilidade para fornecedores de turismo e para as demais partes interessadas do entorno do DTI.', NULL),
(@met, 53, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'acessibilidade', 'Acessibilidade', 'O órgão gestor deve promover o cumprimento dos requisitos de acessibilidade no próprio órgão gestor e nos fornecedores externos de gestão do DTI.', NULL),
(@met, 54, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Economia', 'O órgão gestor deve avaliar os impactos econômicos do turismo no DTI.', NULL),
(@met, 55, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Economia', 'O órgão gestor deve promover a formação e empregabilidade dos residentes no setor de turismo.', NULL),
(@met, 56, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Economia', 'O órgão gestor deve promover o desenvolvimento de empresas locais de produtos e serviços.', NULL),
(@met, 57, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Economia', 'O órgão gestor deve estabelecer prioridades de investimento em áreas estratégicas do DTI.', NULL),
(@met, 58, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Ambiente', 'O órgão gestor deve avaliar os aspectos relacionados ao cuidado e à poluição da água, do ar e do solo, que sejam significativos para o DTI, para sensibilizar as partes interessadas e promover ações para sua proteção.', NULL),
(@met, 59, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Ambiente', 'O órgão gestor deve avaliar os aspectos relacionados ao cuidado da flora e da fauna, que sejam significativos para o DTI, para sensibilizar as partes interessadas e promover ações para sua proteção.', NULL),
(@met, 60, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Ambiente', 'O órgão gestor deve avaliar os aspectos ambientais significativos para o DTI considerando a gestão eficiente da água, com o intuito de sensibilizar as partes interessadas e promover ações para sua otimização.', NULL),
(@met, 61, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Ambiente', 'O órgão gestor deve avaliar os aspectos ambientais significativos para o DTI considerando a gestão eficiente da energia, para sensibilizar as partes interessadas e promover ações para sua otimização.', NULL),
(@met, 62, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Ambiente', 'O órgão gestor deve avaliar os aspectos ambientais significativos para o DTI considerando o uso de energias renováveis, para sensibilizar as partes interessadas e promover ações para sua incorporação.', NULL),
(@met, 63, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Ambiente', 'O órgão gestor deve avaliar os aspectos ambientais significativos para o DTI considerando a gestão sustentável de resíduos, para sensibilizar as partes interessadas e promover ações para sua otimização.', NULL),
(@met, 64, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Ambiente', 'O órgão gestor deve implementar ações voltadas para a identificação, proteção, conservação e recuperação da paisagem e do patrimônio natural do DTI.', NULL),
(@met, 65, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Social', 'O órgão gestor deve implementar ações voltadas à igualdade de gênero em sua organização.', NULL),
(@met, 66, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Social', 'O órgão gestor deve incentivar a igualdade de gênero nos órgãos e empresas que compõem as partes interessadas do DTI.', NULL),
(@met, 67, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Social', 'O órgão gestor deve implementar ações voltadas à inclusão social na sua organização no DTI.', NULL),
(@met, 68, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'sustentabilidade', 'Social', 'O órgão gestor deve incentivar a inclusão social nos órgãos e empresas que compõem as partes interessadas do DTI.', NULL),
(@met, 69, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'criatividade', 'Criatividade', 'O órgão gestor deve fortalecer a cooperação com outros destinos reconhecidos como criativos.', NULL),
(@met, 70, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'criatividade', 'Criatividade', 'O órgão gestor deve impulsionar as indústrias criativas por meio da criação, produção, distribuição, difusão e fruição de atividades, bens e serviços criativos.', NULL),
(@met, 71, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'criatividade', 'Criatividade', 'O órgão gestor deve promover a criatividade no destino, incentivando a participação de residentes, fornecedores de bens e serviços turísticos e outras partes interessadas no desenvolvimento de projetos criativos.', NULL),
(@met, 72, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'criatividade', 'Cultura', 'O órgão gestor deve promover a diversidade cultural e incentivar a participação das comunidades locais e povos originários em ações ligadas ao turismo.', NULL),
(@met, 73, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'criatividade', 'Cultura', 'O órgão gestor deve realizar ações voltadas para a identificação, avaliação, proteção, conservação e restauração do patrimônio cultural no DTI.', NULL),
(@met, 74, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'mobilidade', 'Mobilidade e transporte', 'O órgão gestor deve avaliar as condições dos meios e infraestruturas de transporte dependendo das necessidades do DTI e promover melhorias relevantes.', NULL),
(@met, 75, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'mobilidade', 'Mobilidade e transporte', 'O órgão gestor deve avaliar as condições de acessibilidade em meios e infraestruturas de transporte no DTI.', NULL),
(@met, 76, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'mobilidade', 'Mobilidade e transporte', 'O órgão gestor deve identificar e avaliar a rede de conectividade de transporte do DTI com outros municípios, incluindo os terminais dos diversos meios de transporte, e promover as melhorias pertinentes.', NULL),
(@met, 77, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'mobilidade', 'Mobilidade e transporte', 'O órgão gestor deve identificar e promover a existência de meios de transporte sustentáveis no DTI, com foco nos modos de transporte não motorizado.', NULL),
(@met, 78, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'mobilidade', 'Mobilidade e transporte', 'O órgão gestor deve ter informações sobre acidentes, sinistros de trânsito e segurança viária no DTI.', NULL),
(@met, 79, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'promocao', 'Promoção e marketing', 'O órgão gestor deve promover, facilitar e/ou implementar aplicativos e/ou plataformas tecnológicas para a promoção do DTI.', NULL),
(@met, 80, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'promocao', 'Promoção e marketing', 'O órgão gestor deve promover, facilitar e/ou implementar aplicativos e/ou plataformas tecnológicas para a comercialização dos serviços turísticos entre as partes interessadas.', NULL),
(@met, 81, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'promocao', 'Promoção e marketing', 'O órgão gestor deve determinar e segmentar o comportamento e as características do consumo do turista no DTI, incluindo suas interações e transações.', NULL),
(@met, 82, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'promocao', 'Promoção e marketing', 'O órgão gestor deve publicizar a acessibilidade do DTI, incluindo instalações e serviços turísticos acessíveis.', NULL),
(@met, 83, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'seguranca', 'Segurança sanitária', 'O órgão gestor deve comunicar as condições sanitárias do DTI, incluindo a oferta de serviços de saúde.', NULL),
(@met, 84, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'seguranca', 'Segurança sanitária', 'O órgão gestor deve informar medidas preventivas e de cuidado às partes interessadas.', NULL),
(@met, 85, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'seguranca', 'Segurança sanitária', 'O órgão gestor deve determinar as informações necessárias para rastrear a segurança da saúde dos turistas do DTI.', NULL),
(@met, 86, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'seguranca', 'Segurança pública', 'O órgão gestor deve identificar as medidas adotadas pelo DTI para a prevenção do crime.', NULL),
(@met, 87, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'seguranca', 'Segurança pública', 'O órgão gestor deve identificar as características dos crimes que sofrem os turistas no DTI para promover medidas preventivas.', NULL),
(@met, 88, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'seguranca', 'Prevenção da exploração de crianças e adolescentes', 'O órgão gestor deve identificar as características dos crimes de exploração sexual e laboral contra crianças e adolescentes, com fins turísticos, para promover medidas preventivas.', NULL),
(@met, 89, 'Capítulo 8. Operação', '8.2 Requisitos mínimos dos eixos', 'seguranca', 'Prevenção da exploração de crianças e adolescentes', 'O órgão gestor deve realizar ações de sensibilização e divulgação do Código de Conduta do Brasil.', 'O Código de Conduta do Brasil é um instrumento de compromisso, de livre adesão, para que empresas e prestadores de serviços turísticos adotem uma posição explícita de repúdio e ações de prevenção e enfrentamento à exploração sexual de crianças e adolescentes.'),
(@met, 90, 'Capítulo 9. Avaliação de desempenho', '9.1 Rastreamento de sistemas de gestão do DTI', NULL, NULL, 'O órgão gestor deve determinar e analisar os meios, fontes, dados, informações e processos para coletar e usar as informações relativas à gestão do DTI, realizando seu acompanhamento constante.', NULL),
(@met, 91, 'Capítulo 9. Avaliação de desempenho', '9.1.1 Satisfação dos turistas e prestadores de serviços turísticos', NULL, NULL, 'O órgão gestor deve obter informações e monitorar o grau de satisfação dos turistas do DTI.', 'Pode ser medido nos produtos e serviços que o turista recebe do órgão gestor, dos prestadores de serviços turísticos e de outras partes interessadas, e no DTI no seu conjunto.'),
(@met, 92, 'Capítulo 9. Avaliação de desempenho', '9.1.1 Satisfação dos turistas e prestadores de serviços turísticos', NULL, NULL, 'O órgão gestor deve obter informações e rastrear o grau de satisfação dos prestadores de serviços turísticos do DTI com relação aos serviços que recebem do órgão gestor.', NULL),
(@met, 93, 'Capítulo 9. Avaliação de desempenho', '9.1.2 Monitoramento de variáveis de mercado', NULL, NULL, 'O órgão gestor deve determinar e rastrear as variáveis de mercado relevantes ao sistema de gestão do DTI.', NULL),
(@met, 94, 'Capítulo 9. Avaliação de desempenho', '9.1.3 Rastreamento dos eixos do DTI', NULL, NULL, 'O órgão gestor deve determinar indicadores para monitorar o cumprimento dos processos e ações associadas ao DTI.', NULL),
(@met, 95, 'Capítulo 9. Avaliação de desempenho', '9.1.3 Rastreamento dos eixos do DTI', NULL, NULL, 'O órgão gestor deve monitorar o cumprimento dos requisitos do DTI associados ao Referencial Técnico.', NULL),
(@met, 96, 'Capítulo 9. Avaliação de desempenho', '9.1.4 Aplicativo (app) para monitoramento', NULL, NULL, 'O órgão gestor deve implementar um aplicativo ou plataforma informática para monitorar processos, objetivos, planos, indicadores e requisitos do sistema de gestão do DTI.', NULL),
(@met, 97, 'Capítulo 9. Avaliação de desempenho', '9.2 Auditoria interna', NULL, NULL, 'O órgão gestor deve:
a) planejar, implementar e manter pelo menos um programa de auditoria que inclua a frequência, métodos, responsabilidades, requisitos de planejamento, relatórios e resultados de auditorias anteriores;
b) definir os critérios e o escopo de cada auditoria;
c) selecionar os auditores e garantir a imparcialidade do processo de auditoria;
d) garantir que os resultados das auditorias sejam comunicados às autoridades do DTI;
e) reter informações documentadas como evidência de implementação do programa de auditoria e de resultados.', NULL),
(@met, 98, 'Capítulo 9. Avaliação de desempenho', '9.3 Revisão gerencial', NULL, NULL, 'As autoridades do DTI devem revisar os sistemas de gestão em intervalos planejados, para garantir sua conveniência, adequação e eficácia.', NULL),
(@met, 99, 'Capítulo 9. Avaliação de desempenho', '9.3 Revisão gerencial', NULL, NULL, 'A revisão pelos gestores deve ser planejada e executada, incluindo considerações sobre:
a) o status das ações de revisão pelos gestores anteriores;
b) alterações em questões externas e internas relevantes ao sistema de gestão do DTI;
c) a informação de desempenho do DTI e dos eixos do subcapítulo 8.2 e o grau de cumprimento dos objetivos;
d) a satisfação dos turistas;
e) o cumprimento dos requisitos pelos prestadores de serviços turísticos;
f) os resultados da auditoria interna e as avaliações externas, incluindo as auditorias externas;
g) a eficácia das ações realizadas para tratar riscos e oportunidades;
h) as oportunidades de melhoria.', NULL),
(@met, 100, 'Capítulo 9. Avaliação de desempenho', '9.3 Revisão gerencial', NULL, NULL, 'Os resultados da revisão gerencial devem incluir decisões relacionadas a:
a) oportunidades de melhoria;
b) qualquer necessidade de mudança no sistema de gestão do DTI;
c) necessidades de recursos.', NULL),
(@met, 101, 'Capítulo 9. Avaliação de desempenho', '9.3 Revisão gerencial', NULL, NULL, 'O órgão gestor deve reter informações documentadas como evidência dos resultados das revisões gerenciais.', NULL),
(@met, 102, 'Capítulo 10. Aperfeiçoamento', '10.1 Não conformidades e ações corretivas', NULL, NULL, 'Em caso de inconformidades, o órgão gestor deve:
a) reagir à não conformidade e realizar ações para controlá-la e corrigi-la;
b) avaliar a necessidade de ações para eliminar as causas da não conformidade;
c) implementar qualquer ação necessária;
d) revisar a eficácia de qualquer ação tomada;
e) atualizar os riscos e oportunidades determinadas durante o planejamento;
f) fazer mudanças nos sistemas de gestão do DTI.', NULL),
(@met, 103, 'Capítulo 10. Aperfeiçoamento', '10.1 Não conformidades e ações corretivas', NULL, NULL, 'A organização deve reter informações documentadas como evidência:
a) da natureza das não conformidades e de qualquer ação tomada posteriormente;
b) dos resultados de qualquer ação corretiva.', NULL),
(@met, 104, 'Capítulo 10. Aperfeiçoamento', '10.2 Melhoria contínua', NULL, NULL, 'O órgão gestor deve melhorar continuamente a eficiência e o desempenho do sistema de gerenciamento do DTI.', NULL),
(@met, 105, 'Capítulo 10. Aperfeiçoamento', '10.2 Melhoria contínua', NULL, NULL, 'O órgão gestor deve considerar os resultados dos processos de monitoramento e os resultados da revisão gerencial para determinar se existem necessidades e oportunidades que devem ser consideradas como parte da melhoria contínua.', NULL);
