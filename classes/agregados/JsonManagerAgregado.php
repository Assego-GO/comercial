<?php
/**
 * Gerenciador de arquivos JSON para Sócios Agregados
 * classes/agregado/JsonManagerAgregado.php
 * 
 * Salva dados dos sócios agregados em formato JSON para integração com ZapSign
 */

class JsonManagerAgregado {
    
    private $jsonDirectory;
    private $logFile;
    
    public function __construct() {
        // Define diretórios (ajustado para classes/agregado/)
        $this->jsonDirectory = dirname(dirname(__DIR__)) . '/data/json_agregados/';
        $this->logFile = dirname(dirname(__DIR__)) . '/logs/json_agregados.log';
        
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
            $htaccessContent = "# Protege arquivos JSON dos agregados\n";
            $htaccessContent .= "Order Deny,Allow\n";
            $htaccessContent .= "Deny from all\n";
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
     * Salva os dados do sócio agregado em JSON
     * 
     * @param array $dados Dados do formulário
     * @param int $agregadoId ID do agregado no banco
     * @param string $operacao 'CREATE' ou 'UPDATE'
     * @return array Resultado da operação
     */
    public function salvarAgregadoJson($dados, $agregadoId, $operacao = 'CREATE') {
        try {
            $this->log("Iniciando salvamento JSON - Agregado ID: {$agregadoId}, Operação: {$operacao}");
            
            // Valida dados essenciais
            if (empty($dados['nome']) || empty($agregadoId)) {
                throw new Exception("Dados insuficientes para gerar JSON do agregado");
            }
            
            // Prepara dados estruturados
            $dadosJson = $this->prepararDadosCompletos($dados, $agregadoId, $operacao);
            
            // Salva arquivo individual
            $resultadoIndividual = $this->salvarArquivoIndividual($dadosJson, $agregadoId);
            
            // Adiciona ao arquivo consolidado
            $this->adicionarAoConsolidado($dadosJson);
            
            // Atualiza estatísticas
            $this->atualizarEstatisticas($operacao);
            
            $this->log("JSON do agregado salvo com sucesso - Arquivo: " . $resultadoIndividual['nome_arquivo']);
            
            return [
                'sucesso' => true,
                'arquivo_individual' => $resultadoIndividual['nome_arquivo'],
                'caminho_completo' => $resultadoIndividual['caminho_completo'],
                'tamanho_bytes' => $resultadoIndividual['tamanho'],
                'timestamp' => date('Y-m-d H:i:s'),
                'operacao' => $operacao
            ];
            
        } catch (Exception $e) {
            $this->log("ERRO ao salvar JSON do agregado: " . $e->getMessage(), 'ERROR');
            
            // Salva dados em arquivo de erro para recuperação
            $this->salvarDadosErro($dados, $agregadoId, $e->getMessage());
            
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
    private function prepararDadosCompletos($dados, $agregadoId, $operacao) {
        
        $timestamp = date('Y-m-d H:i:s');
        
        return [
            // === METADADOS ===
            'meta' => [
                'id_agregado' => (int) $agregadoId,
                'operacao' => $operacao,
                'timestamp_operacao' => $timestamp,
                'timestamp_exportacao' => $timestamp,
                'versao_sistema' => '1.0',
                'origem' => 'ASSEGO_AGREGADOS',
                'tipo_cadastro' => 'socio_agregado',
                'hash_dados' => $this->gerarHashDados($dados)
            ],
            
            // === DADOS PESSOAIS DO AGREGADO ===
            'dados_agregado' => [
                'nome_completo' => trim($dados['nome'] ?? ''),
                'data_nascimento' => $dados['dataNascimento'] ?? '',
                'idade_anos' => $this->calcularIdade($dados['dataNascimento'] ?? ''),
                'telefone' => $dados['telefone'] ?? '',
                'telefone_numeros' => preg_replace('/[^0-9]/', '', $dados['telefone'] ?? ''),
                'celular' => $dados['celular'] ?? '',
                'celular_numeros' => preg_replace('/[^0-9]/', '', $dados['celular'] ?? ''),
                'email' => strtolower(trim($dados['email'] ?? '')),
                'cpf' => $dados['cpf'] ?? '',
                'cpf_numeros' => preg_replace('/[^0-9]/', '', $dados['cpf'] ?? ''),
                'documento' => $dados['documento'] ?? '',
                'estado_civil' => $dados['estadoCivil'] ?? '',
                'data_filiacao' => $dados['dataFiliacao'] ?? ''
            ],
            
            // === DADOS DO SÓCIO TITULAR ===
            'socio_titular' => [
                'nome_completo' => trim($dados['socioTitularNome'] ?? ''),
                'telefone' => $dados['socioTitularFone'] ?? '',
                'telefone_numeros' => preg_replace('/[^0-9]/', '', $dados['socioTitularFone'] ?? ''),
                'cpf' => $dados['socioTitularCpf'] ?? '',
                'cpf_numeros' => preg_replace('/[^0-9]/', '', $dados['socioTitularCpf'] ?? ''),
                'email' => strtolower(trim($dados['socioTitularEmail'] ?? ''))
            ],
            
            // === ENDEREÇO ===
            'endereco' => [
                'cep' => $dados['cep'] ?? '',
                'cep_numeros' => preg_replace('/[^0-9]/', '', $dados['cep'] ?? ''),
                'logradouro' => $dados['endereco'] ?? '',
                'numero' => $dados['numero'] ?? '',
                'bairro' => $dados['bairro'] ?? '',
                'cidade' => $dados['cidade'] ?? '',
                'estado' => $dados['estado'] ?? 'GO',
                'endereco_completo' => $this->montarEnderecoCompleto($dados)
            ],
            
            // === DADOS BANCÁRIOS ===
            'dados_bancarios' => [
                'banco' => $dados['banco'] ?? '',
                'banco_nome_exibicao' => $this->formatarNomeBanco($dados['banco'] ?? ''),
                'banco_outro_nome' => ($dados['banco'] === 'outro') ? ($dados['bancoOutroNome'] ?? '') : null,
                'agencia' => $dados['agencia'] ?? '',
                'conta_corrente' => $dados['contaCorrente'] ?? '',
                'valor_contribuicao' => 86.55,
                'percentual_desconto' => 50.00
            ],
            
            // === DEPENDENTES ===
            'dependentes' => $this->processarDependentes($dados),
            
            // === CONTROLE ZAPSIGN ===
            'controle_zapsign' => [
                'processado' => false,
                'template_tipo' => 'socio_agregado',
                'data_primeiro_envio' => null,
                'data_ultimo_envio' => null,
                'tentativas_envio' => 0,
                'ultimo_status' => null,
                'ultimo_erro' => null,
                'documento_id' => null,
                'link_assinatura' => null,
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
     * Método público para obter dados completos estruturados
     * Para uso em integrações externas (como ZapSign)
     */
    public function obterDadosCompletos($dados, $agregadoId, $operacao = 'CREATE') {
        return $this->prepararDadosCompletos($dados, $agregadoId, $operacao);
    }
    
    /**
     * Processa dependentes específicos para agregados
     */
    private function processarDependentes($dados) {
        $dependentes = [];
        
        if (isset($dados['dependentes']) && is_array($dados['dependentes'])) {
            foreach ($dados['dependentes'] as $index => $dep) {
                if (!empty($dep['tipo']) && !empty($dep['data_nascimento'])) {
                    $dependente = [
                        'ordem' => $index + 1,
                        'tipo' => trim($dep['tipo']),
                        'tipo_formatado' => $this->formatarTipoDependente($dep['tipo']),
                        'data_nascimento' => trim($dep['data_nascimento']),
                        'idade_anos' => $this->calcularIdade($dep['data_nascimento']),
                        'eh_menor_idade' => $this->calcularIdade($dep['data_nascimento']) < 18,
                        'eh_conjuge' => in_array($dep['tipo'], ['esposa_companheira', 'marido_companheiro'])
                    ];
                    
                    // CPF do dependente (se fornecido)
                    if (!empty($dep['cpf'])) {
                        $dependente['cpf'] = trim($dep['cpf']);
                        $dependente['cpf_numeros'] = preg_replace('/[^0-9]/', '', $dep['cpf']);
                    }
                    
                    // Telefone do dependente (principalmente cônjuges)
                    if (!empty($dep['telefone'])) {
                        $dependente['telefone'] = trim($dep['telefone']);
                        $dependente['telefone_numeros'] = preg_replace('/[^0-9]/', '', $dep['telefone']);
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
    private function salvarArquivoIndividual($dadosJson, $agregadoId) {
        $nomeArquivo = $this->gerarNomeArquivo($agregadoId);
        $caminhoCompleto = $this->jsonDirectory . 'individual/' . $nomeArquivo;
        
        // Adiciona dados de auditoria
        $jsonString = json_encode($dadosJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $dadosJson['auditoria']['tamanho_dados_bytes'] = strlen($jsonString);
        $dadosJson['auditoria']['checksum'] = md5($jsonString);
        
        // Regenera JSON com dados de auditoria completos
        $jsonString = json_encode($dadosJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($caminhoCompleto, $jsonString) === false) {
            throw new Exception("Falha ao escrever arquivo JSON do agregado");
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
        $arquivoConsolidado = $this->jsonDirectory . 'consolidado/agregados_' . date('Y-m') . '.json';
        
        // Carrega arquivo existente ou cria novo
        if (file_exists($arquivoConsolidado)) {
            $conteudo = file_get_contents($arquivoConsolidado);
            $dados = json_decode($conteudo, true) ?? [];
        } else {
            $dados = [
                'meta' => [
                    'mes_referencia' => date('Y-m'),
                    'criado_em' => date('Y-m-d H:i:s'),
                    'total_registros' => 0,
                    'tipo' => 'socios_agregados'
                ],
                'agregados' => []
            ];
        }
        
        // Adiciona novo registro
        $dados['agregados'][] = $dadosJson;
        $dados['meta']['total_registros'] = count($dados['agregados']);
        $dados['meta']['ultima_atualizacao'] = date('Y-m-d H:i:s');
        
        // Salva arquivo atualizado
        $jsonString = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($arquivoConsolidado, $jsonString);
    }
    
    /**
     * Salva dados em caso de erro
     */
    private function salvarDadosErro($dados, $agregadoId, $erro) {
        $arquivoErro = $this->jsonDirectory . 'errors/erro_agregado_' . $agregadoId . '_' . date('Y-m-d_H-i-s') . '.json';
        
        $dadosErro = [
            'timestamp' => date('Y-m-d H:i:s'),
            'agregado_id' => $agregadoId,
            'erro' => $erro,
            'dados_originais' => $dados,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];
        
        file_put_contents($arquivoErro, json_encode($dadosErro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    // =====================================================
    // FUNÇÕES AUXILIARES
    // =====================================================
    
    private function calcularIdade($dataNascimento) {
        if (empty($dataNascimento)) return null;
        
        try {
            $nascimento = new DateTime($dataNascimento);
            $hoje = new DateTime();
            return $hoje->diff($nascimento)->y;
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function formatarNomeBanco($banco) {
        $bancos = [
            'itau' => 'Itaú',
            'caixa' => 'Caixa Econômica Federal',
            'outro' => 'Outro'
        ];
        
        return $bancos[$banco] ?? $banco;
    }
    
    private function formatarTipoDependente($tipo) {
        $tipos = [
            'esposa_companheira' => 'Esposa/Companheira',
            'marido_companheiro' => 'Marido/Companheiro',
            'filho_menor_18' => 'Filho menor de 18 anos',
            'filha_menor_18' => 'Filha menor de 18 anos',
            'filho_estudante' => 'Filho estudante até 21 anos',
            'filha_estudante' => 'Filha estudante até 21 anos'
        ];
        
        return $tipos[$tipo] ?? $tipo;
    }
    
    private function montarEnderecoCompleto($dados) {
        $partes = array_filter([
            $dados['endereco'] ?? '',
            $dados['numero'] ?? '',
            $dados['bairro'] ?? '',
            $dados['cidade'] ?? '',
            $dados['estado'] ?? ''
        ]);
        
        return implode(', ', $partes);
    }
    
    private function gerarHashDados($dados) {
        return md5(serialize($dados) . date('Y-m-d'));
    }
    
    private function gerarNomeArquivo($agregadoId) {
        return sprintf(
            'agregado_%06d_%s.json',
            $agregadoId,
            date('Y-m-d_H-i-s')
        );
    }
    
    private function atualizarEstatisticas($operacao) {
        $arquivo = $this->jsonDirectory . 'estatisticas_agregados.json';
        
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
        $logLine = "[{$timestamp}] [{$level}] AGREGADOS - {$message}" . PHP_EOL;
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
    
    public function obterEstatisticas() {
        $arquivo = $this->jsonDirectory . 'estatisticas_agregados.json';
        
        if (file_exists($arquivo)) {
            return json_decode(file_get_contents($arquivo), true);
        }
        
        return ['total_criados' => 0, 'total_atualizados' => 0];
    }
}
?>