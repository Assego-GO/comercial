<?php
/**
 * API para buscar serviços de um associado - VERSÃO CORRIGIDA
 * api/buscar_servicos_associado.php
 */

// Headers para CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configuração de erro reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Resposta padrão
$response = [
    'status' => 'error',
    'message' => 'Erro ao processar requisição',
    'data' => null
];

try {
    // Verifica método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido. Use GET.');
    }

    // Verifica se ID foi fornecido
    $associadoId = isset($_GET['associado_id']) ? intval($_GET['associado_id']) : null;
    if (!$associadoId) {
        throw new Exception('ID do associado não fornecido');
    }

    // Carrega configurações e classes
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';

    // Inicia sessão se não estiver iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    error_log("=== BUSCANDO SERVIÇOS DO ASSOCIADO $associadoId ===");

    // Buscar serviços ativos do associado
    $stmt = $db->prepare("
        SELECT 
            sa.id,
            sa.servico_id,
            sa.ativo,
            sa.data_adesao,
            sa.valor_aplicado,
            sa.percentual_aplicado,
            sa.observacao,
            s.nome as servico_nome,
            s.descricao as servico_descricao,
            s.valor_base
        FROM Servicos_Associado sa
        INNER JOIN Servicos s ON sa.servico_id = s.id
        WHERE sa.associado_id = ? AND sa.ativo = 1
        ORDER BY s.obrigatorio DESC, s.nome ASC
    ");
    
    $stmt->execute([$associadoId]);
    $servicosAtivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Serviços ativos encontrados: " . count($servicosAtivos));

    // CORREÇÃO: Busca o tipo de associado de forma mais robusta
    $tipoAssociadoServico = null;
    
    if (!empty($servicosAtivos)) {
        // Método 1: Busca pela auditoria (mais recente)
        $stmt = $db->prepare("
            SELECT alteracoes 
            FROM Auditoria 
            WHERE tabela = 'Servicos_Associado' 
            AND registro_id = ? 
            AND alteracoes LIKE '%tipo_associado_servico%'
            ORDER BY data_hora DESC 
            LIMIT 1
        ");
        $stmt->execute([$associadoId]);
        $auditoria = $stmt->fetch();
        
        if ($auditoria && $auditoria['alteracoes']) {
            $alteracoes = json_decode($auditoria['alteracoes'], true);
            if (isset($alteracoes['tipo_associado_servico'])) {
                $tipoAssociadoServico = $alteracoes['tipo_associado_servico'];
                error_log("Tipo encontrado na auditoria: $tipoAssociadoServico");
            }
        }
        
        // Método 2: Se não encontrou na auditoria, tenta inferir pelas regras
        if (!$tipoAssociadoServico) {
            $primeiroServico = $servicosAtivos[0];
            
            $stmt = $db->prepare("
                SELECT DISTINCT rc.tipo_associado
                FROM Regras_Contribuicao rc
                WHERE rc.servico_id = ? 
                AND ABS(rc.percentual_valor - ?) < 0.01
                ORDER BY 
                    CASE 
                        WHEN rc.tipo_associado = 'Contribuinte' THEN 1
                        WHEN rc.tipo_associado = 'Aluno' THEN 2
                        WHEN rc.tipo_associado = 'Soldado 1ª Classe' THEN 3
                        WHEN rc.tipo_associado = 'Soldado 2ª Classe' THEN 4
                        WHEN rc.tipo_associado = 'Agregado' THEN 5
                        WHEN rc.tipo_associado = 'Remido 50%' THEN 6
                        WHEN rc.tipo_associado = 'Remido' THEN 7
                        WHEN rc.tipo_associado = 'Benemerito' THEN 8
                        ELSE 9
                    END
                LIMIT 1
            ");
            
            $stmt->execute([
                $primeiroServico['servico_id'], 
                $primeiroServico['percentual_aplicado']
            ]);
            
            $tipoAssociadoServico = $stmt->fetchColumn();
            
            if ($tipoAssociadoServico) {
                error_log("Tipo inferido pelas regras: $tipoAssociadoServico");
            }
        }
        
        // Método 3: Fallback - verifica se é isento (0% no social)
        if (!$tipoAssociadoServico) {
            $servicoSocial = null;
            foreach ($servicosAtivos as $servico) {
                if ($servico['servico_id'] == 1) {
                    $servicoSocial = $servico;
                    break;
                }
            }
            
            if ($servicoSocial && floatval($servicoSocial['percentual_aplicado']) == 0) {
                // Verifica se existe "Remido" ou "Benemerito" nas regras
                $stmt = $db->prepare("
                    SELECT tipo_associado 
                    FROM Regras_Contribuicao 
                    WHERE servico_id = 1 AND percentual_valor = 0 
                    AND tipo_associado IN ('Remido', 'Benemerito')
                    ORDER BY tipo_associado
                    LIMIT 1
                ");
                $stmt->execute();
                $tipoAssociadoServico = $stmt->fetchColumn() ?: 'Remido';
                
                error_log("Tipo inferido para isento: $tipoAssociadoServico");
            }
        }
    }

    // Se ainda não encontrou, define um padrão
    if (!$tipoAssociadoServico && !empty($servicosAtivos)) {
        $tipoAssociadoServico = 'Contribuinte'; // Padrão
        error_log("Usando tipo padrão: $tipoAssociadoServico");
    }

    // Organizar dados para resposta
    $servicosOrganizados = [
        'social' => null,
        'juridico' => null
    ];

    foreach ($servicosAtivos as $servico) {
        if ($servico['servico_id'] == 1) {
            $servicosOrganizados['social'] = $servico;
        } elseif ($servico['servico_id'] == 2) {
            $servicosOrganizados['juridico'] = $servico;
        }
    }

    // Buscar histórico dos serviços
    $stmt = $db->prepare("
        SELECT 
            hsa.id,
            hsa.servico_associado_id,
            hsa.tipo_alteracao,
            hsa.valor_anterior,
            hsa.valor_novo,
            hsa.percentual_anterior,
            hsa.percentual_novo,
            hsa.data_alteracao,
            hsa.motivo,
            f.nome as funcionario_nome,
            s.nome as servico_nome
        FROM Historico_Servicos_Associado hsa
        INNER JOIN Servicos_Associado sa ON hsa.servico_associado_id = sa.id
        INNER JOIN Servicos s ON sa.servico_id = s.id
        LEFT JOIN Funcionarios f ON hsa.funcionario_id = f.id
        WHERE sa.associado_id = ?
        ORDER BY hsa.data_alteracao DESC
        LIMIT 20
    ");
    
    $stmt->execute([$associadoId]);
    $historicoServicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcula valor total mensal
    $valorTotalMensal = 0;
    foreach ($servicosAtivos as $servico) {
        $valorTotalMensal += floatval($servico['valor_aplicado']);
    }

    error_log("Valor total mensal calculado: $valorTotalMensal");
    error_log("Tipo de associado final: " . ($tipoAssociadoServico ?: 'Não definido'));

    // Resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => 'Serviços encontrados com sucesso',
        'data' => [
            'associado_id' => $associadoId,
            'tipo_associado_servico' => $tipoAssociadoServico,
            'servicos' => $servicosOrganizados,
            'total_servicos_ativos' => count($servicosAtivos),
            'valor_total_mensal' => $valorTotalMensal,
            'historico' => $historicoServicos,
            'debug' => [
                'metodo_tipo_encontrado' => $tipoAssociadoServico ? 'sucesso' : 'falhou',
                'total_servicos_raw' => count($servicosAtivos)
            ]
        ]
    ];

    error_log("✓ Resposta preparada com sucesso");

} catch (Exception $e) {
    error_log("✗ Erro ao buscar serviços do associado: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null
    ];
    
    http_response_code(400);
}

// Retorna resposta JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>