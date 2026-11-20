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
