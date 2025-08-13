<?php
/**
 * API para buscar situação financeira individual de associado - Sistema ASSEGO
 * api/associados/buscar_situacao_financeira.php
 * VERSÃO CORRIGIDA COM VALORES REAIS DOS SERVIÇOS
 */

// Headers para CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Inclui arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Verifica permissões
    $usuarioLogado = $auth->getUser();
    $temPermissao = false;
    
    if (isset($usuarioLogado['departamento_id'])) {
        $deptId = $usuarioLogado['departamento_id'];
        // Apenas financeiro (ID: 5) ou presidência (ID: 1)
        if ($deptId == 5 || $deptId == 1) {
            $temPermissao = true;
        }
    }
    
    if (!$temPermissao) {
        throw new Exception('Acesso negado. Apenas funcionários do setor financeiro e presidência podem acessar dados financeiros.');
    }

    // Pega parâmetros de busca
    $rg = $_GET['rg'] ?? '';
    $nome = $_GET['nome'] ?? '';
    $id = $_GET['id'] ?? '';

    if (empty($rg) && empty($nome) && empty($id)) {
        throw new Exception('É necessário informar RG, nome ou ID para busca');
    }

    // Conecta ao banco de dados
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Constrói a query base
    $sql = "
        SELECT 
            a.id,
            a.nome,
            a.rg,
            a.cpf,
            a.email,
            a.telefone,
            a.nasc,
            a.situacao as situacao_cadastro,
            a.estadoCivil,
            a.escolaridade,
            f.tipoAssociado,
            f.situacaoFinanceira,
            f.vinculoServidor,
            f.localDebito,
            f.agencia,
            f.operacao,
            f.contaCorrente,
            f.observacoes,
            f.doador
        FROM Associados a
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Adiciona condições de busca
    if (!empty($id)) {
        $sql .= " AND a.id = :id";
        $params[':id'] = $id;
    } elseif (!empty($rg)) {
        $sql .= " AND a.rg = :rg";
        $params[':rg'] = $rg;
    } elseif (!empty($nome)) {
        $sql .= " AND a.nome LIKE :nome";
        $params[':nome'] = '%' . $nome . '%';
    }
    
    // Ordena por nome
    $sql .= " ORDER BY a.nome ASC LIMIT 10";

    // Log da query para debug
    error_log("Query SQL situação financeira: " . $sql);
    error_log("Parâmetros: " . json_encode($params));

    // Executa a query
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($resultados)) {
        throw new Exception('Associado não encontrado');
    }

    // Se busca por RG ou ID, retorna apenas um resultado
    if (!empty($rg) || !empty($id)) {
        $associado = $resultados[0];
    } else {
        // Se busca por nome e há múltiplos resultados, retorna lista para escolha
        if (count($resultados) > 1) {
            $response = [
                'status' => 'multiple_results',
                'message' => 'Múltiplos associados encontrados. Selecione um:',
                'data' => array_map(function($assoc) {
                    return [
                        'id' => $assoc['id'],
                        'nome' => $assoc['nome'],
                        'rg' => $assoc['rg'],
                        'situacao_financeira' => $assoc['situacaoFinanceira']
                    ];
                }, $resultados)
            ];
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        $associado = $resultados[0];
    }

    // ========================================
    // NOVA LÓGICA: BUSCAR SERVIÇOS REAIS
    // ========================================
    
    $valorMensalidadeReal = 0;
    $servicosDetalhes = [];
    $tipoAssociadoServico = null;
    
    // Buscar serviços ativos do associado (igual ao dashboard)
    $stmtServicos = $db->prepare("
        SELECT 
            sa.id,
            sa.servico_id,
            sa.ativo,
            sa.data_adesao,
            sa.valor_aplicado,
            sa.percentual_aplicado,
            sa.observacao,
            s.nome as servico_nome,
            s.descricao as servico_descricao,
            s.valor_base
        FROM Servicos_Associado sa
        INNER JOIN Servicos s ON sa.servico_id = s.id
        WHERE sa.associado_id = ? AND sa.ativo = 1
        ORDER BY s.obrigatorio DESC, s.nome ASC
    ");
    
    $stmtServicos->execute([$associado['id']]);
    $servicosAtivos = $stmtServicos->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Serviços ativos encontrados para associado {$associado['id']}: " . count($servicosAtivos));
    
    // Calcula valor total mensal REAL
    foreach ($servicosAtivos as $servico) {
        $valorMensalidadeReal += floatval($servico['valor_aplicado']);
        
        // Adiciona detalhes dos serviços
        if ($servico['servico_id'] == 1) {
            $servicosDetalhes['social'] = [
                'ativo' => true,
                'valor' => floatval($servico['valor_aplicado']),
                'percentual' => floatval($servico['percentual_aplicado']),
                'data_adesao' => $servico['data_adesao']
            ];
        } elseif ($servico['servico_id'] == 2) {
            $servicosDetalhes['juridico'] = [
                'ativo' => true,
                'valor' => floatval($servico['valor_aplicado']),
                'percentual' => floatval($servico['percentual_aplicado']),
                'data_adesao' => $servico['data_adesao']
            ];
        }
    }
    
    error_log("Valor mensal REAL calculado: R$ " . $valorMensalidadeReal);
    
    // Busca o tipo de associado baseado nos serviços (igual ao dashboard)
    if (!empty($servicosAtivos)) {
        // Tenta buscar pela auditoria primeiro
        $stmtAudit = $db->prepare("
            SELECT alteracoes 
            FROM Auditoria 
            WHERE tabela = 'Servicos_Associado' 
            AND registro_id = ? 
            AND alteracoes LIKE '%tipo_associado_servico%'
            ORDER BY data_hora DESC 
            LIMIT 1
        ");
        $stmtAudit->execute([$associado['id']]);
        $auditoria = $stmtAudit->fetch();
        
        if ($auditoria && $auditoria['alteracoes']) {
            $alteracoes = json_decode($auditoria['alteracoes'], true);
            if (isset($alteracoes['tipo_associado_servico'])) {
                $tipoAssociadoServico = $alteracoes['tipo_associado_servico'];
            }
        }
        
        // Se não encontrou, tenta inferir pelas regras
        if (!$tipoAssociadoServico && !empty($servicosAtivos)) {
            $primeiroServico = $servicosAtivos[0];
            
            $stmtRegras = $db->prepare("
                SELECT DISTINCT rc.tipo_associado
                FROM Regras_Contribuicao rc
                WHERE rc.servico_id = ? 
                AND ABS(rc.percentual_valor - ?) < 0.01
                LIMIT 1
            ");
            
            $stmtRegras->execute([
                $primeiroServico['servico_id'], 
                $primeiroServico['percentual_aplicado']
            ]);
            
            $tipoAssociadoServico = $stmtRegras->fetchColumn() ?: 'Contribuinte';
        }
    }
    
    // ========================================
    // FIM DA NOVA LÓGICA
    // ========================================

    // Busca dados financeiros adicionais
    $dadosFinanceirosExtras = [];
    
    // Busca histórico de pagamentos (se existir tabela de pagamentos)
    try {
        $sqlPagamentos = "
            SELECT 
                COUNT(*) as total_pagamentos,
                MAX(data_pagamento) as ultimo_pagamento,
                SUM(CASE WHEN status_pagamento = 'PAGO' THEN valor_pagamento ELSE 0 END) as total_pago,
                SUM(CASE WHEN status_pagamento = 'PENDENTE' THEN valor_pagamento ELSE 0 END) as total_pendente
            FROM Pagamentos 
            WHERE associado_id = :associado_id
        ";
        
        $stmtPag = $db->prepare($sqlPagamentos);
        $stmtPag->bindValue(':associado_id', $associado['id'], PDO::PARAM_INT);
        $stmtPag->execute();
        $pagamentos = $stmtPag->fetch(PDO::FETCH_ASSOC);
        
        if ($pagamentos) {
            $dadosFinanceirosExtras['pagamentos'] = $pagamentos;
        }
    } catch (PDOException $e) {
        // Tabela de pagamentos pode não existir ainda
        error_log("Tabela de pagamentos não encontrada: " . $e->getMessage());
    }

    // Estrutura os dados para resposta
    $dadosEstruturados = [
        'dados_pessoais' => [
            'id' => $associado['id'],
            'nome' => $associado['nome'],
            'rg' => $associado['rg'],
            'cpf' => $associado['cpf'],
            'email' => $associado['email'],
            'telefone' => $associado['telefone'],
            'data_nascimento' => $associado['nasc'],
            'estado_civil' => $associado['estadoCivil'],
            'escolaridade' => $associado['escolaridade']
        ],
        'situacao_financeira' => [
            'situacao' => $associado['situacaoFinanceira'] ?? 'Adimplente',
            'tipo_associado' => $tipoAssociadoServico ?: $associado['tipoAssociado'],
            'vinculo_servidor' => $associado['vinculoServidor'],
            'local_debito' => $associado['localDebito'],
            'agencia' => $associado['agencia'],
            'operacao' => $associado['operacao'],
            'conta_corrente' => $associado['contaCorrente'],
            'observacoes' => $associado['observacoes'] ?? '',
            'doador' => intval($associado['doador'] ?? 0),
            'eh_doador' => ($associado['doador'] ?? 0) == 1 ? 'Sim' : 'Não',
            'valor_mensalidade' => $valorMensalidadeReal, // USA O VALOR REAL!
            'servicos_ativos' => $servicosDetalhes, // Adiciona detalhes dos serviços
            'ultimo_pagamento' => $dadosFinanceirosExtras['pagamentos']['ultimo_pagamento'] ?? null,
            'total_pago' => $dadosFinanceirosExtras['pagamentos']['total_pago'] ?? 0,
            'total_pendente' => $dadosFinanceirosExtras['pagamentos']['total_pendente'] ?? 0,
            'valor_debito' => 0, // Implementar cálculo se necessário
            'meses_atraso' => 0, // Implementar cálculo se necessário
            'vencimento_proxima' => null // Implementar se necessário
        ],
        'status_cadastro' => $associado['situacao_cadastro'],
        'tem_dados_financeiros' => !empty($associado['tipoAssociado']) || count($servicosAtivos) > 0
    ];

    // Calcula dados adicionais
    if ($associado['nasc']) {
        $nascimento = new DateTime($associado['nasc']);
        $hoje = new DateTime();
        $dadosEstruturados['dados_pessoais']['idade'] = $nascimento->diff($hoje)->y;
    }

    // Adiciona alertas baseado na situação
    $alertas = [];
    if ($associado['situacaoFinanceira'] === 'INADIMPLENTE' || $associado['situacaoFinanceira'] === 'Inadimplente') {
        $alertas[] = [
            'tipo' => 'warning',
            'mensagem' => 'Associado com pendências financeiras'
        ];
    }
    
    if (empty($associado['email'])) {
        $alertas[] = [
            'tipo' => 'info',
            'mensagem' => 'Email não cadastrado'
        ];
    }
    
    if (empty($associado['telefone'])) {
        $alertas[] = [
            'tipo' => 'info',
            'mensagem' => 'Telefone não cadastrado'
        ];
    }

    if (($associado['doador'] ?? 0) == 1) {
        $alertas[] = [
            'tipo' => 'success',
            'mensagem' => 'Este associado é um doador da ASSEGO'
        ];
    }

    if (!empty($associado['observacoes']) && strlen(trim($associado['observacoes'])) > 0) {
        $alertas[] = [
            'tipo' => 'info',
            'mensagem' => 'Possui observações no registro financeiro',
            'observacoes' => $associado['observacoes']
        ];
    }

    if (!empty($alertas)) {
        $dadosEstruturados['alertas'] = $alertas;
    }

    // Monta resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => 'Dados financeiros carregados com sucesso',
        'data' => $dadosEstruturados
    ];

    // Log de sucesso
    error_log("Situação financeira carregada com sucesso para associado ID: " . $associado['id'] . " - Valor: R$ " . $valorMensalidadeReal);

} catch (PDOException $e) {
    // Erro de banco de dados
    error_log("Erro de banco de dados ao buscar situação financeira: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => 'Erro interno do servidor. Tente novamente.',
        'error_type' => 'database_error',
        'data' => null
    ];
    
    http_response_code(500);

} catch (Exception $e) {
    // Outros erros
    error_log("Erro ao buscar situação financeira: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'error_type' => 'general_error',
        'data' => null
    ];
    
    http_response_code(400);

} finally {
    // Sempre retorna JSON
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>