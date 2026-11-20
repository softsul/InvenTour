-- InvenTour: núcleo de destinos, usuários, papéis, permissões, segurança,
-- fila e bases públicas de apoio (IBGE, Mapa do Turismo, Cadastur).
-- Softsul Software, 20/11/2026.
--
-- É o núcleo do EventCity no estado de 20/11/2026 (que por sua vez veio do
-- Centurion e do Orbit), sem nada do domínio da agenda: o tenant é a tabela
-- `empresas` (um destino: município, região turística ou corporação), a
-- pessoa tem UM login e um vínculo por destino, com um ou mais papéis, e a
-- equipe Softsul (usuarios.softsul = 1) trabalha na visão da plataforma
-- (empresa 0).
--
-- Ligação com o EventCity (docs/INTEGRACAO-INVENTOUR.md): o destino é o
-- mesmo pelo código IBGE do município (municipios.id) e pelo slug, e cada
-- lado guarda o id do outro (aqui, empresas.eventcity_id).
--
-- Todo dado operacional das próximas migrações leva empresa_id, e o filtro
-- vive na consulta, nunca na tela.

SET FOREIGN_KEY_CHECKS = 0;
CREATE TABLE `estados` (
  `id` tinyint unsigned NOT NULL COMMENT 'código IBGE da UF (41 = PR)',
  `uf` char(2) NOT NULL,
  `nome` varchar(40) NOT NULL,
  `regiao` enum('Norte','Nordeste','Centro-Oeste','Sudeste','Sul') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_estados_uf` (`uf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `regioes_turisticas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `estado_id` tinyint unsigned NOT NULL,
  `nome` varchar(120) NOT NULL COMMENT 'ex.: Vales do Iguaçu',
  `tipo` enum('regiao_turistica','igr','consorcio') NOT NULL DEFAULT 'regiao_turistica',
  `mapa_ano` smallint unsigned DEFAULT NULL COMMENT 'último Mapa do Turismo em que a região aparece',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_regioes_nome` (`estado_id`,`nome`),
  KEY `ix_regioes_estado` (`estado_id`),
  CONSTRAINT `fk_regioes_estado` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `regioes_ibge` (
  `id` int unsigned NOT NULL COMMENT 'código IBGE da região',
  `tipo` enum('imediata','intermediaria') NOT NULL,
  `nome` varchar(120) NOT NULL,
  `estado_id` tinyint unsigned NOT NULL,
  `intermediaria_id` int unsigned DEFAULT NULL COMMENT 'na imediata: a intermediária que a contém',
  PRIMARY KEY (`id`),
  KEY `ix_regioes_ibge_estado` (`estado_id`,`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `municipios` (
  `id` int unsigned NOT NULL COMMENT 'código IBGE de 7 dígitos (4118501 = Pato Branco)',
  `estado_id` tinyint unsigned NOT NULL,
  `regiao_id` int unsigned DEFAULT NULL COMMENT 'região turística, quando mapeada',
  `regiao_imediata_id` int unsigned DEFAULT NULL,
  `regiao_intermediaria_id` int unsigned DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL COMMENT 'nome sem acento, para o subdomínio sugerido',
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `populacao` int unsigned DEFAULT NULL COMMENT 'base do preço da licença',
  PRIMARY KEY (`id`),
  KEY `ix_municipios_estado` (`estado_id`,`nome`),
  KEY `ix_municipios_regiao` (`regiao_id`),
  KEY `ix_municipios_slug` (`slug`),
  KEY `ix_municipios_imediata` (`regiao_imediata_id`),
  KEY `ix_municipios_intermediaria` (`regiao_intermediaria_id`),
  CONSTRAINT `fk_municipios_estado` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`),
  CONSTRAINT `fk_municipios_regiao` FOREIGN KEY (`regiao_id`) REFERENCES `regioes_turisticas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `empresas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL COMMENT 'nome institucional: Prefeitura Municipal de Pato Branco',
  `nome_fantasia` varchar(100) NOT NULL COMMENT 'como aparece no portal: Pato Branco',
  `tipo` enum('municipio','regiao','corporativo') NOT NULL DEFAULT 'municipio',
  `cnpj` char(14) DEFAULT NULL COMMENT 'letras maiúsculas e números, sem pontuação (CNPJ alfanumérico)',
  `slug` varchar(60) NOT NULL COMMENT 'subdomínio do portal: <slug>.inventour.com.br',
  `dominio_proprio` varchar(150) DEFAULT NULL COMMENT 'CNAME da prefeitura, ex.: turismo.patobranco.pr.gov.br',
  `municipio_id` int unsigned DEFAULT NULL COMMENT 'sede; obrigatório na tela, nulo só até o seed do IBGE',
  `regiao_id` int unsigned DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL COMMENT 'E.164 sem o +',
  `whatsapp` varchar(20) DEFAULT NULL COMMENT 'E.164 sem o +',
  `cep` char(8) DEFAULT NULL,
  `logradouro` varchar(150) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(80) DEFAULT NULL,
  `bairro` varchar(80) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `cor_acento` char(7) DEFAULT NULL COMMENT 'acento do portal (#RRGGBB); nulo usa o do InvenTour',
  `logo` varchar(120) DEFAULT NULL COMMENT 'caminho em storage/midias/marcas/',
  `logo_no_portal` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 = o logo não aparece no cabeçalho do portal',
  `foto` varchar(120) DEFAULT NULL,
  `exigir_2fa_equipe` tinyint(1) NOT NULL DEFAULT '1',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime DEFAULT NULL,
  `eventcity_id` int unsigned DEFAULT NULL COMMENT 'o mesmo destino no EventCity (empresas.id de lá), gravado no aperto de mão da API',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_empresas_eventcity` (`eventcity_id`),
  UNIQUE KEY `uk_empresas_slug` (`slug`),
  UNIQUE KEY `uk_empresas_cnpj` (`cnpj`),
  UNIQUE KEY `uk_empresas_dominio` (`dominio_proprio`),
  KEY `ix_empresas_municipio` (`municipio_id`),
  KEY `ix_empresas_regiao` (`regiao_id`),
  CONSTRAINT `fk_empresas_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`),
  CONSTRAINT `fk_empresas_regiao` FOREIGN KEY (`regiao_id`) REFERENCES `regioes_turisticas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `papeis` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(40) NOT NULL,
  `nome` varchar(60) NOT NULL,
  `escopo` enum('softsul','destino') NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ordem` smallint NOT NULL DEFAULT '0' COMMENT 'ninguém dá papel de ordem menor que a do próprio',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_papeis_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modulos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(40) NOT NULL COMMENT 'é o nome da tela: public/<chave>.php',
  `nome` varchar(60) NOT NULL,
  `icone` varchar(30) NOT NULL DEFAULT 'quadro',
  `grupo` varchar(40) NOT NULL DEFAULT 'Geral',
  `grupo_ordem` smallint NOT NULL DEFAULT '0',
  `ordem` smallint NOT NULL DEFAULT '0',
  `no_menu` tinyint(1) NOT NULL DEFAULT '1',
  `apenas_softsul` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'nunca aparece para quem é de destino',
  `contexto` enum('ambos','destino','plataforma') NOT NULL DEFAULT 'ambos' COMMENT 'onde o módulo vale',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `pode_desligar` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'aparece em Configurações, Módulos, na visão da plataforma',
  `desligado` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'no menu, mas desabilitado',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_modulos_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `modulo_id` int unsigned NOT NULL,
  `chave` varchar(60) NOT NULL COMMENT 'modulo.verbo',
  `verbo` enum('ver','criar','editar','excluir','exportar') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissoes_chave` (`chave`),
  KEY `ix_permissoes_modulo` (`modulo_id`),
  CONSTRAINT `fk_permissoes_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `papel_permissao` (
  `papel_id` int unsigned NOT NULL,
  `permissao_id` int unsigned NOT NULL,
  PRIMARY KEY (`papel_id`,`permissao_id`),
  KEY `ix_pp_permissao` (`permissao_id`),
  CONSTRAINT `fk_pp_papel` FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`),
  CONSTRAINT `fk_pp_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `apelido` varchar(40) DEFAULT NULL,
  `departamento` varchar(120) DEFAULT NULL COMMENT 'Departamento ou secretaria',
  `funcao` varchar(80) DEFAULT NULL COMMENT 'Função ou cargo',
  `email` varchar(150) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL COMMENT 'E.164 sem o +',
  `foto` varchar(120) DEFAULT NULL,
  `whatsapp_chatid` varchar(40) DEFAULT NULL,
  `whatsapp_existe` tinyint(1) DEFAULT NULL,
  `whatsapp_conferido_em` datetime DEFAULT NULL,
  `senha_hash` varchar(255) DEFAULT NULL COMMENT 'nulo até aceitar o convite; Argon2id',
  `softsul` tinyint(1) NOT NULL DEFAULT '0',
  `papel_softsul_id` int unsigned DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `precisa_trocar_senha` tinyint(1) NOT NULL DEFAULT '0',
  `duplo_fator_segredo` varchar(255) DEFAULT NULL COMMENT 'cifrado, prefixo v1:',
  `duplo_fator_confirmado` tinyint(1) NOT NULL DEFAULT '0',
  `duplo_fator_ultimo_periodo` bigint DEFAULT NULL COMMENT 'impede reusar o mesmo código',
  `versao_sessao` int unsigned NOT NULL DEFAULT '1' COMMENT 'subir derruba as sessões abertas',
  `empresa_padrao_id` int unsigned DEFAULT NULL,
  `tema` enum('claro','escuro') NOT NULL DEFAULT 'escuro',
  `por_pagina` smallint unsigned DEFAULT NULL COMMENT '25, 50, 100 ou 200',
  `ultimo_login` datetime DEFAULT NULL,
  `ultimo_ip` varchar(45) DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_email` (`email`),
  KEY `ix_usuarios_telefone` (`telefone`),
  KEY `ix_usuarios_empresa_padrao` (`empresa_padrao_id`),
  KEY `fk_usuarios_papel_softsul` (`papel_softsul_id`),
  CONSTRAINT `fk_usuarios_empresa_padrao` FOREIGN KEY (`empresa_padrao_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `fk_usuarios_papel_softsul` FOREIGN KEY (`papel_softsul_id`) REFERENCES `papeis` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_empresa` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int unsigned NOT NULL,
  `empresa_id` int unsigned NOT NULL,
  `papel_id` int unsigned NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_empresa` (`usuario_id`,`empresa_id`),
  KEY `ix_ue_empresa` (`empresa_id`),
  KEY `ix_ue_papel` (`papel_id`),
  CONSTRAINT `fk_ue_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `fk_ue_papel` FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`),
  CONSTRAINT `fk_ue_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_empresa_papel` (
  `usuario_id` int unsigned NOT NULL,
  `empresa_id` int unsigned NOT NULL,
  `papel_id` int unsigned NOT NULL,
  PRIMARY KEY (`usuario_id`,`empresa_id`,`papel_id`),
  KEY `ix_uep_empresa` (`empresa_id`),
  KEY `ix_uep_papel` (`papel_id`),
  CONSTRAINT `fk_uep_papel` FOREIGN KEY (`papel_id`) REFERENCES `papeis` (`id`),
  CONSTRAINT `fk_uep_vinculo` FOREIGN KEY (`usuario_id`, `empresa_id`) REFERENCES `usuario_empresa` (`usuario_id`, `empresa_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_permissao` (
  `usuario_id` int unsigned NOT NULL,
  `empresa_id` int unsigned NOT NULL DEFAULT '0',
  `permissao_id` int unsigned NOT NULL,
  `concedida` tinyint(1) NOT NULL,
  PRIMARY KEY (`usuario_id`,`empresa_id`,`permissao_id`),
  KEY `ix_up_permissao` (`permissao_id`),
  CONSTRAINT `fk_up_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`),
  CONSTRAINT `fk_up_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tentativas_login` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `sucesso` tinyint(1) NOT NULL,
  `etapa` enum('senha','2fa','esqueci') NOT NULL DEFAULT 'senha',
  `criado_em` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_tentativas_email` (`email`,`criado_em`),
  KEY `ix_tentativas_ip` (`ip`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `convites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `origem` enum('convite','esqueci','redefinir') NOT NULL DEFAULT 'convite',
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_por` int unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_convites_token` (`token_hash`),
  KEY `ix_convites_usuario` (`usuario_id`),
  CONSTRAINT `fk_convites_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `credenciais_webauthn` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int unsigned NOT NULL,
  `credencial_id` varbinary(255) NOT NULL,
  `chave_publica` text NOT NULL,
  `apelido` varchar(60) NOT NULL,
  `sincronizavel` tinyint(1) DEFAULT NULL,
  `contador` int unsigned NOT NULL DEFAULT '0',
  `ultimo_uso_em` datetime DEFAULT NULL,
  `ultimo_ip` varchar(45) DEFAULT NULL,
  `ultimo_aparelho` varchar(80) DEFAULT NULL COMMENT 'chave sincronizada vale em vários aparelhos; guarda de onde veio o último uso',
  `criado_em` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_webauthn_credencial` (`credencial_id`),
  KEY `ix_webauthn_usuario` (`usuario_id`),
  CONSTRAINT `fk_webauthn_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_certificados` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int unsigned NOT NULL,
  `impressao` char(64) NOT NULL,
  `titular` varchar(190) NOT NULL,
  `emissor` varchar(190) DEFAULT NULL,
  `valido_ate` datetime NOT NULL,
  `criado_em` datetime NOT NULL,
  `ultimo_uso_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_certificados_impressao` (`impressao`),
  KEY `ix_certificados_usuario` (`usuario_id`),
  CONSTRAINT `fk_certificados_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `certificado_passes_usados` (
  `id` char(32) NOT NULL,
  `usado_em` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_passes_usado_em` (`usado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auditoria` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int unsigned DEFAULT NULL,
  `usuario_nome` varchar(120) DEFAULT NULL COMMENT 'desnormalizado para sobreviver a exclusões',
  `empresa_id` int unsigned DEFAULT NULL,
  `acao` varchar(30) NOT NULL,
  `entidade` varchar(60) DEFAULT NULL,
  `entidade_id` int unsigned DEFAULT NULL,
  `resumo` varchar(255) DEFAULT NULL,
  `dados_antes` json DEFAULT NULL,
  `dados_depois` json DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  PRIMARY KEY (`id`,`criado_em`),
  KEY `ix_auditoria_empresa` (`empresa_id`,`criado_em`),
  KEY `ix_auditoria_usuario` (`usuario_id`,`criado_em`),
  KEY `ix_auditoria_entidade` (`entidade`,`entidade_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (year(`criado_em`))
(PARTITION p2026 VALUES LESS THAN (2027) ENGINE = InnoDB,
 PARTITION p2027 VALUES LESS THAN (2028) ENGINE = InnoDB,
 PARTITION p2028 VALUES LESS THAN (2029) ENGINE = InnoDB,
 PARTITION pmax VALUES LESS THAN MAXVALUE ENGINE = InnoDB);

CREATE TABLE `ajustes` (
  `empresa_id` int unsigned NOT NULL DEFAULT '0',
  `grupo` varchar(40) NOT NULL,
  `chave` varchar(60) NOT NULL,
  `valor` text,
  `cifrado` tinyint(1) NOT NULL DEFAULT '0',
  `atualizado_em` datetime DEFAULT NULL,
  `atualizado_por` int unsigned DEFAULT NULL,
  PRIMARY KEY (`empresa_id`,`grupo`,`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned DEFAULT NULL COMMENT 'para o painel da plataforma ver a fila por destino',
  `tipo` varchar(60) NOT NULL,
  `payload` json DEFAULT NULL,
  `status` enum('pendente','processando','concluido','falhou') NOT NULL DEFAULT 'pendente',
  `tentativas` smallint NOT NULL DEFAULT '0',
  `max_tentativas` smallint NOT NULL DEFAULT '3',
  `erro` text,
  `executar_em` datetime NOT NULL COMMENT 'permite agendar para o futuro',
  `iniciado_em` datetime DEFAULT NULL,
  `concluido_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_jobs_fila` (`status`,`executar_em`),
  KEY `ix_jobs_tipo` (`tipo`),
  KEY `ix_jobs_empresa` (`empresa_id`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `limites` (
  `chave` varchar(120) NOT NULL COMMENT 'ex.: portal:ip:203.0.113.9',
  `janela` datetime NOT NULL COMMENT 'início do minuto ou da hora',
  `contagem` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`chave`,`janela`),
  KEY `ix_limites_janela` (`janela`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_ocorrencias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `tipo` enum('devolucao','reclamacao') NOT NULL,
  `permanente` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'devolução Permanent; reclamação conta sempre',
  `subtipo` varchar(60) DEFAULT NULL COMMENT 'bounceSubType ou complaintFeedbackType',
  `detalhe` varchar(500) DEFAULT NULL COMMENT 'diagnosticCode do servidor de destino',
  `mensagem_id` varchar(120) DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  `liberado_em` datetime DEFAULT NULL,
  `liberado_por` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_email_ocorrencias_email` (`email`,`liberado_em`),
  KEY `ix_email_ocorrencias_data` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cadastur_prestadores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `documento` varchar(14) NOT NULL COMMENT 'CNPJ, ou CPF do prestador pessoa física; só letras e dígitos',
  `tipo_documento` enum('cnpj','cpf') NOT NULL,
  `atividade` varchar(100) NOT NULL COMMENT 'ex.: Organizadora de Eventos, Meio de Hospedagem, Guia de Turismo',
  `nome_juridico` varchar(200) DEFAULT NULL COMMENT 'nome da pessoa jurídica, ou nome do prestador pessoa física',
  `nome_fantasia` varchar(200) DEFAULT NULL,
  `situacao_cadastral` varchar(40) DEFAULT NULL COMMENT 'ex.: Regular, Irregular, Cancelado',
  `situacao_atividade` varchar(40) DEFAULT NULL COMMENT 'ex.: Operação, Paralisada',
  `natureza_juridica` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `municipio` varchar(100) DEFAULT NULL,
  `municipio_id` int unsigned DEFAULT NULL COMMENT 'código IBGE, achado pelo nome e pela UF',
  `certificado` varchar(30) DEFAULT NULL,
  `validade` date DEFAULT NULL COMMENT 'validade do certificado',
  `leitos` int unsigned DEFAULT NULL COMMENT 'só meios de hospedagem',
  `unidades` int unsigned DEFAULT NULL COMMENT 'unidades habitacionais, só meios de hospedagem',
  `categorias` varchar(500) DEFAULT NULL COMMENT 'categorias, tipos ou segmentos que a planilha traz para a atividade',
  `endereco` varchar(300) DEFAULT NULL COMMENT 'endereço comercial; sem ele, o da Receita Federal',
  `telefone` varchar(80) DEFAULT NULL COMMENT 'comercial; sem ele, o institucional',
  `email` varchar(160) DEFAULT NULL COMMENT 'comercial; sem ele, o institucional',
  `site` varchar(200) DEFAULT NULL,
  `porte` varchar(60) DEFAULT NULL,
  `idiomas` varchar(200) DEFAULT NULL,
  `unidades_acessiveis` int unsigned DEFAULT NULL COMMENT 'só meios de hospedagem',
  `leitos_acessiveis` int unsigned DEFAULT NULL COMMENT 'só meios de hospedagem',
  `importacao_id` int unsigned NOT NULL,
  `nome_exibicao` varchar(200) GENERATED ALWAYS AS (coalesce(`nome_fantasia`,`nome_juridico`)) STORED,
  PRIMARY KEY (`documento`,`atividade`),
  UNIQUE KEY `uq_cadastur_id` (`id`),
  KEY `ix_cadastur_municipio` (`municipio_id`,`atividade`),
  KEY `ix_cadastur_uf` (`uf`,`municipio`),
  KEY `ix_cadastur_importacao` (`atividade`,`importacao_id`),
  KEY `ix_cadastur_nome` (`nome_exibicao`,`atividade`),
  KEY `ix_cadastur_atividade_nome` (`atividade`,`nome_exibicao`),
  KEY `ix_cadastur_uf_municipio_nome` (`uf`,`municipio`,`nome_exibicao`),
  KEY `ix_cadastur_validade` (`validade`),
  KEY `ix_cadastur_leitos` (`leitos`),
  KEY `ix_cadastur_certificado` (`certificado`),
  FULLTEXT KEY `ft_cadastur_busca` (`nome_fantasia`,`nome_juridico`,`atividade`,`categorias`,`municipio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cadastur_importacoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pacote` varchar(120) NOT NULL COMMENT 'nome do conjunto no dados.turismo.gov.br',
  `recurso_id` varchar(64) NOT NULL,
  `recurso_nome` varchar(200) DEFAULT NULL COMMENT 'ex.: Segundo Trimestre 2026',
  `publicado_em` datetime DEFAULT NULL COMMENT 'última modificação do arquivo no portal',
  `linhas` int unsigned NOT NULL DEFAULT '0',
  `status` enum('importando','concluida','falhou') NOT NULL DEFAULT 'importando',
  `erro` varchar(500) DEFAULT NULL,
  `iniciada_em` datetime NOT NULL,
  `concluida_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_importacoes_pacote` (`pacote`,`status`,`concluida_em`),
  KEY `ix_importacoes_recurso` (`recurso_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mapa_turismo` (
  `ano` smallint unsigned NOT NULL,
  `municipio_id` int unsigned NOT NULL,
  `regiao_id` int unsigned NOT NULL,
  `categoria` tinyint unsigned NOT NULL COMMENT '1 turístico, 2 oferta complementar, 3 apoio ao turismo',
  `empregos` int unsigned DEFAULT NULL COMMENT 'empregos formais em hospedagem (RAIS)',
  `estabelecimentos` int unsigned DEFAULT NULL COMMENT 'estabelecimentos de hospedagem (RAIS)',
  `visitas_nacionais` int unsigned DEFAULT NULL COMMENT 'estimativa do Ministério',
  `visitas_internacionais` int unsigned DEFAULT NULL COMMENT 'estimativa do Ministério',
  `arrecadacao` decimal(15,2) DEFAULT NULL COMMENT 'impostos federais de hospedagem, em reais',
  `instancia` varchar(150) DEFAULT NULL COMMENT 'instância de governança municipal, em geral o COMTUR',
  `instancia_cnpj` char(14) DEFAULT NULL,
  `conselho_ativo` tinyint(1) DEFAULT NULL,
  `importado_em` datetime NOT NULL,
  PRIMARY KEY (`ano`,`municipio_id`),
  KEY `ix_mapa_turismo_municipio` (`municipio_id`,`ano`),
  KEY `ix_mapa_turismo_regiao` (`regiao_id`),
  CONSTRAINT `fk_mapa_turismo_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`),
  CONSTRAINT `fk_mapa_turismo_regiao` FOREIGN KEY (`regiao_id`) REFERENCES `regioes_turisticas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
