<?php
/**
 * API para gerenciar notificações - VERSÃO CORRIGIDA COM DATA_LEITURA
 * api/notificacoes.php
 */

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/NotificacoesManager.php';

try {
    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    $usuario = $auth->getUser();
    $funcionario_id = $usuario['id'];
    
    $notificacoes = new NotificacoesManager();
    
    $acao = $_GET['acao'] ?? $_POST['acao'] ?? '';
    
    switch ($acao) {
        case 'buscar':
        case 'listar':
        case 'consultar':
        case 'obter':
        case 'get':
            // Busca notificações do funcionário - VERSÃO MELHORADA
            $limite = intval($_GET['limite'] ?? 50);
            $pagina = intval($_GET['pagina'] ?? 1);
            $registros_por_pagina = intval($_GET['registros_por_pagina'] ?? 10);
            $tipo = $_GET['tipo'] ?? 'todos';
            $prioridade = $_GET['prioridade'] ?? 'todas';
            $status = $_GET['status'] ?? 'todas';
            $busca = $_GET['busca'] ?? '';
            
            // Construir query melhorada com JOIN para pegar nomes
            $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            
            $sql = "SELECT n.id, n.titulo, n.mensagem, n.tipo, n.prioridade, 
                           n.lida, n.data_criacao, n.data_leitura,
                           a.nome as associado_nome, a.cpf as associado_cpf,
                           f.nome as criado_por_nome,
                           TIMESTAMPDIFF(MINUTE, n.data_criacao, NOW()) as minutos_atras
                    FROM Notificacoes n
                    LEFT JOIN Associados a ON n.associado_id = a.id
                    LEFT JOIN Funcionarios f ON n.criado_por = f.id
                    LEFT JOIN Funcionarios func_logado ON func_logado.id = ?
                    WHERE (n.funcionario_id = ? OR (n.funcionario_id IS NULL AND n.departamento_id = func_logado.departamento_id))
                    AND n.ativo = 1";
            
            $params = [$funcionario_id, $funcionario_id];
            
            // Filtros adicionais
            if ($tipo !== 'todos') {
                $sql .= " AND n.tipo = ?";
                $params[] = $tipo;
            }
            
            if ($prioridade !== 'todas') {
                $sql .= " AND n.prioridade = ?";
                $params[] = $prioridade;
            }
            
            if ($status !== 'todas') {
                $sql .= " AND n.lida = ?";
                $params[] = intval($status);
            }
            
            if (!empty($busca)) {
                $sql .= " AND (n.titulo LIKE ? OR n.mensagem LIKE ? OR a.nome LIKE ?)";
                $params[] = "%$busca%";
                $params[] = "%$busca%";
                $params[] = "%$busca%";
            }
            
            // Contar total de registros
            $sqlCount = str_replace("SELECT n.id, n.titulo, n.mensagem, n.tipo, n.prioridade, 
                           n.lida, n.data_criacao, n.data_leitura,
                           a.nome as associado_nome, a.cpf as associado_cpf,
                           f.nome as criado_por_nome,
                           TIMESTAMPDIFF(MINUTE, n.data_criacao, NOW()) as minutos_atras", "SELECT COUNT(*) as total", $sql);
            
            $stmtCount = $db->prepare($sqlCount);
            $stmtCount->execute($params);
            $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Aplicar paginação
            $sql .= " ORDER BY n.data_criacao DESC LIMIT ? OFFSET ?";
            $offset = ($pagina - 1) * $registros_por_pagina;
            $params[] = $registros_por_pagina;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatar dados para exibição - INCLUINDO data_leitura FORMATADA
            $notificacoes_formatadas = array_map(function($notif) {
                return [
                    'id' => $notif['id'],
                    'titulo' => $notif['titulo'],
                    'mensagem' => $notif['mensagem'],
                    'tipo' => $notif['tipo'],
                    'prioridade' => $notif['prioridade'],
                    'associado_nome' => $notif['associado_nome'] ?? 'Sistema',
                    'associado_cpf' => $notif['associado_cpf'] ?? null,
                    'criado_por_nome' => $notif['criado_por_nome'] ?? 'Sistema',
                    'data_criacao' => $notif['data_criacao'],
                    'data_leitura' => $notif['data_leitura'], // ✅ INCLUINDO data_leitura
                    'data_leitura_formatada' => $notif['data_leitura'] ? 
                        date('d/m/Y H:i:s', strtotime($notif['data_leitura'])) : null, // ✅ FORMATADA
                    'tempo_atras' => formatarTempoAtras($notif['minutos_atras']),
                    'lida' => intval($notif['lida']),
                    'icone' => getIconeNotificacao($notif['tipo']),
                    'cor' => getCorNotificacao($notif['tipo'], $notif['prioridade'])
                ];
            }, $resultado);
            
            // Calcular paginação
            $totalPaginas = ceil($totalRegistros / $registros_por_pagina);
            
            echo json_encode([
                'status' => 'success',
                'data' => $notificacoes_formatadas,
                'total' => count($notificacoes_formatadas),
                'paginacao' => [
                    'pagina_atual' => $pagina,
                    'registros_por_pagina' => $registros_por_pagina,
                    'total_registros' => $totalRegistros,
                    'total_paginas' => $totalPaginas,
                    'tem_proxima' => $pagina < $totalPaginas,
                    'tem_anterior' => $pagina > 1
                ]
            ]);
            break;
            
        case 'contar':
            // Conta notificações não lidas
            $total = $notificacoes->contarNaoLidas($funcionario_id);
            echo json_encode([
                'status' => 'success',
                'total' => $total
            ]);
            break;
            
        case 'marcar_lida':
            // Marca notificação como lida
            $notificacao_id = intval($_POST['notificacao_id'] ?? 0);
            
            if (!$notificacao_id) {
                throw new Exception('ID da notificação não informado');
            }
            
            $sucesso = $notificacoes->marcarComoLida($notificacao_id, $funcionario_id);
            
            echo json_encode([
                'status' => $sucesso ? 'success' : 'error',
                'message' => $sucesso ? 'Notificação marcada como lida' : 'Erro ao marcar notificação'
            ]);
            break;
            
        case 'marcar_todas_lidas':
            // Marca todas as notificações do funcionário como lidas
            $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            
            $sql = "UPDATE Notificacoes n
                    LEFT JOIN Funcionarios f ON f.id = ?
                    SET n.lida = 1, n.data_leitura = NOW()
                    WHERE (n.funcionario_id = ? OR (n.funcionario_id IS NULL AND n.departamento_id = f.departamento_id))
                    AND n.lida = 0
                    AND n.ativo = 1";
            
            $stmt = $db->prepare($sql);
            $sucesso = $stmt->execute([$funcionario_id, $funcionario_id]);
            $total_marcadas = $stmt->rowCount();
            
            echo json_encode([
                'status' => $sucesso ? 'success' : 'error',
                'message' => $sucesso ? "Todas as $total_marcadas notificações foram marcadas como lidas" : 'Erro ao marcar notificações',
                'total_marcadas' => $total_marcadas
            ]);
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Formata tempo decorrido
 */
function formatarTempoAtras($minutos) {
    if ($minutos < 1) {
        return 'Agora mesmo';
    } elseif ($minutos < 60) {
        return $minutos . ' min atrás';
    } elseif ($minutos < 1440) { // menos de 24h
        $horas = floor($minutos / 60);
        return $horas . 'h atrás';
    } else {
        $dias = floor($minutos / 1440);
        return $dias . ' dia' . ($dias > 1 ? 's' : '') . ' atrás';
    }
}

/**
 * Retorna ícone baseado no tipo
 */
function getIconeNotificacao($tipo) {
    $icones = [
        'ALTERACAO_FINANCEIRO' => 'fas fa-dollar-sign',
        'NOVA_OBSERVACAO' => 'fas fa-sticky-note',
        'ALTERACAO_CADASTRO' => 'fas fa-user-edit'
    ];
    
    return $icones[$tipo] ?? 'fas fa-bell';
}

/**
 * Retorna cor baseada no tipo e prioridade
 */
function getCorNotificacao($tipo, $prioridade) {
    if ($prioridade === 'URGENTE') return '#dc3545'; // vermelho
    if ($prioridade === 'ALTA') return '#fd7e14'; // laranja
    
    $cores = [
        'ALTERACAO_FINANCEIRO' => '#28a745', // verde
        'NOVA_OBSERVACAO' => '#17a2b8', // azul claro
        'ALTERACAO_CADASTRO' => '#6f42c1' // roxo
    ];
    
    return $cores[$tipo] ?? '#6c757d'; // cinza padrão
}
?>