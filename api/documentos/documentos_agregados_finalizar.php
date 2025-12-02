<?php
/**
 * API para finalizar processo de sócio agregado
 * api/documentos/documentos_agregados_finalizar.php
 * 
 * ATUALIZA DUAS TABELAS:
 * 1. Documentos_Agregado -> status_fluxo = 'FINALIZADO'
 * 2. Socios_Agregados -> situacao = 'ativo'
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_clean();

$response = ['status' => 'error', 'message' => 'Erro ao processar requisição'];

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    $funcionarioId = $_SESSION['funcionario_id'] ?? null;
    $funcionarioNome = $_SESSION['funcionario_nome'] ?? $_SESSION['usuario_nome'] ?? 'Sistema';
    
    if (!$funcionarioId) {
        throw new Exception('Funcionário não identificado');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    // Aceitar documento_id ou agregado_id
    $documentoId = isset($input['documento_id']) ? $input['documento_id'] : null;
    $agregadoId = isset($input['agregado_id']) ? intval($input['agregado_id']) : 0;
    $observacao = isset($input['observacao']) ? trim($input['observacao']) : '';

    // Se documento_id for string com prefixo AGR_, extrair o número
    if (is_string($documentoId) && strpos($documentoId, 'AGR_') === 0) {
        $agregadoId = intval(str_replace('AGR_', '', $documentoId));
        $documentoId = null;
    } elseif (is_numeric($documentoId)) {
        $documentoId = intval($documentoId);
    }

    if ($agregadoId <= 0 && $documentoId <= 0) {
        throw new Exception('ID do agregado ou documento inválido');
    }

    // Conectar ao banco
    $dbName = defined('DB_NAME_CADASTRO') ? DB_NAME_CADASTRO : (defined('DB_NAME') ? DB_NAME : 'wwasse_cadastro');
    $dbInstance = Database::getInstance($dbName);
    $db = $dbInstance->getConnection();
    
    // Verificar se tabela Documentos_Agregado existe
    $tabelaDocAgregadoExiste = false;
    try {
        $db->query("SELECT 1 FROM Documentos_Agregado LIMIT 1");
        $tabelaDocAgregadoExiste = true;
    } catch (PDOException $e) {
        $tabelaDocAgregadoExiste = false;
    }

    $db->beginTransaction();

    try {
        $agregado = null;
        $documento = null;

        // CASO 1: Temos o ID do documento (da tabela Documentos_Agregado)
        if ($documentoId > 0 && $tabelaDocAgregadoExiste) {
            $stmt = $db->prepare("
                SELECT da.id as documento_id, da.agregado_id, da.status_fluxo,
                       sa.id, sa.nome, sa.cpf, sa.situacao, sa.socio_titular_nome, sa.socio_titular_cpf
                FROM Documentos_Agregado da
                INNER JOIN Socios_Agregados sa ON da.agregado_id = sa.id
                WHERE da.id = :id
            ");
            $stmt->execute([':id' => $documentoId]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado) {
                $documento = [
                    'id' => $resultado['documento_id'],
                    'agregado_id' => $resultado['agregado_id'],
                    'status_fluxo' => $resultado['status_fluxo']
                ];
                $agregado = [
                    'id' => $resultado['agregado_id'],
                    'nome' => $resultado['nome'],
                    'cpf' => $resultado['cpf'],
                    'situacao' => $resultado['situacao'],
                    'socio_titular_nome' => $resultado['socio_titular_nome'],
                    'socio_titular_cpf' => $resultado['socio_titular_cpf']
                ];
                $agregadoId = $resultado['agregado_id'];
            }
        }

        // CASO 2: Temos apenas o ID do agregado
        if (!$agregado && $agregadoId > 0) {
            // Buscar agregado
            $stmt = $db->prepare("
                SELECT id, nome, cpf, situacao, socio_titular_nome, socio_titular_cpf
                FROM Socios_Agregados 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $agregadoId]);
            $agregado = $stmt->fetch(PDO::FETCH_ASSOC);

            // Buscar documento associado (se existir)
            if ($agregado && $tabelaDocAgregadoExiste) {
                $stmt = $db->prepare("
                    SELECT id, agregado_id, status_fluxo
                    FROM Documentos_Agregado 
                    WHERE agregado_id = :agregado_id
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $stmt->execute([':agregado_id' => $agregadoId]);
                $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        if (!$agregado) {
            throw new Exception('Agregado não encontrado com ID: ' . $agregadoId);
        }

        // Verificar se já está finalizado
        $jaFinalizado = ($agregado['situacao'] === 'ativo');
        if ($documento) {
            $jaFinalizado = $jaFinalizado && ($documento['status_fluxo'] === 'FINALIZADO');
        }

        if ($jaFinalizado) {
            $response = [
                'status' => 'success',
                'message' => 'Agregado já está finalizado e ativo',
                'data' => [
                    'agregado_id' => $agregadoId,
                    'nome' => $agregado['nome'],
                    'situacao' => 'ativo',
                    'status_fluxo' => 'FINALIZADO'
                ]
            ];
            $db->commit();
            ob_end_clean();
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Preparar observação
        $novaObservacao = $observacao ?: 'Processo finalizado pela presidência';
        $observacaoCompleta = "[FINALIZAÇÃO " . date('d/m/Y H:i') . " - {$funcionarioNome}] " . $novaObservacao;

        // =====================================================
        // 1. ATUALIZAR TABELA Documentos_Agregado (se existir)
        // =====================================================
        if ($tabelaDocAgregadoExiste && $documento) {
            // CORREÇÃO: Usar apenas colunas que existem na tabela
            // Colunas existentes: id, agregado_id, tipo_documento, tipo_origem, 
            //                     caminho_arquivo, status_fluxo, departamento_atual, data_upload
            $stmt = $db->prepare("
                UPDATE Documentos_Agregado 
                SET status_fluxo = 'FINALIZADO'
                WHERE id = :id
            ");
            $stmt->execute([':id' => $documento['id']]);
            
            error_log("[DOCUMENTO_AGREGADO] Atualizado para FINALIZADO - Doc ID: {$documento['id']}, Agregado ID: {$agregadoId}");
        }

        // =====================================================
        // 2. ATUALIZAR TABELA Socios_Agregados
        // =====================================================
        // Verificar quais colunas existem
        $colunas = [];
        try {
            $stmt = $db->query("DESCRIBE Socios_Agregados");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $colunas[] = $row['Field'];
            }
        } catch (PDOException $e) {
            $colunas = ['id', 'situacao', 'ativo'];
        }

        // Montar SQL dinamicamente
        $sql = "UPDATE Socios_Agregados SET situacao = 'ativo'";
        $params = [':id' => $agregadoId];

        if (in_array('ativo', $colunas)) {
            $sql .= ", ativo = 1";
        }

        if (in_array('observacoes', $colunas)) {
            $sql .= ", observacoes = CONCAT(IFNULL(observacoes, ''), '\n', :observacao)";
            $params[':observacao'] = $observacaoCompleta;
        }

        if (in_array('data_atualizacao', $colunas)) {
            $sql .= ", data_atualizacao = NOW()";
        }

        if (in_array('data_ativacao', $colunas)) {
            $sql .= ", data_ativacao = NOW()";
        }

        $sql .= " WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        error_log("[SOCIOS_AGREGADOS] Atualizado para ativo - ID: {$agregadoId}, Nome: {$agregado['nome']}");

        // =====================================================
        // 3. REGISTRAR HISTÓRICO DO FLUXO (se tabela existir)
        // =====================================================
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'Historico_Fluxo_Agregado'");
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("
                    INSERT INTO Historico_Fluxo_Agregado 
                    (documento_id, agregado_id, status_anterior, status_novo, funcionario_id, observacao, data_acao)
                    VALUES (:documento_id, :agregado_id, :status_anterior, 'FINALIZADO', :funcionario_id, :observacao, NOW())
                ");
                $stmt->execute([
                    ':documento_id' => $documento ? $documento['id'] : null,
                    ':agregado_id' => $agregadoId,
                    ':status_anterior' => $documento ? $documento['status_fluxo'] : 'ASSINADO',
                    ':funcionario_id' => $funcionarioId,
                    ':observacao' => $novaObservacao
                ]);
            }
        } catch (PDOException $e) {
            // Tabela não existe - ignorar
        }

        // =====================================================
        // 4. REGISTRAR NA AUDITORIA (opcional)
        // =====================================================
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'Auditoria'");
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("
                    INSERT INTO Auditoria (tabela, acao, registro_id, funcionario_id, alteracoes, data_hora)
                    VALUES ('Socios_Agregados', 'FINALIZACAO', :registro_id, :funcionario_id, :alteracoes, NOW())
                ");
                $stmt->execute([
                    ':registro_id' => $agregadoId,
                    ':funcionario_id' => $funcionarioId,
                    ':alteracoes' => json_encode([
                        'documento_id' => $documento ? $documento['id'] : null,
                        'situacao_anterior' => $agregado['situacao'],
                        'situacao_nova' => 'ativo',
                        'status_fluxo_anterior' => $documento ? $documento['status_fluxo'] : null,
                        'status_fluxo_novo' => 'FINALIZADO',
                        'observacao' => $novaObservacao,
                        'finalizado_por' => $funcionarioNome
                    ])
                ]);
            }
        } catch (PDOException $e) {
            // Erro de auditoria - ignorar
        }

        $db->commit();

        // Log de sucesso
        error_log("[AGREGADO] FINALIZAÇÃO COMPLETA - ID: {$agregadoId}, Nome: {$agregado['nome']}, Por: {$funcionarioNome}");

        $response = [
            'status' => 'success',
            'message' => 'Processo do agregado finalizado com sucesso! Agregado ativado.',
            'data' => [
                'agregado_id' => $agregadoId,
                'documento_id' => $documento ? $documento['id'] : null,
                'nome' => $agregado['nome'],
                'cpf' => $agregado['cpf'],
                'titular_nome' => $agregado['socio_titular_nome'] ?? null,
                'titular_cpf' => $agregado['socio_titular_cpf'] ?? null,
                'situacao_anterior' => $agregado['situacao'],
                'situacao_nova' => 'ativo',
                'status_fluxo_anterior' => $documento ? $documento['status_fluxo'] : null,
                'status_fluxo_novo' => 'FINALIZADO',
                'agregado_ativado' => true,
                'finalizado_por' => $funcionarioNome
            ]
        ];

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Erro PDO em documentos_agregados_finalizar: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => 'Erro de banco de dados: ' . $e->getMessage()
    ];
    http_response_code(500);
} catch (Exception $e) {
    error_log("Erro em documentos_agregados_finalizar: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;