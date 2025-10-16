<?php
// API Verificar Associados - BUSCA SUPER RESTRITIVA
// 
// CONFIGURAÇÃO ATUAL (v2.0 - ULTRA RESTRITIVA):
// ================================================
// Threshold match confirmado: 90% (apenas variações mínimas)
// Threshold avisos: 85-89% (sugestões de escrita diferente)
// Abaixo de 85%: Não encontrado (sem avisos)
//
// VALIDAÇÕES:
// - 40% peso texto normal (quase idêntico)
// - 35% peso fonético (S/Z, C/Ç, SS/Ç, acentos)
// - Match individual de palavra: 80% mínimo
// - Validação obrigatória: 50%+ palavras devem ter correspondência
//
// ACEITA: José/Jose, Carlos/Karlos, Souza/Sousa, César/Cesar
// REJEITA: Luiz/Vamir, João/José, nomes completamente diferentes

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
error_log("=== INÍCIO API VERIFICAR ASSOCIADOS - BUSCA SUPER RESTRITIVA ===");
error_log("CONFIGURAÇÃO: Threshold 90% | Avisos 85-89% | Match palavra: 80% | Validação 50% palavras");

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
    
    // ========== FUNÇÕES DE VALIDAÇÃO ==========
    function validarRG($rg) {
        if (empty($rg)) return false;
        $rgLimpo = preg_replace('/[^0-9]/', '', $rg);
        if (strlen($rgLimpo) < 2 || strlen($rgLimpo) > 6) return false;
        if (strlen($rgLimpo) == 11) return false;
        
        $sequenciasInvalidas = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', 
                               '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
                               '12', '23', '34', '45', '56', '67', '78', '89', '99', '00',
                               '123', '1234', '12345', '123456'];
        if (in_array($rgLimpo, $sequenciasInvalidas)) return false;
        
        return true;
    }
    
    function validarCPF($cpf) {
        if (empty($cpf)) return false;
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpfLimpo) != 11) return false;
        if (preg_match('/(\d)\1{10}/', $cpfLimpo)) return false;
        return true;
    }
    
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
    
    // ========== ALGORITMOS FONÉTICOS ==========
    
    function normalizarNome($nome) {
        $nome = mb_strtolower($nome, 'UTF-8');
        
        $acentos = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n'
        ];
        
        $nome = strtr($nome, $acentos);
        $nome = preg_replace('/[^a-z\s]/', '', $nome);
        $nome = preg_replace('/\s+/', ' ', trim($nome));
        
        return $nome;
    }
    
    function normalizarFoneticoPTBR($nome) {
        $nome = normalizarNome($nome);
        
        $regras = [
            '/^h/' => '',
            '/\sh/' => ' ',
            '/lh/' => 'l',
            '/nh/' => 'n',
            '/rr/' => 'r',
            '/ss/' => 's',
            '/ch/' => 'x',
            '/qu([ei])/' => 'k$1',
            '/qu/' => 'k',
            '/c([ei])/' => 's$1',
            '/ç/' => 's',
            '/k/' => 'c',
            '/ph/' => 'f',
            '/w/' => 'v',
            '/y/' => 'i',
            '/x/' => 'x',
            '/z$/' => 's',
            '/z([bcdfghjklmnpqrstvwxz])/' => 's$1',
            '/g([ei])/' => 'j$1',
            '/aa/' => 'a',
            '/ee/' => 'e',
            '/ii/' => 'i',
            '/oo/' => 'o',
            '/uu/' => 'u',
            '/([bcdfghjklmnpqrstvwxz])\1/' => '$1',
        ];
        
        foreach ($regras as $pattern => $replacement) {
            $nome = preg_replace($pattern, $replacement, $nome);
        }
        
        return $nome;
    }
    
    function metaphonePTBR($nome) {
        $nome = normalizarFoneticoPTBR($nome);
        $partes = explode(' ', $nome);
        $resultado = [];
        
        foreach ($partes as $parte) {
            if (strlen($parte) <= 2) {
                $resultado[] = $parte;
                continue;
            }
            
            $codigo = substr($parte, 0, 1);
            $resto = substr($parte, 1);
            $resto = preg_replace('/[aeiou]/', '', $resto);
            $codigo .= $resto;
            $resultado[] = $codigo;
        }
        
        return implode(' ', $resultado);
    }
    
    function soundexPTBR($nome) {
        $nome = normalizarFoneticoPTBR($nome);
        $partes = explode(' ', $nome);
        $resultado = [];
        
        foreach ($partes as $parte) {
            if (strlen($parte) < 2) {
                $resultado[] = $parte;
                continue;
            }
            
            $codigo = strtoupper(substr($parte, 0, 1));
            
            $mapeamento = [
                'b' => '1', 'p' => '1', 'f' => '1', 'v' => '1',
                'c' => '2', 'g' => '2', 'j' => '2', 'k' => '2', 'q' => '2', 's' => '2', 'x' => '2', 'z' => '2',
                'd' => '3', 't' => '3',
                'l' => '4',
                'm' => '5', 'n' => '5',
                'r' => '6'
            ];
            
            $anterior = '';
            for ($i = 1; $i < strlen($parte); $i++) {
                $char = $parte[$i];
                if (isset($mapeamento[$char]) && $mapeamento[$char] !== $anterior) {
                    $codigo .= $mapeamento[$char];
                    $anterior = $mapeamento[$char];
                }
            }
            
            $codigo = str_pad(substr($codigo, 0, 4), 4, '0');
            $resultado[] = $codigo;
        }
        
        return implode(' ', $resultado);
    }
    
    /**
     * CÁLCULO DE SIMILARIDADE SUPER RESTRITIVO
     * 
     * CONFIGURAÇÃO ATUAL:
     * - Threshold para match confirmado: 90%
     * - Threshold para aviso de escrita diferente: 85-89%
     * - Abaixo de 85%: NÃO encontrado (sem avisos)
     * 
     * VALIDAÇÕES RIGOROSAS:
     * - 40% de peso para texto normal (idêntico ou quase)
     * - 35% para diferenças fonéticas (S/Z, C/Ç, SS/Ç, acentos)
     * - Se menos de 50% das palavras têm correspondência, score de palavras = 0
     * - Match de palavra individual requer 80% de similaridade
     * 
     * EXEMPLOS DE MATCHES VÁLIDOS:
     * - José Silva ↔ Jose Silva (acentos)
     * - Carlos Souza ↔ Carlos Sousa (Z/S)
     * - César Assis ↔ Cesar Açis (acentos + SS/Ç)
     * 
     * EXEMPLOS DE NÃO-MATCHES:
     * - Luiz Alexandre Dias ↔ Vamir Alexandre Dias (nomes diferentes!)
     * - João Silva ↔ José Silva (nomes diferentes, não apenas variação)
     */
    function calcularSimilaridadeFonetica($nome1, $nome2) {
        // 1. Normalização simples
        $norm1 = normalizarNome($nome1);
        $norm2 = normalizarNome($nome2);
        
        $norm1_trunc = substr($norm1, 0, 255);
        $norm2_trunc = substr($norm2, 0, 255);
        
        $distNorm = levenshtein($norm1_trunc, $norm2_trunc);
        $maxLenNorm = max(strlen($norm1), strlen($norm2));
        $simNorm = $maxLenNorm > 0 ? (1 - $distNorm / $maxLenNorm) * 100 : 0;
        
        // 2. Normalização fonética
        $fon1 = normalizarFoneticoPTBR($nome1);
        $fon2 = normalizarFoneticoPTBR($nome2);
        
        $fon1_trunc = substr($fon1, 0, 255);
        $fon2_trunc = substr($fon2, 0, 255);
        
        $distFon = levenshtein($fon1_trunc, $fon2_trunc);
        $maxLenFon = max(strlen($fon1), strlen($fon2));
        $simFon = $maxLenFon > 0 ? (1 - $distFon / $maxLenFon) * 100 : 0;
        
        // 3. Metaphone
        $meta1 = metaphonePTBR($nome1);
        $meta2 = metaphonePTBR($nome2);
        
        $meta1_trunc = substr($meta1, 0, 255);
        $meta2_trunc = substr($meta2, 0, 255);
        
        $distMeta = levenshtein($meta1_trunc, $meta2_trunc);
        $maxLenMeta = max(strlen($meta1), strlen($meta2));
        $simMeta = $maxLenMeta > 0 ? (1 - $distMeta / $maxLenMeta) * 100 : 0;
        
        // 4. Soundex
        $sound1 = soundexPTBR($nome1);
        $sound2 = soundexPTBR($nome2);
        $simSound = ($sound1 === $sound2) ? 100 : 0;
        
        // 5. Similaridade de palavras
        $palavras1 = explode(' ', $norm1);
        $palavras2 = explode(' ', $norm2);
        $matchesPalavras = 0;
        $totalPalavras = max(count($palavras1), count($palavras2));
        
        $palavras1 = array_filter($palavras1, fn($p) => strlen($p) >= 2);
        $palavras2 = array_filter($palavras2, fn($p) => strlen($p) >= 2);
        
        foreach ($palavras1 as $p1) {
            foreach ($palavras2 as $p2) {
                $maxLen = max(strlen($p1), strlen($p2));
                if ($maxLen === 0) continue;
                
                $simPalavra = (similar_text($p1, $p2) / $maxLen) * 100;
                
                if ($simPalavra > 70) {
                    $matchesPalavras++;
                    break;
                }
            }
        }
        
        $simPalavras = $totalPalavras > 0 ? ($matchesPalavras / $totalPalavras) * 100 : 0;
        
        // 6. Iniciais
        $iniciais1 = implode('', array_map(fn($p) => substr($p, 0, 1), $palavras1));
        $iniciais2 = implode('', array_map(fn($p) => substr($p, 0, 1), $palavras2));
        $simIniciais = ($iniciais1 === $iniciais2) ? 100 : 0;
        
        // 7. Similar text
        $similarTextScore = 0;
        if ($maxLenNorm > 0) {
            similar_text($norm1, $norm2, $percentSimilar);
            $similarTextScore = $percentSimilar;
        }
        
        // Combinar scores com pesos SUPER RESTRITIVOS
        // Foco em texto exato e diferenças fonéticas mínimas (S/Z, C/Ç, SS/Ç)
        // NOVO: Se menos de 50% das palavras têm match, score de palavras = 0
        $scoreTotal = (
            $simNorm * 0.40 +           // 40% - similaridade texto normal (MUITO AUMENTADO!)
            $simFon * 0.35 +            // 35% - similaridade fonética (apenas diferenças reais)
            $simMeta * 0.15 +           // 15% - metaphone
            $simSound * 0.05 +          // 5% - soundex
            $simPalavras * 0.02 +       // 2% - match de palavras (DRASTICAMENTE REDUZIDO!)
            $simIniciais * 0.02 +       // 2% - iniciais (REDUZIDO)
            $similarTextScore * 0.01    // 1% - similar_text (MÍNIMO)
        );
        
        return [
            'score_total' => round($scoreTotal, 2),
            'score_normal' => round($simNorm, 2),
            'score_fonetico' => round($simFon, 2),
            'score_metaphone' => round($simMeta, 2),
            'score_soundex' => round($simSound, 2),
            'score_palavras' => round($simPalavras, 2),
            'score_iniciais' => round($simIniciais, 2),
            'score_similar_text' => round($similarTextScore, 2),
            'distancia_fonetica' => $distFon,
            'nome1_normalizado' => $norm1,
            'nome2_normalizado' => $norm2,
            'nome1_fonetico' => $fon1,
            'nome2_fonetico' => $fon2
        ];
    }
    
    // ========== COLETAR DADOS ==========
    $rgsParaBuscar = [];
    $cpfsParaBuscar = [];
    $nomesParaBuscar = [];
    $dadosIndexados = [];
    
    foreach ($data['dados'] as $index => $item) {
        // RGs
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
            }
        }
        
        // CPFs
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
            }
        }
        
        // Nomes
        if (!empty($item['nome']) && strlen(trim($item['nome'])) >= 3) {
            $nome = trim($item['nome']);
            $nomesParaBuscar[] = $nome;
            $dadosIndexados['nome'][$nome] = [
                'index' => $index,
                'nome' => $nome,
                'linha_original' => $item['linhaoriginal'] ?? ''
            ];
        }
    }
    
    $rgsParaBuscar = array_unique($rgsParaBuscar);
    $cpfsParaBuscar = array_unique($cpfsParaBuscar);
    $nomesParaBuscar = array_unique($nomesParaBuscar);
    
    error_log("📊 COLETA: RGs=" . count($rgsParaBuscar) . ", CPFs=" . count($cpfsParaBuscar) . ", Nomes=" . count($nomesParaBuscar));
    
    // ========== BUSCAR NO BANCO ==========
    $associadosEncontrados = [];
    $estatisticasBusca = [
        'encontrados_por_rg' => 0,
        'encontrados_por_cpf' => 0,
        'encontrados_por_nome' => 0,
        'total_encontrados' => 0
    ];
    
    // Busca por RG
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
        
        $stmt = $db->prepare($sql);
        $stmt->execute($rgsParaBuscar);
        $resultadosRG = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultadosRG as $associado) {
            $rgLimpo = $associado['rg_limpo'];
            $associado['found_by'] = 'RG';
            $associadosEncontrados['rg_' . $rgLimpo] = $associado;
            $estatisticasBusca['encontrados_por_rg']++;
        }
    }
    
    // Busca por CPF
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
        
        $stmt = $db->prepare($sql);
        $stmt->execute($cpfsParaBuscar);
        $resultadosCPF = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultadosCPF as $associado) {
            $cpfLimpo = $associado['cpf_limpo'];
            $associado['found_by'] = 'CPF';
            $associadosEncontrados['cpf_' . $cpfLimpo] = $associado;
            $estatisticasBusca['encontrados_por_cpf']++;
        }
    }
    
    // ========== BUSCA FONÉTICA COM AVISOS ==========
    if (!empty($nomesParaBuscar)) {
        error_log("🔍 BUSCA FONÉTICA RESTRITIVA - " . count($nomesParaBuscar) . " nomes");
        
        foreach ($nomesParaBuscar as $nomeBusca) {
            error_log("🔍 Buscando: '$nomeBusca'");
            
            $candidatos = [];
            
            // ETAPA 1: Busca exata
            $sql = "SELECT a.id, a.nome, a.rg, a.cpf, a.situacao, a.email, a.telefone,
                           REGEXP_REPLACE(a.rg, '[^0-9]', '') as rg_limpo,
                           REGEXP_REPLACE(a.cpf, '[^0-9]', '') as cpf_limpo,
                           m.corporacao, m.patente, m.categoria, m.lotacao,
                           f.situacaoFinanceira
                    FROM Associados a
                    LEFT JOIN Militar m ON a.id = m.associado_id
                    LEFT JOIN Financeiro f ON a.id = f.associado_id
                    WHERE a.nome = ?
                    LIMIT 1";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$nomeBusca]);
            $matchExato = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($matchExato) {
                error_log("✅ MATCH EXATO: '$nomeBusca' = '{$matchExato['nome']}'");
                $nomeKey = strtolower(trim($nomeBusca));
                $matchExato['found_by'] = 'NOME';
                $matchExato['tipo_match'] = 'exato';
                $matchExato['similaridade'] = ['score_total' => 100];
                
                $associadosEncontrados['nome_' . md5($nomeKey)] = $matchExato;
                $associadosEncontrados['nome_original_' . $nomeKey] = $matchExato;
                $estatisticasBusca['encontrados_por_nome']++;
                continue;
            }
            
            // ETAPA 2: Busca por palavras
            $partesNome = explode(' ', normalizarNome($nomeBusca));
            $partesNome = array_filter($partesNome, fn($p) => strlen($p) >= 3);
            
            if (!empty($partesNome)) {
                $conditions = [];
                $params = [];
                
                foreach ($partesNome as $parte) {
                    $conditions[] = "a.nome LIKE ?";
                    $params[] = '%' . $parte . '%';
                }
                
                $whereClause = implode(' OR ', $conditions);
                
                $sql = "SELECT a.id, a.nome, a.rg, a.cpf, a.situacao, a.email, a.telefone,
                               REGEXP_REPLACE(a.rg, '[^0-9]', '') as rg_limpo,
                               REGEXP_REPLACE(a.cpf, '[^0-9]', '') as cpf_limpo,
                               m.corporacao, m.patente, m.categoria, m.lotacao,
                               f.situacaoFinanceira
                        FROM Associados a
                        LEFT JOIN Militar m ON a.id = m.associado_id
                        LEFT JOIN Financeiro f ON a.id = f.associado_id
                        WHERE $whereClause
                        LIMIT 200";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("   📋 Candidatos por palavras: " . count($candidatos));
            }
            
            // ETAPA 3: Busca por estrutura
            if (empty($candidatos)) {
                error_log("   ⚠️ Buscando por estrutura...");
                
                $numPalavras = count($partesNome);
                $tamNome = strlen(str_replace(' ', '', normalizarNome($nomeBusca)));
                
                $sql = "SELECT a.id, a.nome, a.rg, a.cpf, a.situacao, a.email, a.telefone,
                               REGEXP_REPLACE(a.rg, '[^0-9]', '') as rg_limpo,
                               REGEXP_REPLACE(a.cpf, '[^0-9]', '') as cpf_limpo,
                               m.corporacao, m.patente, m.categoria, m.lotacao,
                               f.situacaoFinanceira
                        FROM Associados a
                        LEFT JOIN Militar m ON a.id = m.associado_id
                        LEFT JOIN Financeiro f ON a.id = f.associado_id
                        WHERE LENGTH(a.nome) - LENGTH(REPLACE(a.nome, ' ', '')) + 1 BETWEEN ? AND ?
                          AND LENGTH(REGEXP_REPLACE(a.nome, '[^a-zA-Z]', '')) BETWEEN ? AND ?
                        LIMIT 300";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    max(1, $numPalavras - 1),
                    $numPalavras + 2,
                    max(1, $tamNome - 8),
                    $tamNome + 8
                ]);
                $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("   📋 Candidatos por estrutura: " . count($candidatos));
            }
            
            // CALCULAR SIMILARIDADE
            if (!empty($candidatos)) {
                $melhorMatch = null;
                $melhorScore = 0;
                $melhorSimilaridade = null;
                
                error_log("   🔍 Analisando " . count($candidatos) . " candidatos...");
                
                $todosScores = [];
                
                foreach ($candidatos as $candidato) {
                    $similaridade = calcularSimilaridadeFonetica($nomeBusca, $candidato['nome']);
                    
                    $todosScores[] = [
                        'nome' => $candidato['nome'],
                        'score' => $similaridade['score_total'],
                        'detalhes' => $similaridade
                    ];
                    
                    if ($similaridade['score_total'] > $melhorScore) {
                        $melhorMatch = $candidato;
                        $melhorScore = $similaridade['score_total'];
                        $melhorSimilaridade = $similaridade;
                        $melhorMatch['similaridade'] = $similaridade;
                    }
                }
                
                usort($todosScores, fn($a, $b) => $b['score'] <=> $a['score']);
                $top10 = array_slice($todosScores, 0, 10);
                
                error_log("   📊 TOP 10 CANDIDATOS:");
                foreach ($top10 as $idx => $item) {
                    $pos = $idx + 1;
                    error_log("      #{$pos} '{$item['nome']}' = {$item['score']}% " . 
                             "(fon:{$item['detalhes']['score_fonetico']}%, " .
                             "meta:{$item['detalhes']['score_metaphone']}%)");
                }
                
                // THRESHOLD MUITO RESTRITIVO: 90% para match confirmado
                // Apenas variações fonéticas mínimas: José/Jose, Silva/Sylva, Souza/Sousa, etc.
                if ($melhorMatch && $melhorScore >= 90) {
                    $nomeKey = strtolower(trim($nomeBusca));
                    $melhorMatch['found_by'] = 'NOME';
                    $melhorMatch['tipo_match'] = 'fonetico_avancado';
                    
                    $associadosEncontrados['nome_' . md5($nomeKey)] = $melhorMatch;
                    $associadosEncontrados['nome_original_' . $nomeKey] = $melhorMatch;
                    
                    $estatisticasBusca['encontrados_por_nome']++;
                    error_log("✅ MATCH FONÉTICO: '$nomeBusca' -> '{$melhorMatch['nome']}' (score: {$melhorScore}%)");
                    error_log("   📈 Breakdown: normal={$melhorSimilaridade['score_normal']}%, " .
                             "fon={$melhorSimilaridade['score_fonetico']}%, " .
                             "meta={$melhorSimilaridade['score_metaphone']}%");
                } 
                // Aviso para escrita diferente: 85-89% (faixa muito estreita!)
                else if ($melhorMatch && $melhorScore >= 85) {
                    $nomeKey = strtolower(trim($nomeBusca));
                    
                    if (!isset($associadosEncontrados['avisos_escrita_diferente'])) {
                        $associadosEncontrados['avisos_escrita_diferente'] = [];
                    }
                    
                    $associadosEncontrados['avisos_escrita_diferente'][$nomeKey] = [
                        'nome_buscado' => $nomeBusca,
                        'nome_encontrado' => $melhorMatch['nome'],
                        'score' => $melhorScore,
                        'detalhes' => $melhorSimilaridade,
                        'associado' => $melhorMatch
                    ];
                    
                    error_log("⚠️ ESCRITA DIFERENTE: '$nomeBusca' -> '{$melhorMatch['nome']}' (score: {$melhorScore}%)");
                    error_log("   📝 Muito similar, mas abaixo do threshold de confirmação (90%)");
                } 
                else {
                    if ($melhorMatch) {
                        error_log("⚠️ MELHOR CANDIDATO ABAIXO DO THRESHOLD:");
                        error_log("   Nome: '{$melhorMatch['nome']}'");
                        error_log("   Score: {$melhorScore}% (threshold: 90%)");
                    }
                    error_log("❌ Sem match aceitável para: '$nomeBusca'");
                }
            } else {
                error_log("❌ Nenhum candidato encontrado para: '$nomeBusca'");
            }
        }
    }
    
    $estatisticasBusca['total_encontrados'] = $estatisticasBusca['encontrados_por_rg'] + 
                                               $estatisticasBusca['encontrados_por_cpf'] + 
                                               $estatisticasBusca['encontrados_por_nome'];
    
    // ========== PROCESSAR RESULTADOS ==========
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
            'situacaofinanceira' => null,
            'aviso_escrita_diferente' => null  // NOVO
        ];
        
        $encontrou = false;
        
        // Tentar RG
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
                    break;
                }
            }
        }
        
        // Tentar CPF
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
                        break;
                    }
                }
            }
        }
        
        // Tentar Nome
        if (!$encontrou && !empty($item['nome'])) {
            $nomeBusca = trim($item['nome']);
            $nomeKey = strtolower($nomeBusca);
            
            if (isset($associadosEncontrados['nome_' . md5($nomeKey)])) {
                $associado = $associadosEncontrados['nome_' . md5($nomeKey)];
                $resultado = preencherDadosAssociado($resultado, $associado, 'NOME');
                $encontrou = true;
            } else if (isset($associadosEncontrados['nome_original_' . $nomeKey])) {
                $associado = $associadosEncontrados['nome_original_' . $nomeKey];
                $resultado = preencherDadosAssociado($resultado, $associado, 'NOME');
                $encontrou = true;
            }
            // NOVO: Verificar aviso de escrita diferente
            else if (isset($associadosEncontrados['avisos_escrita_diferente'][$nomeKey])) {
                $aviso = $associadosEncontrados['avisos_escrita_diferente'][$nomeKey];
                $resultado['aviso_escrita_diferente'] = [
                    'mensagem' => 'Nome com escrita diferente encontrado',
                    'nome_lista' => $aviso['nome_buscado'],
                    'nome_banco' => $aviso['nome_encontrado'],
                    'similaridade' => round($aviso['score'], 1) . '%',
                    'associado_id' => $aviso['associado']['id'],
                    'rg_associado' => $aviso['associado']['rg'],
                    'cpf_associado' => $aviso['associado']['cpf']
                ];
            }
        }
        
        $resultados[] = $resultado;
    }
    
    // ========== ESTATÍSTICAS ==========
    $estatisticas = [
        'total' => count($resultados),
        'filiados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'filiado')),
        'naofiliados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'naofiliado')),
        'naoencontrados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'naoencontrado')),
        'com_avisos' => count(array_filter($resultados, fn($r) => $r['aviso_escrita_diferente'] !== null)),
        'encontrados_por_rg' => count(array_filter($resultados, fn($r) => $r['encontrado_por'] === 'RG')),
        'encontrados_por_cpf' => count(array_filter($resultados, fn($r) => $r['encontrado_por'] === 'CPF')),
        'encontrados_por_nome' => count(array_filter($resultados, fn($r) => $r['encontrado_por'] === 'NOME'))
    ];
    
    echo json_encode([
        'success' => true,
        'message' => count($resultados) . ' registros processados (threshold 90%, avisos apenas para 85-89%)',
        'resultados' => $resultados,
        'estatisticas' => $estatisticas,
        'detalhes_busca' => $estatisticasBusca
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("💥 ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

error_log("=== FIM API ===");
?>