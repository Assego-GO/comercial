-- =====================================================
-- SCRIPT DE CRIAÇÃO - FLUXO DE DESFILIAÇÃO
-- =====================================================
-- Data: 2025-12-08
-- Descrição: Usar apenas Documentos_Associado para rastreamento
-- =====================================================

-- Nenhuma alteração necessária!
-- A tabela Documentos_Associado já tem:
-- - tipo_documento (VARCHAR) - usar 'ficha_desfiliacao'
-- - status_fluxo (VARCHAR) - usar para controlar aprovações
-- - departamento_atual (INT) - rastrear departamento responsável
-- - observacao (TEXT) - guardar comentários
-- - funcionario_id (INT) - quem fez upload

-- Exemplo de inserção de desfiliação:
/*
INSERT INTO Documentos_Associado (
    associado_id,
    tipo_documento,
    tipo_origem,
    nome_arquivo,
    caminho_arquivo,
    tamanho_arquivo,
    tipo_mime,
    data_upload,
    funcionario_id,
    departamento_atual,
    status_fluxo,
    observacao,
    verificado
) VALUES (
    123,
    'ficha_desfiliacao',
    'FISICO',
    'desfiliacao_123_1733671234.pdf',
    'uploads/documentos/desfiliacao/desfiliacao_123_1733671234.pdf',
    15230,
    'application/pdf',
    NOW(),
    45,
    1,
    'AGUARDANDO_APROVACAO',
    'Desfiliação solicitada',
    0
);
*/

-- =====================================================
-- FIM DO SCRIPT
-- =====================================================
