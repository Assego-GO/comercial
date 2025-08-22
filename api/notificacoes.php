<?php
/**
 * API para gerenciar notificações
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
            // Busca notificações do funcionário
            $limite = intval($_GET['limite'] ?? 20);
            $resultado = $notificacoes->buscarNotificacoesFuncionario($funcionario_id, $limite);
            
            // Formatar dados para exibição
            $notificacoes_formatadas = array_map(function($notif) {
                return [
                    'id' => $notif['id'],
                    'titulo' => $notif['titulo'],
                    'mensagem' => $notif['mensagem'],
                    'tipo' => $notif['tipo'],
                    'prioridade' => $notif['prioridade'],
                    'associado_nome' => $notif['associado_nome'],
                    'associado_cpf' => $notif['associado_cpf'],
                    'criado_por_nome' => $notif['criado_por_nome'],
                    'data_criacao' => $notif['data_criacao'],
                    'tempo_atras' => formatarTempoAtras($notif['minutos_atras']),
                    'lida' => $notif['lida'],
                    'icone' => getIconeNotificacao($notif['tipo']),
                    'cor' => getCorNotificacao($notif['tipo'], $notif['prioridade'])
                ];
            }, $resultado);
            
            echo json_encode([
                'status' => 'success',
                'data' => $notificacoes_formatadas,
                'total' => count($notificacoes_formatadas)
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