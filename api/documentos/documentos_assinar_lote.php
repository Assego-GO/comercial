<?php
/**
 * API para assinar múltiplos documentos em lote
 * api/documentos/documentos_assinar_lote.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Documentos.php';

header('Content-Type: application/json');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

// Verificar se é da presidência ou diretor
$user = $auth->getUser();
if (!$auth->isDiretor() && $user['departamento_id'] != 2) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Sem permissão para assinar documentos']);
    exit;
}

// Obter dados do request
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['documentos_ids']) || !is_array($input['documentos_ids'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    exit;
}

$documentosIds = $input['documentos_ids'];
$observacao = $input['observacao'] ?? 'Assinatura em lote pela presidência';

if (empty($documentosIds)) {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum documento selecionado']);
    exit;
}

if (count($documentosIds) > 50) {
    echo json_encode(['status' => 'error', 'message' => 'Máximo de 50 documentos por lote']);
    exit;
}

try {
    $documentos = new Documentos();
    
    // Usar o método da classe se existir, senão fazer manualmente
    if (method_exists($documentos, 'assinarDocumentosLote')) {
        $resultado = $documentos->assinarDocumentosLote($documentosIds, $observacao);
        
        if ($resultado['assinados'] > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => "{$resultado['assinados']} documentos assinados com sucesso",
                'assinados' => $resultado['assinados'],
                'total_processados' => $resultado['total']
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Nenhum documento foi assinado',
                'erros' => $resultado['erros'] ?? []
            ]);
        }
    } else {
        // Implementação manual se o método não existir
        $assinados = 0;
        $erros = [];
        
        foreach ($documentosIds as $documentoId) {
            try {
                $resultado = $documentos->assinarDocumento($documentoId, null, $observacao);
                if ($resultado) {
                    $assinados++;
                }
            } catch (Exception $e) {
                $erros[] = "Documento ID {$documentoId}: " . $e->getMessage();
            }
        }
        
        if ($assinados > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => "{$assinados} documentos assinados com sucesso",
                'assinados' => $assinados,
                'total_processados' => count($documentosIds)
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Nenhum documento foi assinado',
                'erros' => $erros
            ]);
        }
    }
    
    // Log da ação
    error_log("Assinatura em lote realizada - Usuário: {$user['nome']} - Documentos assinados: {$assinados}");
    
} catch (Exception $e) {
    error_log("Erro geral na assinatura em lote: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor',
        'debug' => $e->getMessage()
    ]);
}
?>