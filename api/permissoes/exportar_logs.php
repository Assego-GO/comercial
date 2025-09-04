<?php

// ============================================
// api/permissoes/exportar_logs.php
// ============================================
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('HTTP/1.0 401 Unauthorized');
    echo 'Não autorizado';
    exit;
}

$permissoes = Permissoes::getInstance();
if (!$permissoes->isSuperAdmin() && !$permissoes->hasPermission('SISTEMA_PERMISSOES', 'EXPORT')) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sem permissão';
    exit;
}

$formato = $_GET['formato'] ?? 'csv';
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$resultado = $_GET['resultado'] ?? null;

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    $where = [];
    $params = [];
    
    if ($dataInicio) {
        $where[] = "DATE(l.criado_em) >= ?";
        $params[] = $dataInicio;
    }
    
    if ($dataFim) {
        $where[] = "DATE(l.criado_em) <= ?";
        $params[] = $dataFim;
    }
    
    if ($resultado) {
        $where[] = "l.resultado = ?";
        $params[] = $resultado;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT 
                l.id,
                l.criado_em as data_hora,
                f.nome as funcionario,
                l.acao,
                rec.nome as recurso,
                p.nome as permissao,
                l.resultado,
                l.motivo_negacao,
                l.ip
            FROM log_acessos l
            INNER JOIN Funcionarios f ON l.funcionario_id = f.id
            LEFT JOIN recursos rec ON l.recurso_id = rec.id
            LEFT JOIN permissoes p ON l.permissao_id = p.id
            $whereClause
            ORDER BY l.criado_em DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($formato === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=logs_permissoes_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($output, ['ID', 'Data/Hora', 'Funcionário', 'Ação', 'Recurso', 'Permissão', 'Resultado', 'Motivo', 'IP']);
        
        // Dados
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['data_hora'],
                $log['funcionario'],
                $log['acao'],
                $log['recurso'] ?? '-',
                $log['permissao'] ?? '-',
                $log['resultado'],
                $log['motivo_negacao'] ?? '-',
                $log['ip'] ?? '-'
            ]);
        }
        
        fclose($output);
        
    } else if ($formato === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=logs_permissoes_' . date('Y-m-d') . '.json');
        
        echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Erro ao exportar logs';
}