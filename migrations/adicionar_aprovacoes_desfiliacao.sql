-- =====================================================
-- MIGRATION: ADICIONAR RASTREAMENTO DE APROVAÇÕES
-- DESFILIAÇÃO - FLUXO SEQUENCIAL
-- =====================================================
-- Data: 2025-12-09
-- Objetivo: Fluxo sequencial de aprovações:
-- 1. Financeiro (OBRIGATÓRIO)
-- 2. Jurídico (CONDICIONAL - se associado tem serviço jurídico)
-- 3. Presidência (OBRIGATÓRIO - aprovação final)
-- =====================================================

-- Tabela para rastrear aprovações por departamento (sequencial)
CREATE TABLE IF NOT EXISTS Aprovacoes_Desfiliacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    departamento_id INT NOT NULL,
    departamento_nome VARCHAR(100),
    ordem_aprovacao INT NOT NULL COMMENT 'Ordem sequencial: 1=Financeiro, 2=Jurídico, 3=Presidência',
    obrigatorio TINYINT(1) DEFAULT 1 COMMENT '1=Obrigatório, 0=Condicional',
    status_aprovacao ENUM('PENDENTE', 'APROVADO', 'REJEITADO') DEFAULT 'PENDENTE',
    funcionario_id INT,
    funcionario_nome VARCHAR(100),
    data_acao TIMESTAMP NULL,
    observacao TEXT,
    INDEX idx_documento (documento_id),
    INDEX idx_departamento (departamento_id),
    INDEX idx_status (status_aprovacao),
    INDEX idx_ordem (ordem_aprovacao),
    UNIQUE KEY unique_doc_dept (documento_id, departamento_id),
    FOREIGN KEY (documento_id) REFERENCES Documentos_Associado(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Adicionar colunas em Documentos_Associado (ignora erro se já existir)
SET @sql1 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'Documentos_Associado' 
     AND COLUMN_NAME = 'requer_aprovacao_multi') = 0,
    'ALTER TABLE Documentos_Associado ADD COLUMN requer_aprovacao_multi TINYINT(1) DEFAULT 0',
    'SELECT "Column requer_aprovacao_multi already exists" AS message'
);
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @sql2 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'Documentos_Associado' 
     AND COLUMN_NAME = 'data_finalizacao') = 0,
    'ALTER TABLE Documentos_Associado ADD COLUMN data_finalizacao DATETIME',
    'SELECT "Column data_finalizacao already exists" AS message'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Adicionar índices (ignora erro se já existirem)
SET @sql3 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'Documentos_Associado' 
     AND INDEX_NAME = 'idx_tipo_documento') = 0,
    'ALTER TABLE Documentos_Associado ADD INDEX idx_tipo_documento (tipo_documento)',
    'SELECT "Index idx_tipo_documento already exists" AS message'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

SET @sql4 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'Documentos_Associado' 
     AND INDEX_NAME = 'idx_status_fluxo') = 0,
    'ALTER TABLE Documentos_Associado ADD INDEX idx_status_fluxo (status_fluxo)',
    'SELECT "Index idx_status_fluxo already exists" AS message'
);
PREPARE stmt4 FROM @sql4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

-- =====================================================
-- FUNÇÃO: Obter próxima etapa no fluxo sequencial
-- =====================================================
-- FUNÇÃO: Obter próxima etapa no fluxo sequencial
-- =====================================================
DROP FUNCTION IF EXISTS obter_proxima_etapa_desfiliacao;

DELIMITER $$

CREATE FUNCTION obter_proxima_etapa_desfiliacao(p_documento_id INT)
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_proxima_ordem INT;
    
    -- Buscar a próxima etapa pendente (menor ordem ainda não aprovada)
    SELECT MIN(ordem_aprovacao) INTO v_proxima_ordem
    FROM Aprovacoes_Desfiliacao
    WHERE documento_id = p_documento_id
    AND status_aprovacao = 'PENDENTE';
    
    -- Se não há pendentes, retorna 999 (finalizado)
    IF v_proxima_ordem IS NULL THEN
        RETURN 999;
    END IF;
    
    RETURN v_proxima_ordem;
END$$

DELIMITER ;

-- =====================================================
-- FUNÇÃO: Verificar se fluxo está completo
-- =====================================================
DROP FUNCTION IF EXISTS verificar_fluxo_completo;

