-- A sede do município também vem do OpenStreetMap (o lugar com o nome do
-- município): a coordenada da tabela municipios fica a quilômetros do
-- centro em alguns casos (Pato Branco, ~7 km). Ela segue como reserva.
-- Softsul Software
ALTER TABLE destino_transporte
    MODIFY COLUMN tipo ENUM('aeroporto','rodoviaria','ferroviaria','maritima','metroviaria','ponto_onibus','ponto_taxi','sede') NOT NULL;
