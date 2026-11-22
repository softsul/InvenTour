-- InvenTour, etapa 7: o módulo Relatórios, que estava cadastrado e
-- desligado desde a fundação, passa a valer (app/Relatorio.php, o mesmo
-- motor do EventCity). Softsul Software, 22/11/2026.
UPDATE modulos SET ativo = 1 WHERE chave = 'relatorios';
