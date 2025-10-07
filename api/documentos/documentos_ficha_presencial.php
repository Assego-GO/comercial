<?php
/**
 * API para cadastrar ficha presencial
 * api/documentos/documentos_ficha_presencial.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Documentos.php';

header('Content-Type: application/json');

try {
    $auth = new Auth();
    
    if (!$auth->isLoggedIn()) {
        throw new Exception('Não autorizado');
    }
    
    // Validar dados recebidos
    if (!isset($_POST['associado_id']) || empty($_POST['associado_id'])) {
        throw new Exception('Associado não informado');
    }
    
    $associadoId = intval($_POST['associado_id']);
    $tipoOrigem = 'PRESENCIAL'; // Sempre presencial neste endpoint
    $observacao = $_POST['observacao'] ?? 'Ficha preenchida presencialmente na ASSEGO';
    
    $documentos = new Documentos();
    
    // Primeiro, gerar a ficha PDF com os dados do associado
    $resultadoFicha = $documentos->gerarFichaVirtual($associadoId);
    
    if (!$resultadoFicha || !isset($resultadoFicha['documento_id'])) {
        throw new Exception('Erro ao gerar ficha PDF');
    }
    
    $documentoId = $resultadoFicha['documento_id'];
    
    // Atualizar o tipo de origem para PRESENCIAL
    $documentos->atualizarTipoOrigem($documentoId, 'PRESENCIAL');
    
    // Se foi enviada foto da ficha física, anexar como documento adicional
    if (isset($_FILES['arquivo_fisico']) && $_FILES['arquivo_fisico']['error'] === UPLOAD_ERR_OK) {
        try {
            // Upload do arquivo físico como documento adicional
            $documentos->anexarDocumentoAdicional(
                $associadoId,
                $_FILES['arquivo_fisico'],
                'foto_ficha_fisica',
                'Foto da ficha física preenchida presencialmente',
                $documentoId // Vincular ao documento principal
            );
        } catch (Exception $e) {
            // Log do erro mas não falha a operação principal
            error_log("Aviso: Erro ao anexar foto da ficha física: " . $e->getMessage());
        }
    }
    
    // Registrar observação adicional
    if ($observacao) {
        $documentos->adicionarObservacao($documentoId, $observacao);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Ficha presencial cadastrada com sucesso',
        'data' => [
            'documento_id' => $documentoId,
            'nome_arquivo' => $resultadoFicha['nome_arquivo'],
            'associado_nome' => $resultadoFicha['associado_nome']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>