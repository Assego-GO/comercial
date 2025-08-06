<?php
/**
 * API para listar documentos do ZapSign
 * api/documentos/zapsign_listar_documentos.php
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_clean();

$response = [
    'status' => 'error',
    'message' => 'Erro ao processar requisição',
    'data' => []
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido. Use GET.');
    }

    // Carrega configurações
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    // Sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Verifica permissão (deve ser da presidência)
    $user = $auth->getUser();
    if (!isset($user['departamento_id']) || $user['departamento_id'] != 1) {
        throw new Exception('Acesso negado. Apenas usuários da Presidência podem acessar esta funcionalidade.');
    }

    error_log("=== LISTANDO DOCUMENTOS ZAPSIGN ===");
    error_log("Usuário: " . $user['nome']);

    // Parâmetros da requisição
    $page = intval($_GET['page'] ?? 1);
    $status = $_GET['status'] ?? ''; // pending, signed, refused, expired ou vazio para todos
    $search = trim($_GET['search'] ?? '');
    $sortOrder = $_GET['sort_order'] ?? 'desc'; // desc ou asc
    $showDeleted = $_GET['show_deleted'] ?? 'false'; // ✅ NOVO: false, true, ou only

    // Validações
    if ($page < 1) $page = 1;
    if (!in_array($status, ['', 'pending', 'signed', 'refused', 'expired'])) {
        $status = '';
    }
    if (!in_array($sortOrder, ['asc', 'desc'])) {
        $sortOrder = 'desc';
    }
    if (!in_array($showDeleted, ['false', 'true', 'only'])) {
        $showDeleted = 'false';
    }

    error_log("Parâmetros: page=$page, status=$status, search=$search, sort_order=$sortOrder, show_deleted=$showDeleted");

    // Configuração da API ZapSign
    $apiUrl = 'https://sandbox.api.zapsign.com.br/api/v1/docs/'; // Use https://api.zapsign.com.br/api/v1/docs/ para produção
    $bearerToken = API_KEY; // Certifique-se de que API_KEY está definida no config.php

    if (empty($bearerToken)) {
        throw new Exception('Token da API ZapSign não configurado');
    }

    // Monta URL da API
    $apiParams = [
        'page' => $page, 
        'sort_order' => $sortOrder
    ];
    
    // ✅ TRATAMENTO DE DOCUMENTOS DELETADOS
    if ($showDeleted === 'only') {
        $apiParams['deleted'] = 'true';  // Apenas deletados
    } elseif ($showDeleted === 'true') {
        // Não adiciona o parâmetro deleted (mostra todos)
    } else {
        $apiParams['deleted'] = 'false';  // Apenas não deletados (padrão)
    }
    
    if (!empty($status)) {
        $apiParams['status'] = $status;
    }

    $apiUrlWithParams = $apiUrl . '?' . http_build_query($apiParams);

    error_log("URL da API ZapSign: $apiUrlWithParams");

    // Faz requisição para ZapSign
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrlWithParams,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $bearerToken,
            'Content-Type: application/json'
        ],
    ]);

    $apiResponse = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError) {
        throw new Exception("Erro de conectividade: " . $curlError);
    }

    if ($httpCode !== 200) {
        error_log("Erro HTTP da API ZapSign: $httpCode - $apiResponse");
        throw new Exception("Erro da API ZapSign (HTTP $httpCode): " . substr($apiResponse, 0, 200));
    }

    $zapSignData = json_decode($apiResponse, true);
    
    if (!$zapSignData) {
        throw new Exception("Resposta inválida da API ZapSign");
    }

    error_log("Resposta da API ZapSign recebida. Processando documentos...");

    // Processa os documentos
    $documentos = [];
    $documentosZapSign = is_array($zapSignData) ? $zapSignData : [];

    // Se a resposta tem paginação (formato com count, next, previous, results)
    if (isset($zapSignData['results'])) {
        $documentosZapSign = $zapSignData['results'];
        $totalCount = $zapSignData['count'] ?? 0;
        $nextPage = $zapSignData['next'] ? true : false;
        $prevPage = $zapSignData['previous'] ? true : false;
    } else {
        // Formato direto (array de documentos)
        $totalCount = count($documentosZapSign);
        $nextPage = false;
        $prevPage = false;
    }

    // Conecta ao banco para buscar dados dos associados
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    foreach ($documentosZapSign as $doc) {
        // Dados básicos do documento
        $documento = [
            'id' => $doc['open_id'] ?? 0,
            'token' => $doc['token'] ?? '',
            'name' => $doc['name'] ?? 'Documento sem nome',
            'status' => $doc['status'] ?? 'unknown',
            'status_label' => getStatusLabel($doc['status'] ?? 'unknown'),
            'status_class' => getStatusClass($doc['status'] ?? 'unknown'),
            'original_file' => $doc['original_file'] ?? null,
            'signed_file' => $doc['signed_file'] ?? null,
            'created_at' => $doc['created_at'] ?? null,
            'last_update_at' => $doc['last_update_at'] ?? null,
            'folder_path' => $doc['folder_path'] ?? '/',
            'lang' => $doc['lang'] ?? 'pt-br',
            'deleted' => $doc['deleted'] ?? false,  // ✅ ADICIONADO: Status de deletado
            'deleted_at' => $doc['deleted_at'] ?? null  // ✅ ADICIONADO: Data da exclusão
        ];

        // Dados formatados
        $documento['created_at_formatted'] = formatarDataHora($documento['created_at']);
        $documento['last_update_formatted'] = formatarDataHora($documento['last_update_at']);
        $documento['tempo_desde_criacao'] = calcularTempo($documento['created_at']);

        // Busca dados do associado pelo token do ZapSign (se disponível)
        $associado = null;
        if (!empty($documento['token'])) {
            try {
                $stmt = $db->prepare("
                    SELECT 
                        a.id,
                        a.nome,
                        a.cpf,
                        a.email,
                        a.telefone,
                        a.situacao,
                        a.zapsign_documento_id,
                        a.zapsign_status,
                        a.zapsign_data_envio,
                        a.data_filiacao
                    FROM associados a 
                    WHERE a.zapsign_documento_id = ? OR a.zapsign_link_assinatura LIKE ?
                    LIMIT 1
                ");
                $stmt->execute([$documento['id'], '%' . $documento['token'] . '%']);
                $associado = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Erro ao buscar associado: " . $e->getMessage());
            }
        }

        // Se encontrou associado, adiciona os dados
        if ($associado) {
            $documento['associado'] = [
                'id' => $associado['id'],
                'nome' => $associado['nome'],
                'cpf' => $associado['cpf'],
                'email' => $associado['email'],
                'telefone' => $associado['telefone'],
                'situacao' => $associado['situacao'],
                'data_filiacao' => $associado['data_filiacao'],
                'zapsign_status' => $associado['zapsign_status'],
                'zapsign_data_envio' => $associado['zapsign_data_envio']
            ];

            $documento['associado']['cpf_formatted'] = formatarCPF($associado['cpf']);
            $documento['associado']['telefone_formatted'] = formatarTelefone($associado['telefone']);
        } else {
            // Se não encontrou associado, tenta extrair da resposta dos signatários
            $documento['associado'] = [
                'id' => null,
                'nome' => 'Associado não encontrado',
                'cpf' => null,
                'email' => null,
                'telefone' => null,
                'situacao' => null,
                'cpf_formatted' => '-',
                'telefone_formatted' => '-'
            ];
        }

        // Filtro por busca (se especificado)
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $nomeMatch = strpos(strtolower($documento['associado']['nome'] ?? ''), $searchLower) !== false;
            $cpfMatch = strpos(preg_replace('/\D/', '', $documento['associado']['cpf'] ?? ''), preg_replace('/\D/', '', $search)) !== false;
            $docNameMatch = strpos(strtolower($documento['name']), $searchLower) !== false;
            
            if (!$nomeMatch && !$cpfMatch && !$docNameMatch) {
                continue; // Pula este documento se não corresponder à busca
            }
        }

        $documentos[] = $documento;
    }

    // Estatísticas
    $estatisticas = [
        'total_documentos' => $totalCount,
        'documentos_retornados' => count($documentos),
        'tem_proxima_pagina' => $nextPage,
        'tem_pagina_anterior' => $prevPage,
        'pagina_atual' => $page,
        'filtro_status' => $status,
        'filtro_deletados' => $showDeleted,  // ✅ ADICIONADO
        'termo_busca' => $search
    ];

    // Resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => count($documentos) . ' documento(s) encontrado(s)',
        'data' => $documentos,
        'estatisticas' => $estatisticas,
        'paginacao' => [
            'pagina_atual' => $page,
            'tem_proxima' => $nextPage,
            'tem_anterior' => $prevPage,
            'total_itens' => $totalCount
        ]
    ];

    error_log("✅ Documentos listados com sucesso: " . count($documentos) . " itens");

} catch (Exception $e) {
    error_log("❌ Erro ao listar documentos ZapSign: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => [],
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'params' => $_GET
        ]
    ];
    
    http_response_code(400);
}

// Funções auxiliares
function getStatusLabel($status) {
    $labels = [
        'pending' => 'Aguardando Assinatura',
        'signed' => 'Assinado',
        'refused' => 'Recusado',
        'expired' => 'Expirado',
        'unknown' => 'Status Desconhecido'
    ];
    
    return $labels[$status] ?? 'Status Desconhecido';
}

function getStatusClass($status) {
    $classes = [
        'pending' => 'warning',
        'signed' => 'success',
        'refused' => 'danger',
        'expired' => 'secondary',
        'unknown' => 'secondary'
    ];
    
    return $classes[$status] ?? 'secondary';
}

function formatarDataHora($dataISO) {
    if (!$dataISO) return '-';
    
    try {
        $date = new DateTime($dataISO);
        return $date->format('d/m/Y H:i');
    } catch (Exception $e) {
        return '-';
    }
}

function calcularTempo($dataISO) {
    if (!$dataISO) return '-';
    
    try {
        $date = new DateTime($dataISO);
        $now = new DateTime();
        $diff = $now->diff($date);
        
        if ($diff->days > 0) {
            return $diff->days . ' dia(s) atrás';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hora(s) atrás';
        } else {
            return $diff->i . ' minuto(s) atrás';
        }
    } catch (Exception $e) {
        return '-';
    }
}

function formatarCPF($cpf) {
    if (!$cpf) return '-';
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11) return $cpf;
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

function formatarTelefone($telefone) {
    if (!$telefone) return '-';
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    return $telefone;
}

// Limpa buffer e envia resposta
ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>