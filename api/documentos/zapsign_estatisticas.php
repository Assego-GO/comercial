<?php
/**
 * API para obter estatísticas dos documentos ZapSign
 * api/documentos/zapsign_estatisticas.php
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

    error_log("=== OBTENDO ESTATÍSTICAS ZAPSIGN ===");
    error_log("Usuário: " . $user['nome']);

    // Configuração da API ZapSign
    $apiUrl = 'https://sandbox.api.zapsign.com.br/api/v1/docs/'; // Use https://api.zapsign.com.br/api/v1/docs/ para produção
    $bearerToken = API_KEY;

    if (empty($bearerToken)) {
        throw new Exception('Token da API ZapSign não configurado');
    }

    // Cache simples para evitar muitas requisições
    $cacheFile = '../../temp/zapsign_stats_cache.json';
    $cacheTime = 300; // 5 minutos

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        if ($cachedData) {
            error_log("✅ Estatísticas obtidas do cache");
            echo json_encode([
                'status' => 'success',
                'message' => 'Estatísticas obtidas com sucesso (cache)',
                'data' => $cachedData,
                'cache' => true
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Função para fazer requisição à API ZapSign
    function fazerRequisicaoZapSign($url, $bearerToken) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
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

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            throw new Exception("Erro de conectividade: " . $curlError);
        }

        if ($httpCode !== 200) {
            throw new Exception("Erro da API ZapSign (HTTP $httpCode): " . substr($response, 0, 200));
        }

        return json_decode($response, true);
    }

    // Busca estatísticas por status
    $estatisticas = [
        'pending' => 0,
        'signed' => 0,
        'refused' => 0,
        'expired' => 0,
        'total' => 0,
        'ultima_atualizacao' => date('Y-m-d H:i:s'),
        'documentos_recentes' => [],
        'tempo_medio_assinatura' => 0,
        'taxa_assinatura' => 0
    ];

    $statusList = ['pending', 'signed', 'refused', 'expired'];
    $todosDocumentos = [];

    // Busca documentos por status
    foreach ($statusList as $status) {
        try {
            error_log("Buscando documentos com status: $status");
            
            $url = $apiUrl . '?' . http_build_query([
                'page' => 1,
                'status' => $status,
                'sort_order' => 'desc'
            ]);
            
            $data = fazerRequisicaoZapSign($url, $bearerToken);
            
            if (isset($data['count'])) {
                // Formato paginado
                $estatisticas[$status] = $data['count'];
                $documentos = $data['results'] ?? [];
            } else {
                // Formato direto
                $documentos = is_array($data) ? $data : [];
                $estatisticas[$status] = count($documentos);
            }
            
            $todosDocumentos = array_merge($todosDocumentos, $documentos);
            
            error_log("Status $status: " . $estatisticas[$status] . " documentos");
            
        } catch (Exception $e) {
            error_log("Erro ao buscar status $status: " . $e->getMessage());
            // Continua mesmo com erro em um status específico
        }
    }

    // Calcula total
    $estatisticas['total'] = array_sum([
        $estatisticas['pending'],
        $estatisticas['signed'],
        $estatisticas['refused'],
        $estatisticas['expired']
    ]);

    // Processa documentos recentes (últimos 10)
    usort($todosDocumentos, function($a, $b) {
        return strtotime($b['created_at'] ?? '1970-01-01') - strtotime($a['created_at'] ?? '1970-01-01');
    });

    $documentosRecentes = array_slice($todosDocumentos, 0, 10);
    
    foreach ($documentosRecentes as $doc) {
        $estatisticas['documentos_recentes'][] = [
            'id' => $doc['open_id'] ?? 0,
            'token' => $doc['token'] ?? '',
            'name' => $doc['name'] ?? 'Documento sem nome',
            'status' => $doc['status'] ?? 'unknown',
            'status_label' => getStatusLabel($doc['status'] ?? 'unknown'),
            'created_at' => $doc['created_at'] ?? null,
            'created_at_formatted' => formatarDataHora($doc['created_at'] ?? null),
            'tempo_desde_criacao' => calcularTempo($doc['created_at'] ?? null)
        ];
    }

    // Calcula métricas adicionais
    $documentosAssinados = array_filter($todosDocumentos, function($doc) {
        return $doc['status'] === 'signed';
    });

    if (count($documentosAssinados) > 0) {
        $temposAssinatura = [];
        
        foreach ($documentosAssinados as $doc) {
            $created = strtotime($doc['created_at'] ?? '');
            $updated = strtotime($doc['last_update_at'] ?? '');
            
            if ($created && $updated && $updated > $created) {
                $temposAssinatura[] = ($updated - $created) / 3600; // em horas
            }
        }
        
        if (count($temposAssinatura) > 0) {
            $estatisticas['tempo_medio_assinatura'] = round(array_sum($temposAssinatura) / count($temposAssinatura), 1);
        }
    }

    // Taxa de assinatura (%)
    if ($estatisticas['total'] > 0) {
        $estatisticas['taxa_assinatura'] = round(
            ($estatisticas['signed'] / $estatisticas['total']) * 100, 1
        );
    }

    // Conecta ao banco para buscar dados locais complementares
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Conta associados com ZapSign configurado
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_com_zapsign
            FROM associados 
            WHERE zapsign_documento_id IS NOT NULL 
            AND zapsign_documento_id != ''
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estatisticas['associados_com_zapsign'] = $result['total_com_zapsign'] ?? 0;
        
        // Busca associados com documentos pendentes há mais tempo
        $stmt = $db->prepare("
            SELECT 
                a.nome,
                a.cpf,
                a.zapsign_data_envio,
                DATEDIFF(NOW(), a.zapsign_data_envio) as dias_pendente
            FROM associados a
            WHERE a.zapsign_status = 'ENVIADO'
            AND a.zapsign_data_envio IS NOT NULL
            ORDER BY a.zapsign_data_envio ASC
            LIMIT 5
        ");
        $stmt->execute();
        $pendentesLocal = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $estatisticas['documentos_urgentes'] = array_map(function($assoc) {
            return [
                'nome' => $assoc['nome'],
                'cpf' => formatarCPF($assoc['cpf']),
                'dias_pendente' => $assoc['dias_pendente'],
                'data_envio' => formatarData($assoc['zapsign_data_envio'])
            ];
        }, $pendentesLocal);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar dados locais: " . $e->getMessage());
        $estatisticas['associados_com_zapsign'] = 0;
        $estatisticas['documentos_urgentes'] = [];
    }

    // Adiciona informações de sistema
    $estatisticas['info_sistema'] = [
        'api_zapsign' => strpos($apiUrl, 'sandbox') !== false ? 'Sandbox' : 'Produção',
        'total_requisicoes' => count($statusList),
        'cache_ativo' => true,
        'cache_valido_ate' => date('Y-m-d H:i:s', time() + $cacheTime)
    ];

    // Salva no cache
    if (!is_dir('../../temp')) {
        mkdir('../../temp', 0755, true);
    }
    file_put_contents($cacheFile, json_encode($estatisticas, JSON_UNESCAPED_UNICODE));

    // Resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => 'Estatísticas obtidas com sucesso',
        'data' => $estatisticas,
        'cache' => false
    ];

    error_log("✅ Estatísticas ZapSign obtidas com sucesso");
    error_log("Total: " . $estatisticas['total'] . " | Pendentes: " . $estatisticas['pending'] . " | Assinados: " . $estatisticas['signed']);

} catch (Exception $e) {
    error_log("❌ Erro ao obter estatísticas ZapSign: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => [
            'pending' => 0,
            'signed' => 0,
            'refused' => 0,
            'expired' => 0,
            'total' => 0,
            'erro' => true
        ],
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'user_id' => $_SESSION['user_id'] ?? null
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

function formatarDataHora($dataISO) {
    if (!$dataISO) return '-';
    
    try {
        $date = new DateTime($dataISO);
        return $date->format('d/m/Y H:i');
    } catch (Exception $e) {
        return '-';
    }
}

function formatarData($data) {
    if (!$data) return '-';
    
    try {
        $date = new DateTime($data);
        return $date->format('d/m/Y');
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

// Limpa buffer e envia resposta
ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>