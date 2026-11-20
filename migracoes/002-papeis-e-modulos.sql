-- InvenTour: papéis, módulos e permissões iniciais.
-- Softsul Software, 20/11/2026.
--
-- Módulo é dado: ligar um módulo é UPDATE modulos SET ativo = 1, e a
-- permissão de módulo inativo não vale. Os módulos do inventário já nascem
-- cadastrados e desligados (inventario, coleta, homologacao, caderno,
-- phygital, relatorios); cada um é ligado na migração da etapa que entrega a
-- tela dele.
--
-- Papéis do destino (escopo destino):
--   gestor       secretaria ou COMTUR: tudo no destino, inclusive homologar
--   tecnico      técnico de turismo do município: revisa e homologa fichas
--   pesquisador  turismólogo, consultor ou estudante: coleta em campo
--   leitor       secretarias e conselhos: só consulta
--   prestador    o trade: mantém a ficha do próprio negócio (fora do 2FA
--                obrigatório, como o proponente do EventCity)

INSERT INTO papeis (chave, nome, escopo, descricao, ordem) VALUES
('plataforma_admin',   'Administrador da plataforma', 'softsul', 'Tudo, em todos os destinos.', 1),
('plataforma_suporte', 'Suporte da plataforma',       'softsul', 'Consulta todos os destinos para atender, sem alterar cadastro.', 2),
('gestor',      'Gestor',      'destino', 'Secretaria de turismo ou COMTUR: configura o destino, homologa, vê tudo.', 10),
('tecnico',     'Técnico',     'destino', 'Técnico de turismo do município: revisa as fichas coletadas e homologa.', 11),
('pesquisador', 'Pesquisador', 'destino', 'Turismólogo, consultor ou estudante: coleta as fichas em campo.', 12),
('leitor',      'Leitor',      'destino', 'Secretarias e conselhos: só consulta o inventário e os relatórios.', 13),
('prestador',   'Prestador',   'destino', 'Empresa do trade: mantém a ficha do próprio negócio.', 20);

INSERT INTO modulos (chave, nome, icone, grupo, grupo_ordem, ordem, no_menu, apenas_softsul, contexto, ativo) VALUES
('painel',        'Início',              'casa',       'Geral',         1, 1, 1, 0, 'ambos',      1),
('inventario',    'Inventário',          'mapa',       'Inventário',    2, 1, 1, 0, 'destino',    0),
('coleta',        'Coleta de campo',     'camera',     'Inventário',    2, 2, 1, 0, 'destino',    0),
('homologacao',   'Homologação',         'certificado','Inventário',    2, 3, 1, 0, 'destino',    0),
('caderno',       'Caderno oficial',     'manual',     'Inventário',    2, 4, 1, 0, 'destino',    0),
('phygital',      'Pontos de QR',        'qrcode',     'Destino',       3, 1, 1, 0, 'destino',    0),
('relatorios',    'Relatórios',          'manual',     'Gestão',        5, 1, 1, 0, 'destino',    0),
('cadastur',      'Cadastur',            'certificado','Consultas',     8, 1, 1, 0, 'ambos',      1),
('mapa-turismo',  'Mapa do Turismo',     'mapa',       'Consultas',     8, 2, 1, 0, 'ambos',      1),
('destinos',      'Destinos',            'predio',     'Plataforma',    9, 1, 1, 1, 'plataforma', 1),
('catalogos',     'Catálogos',           'etiqueta',   'Plataforma',    9, 2, 1, 1, 'plataforma', 0),
('usuarios',      'Usuários',            'pessoas',    'Administração', 11, 1, 0, 0, 'ambos',     1),
('auditoria',     'Auditoria',           'lista',      'Administração', 11, 2, 0, 0, 'ambos',     1),
('configuracoes', 'Configurações',       'engrenagem', 'Administração', 11, 3, 1, 0, 'ambos',     1);

-- Os cinco verbos para todo módulo.
INSERT INTO permissoes (modulo_id, chave, verbo)
SELECT m.id, CONCAT(m.chave, '.', v.verbo), v.verbo
FROM modulos m
CROSS JOIN (SELECT 'ver' AS verbo UNION ALL SELECT 'criar' UNION ALL SELECT 'editar' UNION ALL SELECT 'excluir' UNION ALL SELECT 'exportar') v;

-- Administrador da plataforma: tudo.
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p CROSS JOIN permissoes pe WHERE p.chave = 'plataforma_admin';

-- Suporte: só ver.
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p JOIN permissoes pe ON pe.verbo = 'ver' WHERE p.chave = 'plataforma_suporte';

-- Gestor: tudo o que é do destino; Cadastur e Mapa só para consultar.
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p
JOIN permissoes pe JOIN modulos m ON m.id = pe.modulo_id
WHERE p.chave = 'gestor' AND m.apenas_softsul = 0
  AND (m.chave NOT IN ('cadastur', 'mapa-turismo') OR pe.verbo = 'ver');

-- Técnico: o inventário inteiro (ver, criar, editar, exportar), sem excluir;
-- Início, consultas e o caderno.
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p
JOIN permissoes pe JOIN modulos m ON m.id = pe.modulo_id
WHERE p.chave = 'tecnico'
  AND ((m.chave IN ('inventario', 'coleta', 'homologacao', 'caderno', 'phygital') AND pe.verbo <> 'excluir')
    OR (m.chave IN ('painel', 'cadastur', 'mapa-turismo', 'relatorios') AND pe.verbo = 'ver'));

-- Pesquisador: coleta (ver, criar, editar) e consulta ao inventário.
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p
JOIN permissoes pe JOIN modulos m ON m.id = pe.modulo_id
WHERE p.chave = 'pesquisador'
  AND ((m.chave = 'coleta' AND pe.verbo IN ('ver', 'criar', 'editar'))
    OR (m.chave IN ('painel', 'inventario', 'cadastur', 'mapa-turismo') AND pe.verbo = 'ver'));

-- Leitor: só ver.
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p
JOIN permissoes pe JOIN modulos m ON m.id = pe.modulo_id
WHERE p.chave = 'leitor' AND pe.verbo = 'ver'
  AND m.chave IN ('painel', 'inventario', 'caderno', 'relatorios', 'cadastur', 'mapa-turismo');

-- Prestador: o Início e a ficha do próprio negócio (o escopo por ficha
-- chega com o módulo do inventário, no SQL, como o Escopo do EventCity).
INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT p.id, pe.id FROM papeis p
JOIN permissoes pe JOIN modulos m ON m.id = pe.modulo_id
WHERE p.chave = 'prestador'
  AND ((m.chave = 'painel' AND pe.verbo = 'ver') OR (m.chave = 'inventario' AND pe.verbo IN ('ver', 'editar')));
