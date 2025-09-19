<?php
// API Verificar Associados - VERSÃO CORRIGIDA PARA ACEITAR RGs DE 2-6 DÍGITOS
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
error_log("=== INÍCIO API VERIFICAR ASSOCIADOS - VERSÃO RGs 2-6 DÍGITOS ===");

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
    
    // FUNÇÃO MELHORADA PARA VALIDAR RG (AGORA COM 2 DÍGITOS!)
    function validarRG($rg) {
        if (empty($rg)) return false;
        
        $rgLimpo = preg_replace('/[^0-9]/', '', $rg);
        
        // MUDANÇA: Aceitar RGs de 2 a 6 dígitos
        if (strlen($rgLimpo) < 2 || strlen($rgLimpo) > 6) {
            return false;
        }
        
        // Não pode ser um CPF
        if (strlen($rgLimpo) == 11) {
            return false;
        }
        
        // Sequências óbvias que não são RGs (expandida para incluir 2 dígitos)
        $sequenciasInvalidas = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', 
                               '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
                               '12', '23', '34', '45', '56', '67', '78', '89', '99', '00',
                               '123', '1234', '12345', '123456'];
        if (in_array($rgLimpo, $sequenciasInvalidas)) {
            return false;
        }
        
        return true;
    }
    
    // COLETAR TODOS OS RGs PARA BUSCA COM NOVA VALIDAÇÃO
    $rgsParaBuscar = [];
    $dadosIndexados = [];
    
    foreach ($data['dados'] as $index => $item) {
        error_log("🔍 PROCESSANDO ITEM $index: " . json_encode($item));
        
        $rgPrincipal = $item['rgprincipal'] ?? null;
        
        // Processar array de RGs se existir
        $rgsDoItem = [];
        if (!empty($item['rgs']) && is_array($item['rgs'])) {
            $rgsDoItem = $item['rgs'];
        } else if (!empty($rgPrincipal)) {
            $rgsDoItem = [$rgPrincipal];
        }
        
        // Processar cada RG encontrado com a nova validação
        foreach ($rgsDoItem as $rg) {
            if (validarRG($rg)) {
                $rgLimpo = preg_replace('/[^0-9]/', '', $rg);
                $rgsParaBuscar[] = $rgLimpo;
                $dadosIndexados[$rgLimpo] = [
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
    }
    
    // Remover duplicatas
    $rgsParaBuscar = array_unique($rgsParaBuscar);
    error_log("🔍 RGs ÚNICOS PARA BUSCAR (incluindo de 2 dígitos): " . json_encode($rgsParaBuscar));
    
    // BUSCA NO BANCO - AGORA COM RGs DE 3-6 DÍGITOS
    $associadosEncontrados = [];
    if (!empty($rgsParaBuscar)) {
        $placeholders = str_repeat('?,', count($rgsParaBuscar) - 1) . '?';
        
        // Query que busca RGs limpos de 2 a 6 dígitos
        $sql = "SELECT a.id, a.nome, a.rg, a.cpf, a.situacao, a.email, a.telefone,
                       REGEXP_REPLACE(a.rg, '[^0-9]', '') as rg_limpo,
                       m.corporacao, m.patente, m.categoria, m.lotacao,
                       f.situacaoFinanceira
                FROM Associados a
                LEFT JOIN Militar m ON a.id = m.associado_id
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                WHERE REGEXP_REPLACE(a.rg, '[^0-9]', '') IN ($placeholders)
                  AND LENGTH(REGEXP_REPLACE(a.rg, '[^0-9]', '')) BETWEEN 2 AND 6";
        
        error_log("🔍 QUERY SQL MELHORADA: $sql");
        error_log("🔍 PARÂMETROS: " . json_encode($rgsParaBuscar));
        
        $stmt = $db->prepare($sql);
        $stmt->execute($rgsParaBuscar);
        $resultadosBusca = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("🎯 ENCONTRADOS NO BANCO: " . count($resultadosBusca) . " registros");
        
        // Indexar por RG LIMPO para acesso direto
        foreach ($resultadosBusca as $associado) {
            $rgLimpo = $associado['rg_limpo'];
            $associadosEncontrados[$rgLimpo] = $associado;
            error_log("✅ ENCONTRADO: RG Limpo {$rgLimpo} (tam:" . strlen($rgLimpo) . ") = {$associado['nome']} (RG Original: {$associado['rg']})");
        }
    }
    
    // PROCESSAR RESULTADOS
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
        
        // BUSCAR POR QUALQUER RG DO ITEM
        $rgsDoItem = [];
        if (!empty($item['rgs']) && is_array($item['rgs'])) {
            $rgsDoItem = $item['rgs'];
        } else if (!empty($item['rgprincipal'])) {
            $rgsDoItem = [$item['rgprincipal']];
        }
        
        $encontrou = false;
        foreach ($rgsDoItem as $rg) {
            if (validarRG($rg)) {
                $rgLimpo = preg_replace('/[^0-9]/', '', $rg);
                
                // BUSCA DIRETA NO ARRAY INDEXADO
                if (isset($associadosEncontrados[$rgLimpo])) {
                    $associado = $associadosEncontrados[$rgLimpo];
                    
                    // Determinar status baseado na situação
                    $situacao = trim(strtolower($associado['situacao'] ?? ''));
                    $statusVerificacao = 'filiado'; // Padrão
                    
                    if (in_array($situacao, ['desfiliado', 'desligado', 'suspenso', 'inativo'])) {
                        $statusVerificacao = 'naofiliado';
                    }
                    
                    $resultado['statusverificacao'] = $statusVerificacao;
                    $resultado['associadoid'] = $associado['id'];
                    $resultado['nomeassociado'] = $associado['nome'];
                    $resultado['rgassociado'] = $associado['rg'];
                    $resultado['cpfassociado'] = $associado['cpf'];
                    $resultado['emailassociado'] = $associado['email'];
                    $resultado['telefoneassociado'] = $associado['telefone'];
                    $resultado['situacaoassociado'] = $associado['situacao'];
                    $resultado['corporacao'] = $associado['corporacao'];
                    $resultado['patente'] = $associado['patente'];
                    $resultado['situacaofinanceira'] = $associado['situacaoFinanceira'];
                    
                    error_log("✅ MATCH ENCONTRADO: RG {$rgLimpo} (tam:" . strlen($rgLimpo) . ") -> {$associado['nome']} - Status: {$statusVerificacao}");
                    $encontrou = true;
                    break; // Parar no primeiro match
                }
            }
        }
        
        if (!$encontrou) {
            error_log("❌ NÃO ENCONTRADO: " . json_encode($rgsDoItem));
        }
        
        $resultados[] = $resultado;
    }
    
    // ESTATÍSTICAS
    $estatisticas = [
        'total' => count($resultados),
        'filiados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'filiado')),
        'naofiliados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'naofiliado')),
        'naoencontrados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'naoencontrado'))
    ];
    
    error_log("🎉 ESTATÍSTICAS FINAIS (com RGs 2-6 dígitos): " . json_encode($estatisticas));
    
    // Retornar resposta
    echo json_encode([
        'success' => true,
        'message' => count($resultados) . ' registros processados (aceita RGs de 2-6 dígitos)',
        'resultados' => $resultados,
        'estatisticas' => $estatisticas
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("💥 ERRO: " . $e->getMessage());
    error_log("📍 LINHA: " . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>