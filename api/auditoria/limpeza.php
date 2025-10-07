<?php
/**
 * API para limpeza de registros antigos de auditoria
 * /api/auditoria/limpeza.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auditoria.php';
require_once '../../classes/Auth.php';

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Verificar autenticação (apenas administradores)
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Verificar se é diretor/administrador
    if (!$auth->isDiretor()) {
        throw new Exception('Acesso negado. Apenas diretores podem executar limpeza de auditoria.');
    }

    // Obter dados da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados da requisição inválidos');
    }

    $dias = isset($input['dias']) ? (int)$input['dias'] : 365;
    $confirmarExclusao = isset($input['confirmar']) ? (bool)$input['confirmar'] : false;
    $modoSimulacao = isset($input['simulacao']) ? (bool)$input['simulacao'] : true;

    // Validar parâmetros
    if ($dias < 30) {
        throw new Exception('Não é possível excluir registros com menos de 30 dias');
    }

    if ($dias > 3650) {
        throw new Exception('Período máximo é de 10 anos (3650 dias)');
    }

    // Criar instância da auditoria
    $auditoria = new Auditoria();
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Calcular data limite
    $dataLimite = date('Y-m-d', strtotime("-$dias days"));
    
    // Contar quantos registros serão afetados
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_registros
        FROM Auditoria 
        WHERE DATE(data_hora) < :data_limite
    ");
    $stmt->execute([':data_limite' => $dataLimite]);
    $totalRegistros = $stmt->fetch(PDO::FETCH_ASSOC)['total_registros'];
    
    // Contar detalhes que serão afetados
    $stmt = $db->prepare("
        SELECT COUNT(ad.id) as total_detalhes
        FROM Auditoria_Detalhes ad
        INNER JOIN Auditoria a ON ad.auditoria_id = a.id
        WHERE DATE(a.data_hora) < :data_limite
    ");
    $stmt->execute([':data_limite' => $dataLimite]);
    $totalDetalhes = $stmt->fetch(PDO::FETCH_ASSOC)['total_detalhes'];
    
    // Obter estatísticas dos registros que serão excluídos
    $stmt = $db->prepare("
        SELECT 
            acao,
            COUNT(*) as total,
            MIN(data_hora) as mais_antigo,
            MAX(data_hora) as mais_recente
        FROM Auditoria 
        WHERE DATE(data_hora) < :data_limite
        GROUP BY acao
        ORDER BY total DESC
    ");
    $stmt->execute([':data_limite' => $dataLimite]);
    $estatisticasExclusao = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $resultado = [
        'parametros' => [
            'dias' => $dias,
            'data_limite' => $dataLimite,
            'modo_simulacao' => $modoSimulacao,
            'confirmado' => $confirmarExclusao
        ],
        'impacto' => [
            'total_registros_afetados' => $totalRegistros,
            'total_detalhes_afetados' => $totalDetalhes,
            'estatisticas_por_acao' => $estatisticasExclusao
        ],
        'executado' => false,
        'resultado_limpeza' => null
    ];
    
    // Se não há registros para excluir
    if ($totalRegistros == 0) {
        $resultado['mensagem'] = 'Não há registros antigos para excluir com os parâmetros fornecidos.';
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Consulta executada com sucesso',
            'data' => $resultado
        ]);
        return;
    }
    
    // Se é apenas simulação, retornar preview
    if ($modoSimulacao) {
        $resultado['mensagem'] = "SIMULAÇÃO: $totalRegistros registros e $totalDetalhes detalhes seriam excluídos.";
        $resultado['aviso'] = 'Esta é apenas uma simulação. Para executar a limpeza, defina "simulacao" como false e "confirmar" como true.';
        
        echo json_encode([
            'status' => 'info',
            'message' => 'Simulação executada com sucesso',
            'data' => $resultado
        ]);
        return;
    }
    
    // Se não confirmou, exigir confirmação
    if (!$confirmarExclusao) {
        throw new Exception('Para executar a limpeza real, você deve confirmar definindo "confirmar" como true.');
    }
    
    // Executar limpeza real
    $resultadoLimpeza = $auditoria->limparRegistrosAntigos($dias);
    
    if ($resultadoLimpeza === false) {
        throw new Exception('Erro ao executar limpeza de registros');
    }
    
    $resultado['executado'] = true;
    $resultado['resultado_limpeza'] = $resultadoLimpeza;
    $resultado['mensagem'] = "Limpeza executada com sucesso. {$resultadoLimpeza['registros']} registros e {$resultadoLimpeza['detalhes']} detalhes foram removidos.";
    
    // Calcular estatísticas pós-limpeza
    $stmt = $db->query("SELECT COUNT(*) as total_restante FROM Auditoria");
    $totalRestante = $stmt->fetch(PDO::FETCH_ASSOC)['total_restante'];
    
    $stmt = $db->query("
        SELECT 
            MIN(data_hora) as registro_mais_antigo,
            MAX(data_hora) as registro_mais_recente
        FROM Auditoria 
        WHERE data_hora IS NOT NULL
    ");
    $rangeDatas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $resultado['pos_limpeza'] = [
        'total_registros_restantes' => $totalRestante,
        'registro_mais_antigo' => $rangeDatas['registro_mais_antigo'],
        'registro_mais_recente' => $rangeDatas['registro_mais_recente']
    ];
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Limpeza executada com sucesso',
        'data' => $resultado
    ]);

} catch (Exception $e) {
    error_log("Erro na API de limpeza de auditoria: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro na limpeza: ' . $e->getMessage(),
        'data' => null
    ]);
}
?>