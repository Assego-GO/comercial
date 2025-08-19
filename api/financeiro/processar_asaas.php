<?php
/**
 * Processador de dados CSV ASAAS - Sistema ASSEGO
 * api/financeiro/processar_asaas.php
 * Processa os dados importados do ASAAS e atualiza status de adimplência
 * 
 * VERSÃO HÍBRIDA - Lógica seletiva:
 * - PAGANTES: Todas as corporações são processadas
 * - NÃO ENCONTRADOS: Apenas Exército, Agregados e Pensionista são reportados
 * Problemas corrigidos:
 * 1. CPFs com 9-10 dígitos (preenchimento com zeros)
 * 2. Valores zerados (extração melhorada)
 * 3. Escopo híbrido conforme solicitação
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
    error_log("=== PROCESSAMENTO ASAAS UNIVERSAL INICIADO ===");
    error_log("Usuário: " . $usuarioLogado['nome'] . " (ID: " . $usuarioLogado['id'] . ")");
    error_log("Registros: " . count($dadosCSV));
    error_log("Escopo: Todos os associados (todas as corporações)");

    // Processar dados
    $processador = new ProcessadorAsaas();
    $resultado = $processador->processar($dadosCSV, $usuarioLogado['id']);

    // Log de resultado
    error_log("✅ PROCESSAMENTO UNIVERSAL CONCLUÍDO:");
    error_log("- Total associados processados: " . $resultado['resumo']['totalProcessados']);
    error_log("- Pagantes (marcados adimplentes): " . $resultado['resumo']['pagantes']);
    error_log("- Não encontrados (reportados): " . $resultado['resumo']['nao_encontrados']);
    error_log("- Ignorados (não são associados): " . $resultado['resumo']['ignorados']);

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
 * Processador ASAAS - VERSÃO HÍBRIDA
 * 
 * LÓGICA:
 * 1. CSV contém apenas quem PAGOU (não mais cobranças pendentes)
 * 2. PAGANTES: Processa TODOS os associados (todas as corporações)
 * 3. NÃO ENCONTRADOS: Reporta apenas Exército, Agregados e Pensionista
 * 4. Quem está no CSV = ADIMPLENTE
 * 5. Quem não está = apenas reporta se for das 3 corporações específicas
 * 6. CPFs não encontrados no sistema = ignorados
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
     * PROCESSAMENTO PRINCIPAL - VERSÃO UNIVERSAL COM FILTRO NOS NÃO ENCONTRADOS
     */
    public function processar($dadosCSV, $usuarioId) {
        $resultado = [
            'pagantes' => [],           
            'nao_encontrados' => [],    
            'ignorados' => [],          
            'resumo' => [
                'totalProcessados' => 0,
                'pagantes' => 0,
                'nao_encontrados' => 0,
                'ignorados' => 0,
                'atualizadosBanco' => 0,
                'erros' => 0
            ]
        ];

        try {
            // 🆕 PASSO 0: Detectar estrutura do CSV
            $this->detectarEstruturaCsv($dadosCSV);

            // PASSO 1: Extrair CPFs únicos do CSV (quem pagou)
            $this->cpfsPagantes = $this->extrairCPFsUnicos($dadosCSV);
            error_log("🔍 CPFs únicos extraídos do CSV: " . count($this->cpfsPagantes));
            error_log("📋 Lista de CPFs: " . implode(', ', array_slice($this->cpfsPagantes, 0, 5)) . (count($this->cpfsPagantes) > 5 ? '...' : ''));

            // PASSO 2: Buscar TODOS os associados do sistema (todas as corporações)
            $this->associadosSistema = $this->buscarTodosAssociados();
            error_log("🔍 Total de associados no sistema: " . count($this->associadosSistema));

            // 🆕 DEBUG: Verificar se há intersecção entre CPFs
            $this->debugInterseccaoCPFs();

            // PASSO 3: Classificar cada associado
            $processados = 0;
            foreach ($this->associadosSistema as $associado) {
                try {
                    $cpf = $this->limparCPF($associado['cpf']);
                    $processados++;
                    
                    // Debug apenas para os primeiros ou quando encontra match
                    $isMatch = in_array($cpf, $this->cpfsPagantes);
                    if ($processados <= 10 || $isMatch) {
                        error_log("🔍 [$processados] Verificando: {$associado['nome']} ({$associado['corporacao']}) (CPF: {$cpf}) " . ($isMatch ? "✅ MATCH!" : "❌ Não encontrado"));
                    }
                    
                    if ($isMatch) {
                        // ✅ PAGOU - Marcar como adimplente
                        $dadosPagamento = $this->buscarDadosPagamento($cpf, $dadosCSV);
                        
                        // 🆕 GARANTIR CPF CORRIGIDO NO RESULTADO
                        $associado['cpf'] = $cpf; // CPF já limpo e com zeros à esquerda
                        $associado['status'] = 'ADIMPLENTE';
                        $associado['motivo'] = 'Encontrado no arquivo de pagamentos';
                        $associado['dados_pagamento'] = $dadosPagamento;
                        $associado['acao'] = 'Marcado como ADIMPLENTE';
                        
                        $resultado['pagantes'][] = $associado;
                        $resultado['resumo']['pagantes']++;
                        
                        error_log("✅ PAGANTE: {$associado['nome']} ({$associado['corporacao']}) - CPF: {$cpf} - Valor: R$ {$dadosPagamento['valor']}");
                    } else {
                        // ⚠️ NÃO PAGOU - Verificar se deve ser reportado
                        $corporacao = $associado['corporacao'];
                        
                        // 🆕 FILTRO: Apenas Exército, Agregados e Pensionista na aba "Não Encontrados"
                        if (in_array($corporacao, ['Exército', 'Agregados', 'Pensionista'])) {
                            // 🆕 GARANTIR CPF CORRIGIDO NO RESULTADO
                            $associado['cpf'] = $cpf; // CPF já limpo e com zeros à esquerda
                            $associado['status'] = 'NAO_ENCONTRADO';
                            $associado['motivo'] = 'Não encontrado no arquivo de pagamentos';
                            $associado['acao'] = 'Apenas reportado (não marcado inadimplente)';
                            
                            $resultado['nao_encontrados'][] = $associado;
                            $resultado['resumo']['nao_encontrados']++;
                            
                            if ($resultado['resumo']['nao_encontrados'] <= 5) { // Log apenas os primeiros não encontrados
                                error_log("⚠️ NÃO ENCONTRADO (reportado): {$associado['nome']} ({$associado['corporacao']}) - CPF: {$cpf}");
                            }
                        } else {
                            // Outras corporações não são reportadas na aba "Não Encontrados"
                            if ($processados <= 3) {
                                error_log("ℹ️ NÃO ENCONTRADO (não reportado): {$associado['nome']} ({$associado['corporacao']}) - CPF: {$cpf} - Corporação fora do escopo de relatório");
                            }
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

            // PASSO 5: Atualizar banco APENAS com os pagantes
            $atualizados = $this->atualizarBanco($resultado, $usuarioId);
            $resultado['resumo']['atualizadosBanco'] = $atualizados;

            // 🆕 RESUMO FINAL DO PROCESSAMENTO
            error_log("🎯 RESUMO FINAL DO PROCESSAMENTO:");
            error_log("  📊 Total de registros no CSV: " . count($dadosCSV));
            error_log("  📊 CPFs únicos extraídos do CSV: " . count($this->cpfsPagantes));
            error_log("  📊 Associados no sistema: " . count($this->associadosSistema));
            error_log("  ✅ Pagantes encontrados: " . $resultado['resumo']['pagantes']);
            error_log("  ⚠️ Não encontrados (Exército/Agregados/Pensionista): " . $resultado['resumo']['nao_encontrados']);
            error_log("  🚫 Ignorados (não são associados): " . $resultado['resumo']['ignorados']);
            error_log("  💾 Registros atualizados no banco: $atualizados");
            error_log("  ❌ Erros: " . $resultado['resumo']['erros']);

            return $resultado;

        } catch (Exception $e) {
            error_log("❌ Erro no processamento ASAAS: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 🆕 DETECTAR ESTRUTURA DO CSV AUTOMATICAMENTE
     */
    private function detectarEstruturaCsv($dadosCSV) {
        if (empty($dadosCSV)) {
            throw new Exception('CSV vazio ou inválido');
        }

        $primeiraLinha = $dadosCSV[0];
        $colunas = array_keys($primeiraLinha);
        
        error_log("📊 ESTRUTURA DO CSV DETECTADA:");
        error_log("Colunas encontradas: " . implode(' | ', $colunas));

        // Mapear colunas mais prováveis
        $this->colunasCSV = [
            'cpf' => $this->encontrarColuna($colunas, ['CPF ou CNPJ', 'CPF', 'CNPJ', 'cpf', 'Cpf']),
            'email' => $this->encontrarColuna($colunas, ['Email', 'E-mail', 'email', 'EMAIL']),
            'situacao' => $this->encontrarColuna($colunas, ['Situação', 'Status', 'situacao', 'status']),
            'valor' => $this->encontrarColuna($colunas, ['Valor', 'Valor da cobrança', 'Valor pago', 'valor', 'VALOR']),
            'data_pagamento' => $this->encontrarColuna($colunas, ['Data de Pagamento', 'Data do pagamento', 'Data', 'data_pagamento']),
            'nome' => $this->encontrarColuna($colunas, ['Nome', 'Cliente', 'nome', 'NOME'])
        ];

        error_log("📋 MAPEAMENTO DE COLUNAS:");
        foreach ($this->colunasCSV as $campo => $coluna) {
            error_log("  $campo -> " . ($coluna ?: 'NÃO ENCONTRADA'));
        }

        // Validar colunas essenciais
        if (!$this->colunasCSV['cpf']) {
            throw new Exception('Coluna de CPF não encontrada. Colunas disponíveis: ' . implode(', ', $colunas));
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
     * EXTRAIR CPFs ÚNICOS - VERSÃO CORRIGIDA COM DEBUG
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
                    
                    // Log apenas para os primeiros registros ou quando há correção
                    if ($index < 5 || strlen($cpfLimpoOriginal) < 11) {
                        if (strlen($cpfLimpoOriginal) < 11) {
                            error_log("✅ Linha $index: '$cpfBruto' → '$cpf' (corrigido)");
                            $cpfsCorrigidos++;
                        } else {
                            error_log("✅ Linha $index: '$cpfBruto' → '$cpf' (já correto)");
                        }
                    }
                }
            } else {
                $cpfsInvalidos++;
                if ($index < 5) {
                    error_log("❌ Linha $index: CPF inválido '$cpfBruto'");
                }
            }
        }
        
        error_log("📋 RESUMO DA EXTRAÇÃO DE CPFs:");
        error_log("  Total de CPFs únicos extraídos: " . count($cpfs));
        error_log("  CPFs corrigidos (preenchidos com zeros): $cpfsCorrigidos");
        error_log("  CPFs inválidos (ignorados): $cpfsInvalidos");
        error_log("  Taxa de sucesso: " . round((count($cpfs) / count($dadosCSV)) * 100, 1) . "%");
        
        return $cpfs;
    }

    /**
     * BUSCAR TODOS OS ASSOCIADOS DO SISTEMA - VERSÃO UNIVERSAL
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
        $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Estatísticas por corporação
        $corporacoes = [];
        foreach ($associados as $assoc) {
            $corp = $assoc['corporacao'] ?: 'Não informado';
            $corporacoes[$corp] = ($corporacoes[$corp] ?? 0) + 1;
        }
        
        error_log("📋 ASSOCIADOS POR CORPORAÇÃO:");
        foreach ($corporacoes as $corp => $count) {
            error_log("  - $corp: $count associados");
        }
        
        error_log("📋 PRIMEIROS ASSOCIADOS ENCONTRADOS:");
        foreach (array_slice($associados, 0, 3) as $assoc) {
            error_log("  - {$assoc['nome']} (CPF: {$assoc['cpf']}) - {$assoc['corporacao']}");
        }
        if (count($associados) > 3) {
            error_log("  ... e mais " . (count($associados) - 3) . " associados");
        }
        
        return $associados;
    }

    /**
     * 🆕 DEBUG: Verificar intersecção entre CPFs - VERSÃO UNIVERSAL
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
        
        error_log("🔍 DEBUG INTERSECÇÃO DETALHADA:");
        error_log("  CPFs no CSV (após limpeza): " . count($this->cpfsPagantes));
        error_log("  CPFs de associados no sistema (após limpeza): " . count($cpfsAssociados));
        error_log("  Intersecção (matches): " . count($interseccao));
        
        if (count($interseccao) > 0) {
            error_log("  ✅ Primeiros matches encontrados:");
            foreach (array_slice($interseccao, 0, 5) as $index => $cpf) {
                // Encontrar nome e corporação do associado
                $infoAssociado = 'Não encontrado';
                foreach ($this->associadosSistema as $assoc) {
                    if ($this->limparCPF($assoc['cpf']) === $cpf) {
                        $infoAssociado = $assoc['nome'] . ' (' . $assoc['corporacao'] . ')';
                        break;
                    }
                }
                error_log("    " . ($index + 1) . ". CPF: $cpf - $infoAssociado");
            }
        } else {
            error_log("  ❌ NENHUM MATCH ENCONTRADO!");
            error_log("  📋 Primeiros CPFs do CSV:");
            foreach (array_slice($this->cpfsPagantes, 0, 3) as $index => $cpf) {
                error_log("    " . ($index + 1) . ". $cpf");
            }
            error_log("  📋 Primeiros CPFs do banco:");
            foreach (array_slice($cpfsAssociados, 0, 3) as $index => $cpf) {
                error_log("    " . ($index + 1) . ". $cpf");
            }
        }
    }

    /**
     * BUSCAR DADOS DE PAGAMENTO - VERSÃO CORRIGIDA COM MAIS DEBUG
     */
    private function buscarDadosPagamento($cpf, $dadosCSV) {
        $colunaCpf = $this->colunasCSV['cpf'];
        $colunaEmail = $this->colunasCSV['email'];
        $colunaSituacao = $this->colunasCSV['situacao'];
        $colunaValor = $this->colunasCSV['valor'];
        $colunaData = $this->colunasCSV['data_pagamento'];
        
        foreach ($dadosCSV as $index => $linha) {
            $cpfLinha = $this->limparCPF($linha[$colunaCpf] ?? '');
            if ($cpfLinha === $cpf) {
                
                // 🆕 EXTRAIR E LIMPAR VALOR COM DEBUG DETALHADO
                $valorBruto = $linha[$colunaValor] ?? '';
                $valorLimpo = $this->extrairValor($valorBruto);
                
                // 🆕 EXTRAIR OUTROS DADOS
                $email = $linha[$colunaEmail] ?? '';
                $situacao = $linha[$colunaSituacao] ?? 'Recebida';
                $dataPagamento = $linha[$colunaData] ?? date('d/m/Y');
                
                $dados = [
                    'email' => $email,
                    'situacao' => $situacao,
                    'valor' => $valorLimpo,
                    'data_pagamento' => $dataPagamento,
                    'linha_completa' => $linha
                ];
                
                error_log("💰 DADOS DE PAGAMENTO ENCONTRADOS:");
                error_log("  CPF: $cpf");
                error_log("  Valor bruto: '$valorBruto' → Valor limpo: 'R$ $valorLimpo'");
                error_log("  Email: '$email'");
                error_log("  Situação: '$situacao'");
                error_log("  Data: '$dataPagamento'");
                
                return $dados;
            }
        }
        
        // Default se não encontrar (não deveria acontecer)
        error_log("⚠️ ATENÇÃO: CPF $cpf não encontrado no CSV para buscar dados de pagamento!");
        return [
            'email' => '',
            'situacao' => 'Recebida',
            'valor' => '0,00',
            'data_pagamento' => date('d/m/Y')
        ];
    }

    /**
     * 🆕 EXTRAIR VALOR NUMÉRICO DE STRING
     */
    private function extrairValor($valorString) {
        if (empty($valorString)) {
            return '0,00';
        }
        
        // Remover tudo exceto números, vírgulas e pontos
        $valorLimpo = preg_replace('/[^\d,.]/', '', $valorString);
        
        // Se está vazio após limpeza
        if (empty($valorLimpo)) {
            return '0,00';
        }
        
        // Converter para float
        // Se tem vírgula e ponto, assumir formato brasileiro (1.234,56)
        if (strpos($valorLimpo, ',') !== false && strpos($valorLimpo, '.') !== false) {
            $valorLimpo = str_replace('.', '', $valorLimpo); // Remove pontos
            $valorLimpo = str_replace(',', '.', $valorLimpo); // Vírgula vira ponto
        } 
        // Se tem apenas vírgula, assumir decimal brasileiro (123,45)
        else if (strpos($valorLimpo, ',') !== false) {
            $valorLimpo = str_replace(',', '.', $valorLimpo);
        }
        
        $valorFloat = floatval($valorLimpo);
        return number_format($valorFloat, 2, ',', '.');
    }

    /**
     * VERIFICAR CPFs QUE NÃO SÃO ASSOCIADOS DO SISTEMA
     */
    private function verificarCPFsIgnorados($dadosCSV, &$resultado) {
        $cpfsAssociados = array_map([$this, 'limparCPF'], array_column($this->associadosSistema, 'cpf'));
        $cpfsAssociados = array_filter($cpfsAssociados); // Remove nulls
        
        foreach ($this->cpfsPagantes as $cpf) {
            if (!in_array($cpf, $cpfsAssociados)) {
                // Buscar dados da pessoa para reportar (pode não estar no sistema)
                $dadosPessoa = $this->buscarDadosAssociadoPorCPF($cpf);
                
                if ($dadosPessoa) {
                    // Pessoa existe no sistema mas não estava na busca inicial (provavelmente situação != 'Filiado')
                    $resultado['ignorados'][] = [
                        'cpf' => $cpf, // 🆕 CPF já corrigido com zeros à esquerda
                        'nome' => $dadosPessoa['nome'],
                        'corporacao' => $dadosPessoa['corporacao'] ?? 'N/A',
                        'motivo' => 'Situação: ' . ($dadosPessoa['situacao'] ?? 'Desconhecida'),
                        'acao' => 'Não processado (situação não é Filiado)'
                    ];
                } else {
                    // Pessoa não existe no sistema
                    $resultado['ignorados'][] = [
                        'cpf' => $cpf, // 🆕 CPF já corrigido com zeros à esquerda
                        'nome' => 'Não cadastrado no sistema',
                        'corporacao' => 'N/A',
                        'motivo' => 'CPF não existe no sistema de associados',
                        'acao' => 'Não processado'
                    ];
                }
                
                $resultado['resumo']['ignorados']++;
            }
        }
    }

    /**
     * BUSCAR DADOS COMPLETOS DE UM ASSOCIADO POR CPF
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
     * ATUALIZAR BANCO - MANTIDO
     */
    private function atualizarBanco($resultado, $usuarioId) {
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

            // Processar APENAS os pagantes (marcar como adimplente)
            foreach ($resultado['pagantes'] as $associado) {
                $observacao = sprintf(
                    "ASAAS: %s | Valor pago: R$ %s | Data: %s (Importado em %s)",
                    $associado['motivo'],
                    $associado['dados_pagamento']['valor'] ?? '0,00',
                    $associado['dados_pagamento']['data_pagamento'] ?? 'N/A',
                    date('d/m/Y H:i')
                );
                
                if ($stmt->execute([$observacao, $associado['id']])) {
                    $atualizados++;
                    error_log("✅ {$associado['nome']} → ADIMPLENTE (pagou R$ {$associado['dados_pagamento']['valor']})");
                }
            }

            // Registros de auditoria
            $this->registrarAuditoria($usuarioId, $resultado);
            $this->registrarHistoricoImportacao($usuarioId, $resultado);

            $this->db->commit();
            error_log("✅ Transação finalizada: $atualizados registros atualizados para ADIMPLENTE");
            
            return $atualizados;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("❌ Erro na transação: " . $e->getMessage());
            throw new Exception('Erro ao atualizar banco: ' . $e->getMessage());
        }
    }

    /**
     * REGISTRAR AUDITORIA - VERSÃO HÍBRIDA
     */
    private function registrarAuditoria($usuarioId, $resultado) {
        try {
            $sql = "INSERT INTO Auditoria (tabela, acao, funcionario_id, detalhes, ip, user_agent) 
                    VALUES ('Financeiro', 'IMPORTACAO_ASAAS_HIBRIDA', ?, ?, ?, ?)";
            
            $detalhes = json_encode([
                'total_processados' => $resultado['resumo']['totalProcessados'],
                'pagantes' => $resultado['resumo']['pagantes'],
                'nao_encontrados' => $resultado['resumo']['nao_encontrados'],
                'ignorados' => $resultado['resumo']['ignorados'],
                'data_importacao' => date('Y-m-d H:i:s'),
                'escopo_pagantes' => 'Todas as corporações',
                'escopo_nao_encontrados' => 'Exército, Agregados e Pensionista',
                'versao' => 'HIBRIDA'
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
     * REGISTRAR HISTÓRICO - VERSÃO HÍBRIDA
     */
    private function registrarHistoricoImportacao($usuarioId, $resultado) {
        try {
            $sql = "INSERT INTO Historico_Importacoes_ASAAS 
                    (funcionario_id, total_registros, adimplentes, inadimplentes, atualizados, erros, observacoes, ip_origem) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $observacoes = sprintf(
                "VERSÃO HÍBRIDA: Pagantes de todas corporações | Relatório de não encontrados: Exército/Agregados/Pensionista | Pagantes: %d | Não encontrados: %d | Ignorados: %d - %s",
                $resultado['resumo']['pagantes'],
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
            
            error_log("✅ Histórico registrado - Versão híbrida");
            
        } catch (Exception $e) {
            error_log("Erro ao registrar histórico: " . $e->getMessage());
        }
    }

    /**
     * 🆕 LIMPAR E CORRIGIR CPF - VERSÃO CORRIGIDA
     * Preenche com zeros à esquerda quando necessário
     */
    private function limparCPF($cpf) {
        if (empty($cpf)) return null;
        
        // Remover tudo que não é número
        $cpfLimpo = preg_replace('/\D/', '', trim($cpf));
        
        // Se está vazio após limpeza
        if (empty($cpfLimpo)) return null;
        
        // Se tem entre 9-11 dígitos, preencher com zeros à esquerda
        if (strlen($cpfLimpo) >= 9 && strlen($cpfLimpo) <= 11) {
            $cpfCorrigido = str_pad($cpfLimpo, 11, '0', STR_PAD_LEFT);
            
            // Log da correção se foi necessária
            if (strlen($cpfLimpo) < 11) {
                error_log("🔧 CPF corrigido: '$cpfLimpo' (" . strlen($cpfLimpo) . " dígitos) → '$cpfCorrigido' (11 dígitos)");
            }
            
            return $cpfCorrigido;
        }
        
        // Se tem mais de 11 ou menos de 9 dígitos, é inválido
        error_log("❌ CPF inválido: '$cpfLimpo' (" . strlen($cpfLimpo) . " dígitos) - fora do range 9-11");
        return null;
    }
}
?>