<?php
/**
 * Processador de dados CSV ASAAS - Sistema ASSEGO
 * api/financeiro/processar_asaas.php
 * Processa os dados importados do ASAAS e atualiza status de adimplência
 * 
 * VERSÃO COM HISTÓRICO DE PAGAMENTOS + DATA DE VENCIMENTO
 * - PAGANTES: Todas as corporações são processadas
 * - NÃO ENCONTRADOS: Apenas Exército, Agregados e Pensionista são reportados
 * - HISTÓRICO: Registra cada pagamento individualmente na tabela Pagamentos_Associado
 * - MÊS REFERÊNCIA: Usa DATA DE VENCIMENTO para determinar qual mensalidade foi paga
 * 
 * Problemas corrigidos:
 * 1. CPFs com 9-10 dígitos (preenchimento com zeros)
 * 2. Valores zerados (extração melhorada)
 * 3. Escopo híbrido conforme solicitação
 * 4. Histórico completo de pagamentos por mês
 * 5. Mês de referência correto baseado na data de vencimento
 */

// ✅ SOLUÇÃO 1: Controlar output desde o início
ob_start();

// ✅ SOLUÇÃO 2: Headers primeiro
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ✅ SOLUÇÃO 3: Configurar erros para não aparecer na resposta
error_reporting(E_ALL);
ini_set('display_errors', 0); // ← Não mostrar erros na tela
ini_set('log_errors', 1);     // ← Mas continuar logando

// ✅ SOLUÇÃO 4: Função para resposta JSON limpa
function enviarJSON($data) {
    // Limpar qualquer output anterior
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Enviar apenas JSON
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // ✅ SOLUÇÃO 5: Verificar método primeiro
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        enviarJSON([
            'status' => 'error',
            'message' => 'Método não permitido. Use POST.'
        ]);
    }

    // ✅ SOLUÇÃO 6: Configurar sessão antes de incluir arquivos
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar sessão antes de iniciar
        ini_set('session.gc_maxlifetime', 36000);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }

    // Incluir arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    // Debug nos logs (não na resposta)
    error_log("=== DEBUG REQUISIÇÃO ASAAS ===");
    error_log("Método: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST dados: " . print_r($_POST, true));
    
    // Verificar autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        error_log("❌ Usuário não autenticado");
        enviarJSON([
            'status' => 'error',
            'message' => 'Usuário não autenticado. Faça login novamente.'
        ]);
    }

    $usuarioLogado = $auth->getUser();
    error_log("✅ Usuário logado: " . $usuarioLogado['nome']);

    // Verificar permissões
    if (!isset($usuarioLogado['departamento_id']) || !in_array($usuarioLogado['departamento_id'], [1, 5])) {
        enviarJSON([
            'status' => 'error',
            'message' => 'Acesso negado. Apenas Setor Financeiro e Presidência podem importar dados do ASAAS.'
        ]);
    }

    // Verificar dados
    if (!isset($_POST['dados_csv']) || !isset($_POST['action'])) {
        error_log("❌ Dados não recebidos");
        enviarJSON([
            'status' => 'error',
            'message' => 'Dados não recebidos. Esperado: dados_csv e action'
        ]);
    }

    if ($_POST['action'] !== 'processar_asaas') {
        enviarJSON([
            'status' => 'error',
            'message' => 'Ação inválida: ' . $_POST['action']
        ]);
    }

    // Decodificar dados
    $dadosCSV = json_decode($_POST['dados_csv'], true);
    if (!$dadosCSV) {
        enviarJSON([
            'status' => 'error',
            'message' => 'Erro ao decodificar dados do CSV'
        ]);
    }

    // 🔍 DEBUG: Log da estrutura do CSV
    error_log("=== DEBUG CSV RECEBIDO ===");
    error_log("Total de registros: " . count($dadosCSV));
    if (count($dadosCSV) > 0) {
        error_log("Primeira linha (colunas): " . implode(' | ', array_keys($dadosCSV[0])));
        error_log("Primeira linha (dados): " . json_encode($dadosCSV[0], JSON_UNESCAPED_UNICODE));
    }

    // Log de início
    error_log("=== PROCESSAMENTO ASAAS COM HISTÓRICO + VENCIMENTO INICIADO ===");
    error_log("Usuário: " . $usuarioLogado['nome'] . " (ID: " . $usuarioLogado['id'] . ")");
    error_log("Registros: " . count($dadosCSV));
    error_log("Escopo: Todos os associados + Histórico + Data de Vencimento");

    // Processar dados
    $processador = new ProcessadorAsaas();
    $resultado = $processador->processar($dadosCSV, $usuarioLogado['id']);

    // Log de resultado
    error_log("✅ PROCESSAMENTO COM HISTÓRICO + VENCIMENTO CONCLUÍDO:");
    error_log("- Total associados processados: " . $resultado['resumo']['totalProcessados']);
    error_log("- Pagantes (marcados adimplentes): " . $resultado['resumo']['pagantes']);
    error_log("- Pagamentos registrados no histórico: " . ($resultado['resumo']['pagamentosRegistrados'] ?? 0));
    error_log("- Não encontrados (reportados): " . $resultado['resumo']['nao_encontrados']);
    error_log("- Ignorados (não são associados): " . $resultado['resumo']['ignorados']);
    error_log("- Mês de referência: " . ($resultado['mes_referencia'] ?? 'N/A'));

    // ✅ SOLUÇÃO 7: Resposta final limpa
    enviarJSON([
        'status' => 'success',
        'message' => 'Dados processados com sucesso',
        'resultado' => $resultado
    ]);

} catch (Exception $e) {
    error_log("❌ ERRO: " . $e->getMessage());
    error_log("Stack: " . $e->getTraceAsString());
    
    enviarJSON([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $usuarioLogado['id'] ?? 'N/A'
        ]
    ]);
}

