-- InvenTour: plus code do item (código de localização aberto do Google).
-- Softsul Software, 20/11/2026.
--
-- O caderno de Pato Branco 2025 traz o plus code de quase todo lugar
-- (Q8FF+VM). Guardado como veio; a latitude e a longitude saem dele sem
-- custo (app/PlusCode.php), usando a coordenada do município como referência
-- para o código curto.
ALTER TABLE inventario_itens
    ADD COLUMN plus_code VARCHAR(16) NULL COMMENT 'Open Location Code, curto (Q8FF+VM) ou completo' AFTER longitude;
