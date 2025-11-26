<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Tratar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function jsonResponse($status, $message, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function getStatusLabel($registro) {
    // Verificar se recebeu (data válida com ano > 0)
    if (!empty($registro['data_recebimento']) && 
        substr($registro['data_recebimento'], 0, 4) !== '0000') {
        return 'Recebido';
    }
    
    // Verificar se tem data prevista válida
    if (empty($registro['data_prevista']) || 
        substr($registro['data_prevista'], 0, 4) === '0000') {
        return 'Sem Data';
    }
    
    // Verificar se está vencido
    $dataPrevista = strtotime($registro['data_prevista']);
    $hoje = strtotime(date('Y-m-d'));
    
    if ($dataPrevista < $hoje) {
        return 'Vencido';
    }
    
    return 'Pendente';
}

function getStatusColor($registro) {
    $status = getStatusLabel($registro);
    switch ($status) {
        case 'Recebido': return '#10b981';
        case 'Vencido': return '#ef4444';
        case 'Sem Data': return '#6b7280';
        default: return '#f59e0b';
    }
}

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';

    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Método não permitido. Use POST.');
    }

    // Ler dados JSON
    $input = file_get_contents('php://input');
    $filtros = json_decode($input, true);

    if (!$filtros) {
        $filtros = $_POST;
    }

    if (empty($filtros)) {
        jsonResponse('error', 'Nenhum filtro recebido');
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Construir query base
    $sql = "SELECT 
                a.id,
                a.nome,
                a.rg,
                a.cpf,
                a.telefone,
                a.email,
                p.valor,
                p.data_prevista,
                p.data_recebimento
            FROM Associados a
            INNER JOIN Peculio p ON a.id = p.associado_id
            WHERE 1=1";
    
    $params = [];

    // Aplicar filtros por tipo
    // NOTA: Usando YEAR() para evitar erro com '0000-00-00' no MySQL modo estrito
    $tipo = $filtros['tipo'] ?? 'todos';
    
    switch ($tipo) {
        case 'recebidos':
            $sql .= " AND p.data_recebimento IS NOT NULL 
                      AND YEAR(p.data_recebimento) > 0";
            break;
            
        case 'pendentes':
            $sql .= " AND (p.data_recebimento IS NULL 
                      OR YEAR(p.data_recebimento) = 0)";
            break;
            
        case 'sem_data':
            $sql .= " AND (p.data_prevista IS NULL 
                      OR YEAR(p.data_prevista) = 0)";
            break;
            
        case 'vencidos':
            $sql .= " AND p.data_prevista IS NOT NULL 
                      AND YEAR(p.data_prevista) > 0
                      AND p.data_prevista < CURDATE()
                      AND (p.data_recebimento IS NULL 
                           OR YEAR(p.data_recebimento) = 0)";
            break;
            
        case 'proximos':
            $sql .= " AND p.data_prevista IS NOT NULL 
                      AND YEAR(p.data_prevista) > 0
                      AND p.data_prevista >= CURDATE()
                      AND p.data_prevista <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                      AND (p.data_recebimento IS NULL 
                           OR YEAR(p.data_recebimento) = 0)";
            break;
            
        case 'todos':
        default:
            break;
    }

    // Aplicar filtro de período se especificado
    if (!empty($filtros['periodo']) && is_array($filtros['periodo'])) {
        $tipoData = $filtros['periodo']['tipo_data'] ?? 'data_prevista';
        $dataInicio = $filtros['periodo']['data_inicio'] ?? null;
        $dataFim = $filtros['periodo']['data_fim'] ?? null;

        $campoData = ($tipoData === 'data_recebimento') ? 'p.data_recebimento' : 'p.data_prevista';

        if (!empty($dataInicio)) {
            $sql .= " AND $campoData >= ?";
            $params[] = $dataInicio;
        }
        if (!empty($dataFim)) {
            $sql .= " AND $campoData <= ?";
            $params[] = $dataFim;
        }
    }

    // Aplicar ordenação
    $ordenarPor = $filtros['ordenar_por'] ?? 'nome_asc';
    $orderBy = match($ordenarPor) {
        'nome_asc' => 'a.nome ASC',
        'nome_desc' => 'a.nome DESC',
        'data_prevista_asc' => 'p.data_prevista ASC',
        'data_prevista_desc' => 'p.data_prevista DESC',
        'data_recebimento_asc' => 'p.data_recebimento ASC',
        'data_recebimento_desc' => 'p.data_recebimento DESC',
        'valor_asc' => 'p.valor ASC',
        'valor_desc' => 'p.valor DESC',
        default => 'a.nome ASC'
    };
    
    $sql .= " ORDER BY $orderBy";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estatísticas
    $estatisticas = [
        'total' => count($registros),
        'pendentes' => 0,
        'recebidos' => 0,
        'vencidos' => 0,
        'sem_data' => 0,
        'valor_total' => 0,
        'valor_pendente' => 0,
        'valor_recebido' => 0
    ];

    // Processar registros e calcular estatísticas
    foreach ($registros as &$reg) {
        $status = getStatusLabel($reg);
        $reg['status_calculado'] = $status;
        $reg['status_cor'] = getStatusColor($reg);
        
        $valor = floatval($reg['valor'] ?? 0);
        $estatisticas['valor_total'] += $valor;
        
        switch ($status) {
            case 'Recebido':
                $estatisticas['recebidos']++;
                $estatisticas['valor_recebido'] += $valor;
                break;
            case 'Vencido':
                $estatisticas['vencidos']++;
                $estatisticas['valor_pendente'] += $valor;
                break;
            case 'Sem Data':
                $estatisticas['sem_data']++;
                $estatisticas['valor_pendente'] += $valor;
                break;
            default:
                $estatisticas['pendentes']++;
                $estatisticas['valor_pendente'] += $valor;
        }
    }

    // Verificar formato de saída
    $formato = $filtros['formato'] ?? 'html';

    if ($formato === 'csv') {
        gerarCSV($registros, $filtros);
    } elseif ($formato === 'excel') {
        gerarExcel($registros, $estatisticas, $filtros);
    } else {
        // Retornar JSON para HTML/preview
        jsonResponse('success', 'Relatório gerado com sucesso', [
            'registros' => $registros,
            'estatisticas' => $estatisticas,
            'filtros_aplicados' => [
                'tipo' => $tipo,
                'ordenacao' => $ordenarPor,
                'periodo' => $filtros['periodo'] ?? null
            ]
        ]);
    }

} catch (PDOException $e) {
    error_log("ERRO PDO ao gerar relatório: " . $e->getMessage());
    jsonResponse('error', 'Erro de banco de dados: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("ERRO ao gerar relatório: " . $e->getMessage());
    jsonResponse('error', 'Erro ao gerar relatório: ' . $e->getMessage());
}

function gerarCSV($registros, $filtros) {
    $campos = $filtros['campos'] ?? ['nome', 'valor', 'data_prevista', 'data_recebimento', 'status'];
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_peculio_' . date('Y-m-d_His') . '.csv"');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Mapear campos para cabeçalhos
    $headerMap = [
        'nome' => 'Nome',
        'rg' => 'RG',
        'cpf' => 'CPF',
        'telefone' => 'Telefone',
        'email' => 'E-mail',
        'valor' => 'Valor',
        'data_prevista' => 'Data Prevista',
        'data_recebimento' => 'Data Recebimento',
        'status' => 'Status'
    ];
    
    // Cabeçalhos
    $headers = [];
    foreach ($campos as $campo) {
        if (isset($headerMap[$campo])) {
            $headers[] = $headerMap[$campo];
        }
    }
    fputcsv($output, $headers, ';');
    
    // Dados
    foreach ($registros as $reg) {
        $row = [];
        foreach ($campos as $campo) {
            if ($campo === 'status') {
                $row[] = $reg['status_calculado'] ?? getStatusLabel($reg);
            } elseif ($campo === 'valor') {
                $row[] = number_format(floatval($reg['valor'] ?? 0), 2, ',', '.');
            } elseif ($campo === 'data_prevista' || $campo === 'data_recebimento') {
                $data = $reg[$campo] ?? '';
                if (!empty($data) && substr($data, 0, 4) !== '0000') {
                    $row[] = date('d/m/Y', strtotime($data));
                } else {
                    $row[] = '-';
                }
            } else {
                $row[] = $reg[$campo] ?? '';
            }
        }
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

function gerarExcel($registros, $estatisticas, $filtros) {
    $campos = $filtros['campos'] ?? ['nome', 'valor', 'data_prevista', 'data_recebimento', 'status'];
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_peculio_' . date('Y-m-d_His') . '.xls"');
    
    $headerMap = [
        'nome' => 'Nome',
        'rg' => 'RG',
        'cpf' => 'CPF',
        'telefone' => 'Telefone',
        'email' => 'E-mail',
        'valor' => 'Valor',
        'data_prevista' => 'Data Prevista',
        'data_recebimento' => 'Data Recebimento',
        'status' => 'Status'
    ];
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    
    // Título
    echo '<h2>Relatório de Pecúlios - ASSEGO</h2>';
    echo '<p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>';
    
    // Estatísticas
    echo '<table border="1" style="margin-bottom: 20px;">';
    echo '<tr><th>Total</th><th>Pendentes</th><th>Recebidos</th><th>Vencidos</th><th>Valor Total</th></tr>';
    echo '<tr>';
    echo '<td>' . $estatisticas['total'] . '</td>';
    echo '<td>' . $estatisticas['pendentes'] . '</td>';
    echo '<td>' . $estatisticas['recebidos'] . '</td>';
    echo '<td>' . $estatisticas['vencidos'] . '</td>';
    echo '<td>R$ ' . number_format($estatisticas['valor_total'], 2, ',', '.') . '</td>';
    echo '</tr></table>';
    
    // Tabela principal
    echo '<table border="1">';
    echo '<tr>';
    foreach ($campos as $campo) {
        if (isset($headerMap[$campo])) {
            echo '<th style="background-color: #4a5568; color: white;">' . $headerMap[$campo] . '</th>';
        }
    }
    echo '</tr>';
    
    foreach ($registros as $reg) {
        echo '<tr>';
        foreach ($campos as $campo) {
            if ($campo === 'status') {
                $status = $reg['status_calculado'] ?? getStatusLabel($reg);
                $cor = getStatusColor($reg);
                echo '<td style="color: ' . $cor . '; font-weight: bold;">' . $status . '</td>';
            } elseif ($campo === 'valor') {
                echo '<td>R$ ' . number_format(floatval($reg['valor'] ?? 0), 2, ',', '.') . '</td>';
            } elseif ($campo === 'data_prevista' || $campo === 'data_recebimento') {
                $data = $reg[$campo] ?? '';
                if (!empty($data) && substr($data, 0, 4) !== '0000') {
                    echo '<td>' . date('d/m/Y', strtotime($data)) . '</td>';
                } else {
                    echo '<td>-</td>';
                }
            } else {
                echo '<td>' . htmlspecialchars($reg[$campo] ?? '') . '</td>';
            }
        }
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>' ;
    exit;
}
?>