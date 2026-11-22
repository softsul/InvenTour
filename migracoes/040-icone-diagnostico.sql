-- O módulo Diagnóstico DTI saiu com o ícone 'emblema', que não existe no
-- conjunto de ícones (é o nome do logotipo): o menu ficava sem ícone.
-- A distinção DTI é um selo, então fica com o certificado.
-- Softsul Software
UPDATE modulos SET icone = 'certificado' WHERE chave = 'diagnostico' AND icone = 'emblema';
