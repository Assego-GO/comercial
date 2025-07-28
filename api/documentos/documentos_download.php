<?php
/**
 * API para download de documento
 * api/documentos/documentos_download.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Documentos.php';

try {
    $auth = new Auth();
    
    if (!$auth->isLoggedIn()) {
        header('HTTP/1.0 401 Unauthorized');
        exit('Não autorizado');
    }
    
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        header('HTTP/1.0 400 Bad Request');
        exit('Documento não informado');
    }
    
    $documentoId = intval($_GET['id']);
    
    $documentos = new Documentos();
    $documentos->download($documentoId);
    
} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    exit($e->getMessage());
}
?>