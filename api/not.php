<?php
/**
 * Página para testar o sistema de notificações
 * test_notificacoes.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/NotificacoesManager.php';

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

$usuario = $auth->getUser();
$funcionario_id = $usuario['id'];

// Buscar apenas o associado de teste específico (ID 16917)
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $stmt = $db->prepare("SELECT id, nome, cpf, situacao FROM Associados WHERE id = 16917");
    $stmt->execute();
    $associado_teste = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($associado_teste) {
        $associados = [$associado_teste]; // Array com apenas o associado de teste
    } else {
        $associados = [];
        $erro_db = "Associado de teste (ID: 16917) não encontrado na base de dados";
    }
} catch (Exception $e) {
    $associados = [];
    $erro_db = $e->getMessage();
}

// Processar ações de teste
$resultado_teste = '';
$logs_teste = [];

if ($_POST) {
    try {
        $notificacoes = new NotificacoesManager();
        $acao = $_POST['acao'] ?? '';
        
        switch ($acao) {
            case 'teste_financeiro':
                $associado_id = intval($_POST['associado_id']);
                $campos_alterados = [
                    'situacaoFinanceira' => [
                        'antes' => 'Inadimplente',
                        'depois' => 'Adimplente'
                    ],
                    'tipoAssociado' => [
                        'antes' => 'Pensionista',
                        'depois' => 'Contribuinte'
                    ]
                ];
                
                $notif_id = $notificacoes->notificarAlteracaoFinanceiro(
                    $associado_id,
                    $campos_alterados,
                    $funcionario_id
                );
                
                $resultado_teste = "✅ Notificação financeira criada com ID: $notif_id";
                $logs_teste[] = "Simulou alteração: Inadimplente → Adimplente";
                $logs_teste[] = "Simulou alteração: Pensionista → Contribuinte";
                break;
                
            case 'teste_observacao':
                $associado_id = intval($_POST['associado_id']);
                $categoria = $_POST['categoria'] ?? 'geral';
                $observacao = $_POST['observacao'] ?? 'Esta é uma observação de teste criada automaticamente.';
                
                $notif_id = $notificacoes->notificarNovaObservacao(
                    $associado_id,
                    $observacao,
                    $categoria,
                    $funcionario_id
                );
                
                $resultado_teste = "✅ Notificação de observação criada com ID: $notif_id";
                $logs_teste[] = "Categoria: $categoria";
                $logs_teste[] = "Texto: " . substr($observacao, 0, 50) . "...";
                break;
                
            case 'teste_cadastro':
                $associado_id = intval($_POST['associado_id']);
                $campos_alterados = [
                    'situacao' => [
                        'antes' => 'Desfiliado',
                        'depois' => 'Filiado'
                    ],
                    'vinculoServidor' => [
                        'antes' => 'Aposentado',
                        'depois' => 'Ativo'
                    ]
                ];
                
                $notif_id = $notificacoes->notificarAlteracaoCadastro(
                    $associado_id,
                    $campos_alterados,
                    $funcionario_id
                );
                
                if ($notif_id) {
                    $resultado_teste = "✅ Notificação de cadastro criada com ID: $notif_id";
                    $logs_teste[] = "Simulou alteração: Desfiliado → Filiado";
                    $logs_teste[] = "Simulou alteração: Aposentado → Ativo";
                } else {
                    $resultado_teste = "ℹ️ Nenhuma notificação criada (campos não interessam ao financeiro)";
                }
                break;
                
            case 'teste_multiplas':
                $total_criadas = 0;
                $associado_id = 16917; // Usar sempre o associado de teste
                
                // Criar 5 notificações de tipos diferentes
                $testes = [
                    [
                        'tipo' => 'financeiro',
                        'dados' => ['situacaoFinanceira' => ['antes' => 'Inadimplente', 'depois' => 'Adimplente']]
                    ],
                    [
                        'tipo' => 'observacao',
                        'texto' => 'Observação de teste automática - Categoria Importante',
                        'categoria' => 'importante'
                    ],
                    [
                        'tipo' => 'cadastro',
                        'dados' => ['situacao' => ['antes' => 'Desfiliado', 'depois' => 'Filiado']]
                    ],
                    [
                        'tipo' => 'observacao',
                        'texto' => 'Observação de teste automática - Categoria Pendência',
                        'categoria' => 'pendencia'
                    ],
                    [
                        'tipo' => 'financeiro',
                        'dados' => ['tipoAssociado' => ['antes' => 'Pensionista', 'depois' => 'Contribuinte']]
                    ]
                ];
                
                foreach ($testes as $i => $teste) {
                    $notif_id = false;
                    
                    switch ($teste['tipo']) {
                        case 'financeiro':
                            $notif_id = $notificacoes->notificarAlteracaoFinanceiro(
                                $associado_id,
                                $teste['dados'],
                                $funcionario_id
                            );
                            break;
                        case 'observacao':
                            $notif_id = $notificacoes->notificarNovaObservacao(
                                $associado_id,
                                $teste['texto'],
                                $teste['categoria'],
                                $funcionario_id
                            );
                            break;
                        case 'cadastro':
                            $notif_id = $notificacoes->notificarAlteracaoCadastro(
                                $associado_id,
                                $teste['dados'],
                                $funcionario_id
                            );
                            break;
                    }
                    
                    if ($notif_id) {
                        $total_criadas++;
                        $logs_teste[] = "Notificação #{$notif_id} - Associado 16917 - Tipo: {$teste['tipo']} - Teste " . ($i + 1);
                    }
                }
                
                $resultado_teste = "✅ $total_criadas notificações criadas em lote para o associado de teste";
                break;
                
            case 'limpar_teste':
                $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
                $stmt = $db->prepare("DELETE FROM Notificacoes WHERE criado_por = ? AND titulo LIKE '%teste%'");
                $stmt->execute([$funcionario_id]);
                $deletadas = $stmt->rowCount();
                
                $resultado_teste = "🗑️ $deletadas notificações de teste removidas";
                break;
        }
        
    } catch (Exception $e) {
        $resultado_teste = "❌ Erro: " . $e->getMessage();
        $logs_teste[] = "Erro técnico: " . $e->getMessage();
    }
}

// Buscar notificações existentes para este usuário
try {
    $notificacoes = new NotificacoesManager();
    $notificacoes_existentes = $notificacoes->buscarNotificacoesFuncionario($funcionario_id, 20);
    $total_nao_lidas = $notificacoes->contarNaoLidas($funcionario_id);
} catch (Exception $e) {
    $notificacoes_existentes = [];
    $total_nao_lidas = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Notificações - ASSEGO</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .test-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .test-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .test-body {
            padding: 2rem;
        }
        
        .test-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 2px solid #f1f5f9;
            border-radius: 12px;
            background: #fafafa;
        }
        
        .test-section h5 {
            color: #334155;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .btn-test {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 0.25rem;
        }
        
        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        .btn-test.btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        
        .btn-test.btn-danger:hover {
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }
        
        .btn-test.btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .btn-test.btn-warning:hover {
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }
        
        .resultado-teste {
            background: #f0fdf4;
            border: 2px solid #10b981;
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
            font-weight: 600;
        }
        
        .resultado-teste.erro {
            background: #fef2f2;
            border-color: #ef4444;
            color: #dc2626;
        }
        
        .logs-container {
            background: #1e293b;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .notification-preview {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin: 0.5rem 0;
            background: white;
        }
        
        .notification-preview.unread {
            border-left: 4px solid #3b82f6;
            background: #eff6ff;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.success {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-badge.danger {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .status-badge.warning {
            background: #fefce8;
            color: #ca8a04;
        }
        
        .status-badge.info {
            background: #eff6ff;
            color: #2563eb;
        }
        
        /* Sistema de notificações (copiado do sistema principal) */
        .notifications-dropdown {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .notifications-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid white;
            animation: notification-pulse 2s infinite;
        }

        @keyframes notification-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .notifications-panel {
            position: absolute;
            top: 120%;
            right: 0;
            width: 380px;
            max-height: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            border: 1px solid #e0e0e0;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .notifications-panel.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .notifications-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
        }

        .notifications-header h6 {
            margin: 0;
            font-weight: 600;
            color: #2c3e50;
        }

        .notifications-list {
            max-height: 350px;
            overflow-y: auto;
            padding: 0;
        }

        .notification-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background: linear-gradient(90deg, rgba(0,123,255,0.05) 0%, transparent 100%);
            border-left: 3px solid #007bff;
        }

        .notification-content {
            display: flex;
            gap: 0.75rem;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
            color: white;
        }

        .notification-body {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .notification-message {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .notification-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .header-icon-btn {
            background: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #4f46e5;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .header-icon-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <!-- Sistema de Notificações (fixo no topo direito) -->
    <div class="notifications-dropdown">
        <button class="header-icon-btn" onclick="toggleNotifications()" id="notificationsBtn">
            <i class="fas fa-bell"></i>
            <span class="notifications-badge" id="notificationsBadge" style="display: none;">0</span>
        </button>
        
        <div class="notifications-panel" id="notificationsPanel">
            <div class="notifications-header">
                <h6><i class="fas fa-bell me-2"></i>Notificações</h6>
                <button class="btn btn-link btn-sm" onclick="marcarTodasLidas()">Marcar todas como lidas</button>
            </div>
            
            <div class="notifications-list" id="notificationsList">
                <div class="text-center p-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="test-container">
        <!-- Header -->
        <div class="test-card">
            <div class="test-header">
                <h1><i class="fas fa-flask me-3"></i>Teste do Sistema de Notificações</h1>
                <p class="mb-0">Teste todas as funcionalidades do sistema de notificações</p>
                <div class="mt-3">
                    <span class="status-badge info">Logado como: <?php echo htmlspecialchars($usuario['nome']); ?></span>
                    <span class="status-badge warning">Notificações não lidas: <?php echo $total_nao_lidas; ?></span>
                    <?php if (!empty($associados)): ?>
                        <span class="status-badge success">Associado de Teste: ID 16917 ✓</span>
                    <?php else: ?>
                        <span class="status-badge danger">Associado 16917 não encontrado!</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status da Conexão -->
        <div class="test-card">
            <div class="test-body">
                <h4><i class="fas fa-database me-2"></i>Status do Sistema</h4>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <?php if (isset($erro_db)): ?>
                                <i class="fas fa-times-circle fa-3x text-danger mb-2"></i>
                                <h6 class="text-danger">Erro na Base de Dados</h6>
                                <small><?php echo htmlspecialchars($erro_db); ?></small>
                            <?php else: ?>
                                <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                                <h6 class="text-success">Associado de Teste OK</h6>
                                <small>ID 16917: <?php echo htmlspecialchars($associado_teste['nome'] ?? 'Nome não encontrado'); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <?php if (class_exists('NotificacoesManager')): ?>
                                <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                                <h6 class="text-success">Classe Carregada</h6>
                                <small>NotificacoesManager disponível</small>
                            <?php else: ?>
                                <i class="fas fa-times-circle fa-3x text-danger mb-2"></i>
                                <h6 class="text-danger">Classe Não Encontrada</h6>
                                <small>Verifique NotificacoesManager.php</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <i class="fas fa-bell fa-3x text-info mb-2"></i>
                            <h6 class="text-info">Notificações Existentes</h6>
                            <small><?php echo count($notificacoes_existentes); ?> total | <?php echo $total_nao_lidas; ?> não lidas</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resultado do Teste -->
        <?php if ($resultado_teste): ?>
        <div class="test-card">
            <div class="test-body">
                <div class="resultado-teste <?php echo strpos($resultado_teste, '❌') !== false ? 'erro' : ''; ?>">
                    <?php echo htmlspecialchars($resultado_teste); ?>
                </div>
                
                <?php if (!empty($logs_teste)): ?>
                <div class="logs-container">
                    <div><strong>📋 Logs do Teste:</strong></div>
                    <?php foreach ($logs_teste as $log): ?>
                    <div>→ <?php echo htmlspecialchars($log); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Testes Unitários -->
        <div class="test-card">
            <div class="test-body">
                <h4><i class="fas fa-vial me-2"></i>Testes de Notificação</h4>
                
                <!-- Teste 1: Alteração Financeira -->
                <div class="test-section">
                    <h5><i class="fas fa-dollar-sign me-2 text-success"></i>Teste 1: Notificação Financeira</h5>
                    <p>Simula alteração nos dados financeiros do associado de teste.</p>
                    
                    <?php if (!empty($associados)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-user me-2"></i>
                        <strong>Associado de Teste:</strong> <?php echo htmlspecialchars($associado_teste['nome']); ?> 
                        (ID: <?php echo $associado_teste['id']; ?>, CPF: <?php echo $associado_teste['cpf']; ?>)
                    </div>
                    
                    <form method="post" class="d-inline">
                        <input type="hidden" name="acao" value="teste_financeiro">
                        <input type="hidden" name="associado_id" value="16917">
                        <button type="submit" class="btn btn-test">
                            <i class="fas fa-play me-2"></i>Testar Notificação Financeira
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Associado de teste (ID: 16917) não encontrado na base de dados.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Teste 2: Nova Observação -->
                <div class="test-section">
                    <h5><i class="fas fa-sticky-note me-2 text-info"></i>Teste 2: Notificação de Observação</h5>
                    <p>Simula criação de uma nova observação para o associado de teste.</p>
                    
                    <?php if (!empty($associados)): ?>
                    <form method="post">
                        <input type="hidden" name="acao" value="teste_observacao">
                        <input type="hidden" name="associado_id" value="16917">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Categoria:</label>
                                <select name="categoria" class="form-select">
                                    <option value="geral">Geral</option>
                                    <option value="financeiro">Financeiro</option>
                                    <option value="importante">Importante</option>
                                    <option value="pendencia">Pendência</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Ação:</label>
                                <div>
                                    <button type="submit" class="btn btn-test">
                                        <i class="fas fa-play me-2"></i>Testar Notificação de Observação
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <textarea name="observacao" class="form-control" rows="2" placeholder="Texto da observação (opcional - será gerado automaticamente se vazio)"></textarea>
                            </div>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Associado de teste não disponível.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Teste 3: Alteração de Cadastro -->
                <div class="test-section">
                    <h5><i class="fas fa-user-edit me-2 text-warning"></i>Teste 3: Notificação de Cadastro</h5>
                    <p>Simula alteração nos dados cadastrais que interessam ao financeiro.</p>
                    
                    <?php if (!empty($associados)): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="acao" value="teste_cadastro">
                        <input type="hidden" name="associado_id" value="16917">
                        <button type="submit" class="btn btn-test btn-warning">
                            <i class="fas fa-play me-2"></i>Testar Notificação de Cadastro
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Associado de teste não disponível.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Teste 4: Múltiplas Notificações -->
                <div class="test-section">
                    <h5><i class="fas fa-layer-group me-2 text-primary"></i>Teste 4: Múltiplas Notificações</h5>
                    <p>Cria várias notificações de tipos diferentes para o associado de teste.</p>
                    
                    <?php if (!empty($associados)): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="acao" value="teste_multiplas">
                        <button type="submit" class="btn btn-test" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                            <i class="fas fa-rocket me-2"></i>Criar 5 Notificações Diferentes para o Associado de Teste
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Associado de teste não disponível.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Limpeza -->
                <div class="test-section" style="background: #fef2f2; border-color: #fecaca;">
                    <h5><i class="fas fa-trash me-2 text-danger"></i>Limpeza dos Testes</h5>
                    <p>Remove todas as notificações de teste criadas por você.</p>
                    
                    <form method="post" class="d-inline" onsubmit="return confirm('Tem certeza que deseja limpar todas as notificações de teste?')">
                        <input type="hidden" name="acao" value="limpar_teste">
                        <button type="submit" class="btn btn-test btn-danger">
                            <i class="fas fa-trash me-2"></i>Limpar Notificações de Teste
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Preview das Notificações -->
        <div class="test-card">
            <div class="test-body">
                <h4><i class="fas fa-eye me-2"></i>Preview das Notificações Existentes</h4>
                
                <?php if (empty($notificacoes_existentes)): ?>
                <div class="text-center p-4">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Nenhuma notificação encontrada</h6>
                    <p class="text-muted">Crie algumas notificações usando os testes acima.</p>
                </div>
                <?php else: ?>
                
                <div class="row">
                    <?php foreach (array_slice($notificacoes_existentes, 0, 6) as $notif): ?>
                    <div class="col-md-6 mb-3">
                        <div class="notification-preview <?php echo $notif['lida'] ? '' : 'unread'; ?>">
                            <div class="d-flex align-items-start">
                                <div class="notification-icon me-3" style="background-color: <?php 
                                    echo $notif['tipo'] === 'ALTERACAO_FINANCEIRO' ? '#28a745' : 
                                        ($notif['tipo'] === 'NOVA_OBSERVACAO' ? '#17a2b8' : '#6f42c1'); 
                                ?>">
                                    <i class="fas fa-<?php 
                                        echo $notif['tipo'] === 'ALTERACAO_FINANCEIRO' ? 'dollar-sign' : 
                                            ($notif['tipo'] === 'NOVA_OBSERVACAO' ? 'sticky-note' : 'user-edit'); 
                                    ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="notification-title"><?php echo htmlspecialchars($notif['titulo']); ?></h6>
                                    <p class="notification-message mb-2"><?php echo htmlspecialchars(substr($notif['mensagem'], 0, 80)) . '...'; ?></p>
                                    <div class="notification-meta">
                                        <span><?php echo htmlspecialchars($notif['associado_nome'] ?? 'Sistema'); ?></span>
                                        <span><?php echo htmlspecialchars($notif['data_criacao']); ?></span>
                                    </div>
                                    <div class="mt-2">
                                        <span class="status-badge <?php echo $notif['lida'] ? 'success' : 'danger'; ?>">
                                            <?php echo $notif['lida'] ? 'Lida' : 'Não lida'; ?>
                                        </span>
                                        <span class="status-badge info"><?php echo $notif['prioridade']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($notificacoes_existentes) > 6): ?>
                <div class="text-center">
                    <p class="text-muted">E mais <?php echo count($notificacoes_existentes) - 6; ?> notificações...</p>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </div>

        <!-- Instruções -->
        <div class="test-card">
            <div class="test-body">
                <h4><i class="fas fa-info-circle me-2"></i>Como Testar</h4>
                <div class="row">
                    <div class="col-md-6">
                        <h6>🔔 Sistema de Notificações (Canto Superior Direito)</h6>
                        <ul>
                            <li>Clique no sino para ver as notificações</li>
                            <li>Badge vermelha mostra quantas não lidas</li>
                            <li>Clique numa notificação para marcar como lida</li>
                            <li>Use "Marcar todas como lidas"</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>🧪 Testes Automáticos</h6>
                        <ul>
                            <li>Use os botões de teste acima</li>
                            <li>Cada teste simula um cenário real</li>
                            <li>Verifique o badge de notificações após cada teste</li>
                            <li>Limpe os testes quando terminar</li>
                        </ul>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>💡 Sistema Seguro:</strong> Esta página usa exclusivamente o associado de teste (ID: 16917) para não interferir com dados reais. 
                    Após criar notificações, clique no sino no canto superior direito para ver o sistema funcionando em tempo real!
                </div>
                
                <div class="alert alert-warning mt-2">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>📋 Observação:</strong> Se o associado ID 16917 não existir na base de dados, os testes não funcionarão. 
                    Verifique se este registro existe ou crie um associado de teste com este ID.
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Sistema de Notificações JavaScript -->
    <script>
    // Sistema de Notificações (mesmo código do sistema principal)
    class NotificacoesManager {
        constructor() {
            this.panel = document.getElementById('notificationsPanel');
            this.badge = document.getElementById('notificationsBadge');
            this.list = document.getElementById('notificationsList');
            this.intervaloAtualizacao = 10000; // 10 segundos para teste
            
            this.inicializar();
        }
        
        inicializar() {
            this.buscarNotificacoes();
            
            setInterval(() => {
                this.atualizarContador();
            }, this.intervaloAtualizacao);
            
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.notifications-dropdown')) {
                    this.fecharPanel();
                }
            });
        }
        
        async buscarNotificacoes() {
            try {
                const response = await fetch('../api/notificacoes.php?acao=buscar&limite=15');
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.renderizarNotificacoes(data.data);
                    this.atualizarBadge(data.data.filter(n => !n.lida).length);
                }
            } catch (error) {
                console.error('Erro ao buscar notificações:', error);
                this.list.innerHTML = '<div class="text-center p-3 text-danger">Erro ao carregar notificações</div>';
            }
        }
        
        async atualizarContador() {
            try {
                const response = await fetch('../api/notificacoes.php?acao=contar');
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.atualizarBadge(data.total);
                }
            } catch (error) {
                console.error('Erro ao contar notificações:', error);
            }
        }
        
        renderizarNotificacoes(notificacoes) {
            if (!notificacoes || notificacoes.length === 0) {
                this.list.innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">Nenhuma notificação</p>
                        <small class="text-muted">Use os testes para criar notificações</small>
                    </div>
                `;
                return;
            }
            
            this.list.innerHTML = notificacoes.map(notif => this.criarItemNotificacao(notif)).join('');
        }
        
        criarItemNotificacao(notif) {
            const readClass = notif.lida ? '' : 'unread';
            const timeAgo = this.formatarTempo(notif.data_criacao);
            
            return `
                <div class="notification-item ${readClass}" 
                     onclick="marcarComoLida(${notif.id})" 
                     data-id="${notif.id}">
                    <div class="notification-content">
                        <div class="notification-icon" style="background-color: ${this.getCorTipo(notif.tipo)}">
                            <i class="fas ${this.getIconeTipo(notif.tipo)}"></i>
                        </div>
                        <div class="notification-body">
                            <div class="notification-title">${notif.titulo}</div>
                            <div class="notification-message">${notif.mensagem}</div>
                            <div class="notification-meta">
                                <span>${notif.associado_nome || 'Sistema'}</span>
                                <span>${timeAgo}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        getCorTipo(tipo) {
            const cores = {
                'ALTERACAO_FINANCEIRO': '#28a745',
                'NOVA_OBSERVACAO': '#17a2b8',
                'ALTERACAO_CADASTRO': '#6f42c1'
            };
            return cores[tipo] || '#6c757d';
        }
        
        getIconeTipo(tipo) {
            const icones = {
                'ALTERACAO_FINANCEIRO': 'fa-dollar-sign',
                'NOVA_OBSERVACAO': 'fa-sticky-note',
                'ALTERACAO_CADASTRO': 'fa-user-edit'
            };
            return icones[tipo] || 'fa-bell';
        }
        
        formatarTempo(dataStr) {
            const agora = new Date();
            const data = new Date(dataStr);
            const diff = agora - data;
            const minutos = Math.floor(diff / 60000);
            
            if (minutos < 1) return 'Agora mesmo';
            if (minutos < 60) return `${minutos}min atrás`;
            if (minutos < 1440) return `${Math.floor(minutos/60)}h atrás`;
            return `${Math.floor(minutos/1440)}d atrás`;
        }
        
        atualizarBadge(count) {
            if (count > 0) {
                this.badge.textContent = count > 99 ? '99+' : count;
                this.badge.style.display = 'flex';
            } else {
                this.badge.style.display = 'none';
            }
        }
        
        mostrarPanel() {
            this.panel.classList.add('show');
            this.buscarNotificacoes();
        }
        
        fecharPanel() {
            this.panel.classList.remove('show');
        }
        
        togglePanel() {
            if (this.panel.classList.contains('show')) {
                this.fecharPanel();
            } else {
                this.mostrarPanel();
            }
        }
        
        async marcarComoLida(notificacaoId) {
            try {
                const formData = new FormData();
                formData.append('acao', 'marcar_lida');
                formData.append('notificacao_id', notificacaoId);
                
                const response = await fetch('../api/notificacoes.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    const item = document.querySelector(`[data-id="${notificacaoId}"]`);
                    if (item) {
                        item.classList.remove('unread');
                    }
                    this.atualizarContador();
                }
            } catch (error) {
                console.error('Erro ao marcar como lida:', error);
            }
        }
        
        async marcarTodasLidas() {
            try {
                const formData = new FormData();
                formData.append('acao', 'marcar_todas_lidas');
                
                const response = await fetch('../api/notificacoes.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    this.atualizarBadge(0);
                    this.mostrarToast(`${data.total_marcadas} notificações marcadas como lidas`, 'success');
                }
            } catch (error) {
                console.error('Erro ao marcar todas como lidas:', error);
            }
        }
        
        mostrarToast(mensagem, tipo = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${tipo} position-fixed`;
            toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px; animation: slideIn 0.3s ease;';
            toast.innerHTML = `
                <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                ${mensagem}
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    }

    // Funções globais
    function toggleNotifications() {
        window.notificacoesManager.togglePanel();
    }

    function marcarComoLida(id) {
        window.notificacoesManager.marcarComoLida(id);
    }

    function marcarTodasLidas() {
        window.notificacoesManager.marcarTodasLidas();
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        window.notificacoesManager = new NotificacoesManager();
        console.log('✅ Sistema de notificações de teste inicializado');
        
        // Auto-refresh da página quando há novos resultados (apenas para teste)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_refresh') === '1') {
            setTimeout(() => {
                window.notificacoesManager.buscarNotificacoes();
            }, 2000);
        }
    });

    // CSS para animações dos toasts
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>