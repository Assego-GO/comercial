<?php
/**
 * API para buscar associado por RG, CPF, Nome ou ID - Sistema ASSEGO
 * api/associados/buscar_por_rg.php
 * VERSÃO ATUALIZADA - Com suporte a múltiplos RGs de diferentes corporações
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

// Função para log de debug
function debug_log($message) {
    error_log("[DEBUG BUSCAR_RG] " . $message);
}

// Função para detectar se é CPF ou RG
function detectarTipoDocumento($documento) {
    // Remove caracteres não numéricos
    $documentoLimpo = preg_replace('/\D/', '', $documento);
    // Se tem 11 dígitos, provavelmente é CPF
    if (strlen($documentoLimpo) == 11) {
        return 'CPF';
    }
    // Caso contrário, considera como RG
    return 'RG';
}

// Função para formatar CPF para busca
function formatarCPFParaBusca($cpf) {
    $cpfLimpo = preg_replace('/\D/', '', $cpf);
    if (strlen($cpfLimpo) == 11) {
        return $cpfLimpo;
    }
    return $cpf;
}

try {
    debug_log("Iniciando busca de associado");
    
    // Inclui arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';

    // Pega parâmetros de busca
    $rg = $_GET['rg'] ?? $_GET['documento'] ?? '';
    $nome = $_GET['nome'] ?? '';
    $id = $_GET['id'] ?? '';
    $cpf = $_GET['cpf'] ?? '';
    $corporacao = $_GET['corporacao'] ?? ''; // Novo parâmetro para filtrar por corporação

    // Se não especificou um tipo, tenta detectar automaticamente
    if (!empty($rg) && empty($cpf) && empty($nome) && empty($id)) {
        $tipoDetectado = detectarTipoDocumento($rg);
        if ($tipoDetectado === 'CPF') {
            $cpf = $rg;
            $rg = '';
        }
    }

    if (empty($rg) && empty($nome) && empty($id) && empty($cpf)) {
        throw new Exception('É necessário informar RG, CPF, nome ou ID para busca');
    }

    debug_log("Parâmetros recebidos - RG: $rg, Nome: $nome, ID: $id, CPF: $cpf, Corporação: $corporacao");

    // Conecta ao banco de dados
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    if (!$db) {
        throw new Exception('Falha na conexão com banco de dados');
    }

    // Constrói a query base com todos os dados necessários
    $sql = "
        SELECT 
            a.id,
            a.nome,
            a.rg,
            a.cpf,
            a.email,
            a.telefone,
            a.nasc as data_nascimento,
            a.sexo,
            a.escolaridade,
            a.estadoCivil as estado_civil,
            a.situacao,
            a.pre_cadastro,
            a.observacao_aprovacao,
            a.foto,
            m.corporacao,
            m.patente,
            m.categoria,
            m.lotacao,
            m.unidade,
            e.cep,
            e.endereco,
            e.bairro,
            e.cidade,
            e.numero,
            e.complemento,
            f.tipoAssociado as tipo_associado,
            f.situacaoFinanceira as situacao_financeira,
            f.vinculoServidor as vinculo_servidor,
            f.localDebito as local_debito,
            f.agencia,
            f.operacao,
            f.contaCorrente as conta_corrente,
            f.id_neoconsig,
            f.doador,
            c.dataFiliacao as data_filiacao,
            c.dataDesfiliacao as data_desfiliacao
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Endereco e ON a.id = e.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Adiciona condições de busca
    if (!empty($id)) {
        $sql .= " AND a.id = :id";
        $params[':id'] = $id;
    } elseif (!empty($cpf)) {
        // Busca por CPF (remove formatação)
        $cpfParaBusca = formatarCPFParaBusca($cpf);
        $sql .= " AND REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = :cpf";
        $params[':cpf'] = $cpfParaBusca;
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
    
    // Ordena por nome e corporação para facilitar a seleção
    $sql .= " ORDER BY a.nome ASC, m.corporacao ASC LIMIT 50";

    // Log da query para debug
    debug_log("Query SQL: " . $sql);
    debug_log("Parâmetros: " . json_encode($params));

    // Executa a query
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($resultados)) {
        $tipoMsg = !empty($cpf) ? 'CPF' : (!empty($rg) ? 'RG' : (!empty($nome) ? 'nome' : 'critério'));
        $valorMsg = !empty($cpf) ? $cpf : (!empty($rg) ? $rg : (!empty($nome) ? $nome : 'informado'));
        throw new Exception("Nenhum associado encontrado com o $tipoMsg: $valorMsg");
    }

    debug_log("Query executada. Resultados encontrados: " . count($resultados));

    // ========================================
    // LÓGICA DE MÚLTIPLOS RESULTADOS
    // ========================================
    
    // IMPORTANTE: Verifica se há múltiplos resultados com o mesmo RG
    if (!empty($rg) && count($resultados) > 1 && empty($id)) {
        // Retorna lista para seleção
        debug_log("Múltiplos resultados encontrados para RG: $rg");
        
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
                    'situacao' => $assoc['situacao'] ?? 'Não informada',
                    'situacao_financeira' => $assoc['situacao_financeira'] ?? 'Não informada',
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
        debug_log("Múltiplos resultados encontrados para nome: $nome");
        
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
                    'situacao' => $assoc['situacao'] ?? 'Não informada',
                    'situacao_financeira' => $assoc['situacao_financeira'] ?? 'Não informada',
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

    // ========================================
    // RESULTADO ÚNICO - FORMATO COMPLETO
    // ========================================
    
    // Pega o primeiro resultado (ou único)
    $associado = $resultados[0];

    debug_log("Processando dados do associado ID: " . $associado['id']);

    // ========================================
    // BUSCAR SERVIÇOS ATIVOS (se disponível)
    // ========================================
    
    $valorMensalidadeReal = 0;
    $servicosDetalhes = [];
    
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
        
        debug_log("Serviços ativos encontrados: " . count($servicosAtivos));
        
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
        
    } catch (Exception $e) {
        debug_log("Erro ao buscar serviços (tabela pode não existir): " . $e->getMessage());
        
        // Se não conseguir buscar serviços, usa valor padrão baseado no tipo
        if (!empty($associado['tipo_associado'])) {
            switch(strtolower($associado['tipo_associado'])) {
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
                    'valor' => $valorMensalidadeReal,
                    'percentual' => 100,
                    'data_adesao' => date('Y-m-d')
                ];
            }
        }
    }

    // ========================================
    // ESTRUTURAÇÃO DOS DADOS
    // ========================================
    
    // Formatar dados para retorno - ESTRUTURA IGUAL AO FINANCEIRO
    $dadosFormatados = [
        'dados_pessoais' => [
            'id' => $associado['id'],
            'nome' => $associado['nome'] ?? '',
            'data_nascimento' => $associado['data_nascimento'] ?? '',
            'sexo' => $associado['sexo'] ?? '',
            'rg' => $associado['rg'] ?? '',
            'cpf' => $associado['cpf'] ?? '',
            'email' => $associado['email'] ?? '',
            'telefone' => $associado['telefone'] ?? '',
            'escolaridade' => $associado['escolaridade'] ?? '',
            'estado_civil' => $associado['estado_civil'] ?? '',
            'situacao' => $associado['situacao'] ?? '',
            'foto' => $associado['foto'] ?? null
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
            'cep' => $associado['cep'] ?? '',
            'endereco' => $associado['endereco'] ?? '',
            'bairro' => $associado['bairro'] ?? '',
            'cidade' => $associado['cidade'] ?? '',
            'numero' => $associado['numero'] ?? '',
            'complemento' => $associado['complemento'] ?? ''
        ],
        'dados_financeiros' => [
            'tipo_associado' => $associado['tipo_associado'] ?? '',
            'situacao_financeira' => $associado['situacao_financeira'] ?? 'Adimplente',
            'vinculo_servidor' => $associado['vinculo_servidor'] ?? '',
            'local_debito' => $associado['local_debito'] ?? '',
            'agencia' => $associado['agencia'] ?? '',
            'operacao' => $associado['operacao'] ?? '',
            'conta_corrente' => $associado['conta_corrente'] ?? '',
            'id_neoconsig' => $associado['id_neoconsig'] ?? '',
            'doador' => intval($associado['doador'] ?? 0),
            'eh_doador' => ($associado['doador'] ?? 0) == 1 ? 'Sim' : 'Não',
            'valor_mensalidade' => $valorMensalidadeReal,
            'servicos_ativos' => $servicosDetalhes,
            'total_servicos' => count($servicosDetalhes)
        ],
        'contrato' => [
            'data_filiacao' => $associado['data_filiacao'] ?? '',
            'data_desfiliacao' => $associado['data_desfiliacao'] ?? ''
        ],
        'observacoes' => [
            'observacao_aprovacao' => $associado['observacao_aprovacao'] ?? ''
        ],
        'status_cadastro' => ($associado['pre_cadastro'] == 1) ? 'PRE_CADASTRO' : 'DEFINITIVO',
        'tipo_busca' => !empty($cpf) ? 'CPF' : (!empty($rg) ? 'RG' : (!empty($nome) ? 'NOME' : 'ID'))
    ];

    // Calcula idade se possível
    if ($associado['data_nascimento']) {
        try {
            $nascimento = new DateTime($associado['data_nascimento']);
            $hoje = new DateTime();
            $dadosFormatados['dados_pessoais']['idade'] = $nascimento->diff($hoje)->y;
        } catch (Exception $e) {
            // Ignora erro de data
        }
    }

    // ========================================
    // SISTEMA DE ALERTAS
    // ========================================
    
    $alertas = [];
    
    if ($associado['situacao_financeira'] === 'INADIMPLENTE' || $associado['situacao_financeira'] === 'Inadimplente') {
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

    // Adiciona alerta se não houver corporação cadastrada
    if (empty($associado['corporacao'])) {
        $alertas[] = [
            'tipo' => 'warning',
            'mensagem' => 'Corporação militar não cadastrada',
            'sugestao' => 'Atualize o cadastro do associado com a corporação correta'
        ];
    }

    if ($associado['situacao'] === 'DESFILIADO') {
        $alertas[] = [
            'tipo' => 'danger',
            'mensagem' => 'Este associado está DESFILIADO'
        ];
    }

    if (!empty($alertas)) {
        $dadosFormatados['alertas'] = $alertas;
    }

    debug_log("Dados formatados com sucesso para associado ID: " . $associado['id']);

    // ========================================
    // RESPOSTA DE SUCESSO
    // ========================================
    
    $tipoMsg = !empty($cpf) ? 'CPF' : (!empty($rg) ? 'RG' : (!empty($nome) ? 'nome' : 'ID'));
    
    $response = [
        'status' => 'success',
        'message' => "Associado encontrado com sucesso pelo $tipoMsg",
        'data' => $dadosFormatados
    ];

    // Log de sucesso
    debug_log("✓ Associado carregado com sucesso - ID: " . $associado['id'] . 
             " - Corporação: " . ($associado['corporacao'] ?? 'N/A') . 
             " - Valor mensal: R$ " . $valorMensalidadeReal);

} catch (PDOException $e) {
    debug_log("❌ Erro PDO: " . $e->getMessage());
    
    // Mensagem mais amigável para erros de banco
    $mensagemErro = 'Erro ao buscar associado no banco de dados';
    
    // Se for erro de coluna desconhecida, personaliza a mensagem
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $mensagemErro = 'Erro: Campo não encontrado no banco de dados. Verifique a estrutura das tabelas.';
    }
    
    $response = [
        'status' => 'error',
        'message' => $mensagemErro,
        'error_type' => 'database_error',
        'data' => null
    ];
    
    if (isset($_GET['debug'])) {
        $response['debug'] = [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'hint' => 'Verifique se todos os campos existem nas tabelas do banco'
        ];
    }

} catch (Exception $e) {
    debug_log("❌ Erro geral: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'error_type' => 'general_error',
        'data' => null
    ];

} catch (Throwable $e) {
    debug_log("❌ Erro fatal: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => 'Erro fatal no servidor',
        'error_type' => 'fatal_error',
        'data' => null
    ];
    
    if (isset($_GET['debug'])) {
        $response['debug'] = [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ];
    }

} finally {
    // Sempre retorna JSON
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>