<?php
/**
 * API para buscar dados de serviços e regras de contribuição
 * api/buscar_dados_servicos.php
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
    'servicos' => [],
    'regras' => [],
    'tipos_associado' => []
];

try {
    // Verifica método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido. Use GET.');
    }

    // Carrega configurações e classes
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';

    error_log("=== BUSCANDO DADOS DE SERVIÇOS E REGRAS ===");

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // 1. Buscar todos os serviços ativos
    $stmt = $db->prepare("
        SELECT 
            id,
            nome,
            descricao,
            valor_base,
            obrigatorio,
            ativo
        FROM Servicos 
        WHERE ativo = 1 
        ORDER BY obrigatorio DESC, nome ASC
    ");
    
    $stmt->execute();
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Serviços encontrados: " . count($servicos));

    // 2. Buscar todas as regras de contribuição
    $stmt = $db->prepare("
        SELECT 
            rc.id,
            rc.tipo_associado,
            rc.servico_id,
            rc.percentual_valor,
            rc.opcional,
            rc.descricao,
            s.nome as servico_nome,
            s.valor_base as servico_valor_base
        FROM Regras_Contribuicao rc
        INNER JOIN Servicos s ON rc.servico_id = s.id
        WHERE s.ativo = 1
        ORDER BY rc.tipo_associado, rc.servico_id
    ");
    
    $stmt->execute();
    $regrasContribuicao = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Regras de contribuição encontradas: " . count($regrasContribuicao));

    // 3. Buscar tipos de associado únicos
    $stmt = $db->prepare("
        SELECT DISTINCT tipo_associado 
        FROM Regras_Contribuicao 
        ORDER BY 
            CASE 
                WHEN tipo_associado = 'Contribuinte' THEN 1
                WHEN tipo_associado = 'Aluno' THEN 2
                WHEN tipo_associado = 'Soldado 1ª Classe' THEN 3
                WHEN tipo_associado = 'Soldado 2ª Classe' THEN 4
                WHEN tipo_associado = 'Agregado' THEN 5
                WHEN tipo_associado = 'Remido 50%' THEN 6
                WHEN tipo_associado = 'Remido' THEN 7
                WHEN tipo_associado = 'Benemerito' THEN 8
                ELSE 9
            END
    ");
    
    $stmt->execute();
    $tiposAssociado = $stmt->fetchAll(PDO::FETCH_COLUMN);

    error_log("Tipos de associado encontrados: " . count($tiposAssociado));

    // 4. Se não há dados, cria dados padrão
    if (empty($servicos) || empty($regrasContribuicao)) {
        error_log("⚠ Dados insuficientes no banco, criando dados padrão");
        
        // Cria dados padrão se não existirem
        criarDadosPadrao($db);
        
        // Recarrega os dados
        $stmt = $db->prepare("SELECT id, nome, descricao, valor_base, obrigatorio, ativo FROM Servicos WHERE ativo = 1 ORDER BY obrigatorio DESC, nome ASC");
        $stmt->execute();
        $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            SELECT 
                rc.id, rc.tipo_associado, rc.servico_id, rc.percentual_valor, rc.opcional, rc.descricao,
                s.nome as servico_nome, s.valor_base as servico_valor_base
            FROM Regras_Contribuicao rc
            INNER JOIN Servicos s ON rc.servico_id = s.id
            WHERE s.ativo = 1
            ORDER BY rc.tipo_associado, rc.servico_id
        ");
        $stmt->execute();
        $regrasContribuicao = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT DISTINCT tipo_associado FROM Regras_Contribuicao ORDER BY tipo_associado");
        $stmt->execute();
        $tiposAssociado = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // 5. Organiza dados para JavaScript
    $servicosFormatados = [];
    foreach ($servicos as $servico) {
        $servicosFormatados[] = [
            'id' => $servico['id'],
            'nome' => $servico['nome'],
            'valor_base' => number_format($servico['valor_base'], 2, '.', ''),
            'obrigatorio' => (bool)$servico['obrigatorio']
        ];
    }

    $regrasFormatadas = [];
    foreach ($regrasContribuicao as $regra) {
        $regrasFormatadas[] = [
            'tipo_associado' => $regra['tipo_associado'],
            'servico_id' => $regra['servico_id'],
            'percentual_valor' => number_format($regra['percentual_valor'], 2, '.', ''),
            'opcional' => (bool)$regra['opcional'],
            'servico_nome' => $regra['servico_nome']
        ];
    }

    // Resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => 'Dados carregados com sucesso',
        'servicos' => $servicosFormatados,
        'regras' => $regrasFormatadas,
        'tipos_associado' => $tiposAssociado,
        'totais' => [
            'servicos' => count($servicosFormatados),
            'regras' => count($regrasFormatadas),
            'tipos' => count($tiposAssociado)
        ]
    ];

    error_log("✓ Dados preparados: " . count($servicosFormatados) . " serviços, " . count($regrasFormatadas) . " regras");

} catch (Exception $e) {
    error_log("✗ Erro ao buscar dados de serviços: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'servicos' => [],
        'regras' => [],
        'tipos_associado' => []
    ];
    
    http_response_code(500);
}

// Retorna resposta JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Função para criar dados padrão se não existirem
 */
