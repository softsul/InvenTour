-- InvenTour: o redator escolhe como se chega a cada parada do roteiro.
-- Softsul Software, 21/11/2026.
--
-- A pé, de carro, de bicicleta ou de ônibus. Vazio: sugestão pela distância
-- (a pé até 1,2 km, de carro acima). O tempo estimado segue o meio.

ALTER TABLE roteiro_itens
    ADD COLUMN meio ENUM('pe','carro','bicicleta','onibus') NULL COMMENT 'como se chega a esta parada; vazio = pela distância' AFTER deslocamento_min;
