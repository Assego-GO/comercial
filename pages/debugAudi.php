<?php
/**
 * TESTE CRÍTICO DE SEGURANÇA
 * Testar se o sistema bloqueia alterações sem usuário logado
 * ou se ainda cai no problema "ANA PAULA GOVEIA"
 * 
 * Salve como: /api/teste_seguranca_sem_usuario.php
 */

// NÃO iniciar sessão intencionalmente para testar
// session_start();

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Auditoria.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // ===========================================
    // TESTE 1: VERIFICAR ESTADO DA SESSÃO
    // ===========================================
    
    $estadoSessao = [
        'session_status' => session_status(),
        'session_id' => session_id(),
        'funcionario_id_sessao' => $_SESSION['funcionario_id'] ?? 'NÃO EXISTE',
        'nome_sessao' => $_SESSION['funcionario_nome'] ?? 'NÃO EXISTE',
        'tipo_usuario_sessao' => $_SESSION['tipo_usuario'] ?? 'NÃO EXISTE'
    ];
    
    // ===========================================
    // TESTE 2: TENTAR USAR AUTH SEM ESTAR LOGADO
    // ===========================================
    
    $testeAuth = [];
    try {
        $auth = new Auth();
        $testeAuth['isLoggedIn'] = $auth->isLoggedIn();
        $testeAuth['getUser'] = $auth->getUser();
        $testeAuth['erro_auth'] = null;
    } catch (Exception $e) {
        $testeAuth['isLoggedIn'] = false;
        $testeAuth['getUser'] = null;
        $testeAuth['erro_auth'] = $e->getMessage();
    }
    
    // ===========================================
    // TESTE 3: TENTAR REGISTRAR AUDITORIA SEM USUÁRIO
    // ===========================================
    
    $testeAuditoriaSemUsuario = [];
    try {
        $auditoria = new Auditoria();
        
        // Tentar registrar sem passar funcionario_id
        $auditoriaId1 = $auditoria->registrar([
            'tabela' => 'TESTE_SEGURANCA',
            'acao' => 'TESTE_SEM_USUARIO',
            'registro_id' => 9999,
            'detalhes' => [
                'teste_tipo' => 'sem_funcionario_id',
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        
        $testeAuditoriaSemUsuario['registro_sem_funcionario_id'] = [
            'sucesso' => ($auditoriaId1 !== false),
            'auditoria_id' => $auditoriaId1
        ];
        
        // Tentar registrar passando funcionario_id = null explicitamente
        $auditoriaId2 = $auditoria->registrar([
            'tabela' => 'TESTE_SEGURANCA',
            'acao' => 'TESTE_FUNCIONARIO_NULL',
            'registro_id' => 9998,
            'funcionario_id' => null,
            'detalhes' => [
                'teste_tipo' => 'funcionario_id_null',
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        
        $testeAuditoriaSemUsuario['registro_funcionario_null'] = [
            'sucesso' => ($auditoriaId2 !== false),
            'auditoria_id' => $auditoriaId2
        ];
        
        $testeAuditoriaSemUsuario['erro_auditoria'] = null;
        
    } catch (Exception $e) {
        $testeAuditoriaSemUsuario['erro_auditoria'] = $e->getMessage();
        $testeAuditoriaSemUsuario['registro_sem_funcionario_id'] = ['sucesso' => false, 'auditoria_id' => null];
        $testeAuditoriaSemUsuario['registro_funcionario_null'] = ['sucesso' => false, 'auditoria_id' => null];
    }
    
    // ===========================================
    // TESTE 4: VERIFICAR REGISTROS CRIADOS
    // ===========================================
    
    $registrosCriados = [];
    
    // Buscar registros criados nos últimos 5 minutos
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.funcionario_id,
            a.acao,
            a.tabela,
            a.data_hora,
            COALESCE(f.nome, ass.nome, 'SISTEMA/NULL') as nome_responsavel,
            CASE 
                WHEN f.id IS NOT NULL THEN 'funcionario'
                WHEN ass.id IS NOT NULL THEN 'associado'  
                ELSE 'sem_identificacao'
            END as tipo_responsavel
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Associados ass ON a.funcionario_id = ass.id AND f.id IS NULL
        WHERE a.tabela = 'TESTE_SEGURANCA'
        AND a.data_hora >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY a.id DESC
    ");
    $stmt->execute();
    $registrosCriados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===========================================
    // TESTE 5: BUSCAR REGISTROS COM ANA PAULA RECENTES
    // ===========================================
    
    $registrosAnaPaula = [];
    $stmt = $db->prepare("
        SELECT a.id, a.acao, a.tabela, a.data_hora, a.funcionario_id
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        WHERE f.nome = 'ANA PAULA GOVEIA'
        AND a.data_hora >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY a.data_hora DESC
    ");
    $stmt->execute();
    $registrosAnaPaula = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===========================================
    // TESTE 6: VERIFICAR REGISTROS NULL/SISTEMA
    // ===========================================
    
    $registrosSistema = [];
    $stmt = $db->prepare("
        SELECT 
            a.id, a.funcionario_id, a.acao, a.tabela, a.data_hora
        FROM Auditoria a
        WHERE a.funcionario_id IS NULL
        AND a.data_hora >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY a.data_hora DESC
        LIMIT 5
    ");
    $stmt->execute();
    $registrosSistema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===========================================
    // ANÁLISE DE SEGURANÇA
    // ===========================================
    
    $problemas = [];
    $sucessos = [];
    
    // Verificar se registros foram criados sem usuário
    if ($testeAuditoriaSemUsuario['registro_sem_funcionario_id']['sucesso']) {
        $problemas[] = "CRÍTICO: Sistema permite registro de auditoria sem usuário logado";
    } else {
        $sucessos[] = "Sistema bloqueia corretamente registros sem usuário";
    }
    
    // Verificar ANA PAULA recente
    if (count($registrosAnaPaula) > 0) {
        $problemas[] = "CRÍTICO: Ainda há " . count($registrosAnaPaula) . " registros com ANA PAULA na última hora";
    } else {
        $sucessos[] = "Nenhum registro novo com ANA PAULA";
    }
    
    // Verificar registros NULL
    if (count($registrosSistema) > 0) {
        $problemas[] = "ATENÇÃO: " . count($registrosSistema) . " registros com funcionario_id NULL";
    } else {
        $sucessos[] = "Nenhum registro com funcionario_id NULL recente";
    }
    
    // Status de segurança
    $statusSeguranca = empty($problemas) ? 'SEGURO' : 
                      (count($sucessos) > count($problemas) ? 'PARCIALMENTE_SEGURO' : 'INSEGURO');
    
    // ===========================================
    // RESPOSTA FINAL
    // ===========================================
    
    echo json_encode([
        'teste_executado_em' => date('Y-m-d H:i:s'),
        'status_seguranca' => $statusSeguranca,
        
        'estado_sessao' => $estadoSessao,
        'teste_auth' => $testeAuth,
        'teste_auditoria_sem_usuario' => $testeAuditoriaSemUsuario,
        
        'registros_criados_teste' => $registrosCriados,
        'total_registros_teste' => count($registrosCriados),
        
        'registros_ana_paula_recentes' => $registrosAnaPaula,
        'total_ana_paula' => count($registrosAnaPaula),
        
        'registros_sistema_null' => $registrosSistema,
        'total_sistema_null' => count($registrosSistema),
        
        'analise_seguranca' => [
            'sucessos' => $sucessos,
            'problemas' => $problemas,
            'nivel_risco' => match($statusSeguranca) {
                'SEGURO' => 'BAIXO - Sistema protegido adequadamente',
                'PARCIALMENTE_SEGURO' => 'MÉDIO - Algumas vulnerabilidades presentes',
                'INSEGURO' => 'ALTO - Vulnerabilidades críticas detectadas',
                default => 'INDETERMINADO'
            }
        ],
        
        'recomendacoes' => [
            'se_inseguro' => 'Aplicar correções na classe Auditoria para bloquear registros sem usuário',
            'se_ana_paula_presente' => 'Verificar e corrigir códigos que ainda usam fallback para ANA PAULA',
            'se_registros_null' => 'Investigar origem de registros com funcionario_id NULL',
            'geral' => 'Executar teste após fazer login para comparar comportamento'
        ],
        
        'proximo_passo' => 'Execute este mesmo teste APÓS fazer login para ver a diferença'
        
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'erro_critico' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'status_seguranca' => 'ERRO_TESTE'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>