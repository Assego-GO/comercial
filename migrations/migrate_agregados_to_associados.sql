-- ============================================================================
-- SCRIPT DE MIGRAÇÃO: Socios_Agregados -> Associados
-- Data: 2025-12-01
-- Objetivo: Unificar agregados na tabela Associados
-- ============================================================================

-- PASSO 1: Adicionar campo para vincular agregados aos titulares
-- ============================================================================

ALTER TABLE Associados 
ADD COLUMN IF NOT EXISTS associado_titular_id INT NULL 
    COMMENT 'ID do associado titular (para agregados)',
ADD CONSTRAINT fk_associado_titular 
    FOREIGN KEY (associado_titular_id) 
    REFERENCES Associados(id) 
    ON DELETE SET NULL;

-- Índice para melhor performance
CREATE INDEX IF NOT EXISTS idx_associado_titular ON Associados(associado_titular_id);

-- ============================================================================
-- PASSO 2: MIGRAR DADOS DE Socios_Agregados PARA Associados
-- ============================================================================

START TRANSACTION;

-- 2.1. Migrar agregados para Associados
INSERT INTO Associados (
    nome, 
    nasc, 
    cpf, 
    rg,
    email, 
    telefone,
    estadoCivil,
    situacao,
    data_filiacao,
    pre_cadastro,
    data_pre_cadastro,
    associado_titular_id
)
SELECT 
    sa.nome,
    sa.data_nascimento,
    sa.cpf,
    sa.documento,
    sa.email,
    COALESCE(sa.celular, sa.telefone),
    sa.estado_civil,
    CASE 
        WHEN sa.situacao = 'ativo' THEN 'Filiado'
        WHEN sa.situacao = 'inativo' THEN 'Desfiliado'
        ELSE 'Filiado'
    END,
    sa.data_filiacao,
    0, -- Não é pré-cadastro
    sa.data_criacao,
    sa.associado_id -- Vínculo com o titular (se existir)
FROM Socios_Agregados sa
WHERE sa.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM Associados a WHERE a.cpf = sa.cpf
);

-- 2.2. Migrar dados militares (Corporação = Agregados)
INSERT INTO Militar (associado_id, corporacao, patente, categoria)
SELECT 
    a.id,
    'Agregados',
    'Agregado',
    'Agregado'
FROM Associados a
INNER JOIN Socios_Agregados sa ON a.cpf = sa.cpf
WHERE NOT EXISTS (
    SELECT 1 FROM Militar m WHERE m.associado_id = a.id
);

-- 2.3. Migrar endereços
INSERT INTO Endereco (associado_id, cep, endereco, numero, bairro, cidade, complemento)
SELECT 
    a.id,
    sa.cep,
    sa.endereco,
    sa.numero,
    sa.bairro,
    sa.cidade,
    sa.complemento
FROM Associados a
INNER JOIN Socios_Agregados sa ON a.cpf = sa.cpf
WHERE sa.cep IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM Endereco e WHERE e.associado_id = a.id
);

-- 2.4. Migrar dados financeiros
INSERT INTO Financeiro (
    associado_id,
    tipoAssociado,
    situacaoFinanceira,
    vinculoServidor,
    agencia,
    contaCorrente,
    observacoes
)
SELECT 
    a.id,
    'Agregado',
    CASE 
        WHEN sa.situacao = 'ativo' THEN 'Adimplente'
        ELSE 'Inadimplente'
    END,
    'Agregado',
    sa.agencia,
    sa.conta_corrente,
    sa.observacoes
FROM Associados a
INNER JOIN Socios_Agregados sa ON a.cpf = sa.cpf
WHERE NOT EXISTS (
    SELECT 1 FROM Financeiro f WHERE f.associado_id = a.id
);

-- ============================================================================
-- PASSO 3: MIGRAR DOCUMENTOS DE Documentos_Agregado PARA Documentos_Associado
-- ============================================================================

-- 3.1. Migrar documentos
INSERT INTO Documentos_Associado (
    associado_id,
    tipo_documento,
    tipo_origem,
    nome_arquivo,
    caminho_arquivo,
    status_fluxo,
    departamento_atual,
    data_upload,
    funcionario_id,
    zapsign_doc_id,
    zapsign_status
)
SELECT 
    a.id,
    da.tipo_documento,
    COALESCE(da.tipo_origem, 'FISICO'),
    da.nome_arquivo,
    da.caminho_arquivo,
    COALESCE(da.status_fluxo, 'DIGITALIZADO'),
    da.departamento_atual,
    COALESCE(da.data_upload, NOW()),
    da.funcionario_id,
    da.zapsign_doc_id,
    da.zapsign_status
FROM Associados a
INNER JOIN Socios_Agregados sa ON a.cpf = sa.cpf
INNER JOIN Documentos_Agregado da ON da.agregado_id = sa.id
WHERE NOT EXISTS (
    SELECT 1 
    FROM Documentos_Associado doc 
    WHERE doc.associado_id = a.id 
    AND doc.tipo_documento = da.tipo_documento
    AND doc.caminho_arquivo = da.caminho_arquivo
);

COMMIT;

-- ============================================================================
-- PASSO 4: VERIFICAÇÃO DA MIGRAÇÃO
-- ============================================================================

-- Verificar agregados migrados
SELECT 
    'AGREGADOS MIGRADOS' AS status,
    COUNT(*) AS total,
    COUNT(DISTINCT a.associado_titular_id) AS com_titular
FROM Associados a
INNER JOIN Militar m ON a.id = m.associado_id
WHERE m.corporacao = 'Agregados';

-- Verificar documentos migrados
SELECT 
    'DOCUMENTOS MIGRADOS' AS status,
    COUNT(*) AS total
FROM Documentos_Associado da
INNER JOIN Associados a ON da.associado_id = a.id
INNER JOIN Militar m ON a.id = m.associado_id
WHERE m.corporacao = 'Agregados';

-- Verificar se ainda há dados não migrados
SELECT 
    'AGREGADOS NÃO MIGRADOS' AS status,
    COUNT(*) AS total
FROM Socios_Agregados sa
WHERE sa.ativo = 1
AND NOT EXISTS (
    SELECT 1 FROM Associados a WHERE a.cpf = sa.cpf
);

-- ============================================================================
-- PASSO 5: BACKUP DAS TABELAS ANTIGAS (Executar após confirmar migração)
-- ============================================================================

-- ATENÇÃO: Só executar após confirmar que a migração foi bem-sucedida!
-- Descomente as linhas abaixo quando estiver pronto

-- CREATE TABLE IF NOT EXISTS Socios_Agregados_BACKUP_20251201 AS SELECT * FROM Socios_Agregados;
-- CREATE TABLE IF NOT EXISTS Documentos_Agregado_BACKUP_20251201 AS SELECT * FROM Documentos_Agregado;

-- ============================================================================
-- PASSO 6: REMOVER TABELAS ANTIGAS (Executar SOMENTE após backup confirmado)
-- ============================================================================

-- ATENÇÃO: Esta ação é IRREVERSÍVEL!
-- Descomente as linhas abaixo quando tiver certeza

-- DROP TABLE IF EXISTS Documentos_Agregado;
-- DROP TABLE IF EXISTS Socios_Agregados;

-- ============================================================================
-- FIM DA MIGRAÇÃO
-- ============================================================================
