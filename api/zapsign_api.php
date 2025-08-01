<?php
/**
 * Integração com API ZapSign
 * zapsign_api.php
 */

class ZapSignAPI {
    
    private $apiUrl = 'https://sandbox.api.zapsign.com.br/api/v1/models/create-doc/';
    private $bearerToken = API_KEY;
    private $templateId = '192728fe-fe4f-4b48-9566-4bf4223ed551'; // Ajuste conforme seu template
    private $checkboxMarcado = 'X';      // Valor para checkbox marcado
    private $checkboxDesmarcado = '';     // Valor para checkbox desmarcado
    
    /**
     * Envia dados para ZapSign
     * @param array $dadosCompletos - Dados retornados da função prepararDadosCompletos()
     * @return array - Resultado da API
     */
    public function enviarParaZapSign($dadosCompletos) {
        try {
            error_log("=== INICIANDO ENVIO PARA ZAPSIGN ===");
            error_log("Dados recebidos: " . json_encode(array_keys($dadosCompletos), JSON_UNESCAPED_UNICODE));
            
            // Extrai dados principais
            $dadosPessoais = $dadosCompletos['dados_pessoais'] ?? [];
            $endereco = $dadosCompletos['endereco'] ?? [];
            $dadosMilitares = $dadosCompletos['dados_militares'] ?? [];
            $dadosFinanceiros = $dadosCompletos['dados_financeiros'] ?? [];
            $dependentes = $dadosCompletos['dependentes'] ?? [];
            
            error_log("Nome do associado: " . ($dadosPessoais['nome_completo'] ?? 'N/A'));
            error_log("Email do associado: " . ($dadosPessoais['email'] ?? 'N/A'));
            error_log("Telefone do associado: " . ($dadosPessoais['telefone'] ?? 'N/A'));
            
            // Valida campos obrigatórios para ZapSign
            if (empty($dadosPessoais['nome_completo'])) {
                throw new Exception("Nome do associado é obrigatório para ZapSign");
            }
            
            if (empty($dadosPessoais['email'])) {
                throw new Exception("Email do associado é obrigatório para ZapSign");
            }
            
            // Monta JSON para ZapSign
            $jsonZapSign = $this->montarJsonZapSign($dadosPessoais, $endereco, $dadosMilitares, $dadosFinanceiros, $dependentes);
            
            error_log("JSON montado para ZapSign (primeiros 500 chars): " . substr(json_encode($jsonZapSign, JSON_UNESCAPED_UNICODE), 0, 500));
            
            // Faz requisição para API
            $resultado = $this->fazerRequisicaoAPI($jsonZapSign);
            
            // Atualiza controle no banco se necessário
            if ($resultado['sucesso']) {
                $this->atualizarControleZapSign($dadosCompletos['meta']['id_associado'], $resultado);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("ERRO ao enviar para ZapSign: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'detalhes' => null
            ];
        }
    }
    
    /**
     * Monta o JSON no formato da API ZapSign
     */
    private function montarJsonZapSign($dadosPessoais, $endereco, $dadosMilitares, $dadosFinanceiros, $dependentes) {
        
        // Processa telefone (remove +55 se existir e pega só os números)
        $telefone = $dadosPessoais['telefone_numeros'] ?? $dadosPessoais['telefone'] ?? '';
        $telefone = preg_replace('/[^0-9]/', '', $telefone);
        
        // Remove código do país se tiver
        if (strlen($telefone) > 11 && substr($telefone, 0, 2) == '55') {
            $telefone = substr($telefone, 2);
        }
        
        // Procura cônjuge nos dependentes
        $conjuge = null;
        foreach ($dependentes as $dep) {
            if (in_array(strtolower($dep['parentesco'] ?? ''), ['cônjuge', 'conjuge', 'esposa', 'esposo', 'marido'])) {
                $conjuge = $dep;
                break;
            }
        }

        $servicosContratados = $dadosFinanceiros['servicos_contratados'] ?? [];
        $juridicoContratado = $servicosContratados['juridico']['contratado'] ?? false;

        // Lógica simples
        $marcarOptou = $juridicoContratado ? $this->checkboxMarcado : $this->checkboxDesmarcado;
        $marcarNaoOptou = !$juridicoContratado ? $this->checkboxMarcado : $this->checkboxDesmarcado;
        
        // Monta array de dados para substituição no template
        $dadosTemplate = [
            [
                "de" => "{{signatário_nome_2}}",
                "para" => 'Paulo Sérgio de Sousa'
            ],
            [
                "de" => "{{nome_pessoa}}",
                "para" => $dadosPessoais['nome_completo'] ?? ''
            ],
            [
                "de" => "{{rua}}",
                "para" => $endereco['logradouro'] ?? ''
            ],
            [
                "de" => "{{numero}}",
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
                "para" => "Goiás"
            ],
            [
                "de" => "{{email}}",
                "para" => $dadosPessoais['email'] ?? ''
            ],
             [
                "de" => match (strtolower($dadosPessoais['estado_civil'] ?? '')) {
                    'solteiro(a)' => '{{solteiro}}',
                    'casado(a)' => '{{casado}}',
                    'viúvo(a)' => '{{viuvo}}',
                    'divorciado(a)' => '{{divorciado}}',
                    'separado(a)' => '{{separado}}',
                    'outro' => '{{outro}}',
                    default => '{{desconhecido}}' 
                },
                "para" => 'X'
            ],
            [
                "de" => "{{nascimento}}",
                "para" => $this->formatarData($dadosPessoais['data_nascimento'] ?? '')
            ],
            [
                "de" => "{{optou}}",
                "para" => $marcarOptou
            ],
            [
                "de" => "{{naoOptou}}",
                "para" => $marcarNaoOptou
            ],
            [
                "de" => "{{telefone}}",
                "para" => $this->formatarTelefone($telefone)
            ],
            [
                "de" => "{{cpf}}",
                "para" => $this->formatarCPF($dadosPessoais['cpf'] ?? '')
            ],
            [
                "de" => "{{rg}}",
                "para" => $dadosPessoais['rg'] ?? ''
            ],
            [
                "de" => "{{lotacao}}",
                "para" => $dadosMilitares['lotacao'] ?? ''
            ],
            [
                "de" => match (strtolower($dadosMilitares['corporacao'] ?? '')) {
                    'pm' => '{{pm}}',
                    'bm' => '{{bm}}',
                    'pensionista' => '{{pensionista}}',
                    default => '{{desconhecido}}' 
                },
                "para" => 'X'
            ],

            [
                "de" => "{{vinculo}}",
                "para" => $dadosFinanceiros['vinculo_servidor'] ?? ''
            ],
            [
                "de" => "{{graduacao}}",
                "para" => $dadosPessoais['escolaridade'] ?? ''
            ],
            [
                "de" => "{{data_admissao}}",
                "para" => $this->formatarData($dadosPessoais['data_filiacao'] ?? '')
            ],
            [
                "de" => "{{telefone_departamento}}",
                "para" => $dadosMilitares['telefone_lotacao']
            ],
            [
                "de" => "{{escolaridade}}",
                "para" => $dadosPessoais['escolaridade'] ?? ''
            ],
            [
                "de" => "{{nome_indicador}}",
                "para" => $dadosPessoais['indicado_por'] ?? ''
            ],
            
            // Dados do cônjuge
            [
                "de" => "{{nome_esposa}}",
                "para" => $conjuge['nome'] ?? ''
            ],
            [
                "de" => "{{nascimento_esposa}}",
                "para" => $this->formatarData($conjuge['data_nascimento'] ?? '')
            ],
            [
                "de" => "{{fone_esposa}}",
                "para" => $this->formatarTelefone($conjuge['telefone'] ?? '')
            ]
        ];
        
        // Adiciona dependentes (até 6)
        for ($i = 1; $i <= 6; $i++) {
            $dependente = null;
            $index = $i - 1;
            
            // Pula o cônjuge se já foi processado
            $depIndex = 0;
            foreach ($dependentes as $dep) {
                if ($conjuge && $dep['nome'] == $conjuge['nome']) {
                    continue; // Pula cônjuge
                }
                if ($depIndex == $index) {
                    $dependente = $dep;
                    break;
                }
                $depIndex++;
            }
            
            $dadosTemplate[] = [
                "de" => "{{dependente{$i}}}",
                "para" => $dependente['nome'] ?? ''
            ];
            
            $dadosTemplate[] = [
                "de" => "{{nascimento{$i}}}",
                "para" => $this->formatarData($dependente['data_nascimento'] ?? '')
            ];
        }
        
        // Monta JSON final
        return [
            "template_id" => $this->templateId,
            "signer_name" => $dadosPessoais['nome_completo'] ?? '',
            "signer_email" => $dadosPessoais['email'] ?? '',
            "signer_phone_country" => "55",
            "signer_phone_number" => $telefone,
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
            error_log("✓ ZapSign API - Sucesso: HTTP {$httpCode}");
            return [
                'sucesso' => true,
                'http_code' => $httpCode,
                'resposta' => $responseData,
                'documento_id' => $responseData['id'] ?? null,
                'link_assinatura' => $responseData['sign_url'] ?? null
            ];
        } else {
            error_log("✗ ZapSign API - Erro: HTTP {$httpCode} - " . $response);
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
    private function atualizarControleZapSign($associadoId, $resultado) {
        try {
            // Aqui você pode atualizar o banco com os dados do ZapSign
            // Por exemplo, salvar o ID do documento, link de assinatura, etc.
            
            error_log("Atualizando controle ZapSign para associado {$associadoId}");
            
            // Exemplo de como poderia ser:
            /*
            $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            $stmt = $db->prepare("
                UPDATE associados 
                SET zapsign_documento_id = ?, 
                    zapsign_link_assinatura = ?,
                    zapsign_status = 'ENVIADO',
                    zapsign_data_envio = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $resultado['documento_id'],
                $resultado['link_assinatura'],
                $associadoId
            ]);
            */
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar controle ZapSign: " . $e->getMessage());
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
    
    private function extrairEstado($cidade) {
        // Mapeamento simples - você pode melhorar isso
        $estados = [
            'São Paulo' => 'SP',
            'Rio de Janeiro' => 'RJ',
            'Belo Horizonte' => 'MG',
            'Salvador' => 'BA',
            'Brasília' => 'DF',
            'Fortaleza' => 'CE',
            'Manaus' => 'AM',
            'Curitiba' => 'PR',
            'Recife' => 'PE',
            'Goiânia' => 'GO',
            'Belém' => 'PA',
            'Guarulhos' => 'SP',
            'Campinas' => 'SP',
            'Porto Alegre' => 'RS'
        ];
        
        return $estados[$cidade] ?? '';
    }
}

/**
 * Função principal para chamar do criar_associado.php
 */
function enviarParaZapSign($dadosCompletos) {
    $zapSign = new ZapSignAPI();
    return $zapSign->enviarParaZapSign($dadosCompletos);
}

?>