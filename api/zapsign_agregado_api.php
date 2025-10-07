<?php
/**
 * Integração com API ZapSign - Sócios Agregados
 * api/zapsign_agregado_api.php
 */

class ZapSignAgregadoAPI {
    
    private $apiUrl = 'https://sandbox.api.zapsign.com.br/api/v1/models/create-doc/';
    private $bearerToken = API_KEY;
    private $templateId = '1ac5cd7e-7a28-47b3-98b1-038a6e11f001'; // Template específico para agregados
    private $checkboxMarcado = 'X';
    private $checkboxDesmarcado = '';
    
    /**
     * Envia dados do agregado para ZapSign
     * @param array $dadosCompletos - Dados retornados da função prepararDadosCompletos()
     * @return array - Resultado da API
     */
    public function enviarParaZapSign($dadosCompletos) {
        try {
            error_log("=== INICIANDO ENVIO AGREGADO PARA ZAPSIGN ===");
            error_log("Dados recebidos: " . json_encode(array_keys($dadosCompletos), JSON_UNESCAPED_UNICODE));
            
            // Extrai dados principais
            $dadosAgregado = $dadosCompletos['dados_agregado'] ?? [];
            $socioTitular = $dadosCompletos['socio_titular'] ?? [];
            $endereco = $dadosCompletos['endereco'] ?? [];
            $dadosBancarios = $dadosCompletos['dados_bancarios'] ?? [];
            $dependentes = $dadosCompletos['dependentes'] ?? [];
            
            error_log("Nome do agregado: " . ($dadosAgregado['nome_completo'] ?? 'N/A'));
            error_log("Nome do titular: " . ($socioTitular['nome_completo'] ?? 'N/A'));
            error_log("Email do agregado: " . ($dadosAgregado['email'] ?? 'N/A'));
            
            // Valida campos obrigatórios para ZapSign
            if (empty($dadosAgregado['nome_completo'])) {
                throw new Exception("Nome do agregado é obrigatório para ZapSign");
            }
            
            if (empty($socioTitular['nome_completo'])) {
                throw new Exception("Nome do sócio titular é obrigatório para ZapSign");
            }
            
            if (empty($dadosAgregado['email']) && empty($socioTitular['email'])) {
                throw new Exception("Email do agregado ou do titular é obrigatório para ZapSign");
            }
            
            // Monta JSON para ZapSign
            $jsonZapSign = $this->montarJsonZapSign($dadosAgregado, $socioTitular, $endereco, $dadosBancarios, $dependentes);
            
            error_log("JSON montado para ZapSign (primeiros 500 chars): " . substr(json_encode($jsonZapSign, JSON_UNESCAPED_UNICODE), 0, 500));
            
            // Faz requisição para API
            $resultado = $this->fazerRequisicaoAPI($jsonZapSign);
            
            // Atualiza controle no banco se necessário
            if ($resultado['sucesso']) {
                $this->atualizarControleZapSign($dadosCompletos['meta']['id_agregado'], $resultado);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("ERRO ao enviar agregado para ZapSign: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'detalhes' => null
            ];
        }
    }
    
    /**
     * Monta o JSON no formato da API ZapSign para agregados
     */
    private function montarJsonZapSign($dadosAgregado, $socioTitular, $endereco, $dadosBancarios, $dependentes) {
        
        // Processa telefone do agregado
        $telefoneAgregado = $dadosAgregado['telefone_numeros'] ?? $dadosAgregado['telefone'] ?? '';
        $telefoneAgregado = preg_replace('/[^0-9]/', '', $telefoneAgregado);
        
        // Remove código do país se tiver
        if (strlen($telefoneAgregado) > 11 && substr($telefoneAgregado, 0, 2) == '55') {
            $telefoneAgregado = substr($telefoneAgregado, 2);
        }
        
        // Processa telefone do titular
        $telefoneTitular = $socioTitular['telefone_numeros'] ?? $socioTitular['telefone'] ?? '';
        $telefoneTitular = preg_replace('/[^0-9]/', '', $telefoneTitular);
        
        if (strlen($telefoneTitular) > 11 && substr($telefoneTitular, 0, 2) == '55') {
            $telefoneTitular = substr($telefoneTitular, 2);
        }
        
        // Busca cônjuge nos dependentes
        $conjuge = null;
        $filhos = [];
        
        foreach ($dependentes as $dep) {
            if (in_array($dep['tipo'] ?? '', ['esposa_companheira', 'marido_companheiro'])) {
                $conjuge = $dep;
            } else {
                $filhos[] = $dep;
            }
        }
        
        // Define email para assinatura (prioriza agregado, depois titular)
        $emailAssinatura = $dadosAgregado['email'] ?: $socioTitular['email'];
        
        // Monta array de dados para substituição no template
        $dadosTemplate = [
            // === DADOS DO AGREGADO ===
            [
                "de" => "{{nome_agregado}}",
                "para" => $dadosAgregado['nome_completo'] ?? ''
            ],
            [
                "de" => "{{data_nascimento}}",
                "para" => $this->formatarData($dadosAgregado['data_nascimento'] ?? '')
            ],
            [
                "de" => "{{telefone}}",
                "para" => $this->formatarTelefone($dadosAgregado['telefone'] ?? '')
            ],
            [
                "de" => "{{celular}}",
                "para" => $this->formatarTelefone($dadosAgregado['celular'] ?? '')
            ],
            [
                "de" => "{{email_agregado}}",
                "para" => $dadosAgregado['email'] ?? ''
            ],
            [
                "de" => "{{cpf}}",
                "para" => $this->formatarCPF($dadosAgregado['cpf'] ?? '')
            ],
            [
                "de" => "{{documento_identificacao}}",
                "para" => $dadosAgregado['documento'] ?? ''
            ],
            
            // === ESTADO CIVIL (CHECKBOXES) ===
            [
                "de" => match (strtolower($dadosAgregado['estado_civil'] ?? '')) {
                    'solteiro' => '{{solteiro}}',
                    'casado' => '{{casado}}',
                    'divorciado' => '{{divorciado}}',
                    'separado_judicial' => '{{separado}}',
                    'viuvo' => '{{viuvo}}',
                    'outro' => '{{outro}}',
                    default => '{{solteiro}}' // fallback
                },
                "para" => 'X'
            ],
            
            // === DADOS DO SÓCIO TITULAR ===
            [
                "de" => "{{nome_associado}}",
                "para" => $socioTitular['nome_completo'] ?? ''
            ],
            [
                "de" => "{{telefone_associado}}",
                "para" => $this->formatarTelefone($socioTitular['telefone'] ?? '')
            ],
            [
                "de" => "{{cpf_associado}}",
                "para" => $this->formatarCPF($socioTitular['cpf'] ?? '')
            ],
            [
                "de" => "{{email_associado}}",
                "para" => $socioTitular['email'] ?? ''
            ],
            
            // === ENDEREÇO ===
            [
                "de" => "{{rua}}",
                "para" => $endereco['logradouro'] ?? ''
            ],
            [
                "de" => "{{num}}",
                "para" => $endereco['numero'] ?? ''
            ],
            [
                "de" => "{{bairro}}",
                "para" => $endereco['bairro'] ?? ''
            ],
            [
                "de" => "{{cep}}",
                "para" => $endereco['cep'] ?? ''
            ],
            [
                "de" => "{{cidade}}",
                "para" => $endereco['cidade'] ?? ''
            ],
            [
                "de" => "{{estado}}",
                "para" => $endereco['estado'] ?? 'GO'
            ],
            
            // === DADOS BANCÁRIOS ===
            [
                "de" => "{{agencia}}",
                "para" => $dadosBancarios['agencia'] ?? ''
            ],
            [
                "de" => "{{conta_corrente}}",
                "para" => $dadosBancarios['conta_corrente'] ?? ''
            ],
            
            // === BANCO (CHECKBOXES) ===
            [
                "de" => match (strtolower($dadosBancarios['banco'] ?? '')) {
                    'itau' => '{{banco_itau}}',
                    'caixa' => '{{banco_caixa}}',
                    default => '{{banco_outro}}'
                },
                "para" => 'X'
            ]
        ];
        
        // === DADOS DO CÔNJUGE ===
        if ($conjuge) {
            $dadosTemplate[] = [
                "de" => "{{nome_esposa}}",
                "para" => $conjuge['nome'] ?? ''
            ];
            $dadosTemplate[] = [
                "de" => "{{cpf_esposa}}",
                "para" => $this->formatarCPF($conjuge['cpf'] ?? '')
            ];
            $dadosTemplate[] = [
                "de" => "{{telefone_esposa}}",
                "para" => $this->formatarTelefone($conjuge['telefone'] ?? '')
            ];
        } else {
            // Campos vazios se não há cônjuge
            $dadosTemplate[] = ["de" => "{{nome_esposa}}", "para" => ""];
            $dadosTemplate[] = ["de" => "{{cpf_esposa}}", "para" => ""];
            $dadosTemplate[] = ["de" => "{{telefone_esposa}}", "para" => ""];
        }
        
        // === DEPENDENTES (FILHOS) ===
        // Adiciona até 6 dependentes
        for ($i = 1; $i <= 6; $i++) {
            $index = $i - 1;
            $filho = $filhos[$index] ?? null;
            
            $dadosTemplate[] = [
                "de" => "{{nome_dependente}}",
                "para" => $filho['nome'] ?? ''
            ];
            
            $dadosTemplate[] = [
                "de" => "{{cpf_dependente}}",
                "para" => $this->formatarCPF($filho['cpf'] ?? '')
            ];
            
            $dadosTemplate[] = [
                "de" => "{{nasc_dependente}}",
                "para" => $this->formatarData($filho['data_nascimento'] ?? '')
            ];
        }
        
        // Monta JSON final
        return [
            "template_id" => $this->templateId,
            "signer_name" => $dadosAgregado['nome_completo'] ?? '',
            "signer_email" => $emailAssinatura,
            "signer_phone_country" => "55",
            "signer_phone_number" => $telefoneAgregado,
            "lang" => "pt-br",
            "data" => $dadosTemplate
        ];
    }
    
    /**
     * Faz a requisição para a API ZapSign
     */
    private function fazerRequisicaoAPI($jsonData) {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($jsonData, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->bearerToken,
                'Content-Type: application/json'
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            throw new Exception("Erro cURL: " . $error);
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("✓ ZapSign API Agregado - Sucesso: HTTP {$httpCode}");
            return [
                'sucesso' => true,
                'http_code' => $httpCode,
                'resposta' => $responseData,
                'documento_id' => $responseData['id'] ?? null,
                'link_assinatura' => $responseData['sign_url'] ?? null
            ];
        } else {
            error_log("✗ ZapSign API Agregado - Erro: HTTP {$httpCode} - " . $response);
            return [
                'sucesso' => false,
                'http_code' => $httpCode,
                'erro' => $responseData['message'] ?? 'Erro desconhecido',
                'resposta_completa' => $responseData
            ];
        }
    }
    
    /**
     * Atualiza controle ZapSign no banco
     */
    private function atualizarControleZapSign($agregadoId, $resultado) {
        try {
            error_log("Atualizando controle ZapSign para agregado {$agregadoId}");
            
            // Atualiza na tabela Socios_Agregados
            $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            $stmt = $db->prepare("
                UPDATE Socios_Agregados 
                SET observacoes = CONCAT(COALESCE(observacoes, ''), '\n--- ZAPSIGN ---\n',
                    'Documento ID: ', ?, '\n',
                    'Link: ', ?, '\n',
                    'Enviado em: ', NOW())
                WHERE id = ?
            ");
            $stmt->execute([
                $resultado['documento_id'],
                $resultado['link_assinatura'],
                $agregadoId
            ]);
            
            error_log("✓ Dados ZapSign salvos para agregado {$agregadoId}");
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar controle ZapSign do agregado: " . $e->getMessage());
        }
    }
    
    // ========================================
    // FUNÇÕES AUXILIARES DE FORMATAÇÃO
    // ========================================
    
    private function formatarCPF($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) == 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        }
        return $cpf;
    }
    
