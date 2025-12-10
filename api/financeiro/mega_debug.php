<?php
/**
 * TESTE SIMPLES - Diagnosticar erro 500
 * Acesse: http://172.16.253.44/matheus/comercial/api/financeiro/teste_importacao.php
 */

// Limpar qualquer output anterior
while (ob_get_level()) ob_end_clean();
ob_start();

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Array para coletar erros
$diagnostico = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// TESTE 1: PHP básico
$diagnostico['tests']['php_version'] = [
    'status' => 'OK',
    'value' => PHP_VERSION
];

// TESTE 2: Sessão
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $diagnostico['tests']['sessao'] = [
        'status' => 'OK',
        'funcionario_id' => $_SESSION['funcionario_id'] ?? null,
        'user_id' => $_SESSION['user_id'] ?? null
    ];
} catch (Exception $e) {
    $diagnostico['tests']['sessao'] = [
        'status' => 'ERRO',
        'message' => $e->getMessage()
    ];
}

// TESTE 3: Arquivo de configuração
$configPath = __DIR__ . '/../../config/database.php';
if (file_exists($configPath)) {
    $diagnostico['tests']['config_file'] = [
        'status' => 'OK',
        'path' => $configPath,
        'readable' => is_readable($configPath)
    ];
    
    // Tentar carregar
    try {
        ob_start();
        include_once $configPath;
        ob_end_clean();
        
        $diagnostico['tests']['config_loaded'] = [
            'status' => 'OK',
            'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'não definido',
            'DB_USER' => defined('DB_USER') ? DB_USER : 'não definido',
            'DB_NAME_CADASTRO' => defined('DB_NAME_CADASTRO') ? DB_NAME_CADASTRO : 'não definido'
        ];
    } catch (Exception $e) {
        $diagnostico['tests']['config_loaded'] = [
            'status' => 'ERRO',
            'message' => $e->getMessage()
        ];
    }
} else {
    $diagnostico['tests']['config_file'] = [
        'status' => 'ERRO',
        'message' => 'Arquivo não encontrado',
        'path' => $configPath
    ];
}

// TESTE 4: Conexão com banco
try {
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $user = defined('DB_USER') ? DB_USER : 'superuser';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $dbname = defined('DB_NAME_CADASTRO') ? DB_NAME_CADASTRO : 'wwasse_cadastro';
    
    $conn = new mysqli($host, $user, $pass, $dbname);
    
    if ($conn->connect_error) {
        $diagnostico['tests']['database'] = [
            'status' => 'ERRO',
            'message' => $conn->connect_error
        ];
    } else {
        $conn->set_charset("utf8mb4");
        
        // Testar query simples
        $result = $conn->query("SELECT COUNT(*) as total FROM Associados");
        if ($result) {
            $row = $result->fetch_assoc();
            $diagnostico['tests']['database'] = [
                'status' => 'OK',
                'total_associados' => $row['total']
            ];
        } else {
            $diagnostico['tests']['database'] = [
                'status' => 'ERRO',
                'message' => 'Query falhou: ' . $conn->error
            ];
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    $diagnostico['tests']['database'] = [
        'status' => 'ERRO',
        'message' => $e->getMessage()
    ];
}

// TESTE 5: Permissões de escrita
$logPath = __DIR__ . '/teste_log.txt';
try {
    $written = @file_put_contents($logPath, "Teste de escrita\n");
    if ($written !== false) {
        $diagnostico['tests']['write_permission'] = [
            'status' => 'OK',
            'path' => $logPath,
            'bytes_written' => $written
        ];
        @unlink($logPath); // Limpar
    } else {
        $diagnostico['tests']['write_permission'] = [
            'status' => 'ERRO',
            'message' => 'Não foi possível escrever',
            'path' => $logPath
        ];
    }
} catch (Exception $e) {
    $diagnostico['tests']['write_permission'] = [
        'status' => 'ERRO',
        'message' => $e->getMessage()
    ];
}

// TESTE 6: Testar query do histórico
if (isset($conn) && $conn && !$conn->connect_error) {
    try {
        $conn = new mysqli($host, $user, $pass, $dbname);
        $conn->set_charset("utf8mb4");
        
        $sql = "SELECT 
                    h.id,
                    h.data_importacao,
                    h.total_registros,
                    h.adimplentes as quitados,
                    h.inadimplentes as pendentes
                FROM Historico_Importacoes_ASAAS h
                ORDER BY h.data_importacao DESC
                LIMIT 1";
        
        $result = $conn->query($sql);
        
        if ($result) {
            $row = $result->fetch_assoc();
            $diagnostico['tests']['historico_query'] = [
                'status' => 'OK',
                'ultimo_registro' => $row
            ];
        } else {
            $diagnostico['tests']['historico_query'] = [
                'status' => 'ERRO',
                'message' => $conn->error,
                'sql' => $sql
            ];
        }
        
        $conn->close();
    } catch (Exception $e) {
        $diagnostico['tests']['historico_query'] = [
            'status' => 'ERRO',
            'message' => $e->getMessage()
        ];
    }
}

// TESTE 7: Verificar se o action funciona
$action = $_GET['action'] ?? 'none';
$diagnostico['tests']['request'] = [
    'status' => 'OK',
    'method' => $_SERVER['REQUEST_METHOD'],
    'action' => $action,
    'files_uploaded' => isset($_FILES['csv_file']) ? 'yes' : 'no'
];

// RESUMO
$diagnostico['summary'] = [
    'total_tests' => count($diagnostico['tests']),
    'passed' => count(array_filter($diagnostico['tests'], function($test) {
        return $test['status'] === 'OK';
    })),
    'failed' => count(array_filter($diagnostico['tests'], function($test) {
        return $test['status'] === 'ERRO';
    }))
];

// Retornar JSON
ob_end_clean();
echo json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;