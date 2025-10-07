<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug: ID do Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .debug-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .debug-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .debug-header {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .test-button {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            margin: 0.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .test-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        .test-result {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            font-size: 0.9rem;
        }
        
        .test-result.success {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        
        .test-result.error {
            background: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }
        
        .test-result.warning {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
        }
        
        .highlight {
            background: #fef3c7;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <!-- Header -->
        <div class="debug-card">
            <div class="debug-header">
                <h1><i class="fas fa-user-search me-3"></i>Debug: Qual ID Está Sendo Usado?</h1>
                <p class="mb-0">Vamos descobrir exatamente qual ID de usuário a API está recebendo!</p>
            </div>
        </div>

        <!-- Problema Identificado -->
        <div class="debug-card">
            <div class="p-4">
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Problema Identificado</h5>
                    <p class="mb-2">Você está no <strong>departamento correto (2)</strong>, mas as notificações não aparecem.</p>
                    <p class="mb-0">Isso indica que há uma divergência entre:</p>
                    <ul class="mt-2 mb-0">
                        <li>O <strong>ID que a API está recebendo</strong> (possivelmente diferente de 14)</li>
                        <li>O <strong>ID real no banco</strong> (14 - LETICIA FIALHO DE MOURA)</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Debug de ID -->
        <div class="debug-card">
            <div class="p-4">
                <h3><i class="fas fa-search me-2"></i>Descobrir Seu ID Real</h3>
                <p>Vamos criar um endpoint temporário para ver exatamente qual ID a API está usando:</p>
                
                <button class="test-button" onclick="criarDebugEndpoint()">
                    <i class="fas fa-code me-2"></i>Criar Endpoint de Debug
                </button>
                
                <div id="debug-endpoint" class="test-result" style="display: none;"></div>
            </div>
        </div>

        <!-- Teste Direto -->
        <div class="debug-card">
            <div class="p-4">
                <h3><i class="fas fa-play me-2"></i>Teste Direto</h3>
                
                <button class="test-button" onclick="testarIDAtual()">
                    <i class="fas fa-user me-2"></i>Qual Meu ID na API?
                </button>
                
                <div id="result-id" class="test-result" style="display: none;"></div>
                
                <button class="test-button" onclick="buscarNotificacoesPorDepartamento()">
                    <i class="fas fa-building me-2"></i>Buscar Todas do Departamento 2
                </button>
                
                <div id="result-dept" class="test-result" style="display: none;"></div>
            </div>
        </div>

        <!-- Solução Temporária -->
        <div class="debug-card">
            <div class="p-4">
                <h3><i class="fas fa-wrench me-2"></i>Solução Temporária</h3>
                <p>Enquanto não descobrimos o problema, vamos forçar as notificações para aparecer:</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Opção 1: Atribuir Notificações Diretamente</h6>
                        <div class="alert alert-info">
                            <small>SQL para executar no banco:</small>
                            <code>
                                UPDATE Notificacoes 
                                SET funcionario_id = 14 
                                WHERE id IN (10, 11);
                            </code>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Opção 2: Modificar Query Temporariamente</h6>
                        <div class="alert alert-warning">
                            <small>Na API, modificar a condição para:</small>
                            <code>
                                WHERE n.departamento_id = 2 
                                AND n.lida = 0
                            </code>
                        </div>
                    </div>
                </div>
                
                <button class="test-button" onclick="aplicarSolucaoTemporaria()">
                    <i class="fas fa-magic me-2"></i>Aplicar Solução Temporária via API
                </button>
                
                <div id="result-solucao" class="test-result" style="display: none;"></div>
            </div>
        </div>

        <!-- Debug Avançado -->
        <div class="debug-card">
            <div class="p-4">
                <h3><i class="fas fa-microscope me-2"></i>Debug Avançado</h3>
                
                <button class="test-button" onclick="debugCompleto()">
                    <i class="fas fa-bug me-2"></i>Debug Completo de Autenticação
                </button>
                
                <div id="debug-auth" class="test-result" style="display: none;"></div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Criar endpoint de debug
        function criarDebugEndpoint() {
            const resultDiv = document.getElementById('debug-endpoint');
            resultDiv.style.display = 'block';
            
            const codigo = `<?php
/**
 * Debug temporário - debug_id.php
 * Criar este arquivo na pasta api/
 */
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

try {
    $auth = new Auth();
    
    if (!$auth->isLoggedIn()) {
        echo json_encode(['erro' => 'Não logado']);
        exit;
    }
    
    $usuario = $auth->getUser();
    
    // Buscar dados completos do funcionário
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $stmt = $db->prepare("SELECT * FROM Funcionarios WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    $funcionario_completo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Contar notificações por diferentes critérios
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Notificacoes WHERE departamento_id = 2 AND lida = 0 AND ativo = 1");
    $stmt->execute();
    $total_depto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Notificacoes WHERE funcionario_id = ? AND lida = 0 AND ativo = 1");
    $stmt->execute([$usuario['id']]);
    $total_direto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'usuario_da_sessao' => $usuario,
        'funcionario_do_banco' => $funcionario_completo,
        'total_notificacoes_departamento_2' => $total_depto['total'],
        'total_notificacoes_diretas_para_mim' => $total_direto['total'],
        'query_teste' => "SELECT * FROM Funcionarios WHERE id = " . $usuario['id']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['erro' => $e->getMessage()]);
}
?>`;
            
            resultDiv.className = 'test-result';
            resultDiv.textContent = `📁 CRIE ESTE ARQUIVO: api/debug_id.php

${codigo}

📌 Depois de criar o arquivo, clique em "Qual Meu ID na API?" para testá-lo.`;
        }
        
        // Testar ID atual
        async function testarIDAtual() {
            const resultDiv = document.getElementById('result-id');
            resultDiv.style.display = 'block';
            resultDiv.textContent = 'Testando ID...';
            
            try {
                const response = await fetch('../api/debug_id.php');
                const data = await response.json();
                
                if (response.ok && !data.erro) {
                    resultDiv.className = 'test-result success';
                    
                    let resultado = `🎯 INFORMAÇÕES DE DEBUG:

📋 USUÁRIO DA SESSÃO:
• ID: ${data.usuario_da_sessao.id}
• Nome: ${data.usuario_da_sessao.nome}
• Email: ${data.usuario_da_sessao.email || 'N/A'}

👤 FUNCIONÁRIO NO BANCO:
• ID: ${data.funcionario_do_banco?.id || 'NÃO ENCONTRADO'}
• Nome: ${data.funcionario_do_banco?.nome || 'N/A'}
• Departamento ID: ${data.funcionario_do_banco?.departamento_id || 'N/A'}
• Ativo: ${data.funcionario_do_banco?.ativo || 'N/A'}

📊 CONTADORES:
• Notificações do Depto 2: ${data.total_notificacoes_departamento_2}
• Notificações diretas para você: ${data.total_notificacoes_diretas_para_mim}

🔍 ANÁLISE:`;

                    if (data.usuario_da_sessao.id != 14) {
                        resultado += `\n\n❌ PROBLEMA ENCONTRADO!
Seu ID na sessão é ${data.usuario_da_sessao.id}, mas no banco você é o ID 14.
Isso explica por que as notificações não aparecem!`;
                    } else if (data.funcionario_do_banco?.departamento_id != 2) {
                        resultado += `\n\n❌ PROBLEMA: Departamento incorreto!
Você está no departamento ${data.funcionario_do_banco?.departamento_id}, mas deveria ser 2.`;
                    } else if (data.total_notificacoes_departamento_2 == 0) {
                        resultado += `\n\n❌ PROBLEMA: Não há notificações no departamento 2!`;
                    } else {
                        resultado += `\n\n✅ Configuração parece correta. O problema pode estar na query da API.`;
                    }
                    
                    resultDiv.textContent = resultado;
                } else {
                    resultDiv.className = 'test-result error';
                    resultDiv.textContent = `❌ ERRO: ${data.erro || 'Resposta inválida'}

🔧 SOLUÇÃO: Crie o arquivo debug_id.php primeiro usando o botão "Criar Endpoint de Debug"`;
                }
            } catch (error) {
                resultDiv.className = 'test-result error';
                resultDiv.textContent = `❌ ERRO: ${error.message}

Certifique-se de que o arquivo api/debug_id.php foi criado.`;
            }
        }
        
        // Buscar todas do departamento
        async function buscarNotificacoesPorDepartamento() {
            const resultDiv = document.getElementById('result-dept');
            resultDiv.style.display = 'block';
            resultDiv.textContent = 'Buscando notificações do departamento...';
            
            // Como não temos endpoint específico, vamos simular
            resultDiv.className = 'test-result warning';
            resultDiv.textContent = `📝 QUERY PARA TESTAR NO BANCO:

SELECT n.id, n.titulo, n.tipo, n.lida, n.funcionario_id, n.departamento_id,
       a.nome as associado_nome, f.nome as criado_por_nome
FROM Notificacoes n
LEFT JOIN Associados a ON n.associado_id = a.id
LEFT JOIN Funcionarios f ON n.criado_por = f.id
WHERE n.departamento_id = 2 
AND n.lida = 0 
AND n.ativo = 1
ORDER BY n.data_criacao DESC;

🎯 RESULTADO ESPERADO:
Deveria retornar as notificações IDs 10 e 11.

Se retornar vazio, há problema nos dados.
Se retornar dados, o problema está na condição de JOIN com o funcionário.`;
        }
        
        // Solução temporária
        async function aplicarSolucaoTemporaria() {
            const resultDiv = document.getElementById('result-solucao');
            resultDiv.style.display = 'block';
            
            resultDiv.className = 'test-result warning';
            resultDiv.textContent = `🔧 SOLUÇÕES TEMPORÁRIAS:

OPÇÃO 1 - SQL DIRETO (Execute no banco):
UPDATE Notificacoes SET funcionario_id = 14 WHERE id IN (10, 11);

OPÇÃO 2 - MODIFICAR API (Arquivo: classes/NotificacoesManager.php):
No método buscarNotificacoesFuncionario, linha ~85, substitua:

ANTES:
WHERE (n.funcionario_id = ? OR (n.funcionario_id IS NULL AND n.departamento_id = f2.departamento_id))

DEPOIS:
WHERE (n.funcionario_id = ? OR n.departamento_id = 2)

OPÇÃO 3 - TESTE RÁPIDO:
Modifique temporariamente para buscar todas do departamento:
WHERE n.departamento_id = 2 AND n.lida = 0

⚡ QUAL OPÇÃO PREFERE?
A Opção 1 é mais rápida para testar agora.`;
        }
        
        // Debug completo
        async function debugCompleto() {
            const resultDiv = document.getElementById('debug-auth');
            resultDiv.style.display = 'block';
            resultDiv.textContent = 'Executando debug completo...';
            
            resultDiv.className = 'test-result';
            resultDiv.textContent = `🔍 DEBUG COMPLETO DE AUTENTICAÇÃO

📝 PASSOS PARA RESOLVER:

1️⃣ PRIMEIRO - Crie o arquivo debug_id.php e teste
   → Isso vai revelar qual ID está sendo usado

2️⃣ SEGUNDO - Se o ID estiver errado:
   → Verificar classe Auth.php
   → Verificar sessão PHP
   → Pode ser problema de cache de sessão

3️⃣ TERCEIRO - Se o ID estiver correto:
   → Problema está na query da API
   → Verificar JOINs na NotificacoesManager.php

4️⃣ QUARTO - Solução temporária:
   → Execute: UPDATE Notificacoes SET funcionario_id = 14 WHERE id IN (10, 11);

🎯 SUSPEITA PRINCIPAL:
Baseado nos sintomas, acredito que o problema seja:
- A sessão está usando um ID diferente de 14
- OU a query de JOIN está falhando

Execute o debug_id.php primeiro para confirmar!`;
        }
    </script>
</body>
</html>