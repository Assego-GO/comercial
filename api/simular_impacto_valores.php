<?php
/**
 * API para simular impacto de alteração nos valores base
 * api/simular_impacto_valores.php
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = [
    'status' => 'error',
    'message' => 'Erro ao processar requisição',
    'data' => null
];

try {
    // Verifica método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }
    
    // Carrega configurações
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';

    // Verifica autenticação
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Acesso negado. Faça login.');
    }

    // Lê dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Dados JSON inválidos');
    }
    
    $valorSocial = floatval($input['valor_social'] ?? 0);
    $valorJuridico = floatval($input['valor_juridico'] ?? 0);
    
    if ($valorSocial <= 0 || $valorJuridico <= 0) {
        throw new Exception('Valores devem ser maiores que zero');
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Busca IDs dos serviços
    $stmt = $db->prepare("
        SELECT id, nome, valor_base
        FROM Servicos 
        WHERE ativo = 1 AND (nome LIKE '%social%' OR nome LIKE '%juridico%' OR nome LIKE '%jurídico%')
        ORDER BY id
    ");
    $stmt->execute();
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $servicoSocialId = null;
    $servicoJuridicoId = null;
    $valorSocialAtual = 0;
    $valorJuridicoAtual = 0;
    
    foreach ($servicos as $servico) {
        $nome = strtolower($servico['nome']);
        if (strpos($nome, 'social') !== false) {
            $servicoSocialId = $servico['id'];
            $valorSocialAtual = floatval($servico['valor_base']);
        } elseif (strpos($nome, 'juridico') !== false || strpos($nome, 'jurídico') !== false) {
            $servicoJuridicoId = $servico['id'];
            $valorJuridicoAtual = floatval($servico['valor_base']);
        }
    }
    
    if (!$servicoSocialId || !$servicoJuridicoId) {
        throw new Exception('Serviços Social ou Jurídico não encontrados');
    }
    
    // Busca regras de contribuição
    $stmt = $db->prepare("
        SELECT tipo_associado, servico_id, percentual_valor 
        FROM Regras_Contribuicao 
        WHERE servico_id IN (?, ?)
        ORDER BY tipo_associado, servico_id
    ");
    $stmt->execute([$servicoSocialId, $servicoJuridicoId]);
    $regras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $regrasMap = [];
    foreach ($regras as $regra) {
        $regrasMap[$regra['tipo_associado']][$regra['servico_id']] = floatval($regra['percentual_valor']);
    }
    
    // Busca todos os serviços ativos
    $stmt = $db->prepare("
        SELECT 
            sa.id,
            sa.associado_id,
            sa.servico_id,
            sa.tipo_associado,
            sa.valor_aplicado,
            sa.percentual_aplicado,
            a.nome as associado_nome
        FROM Servicos_Associado sa
        INNER JOIN Associados a ON sa.associado_id = a.id
        WHERE sa.ativo = 1 AND sa.servico_id IN (?, ?)
        ORDER BY sa.associado_id, sa.servico_id
    ");
    $stmt->execute([$servicoSocialId, $servicoJuridicoId]);
    $servicosAssociados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Simula alterações
    $simulacao = [
        'total_afetados' => 0,
        'valor_total_anterior' => 0,
        'valor_total_novo' => 0,
        'alteracoes_por_servico' => [
            'social' => ['afetados' => 0, 'valor_anterior' => 0, 'valor_novo' => 0],
            'juridico' => ['afetados' => 0, 'valor_anterior' => 0, 'valor_novo' => 0]
        ],
        'alteracoes_por_tipo' => []
    ];
    
    $associadosAfetados = [];
    
    foreach ($servicosAssociados as $servicoAssociado) {
        $servicoId = $servicoAssociado['servico_id'];
        $tipoAssociado = $servicoAssociado['tipo_associado'];
        $valorAtual = floatval($servicoAssociado['valor_aplicado']);
        
        // Determina novo valor base
        $novoValorBase = ($servicoId == $servicoSocialId) ? $valorSocial : $valorJuridico;
        $servicoNome = ($servicoId == $servicoSocialId) ? 'social' : 'juridico';
        
        // Busca percentual
        $percentual = 100; // Padrão
        if (isset($regrasMap[$tipoAssociado][$servicoId])) {
            $percentual = $regrasMap[$tipoAssociado][$servicoId];
        }
        
        // Calcula novo valor
        $novoValor = ($novoValorBase * $percentual) / 100;
        
        // Se mudou, adiciona na simulação
        if (abs($valorAtual - $novoValor) > 0.01) {
            $associadosAfetados[$servicoAssociado['associado_id']] = true;
            
            $simulacao['valor_total_anterior'] += $valorAtual;
            $simulacao['valor_total_novo'] += $novoValor;
            
            $simulacao['alteracoes_por_servico'][$servicoNome]['afetados']++;
            $simulacao['alteracoes_por_servico'][$servicoNome]['valor_anterior'] += $valorAtual;
            $simulacao['alteracoes_por_servico'][$servicoNome]['valor_novo'] += $novoValor;
            
            // Por tipo
            if (!isset($simulacao['alteracoes_por_tipo'][$tipoAssociado])) {
                $simulacao['alteracoes_por_tipo'][$tipoAssociado] = [
                    'afetados' => 0,
                    'valor_anterior' => 0,
                    'valor_novo' => 0
                ];
            }
            
            $simulacao['alteracoes_por_tipo'][$tipoAssociado]['afetados']++;
            $simulacao['alteracoes_por_tipo'][$tipoAssociado]['valor_anterior'] += $valorAtual;
            $simulacao['alteracoes_por_tipo'][$tipoAssociado]['valor_novo'] += $novoValor;
        }
    }
    
    $simulacao['total_afetados'] = count($associadosAfetados);
    $simulacao['diferenca_total'] = $simulacao['valor_total_novo'] - $simulacao['valor_total_anterior'];
    $simulacao['percentual_impacto'] = $simulacao['valor_total_anterior'] > 0 
        ? (($simulacao['diferenca_total'] / $simulacao['valor_total_anterior']) * 100) 
        : 0;
    
    // Informações adicionais
    $simulacao['valores_base'] = [
        'social' => [
            'atual' => $valorSocialAtual,
            'novo' => $valorSocial,
            'diferenca' => $valorSocial - $valorSocialAtual
        ],
        'juridico' => [
            'atual' => $valorJuridicoAtual,
            'novo' => $valorJuridico,
            'diferenca' => $valorJuridico - $valorJuridicoAtual
        ]
    ];
    
    $response = [
        'status' => 'success',
        'message' => 'Simulação calculada com sucesso',
        'data' => $simulacao
    ];
    
} catch (Exception $e) {
    error_log("Erro na simulação de impacto: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null
    ];
    
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>