DELIMITER $$

CREATE FUNCTION verificar_fluxo_completo(p_documento_id INT)
RETURNS VARCHAR(20)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_rejeitadas INT;
    DECLARE v_pendentes INT;
    
    -- Verificar se há rejeições
    SELECT COUNT(*) INTO v_rejeitadas
    FROM Aprovacoes_Desfiliacao
    WHERE documento_id = p_documento_id
    AND status_aprovacao = 'REJEITADO';
    
    IF v_rejeitadas > 0 THEN
        RETURN 'REJEITADO';
    END IF;
    
    -- Verificar se há pendentes
    SELECT COUNT(*) INTO v_pendentes
    FROM Aprovacoes_Desfiliacao
    WHERE documento_id = p_documento_id
    AND status_aprovacao = 'PENDENTE';
    
    IF v_pendentes > 0 THEN
        RETURN 'EM_APROVACAO';
    END IF;
    
    -- Se não há rejeições nem pendentes, está finalizado
    RETURN 'FINALIZADO';
END$$

DELIMITER ;

-- =====================================================
-- PROCEDURE: Criar fluxo de aprovação para desfiliação
-- =====================================================
DROP PROCEDURE IF EXISTS criar_fluxo_desfiliacao;

DELIMITER $$

CREATE PROCEDURE criar_fluxo_desfiliacao(
    IN p_documento_id INT,
    IN p_associado_id INT
)
BEGIN
    DECLARE v_dept_financeiro INT;
    DECLARE v_dept_juridico INT;
    DECLARE v_dept_presidencia INT;
    DECLARE v_tem_servico_juridico INT DEFAULT 0;
    
    -- Buscar IDs dos departamentos
    SELECT id INTO v_dept_financeiro FROM Departamentos WHERE nome = 'Financeiro' LIMIT 1;
    SELECT id INTO v_dept_juridico FROM Departamentos WHERE nome = 'Jurídico' LIMIT 1;
    SELECT id INTO v_dept_presidencia FROM Departamentos WHERE nome = 'Presidência' LIMIT 1;
    
    -- Verificar se associado tem serviço jurídico (servico_id = 2)
    SELECT COUNT(*) INTO v_tem_servico_juridico
    FROM Servicos_Associado
    WHERE associado_id = p_associado_id
    AND servico_id = 2
    LIMIT 1;
    
    -- 1. FINANCEIRO (obrigatório, ordem 1)
    IF v_dept_financeiro IS NOT NULL THEN
        INSERT INTO Aprovacoes_Desfiliacao (
            documento_id, departamento_id, departamento_nome, 
            ordem_aprovacao, obrigatorio, status_aprovacao
        ) VALUES (
            p_documento_id, v_dept_financeiro, 'Financeiro',
            1, 1, 'PENDENTE'
        )
        ON DUPLICATE KEY UPDATE ordem_aprovacao = 1;
    END IF;
    
    -- 2. JURÍDICO (condicional, ordem 2 - só se tem serviço jurídico)
    IF v_dept_juridico IS NOT NULL AND v_tem_servico_juridico > 0 THEN
        INSERT INTO Aprovacoes_Desfiliacao (
            documento_id, departamento_id, departamento_nome,
            ordem_aprovacao, obrigatorio, status_aprovacao
        ) VALUES (
            p_documento_id, v_dept_juridico, 'Jurídico',
            2, 1, 'PENDENTE'
        )
        ON DUPLICATE KEY UPDATE ordem_aprovacao = 2;
    END IF;
    
    -- 3. PRESIDÊNCIA (obrigatório, ordem 3 - aprovação final)
    IF v_dept_presidencia IS NOT NULL THEN
        INSERT INTO Aprovacoes_Desfiliacao (
            documento_id, departamento_id, departamento_nome,
            ordem_aprovacao, obrigatorio, status_aprovacao
        ) VALUES (
            p_documento_id, v_dept_presidencia, 'Presidência',
            3, 1, 'PENDENTE'
        )
        ON DUPLICATE KEY UPDATE ordem_aprovacao = 3;
    END IF;
    
END$$

DELIMITER ;

-- =====================================================
-- FIM DA MIGRATION
-- =====================================================
