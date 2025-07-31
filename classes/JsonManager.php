<?php
/**
 * Gerenciador de arquivos JSON para integração com Zapsing
 * classes/JsonManager.php
 * 
 * Salva dados dos associados em formato JSON para integração externa
 */

class JsonManager {
    
    private $jsonDirectory;
    private $logFile;
    
    public function __construct() {
        // Define diretórios
        $this->jsonDirectory = dirname(__DIR__) . '/data/json_exports/';
        $this->logFile = dirname(__DIR__) . '/logs/json_manager.log';
        
        // Cria estrutura de diretórios
        $this->criarEstruturaDiretorios();
    }
    
    /**
     * Cria a estrutura de diretórios necessária
     */
    private function criarEstruturaDiretorios() {
        // Diretório principal para JSONs
        if (!is_dir($this->jsonDirectory)) {
            mkdir($this->jsonDirectory, 0755, true);
            
            // Arquivo .htaccess para proteger o diretório
            $htaccessContent = "# Protege arquivos JSON\n";
            $htaccessContent .= "Order Deny,Allow\n";
            $htaccessContent .= "Deny from all\n";
            $htaccessContent .= "# Permite apenas scripts PHP locais\n";
            $htaccessContent .= "<Files \"*.json\">\n";
            $htaccessContent .= "    Deny from all\n";
            $htaccessContent .= "</Files>";
            
            file_put_contents($this->jsonDirectory . '.htaccess', $htaccessContent);
        }
        
        // Diretório de logs
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Subdiretórios organizacionais
        $subdirs = ['individual', 'consolidado', 'processed', 'errors'];
        foreach ($subdirs as $subdir) {
            $path = $this->jsonDirectory . $subdir . '/';
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    /**
     * Salva os dados do associado em JSON
     * 
     * @param array $dados Dados do formulário
     * @param int $associadoId ID do associado no banco
     * @param string $operacao 'CREATE' ou 'UPDATE'
     * @return array Resultado da operação
     */
    public function salvarAssociadoJson($dados, $associadoId, $operacao = 'CREATE') {
        try {
            $this->log("Iniciando salvamento JSON - Associado ID: {$associadoId}, Operação: {$operacao}");
            
            // Valida dados essenciais
            if (empty($dados['nome']) || empty($associadoId)) {
                throw new Exception("Dados insuficientes para gerar JSON");
            }
            
            // Prepara dados estruturados
            $dadosJson = $this->prepararDadosCompletos($dados, $associadoId, $operacao);
            
            // Salva arquivo individual
            $resultadoIndividual = $this->salvarArquivoIndividual($dadosJson, $associadoId);
            
            // Adiciona ao arquivo consolidado
            $this->adicionarAoConsolidado($dadosJson);
            
            // Atualiza estatísticas
            $this->atualizarEstatisticas($operacao);
            
            $this->log("JSON salvo com sucesso - Arquivo: " . $resultadoIndividual['nome_arquivo']);
            
            return [
                'sucesso' => true,
                'arquivo_individual' => $resultadoIndividual['nome_arquivo'],
                'caminho_completo' => $resultadoIndividual['caminho_completo'],
                'tamanho_bytes' => $resultadoIndividual['tamanho'],
                'timestamp' => date('Y-m-d H:i:s'),
                'operacao' => $operacao
            ];
            
        } catch (Exception $e) {
            $this->log("ERRO ao salvar JSON: " . $e->getMessage(), 'ERROR');
            
            // Salva dados em arquivo de erro para recuperação
            $this->salvarDadosErro($dados, $associadoId, $e->getMessage());
            
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Prepara dados completos e estruturados para JSON
     */
    private function prepararDadosCompletos($dados, $associadoId, $operacao) {
        
        $timestamp = date('Y-m-d H:i:s');
        
        return [
            // === METADADOS ===
            'meta' => [
                'id_associado' => (int) $associadoId,
                'operacao' => $operacao,
                'timestamp_operacao' => $timestamp,
                'timestamp_exportacao' => $timestamp,
                'versao_sistema' => '1.0',
                'origem' => 'ASSEGO_SYSTEM',
                'hash_dados' => $this->gerarHashDados($dados)
            ],
            
            // === DADOS PESSOAIS ===
            'dados_pessoais' => [
                'nome_completo' => trim($dados['nome'] ?? ''),
                'data_nascimento' => $dados['nasc'] ?? '',
                'idade_anos' => $this->calcularIdade($dados['nasc'] ?? ''),
                'sexo' => $dados['sexo'] ?? '',
                'estado_civil' => $dados['estadoCivil'] ?? '',
                'rg' => $dados['rg'] ?? '',
                'cpf' => $dados['cpf'] ?? '',
                'cpf_numeros' => preg_replace('/[^0-9]/', '', $dados['cpf'] ?? ''),
                'telefone' => $dados['telefone'] ?? '',
                'telefone_numeros' => preg_replace('/[^0-9]/', '', $dados['telefone'] ?? ''),
                'email' => strtolower(trim($dados['email'] ?? '')),
                'escolaridade' => $dados['escolaridade'] ?? '',
                'indicado_por' => $dados['indicacao'] ?? '',
                'situacao' => $dados['situacao'] ?? '',
                'data_filiacao' => $dados['dataFiliacao'] ?? '',
                'tem_foto' => isset($_FILES['foto']) && $_FILES['foto']['size'] > 0
            ],
            
            // === DADOS MILITARES ===
            'dados_militares' => [
                'corporacao' => $dados['corporacao'] ?? '',
                'patente' => $dados['patente'] ?? '',
                'categoria' => $dados['categoria'] ?? '',
                'lotacao' => $dados['lotacao'] ?? '',
                'telefone_lotacao' => $dados['telefoneLotacao'] ?? '', // ✅ NOVO CAMPO
                'telefone_lotacao_numeros' => preg_replace('/[^0-9]/', '', $dados['telefoneLotacao'] ?? ''), // ✅ NOVO CAMPO
                'unidade' => $dados['unidade'] ?? '',
                'nivel_hierarquico' => $this->determinarNivelHierarquico($dados['patente'] ?? '')
            ],
            
            // === ENDEREÇO ===
            'endereco' => [
                'cep' => $dados['cep'] ?? '',
                'cep_limpo' => preg_replace('/[^0-9]/', '', $dados['cep'] ?? ''),
                'logradouro' => $dados['endereco'] ?? '',
                'numero' => $dados['numero'] ?? '',
                'complemento' => $dados['complemento'] ?? '',
                'bairro' => $dados['bairro'] ?? '',
                'cidade' => $dados['cidade'] ?? '',
                'endereco_completo' => $this->montarEnderecoCompleto($dados)
            ],
            
            // === DADOS FINANCEIROS ===
            'dados_financeiros' => [
                'tipo_associado_servico' => $dados['tipoAssociadoServico'] ?? '',
                'tipo_associado_categoria' => $dados['tipoAssociado'] ?? '',
                'situacao_financeira' => $dados['situacaoFinanceira'] ?? '',
                'vinculo_servidor' => $dados['vinculoServidor'] ?? '',
                'local_debito' => $dados['localDebito'] ?? '',
                
                // Dados bancários
                'dados_bancarios' => [
                    'agencia' => $dados['agencia'] ?? '',
                    'operacao' => $dados['operacao'] ?? '',
                    'conta_corrente' => $dados['contaCorrente'] ?? ''
                ],
                
                // Serviços contratados
                'servicos_contratados' => $this->processarServicos($dados),
                
                // Resumo financeiro
                'resumo_financeiro' => [
                    'valor_total_mensal' => $this->calcularValorTotal($dados),
                    'quantidade_servicos' => $this->contarServicosAtivos($dados),
                    'desconto_aplicado' => $this->calcularDescontoAplicado($dados)
                ]
            ],
            
            // === DEPENDENTES ===
            'dependentes' => $this->processarDependentes($dados),
            
            // === CONTROLE ZAPSING ===
            'controle_zapsing' => [
                'processado' => false,
                'data_primeiro_envio' => null,
                'data_ultimo_envio' => null,
                'tentativas_envio' => 0,
                'ultimo_status' => null,
                'ultimo_erro' => null,
                'prioridade' => $operacao === 'CREATE' ? 'alta' : 'normal'
            ],
            
            // === AUDITORIA ===
            'auditoria' => [
                'ip_origem' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'tamanho_dados_bytes' => 0, // Será preenchido depois
                'checksum' => null // Será preenchido depois
            ]
        ];
    }
    
    /**
     * Processa os serviços contratados
     */
    private function processarServicos($dados) {
        $servicos = [];
        
        // Serviço Social (sempre obrigatório)
        $servicos['social'] = [
            'id_servico' => 1,
            'nome' => 'Serviço Social',
            'contratado' => true,
            'obrigatorio' => true,
            'valor_base' => $this->obterValorBaseServico(1),
            'percentual_aplicado' => floatval($dados['percentualAplicadoSocial'] ?? 0),
            'valor_final' => floatval($dados['valorSocial'] ?? 0),
            'valor_final_formatado' => 'R$ ' . number_format(floatval($dados['valorSocial'] ?? 0), 2, ',', '.')
        ];
        
        // Serviço Jurídico (opcional)
        $juridicoContratado = isset($dados['servicoJuridico']) && $dados['servicoJuridico'];
        $servicos['juridico'] = [
            'id_servico' => 2,
            'nome' => 'Serviço Jurídico',
            'contratado' => $juridicoContratado,
            'obrigatorio' => false,
            'valor_base' => $this->obterValorBaseServico(2),
            'percentual_aplicado' => $juridicoContratado ? floatval($dados['percentualAplicadoJuridico'] ?? 0) : 0,
            'valor_final' => $juridicoContratado ? floatval($dados['valorJuridico'] ?? 0) : 0,
            'valor_final_formatado' => 'R$ ' . number_format($juridicoContratado ? floatval($dados['valorJuridico'] ?? 0) : 0, 2, ',', '.')
        ];
        
        return $servicos;
    }
    
    /**
     * Processa dependentes - ✅ FUNÇÃO MODIFICADA PARA CÔNJUGE TER TELEFONE E DATA
     */
    private function processarDependentes($dados) {
        $dependentes = [];
        
        if (isset($dados['dependentes']) && is_array($dados['dependentes'])) {
            foreach ($dados['dependentes'] as $index => $dep) {
                if (!empty($dep['nome'])) {
                    $dependente = [
                        'ordem' => $index + 1,
                        'nome' => trim($dep['nome']),
                        'parentesco' => $dep['parentesco'] ?? '',
                        'sexo' => $dep['sexo'] ?? ''
                    ];
                    
                    // ✅ NOVO: Para cônjuge, captura TELEFONE E DATA. Para outros, só data
                    if ($dep['parentesco'] === 'Cônjuge') {
                        // Cônjuge tem AMBOS os campos
                        $dependente['telefone'] = $dep['telefone'] ?? '';
                        $dependente['telefone_numeros'] = preg_replace('/[^0-9]/', '', $dep['telefone'] ?? '');
                        $dependente['data_nascimento'] = $dep['data_nascimento'] ?? '';
                        $dependente['idade_anos'] = $this->calcularIdade($dep['data_nascimento'] ?? '');
                        $dependente['eh_menor_idade'] = $this->calcularIdade($dep['data_nascimento'] ?? '') < 18;
                        $dependente['tipo_campo'] = 'telefone_e_data';
                    } else {
                        // Outros parentes só têm data de nascimento
                        $dependente['data_nascimento'] = $dep['data_nascimento'] ?? '';
                        $dependente['idade_anos'] = $this->calcularIdade($dep['data_nascimento'] ?? '');
                        $dependente['eh_menor_idade'] = $this->calcularIdade($dep['data_nascimento'] ?? '') < 18;
                        $dependente['telefone'] = null;
                        $dependente['telefone_numeros'] = null;
                        $dependente['tipo_campo'] = 'data_nascimento';
                    }
                    
                    $dependentes[] = $dependente;
                }
            }
        }
        
        return $dependentes;
    }
    
    /**
     * Salva arquivo JSON individual
     */
    private function salvarArquivoIndividual($dadosJson, $associadoId) {
        $nomeArquivo = $this->gerarNomeArquivo($associadoId);
        $caminhoCompleto = $this->jsonDirectory . 'individual/' . $nomeArquivo;
        
        // Adiciona dados de auditoria
        $jsonString = json_encode($dadosJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $dadosJson['auditoria']['tamanho_dados_bytes'] = strlen($jsonString);
        $dadosJson['auditoria']['checksum'] = md5($jsonString);
        
        // Regenera JSON com dados de auditoria completos
        $jsonString = json_encode($dadosJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($caminhoCompleto, $jsonString) === false) {
            throw new Exception("Falha ao escrever arquivo JSON individual");
        }
        
        return [
            'nome_arquivo' => $nomeArquivo,
            'caminho_completo' => $caminhoCompleto,
            'tamanho' => strlen($jsonString)
        ];
    }
    
    /**
     * Adiciona dados ao arquivo consolidado
     */
    private function adicionarAoConsolidado($dadosJson) {
        $arquivoConsolidado = $this->jsonDirectory . 'consolidado/associados_' . date('Y-m') . '.json';
        
        // Carrega arquivo existente ou cria novo
        if (file_exists($arquivoConsolidado)) {
            $conteudo = file_get_contents($arquivoConsolidado);
            $dados = json_decode($conteudo, true) ?? [];
        } else {
            $dados = [
                'meta' => [
                    'mes_referencia' => date('Y-m'),
                    'criado_em' => date('Y-m-d H:i:s'),
                    'total_registros' => 0
                ],
                'associados' => []
            ];
        }
        
        // Adiciona novo registro
        $dados['associados'][] = $dadosJson;
        $dados['meta']['total_registros'] = count($dados['associados']);
        $dados['meta']['ultima_atualizacao'] = date('Y-m-d H:i:s');
        
        // Salva arquivo atualizado
        $jsonString = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($arquivoConsolidado, $jsonString);
    }
    
    /**
     * Salva dados em caso de erro para recuperação posterior
     */
    private function salvarDadosErro($dados, $associadoId, $erro) {
        $arquivoErro = $this->jsonDirectory . 'errors/erro_' . $associadoId . '_' . date('Y-m-d_H-i-s') . '.json';
        
        $dadosErro = [
            'timestamp' => date('Y-m-d H:i:s'),
            'associado_id' => $associadoId,
            'erro' => $erro,
            'dados_originais' => $dados,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];
        
        file_put_contents($arquivoErro, json_encode($dadosErro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Funções auxiliares
     */
    private function calcularIdade($dataNascimento) {
        if (empty($dataNascimento)) return null;
        
        $nascimento = new DateTime($dataNascimento);
        $hoje = new DateTime();
        return $hoje->diff($nascimento)->y;
    }
    
    private function calcularValorTotal($dados) {
        $total = floatval($dados['valorSocial'] ?? 0);
        if (isset($dados['servicoJuridico'])) {
            $total += floatval($dados['valorJuridico'] ?? 0);
        }
        return $total;
    }
    
    private function contarServicosAtivos($dados) {
        $count = 1; // Social sempre ativo
        if (isset($dados['servicoJuridico'])) $count++;
        return $count;
    }
    
    private function calcularDescontoAplicado($dados) {
        $valorBaseSocial = $this->obterValorBaseServico(1);
        $valorFinalSocial = floatval($dados['valorSocial'] ?? 0);
        $percentualDesconto = (($valorBaseSocial - $valorFinalSocial) / $valorBaseSocial) * 100;
        return max(0, $percentualDesconto);
    }
    
    private function obterValorBaseServico($servicoId) {
        $valores = [1 => 173.10, 2 => 43.28];
        return $valores[$servicoId] ?? 0;
    }
    
    private function determinarNivelHierarquico($patente) {
        $praças = ['Soldado', 'Cabo', '3º Sargento', '2º Sargento', '1º Sargento', 'Subtenente'];
        $oficiais = ['2º Tenente', '1º Tenente', 'Capitão', 'Major', 'Tenente-Coronel', 'Coronel'];
        
        if (in_array($patente, $praças)) return 'Praça';
        if (in_array($patente, $oficiais)) return 'Oficial';
        
        return 'Indefinido';
    }
    
    private function montarEnderecoCompleto($dados) {
        $partes = array_filter([
            $dados['endereco'] ?? '',
            $dados['numero'] ?? '',
            $dados['complemento'] ?? '',
            $dados['bairro'] ?? '',
            $dados['cidade'] ?? ''
        ]);
        
        return implode(', ', $partes);
    }
    
    private function gerarHashDados($dados) {
        return md5(serialize($dados) . date('Y-m-d'));
    }
    
    private function gerarNomeArquivo($associadoId) {
        return sprintf(
            'associado_%06d_%s.json',
            $associadoId,
            date('Y-m-d_H-i-s')
        );
    }
    
    private function atualizarEstatisticas($operacao) {
        $arquivo = $this->jsonDirectory . 'estatisticas.json';
        
        if (file_exists($arquivo)) {
            $stats = json_decode(file_get_contents($arquivo), true);
        } else {
            $stats = ['total_criados' => 0, 'total_atualizados' => 0];
        }
        
        if ($operacao === 'CREATE') {
            $stats['total_criados']++;
        } else {
            $stats['total_atualizados']++;
        }
        
        $stats['ultima_operacao'] = date('Y-m-d H:i:s');
        
        file_put_contents($arquivo, json_encode($stats, JSON_PRETTY_PRINT));
    }
    
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Métodos públicos utilitários
     */
    
    public function listarArquivosJson() {
        $arquivos = [];
        $pasta = $this->jsonDirectory . 'individual/';
        
        if (is_dir($pasta)) {
            $files = scandir($pasta);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                    $arquivos[] = [
                        'nome' => $file,
                        'tamanho' => filesize($pasta . $file),
                        'criado_em' => date('Y-m-d H:i:s', filemtime($pasta . $file))
                    ];
                }
            }
        }
        
        return $arquivos;
    }
    
    //oi
    public function obterEstatisticas() {
        $arquivo = $this->jsonDirectory . 'estatisticas.json';
        
        if (file_exists($arquivo)) {
            return json_decode(file_get_contents($arquivo), true);
        }
        
        return ['total_criados' => 0, 'total_atualizados' => 0];
    }
}
?>