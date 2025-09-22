<?php
// API Verificar Associados - ENHANCED VERSION WITH CPF FALLBACK
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido. Use POST.']);
    exit;
}

require_once('../../config/config.php');
require_once('../../config/database.php');
require_once('../../classes/Database.php');
require_once('../../classes/Auth.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("=== INÍCIO API VERIFICAR ASSOCIADOS - ENHANCED WITH RG + CPF SEARCH ===");

try {
    // AUTENTICAÇÃO
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }
    
    // CONEXÃO COM BANCO
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    error_log("✅ Conectado ao banco de dados");
    
    // Receber dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['dados']) || !is_array($data['dados'])) {
        throw new Exception('Dados inválidos recebidos');
    }
    
    error_log("📥 RECEBIDOS " . count($data['dados']) . " registros para processar");
    
    // FUNÇÃO MELHORADA PARA VALIDAR RG (ACEITA 2-6 DÍGITOS)
    function validarRG($rg) {
        if (empty($rg)) return false;
        
        $rgLimpo = preg_replace('/[^0-9]/', '', $rg);
        
        // Aceitar RGs de 2 a 6 dígitos
        if (strlen($rgLimpo) < 2 || strlen($rgLimpo) > 6) {
            return false;
        }
        
        // Não pode ser um CPF
        if (strlen($rgLimpo) == 11) {
            return false;
        }
        
        // Sequências óbvias que não são RGs
        $sequenciasInvalidas = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', 
                               '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
                               '12', '23', '34', '45', '56', '67', '78', '89', '99', '00',
                               '123', '1234', '12345', '123456'];
        if (in_array($rgLimpo, $sequenciasInvalidas)) {
            return false;
        }
        
        return true;
    }
    
    // FUNÇÃO PARA VALIDAR CPF
    function validarCPF($cpf) {
        if (empty($cpf)) return false;
        
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        
        // CPF deve ter exatamente 11 dígitos
        if (strlen($cpfLimpo) != 11) {
            return false;
        }
        
        // Não pode ser sequência de números iguais
        if (preg_match('/(\d)\1{10}/', $cpfLimpo)) {
            return false;
        }
        
        return true;
    }
    
    // Helper function to fill associado data
    function preencherDadosAssociado($resultado, $associado, $foundBy) {
        $situacao = trim(strtolower($associado['situacao'] ?? ''));
        $statusVerificacao = 'filiado';
        
        if (in_array($situacao, ['desfiliado', 'desligado', 'suspenso', 'inativo'])) {
            $statusVerificacao = 'naofiliado';
        }
        
        $resultado['statusverificacao'] = $statusVerificacao;
        $resultado['associadoid'] = $associado['id'];
        $resultado['encontrado_por'] = $foundBy;
        $resultado['nomeassociado'] = $associado['nome'];
        $resultado['rgassociado'] = $associado['rg'];
        $resultado['cpfassociado'] = $associado['cpf'];
        $resultado['emailassociado'] = $associado['email'];
        $resultado['telefoneassociado'] = $associado['telefone'];
        $resultado['situacaoassociado'] = $associado['situacao'];
        $resultado['corporacao'] = $associado['corporacao'];
        $resultado['patente'] = $associado['patente'];
        $resultado['situacaofinanceira'] = $associado['situacaoFinanceira'];
        
        return $resultado;
    }
    
    // COLETAR TODOS OS RGs E CPFs PARA BUSCA
    $rgsParaBuscar = [];
    $cpfsParaBuscar = [];
    $dadosIndexados = [];
    
    foreach ($data['dados'] as $index => $item) {
        error_log("🔍 PROCESSANDO ITEM $index: " . json_encode($item));
        
        // ===== PROCESSAR RGs =====
        $rgsDoItem = [];
        if (!empty($item['rgs']) && is_array($item['rgs'])) {
            $rgsDoItem = $item['rgs'];
        } else if (!empty($item['rgprincipal'])) {
            $rgsDoItem = [$item['rgprincipal']];
        }
        
        foreach ($rgsDoItem as $rg) {
            if (validarRG($rg)) {
                $rgLimpo = preg_replace('/[^0-9]/', '', $rg);
                $rgsParaBuscar[] = $rgLimpo;
                $dadosIndexados['rg'][$rgLimpo] = [
                    'index' => $index,
                    'nome' => $item['nome'] ?? 'Nome não informado',
                    'rg_original' => $rg,
                    'rg_limpo' => $rgLimpo,
                    'linha_original' => $item['linhaoriginal'] ?? ''
                ];
                error_log("✅ RG VÁLIDO ADICIONADO: $rgLimpo (original: $rg, tamanho: " . strlen($rgLimpo) . ")");
            } else {
                error_log("❌ RG INVÁLIDO REJEITADO: $rg");
            }
        }
        
        // ===== PROCESSAR CPFs =====
        $cpfsDoItem = [];
        if (!empty($item['cpfs']) && is_array($item['cpfs'])) {
            $cpfsDoItem = $item['cpfs'];
        } else if (!empty($item['cpfprincipal'])) {
            $cpfsDoItem = [$item['cpfprincipal']];
        }
        
        foreach ($cpfsDoItem as $cpf) {
            if (validarCPF($cpf)) {
                $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
                $cpfsParaBuscar[] = $cpfLimpo;
                $dadosIndexados['cpf'][$cpfLimpo] = [
                    'index' => $index,
                    'nome' => $item['nome'] ?? 'Nome não informado',
                    'cpf_original' => $cpf,
                    'cpf_limpo' => $cpfLimpo,
                    'linha_original' => $item['linhaoriginal'] ?? ''
                ];
                error_log("✅ CPF VÁLIDO ADICIONADO: $cpfLimpo (original: $cpf)");
            } else {
                error_log("❌ CPF INVÁLIDO REJEITADO: $cpf");
            }
        }
    }
    
    // Remover duplicatas
    $rgsParaBuscar = array_unique($rgsParaBuscar);
    $cpfsParaBuscar = array_unique($cpfsParaBuscar);
    
    error_log("🔍 RGs ÚNICOS PARA BUSCAR: " . count($rgsParaBuscar) . " - " . json_encode($rgsParaBuscar));
    error_log("🔍 CPFs ÚNICOS PARA BUSCAR: " . count($cpfsParaBuscar) . " - " . json_encode($cpfsParaBuscar));
    
    // BUSCA NO BANCO - PRIMEIRO POR RG
    $associadosEncontrados = [];
    $estatisticasBusca = [
        'encontrados_por_rg' => 0,
        'encontrados_por_cpf' => 0,
        'total_encontrados' => 0
    ];
    
    // ===== BUSCA POR RG =====
    if (!empty($rgsParaBuscar)) {
        $placeholders = str_repeat('?,', count($rgsParaBuscar) - 1) . '?';
        
        $sql = "SELECT a.id, a.nome, a.rg, a.cpf, a.situacao, a.email, a.telefone,
                       REGEXP_REPLACE(a.rg, '[^0-9]', '') as rg_limpo,
                       REGEXP_REPLACE(a.cpf, '[^0-9]', '') as cpf_limpo,
                       m.corporacao, m.patente, m.categoria, m.lotacao,
                       f.situacaoFinanceira
                FROM Associados a
                LEFT JOIN Militar m ON a.id = m.associado_id
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                WHERE REGEXP_REPLACE(a.rg, '[^0-9]', '') IN ($placeholders)
                  AND LENGTH(REGEXP_REPLACE(a.rg, '[^0-9]', '')) BETWEEN 2 AND 6";
        
        error_log("🔍 QUERY RG: $sql");
        error_log("🔍 PARÂMETROS RG: " . json_encode($rgsParaBuscar));
        
        $stmt = $db->prepare($sql);
        $stmt->execute($rgsParaBuscar);
        $resultadosRG = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultadosRG as $associado) {
            $rgLimpo = $associado['rg_limpo'];
            $associado['found_by'] = 'RG';
            $associadosEncontrados['rg_' . $rgLimpo] = $associado;
            $estatisticasBusca['encontrados_por_rg']++;
            error_log("✅ ENCONTRADO POR RG: {$rgLimpo} (tam:" . strlen($rgLimpo) . ") = {$associado['nome']}");
        }
    }
    
    // ===== BUSCA POR CPF =====
    if (!empty($cpfsParaBuscar)) {
        $placeholders = str_repeat('?,', count($cpfsParaBuscar) - 1) . '?';
        
        $sql = "SELECT a.id, a.nome, a.rg, a.cpf, a.situacao, a.email, a.telefone,
                       REGEXP_REPLACE(a.rg, '[^0-9]', '') as rg_limpo,
                       REGEXP_REPLACE(a.cpf, '[^0-9]', '') as cpf_limpo,
                       m.corporacao, m.patente, m.categoria, m.lotacao,
                       f.situacaoFinanceira
                FROM Associados a
                LEFT JOIN Militar m ON a.id = m.associado_id
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                WHERE REGEXP_REPLACE(a.cpf, '[^0-9]', '') IN ($placeholders)";
        
        error_log("🔍 QUERY CPF: $sql");
        error_log("🔍 PARÂMETROS CPF: " . json_encode($cpfsParaBuscar));
        
        $stmt = $db->prepare($sql);
        $stmt->execute($cpfsParaBuscar);
        $resultadosCPF = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultadosCPF as $associado) {
            $cpfLimpo = $associado['cpf_limpo'];
            $associado['found_by'] = 'CPF';
            $associadosEncontrados['cpf_' . $cpfLimpo] = $associado;
            $estatisticasBusca['encontrados_por_cpf']++;
            error_log("✅ ENCONTRADO POR CPF: {$cpfLimpo} = {$associado['nome']}");
        }
    }
    
    $estatisticasBusca['total_encontrados'] = $estatisticasBusca['encontrados_por_rg'] + $estatisticasBusca['encontrados_por_cpf'];
    error_log("📊 ESTATÍSTICAS DA BUSCA: " . json_encode($estatisticasBusca));
    
    // ===== PROCESSAR RESULTADOS COM RG + CPF FALLBACK =====
    $resultados = [];
    
    foreach ($data['dados'] as $index => $item) {
        $resultado = [
            'indice' => $index + 1,
            'linhaoriginal' => $item['linhaoriginal'] ?? '',
            'nomeextraido' => $item['nome'] ?? '',
            'rgextraido' => $item['rgprincipal'] ?? '',
            'cpfextraido' => $item['cpfprincipal'] ?? null,
            'statusverificacao' => 'naoencontrado',
            'associadoid' => null,
            'encontrado_por' => null,
            'nomeassociado' => null,
            'rgassociado' => null,
            'cpfassociado' => null,
            'emailassociado' => null,
            'telefoneassociado' => null,
            'situacaoassociado' => null,
            'corporacao' => null,
            'patente' => null,
            'situacaofinanceira' => null
        ];
        
        $encontrou = false;
        
        // ===== TENTAR ENCONTRAR POR RG PRIMEIRO =====
        $rgsDoItem = [];
        if (!empty($item['rgs']) && is_array($item['rgs'])) {
            $rgsDoItem = $item['rgs'];
        } else if (!empty($item['rgprincipal'])) {
            $rgsDoItem = [$item['rgprincipal']];
        }
        
        foreach ($rgsDoItem as $rg) {
            if (validarRG($rg)) {
                $rgLimpo = preg_replace('/[^0-9]/', '', $rg);
                
                if (isset($associadosEncontrados['rg_' . $rgLimpo])) {
                    $associado = $associadosEncontrados['rg_' . $rgLimpo];
                    $resultado = preencherDadosAssociado($resultado, $associado, 'RG');
                    $encontrou = true;
                    error_log("✅ MATCH POR RG: {$rgLimpo} -> {$associado['nome']}");
                    break; // Parar no primeiro match por RG
                }
            }
        }
        
        // ===== SE NÃO ENCONTROU POR RG, TENTAR POR CPF =====
        if (!$encontrou) {
            $cpfsDoItem = [];
            if (!empty($item['cpfs']) && is_array($item['cpfs'])) {
                $cpfsDoItem = $item['cpfs'];
            } else if (!empty($item['cpfprincipal'])) {
                $cpfsDoItem = [$item['cpfprincipal']];
            }
            
            foreach ($cpfsDoItem as $cpf) {
                if (validarCPF($cpf)) {
                    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
                    
                    if (isset($associadosEncontrados['cpf_' . $cpfLimpo])) {
                        $associado = $associadosEncontrados['cpf_' . $cpfLimpo];
                        $resultado = preencherDadosAssociado($resultado, $associado, 'CPF');
                        $encontrou = true;
                        error_log("✅ MATCH POR CPF FALLBACK: {$cpfLimpo} -> {$associado['nome']}");
                        break; // Parar no primeiro match por CPF
                    }
                }
            }
        }
        
        if (!$encontrou) {
            error_log("❌ NÃO ENCONTRADO: RGs=" . json_encode($rgsDoItem) . ", CPFs=" . json_encode($cpfsDoItem ?? []));
        }
        
        $resultados[] = $resultado;
    }
    
    // ===== ESTATÍSTICAS FINAIS =====
    $estatisticas = [
        'total' => count($resultados),
        'filiados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'filiado')),
        'naofiliados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'naofiliado')),
        'naoencontrados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'naoencontrado')),
        'encontrados_por_rg' => count(array_filter($resultados, fn($r) => $r['encontrado_por'] === 'RG')),
        'encontrados_por_cpf' => count(array_filter($resultados, fn($r) => $r['encontrado_por'] === 'CPF')),
        'rgs_processados' => count($rgsParaBuscar),
        'cpfs_processados' => count($cpfsParaBuscar)
    ];
    
    error_log("🎉 ESTATÍSTICAS FINAIS (RG + CPF): " . json_encode($estatisticas));
    
    // Retornar resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => count($resultados) . ' registros processados (busca por RG + CPF fallback)',
        'resultados' => $resultados,
        'estatisticas' => $estatisticas,
        'detalhes_busca' => $estatisticasBusca
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("💥 ERRO GERAL: " . $e->getMessage());
    error_log("📍 LINHA: " . $e->getLine());
    error_log("📍 ARQUIVO: " . $e->getFile());
    error_log("📍 STACK TRACE: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno: ' . $e->getMessage(),
        'debug' => [
            'linha' => $e->getLine(),
            'arquivo' => basename($e->getFile())
        ]
    ], JSON_UNESCAPED_UNICODE);
}

error_log("=== FIM API VERIFICAR ASSOCIADOS ===");
?>