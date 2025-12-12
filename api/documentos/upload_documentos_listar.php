<?php
/**
 * API para listar documentos anexados
 * ../api/documentos/upload_documentos_listar.php
 */

header('Content-Type: application/json');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

try {
    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
        exit;
    }

    // Conecta ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Verifica se tabela existe
    $stmt = $db->prepare("SHOW TABLES LIKE 'DocumentosFluxo'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'success', 'data' => [], 'total' => 0]);
        exit;
    }

    // Pega ID do associado
    $associadoId = isset($_GET['associado_id']) ? (int)$_GET['associado_id'] : 0;

    // Busca documentos
    $sql = "
        SELECT 
            df.*,
            a.nome as associado_nome,
            f.nome as funcionario_upload,
            CASE df.status_fluxo
                WHEN 'DIGITALIZADO' THEN 'Digitalizado'
                WHEN 'AGUARDANDO_ASSINATURA' THEN 'Aguardando Assinatura'
                WHEN 'ASSINADO' THEN 'Assinado'
                WHEN 'FINALIZADO' THEN 'Finalizado'
                ELSE df.status_fluxo
            END as status_descricao,
            DATEDIFF(NOW(), df.data_upload) as dias_em_processo
        FROM DocumentosFluxo df
        LEFT JOIN Associados a ON df.associado_id = a.id
        LEFT JOIN Funcionarios f ON df.funcionario_upload = f.id
        WHERE df.associado_id = ?
        ORDER BY df.data_upload DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$associadoId]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Adiciona informações extras
    foreach ($documentos as &$doc) {
        // Formata tamanho
        $bytes = $doc['tamanho_arquivo'];
        if ($bytes == 0) {
            $doc['tamanho_formatado'] = '0 B';
        } else {
            $k = 1024;
            $sizes = ['B', 'KB', 'MB', 'GB'];
            $i = floor(log($bytes) / log($k));
            $doc['tamanho_formatado'] = round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
        }

        // Verifica se arquivo existe
        $doc['arquivo_existe'] = file_exists('../../' . $doc['caminho_arquivo']);
        
        // Formata datas
        $doc['data_upload_formatada'] = date('d/m/Y H:i', strtotime($doc['data_upload']));
    }

    // Função auxiliar para processar arquivos de uma pasta
    $processarArquivosPasta = function($pasta, $prefixoPasta, $labelOrigem) use (&$documentos, $associadoId) {
        if (!is_dir($pasta)) return;
        
        $arquivos = scandir($pasta);
        foreach ($arquivos as $arquivo) {
            if ($arquivo === '.' || $arquivo === '..') continue;
            
            $caminhoCompleto = $pasta . $arquivo;
            if (!is_file($caminhoCompleto)) continue;
            
            // Verifica se esse arquivo já não está na lista (para evitar duplicados)
            $caminhoRelativo = $prefixoPasta . $associadoId . '/' . $arquivo;
            $jaExiste = false;
            foreach ($documentos as $doc) {
                if ($doc['caminho_arquivo'] === $caminhoRelativo) {
                    $jaExiste = true;
                    break;
                }
            }
            
            if (!$jaExiste) {
                // Adiciona o arquivo
                $tamanho = filesize($caminhoCompleto);
                $tipoMime = mime_content_type($caminhoCompleto);
                $dataModificacao = filemtime($caminhoCompleto);
                
                // Formata tamanho
                if ($tamanho == 0) {
                    $tamanhoFormatado = '0 B';
                } else {
                    $k = 1024;
                    $sizes = ['B', 'KB', 'MB', 'GB'];
                    $i = floor(log($tamanho) / log($k));
                    $tamanhoFormatado = round($tamanho / pow($k, $i), 2) . ' ' . $sizes[$i];
                }
                
                // Determina tipo de documento baseado no nome ou mime
                $tipoDescricao = 'Documento (' . $labelOrigem . ')';
                if (strpos(strtolower($arquivo), 'rg') !== false) {
                    $tipoDescricao = 'RG (' . $labelOrigem . ')';
                } elseif (strpos(strtolower($arquivo), 'cpf') !== false) {
                    $tipoDescricao = 'CPF (' . $labelOrigem . ')';
                } elseif (strpos(strtolower($arquivo), 'comprovante') !== false) {
                    $tipoDescricao = 'Comprovante (' . $labelOrigem . ')';
                } elseif (strpos(strtolower($arquivo), 'ficha') !== false) {
                    $tipoDescricao = 'Ficha de Associação (' . $labelOrigem . ')';
                } elseif (strpos($tipoMime, 'image') !== false) {
                    $tipoDescricao = 'Imagem (' . $labelOrigem . ')';
                } elseif (strpos($tipoMime, 'pdf') !== false) {
                    $tipoDescricao = 'PDF (' . $labelOrigem . ')';
                }
                
                $documentos[] = [
                    'id' => $labelOrigem . '_' . md5($arquivo),
                    'associado_id' => $associadoId,
                    'tipo_documento' => strtolower($labelOrigem) . '_legado',
                    'tipo_descricao' => $tipoDescricao,
                    'nome_arquivo' => $arquivo,
                    'caminho_arquivo' => $caminhoRelativo,
                    'tipo_mime' => $tipoMime,
                    'tamanho_arquivo' => $tamanho,
                    'tamanho_formatado' => $tamanhoFormatado,
                    'data_upload' => date('Y-m-d H:i:s', $dataModificacao),
                    'data_upload_formatada' => date('d/m/Y H:i', $dataModificacao),
                    'arquivo_existe' => true,
                    'status_fluxo' => 'DIGITALIZADO',
                    'status_descricao' => 'Digitalizado (' . $labelOrigem . ')',
                    'funcionario_upload' => null,
                    'observacao' => 'Arquivo da pasta ' . strtolower($labelOrigem),
                    'dias_em_processo' => null
                ];
            }
        }
    };
    
    // Buscar arquivos da pasta uploads/anexos
    $pastaAnexos = '../../uploads/anexos/' . $associadoId . '/';
    $processarArquivosPasta($pastaAnexos, 'uploads/anexos/', 'Anexos');
    
    // Buscar arquivos da pasta uploads/documentos
    $pastaDocumentos = '../../uploads/documentos/' . $associadoId . '/';
    $processarArquivosPasta($pastaDocumentos, 'uploads/documentos/', 'Documentos');
    
    // Buscar arquivos de desfiliação (padrão: desfiliacao_{id}_{timestamp}.pdf)
    $pastaDesfiliacao = '../../uploads/documentos/desfiliacao/';
    if (is_dir($pastaDesfiliacao)) {
        $arquivosDesfiliacao = scandir($pastaDesfiliacao);
        foreach ($arquivosDesfiliacao as $arquivo) {
            if ($arquivo === '.' || $arquivo === '..') continue;
            
            // Verifica se o arquivo pertence a este associado
            if (preg_match('/^desfiliacao_' . $associadoId . '_(\d+)\.pdf$/', $arquivo, $matches)) {
                $caminhoCompleto = $pastaDesfiliacao . $arquivo;
                if (!is_file($caminhoCompleto)) continue;
                
                // Verifica se já está na lista
                $caminhoRelativo = 'uploads/documentos/desfiliacao/' . $arquivo;
                $jaExiste = false;
                foreach ($documentos as $doc) {
                    if ($doc['caminho_arquivo'] === $caminhoRelativo) {
                        $jaExiste = true;
                        break;
                    }
                }
                
                if (!$jaExiste) {
                    $tamanho = filesize($caminhoCompleto);
                    $tipoMime = mime_content_type($caminhoCompleto);
                    $dataModificacao = filemtime($caminhoCompleto);
                    
                    // Formata tamanho
                    if ($tamanho == 0) {
                        $tamanhoFormatado = '0 B';
                    } else {
                        $k = 1024;
                        $sizes = ['B', 'KB', 'MB', 'GB'];
                        $i = floor(log($tamanho) / log($k));
                        $tamanhoFormatado = round($tamanho / pow($k, $i), 2) . ' ' . $sizes[$i];
                    }
                    
                    $documentos[] = [
                        'id' => 'desfiliacao_' . md5($arquivo),
                        'associado_id' => $associadoId,
                        'tipo_documento' => 'ficha_desfiliacao',
                        'tipo_descricao' => 'Ficha de Desfiliação',
                        'nome_arquivo' => $arquivo,
                        'caminho_arquivo' => $caminhoRelativo,
                        'tipo_mime' => $tipoMime,
                        'tamanho_arquivo' => $tamanho,
                        'tamanho_formatado' => $tamanhoFormatado,
                        'data_upload' => date('Y-m-d H:i:s', $dataModificacao),
                        'data_upload_formatada' => date('d/m/Y H:i', $dataModificacao),
                        'arquivo_existe' => true,
                        'status_fluxo' => 'DESFILIACAO',
                        'status_descricao' => 'Desfiliação',
                        'funcionario_upload' => null,
                        'observacao' => 'Documento de desfiliação',
                        'dias_em_processo' => null
                    ];
                }
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => $documentos,
        'total' => count($documentos)
    ]);

} catch (Exception $e) {
    error_log("Erro ao listar documentos: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erro interno']);
}
?>