<?php
/**
 * API Simplificada para processar solicitação de recadastramento
 * api/recadastro/processar_recadastramento.php
 * 
 * Salva apenas no banco sem integração com ZapSign
 */

// Configurações e includes
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';

// Header JSON
header('Content-Type: application/json');

// Log para debug
error_log("=== INICIANDO PROCESSAMENTO DE RECADASTRAMENTO ===");
error_log("POST recebido: " . print_r($_POST, true));

try {
    // Conectar ao banco de dados
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->beginTransaction();
    
    // Validar dados essenciais
    $associadoId = $_POST['associado_id'] ?? null;
    $motivo = $_POST['motivo_recadastramento'] ?? $_POST['especificar_alteracao'] ?? '';
    
    if (!$associadoId) {
        throw new Exception('ID do associado não informado');
    }
    
    if (empty($motivo)) {
        throw new Exception('Motivo do recadastramento não informado');
    }
    
    // Coletar TODOS os dados do formulário
    $dadosCompletos = [
        // Informações da solicitação
        'info_solicitacao' => [
            'associado_id' => $associadoId,
            'tipo_solicitacao' => $_POST['tipo_solicitacao'] ?? 'recadastramento',
            'motivo' => $motivo,
            'data_solicitacao' => date('Y-m-d H:i:s'),
            'ip_origem' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ],
        
        // Dados Pessoais
        'dados_pessoais' => [
            'nome' => $_POST['nome'] ?? '',
            'nasc' => $_POST['nasc'] ?? '',
            'rg' => $_POST['rg'] ?? '',
            'cpf' => $_POST['cpf'] ?? '',
            'estadoCivil' => $_POST['estadoCivil'] ?? '',
            'telefone' => $_POST['telefone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'escolaridade' => $_POST['escolaridade'] ?? '',
            'indicacao' => $_POST['indicacao'] ?? '',
            'sexo' => $_POST['sexo'] ?? ''
        ],
        
        // Dados Militares
        'dados_militares' => [
            'corporacao' => $_POST['corporacao'] ?? '',
            'patente' => $_POST['patente'] ?? '',
            'categoria' => $_POST['categoria'] ?? '',
            'lotacao' => $_POST['lotacao'] ?? '',
            'unidade' => $_POST['unidade'] ?? ''
        ],
        
        // Endereço
        'endereco' => [
            'cep' => $_POST['cep'] ?? '',
            'endereco' => $_POST['endereco'] ?? '',
            'numero' => $_POST['numero'] ?? '',
            'complemento' => $_POST['complemento'] ?? '',
            'bairro' => $_POST['bairro'] ?? '',
            'cidade' => $_POST['cidade'] ?? '',
            'estado' => $_POST['estado'] ?? 'GO'
        ],
        
        // Dependentes
        'dependentes' => []
    ];
    
    // Processar cônjuge se existir
    if (!empty($_POST['conjuge_nome'])) {
        $dadosCompletos['dependentes']['conjuge'] = [
            'nome' => $_POST['conjuge_nome'],
            'telefone' => $_POST['conjuge_telefone'] ?? '',
            'parentesco' => 'Cônjuge'
        ];
    }
    
    // Processar filhos/dependentes
    if (isset($_POST['dependente_nome']) && is_array($_POST['dependente_nome'])) {
        $filhos = [];
        foreach ($_POST['dependente_nome'] as $index => $nome) {
            if (!empty($nome)) {
                $filhos[] = [
                    'nome' => $nome,
                    'data_nascimento' => $_POST['dependente_nascimento'][$index] ?? '',
                    'parentesco' => 'Filho(a)',
                    'sexo' => $_POST['dependente_sexo'][$index] ?? ''
                ];
            }
        }
        $dadosCompletos['dependentes']['filhos'] = $filhos;
    }
    
    // Processar dependentes JSON se existir
    if (!empty($_POST['dependentes_json'])) {
        $dependentesJson = json_decode($_POST['dependentes_json'], true);
        if ($dependentesJson && is_array($dependentesJson)) {
            $dadosCompletos['dependentes']['filhos_json'] = $dependentesJson;
        }
    }
    
    // Dados Financeiros
    $dadosCompletos['financeiro'] = [
        'tipoAssociado' => $_POST['tipoAssociado'] ?? '',
        'situacaoFinanceira' => $_POST['situacaoFinanceira'] ?? '',
        'vinculoServidor' => $_POST['vinculoServidor'] ?? '',
        'localDebito' => $_POST['localDebito'] ?? '',
        'agencia' => $_POST['agencia'] ?? '',
        'operacao' => $_POST['operacao'] ?? '',
        'contaCorrente' => $_POST['contaCorrente'] ?? ''
    ];
    
    // Serviços
    $dadosCompletos['servicos'] = [
        'servico_juridico_atual' => $_POST['servico_juridico'] ?? false,
        'solicitar_servico_juridico' => isset($_POST['adicionar_servico_juridico']) && $_POST['adicionar_servico_juridico'] == '1',
        'valor_mensalidade_atual' => $_POST['valor_mensalidade'] ?? null
    ];
    
    // Campos alterados (tracking)
    if (!empty($_POST['campos_alterados'])) {
        $dadosCompletos['tracking'] = [
            'campos_alterados' => json_decode($_POST['campos_alterados'], true),
            'campos_alterados_detalhes' => !empty($_POST['campos_alterados_detalhes']) ? 
                json_decode($_POST['campos_alterados_detalhes'], true) : null,
            'total_alteracoes' => $_POST['total_alteracoes'] ?? 0
        ];
    }
    
    // Dados completos JSON se enviado
    if (!empty($_POST['dados_completos_json'])) {
        $dadosCompletosJson = json_decode($_POST['dados_completos_json'], true);
        if ($dadosCompletosJson) {
            // Mesclar com os dados já coletados
            $dadosCompletos = array_merge_recursive($dadosCompletos, $dadosCompletosJson);
        }
    }
    
    // Adicionar todos os outros campos POST não processados
    $camposIgnorar = [
        'associado_id', 'tipo_solicitacao', 'motivo_recadastramento', 
        'especificar_alteracao', 'campos_alterados', 'campos_alterados_detalhes',
        'total_alteracoes', 'dados_completos_json', 'dependentes_json'
    ];
    
    $outrosCampos = [];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, $camposIgnorar) && !isset($dadosCompletos['dados_pessoais'][$key]) 
            && !isset($dadosCompletos['dados_militares'][$key]) 
            && !isset($dadosCompletos['endereco'][$key])
            && !isset($dadosCompletos['financeiro'][$key])) {
            $outrosCampos[$key] = $value;
        }
    }
    
    if (!empty($outrosCampos)) {
        $dadosCompletos['outros_campos'] = $outrosCampos;
    }
    
    // Log dos dados coletados
    error_log("Dados completos coletados: " . json_encode($dadosCompletos, JSON_PRETTY_PRINT));
    
    // Verificar se já existe solicitação pendente
    $sqlVerifica = "SELECT id, status FROM Solicitacoes_Recadastramento 
                    WHERE associado_id = :associado_id 
                    AND status IN ('PENDENTE', 'AGUARDANDO_ASSINATURA', 'ASSINADO_ASSOCIADO', 
                                   'ASSINADO_PRESIDENCIA', 'EM_PROCESSAMENTO')
                    ORDER BY data_solicitacao DESC 
                    LIMIT 1";
    
    $stmtVerifica = $db->prepare($sqlVerifica);
    $stmtVerifica->execute([':associado_id' => $associadoId]);
    $solicitacaoExistente = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
    
    if ($solicitacaoExistente) {
        throw new Exception('Já existe uma solicitação de recadastramento em andamento. Status: ' . $solicitacaoExistente['status']);
    }
    
    // Inserir solicitação de recadastramento
    $sql = "INSERT INTO Solicitacoes_Recadastramento (
                associado_id,
                status,
                motivo,
                dados_alterados,
                data_solicitacao,
                ip_solicitacao,
                observacoes
            ) VALUES (
                :associado_id,
                'PENDENTE',
                :motivo,
                :dados_alterados,
                NOW(),
                :ip,
                :observacoes
            )";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':associado_id' => $associadoId,
        ':motivo' => $motivo,
        ':dados_alterados' => json_encode($dadosCompletos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':observacoes' => 'Solicitação criada via formulário web de recadastramento'
    ]);
    
    if (!$result) {
        throw new Exception('Erro ao inserir solicitação no banco de dados');
    }
    
    $solicitacaoId = $db->lastInsertId();
    
    // Registrar no histórico
    $sqlHist = "INSERT INTO Historico_Recadastramento (
                    solicitacao_id,
                    status,
                    descricao,
                    data_hora
                ) VALUES (
                    :solicitacao_id,
                    'CRIADO',
                    :descricao,
                    NOW()
                )";
    
    $stmtHist = $db->prepare($sqlHist);
    $stmtHist->execute([
        ':solicitacao_id' => $solicitacaoId,
        ':descricao' => 'Solicitação de recadastramento criada. Aguardando processamento para assinatura.'
    ]);
    
    // Registrar na auditoria
    $sqlAudit = "INSERT INTO Auditoria (
                    tabela,
                    acao,
                    registro_id,
                    associado_id,
                    alteracoes,
                    data_hora,
                    ip_origem,
                    browser_info
                ) VALUES (
                    'Solicitacoes_Recadastramento',
                    'INSERT',
                    :registro_id,
                    :associado_id,
                    :alteracoes,
                    NOW(),
                    :ip,
                    :browser
                )";
    
    $stmtAudit = $db->prepare($sqlAudit);
    $stmtAudit->execute([
        ':registro_id' => $solicitacaoId,
        ':associado_id' => $associadoId,
        ':alteracoes' => json_encode([
            'motivo' => $motivo,
            'total_campos' => count($dadosCompletos),
            'origem' => 'Formulário Web de Recadastramento'
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':browser' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    // Commit da transação
    $db->commit();
    
    // Log de sucesso
    error_log("Solicitação de recadastramento criada com sucesso. ID: $solicitacaoId");
    
    // Resposta de sucesso
    echo json_encode([
        'status' => 'success',
        'message' => 'Solicitação de recadastramento registrada com sucesso!',
        'data' => [
            'solicitacao_id' => $solicitacaoId,
            'status' => 'PENDENTE',
            'mensagem_adicional' => 'Sua solicitação foi recebida e será processada em breve. Você receberá o documento para assinatura eletrônica.'
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($db)) {
        $db->rollBack();
    }
    
    // Log do erro
    error_log("ERRO ao processar recadastramento: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Resposta de erro
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}

// Log final
error_log("=== FIM DO PROCESSAMENTO DE RECADASTRAMENTO ===");
?>