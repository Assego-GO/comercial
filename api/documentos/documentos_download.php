<?php
/**
 * API para download/visualização de documentos
 * api/documentos/documentos_download.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Carrega arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';
    require_once '../../classes/Documentos.php';

    // Inicia sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        die('Acesso não autorizado');
    }

    // Validação do ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        die('ID do documento inválido');
    }

    $documentoId = intval($_GET['id']);
    
    // Cria instância da classe Documentos
    $documentos = new Documentos();
    
    // Busca o documento
    $documento = $documentos->getById($documentoId);
    
    if (!$documento) {
        http_response_code(404);
        die('Documento não encontrado');
    }

    // Verifica permissões específicas
    $usuarioLogado = $auth->getUser();
    $temPermissao = false;

    // Permite acesso se:
    // 1. É o próprio associado (futura implementação)
    // 2. É funcionário do sistema
    // 3. É diretor ou está na presidência (para documentos em fluxo)
    if ($auth->isLoggedIn()) {
        $temPermissao = true; // Por enquanto, qualquer funcionário logado pode ver
    }

    if (!$temPermissao) {
        http_response_code(403);
        die('Sem permissão para visualizar este documento');
    }

    // Faz o download usando o método da classe
    $documentos->download($documentoId);
    
} catch (Exception $e) {
    error_log("Erro no download do documento: " . $e->getMessage());
    http_response_code(500);
    die('Erro ao processar download: ' . $e->getMessage());
}
?>