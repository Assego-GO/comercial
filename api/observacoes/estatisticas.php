<?php
/**
 * ========================================
 * ARQUIVO: /api/observacoes/estatisticas.php
 * Endpoint para buscar estatísticas de observações
 * ========================================
 */

// Configurações e includes
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Associados.php';

// Headers para JSON e CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Se for requisição OPTIONS (preflight), retornar apenas headers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Não autorizado. Faça login para continuar.'
    ]);
    exit;
}

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido. Use GET.'
    ]);
    exit;
}

// Pegar parâmetros
$associadoId = filter_input(INPUT_GET, 'associado_id', FILTER_VALIDATE_INT);
$periodo = filter_input(INPUT_GET, 'periodo', FILTER_SANITIZE_STRING) ?: '30'; // dias

// Se associado_id foi fornecido, buscar estatísticas específicas
// Senão, buscar estatísticas gerais do sistema
if ($associadoId) {
    // ===== ESTATÍSTICAS POR ASSOCIADO =====
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Verificar se o associado existe
        $stmt = $db->prepare("SELECT id, nome, cpf FROM Associados WHERE id = ?");
        $stmt->execute([$associadoId]);
        $associado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$associado) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Associado não encontrado'
            ]);
            exit;
        }
        
        // Buscar estatísticas usando a classe
        $associados = new Associados();
        $estatisticas = $associados->getEstatisticasObservacoes($associadoId);
        
        // Buscar dados adicionais
        $stmt = $db->prepare("
            SELECT 
                -- Por categoria
                COUNT(CASE WHEN categoria = 'geral' THEN 1 END) as cat_geral,
                COUNT(CASE WHEN categoria = 'financeiro' THEN 1 END) as cat_financeiro,
                COUNT(CASE WHEN categoria = 'documentacao' THEN 1 END) as cat_documentacao,
                COUNT(CASE WHEN categoria = 'atendimento' THEN 1 END) as cat_atendimento,
                COUNT(CASE WHEN categoria = 'pendencia' THEN 1 END) as cat_pendencia,
                COUNT(CASE WHEN categoria = 'importante' THEN 1 END) as cat_importante,
                
                -- Por prioridade
                COUNT(CASE WHEN prioridade = 'baixa' THEN 1 END) as pri_baixa,
                COUNT(CASE WHEN prioridade = 'media' THEN 1 END) as pri_media,
                COUNT(CASE WHEN prioridade = 'alta' THEN 1 END) as pri_alta,
                COUNT(CASE WHEN prioridade = 'urgente' THEN 1 END) as pri_urgente,
                
                -- Por período
                COUNT(CASE WHEN data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as ultimos_7_dias,
                COUNT(CASE WHEN data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as ultimos_30_dias,
                COUNT(CASE WHEN data_criacao >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 END) as ultimos_90_dias,
                
                -- Outras métricas
                COUNT(CASE WHEN editado = 1 THEN 1 END) as total_editadas,
                MIN(data_criacao) as primeira_observacao,
                MAX(data_criacao) as ultima_observacao
                
            FROM Observacoes_Associado
            WHERE associado_id = ? AND ativo = 1
        ");
        $stmt->execute([$associadoId]);
        $detalhes = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Buscar últimas observações
        $stmt = $db->prepare("
            SELECT 
                o.id,
                o.observacao,
                o.categoria,
                o.prioridade,
                o.importante,
                DATE_FORMAT(o.data_criacao, '%d/%m/%Y %H:%i') as data_formatada,
                f.nome as criado_por
            FROM Observacoes_Associado o
            LEFT JOIN Funcionarios f ON o.criado_por = f.id
            WHERE o.associado_id = ? AND o.ativo = 1
            ORDER BY o.data_criacao DESC
            LIMIT 5
        ");
        $stmt->execute([$associadoId]);
        $ultimasObservacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Montar resposta
        $response = [
            'status' => 'success',
            'data' => [
                'associado' => $associado,
                'resumo' => $estatisticas,
                'categorias' => [
                    'geral' => $detalhes['cat_geral'],
                    'financeiro' => $detalhes['cat_financeiro'],
                    'documentacao' => $detalhes['cat_documentacao'],
                    'atendimento' => $detalhes['cat_atendimento'],
                    'pendencia' => $detalhes['cat_pendencia'],
                    'importante' => $detalhes['cat_importante']
                ],
                'prioridades' => [
                    'baixa' => $detalhes['pri_baixa'],
                    'media' => $detalhes['pri_media'],
                    'alta' => $detalhes['pri_alta'],
                    'urgente' => $detalhes['pri_urgente']
                ],
                'timeline' => [
                    '7_dias' => $detalhes['ultimos_7_dias'],
                    '30_dias' => $detalhes['ultimos_30_dias'],
                    '90_dias' => $detalhes['ultimos_90_dias']
                ],
                'edicoes' => $detalhes['total_editadas'],
                'periodo' => [
                    'primeira' => $detalhes['primeira_observacao'],
                    'ultima' => $detalhes['ultima_observacao']
                ],
                'ultimas_observacoes' => $ultimasObservacoes
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas do associado: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao buscar estatísticas'
        ]);
        exit;
    }
    
} else {
    // ===== ESTATÍSTICAS GERAIS DO SISTEMA =====
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        $usuarioAtual = $auth->getUser();
        
        // Estatísticas gerais
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT o.id) as total_observacoes,
                COUNT(DISTINCT o.associado_id) as total_associados_com_obs,
                COUNT(DISTINCT o.criado_por) as total_funcionarios,
                COUNT(CASE WHEN o.importante = 1 THEN 1 END) as total_importantes,
                COUNT(CASE WHEN o.categoria = 'pendencia' THEN 1 END) as total_pendencias,
                COUNT(CASE WHEN o.prioridade IN ('alta', 'urgente') THEN 1 END) as total_prioritarias,
                COUNT(CASE WHEN o.editado = 1 THEN 1 END) as total_editadas,
                COUNT(CASE WHEN o.data_criacao >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as novas_periodo
            FROM Observacoes_Associado o
            WHERE o.ativo = 1
        ");
        $stmt->execute([$periodo]);
        $estatisticasGerais = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Top funcionários por observações criadas
        $stmt = $db->prepare("
            SELECT 
                f.id,
                f.nome,
                f.cargo,
                COUNT(o.id) as total_observacoes,
                COUNT(CASE WHEN o.importante = 1 THEN 1 END) as importantes
            FROM Funcionarios f
            JOIN Observacoes_Associado o ON f.id = o.criado_por
            WHERE o.ativo = 1
            GROUP BY f.id
            ORDER BY total_observacoes DESC
            LIMIT 10
        ");
        $stmt->execute();
        $topFuncionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Distribuição por categoria
        $stmt = $db->prepare("
            SELECT 
                categoria,
                COUNT(*) as total,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM Observacoes_Associado WHERE ativo = 1), 2) as percentual
            FROM Observacoes_Associado
            WHERE ativo = 1
            GROUP BY categoria
            ORDER BY total DESC
        ");
        $stmt->execute();
        $distribuicaoCategoria = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Distribuição por prioridade
        $stmt = $db->prepare("
            SELECT 
                prioridade,
                COUNT(*) as total,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM Observacoes_Associado WHERE ativo = 1), 2) as percentual
            FROM Observacoes_Associado
            WHERE ativo = 1
            GROUP BY prioridade
            ORDER BY FIELD(prioridade, 'urgente', 'alta', 'media', 'baixa')
        ");
        $stmt->execute();
        $distribuicaoPrioridade = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Evolução mensal (últimos 6 meses)
        $stmt = $db->prepare("
            SELECT 
                DATE_FORMAT(data_criacao, '%Y-%m') as mes,
                COUNT(*) as total
            FROM Observacoes_Associado
            WHERE ativo = 1 
            AND data_criacao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY mes
            ORDER BY mes DESC
        ");
        $stmt->execute();
        $evolucaoMensal = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Associados com mais observações
        $stmt = $db->prepare("
            SELECT 
                a.id,
                a.nome,
                a.cpf,
                COUNT(o.id) as total_observacoes,
                COUNT(CASE WHEN o.importante = 1 THEN 1 END) as importantes,
                COUNT(CASE WHEN o.categoria = 'pendencia' THEN 1 END) as pendencias
            FROM Associados a
            JOIN Observacoes_Associado o ON a.id = o.associado_id
            WHERE o.ativo = 1
            GROUP BY a.id
            ORDER BY total_observacoes DESC
            LIMIT 10
        ");
        $stmt->execute();
        $topAssociados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Montar resposta
        $response = [
            'status' => 'success',
            'data' => [
                'geral' => $estatisticasGerais,
                'distribuicao' => [
                    'categorias' => $distribuicaoCategoria,
                    'prioridades' => $distribuicaoPrioridade
                ],
                'evolucao_mensal' => $evolucaoMensal,
                'rankings' => [
                    'funcionarios' => $topFuncionarios,
                    'associados' => $topAssociados
                ],
                'periodo_analisado' => $periodo . ' dias',
                'data_analise' => date('Y-m-d H:i:s')
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas gerais: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao buscar estatísticas'
        ]);
        exit;
    }
}

// Retornar resposta
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);