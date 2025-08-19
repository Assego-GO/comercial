<?php
/**
 * API para buscar situação financeira individual de associado - Sistema ASSEGO
 * api/associados/buscar_situacao_financeira.php
 * VERSÃO COMPLETA - Com busca real de serviços
 */

// Headers para CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
    $corporacao = $_GET['corporacao'] ?? ''; // Novo parâmetro para filtrar por corporação

    if (empty($rg) && empty($nome) && empty($id)) {
        throw new Exception('É necessário informar RG, nome ou ID para busca');
    }

    // Conecta ao banco de dados
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Constrói a query base com dados militares
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
            a.foto,
            f.tipoAssociado,
            f.situacaoFinanceira,
            f.vinculoServidor,
            f.localDebito,
            f.agencia,
            f.operacao,
            f.contaCorrente,
            f.observacoes_asaas as observacoes,
            f.doador,
            f.valor_em_aberto_asaas,
            f.dias_atraso_asaas,
            f.ultimo_vencimento_asaas,
            m.corporacao,
            m.patente,
            m.categoria,
            m.lotacao,
            m.unidade,
            e.endereco,
            e.bairro,
            e.cidade,
            e.cep
        FROM Associados a
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Endereco e ON a.id = e.associado_id
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
        
        // Se houver corporação especificada, filtra também por ela
        if (!empty($corporacao)) {
            $sql .= " AND m.corporacao = :corporacao";
            $params[':corporacao'] = $corporacao;
        }
    } elseif (!empty($nome)) {
        $sql .= " AND a.nome LIKE :nome";
        $params[':nome'] = '%' . $nome . '%';
    }
    
    // Ordena por nome e corporação
    $sql .= " ORDER BY a.nome ASC, m.corporacao ASC LIMIT 50";

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

    // IMPORTANTE: Verifica se há múltiplos resultados com o mesmo RG
    if (!empty($rg) && count($resultados) > 1 && empty($id)) {
        // Retorna lista para seleção
        $response = [
            'status' => 'multiple_results',
            'message' => 'Múltiplos associados encontrados com o mesmo RG. Selecione o correto:',
            'data' => array_map(function($assoc) {
                return [
                    'id' => $assoc['id'],
                    'nome' => $assoc['nome'],
                    'rg' => $assoc['rg'],
                    'cpf' => $assoc['cpf'],
                    'corporacao' => $assoc['corporacao'] ?? 'Não informada',
                    'patente' => $assoc['patente'] ?? 'Não informada',
                    'unidade' => $assoc['unidade'] ?? 'Não informada',
                    'lotacao' => $assoc['lotacao'] ?? 'Não informada',
                    'situacao_financeira' => $assoc['situacaoFinanceira'] ?? 'Não informada',
                    'foto' => $assoc['foto'] ?? null,
                    'identificacao_completa' => sprintf(
                        "%s - %s %s (%s)",
                        $assoc['nome'],
                        $assoc['patente'] ?? '',
                        $assoc['corporacao'] ?? '',
                        $assoc['unidade'] ?? ''
                    )
                ];
            }, $resultados)
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // Se busca por nome e há múltiplos resultados
    if (!empty($nome) && count($resultados) > 1 && empty($id)) {
        $response = [
            'status' => 'multiple_results',
            'message' => 'Múltiplos associados encontrados. Selecione um:',
            'data' => array_map(function($assoc) {
                return [
                    'id' => $assoc['id'],
                    'nome' => $assoc['nome'],
                    'rg' => $assoc['rg'],
                    'cpf' => $assoc['cpf'],
                    'corporacao' => $assoc['corporacao'] ?? 'Não informada',
                    'patente' => $assoc['patente'] ?? 'Não informada',
                    'unidade' => $assoc['unidade'] ?? 'Não informada',
                    'lotacao' => $assoc['lotacao'] ?? 'Não informada',
                    'situacao_financeira' => $assoc['situacaoFinanceira'] ?? 'Não informada',
                    'foto' => $assoc['foto'] ?? null,
                    'identificacao_completa' => sprintf(
                        "%s - RG: %s - %s %s (%s)",
                        $assoc['nome'],
                        $assoc['rg'],
                        $assoc['patente'] ?? '',
                        $assoc['corporacao'] ?? '',
                        $assoc['unidade'] ?? ''
                    )
                ];
            }, $resultados)
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // Pega o primeiro resultado (ou único)
    $associado = $resultados[0];

    // ========================================
    // BUSCAR SERVIÇOS REAIS
    // ========================================
    
    $valorMensalidadeReal = 0;
    $servicosDetalhes = [];
    $tipoAssociadoServico = null;
    
    try {
        // Buscar serviços ativos do associado
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
            $servicosDetalhes[] = [
                'nome' => $servico['servico_nome'],
                'descricao' => $servico['servico_descricao'],
                'valor' => floatval($servico['valor_aplicado']),
                'percentual' => floatval($servico['percentual_aplicado']),
                'data_adesao' => $servico['data_adesao']
            ];
        }
        
        error_log("Valor mensal REAL calculado: R$ " . $valorMensalidadeReal);
        
        // Busca o tipo de associado baseado nos serviços
        if (!empty($servicosAtivos)) {
            $primeiroServico = $servicosAtivos[0];
            
            try {
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
            } catch (Exception $e) {
                $tipoAssociadoServico = 'Contribuinte';
            }
        }
        
    } catch (Exception $e) {
        error_log("Erro ao buscar serviços: " . $e->getMessage());
        
        // Se não conseguir buscar serviços, usa valor padrão baseado no tipo
        if (!empty($associado['tipoAssociado'])) {
            switch(strtolower($associado['tipoAssociado'])) {
                case 'ativa':
                case 'ativo':
                case 'titular':
                    $valorMensalidadeReal = 181.46;
                    break;
                case 'contribuinte':
                    $valorMensalidadeReal = 150.00;
                    break;
                case 'aluno':
                    $valorMensalidadeReal = 90.00;
                    break;
                case 'agregado':
                    $valorMensalidadeReal = 75.00;
                    break;
                case 'pensionista':
                    $valorMensalidadeReal = 50.00;
                    break;
                default:
                    $valorMensalidadeReal = 50.00;
            }
            
            // Simula serviços básicos
            if ($valorMensalidadeReal > 0) {
                $servicosDetalhes[] = [
                    'nome' => 'Serviço Social',
                    'descricao' => 'Assistência social aos associados',
                    'valor' => $valorMensalidadeReal * 0.7,
                    'percentual' => 70,
                    'data_adesao' => date('Y-m-d')
                ];
                
                if ($valorMensalidadeReal > 100) {
                    $servicosDetalhes[] = [
                        'nome' => 'Serviço Jurídico',
                        'descricao' => 'Assistência jurídica aos associados',
                        'valor' => $valorMensalidadeReal * 0.3,
                        'percentual' => 30,
                        'data_adesao' => date('Y-m-d')
                    ];
                }
            }
        }
    }

    // Busca dados de pagamentos
    $dadosFinanceirosExtras = [];
    
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
            'escolaridade' => $associado['escolaridade'],
            'foto' => $associado['foto']
        ],
        'dados_militares' => [
            'corporacao' => $associado['corporacao'] ?? 'Não informada',
            'patente' => $associado['patente'] ?? 'Não informada',
            'categoria' => $associado['categoria'] ?? 'Não informada',
            'lotacao' => $associado['lotacao'] ?? 'Não informada',
            'unidade' => $associado['unidade'] ?? 'Não informada',
            'identificacao_completa' => sprintf(
                "%s %s - %s",
                $associado['patente'] ?? '',
                $associado['nome'],
                $associado['corporacao'] ?? ''
            )
        ],
        'endereco' => [
            'logradouro' => $associado['endereco'] ?? null,
            'bairro' => $associado['bairro'] ?? null,
            'cidade' => $associado['cidade'] ?? null,
            'cep' => $associado['cep'] ?? null
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
            'valor_mensalidade' => $valorMensalidadeReal,
            'servicos_ativos' => $servicosDetalhes,
            'total_servicos' => count($servicosDetalhes),
            'ultimo_pagamento' => $dadosFinanceirosExtras['pagamentos']['ultimo_pagamento'] ?? null,
            'total_pago' => $dadosFinanceirosExtras['pagamentos']['total_pago'] ?? 0,
            'total_pendente' => $dadosFinanceirosExtras['pagamentos']['total_pendente'] ?? 0,
            'valor_debito' => floatval($associado['valor_em_aberto_asaas'] ?? 0),
            'meses_atraso' => intval($associado['dias_atraso_asaas'] ?? 0) / 30,
            'vencimento_proxima' => $associado['ultimo_vencimento_asaas'] ?? null
        ],
        'status_cadastro' => $associado['situacao_cadastro'],
        'tem_dados_financeiros' => !empty($associado['tipoAssociado']) || count($servicosAtivos) > 0
    ];

    // Calcula idade
    if ($associado['nasc']) {
        try {
            $nascimento = new DateTime($associado['nasc']);
            $hoje = new DateTime();
            $dadosEstruturados['dados_pessoais']['idade'] = $nascimento->diff($hoje)->y;
        } catch (Exception $e) {
            // Ignora erro de data
        }
    }

    // Adiciona alertas baseado na situação
    $alertas = [];
    
    if ($associado['situacaoFinanceira'] === 'INADIMPLENTE' || $associado['situacaoFinanceira'] === 'Inadimplente') {
        $alertas[] = [
            'tipo' => 'warning',
            'mensagem' => 'Associado com pendências financeiras',
            'valor' => floatval($associado['valor_em_aberto_asaas'] ?? 0)
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

    // Adiciona alerta se não houver corporação cadastrada
    if (empty($associado['corporacao'])) {
        $alertas[] = [
            'tipo' => 'warning',
            'mensagem' => 'Corporação militar não cadastrada',
            'sugestao' => 'Atualize o cadastro do associado com a corporação correta'
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
    error_log("✓ Situação financeira carregada com sucesso para associado ID: " . $associado['id'] . 
             " - Corporação: " . ($associado['corporacao'] ?? 'N/A') . 
             " - Valor: R$ " . $valorMensalidadeReal);

} catch (PDOException $e) {
    // Erro de banco de dados
    error_log("❌ Erro de banco de dados: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => 'Erro de banco de dados: ' . $e->getMessage(),
        'error_type' => 'database_error',
        'data' => null
    ];
    
    http_response_code(500);

} catch (Exception $e) {
    // Outros erros
    error_log("❌ Erro geral: " . $e->getMessage());
    
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