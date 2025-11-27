<?php
header('Content-Type: application/json; charset=utf-8');
// listar_peculios.php - API para listar TODOS os pecúlios (SEM LIMITE)

// Função para resposta JSON
function jsonResponse($status, $message, $data = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Incluir dependências
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';

    // Conectar no banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Parâmetro opcional de filtro
    $filtro = $_GET['filtro'] ?? 'todos';

    // Query SIMPLES - busca TODOS os registros (SEM LIMIT)
    $sql = "SELECT
        a.id,
        a.nome,
        a.rg,
        a.email,
        a.telefone,
        COALESCE(p.valor, 0) as valor,
        p.data_prevista,
        p.data_recebimento
    FROM Associados a
    INNER JOIN Peculio p ON a.id = p.associado_id
    ORDER BY a.nome ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$resultados || count($resultados) === 0) {
        jsonResponse('success', 'Nenhum pecúlio cadastrado', [
            'peculios' => [],
            'estatisticas' => [
                'total' => 0,
                'pendentes' => 0,
                'recebidos' => 0,
                'vencidos' => 0,
                'proximos_30_dias' => 0,
                'valor_total_pendente' => 0,
                'valor_total_recebido' => 0
            ]
        ]);
    }

    // Processar TODOS os resultados NO PHP
    $hoje = new DateTime();
    $dadosProcessados = [];

    foreach ($resultados as $resultado) {
        // Limpar datas inválidas
        $dataPrevisiaLimpa = null;
        if ($resultado['data_prevista'] && $resultado['data_prevista'] !== '0000-00-00') {
            $dataPrevisiaLimpa = $resultado['data_prevista'];
        }

        $dataRecebimentoLimpa = null;
        if ($resultado['data_recebimento'] && $resultado['data_recebimento'] !== '0000-00-00') {
            $dataRecebimentoLimpa = $resultado['data_recebimento'];
        }

        // Calcular status
        $status = $dataRecebimentoLimpa ? 'recebido' : 'pendente';

        // Calcular dias até vencimento
        $diasAteVencimento = 9999;
        if ($dataPrevisiaLimpa && $status === 'pendente') {
            try {
                $dataPrevista = new DateTime($dataPrevisiaLimpa);
                $diff = $hoje->diff($dataPrevista);
                $diasAteVencimento = (int)$diff->format('%r%a');
            } catch (Exception $e) {
                $diasAteVencimento = 9999;
            }
        }

        $item = [
            'id' => $resultado['id'],
            'nome' => $resultado['nome'],
            'rg' => $resultado['rg'],
            'email' => $resultado['email'],
            'telefone' => $resultado['telefone'],
            'valor' => (float)$resultado['valor'],
            'data_prevista' => $dataPrevisiaLimpa,
            'data_recebimento' => $dataRecebimentoLimpa,
            'status' => $status,
            'dias_ate_vencimento' => $diasAteVencimento
        ];

        // Adicionar prioridade visual
        if ($status === 'pendente' && $dataPrevisiaLimpa) {
            if ($diasAteVencimento < 0) {
                $item['prioridade'] = 'vencido';
                $item['urgencia'] = 'alta';
            } elseif ($diasAteVencimento <= 30) {
                $item['prioridade'] = 'proximo';
                $item['urgencia'] = 'alta';
            } elseif ($diasAteVencimento <= 60) {
                $item['prioridade'] = 'atencao';
                $item['urgencia'] = 'media';
            } else {
                $item['prioridade'] = 'normal';
                $item['urgencia'] = 'baixa';
            }
        } else {
            $item['prioridade'] = $status === 'recebido' ? 'concluido' : 'normal';
            $item['urgencia'] = 'baixa';
        }

        $dadosProcessados[] = $item;
    }

    // Aplicar filtros NO PHP (se necessário)
    $dadosFiltrados = $dadosProcessados;
    
    switch ($filtro) {
        case 'pendentes':
            $dadosFiltrados = array_filter($dadosProcessados, function($p) {
                return $p['status'] === 'pendente';
            });
            break;
        case 'recebidos':
            $dadosFiltrados = array_filter($dadosProcessados, function($p) {
                return $p['status'] === 'recebido';
            });
            break;
        case 'proximos':
            $dadosFiltrados = array_filter($dadosProcessados, function($p) {
                return $p['status'] === 'pendente' && 
                       $p['data_prevista'] !== null && 
                       $p['dias_ate_vencimento'] >= -30 && 
                       $p['dias_ate_vencimento'] <= 90;
            });
            break;
    }

    // Converter array associativo de volta para indexado
    $dadosFiltrados = array_values($dadosFiltrados);

    // Ordenar NO PHP: pendentes primeiro, depois por data mais próxima
    usort($dadosFiltrados, function($a, $b) {
        // Primeiro: pendentes antes de recebidos
        if ($a['status'] !== $b['status']) {
            return $a['status'] === 'pendente' ? -1 : 1;
        }
        
        // Segundo: ordenar por data prevista (mais próxima primeiro)
        $dataA = $a['data_prevista'] ?? '9999-12-31';
        $dataB = $b['data_prevista'] ?? '9999-12-31';
        
        if ($dataA !== $dataB) {
            return $dataA <=> $dataB;
        }
        
        // Terceiro: ordenar por nome
        return $a['nome'] <=> $b['nome'];
    });

    // Estatísticas sobre TODOS os pecúlios processados
    $stats = [
        'total' => count($dadosFiltrados),
        'pendentes' => count(array_filter($dadosFiltrados, fn($p) => $p['status'] === 'pendente')),
        'recebidos' => count(array_filter($dadosFiltrados, fn($p) => $p['status'] === 'recebido')),
        'vencidos' => count(array_filter($dadosFiltrados, fn($p) => isset($p['prioridade']) && $p['prioridade'] === 'vencido')),
        'proximos_30_dias' => count(array_filter($dadosFiltrados, fn($p) => isset($p['prioridade']) && $p['prioridade'] === 'proximo')),
        'valor_total_pendente' => array_sum(array_map(fn($p) => $p['status'] === 'pendente' ? $p['valor'] : 0, $dadosFiltrados)),
        'valor_total_recebido' => array_sum(array_map(fn($p) => $p['status'] === 'recebido' ? $p['valor'] : 0, $dadosFiltrados))
    ];

    jsonResponse('success', count($dadosFiltrados) . ' pecúlios encontrados', [
        'peculios' => $dadosFiltrados,
        'estatisticas' => $stats
    ]);

} catch (Exception $e) {
    error_log("ERRO ao listar pecúlios: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    jsonResponse('error', 'Erro ao listar pecúlios: ' . $e->getMessage());
}
?>