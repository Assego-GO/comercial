-- ============================================
-- SCRIPT DE CORREÇÃO: PRÉ-CADASTROS
-- ============================================
-- Execure este SQL no phpMyAdmin ou cliente MySQL
-- Database: wwasse_cadastro
-- Data: 2025-12-12

-- 1. CORRIGIR ASSOCIADOS EM PRÉ-CADASTRO QUE ESTÃO COMO "DESFILIADO"
UPDATE Associados 
SET situacao = 'Filiado'
WHERE pre_cadastro = 1 
  AND situacao IN ('Desfiliado', 'Pendente', '');

-- 2. VERIFICAR RESULTADO
SELECT 
    id,
    nome,
    cpf,
    situacao,
    pre_cadastro,
    data_pre_cadastro,
    DATE_FORMAT(data_pre_cadastro, '%d/%m/%Y %H:%i') as data_formatada
FROM Associados
WHERE pre_cadastro = 1
ORDER BY id DESC
LIMIT 20;

-- 3. ESTATÍSTICAS
SELECT 
    situacao,
    pre_cadastro,
    COUNT(*) as total
FROM Associados
GROUP BY situacao, pre_cadastro
ORDER BY pre_cadastro DESC, situacao;