    private function formatarTelefone($telefone) {
        $telefone = preg_replace('/[^0-9]/', '', $telefone);
        if (strlen($telefone) == 11) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
        } elseif (strlen($telefone) == 10) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
        }
        return $telefone;
    }
    
    private function formatarData($data) {
        if (empty($data)) return '';
        
        // Se já está no formato brasileiro
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
            return $data;
        }
        
        // Se está no formato americano ou ISO
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
            $timestamp = strtotime($data);
            return date('d/m/Y', $timestamp);
        }
        
        return $data;
    }
}

/**
 * Função principal para chamar do criar_agregado.php
 */
function enviarAgregadoParaZapSign($dadosCompletos) {
    $zapSign = new ZapSignAgregadoAPI();
    return $zapSign->enviarParaZapSign($dadosCompletos);
}

/**
 * ===============================================
 * DOCUMENTAÇÃO DA API ZAPSIGN AGREGADOS
 * ===============================================
 * 
 * TEMPLATE ID: 192728fe-fe4f-4b48-9566-4bf4223ed551
 * 
 * CAMPOS MAPEADOS:
 * 
 * DADOS DO AGREGADO:
 * - {{nome_agregado}} - Nome completo do sócio agregado
 * - {{data_nascimento}} - Data de nascimento (dd/mm/aaaa)
 * - {{telefone}} - Telefone formatado
 * - {{celular}} - Celular formatado
 * - {{email}} - Email do agregado
 * - {{cpf}} - CPF formatado
 * - {{documento_identificacao}} - RG, CNH, etc
 * 
 * ESTADO CIVIL (CHECKBOXES):
 * - {{solteiro}}, {{casado}}, {{divorciado}}, {{separado}}, {{viuvo}}, {{outro}}
 * 
 * DADOS DO SÓCIO TITULAR:
 * - {{nome_associado}} - Nome do sócio titular
 * - {{telefone_associado}} - Telefone do titular
 * - {{cpf_associado}} - CPF do titular
 * - {{email_associado}} - Email do titular
 * 
 * ENDEREÇO:
 * - {{rua}} - Logradouro
 * - {{num}} - Número
 * - {{bairro}} - Bairro
 * - {{cep}} - CEP
 * - {{cidade}} - Cidade
 * - {{estado}} - Estado (sigla)
 * 
 * DADOS BANCÁRIOS:
 * - {{agencia}} - Agência
 * - {{conta_corrente}} - Conta corrente
 * - {{banco_itau}}, {{banco_caixa}}, {{banco_outro}} - Checkboxes de banco
 * 
 * DEPENDENTES:
 * - {{nome_esposa}} - Nome do cônjuge
 * - {{cpf_esposa}} - CPF do cônjuge  
 * - {{telefone_esposa}} - Telefone do cônjuge
 * - {{nome_dependente}} - Nome dos filhos
 * - {{cpf_dependente}} - CPF dos filhos
 * - {{nasc_dependente}} - Data nascimento dos filhos
 * 
 * USO:
 * $resultado = enviarAgregadoParaZapSign($dadosCompletos);
 * 
 * RETORNO:
 * [
 *   'sucesso' => true/false,
 *   'documento_id' => 'xxx',
 *   'link_assinatura' => 'https://...',
 *   'erro' => null/string
 * ]
 */
?>