/**
 * Processador ASAAS - VERSÃO COM HISTÓRICO + DATA DE VENCIMENTO
 * 
 * LÓGICA MANTIDA + HISTÓRICO + VENCIMENTO:
 * 1. CSV contém apenas quem PAGOU (não mais cobranças pendentes)
 * 2. PAGANTES: Processa TODOS os associados (todas as corporações)
 * 3. NÃO ENCONTRADOS: Reporta apenas Exército, Agregados e Pensionista
 * 4. Quem está no CSV = ADIMPLENTE + REGISTRO NO HISTÓRICO
 * 5. Quem não está = apenas reporta se for das 3 corporações específicas
 * 6. CPFs não encontrados no sistema = ignorados
 * 7. Cada pagamento é registrado individualmente na tabela Pagamentos_Associado
 * 8. MÊS DE REFERÊNCIA determinado pela DATA DE VENCIMENTO (mais preciso)
 */

class ProcessadorAsaas {
    
    private $db;
    private $associadosSistema = []; // Todos os associados
    private $cpfsPagantes = [];
    private $colunasCSV = []; // Mapear colunas automaticamente
    
    public function __construct() {
        try {
            $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        } catch (Exception $e) {
            throw new Exception('Erro ao conectar com banco: ' . $e->getMessage());
        }
    }
    