function criarDadosPadrao($db) {
    try {
        error_log("Criando dados padrão de serviços e regras...");
        
        // 1. Verifica e cria serviços padrão
        $stmt = $db->prepare("SELECT COUNT(*) FROM Servicos");
        $stmt->execute();
        $totalServicos = $stmt->fetchColumn();
        
        if ($totalServicos == 0) {
            error_log("Criando serviços padrão...");
            
            // Serviço Social
            $stmt = $db->prepare("
                INSERT INTO Servicos (nome, descricao, valor_base, obrigatorio, ativo) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute(['Social', 'Serviço social obrigatório', 173.10, 1, 1]);
            
            // Serviço Jurídico  
            $stmt->execute(['Jurídico', 'Serviço jurídico opcional', 43.28, 0, 1]);
            
            error_log("✓ Serviços padrão criados");
        }
        
        // 2. Verifica e cria regras padrão
        $stmt = $db->prepare("SELECT COUNT(*) FROM Regras_Contribuicao");
        $stmt->execute();
        $totalRegras = $stmt->fetchColumn();
        
        if ($totalRegras == 0) {
            error_log("Criando regras de contribuição padrão...");
            
            $regras = [
                // Contribuinte - 100% de ambos
                ['Contribuinte', 1, 100.00, 0, 'Contribuição integral do serviço social'],
                ['Contribuinte', 2, 100.00, 1, 'Contribuição integral do serviço jurídico'],
                
                // Aluno - 50% social, 100% jurídico
                ['Aluno', 1, 50.00, 0, 'Desconto de 50% no serviço social para alunos'],
                ['Aluno', 2, 100.00, 1, 'Valor integral do serviço jurídico para alunos'],
                
                // Soldado 2ª Classe - 50% social, 100% jurídico
                ['Soldado 2ª Classe', 1, 50.00, 0, 'Desconto de 50% no serviço social'],
                ['Soldado 2ª Classe', 2, 100.00, 1, 'Valor integral do serviço jurídico'],
                
                // Soldado 1ª Classe - 100% de ambos
                ['Soldado 1ª Classe', 1, 100.00, 0, 'Contribuição integral do serviço social'],
                ['Soldado 1ª Classe', 2, 100.00, 1, 'Contribuição integral do serviço jurídico'],
                
                // Agregado - 50% social, 100% jurídico
                ['Agregado', 1, 50.00, 0, 'Desconto de 50% no serviço social para agregados'],
                ['Agregado', 2, 100.00, 1, 'Valor integral do serviço jurídico para agregados'],
                
                // Remido - 0% social, 100% jurídico
                ['Remido', 1, 0.00, 0, 'Isento do serviço social'],
                ['Remido', 2, 100.00, 1, 'Valor integral do serviço jurídico'],
                
                // Remido 50% - 50% social, 100% jurídico  
                ['Remido 50%', 1, 50.00, 0, 'Desconto de 50% no serviço social'],
                ['Remido 50%', 2, 100.00, 1, 'Valor integral do serviço jurídico'],
                
                // Benemerito - 0% social, 100% jurídico
                ['Benemerito', 1, 0.00, 0, 'Isento do serviço social como benemérito'],
                ['Benemerito', 2, 100.00, 1, 'Valor integral do serviço jurídico para benemérito']
            ];
            
            $stmt = $db->prepare("
                INSERT INTO Regras_Contribuicao (tipo_associado, servico_id, percentual_valor, opcional, descricao) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($regras as $regra) {
                $stmt->execute($regra);
            }
            
            error_log("✓ Regras de contribuição padrão criadas: " . count($regras) . " regras");
        }
        
    } catch (Exception $e) {
        error_log("Erro ao criar dados padrão: " . $e->getMessage());
        throw $e;
    }
}
?>