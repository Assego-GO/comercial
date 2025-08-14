<?php
/**
 * API para buscar detalhes completos de um associado inadimplente
 * api/financeiro/buscar_detalhes_inadimplente.php
 */

// IMPORTANTE: Desabilitar exibição de erros para produção
error_reporting(E_ALL);
ini_set('display_errors', 0); // NUNCA deixe como 1 em produção
ini_set('log_errors', 1);

// Headers - DEVE vir ANTES de qualquer output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Função para enviar resposta JSON e encerrar
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // Verifica se os arquivos necessários existem antes de incluir
    $requiredFiles = [
        '../../config/config.php',
        '../../config/database.php',
        '../../classes/Database.php',
        '../../classes/Auth.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception("Arquivo necessário não encontrado: $file");
        }
    }
    
    // Includes necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    // Verificar se session já foi iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar autenticação
    $auth = new Auth();
    
    if (!$auth->isLoggedIn()) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Usuário não autenticado'
        ], 401);
    }

    // Verificar permissões (apenas Financeiro ID:5 ou Presidência ID:1)
    $usuarioLogado = $auth->getUser();
    $departamentoId = $usuarioLogado['departamento_id'] ?? null;
    
    if (!in_array($departamentoId, [1, 5])) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Sem permissão para acessar dados financeiros'
        ], 403);
    }

    // Validar parâmetro ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'ID do associado inválido ou não fornecido'
        ], 400);
    }

    $associadoId = intval($_GET['id']);

    // Conectar ao banco
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    } catch (Exception $e) {
        error_log("Erro ao conectar ao banco: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Erro ao conectar ao banco de dados'
        ], 500);
    }

    // Buscar dados principais do associado
    $sql = "
        SELECT 
            a.id,
            a.nome,
            a.nasc,
            a.sexo,
            a.rg,
            a.cpf,
            a.email,
            a.situacao,
            a.escolaridade,
            a.estadoCivil,
            a.telefone,
            a.foto,
            a.indicacao,
            a.pre_cadastro,
            a.data_pre_cadastro,
            a.data_aprovacao,
            
            -- Dados do contrato
            c.dataFiliacao,
            c.dataDesfiliacao,
            
            -- Dados militares
            m.corporacao,
            m.patente,
            m.categoria,
            m.lotacao,
            m.unidade,
            
            -- Dados financeiros
            f.tipoAssociado,
            f.situacaoFinanceira,
            f.vinculoServidor,
            f.localDebito,
            f.agencia,
            f.operacao,
            f.contaCorrente,
            f.observacoes as observacoes_financeiras,
            f.doador,
            
            -- Endereço
            e.cep,
            e.endereco,
            e.bairro,
            e.cidade,
            e.numero,
            e.complemento
            
        FROM Associados a
        LEFT JOIN Contrato c ON a.id = c.associado_id
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        LEFT JOIN Endereco e ON a.id = e.associado_id
        WHERE a.id = :id
    ";

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $associadoId, PDO::PARAM_INT);
        $stmt->execute();
        
        $associado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$associado) {
            sendJsonResponse([
                'status' => 'error',
                'message' => 'Associado não encontrado'
            ], 404);
        }

        // Verificar se é inadimplente
        if ($associado['situacaoFinanceira'] !== 'INADIMPLENTE') {
            // Não é um erro, mas retorna informação
            $associado['aviso'] = 'Este associado não está inadimplente';
        }

    } catch (PDOException $e) {
        error_log("Erro SQL ao buscar associado: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Erro ao buscar dados do associado'
        ], 500);
    }

    // Buscar dependentes
    try {
        $sqlDependentes = "
            SELECT 
                id,
                nome,
                data_nascimento,
                parentesco,
                sexo
            FROM Dependentes 
            WHERE associado_id = :id
            ORDER BY data_nascimento ASC
        ";

        $stmtDep = $db->prepare($sqlDependentes);
        $stmtDep->bindParam(':id', $associadoId, PDO::PARAM_INT);
        $stmtDep->execute();
        
        $associado['dependentes'] = $stmtDep->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar dependentes: " . $e->getMessage());
        $associado['dependentes'] = [];
    }

    // Buscar observações (verificar se a tabela existe)
    try {
        // Verificar se tabela existe
        $checkTable = $db->query("SHOW TABLES LIKE 'Observacoes_Associado'");
        if ($checkTable->rowCount() > 0) {
            $sqlObservacoes = "
                SELECT 
                    o.id,
                    o.observacao,
                    o.categoria,
                    o.prioridade,
                    o.importante,
                    o.data_criacao,
                    o.editado,
                    o.data_edicao,
                    f.nome as criado_por_nome,
                    f.cargo as criado_por_cargo,
                    DATE_FORMAT(o.data_criacao, '%d/%m/%Y às %H:%i') as data_formatada
                FROM Observacoes_Associado o
                LEFT JOIN Funcionarios f ON o.criado_por = f.id
                WHERE o.associado_id = :id 
                AND o.ativo = 1
                ORDER BY o.importante DESC, o.data_criacao DESC
                LIMIT 10
            ";

            $stmtObs = $db->prepare($sqlObservacoes);
            $stmtObs->bindParam(':id', $associadoId, PDO::PARAM_INT);
            $stmtObs->execute();
            
            $observacoes = $stmtObs->fetchAll(PDO::FETCH_ASSOC);

            // Processar tags das observações
            foreach ($observacoes as &$obs) {
                $tags = [];
                if ($obs['importante']) $tags[] = 'Importante';
                if ($obs['categoria']) $tags[] = ucfirst($obs['categoria']);
                if ($obs['prioridade'] == 'urgente' || $obs['prioridade'] == 'alta') {
                    $tags[] = ucfirst($obs['prioridade']);
                }
                $obs['tags'] = implode(',', $tags);
            }

            $associado['observacoes'] = $observacoes;
        } else {
            $associado['observacoes'] = [];
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar observações: " . $e->getMessage());
        $associado['observacoes'] = [];
    }

    // Buscar serviços (verificar se tabela existe)
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'Servicos_Associado'");
        if ($checkTable->rowCount() > 0) {
            $sqlServicos = "
                SELECT 
                    sa.id,
                    sa.servico_id,
                    sa.tipo_associado,
                    sa.valor_aplicado,
                    sa.percentual_aplicado,
                    sa.data_adesao,
                    s.nome as servico_nome,
                    s.descricao as servico_descricao,
                    s.valor_base
                FROM Servicos_Associado sa
                INNER JOIN Servicos s ON sa.servico_id = s.id
                WHERE sa.associado_id = :id
                AND sa.ativo = 1
                ORDER BY sa.data_adesao DESC
            ";

            $stmtServ = $db->prepare($sqlServicos);
            $stmtServ->bindParam(':id', $associadoId, PDO::PARAM_INT);
            $stmtServ->execute();
            
            $associado['servicos'] = $stmtServ->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $associado['servicos'] = [];
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar serviços: " . $e->getMessage());
        $associado['servicos'] = [];
    }

    // Calcular valores de débito (simulado)
    $valorMensalBase = 86.55;
    $valorMensalTotal = $valorMensalBase;
    
    if (!empty($associado['servicos'])) {
        foreach ($associado['servicos'] as $servico) {
            $valorMensalTotal += floatval($servico['valor_aplicado']);
        }
    }

    // Calcular meses em atraso (simulado - ajustar conforme regra de negócio)
    $mesesAtraso = 3; // Valor exemplo
    
    $associado['valor_mensal'] = $valorMensalTotal;
    $associado['meses_atraso'] = $mesesAtraso;
    $associado['valor_total_debito'] = $valorMensalTotal * $mesesAtraso;
    $associado['data_inadimplencia'] = date('Y-m-d', strtotime("-{$mesesAtraso} months"));
    $associado['ultima_contribuicao'] = date('m/Y', strtotime("-{$mesesAtraso} months"));

    // Histórico de cobranças (simulado)
    $associado['historico_cobrancas'] = [];

    // Registrar acesso na auditoria (não crítico)
    try {
        $checkAuditTable = $db->query("SHOW TABLES LIKE 'Auditoria'");
        if ($checkAuditTable->rowCount() > 0) {
            $sqlAudit = "
                INSERT INTO Auditoria (
                    tabela, 
                    acao, 
                    registro_id, 
                    associado_id, 
                    funcionario_id, 
                    ip_origem, 
                    browser_info, 
                    sessao_id,
                    data_hora
                ) VALUES (
                    'Associados',
                    'VISUALIZAR_INADIMPLENTE',
                    :associado_id,
                    :associado_id,
                    :funcionario_id,
                    :ip,
                    :browser,
                    :sessao,
                    NOW()
                )
            ";

            $stmtAudit = $db->prepare($sqlAudit);
            $stmtAudit->execute([
                ':associado_id' => $associadoId,
                ':funcionario_id' => $_SESSION['funcionario_id'] ?? null,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':browser' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':sessao' => session_id()
            ]);
        }
    } catch (Exception $e) {
        // Não falhar se auditoria der erro
        error_log("Aviso: Erro ao registrar auditoria: " . $e->getMessage());
    }

    // Resposta de sucesso
    sendJsonResponse([
        'status' => 'success',
        'data' => $associado
    ]);

} catch (Exception $e) {
    // Log do erro
    error_log("Erro em buscar_detalhes_inadimplente.php: " . $e->getMessage());
    
    // Resposta de erro genérica
    sendJsonResponse([
        'status' => 'error',
        'message' => 'Erro ao processar requisição: ' . $e->getMessage()
    ], 500);
}

// Garantir que nada mais seja enviado
exit;