    /**
     * PROCESSAMENTO PRINCIPAL - VERSÃO COM HISTÓRICO + VENCIMENTO
     */
    public function processar($dadosCSV, $usuarioId) {
        $resultado = [
            'pagantes' => [],           
            'nao_encontrados' => [],    
            'ignorados' => [],
            'pagamentos_registrados' => [], // Nova seção para histórico
            'mes_referencia' => '', // 🆕 Incluir mês de referência no resultado
            'resumo' => [
                'totalProcessados' => 0,
                'pagantes' => 0,
                'nao_encontrados' => 0,
                'ignorados' => 0,
                'atualizadosBanco' => 0,
                'pagamentosRegistrados' => 0, // Novo contador
                'erros' => 0
            ]
        ];

        try {
            // PASSO 0: Detectar estrutura do CSV (com foco em vencimento)
            $this->detectarEstruturaCsv($dadosCSV);

            // PASSO 1: Extrair CPFs únicos do CSV (quem pagou)
            $this->cpfsPagantes = $this->extrairCPFsUnicos($dadosCSV);
            error_log("🔍 CPFs únicos extraídos do CSV: " . count($this->cpfsPagantes));

            // PASSO 2: Buscar TODOS os associados do sistema (todas as corporações)
            $this->associadosSistema = $this->buscarTodosAssociados();
            error_log("🔍 Total de associados no sistema: " . count($this->associadosSistema));

            // 🎯 PASSO 2.5: Determinar mês de referência pela DATA DE VENCIMENTO
            $mesReferencia = $this->determinarMesReferencia($dadosCSV);
            $resultado['mes_referencia'] = $mesReferencia;
            error_log("🎯 Mês de referência (VENCIMENTO): $mesReferencia");

            // DEBUG: Verificar intersecção entre CPFs
            $this->debugInterseccaoCPFs();

            // PASSO 3: Classificar cada associado
            $processados = 0;
            foreach ($this->associadosSistema as $associado) {
                try {
                    $cpf = $this->limparCPF($associado['cpf']);
                    $processados++;
                    
                    $isMatch = in_array($cpf, $this->cpfsPagantes);
                    
                    if ($isMatch) {
                        // ✅ PAGOU - Marcar como adimplente + REGISTRAR NO HISTÓRICO
                        $dadosPagamento = $this->buscarDadosPagamento($cpf, $dadosCSV);
                        
                        // REGISTRAR PAGAMENTO NO HISTÓRICO
                        $pagamentoId = $this->registrarPagamentoHistorico(
                            $associado['id'], 
                            $mesReferencia, 
                            $dadosPagamento, 
                            $usuarioId
                        );
                        
                        if ($pagamentoId) {
                            $resultado['resumo']['pagamentosRegistrados']++;
                        }
                        
                        // Preparar dados do resultado
                        $associado['cpf'] = $cpf;
                        $associado['status'] = 'ADIMPLENTE';
                        $associado['motivo'] = 'Encontrado no arquivo de pagamentos - Vencimento: ' . ($dadosPagamento['data_vencimento'] ?: 'N/A');
                        $associado['dados_pagamento'] = $dadosPagamento;
                        $associado['pagamento_id'] = $pagamentoId;
                        $associado['mes_referencia'] = $mesReferencia;
                        $associado['acao'] = 'Marcado como ADIMPLENTE + Registrado no histórico';
                        
                        $resultado['pagantes'][] = $associado;
                        $resultado['resumo']['pagantes']++;
                        
                        if ($processados <= 5) {
                            error_log("✅ PAGANTE: {$associado['nome']} - Venc: {$dadosPagamento['data_vencimento']} - Valor: R$ {$dadosPagamento['valor']} - ID: $pagamentoId");
                        }
                    } else {
                        // ⚠️ NÃO PAGOU - Verificar se deve ser reportado
                        $corporacao = $associado['corporacao'];
                        
                        // FILTRO: Apenas Exército, Agregados e Pensionista na aba "Não Encontrados"
                        if (in_array($corporacao, ['Exército', 'Agregados', 'Pensionista'])) {
                            $associado['cpf'] = $cpf;
                            $associado['status'] = 'NAO_ENCONTRADO';
                            $associado['motivo'] = 'Não encontrado no arquivo de pagamentos de ' . date('m/Y', strtotime($mesReferencia));
                            $associado['acao'] = 'Apenas reportado (não marcado inadimplente)';
                            
                            $resultado['nao_encontrados'][] = $associado;
                            $resultado['resumo']['nao_encontrados']++;
                        }
                    }
                    
                    $resultado['resumo']['totalProcessados']++;
                    
                } catch (Exception $e) {
                    error_log("❌ Erro ao processar associado {$associado['nome']}: " . $e->getMessage());
                    $resultado['resumo']['erros']++;
                }
            }

            // PASSO 4: Verificar CPFs do CSV que não existem no sistema
            $this->verificarCPFsIgnorados($dadosCSV, $resultado);

            // PASSO 5: Atualizar banco APENAS com os pagantes (mantém lógica original)
            $atualizados = $this->atualizarBanco($resultado, $usuarioId, $mesReferencia);
            $resultado['resumo']['atualizadosBanco'] = $atualizados;

            // PASSO 6: Gerar relatório dos pagamentos registrados no histórico
            $resultado['pagamentos_registrados'] = $this->gerarRelatorioHistorico($mesReferencia);

            // RESUMO FINAL
            error_log("🎯 RESUMO FINAL DO PROCESSAMENTO:");
            error_log("  📊 Total de registros no CSV: " . count($dadosCSV));
            error_log("  📊 CPFs únicos extraídos: " . count($this->cpfsPagantes));
            error_log("  📊 Associados no sistema: " . count($this->associadosSistema));
            error_log("  ✅ Pagantes encontrados: " . $resultado['resumo']['pagantes']);
            error_log("  💾 Pagamentos no histórico: " . $resultado['resumo']['pagamentosRegistrados']);
            error_log("  ⚠️ Não encontrados reportados: " . $resultado['resumo']['nao_encontrados']);
            error_log("  🚫 Ignorados: " . $resultado['resumo']['ignorados']);
            error_log("  💾 Status atualizados: $atualizados");
            error_log("  📅 Mês de referência: $mesReferencia");

            return $resultado;

        } catch (Exception $e) {
            error_log("❌ Erro no processamento ASAAS: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 🎯 DETECTAR ESTRUTURA DO CSV - VERSÃO COM FOCO EM VENCIMENTO
     */
    private function detectarEstruturaCsv($dadosCSV) {
        if (empty($dadosCSV)) {
            throw new Exception('CSV vazio ou inválido');
        }

        $primeiraLinha = $dadosCSV[0];
        $colunas = array_keys($primeiraLinha);
        
        error_log("📊 ESTRUTURA DO CSV DETECTADA:");
        error_log("Colunas encontradas: " . implode(' | ', $colunas));

        // Mapear colunas - AGORA PRIORIZANDO DATA DE VENCIMENTO
        $this->colunasCSV = [
            'cpf' => $this->encontrarColuna($colunas, ['CPF ou CNPJ', 'CPF', 'CNPJ', 'cpf', 'Cpf']),
            'email' => $this->encontrarColuna($colunas, ['Email', 'E-mail', 'email', 'EMAIL']),
            'situacao' => $this->encontrarColuna($colunas, ['Situação', 'Status', 'situacao', 'status']),
            'valor' => $this->encontrarColuna($colunas, ['Valor', 'Valor da cobrança', 'Valor pago', 'valor', 'VALOR']),
            
            // 🎯 PRIORIDADE MÁXIMA: Data de vencimento
            'data_vencimento' => $this->encontrarColuna($colunas, [
                'Data de vencimento', 'Data de Vencimento', 'DATA DE VENCIMENTO',
                'Vencimento', 'VENCIMENTO', 'vencimento',
                'Data vencimento', 'data_vencimento', 'dataVencimento'
            ]),
            
            // Outras datas (secundárias)
            'data_pagamento' => $this->encontrarColuna($colunas, [
                'Data de Pagamento', 'Data do pagamento', 'Data', 'data_pagamento'
            ]),
            
            'nome' => $this->encontrarColuna($colunas, ['Nome', 'Cliente', 'nome', 'NOME'])
        ];

        error_log("📋 MAPEAMENTO DE COLUNAS:");
        foreach ($this->colunasCSV as $campo => $coluna) {
            $status = $coluna ? "✅" : "❌";
            if ($campo === 'data_vencimento') {
                $status = $coluna ? "🎯 ENCONTRADA!" : "⚠️ CRÍTICO - NÃO ENCONTRADA!";
            }
            error_log("  $campo -> " . ($coluna ?: 'N/A') . " $status");
        }

        // Validar colunas essenciais
        if (!$this->colunasCSV['cpf']) {
            throw new Exception('Coluna de CPF não encontrada. Colunas disponíveis: ' . implode(', ', $colunas));
        }
        
        // DEBUG específico para vencimento
        $this->debugDatasVencimento($dadosCSV);
    }

    /**
     * 🎯 DETERMINAR MÊS DE REFERÊNCIA PELA DATA DE VENCIMENTO
     */
    private function determinarMesReferencia($dadosCSV) {
        error_log("🎯 DETERMINANDO MÊS DE REFERÊNCIA PELA DATA DE VENCIMENTO");
        
        // 1. PRIORIDADE: Data de vencimento (indica exatamente qual mensalidade)
        if ($this->colunasCSV['data_vencimento']) {
            $mesReferencia = $this->extrairMesPorVencimento($dadosCSV);
            if ($mesReferencia) {
                error_log("🎯 ✅ Mês definido pela DATA DE VENCIMENTO: $mesReferencia");
                return $mesReferencia;
            }
        }
        
        // 2. FALLBACK: Data de pagamento 
        if ($this->colunasCSV['data_pagamento']) {
            $mesReferencia = $this->extrairMesPorPagamento($dadosCSV);
            if ($mesReferencia) {
                error_log("⚠️ Mês definido pela DATA DE PAGAMENTO (fallback): $mesReferencia");
                return $mesReferencia;
            }
        }
        
        // 3. ÚLTIMO RECURSO: Mês anterior
        $mesAnterior = date('Y-m-01', strtotime('first day of last month'));
        error_log("❌ ATENÇÃO: Usando mês anterior como último recurso: $mesAnterior");
        error_log("❌ ISSO PODE ESTAR INCORRETO! Verifique as colunas de data no CSV.");
        return $mesAnterior;
    }

    /**
     * 🎯 EXTRAIR MÊS PELA DATA DE VENCIMENTO (MÉTODO PRINCIPAL)
     */
    private function extrairMesPorVencimento($dadosCSV) {
        $colunaVencimento = $this->colunasCSV['data_vencimento'];
        $datasVencimento = [];
        
        error_log("🔍 Analisando VENCIMENTOS na coluna: '$colunaVencimento'");
        
        // Analisar amostra das primeiras 10 linhas
        $amostra = array_slice($dadosCSV, 0, 10);
        
        foreach ($amostra as $index => $linha) {
            $dataStr = trim($linha[$colunaVencimento] ?? '');
            
            if (!empty($dataStr)) {
                $timestamp = $this->parsearDataFlexivel($dataStr);
                
                if ($timestamp) {
                    $mesAno = date('Y-m', $timestamp);
                    $datasVencimento[$mesAno] = ($datasVencimento[$mesAno] ?? 0) + 1;
                    
                    // Log das primeiras datas
                    if ($index < 5) {
                        error_log("📅 [$index] Vencimento: '$dataStr' → " . date('d/m/Y', $timestamp) . " (Mês: $mesAno)");
                    }
                } else {
                    if ($index < 3) {
                        error_log("❌ [$index] Vencimento inválido: '$dataStr'");
                    }
                }
            }
        }
        
        if (!empty($datasVencimento)) {
            // Ordenar por frequência
            arsort($datasVencimento);
            
            error_log("📊 ANÁLISE DOS VENCIMENTOS:");
            foreach ($datasVencimento as $mes => $count) {
                $porcentagem = round(($count / count($amostra)) * 100, 1);
                error_log("  🗓️ $mes: $count vencimentos ($porcentagem%)");
            }
            
            // Pegar o mês mais comum
            $mesMaisComum = array_key_first($datasVencimento);
            $mesReferencia = $mesMaisComum . '-01';
            
            // Validar se faz sentido
            if ($this->validarMesReferencia($mesReferencia)) {
                return $mesReferencia;
            } else {
                error_log("⚠️ Mês $mesReferencia parece inválido");
            }
        }
        
        return null;
    }

    /**
     * 🆕 PARSEAR DATA FLEXÍVEL (aceita vários formatos)
     */
    private function parsearDataFlexivel($dataStr) {
        if (empty($dataStr)) return false;
        
        $dataStr = trim($dataStr);
        
        // Formatos mais comuns no Brasil
        $formatos = [
            'Y-m-d',      // 2025-01-15
            'd/m/Y',      // 15/01/2025  
            'd-m-Y',      // 15-01-2025
            'd/m/y',      // 15/01/25
            'Y/m/d',      // 2025/01/15
            'Y-m-d H:i:s', // 2025-01-15 10:30:00
            'm/d/Y'       // 01/15/2025 (americano)
        ];
        
        foreach ($formatos as $formato) {
            $data = DateTime::createFromFormat($formato, $dataStr);
            if ($data && $data->format($formato) === $dataStr) {
                return $data->getTimestamp();
            }
        }
        
        // Último recurso: strtotime
        $timestamp = strtotime($dataStr);
        return ($timestamp && $timestamp > 0) ? $timestamp : false;
    }

    /**
     * 🆕 VALIDAR MÊS DE REFERÊNCIA
     */
    private function validarMesReferencia($mesReferencia) {
        $timestamp = strtotime($mesReferencia);
        if (!$timestamp) return false;
        
        $hoje = time();
        $mesRef = strtotime($mesReferencia);
        
        // Não pode ser mais de 6 meses no futuro (pagamentos muito antecipados são suspeitos)
        $seisMeses = strtotime('+6 months', $hoje);
        if ($mesRef > $seisMeses) {
            error_log("❌ Mês $mesReferencia muito no futuro (+ de 6 meses)");
            return false;
        }
        
        // Não pode ser mais de 12 meses no passado
        $umAno = strtotime('-12 months', $hoje);
        if ($mesRef < $umAno) {
            error_log("❌ Mês $mesReferencia muito no passado (+ de 12 meses)");
            return false;
        }
        
        return true;
    }

    /**
     * 🆕 FALLBACK: Usar data de pagamento
     */
    private function extrairMesPorPagamento($dadosCSV) {
        $colunaPagamento = $this->colunasCSV['data_pagamento'];
        if (!$colunaPagamento) return null;
        
        error_log("⚠️ Usando data de PAGAMENTO como fallback (coluna: '$colunaPagamento')");
        
        $amostra = array_slice($dadosCSV, 0, 5);
        $datasPagamento = [];
        
        foreach ($amostra as $linha) {
            $dataStr = trim($linha[$colunaPagamento] ?? '');
            
            if (!empty($dataStr)) {
                $timestamp = $this->parsearDataFlexivel($dataStr);
                
                if ($timestamp) {
                    // Para pagamento, assumir mensalidade do mesmo mês
                    $mesRef = date('Y-m', $timestamp);
                    $datasPagamento[$mesRef] = ($datasPagamento[$mesRef] ?? 0) + 1;
                }
            }
        }
        
        if (!empty($datasPagamento)) {
            arsort($datasPagamento);
            $mesMaisComum = array_key_first($datasPagamento);
            return $mesMaisComum . '-01';
        }
        
        return null;
    }

    /**
     * 🆕 DEBUG ESPECÍFICO PARA DATAS DE VENCIMENTO
     */
    private function debugDatasVencimento($dadosCSV) {
        if (!$this->colunasCSV['data_vencimento']) {
            error_log("⚠️ DEBUG: Coluna de vencimento NÃO encontrada");
            error_log("📋 Colunas disponíveis: " . implode(', ', array_keys($dadosCSV[0] ?? [])));
            return;
        }
        
        $coluna = $this->colunasCSV['data_vencimento'];
        error_log("🔍 DEBUG VENCIMENTOS (coluna: '$coluna'):");
        
        $amostras = array_slice($dadosCSV, 0, 3);
        foreach ($amostras as $index => $linha) {
            $dataOriginal = $linha[$coluna] ?? '';
            $timestamp = $this->parsearDataFlexivel($dataOriginal);
            
            if ($timestamp) {
                $dataFormatada = date('d/m/Y', $timestamp);
                $mesAno = date('m/Y', $timestamp);
                error_log("  🗓️ [$index] '$dataOriginal' → $dataFormatada (Mês: $mesAno)");
            } else {
                error_log("  ❌ [$index] '$dataOriginal' → INVÁLIDA");
            }
        }
    }

    /**
     * 🆕 REGISTRAR PAGAMENTO NO HISTÓRICO - VERSÃO COM VENCIMENTO
     */
    private function registrarPagamentoHistorico($associadoId, $mesReferencia, $dadosPagamento, $usuarioId) {
        try {
            // Verificar se já existe pagamento para este mês
            $sqlCheck = "SELECT id FROM Pagamentos_Associado 
                        WHERE associado_id = ? AND mes_referencia = ?";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->execute([$associadoId, $mesReferencia]);
            
            if ($stmtCheck->fetch()) {
                error_log("⚠️ Pagamento já existe para associado $associadoId no mês $mesReferencia - atualizando...");
                
                // Atualizar pagamento existente
                $sqlUpdate = "UPDATE Pagamentos_Associado SET
                             valor_pago = ?,
                             data_pagamento = ?,
                             data_vencimento = ?,
                             observacoes = ?,
                             data_atualizacao = NOW()
                             WHERE associado_id = ? AND mes_referencia = ?";
                
                $stmt = $this->db->prepare($sqlUpdate);
                $stmt->execute([
                    $this->extrairValorNumerico($dadosPagamento['valor']),
                    $this->converterDataPagamento($dadosPagamento['data_pagamento']),
                    $this->converterDataPagamento($dadosPagamento['data_vencimento']),
                    "Atualizado via ASAAS - Venc: " . ($dadosPagamento['data_vencimento'] ?: 'N/A'),
                    $associadoId,
                    $mesReferencia
                ]);
                
                return $associadoId;
            } else {
                // Inserir novo pagamento
                $sql = "INSERT INTO Pagamentos_Associado (
                           associado_id, mes_referencia, valor_pago, data_pagamento,
                           data_vencimento, forma_pagamento, status_pagamento, origem_importacao,
                           observacoes, funcionario_registro
                       ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $associadoId,
                    $mesReferencia,
                    $this->extrairValorNumerico($dadosPagamento['valor']),
                    $this->converterDataPagamento($dadosPagamento['data_pagamento']),
                    $this->converterDataPagamento($dadosPagamento['data_vencimento']),
                    'ASAAS',
                    'CONFIRMADO',
                    'IMPORTACAO_ASAAS',
                    "Vencimento: " . ($dadosPagamento['data_vencimento'] ?: 'N/A') . " | " . ($dadosPagamento['situacao'] ?? 'Recebida'),
                    $usuarioId
                ]);
                
                return $this->db->lastInsertId();
            }
            
        } catch (Exception $e) {
            error_log("❌ Erro ao registrar pagamento no histórico: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🆕 CONVERTER DATA PARA FORMATO MYSQL
     */
    private function converterDataPagamento($dataStr) {
        if (empty($dataStr)) {
            return date('Y-m-d');
        }
        
        $timestamp = $this->parsearDataFlexivel($dataStr);
        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    /**
     * 🆕 EXTRAIR VALOR NUMÉRICO LIMPO
     */
    private function extrairValorNumerico($valorString) {
        if (empty($valorString)) {
            return 0.00;
        }
        
        // Remover tudo exceto números, vírgulas e pontos
        $valorLimpo = preg_replace('/[^\d,.]/', '', $valorString);
        
        if (empty($valorLimpo)) {
            return 0.00;
        }
        
        // Converter para float
        if (strpos($valorLimpo, ',') !== false && strpos($valorLimpo, '.') !== false) {
            $valorLimpo = str_replace('.', '', $valorLimpo);
            $valorLimpo = str_replace(',', '.', $valorLimpo);
        } else if (strpos($valorLimpo, ',') !== false) {
            $valorLimpo = str_replace(',', '.', $valorLimpo);
        }
        
        return floatval($valorLimpo);
    }

    /**
     * 🆕 GERAR RELATÓRIO DO HISTÓRICO PROCESSADO
     */
    private function gerarRelatorioHistorico($mesReferencia) {
        try {
            $sql = "SELECT 
                       p.id, p.associado_id, a.nome, a.cpf, p.valor_pago,
                       p.data_pagamento, p.data_vencimento, p.mes_referencia, p.data_registro,
                       m.corporacao, m.patente
                   FROM Pagamentos_Associado p
                   JOIN Associados a ON p.associado_id = a.id
                   LEFT JOIN Militar m ON a.id = m.associado_id
                   WHERE p.mes_referencia = ?
                   AND p.data_registro >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
                   ORDER BY p.data_registro DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$mesReferencia]);
            
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("📊 Relatório de histórico gerado: " . count($resultados) . " pagamentos do mês $mesReferencia");
            
            return $resultados;
            
        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de histórico: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 🆕 ENCONTRAR COLUNA POR NOME
     */
    private function encontrarColuna($colunas, $possiveisNomes) {
        foreach ($possiveisNomes as $nome) {
            if (in_array($nome, $colunas)) {
                return $nome;
            }
        }
        return null;
    }

    /**
     * EXTRAIR CPFs ÚNICOS - VERSÃO CORRIGIDA
     */
    private function extrairCPFsUnicos($dadosCSV) {
        $cpfs = [];
        $colunaCpf = $this->colunasCSV['cpf'];
        $cpfsCorrigidos = 0;
        $cpfsInvalidos = 0;
        
        error_log("🔍 Extraindo CPFs da coluna: '$colunaCpf'");
        
        foreach ($dadosCSV as $index => $linha) {
            $cpfBruto = $linha[$colunaCpf] ?? '';
            $cpfLimpoOriginal = preg_replace('/\D/', '', trim($cpfBruto));
            $cpf = $this->limparCPF($cpfBruto);
            
            if ($cpf) {
                if (!in_array($cpf, $cpfs)) {
                    $cpfs[] = $cpf;
                    
                    if ($index < 5 || strlen($cpfLimpoOriginal) < 11) {
                        if (strlen($cpfLimpoOriginal) < 11) {
                            $cpfsCorrigidos++;
                        }
                    }
                }
            } else {
                $cpfsInvalidos++;
            }
        }
        
        error_log("📋 RESUMO DA EXTRAÇÃO DE CPFs:");
        error_log("  Total de CPFs únicos: " . count($cpfs));
        error_log("  CPFs corrigidos: $cpfsCorrigidos");
        error_log("  CPFs inválidos: $cpfsInvalidos");
        
        return $cpfs;
    }

    /**
     * BUSCAR TODOS OS ASSOCIADOS DO SISTEMA
     */
    private function buscarTodosAssociados() {
        $sql = "SELECT DISTINCT
                    a.id,
                    a.nome,
                    a.cpf,
                    a.email,
                    COALESCE(m.corporacao, 'N/A') as corporacao,
                    COALESCE(m.patente, 'N/A') as patente,
                    f.situacaoFinanceira as situacao_atual
                FROM Associados a
                LEFT JOIN Militar m ON a.id = m.associado_id
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                WHERE a.situacao = 'Filiado'
                ORDER BY a.nome";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * DEBUG: Verificar intersecção entre CPFs
     */
    private function debugInterseccaoCPFs() {
        $cpfsAssociados = [];
        foreach ($this->associadosSistema as $assoc) {
            $cpfLimpo = $this->limparCPF($assoc['cpf']);
            if ($cpfLimpo) {
                $cpfsAssociados[] = $cpfLimpo;
            }
        }
        
        $interseccao = array_intersect($this->cpfsPagantes, $cpfsAssociados);
        
        error_log("🔍 DEBUG INTERSECÇÃO:");
        error_log("  CPFs no CSV: " . count($this->cpfsPagantes));
        error_log("  CPFs associados: " . count($cpfsAssociados));
        error_log("  Matches: " . count($interseccao));
    }

    /**
     * BUSCAR DADOS DE PAGAMENTO - VERSÃO COM VENCIMENTO
     */
    private function buscarDadosPagamento($cpf, $dadosCSV) {
        $colunaCpf = $this->colunasCSV['cpf'];
        $colunaEmail = $this->colunasCSV['email'];
        $colunaSituacao = $this->colunasCSV['situacao'];
        $colunaValor = $this->colunasCSV['valor'];
        $colunaData = $this->colunasCSV['data_pagamento'];
        $colunaVencimento = $this->colunasCSV['data_vencimento']; // 🆕 VENCIMENTO
        
        foreach ($dadosCSV as $index => $linha) {
            $cpfLinha = $this->limparCPF($linha[$colunaCpf] ?? '');
            if ($cpfLinha === $cpf) {
                
                $valorBruto = $linha[$colunaValor] ?? '';
                $valorLimpo = $this->extrairValor($valorBruto);
                
                $dados = [
                    'email' => $linha[$colunaEmail] ?? '',
                    'situacao' => $linha[$colunaSituacao] ?? 'Recebida',
                    'valor' => $valorLimpo,
                    'data_pagamento' => $linha[$colunaData] ?? date('d/m/Y'),
                    'data_vencimento' => $linha[$colunaVencimento] ?? '', // 🆕 VENCIMENTO
                    'linha_completa' => $linha
                ];
                
                return $dados;
            }
        }
        
        return [
            'email' => '', 'situacao' => 'Recebida', 'valor' => '0,00',
            'data_pagamento' => date('d/m/Y'), 'data_vencimento' => ''
        ];
    }

    /**
     * EXTRAIR VALOR DE STRING
     */
    private function extrairValor($valorString) {
        if (empty($valorString)) {
            return '0,00';
        }
        
        $valorLimpo = preg_replace('/[^\d,.]/', '', $valorString);
        
        if (empty($valorLimpo)) {
            return '0,00';
        }
        
        if (strpos($valorLimpo, ',') !== false && strpos($valorLimpo, '.') !== false) {
            $valorLimpo = str_replace('.', '', $valorLimpo);
            $valorLimpo = str_replace(',', '.', $valorLimpo);
        } else if (strpos($valorLimpo, ',') !== false) {
            $valorLimpo = str_replace(',', '.', $valorLimpo);
        }
        
        $valorFloat = floatval($valorLimpo);
        return number_format($valorFloat, 2, ',', '.');
    }

    /**
     * VERIFICAR CPFs IGNORADOS
     */
    private function verificarCPFsIgnorados($dadosCSV, &$resultado) {
        $cpfsAssociados = array_map([$this, 'limparCPF'], array_column($this->associadosSistema, 'cpf'));
        $cpfsAssociados = array_filter($cpfsAssociados);
        
        foreach ($this->cpfsPagantes as $cpf) {
            if (!in_array($cpf, $cpfsAssociados)) {
                $dadosPessoa = $this->buscarDadosAssociadoPorCPF($cpf);
                
                if ($dadosPessoa) {
                    $resultado['ignorados'][] = [
                        'cpf' => $cpf,
                        'nome' => $dadosPessoa['nome'],
                        'corporacao' => $dadosPessoa['corporacao'] ?? 'N/A',
                        'motivo' => 'Situação: ' . ($dadosPessoa['situacao'] ?? 'Desconhecida'),
                        'acao' => 'Não processado (situação não é Filiado)'
                    ];
                } else {
                    $resultado['ignorados'][] = [
                        'cpf' => $cpf,
                        'nome' => 'Não cadastrado no sistema',
                        'corporacao' => 'N/A',
                        'motivo' => 'CPF não existe no sistema',
                        'acao' => 'Não processado'
                    ];
                }
                
                $resultado['resumo']['ignorados']++;
            }
        }
    }

    /**
     * BUSCAR DADOS DE ASSOCIADO POR CPF
     */
    private function buscarDadosAssociadoPorCPF($cpf) {
        $sql = "SELECT 
                    a.id, a.nome, a.cpf, a.email, a.situacao,
                    COALESCE(m.corporacao, 'N/A') as corporacao,
                    COALESCE(m.patente, 'N/A') as patente
                FROM Associados a
                LEFT JOIN Militar m ON a.id = m.associado_id
                WHERE a.cpf = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$cpf]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ATUALIZAR BANCO - VERSÃO COM HISTÓRICO
     */
    private function atualizarBanco($resultado, $usuarioId, $mesReferencia) {
        $atualizados = 0;
        
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE Financeiro SET 
                        situacaoFinanceira = 'Adimplente',
                        data_ultima_verificacao = NOW(),
                        observacoes_asaas = ?,
                        valor_em_aberto_asaas = 0,
                        dias_atraso_asaas = 0,
                        ultimo_vencimento_asaas = NULL
                    WHERE associado_id = ?";
            
            $stmt = $this->db->prepare($sql);

            foreach ($resultado['pagantes'] as $associado) {
                $observacao = sprintf(
                    "PAGAMENTO CONFIRMADO | Mês: %s | Valor: R$ %s | Vencimento: %s | Histórico ID: %d | Importado: %s",
                    date('m/Y', strtotime($mesReferencia)),
                    $associado['dados_pagamento']['valor'] ?? '0,00',
                    $associado['dados_pagamento']['data_vencimento'] ?: 'N/A',
                    $associado['pagamento_id'] ?? 0,
                    date('d/m/Y H:i')
                );
                
                if ($stmt->execute([$observacao, $associado['id']])) {
                    $atualizados++;
                }
            }

            $this->registrarAuditoriaComHistorico($usuarioId, $resultado, $mesReferencia);
            $this->registrarHistoricoImportacao($usuarioId, $resultado, $mesReferencia);

            $this->db->commit();
            
            return $atualizados;

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception('Erro ao atualizar banco: ' . $e->getMessage());
        }
    }

    /**
     * REGISTRAR AUDITORIA
     */
    private function registrarAuditoriaComHistorico($usuarioId, $resultado, $mesReferencia) {
        try {
            $sql = "INSERT INTO Auditoria (tabela, acao, funcionario_id, detalhes, ip, user_agent) 
                    VALUES ('Financeiro', 'IMPORTACAO_ASAAS_COM_HISTORICO_VENCIMENTO', ?, ?, ?, ?)";
            
            $detalhes = json_encode([
                'mes_referencia' => $mesReferencia,
                'total_processados' => $resultado['resumo']['totalProcessados'],
                'pagantes' => $resultado['resumo']['pagantes'],
                'pagamentos_registrados' => $resultado['resumo']['pagamentosRegistrados'],
                'nao_encontrados' => $resultado['resumo']['nao_encontrados'],
                'ignorados' => $resultado['resumo']['ignorados'],
                'data_importacao' => date('Y-m-d H:i:s'),
                'versao' => 'COM_HISTORICO_VENCIMENTO'
            ]);
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $usuarioId,
                $detalhes,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
        }
    }

    /**
     * REGISTRAR HISTÓRICO DE IMPORTAÇÃO
     */
    private function registrarHistoricoImportacao($usuarioId, $resultado, $mesReferencia) {
        try {
            $sql = "INSERT INTO Historico_Importacoes_ASAAS 
                    (funcionario_id, total_registros, adimplentes, inadimplentes, atualizados, erros, observacoes, ip_origem) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $observacoes = sprintf(
                "VERSÃO COM HISTÓRICO + VENCIMENTO: Mês %s | Pagantes: %d | Histórico: %d | Não encontrados: %d | Ignorados: %d - %s",
                date('m/Y', strtotime($mesReferencia)),
                $resultado['resumo']['pagantes'],
                $resultado['resumo']['pagamentosRegistrados'],
                $resultado['resumo']['nao_encontrados'],
                $resultado['resumo']['ignorados'],
                date('d/m/Y H:i:s')
            );
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $usuarioId,
                $resultado['resumo']['totalProcessados'],
                $resultado['resumo']['pagantes'],
                $resultado['resumo']['nao_encontrados'],
                $resultado['resumo']['atualizadosBanco'],
                $resultado['resumo']['erros'],
                $observacoes,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar histórico: " . $e->getMessage());
        }
    }

    /**
     * LIMPAR E CORRIGIR CPF
     */
    private function limparCPF($cpf) {
        if (empty($cpf)) return null;
        
        $cpfLimpo = preg_replace('/\D/', '', trim($cpf));
        
        if (empty($cpfLimpo)) return null;
        
        if (strlen($cpfLimpo) >= 9 && strlen($cpfLimpo) <= 11) {
            return str_pad($cpfLimpo, 11, '0', STR_PAD_LEFT);
        }
        
        return null;
    }
}
?>