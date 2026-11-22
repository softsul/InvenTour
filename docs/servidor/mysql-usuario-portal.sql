-- Permissões do usuário inventour_portal (portal público, sem login).
-- Softsul Software
--
-- O portal só lê as colunas que mostra e só escreve nos limites por IP.
-- Toda coluna nova que o portal ler entra aqui, aplicada como root logo depois
-- da migração (sudo mysql < este arquivo): sem isso o portal cai com erro 500 (aconteceu no
-- EventCity em 03/11/2026). Depois do deploy, curl em cada destino ativo.

GRANT SELECT (id, slug, nome_fantasia, cor_acento, logo, logo_no_portal, cidade, uf, dominio_proprio, ativo, modo_operacao, avaliacao_ate)
    ON inventour.empresas TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON inventour.limites TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';

-- Etapa 4 (21/11/2026): o QR das fichas do caderno e a verificação do PDF.
-- O QR conta a leitura (caderno_leituras) e mostra a prévia bloqueada (modo
-- projeto) ou a ficha (destino licenciado ou em avaliação).
GRANT SELECT (id, empresa_id, codigo, titulo, ano, versao, previa, situacao, paginas, itens, sha256, gerado_em)
    ON inventour.cadernos TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON inventour.caderno_leituras TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, empresa_id, ficha_id, tipo_id, situacao, nome, logradouro, numero, bairro, telefone, whatsapp, site, instagram,
              latitude, longitude, plus_code, descricao_promocional)
    ON inventour.inventario_itens TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, empresa_id, item_id, experiencia_id, arquivo, capa, ordem)
    ON inventour.inventario_midias TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, codigo, nome) ON inventour.inventario_fichas TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, nome) ON inventour.inventario_tipos TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
-- O convite e o contato comercial da prévia não vêm do banco: a tela Comercial
-- grava só eles em storage/cache/portal-comercial.json (Comercial::paraOPortal).

-- Etapa 5 (21/11/2026): o portal do destino. Só as colunas públicas do item
-- (nada de CNPJ, observações internas, pesquisador ou coordenador).
GRANT SELECT (id, slug, nome_fantasia, cor_acento, logo, logo_no_portal, cidade, uf, dominio_proprio, ativo, modo_operacao, avaliacao_ate,
              municipio_id, email, telefone, whatsapp, foto)
    ON inventour.empresas TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, empresa_id, ficha_id, tipo_id, situacao, nome, nome_popular, logradouro, numero, complemento, bairro, distrito, zona, cep,
              telefone, whatsapp, email, site, instagram, latitude, longitude, plus_code, referencia, horario, formas_pagamento, idiomas,
              capacidade_uh, capacidade_leitos, capacidade_assentos, capacidade_publico, acessibilidade_possui, descricao_promocional,
              cadastur, cadastur_validade, possui_experiencia, homologado_em, atualizado_em)
    ON inventour.inventario_itens TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, empresa_id, item_id, experiencia_id, arquivo, largura, altura, legenda, credito, capa, ordem)
    ON inventour.inventario_midias TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, codigo, nome, categoria, ordem, ativo) ON inventour.inventario_fichas TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, nome, pai_id) ON inventour.inventario_tipos TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (item_id, empresa_id, chave, opcoes) ON inventour.inventario_item_acessibilidade TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT ON inventour.inventario_item_facilidades TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT ON inventour.tribos TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT ON inventour.tribo_facilidades TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, empresa_id, item_id, situacao, titulo, dimensoes, narrativa, duracao_minutos, pessoas_min, pessoas_max, agendamento,
              antecedencia_horas, faixa_valor, valor_texto)
    ON inventour.inventario_experiencias TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (empresa_id, secao, titulo, conteudo_html, indicadores, no_portal, ordem)
    ON inventour.destino_narrativas TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, empresa_id, data_marco, precisao, categoria, titulo, descricao, no_portal)
    ON inventour.destino_linha_tempo TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, nome, latitude, longitude) ON inventour.municipios TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, empresa_id, titulo, tipo, tribo_id, descricao, duracao, publicado, ordem)
    ON inventour.roteiros TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT ON inventour.roteiro_itens TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT (id, empresa_id, apelido, titulo, destino_tipo, destino_ref, ativo)
    ON inventour.qr_pontos TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON inventour.qr_leituras TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
-- Métricas do portal sem cookie (portal/m.php, migração 035), como no EventCity.
GRANT SELECT, INSERT, UPDATE ON inventour.metricas_lugares TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON inventour.metricas_portal  TO 'inventour_portal'@'localhost', 'inventour_portal'@'127.0.0.1';
