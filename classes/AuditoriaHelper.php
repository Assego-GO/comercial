<?php
/**
 * Helper para facilitar registro de auditoria em todo o sistema
 * classes/AuditoriaHelper.php
 * 
 * ✅ CORREÇÃO CRÍTICA: Registrar TODAS as alterações com dados completos
 */

class AuditoriaHelper {
    
    /**
     * Registra INSERT com dados completos
     */
    public static function registrarInsert($tabela, $registro_id, $dados, $associado_id = null) {
        try {
            $auditoria = new Auditoria();
            return $auditoria->registrar([
                'tabela' => $tabela,
                'acao' => 'INSERT',
                'registro_id' => $registro_id,
                'associado_id' => $associado_id,
                'dados_completos' => $dados,
                'alteracoes' => self::converterParaAlteracoes('INSERT', $dados)
            ]);
        } catch (Exception $e) {
            error_log("❌ Erro ao registrar INSERT na auditoria: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra UPDATE comparando valores antigos e novos
     */
    public static function registrarUpdate($tabela, $registro_id, $dadosNovos, $dadosAntigos, $associado_id = null) {
        try {
            $alteracoes = [];
            
            foreach ($dadosNovos as $campo => $valorNovo) {
                $valorAntigo = $dadosAntigos[$campo] ?? null;
                
                // Só registra se realmente mudou
                if (self::valorMudou($valorAntigo, $valorNovo)) {
                    $alteracoes[] = [
                        'campo' => $campo,
                        'valor_anterior' => self::formatarValor($valorAntigo),
                        'valor_novo' => self::formatarValor($valorNovo)
                    ];
                }
            }
            
            // Se não houve mudanças, não registra
            if (empty($alteracoes)) {
                error_log("ℹ️ UPDATE sem alterações detectadas - Tabela: $tabela, ID: $registro_id");
                return null;
            }
            
            $auditoria = new Auditoria();
            return $auditoria->registrar([
                'tabela' => $tabela,
                'acao' => 'UPDATE',
                'registro_id' => $registro_id,
                'associado_id' => $associado_id,
                'alteracoes' => $alteracoes
            ]);
        } catch (Exception $e) {
            error_log("❌ Erro ao registrar UPDATE na auditoria: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra DELETE com dados que foram removidos
     */
    public static function registrarDelete($tabela, $registro_id, $dadosRemovidos, $associado_id = null) {
        try {
            $auditoria = new Auditoria();
            return $auditoria->registrar([
                'tabela' => $tabela,
                'acao' => 'DELETE',
                'registro_id' => $registro_id,
                'associado_id' => $associado_id,
                'dados_completos' => $dadosRemovidos,
                'alteracoes' => self::converterParaAlteracoes('DELETE', $dadosRemovidos)
            ]);
        } catch (Exception $e) {
            error_log("❌ Erro ao registrar DELETE na auditoria: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra ação customizada
     */
    public static function registrarAcao($tabela, $acao, $registro_id, $detalhes = [], $associado_id = null) {
        try {
            $auditoria = new Auditoria();
            return $auditoria->registrar([
                'tabela' => $tabela,
                'acao' => $acao,
                'registro_id' => $registro_id,
                'associado_id' => $associado_id,
                'detalhes' => $detalhes,
                'alteracoes' => self::converterParaAlteracoes($acao, $detalhes)
            ]);
        } catch (Exception $e) {
            error_log("❌ Erro ao registrar ação na auditoria: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra LOGIN
     */
    public static function registrarLogin($usuario_id, $sucesso = true, $detalhes = []) {
        try {
            $auditoria = new Auditoria();
            
            $dadosLogin = array_merge([
                'sucesso' => $sucesso ? 'Sim' : 'Não',
                'data_hora' => date('Y-m-d H:i:s'),
                'ip' => self::getIpAddress(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'sessao_id' => session_id()
            ], $detalhes);
            
            return $auditoria->registrar([
                'tabela' => 'Funcionarios',
                'acao' => $sucesso ? 'LOGIN' : 'LOGIN_FALHOU',
                'registro_id' => $usuario_id,
                'funcionario_id' => $usuario_id,
                'alteracoes' => self::converterParaAlteracoes('LOGIN', $dadosLogin)
            ]);
        } catch (Exception $e) {
            error_log("❌ Erro ao registrar LOGIN na auditoria: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra LOGOUT
     */
    public static function registrarLogout($usuario_id, $detalhes = []) {
        try {
            $auditoria = new Auditoria();
            
            $dadosLogout = array_merge([
                'data_hora' => date('Y-m-d H:i:s'),
                'duracao_sessao' => isset($_SESSION['login_time']) ? 
                    (time() - $_SESSION['login_time']) . ' segundos' : 'Desconhecida',
                'ip' => self::getIpAddress()
            ], $detalhes);
            
            return $auditoria->registrar([
                'tabela' => 'Funcionarios',
                'acao' => 'LOGOUT',
                'registro_id' => $usuario_id,
                'funcionario_id' => $usuario_id,
                'alteracoes' => self::converterParaAlteracoes('LOGOUT', $dadosLogout)
            ]);
        } catch (Exception $e) {
            error_log("❌ Erro ao registrar LOGOUT na auditoria: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ✅ CRÍTICO: Converte dados para formato de alterações
     */
    private static function converterParaAlteracoes($acao, $dados) {
        if (empty($dados)) {
            return [[
                'campo' => '_acao',
                'valor_anterior' => null,
                'valor_novo' => "Ação $acao realizada"
            ]];
        }
        
        $alteracoes = [];
        
        switch (strtoupper($acao)) {
            case 'INSERT':
                // Para INSERT: todos os campos são novos
                foreach ($dados as $campo => $valor) {
                    $alteracoes[] = [
                        'campo' => $campo,
                        'valor_anterior' => null,
                        'valor_novo' => self::formatarValor($valor)
                    ];
                }
                break;
                
            case 'DELETE':
                // Para DELETE: todos os campos são removidos
                foreach ($dados as $campo => $valor) {
                    $alteracoes[] = [
                        'campo' => $campo,
                        'valor_anterior' => self::formatarValor($valor),
                        'valor_novo' => null
                    ];
                }
                break;
                
            case 'LOGIN':
            case 'LOGOUT':
                // Para LOGIN/LOGOUT: registra informações do evento
                foreach ($dados as $campo => $valor) {
                    $alteracoes[] = [
                        'campo' => $campo,
                        'valor_anterior' => null,
                        'valor_novo' => self::formatarValor($valor)
                    ];
                }
                break;
                
            default:
                // Para outras ações: registra como está
                foreach ($dados as $campo => $valor) {
                    $alteracoes[] = [
                        'campo' => $campo,
                        'valor_anterior' => null,
                        'valor_novo' => self::formatarValor($valor)
                    ];
                }
                break;
        }
        
        return $alteracoes;
    }
    
    /**
     * Formata valor para exibição
     */
    private static function formatarValor($valor) {
        if (is_null($valor)) {
            return null;
        }
        
        if (is_bool($valor)) {
            return $valor ? 'Sim' : 'Não';
        }
        
        if (is_array($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        }
        
        if (is_object($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        }
        
        $valorStr = (string)$valor;
        
        // Limita tamanho de strings muito grandes
        if (strlen($valorStr) > 5000) {
            return substr($valorStr, 0, 5000) . '... (truncado)';
        }
        
        return $valorStr;
    }
    
    /**
     * Verifica se valor realmente mudou
     */
    private static function valorMudou($valorAntigo, $valorNovo) {
        // Comparação com tratamento de tipos
        if ($valorAntigo === $valorNovo) {
            return false;
        }
        
        // Trata null vs string vazia
        if (($valorAntigo === null || $valorAntigo === '') && 
            ($valorNovo === null || $valorNovo === '')) {
            return false;
        }
        
        // Trata números
        if (is_numeric($valorAntigo) && is_numeric($valorNovo)) {
            return (float)$valorAntigo !== (float)$valorNovo;
        }
        
        return true;
    }
    
    /**
     * Obter IP do usuário
     */
    private static function getIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
    }
    
    /**
     * Buscar dados antigos de um registro para UPDATE
     */
    public static function buscarDadosAntigos($tabela, $registro_id, $campos = ['*']) {
        try {
            $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            
            $camposStr = is_array($campos) ? implode(', ', $campos) : $campos;
            $stmt = $db->prepare("SELECT $camposStr FROM $tabela WHERE id = ? LIMIT 1");
            $stmt->execute([$registro_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar dados antigos: " . $e->getMessage());
            return null;
        }
    }
}
