-- =====================================================
-- MIGRAÇÃO 009: Criar tabela Auditoria_Detalhes
-- Data: 2025-12-16
-- Descrição: Tabela para armazenar detalhes das alterações
--            de forma estruturada (campo a campo)
-- =====================================================

-- Verificar se a tabela já existe
CREATE TABLE IF NOT EXISTS `Auditoria_Detalhes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `auditoria_id` int(11) NOT NULL COMMENT 'FK para Auditoria.id',
  `campo` varchar(100) NOT NULL COMMENT 'Nome do campo alterado',
  `valor_anterior` text DEFAULT NULL COMMENT 'Valor antes da alteração',
  `valor_novo` text DEFAULT NULL COMMENT 'Valor depois da alteração',
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_id` (`auditoria_id`),
  KEY `idx_campo` (`campo`),
  KEY `idx_criado_em` (`criado_em`),
  CONSTRAINT `fk_auditoria_detalhes_auditoria` 
    FOREIGN KEY (`auditoria_id`) 
    REFERENCES `Auditoria` (`id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Detalhes estruturados das alterações de auditoria';

-- Verificar se a coluna alteracoes na tabela Auditoria suporta JSON grande
ALTER TABLE `Auditoria` 
  MODIFY COLUMN `alteracoes` LONGTEXT DEFAULT NULL COMMENT 'JSON com alterações realizadas';

-- Adicionar índice para melhorar consultas
CREATE INDEX IF NOT EXISTS `idx_auditoria_alteracoes_type` 
  ON `Auditoria` (`tabela`, `acao`, `data_hora`);

-- Log de sucesso
SELECT 'Migração 009 executada com sucesso - Auditoria_Detalhes criada' AS resultado;
