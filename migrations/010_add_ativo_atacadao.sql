-- =====================================================
-- MIGRAÇÃO 010: Adicionar coluna ativo_atacadao em Associados
-- Data: 2025-12-18
-- Descrição: Flag de integração com Atacadão Dia a Dia
--            1 = Ativo/Cadastrado com sucesso na API
--            0 = Inativo/Não cadastrado ou falha
-- =====================================================

ALTER TABLE `Associados`
  ADD COLUMN `ativo_atacadao` TINYINT(1) NOT NULL DEFAULT 0 
    COMMENT '1 = Atacadão ativo, 0 = Inativo ou falha',
  ADD INDEX `idx_ativo_atacadao` (`ativo_atacadao`);

-- Log de sucesso
SELECT 'Migração 010 executada com sucesso - ativo_atacadao adicionado' AS resultado;
