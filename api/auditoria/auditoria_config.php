<?php
/**
 * Configurações específicas do sistema de auditoria
 * /config/auditoria_config.php
 */

// Configurações de Auditoria
define('AUDITORIA_CONFIG', [
    
    // === CONFIGURAÇÕES GERAIS ===
    'ativo' => true,                    // Ativar/desativar auditoria globalmente
    'debug_mode' => false,              // Modo debug (logs detalhados)
    'auto_cleanup' => true,             // Limpeza automática ativada
    
    // === RETENÇÃO DE DADOS ===
    'retencao' => [
        'padrao_dias' => 365,           // Retenção padrão (1 ano)
        'minimo_dias' => 30,            // Mínimo para limpeza manual
        'maximo_dias' => 3650,          // Máximo permitido (10 anos)
        'criticos_dias' => 2555,        // Registros críticos (7 anos)
    ],
    
    // === AÇÕES AUDITADAS ===
    'acoes_auditadas' => [
        'INSERT' => true,               // Criação de registros
        'UPDATE' => true,               // Atualização de registros
        'DELETE' => true,               // Exclusão de registros
        'LOGIN' => true,                // Login de usuários
        'LOGOUT' => true,               // Logout de usuários
        'LOGIN_FALHA' => true,