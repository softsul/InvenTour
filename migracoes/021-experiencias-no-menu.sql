-- InvenTour: as experiências turísticas ganham tela própria no menu.
-- Softsul Software, 21/11/2026.
--
-- Até aqui a experiência só abria pela aba Experiências do item: não havia
-- lugar para ver todas, achar as que esperam curadoria nem cadastrar uma
-- sem antes abrir o item. O módulo novo fica no grupo Inventário, logo
-- abaixo dele, e herda as permissões do inventário: quem tem
-- inventario.ver passa a ter experiencias.ver, e assim por diante, por
-- papel e nas exceções individuais.

UPDATE modulos SET ordem = ordem + 1 WHERE grupo = 'Inventário' AND ordem >= 2;

INSERT INTO modulos (chave, nome, icone, grupo, grupo_ordem, ordem, no_menu, apenas_softsul, contexto, ativo) VALUES
('experiencias', 'Experiências', 'natureza', 'Inventário', 2, 2, 1, 0, 'destino', 1);

INSERT INTO permissoes (modulo_id, chave, verbo)
SELECT m.id, CONCAT(m.chave, '.', v.verbo), v.verbo
FROM modulos m
CROSS JOIN (SELECT 'ver' AS verbo UNION ALL SELECT 'criar' UNION ALL SELECT 'editar' UNION ALL SELECT 'excluir' UNION ALL SELECT 'exportar') v
WHERE m.chave = 'experiencias';

INSERT INTO papel_permissao (papel_id, permissao_id)
SELECT pp.papel_id, nova.id
FROM papel_permissao pp
JOIN permissoes inv ON inv.id = pp.permissao_id AND inv.chave LIKE 'inventario.%'
JOIN permissoes nova ON nova.chave = CONCAT('experiencias.', inv.verbo);

INSERT INTO usuario_permissao (usuario_id, empresa_id, permissao_id, concedida)
SELECT up.usuario_id, up.empresa_id, nova.id, up.concedida
FROM usuario_permissao up
JOIN permissoes inv ON inv.id = up.permissao_id AND inv.chave LIKE 'inventario.%'
JOIN permissoes nova ON nova.chave = CONCAT('experiencias.', inv.verbo);
