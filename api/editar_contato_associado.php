<?php
/**
 * API para editar contato do associado COM AUDITORIA
 * api/editar_contato_associado.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

// Iniciar sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

// Obter dados do usuário logado
$usuarioLogado = $auth->getUser();
$funcionarioId = $_SESSION['funcionario_id'] ?? $usuarioLogado['id'] ?? null;

// Validar ID do associado
$id = $_POST['id'] ?? null;
if (!$id || $id === 'undefined') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ID não informado ou inválido']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar dados atuais para comparação
    $stmt = $db->prepare("
        SELECT 
            a.nome, a.telefone, a.email,
            e.cep, e.endereco, e.numero, e.complemento, e.bairro, e.cidade
        FROM Associados a
        LEFT JOIN Endereco e ON e.associado_id = a.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $dadosAtuais = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosAtuais) {
        throw new Exception('Associado não encontrado');
    }
    
    // Preparar dados novos
    $dadosNovos = [
        'telefone' => $_POST['telefone'] ?? null,
        'email' => $_POST['email'] ?? null,
        'cep' => $_POST['cep'] ?? null,
        'endereco' => $_POST['endereco'] ?? null,
        'numero' => $_POST['numero'] ?? null,
        'complemento' => $_POST['complemento'] ?? null,
        'bairro' => $_POST['bairro'] ?? null,
        'cidade' => $_POST['cidade'] ?? null
    ];
    
    // Verificar o que mudou
    $alteracoes = [];
    $houveAlteracao = false;
    
    // Comparar telefone e email
    if (($dadosAtuais['telefone'] ?? '') !== ($dadosNovos['telefone'] ?? '')) {
        $alteracoes['telefone'] = [
            'anterior' => $dadosAtuais['telefone'],
            'novo' => $dadosNovos['telefone']
        ];
        $houveAlteracao = true;
    }
    
    if (($dadosAtuais['email'] ?? '') !== ($dadosNovos['email'] ?? '')) {
        $alteracoes['email'] = [
            'anterior' => $dadosAtuais['email'],
            'novo' => $dadosNovos['email']
        ];
        $houveAlteracao = true;
    }
    
    // Comparar endereço
    $camposEndereco = ['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade'];
    foreach ($camposEndereco as $campo) {
        if (($dadosAtuais[$campo] ?? '') !== ($dadosNovos[$campo] ?? '')) {
            $alteracoes[$campo] = [
                'anterior' => $dadosAtuais[$campo],
                'novo' => $dadosNovos[$campo]
            ];
            $houveAlteracao = true;
        }
    }
    
    // Se não houve alteração, retornar sucesso sem fazer nada
    if (!$houveAlteracao) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'Nenhuma alteração detectada',
            'alteracoes' => 0
        ]);
        exit;
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
        // Atualizar dados do associado
        $stmt = $db->prepare("
            UPDATE Associados 
            SET telefone = ?, email = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $dadosNovos['telefone'],
            $dadosNovos['email'],
            $id
        ]);
        
        // Verificar se existe endereço
        $stmt = $db->prepare("SELECT id FROM Endereco WHERE associado_id = ?");
        $stmt->execute([$id]);
        $enderecoExiste = $stmt->fetch();
        
        if ($enderecoExiste) {
            // Atualizar endereço existente
            $stmt = $db->prepare("
                UPDATE Endereco 
                SET cep = ?, endereco = ?, numero = ?, 
                    complemento = ?, bairro = ?, cidade = ?
                WHERE associado_id = ?
            ");
            $stmt->execute([
                $dadosNovos['cep'],
                $dadosNovos['endereco'],
                $dadosNovos['numero'],
                $dadosNovos['complemento'],
                $dadosNovos['bairro'],
                $dadosNovos['cidade'],
                $id
            ]);
        } else {
            // Criar novo endereço se houver dados
            if ($dadosNovos['cep'] || $dadosNovos['endereco'] || $dadosNovos['bairro'] || $dadosNovos['cidade']) {
                $stmt = $db->prepare("
                    INSERT INTO Endereco (associado_id, cep, endereco, numero, complemento, bairro, cidade)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id,
                    $dadosNovos['cep'],
                    $dadosNovos['endereco'],
                    $dadosNovos['numero'],
                    $dadosNovos['complemento'],
                    $dadosNovos['bairro'],
                    $dadosNovos['cidade']
                ]);
            }
        }
        
        // REGISTRAR NA AUDITORIA
        $stmt = $db->prepare("
            INSERT INTO Auditoria (
                tabela, 
                acao, 
                registro_id, 
                funcionario_id,
                associado_id,
                alteracoes, 
                data_hora, 
                ip_origem,
                browser_info,
                sessao_id
            ) VALUES (
                'Associados', 
                'UPDATE_CONTATO', 
                ?, 
                ?,
                ?,
                ?, 
                NOW(), 
                ?,
                ?,
                ?
            )
        ");
        
        $dadosAuditoria = [
            'tipo_alteracao' => 'EDICAO_CONTATO',
            'usuario' => [
                'id' => $funcionarioId,
                'nome' => $usuarioLogado['nome'] ?? 'Sistema',
                'email' => $usuarioLogado['email'] ?? null
            ],
            'associado' => [
                'id' => $id,
                'nome' => $dadosAtuais['nome']
            ],
            'campos_alterados' => array_keys($alteracoes),
            'alteracoes_detalhadas' => $alteracoes,
            'total_alteracoes' => count($alteracoes),
            'origem' => 'MODAL_EDICAO_CONTATO'
        ];
        
        $stmt->execute([
            $id,                                              // registro_id
            $funcionarioId,                                   // funcionario_id
            $id,                                              // associado_id
            json_encode($dadosAuditoria, JSON_UNESCAPED_UNICODE), // alteracoes
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',          // ip_origem
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',        // browser_info
            session_id()                                      // sessao_id
        ]);
        
        // Registrar também na tabela Auditoria_Detalhes para cada campo alterado
        $stmtDetalhe = $db->prepare("
            INSERT INTO Auditoria_Detalhes (auditoria_id, campo, valor_anterior, valor_novo)
            VALUES (?, ?, ?, ?)
        ");
        
        $auditoriaId = $db->lastInsertId();
        
        foreach ($alteracoes as $campo => $valores) {
            $stmtDetalhe->execute([
                $auditoriaId,
                $campo,
                $valores['anterior'],
                $valores['novo']
            ]);
        }
        
        // Confirmar transação
        $db->commit();
        
        // Log de sucesso
        error_log("✓ Contato do associado ID $id atualizado por usuário ID $funcionarioId");
        error_log("  Campos alterados: " . implode(', ', array_keys($alteracoes)));
        
        // Resposta de sucesso
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Informações de contato atualizadas com sucesso!',
            'data' => [
                'associado_id' => $id,
                'alteracoes' => count($alteracoes),
                'campos_alterados' => array_keys($alteracoes),
                'atualizado_por' => [
                    'id' => $funcionarioId,
                    'nome' => $usuarioLogado['nome'] ?? 'Sistema'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("✗ Erro ao editar contato: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao atualizar informações: ' . $e->getMessage()
    ]);
}
?>