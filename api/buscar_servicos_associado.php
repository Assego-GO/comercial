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

    // CORRIGIDO: Buscar serviços ativos do associado COM tipo_associado
    $stmt = $db->prepare("
        SELECT 
            sa.id,
            sa.servico_id,
            sa.tipo_associado,
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

    // CORRIGIDO: Busca o tipo de associado diretamente da nova coluna
    $tipoAssociadoServico = null;

    if (!empty($servicosAtivos)) {
        // Pega o tipo do primeiro serviço (todos devem ter o mesmo tipo)
        $tipoAssociadoServico = $servicosAtivos[0]['tipo_associado'];
        
        error_log("Tipo encontrado na coluna tipo_associado: " . ($tipoAssociadoServico ?: 'NULL'));
        
        // Fallback caso esteja NULL ou vazio
        if (empty($tipoAssociadoServico)) {
            error_log("Tipo estava vazio, tentando inferir...");
            
            // Busca na tabela Associados
            $stmt = $db->prepare("SELECT tipoAssociado, patente FROM Associados WHERE id = ?");
            $stmt->execute([$associadoId]);
            $associado = $stmt->fetch();
            
            if ($associado) {
                if (!empty($associado['tipoAssociado'])) {
                    $mapeamento = [
                        'Contribuinte' => 'Contribuinte',
                        'Benemérito' => 'Benemerito',
                        'Remido' => 'Remido',
                        'Agregado' => 'Agregado'
                    ];
                    $tipoAssociadoServico = $mapeamento[$associado['tipoAssociado']] ?? 'Contribuinte';
                } elseif (!empty($associado['patente'])) {
                    if (stripos($associado['patente'], 'aluno') !== false) {
                        $tipoAssociadoServico = 'Aluno';
                    } elseif ($associado['patente'] === 'Soldado 1ª Classe') {
                        $tipoAssociadoServico = 'Soldado 1ª Classe';
                    } elseif ($associado['patente'] === 'Soldado 2ª Classe') {
                        $tipoAssociadoServico = 'Soldado 2ª Classe';
                    } else {
                        $tipoAssociadoServico = 'Contribuinte';
                    }
                } else {
                    $tipoAssociadoServico = 'Contribuinte';
                }
                
                error_log("Tipo inferido do associado: $tipoAssociadoServico");
                
                // ATUALIZA os registros com o tipo correto
                $stmt = $db->prepare("UPDATE Servicos_Associado SET tipo_associado = ? WHERE associado_id = ? AND ativo = 1");
                $stmt->execute([$tipoAssociadoServico, $associadoId]);
                error_log("Registros de serviços atualizados com tipo: $tipoAssociadoServico");
            }
        }
    }

    // Se ainda não encontrou, define padrão
    if (!$tipoAssociadoServico && !empty($servicosAtivos)) {
        $tipoAssociadoServico = 'Contribuinte';
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