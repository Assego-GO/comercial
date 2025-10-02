<?php
// API Verificar Associados - BUSCA FONÉTICA AVANÇADA PORTUGUÊS BRASILEIRO
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
error_log("=== INÍCIO API VERIFICAR ASSOCIADOS - BUSCA FONÉTICA PT-BR ===");

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
    
    // ========== FUNÇÕES DE VALIDAÇÃO RG E CPF ==========
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
    
    // ========== ALGORITMO DE NORMALIZAÇÃO FONÉTICA PORTUGUÊS BRASILEIRO ==========
    
    /**
     * Normalização básica: remove acentos e caracteres especiais
     */
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
    
    /**
     * ALGORITMO FONÉTICO AVANÇADO PARA PORTUGUÊS BRASILEIRO
     * Converte nomes para representação fonética, considerando:
     * - Sons equivalentes (K=C=QU, PH=F, W=V, Y=I)
     * - Variações de escrita (S/SS/Ç, Z/S, X/CH)
     * - Dígrafos (LH, NH, RR, SS)
     * - H mudo
     * - Terminações nasais
     */
    function normalizarFoneticoPTBR($nome) {
        $nome = normalizarNome($nome);
        
        // Aplicar regras fonéticas do português brasileiro em ordem específica
        $regras = [
            // 1. H inicial ou após espaço (mudo)
            '/^h/' => '',
            '/\sh/' => ' ',
            
            // 2. Dígrafos (processar antes de letras individuais)
            '/lh/' => 'l',
            '/nh/' => 'n',
            '/rr/' => 'r',
            '/ss/' => 's',
            '/ch/' => 'x',
            
            // 3. QU antes de E ou I vira K
            '/qu([ei])/' => 'k$1',
            '/qu/' => 'k',
            
            // 4. C antes de E ou I vira S
            '/c([ei])/' => 's$1',
            
            // 5. Ç sempre vira S
            '/ç/' => 's',
            
            // 6. K sempre vira C
            '/k/' => 'c',
            
            // 7. PH vira F
            '/ph/' => 'f',
            
            // 8. W vira V
            '/w/' => 'v',
            
            // 9. Y vira I
            '/y/' => 'i',
            
            // 10. X tem vários sons, padronizar para X
            '/x/' => 'x',
            
            // 11. Z no final ou antes de consoante vira S
            '/z$/' => 's',
            '/z([bcdfghjklmnpqrstvwxz])/' => 's$1',
            
            // 12. G antes de E ou I vira J
            '/g([ei])/' => 'j$1',
            
            // 13. Remover vogais duplicadas
            '/aa/' => 'a',
            '/ee/' => 'e',
            '/ii/' => 'i',
            '/oo/' => 'o',
            '/uu/' => 'u',
            
            // 14. Simplificar consoantes duplicadas (exceto RR e SS já tratados)
            '/([bcdfghjklmnpqrstvwxz])\1/' => '$1',
        ];
        
        foreach ($regras as $pattern => $replacement) {
            $nome = preg_replace($pattern, $replacement, $nome);
        }
        
        return $nome;
    }
    
    /**
     * METAPHONE ADAPTADO PARA PORTUGUÊS
     * Gera código fonético mais agressivo para matching
     */
    function metaphonePTBR($nome) {
        $nome = normalizarFoneticoPTBR($nome);
        
        // Remover vogais não acentuadas (exceto no início)
        $partes = explode(' ', $nome);
        $resultado = [];
        
        foreach ($partes as $parte) {
            if (strlen($parte) <= 2) {
                $resultado[] = $parte;
                continue;
            }
            
            // Manter primeira letra
            $codigo = substr($parte, 0, 1);
            
            // Processar resto removendo vogais
            $resto = substr($parte, 1);
            $resto = preg_replace('/[aeiou]/', '', $resto);
            
            $codigo .= $resto;
            $resultado[] = $codigo;
        }
        
        return implode(' ', $resultado);
    }
    
    /**
     * SOUNDEX ADAPTADO PARA PORTUGUÊS
     * Algoritmo clássico adaptado para fonética PT-BR
     */
    function soundexPTBR($nome) {
        $nome = normalizarFoneticoPTBR($nome);
        $partes = explode(' ', $nome);
        $resultado = [];
        
        foreach ($partes as $parte) {
            if (strlen($parte) < 2) {
                $resultado[] = $parte;
                continue;
            }
            
            // Manter primeira letra
            $codigo = strtoupper(substr($parte, 0, 1));
            
            // Mapear consoantes para números
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
            
            // Completar com zeros até 4 caracteres
            $codigo = str_pad(substr($codigo, 0, 4), 4, '0');
            $resultado[] = $codigo;
        }
        
        return implode(' ', $resultado);
    }
    
    /**
     * CÁLCULO DE SIMILARIDADE COMBINADA - VERSÃO OTIMIZADA
     * Usa múltiplos algoritmos para calcular score de similaridade
     */
    function calcularSimilaridadeFonetica($nome1, $nome2) {
        // 1. Normalização simples (Levenshtein)
        $norm1 = normalizarNome($nome1);
        $norm2 = normalizarNome($nome2);
        
        // Truncar strings muito longas para Levenshtein (limite de 255 caracteres)
        $norm1_trunc = substr($norm1, 0, 255);
        $norm2_trunc = substr($norm2, 0, 255);
        
        $distNorm = levenshtein($norm1_trunc, $norm2_trunc);
        $maxLenNorm = max(strlen($norm1), strlen($norm2));
        $simNorm = $maxLenNorm > 0 ? (1 - $distNorm / $maxLenNorm) * 100 : 0;
        
        // 2. Normalização fonética (Levenshtein)
        $fon1 = normalizarFoneticoPTBR($nome1);
        $fon2 = normalizarFoneticoPTBR($nome2);
        
        $fon1_trunc = substr($fon1, 0, 255);
        $fon2_trunc = substr($fon2, 0, 255);
        
        $distFon = levenshtein($fon1_trunc, $fon2_trunc);
        $maxLenFon = max(strlen($fon1), strlen($fon2));
        $simFon = $maxLenFon > 0 ? (1 - $distFon / $maxLenFon) * 100 : 0;
        
        // 3. Metaphone PT-BR
        $meta1 = metaphonePTBR($nome1);
        $meta2 = metaphonePTBR($nome2);
        
        $meta1_trunc = substr($meta1, 0, 255);
        $meta2_trunc = substr($meta2, 0, 255);
        
        $distMeta = levenshtein($meta1_trunc, $meta2_trunc);
        $maxLenMeta = max(strlen($meta1), strlen($meta2));
        $simMeta = $maxLenMeta > 0 ? (1 - $distMeta / $maxLenMeta) * 100 : 0;
        
        // 4. Soundex PT-BR
        $sound1 = soundexPTBR($nome1);
        $sound2 = soundexPTBR($nome2);
        $simSound = ($sound1 === $sound2) ? 100 : 0;
        
        // 5. Similaridade de palavras individuais (OTIMIZADO)
        $palavras1 = explode(' ', $norm1);
        $palavras2 = explode(' ', $norm2);
        $matchesPalavras = 0;
        $totalPalavras = max(count($palavras1), count($palavras2));
        
        // Filtrar palavras muito curtas
        $palavras1 = array_filter($palavras1, fn($p) => strlen($p) >= 2);
        $palavras2 = array_filter($palavras2, fn($p) => strlen($p) >= 2);
        
        foreach ($palavras1 as $p1) {
            $melhorMatchPalavra = 0;
            foreach ($palavras2 as $p2) {
                $maxLen = max(strlen($p1), strlen($p2));
                if ($maxLen === 0) continue;
                
                $simPalavra = (similar_text($p1, $p2) / $maxLen) * 100;
                $melhorMatchPalavra = max($melhorMatchPalavra, $simPalavra);
                
                if ($simPalavra > 70) {
                    $matchesPalavras++;
                    break;
                }
            }
        }
        
        $simPalavras = $totalPalavras > 0 ? ($matchesPalavras / $totalPalavras) * 100 : 0;
        
        // 6. NOVO: Similaridade por iniciais de palavras
        $iniciais1 = implode('', array_map(fn($p) => substr($p, 0, 1), $palavras1));
        $iniciais2 = implode('', array_map(fn($p) => substr($p, 0, 1), $palavras2));
        $simIniciais = ($iniciais1 === $iniciais2) ? 100 : 0;
        
        // 7. NOVO: Similar_text global (complementar ao Levenshtein)
        $similarTextScore = 0;
        if ($maxLenNorm > 0) {
            similar_text($norm1, $norm2, $percentSimilar);
            $similarTextScore = $percentSimilar;
        }
        
        // Combinar scores com pesos AJUSTADOS
        $scoreTotal = (
            $simNorm * 0.15 +           // 15% - similaridade texto normal
            $simFon * 0.30 +            // 30% - similaridade fonética
            $simMeta * 0.15 +           // 15% - metaphone
            $simSound * 0.05 +          // 5% - soundex
            $simPalavras * 0.20 +       // 20% - match de palavras (aumentado!)
            $simIniciais * 0.05 +       // 5% - iniciais
            $similarTextScore * 0.10    // 10% - similar_text global
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
    
    // ========== COLETAR RGs, CPFs E NOMES ==========
    $rgsParaBuscar = [];
    $cpfsParaBuscar = [];
    $nomesParaBuscar = [];
    $dadosIndexados = [];
    
    foreach ($data['dados'] as $index => $item) {
        // Processar RGs
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
        
        // Processar CPFs
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
        
        // Processar Nomes
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
    
    // ========== BUSCA FONÉTICA AVANÇADA POR NOME - VERSÃO ROBUSTA ==========
    if (!empty($nomesParaBuscar)) {
        error_log("🔍 BUSCA FONÉTICA AVANÇADA - " . count($nomesParaBuscar) . " nomes");
        
        foreach ($nomesParaBuscar as $nomeBusca) {
            error_log("🔍 Buscando: '$nomeBusca'");
            
            $candidatos = [];
            
            // ===== ETAPA 1: BUSCA EXATA (MAIS RÁPIDA) =====
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
                continue; // Próximo nome
            }
            
            // ===== ETAPA 2: BUSCA POR PARTES DO NOME =====
            $partesNome = explode(' ', normalizarNome($nomeBusca));
            $partesNome = array_filter($partesNome, fn($p) => strlen($p) >= 3);
            
            if (!empty($partesNome)) {
                $conditions = [];
                $params = [];
                
                // Buscar por CADA palavra individualmente
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
            
            // ===== ETAPA 3: SE AINDA NÃO ACHOU, BUSCA FONÉTICA AMPLA =====
            if (empty($candidatos)) {
                error_log("   ⚠️ Nenhum candidato por palavras, buscando foneticamente...");
                
                // Extrair primeira letra de cada palavra significativa
                $iniciais = '';
                foreach ($partesNome as $parte) {
                    $iniciais .= substr($parte, 0, 1);
                }
                
                // Buscar nomes que comecem com as mesmas iniciais OU tenham tamanho similar
                $numPalavras = count($partesNome);
                $tamNome = strlen(str_replace(' ', '', normalizarNome($nomeBusca)));
                
                $sql = "SELECT a.id, a.nome, a.rg, a.cpf, a.situacao, a.email, a.telefone,
                               REGEXP_REPLACE(a.rg, '[^0-9]', '') as rg_limpo,
                               REGEXP_REPLACE(a.cpf, '[^0-9]', '') as cpf_limpo,
                               m.corporacao, m.patente, m.categoria, m.lotacao,
                               f.situacaoFinanceira,
                               LENGTH(REGEXP_REPLACE(a.nome, '[^a-zA-Z]', '')) as tam_nome
                        FROM Associados a
                        LEFT JOIN Militar m ON a.id = m.associado_id
                        LEFT JOIN Financeiro f ON a.id = f.associado_id
                        WHERE LENGTH(a.nome) - LENGTH(REPLACE(a.nome, ' ', '')) + 1 BETWEEN ? AND ?
                          AND LENGTH(REGEXP_REPLACE(a.nome, '[^a-zA-Z]', '')) BETWEEN ? AND ?
                        LIMIT 300";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    max(1, $numPalavras - 1),  // Mínimo de palavras
                    $numPalavras + 2,           // Máximo de palavras
                    max(1, $tamNome - 8),       // Mínimo tamanho
                    $tamNome + 8                // Máximo tamanho
                ]);
                $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("   📋 Candidatos por estrutura: " . count($candidatos));
            }
            
            // ===== ETAPA 4: FALLBACK FINAL - BUSCAR TODOS SE NECESSÁRIO =====
            if (empty($candidatos)) {
                error_log("   ⚠️ FALLBACK: Buscando em toda a base...");
                
                $sql = "SELECT a.id, a.nome, a.rg, a.cpf, a.situacao, a.email, a.telefone,
                               REGEXP_REPLACE(a.rg, '[^0-9]', '') as rg_limpo,
                               REGEXP_REPLACE(a.cpf, '[^0-9]', '') as cpf_limpo,
                               m.corporacao, m.patente, m.categoria, m.lotacao,
                               f.situacaoFinanceira
                        FROM Associados a
                        LEFT JOIN Militar m ON a.id = m.associado_id
                        LEFT JOIN Financeiro f ON a.id = f.associado_id
                        LIMIT 500";
                
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("   📋 Candidatos fallback (toda base): " . count($candidatos));
            }
            
            // ===== CALCULAR SIMILARIDADE FONÉTICA PARA TODOS OS CANDIDATOS =====
            if (!empty($candidatos)) {
                $melhorMatch = null;
                $melhorScore = 0;
                $melhorSimilaridade = null;
                
                error_log("   🔍 Analisando " . count($candidatos) . " candidatos...");
                
                // Limitar logs para não sobrecarregar (mostrar apenas top 10 melhores)
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
                
                // Ordenar e mostrar top 10
                usort($todosScores, fn($a, $b) => $b['score'] <=> $a['score']);
                $top10 = array_slice($todosScores, 0, 10);
                
                error_log("   📊 TOP 10 CANDIDATOS:");
                foreach ($top10 as $idx => $item) {
                    $pos = $idx + 1;
                    error_log("      #{$pos} '{$item['nome']}' = {$item['score']}% " . 
                             "(normal:{$item['detalhes']['score_normal']}%, " .
                             "fon:{$item['detalhes']['score_fonetico']}%, " .
                             "palavras:{$item['detalhes']['score_palavras']}%)");
                }
                
                // THRESHOLD REDUZIDO: Aceitar match se score >= 55%
                if ($melhorMatch && $melhorScore >= 55) {
                    $nomeKey = strtolower(trim($nomeBusca));
                    $melhorMatch['found_by'] = 'NOME';
                    $melhorMatch['tipo_match'] = 'fonetico_avancado';
                    
                    $associadosEncontrados['nome_' . md5($nomeKey)] = $melhorMatch;
                    $associadosEncontrados['nome_original_' . $nomeKey] = $melhorMatch;
                    
                    $estatisticasBusca['encontrados_por_nome']++;
                    error_log("✅ MATCH FONÉTICO: '$nomeBusca' -> '{$melhorMatch['nome']}' (score: {$melhorScore}%)");
                    error_log("   📈 Breakdown: normal={$melhorSimilaridade['score_normal']}%, " .
                             "fon={$melhorSimilaridade['score_fonetico']}%, " .
                             "meta={$melhorSimilaridade['score_metaphone']}%, " .
                             "palavras={$melhorSimilaridade['score_palavras']}%, " .
                             "iniciais={$melhorSimilaridade['score_iniciais']}%");
                } else {
                    if ($melhorMatch) {
                        error_log("⚠️ MELHOR CANDIDATO ABAIXO DO THRESHOLD:");
                        error_log("   Nome: '{$melhorMatch['nome']}'");
                        error_log("   Score: {$melhorScore}% (threshold: 55%)");
                        error_log("   Breakdown: normal={$melhorSimilaridade['score_normal']}%, " .
                                 "fon={$melhorSimilaridade['score_fonetico']}%, " .
                                 "meta={$melhorSimilaridade['score_metaphone']}%, " .
                                 "palavras={$melhorSimilaridade['score_palavras']}%");
                        error_log("   Nome buscado normalizado: '{$melhorSimilaridade['nome1_normalizado']}'");
                        error_log("   Nome candidato normalizado: '{$melhorSimilaridade['nome2_normalizado']}'");
                        error_log("   Nome buscado fonético: '{$melhorSimilaridade['nome1_fonetico']}'");
                        error_log("   Nome candidato fonético: '{$melhorSimilaridade['nome2_fonetico']}'");
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
            'situacaofinanceira' => null
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
        
        // Tentar Nome (busca fonética)
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
        }
        
        $resultados[] = $resultado;
    }
    
    // ========== ESTATÍSTICAS ==========
    $estatisticas = [
        'total' => count($resultados),
        'filiados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'filiado')),
        'naofiliados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'naofiliado')),
        'naoencontrados' => count(array_filter($resultados, fn($r) => $r['statusverificacao'] === 'naoencontrado')),
        'encontrados_por_rg' => count(array_filter($resultados, fn($r) => $r['encontrado_por'] === 'RG')),
        'encontrados_por_cpf' => count(array_filter($resultados, fn($r) => $r['encontrado_por'] === 'CPF')),
        'encontrados_por_nome' => count(array_filter($resultados, fn($r) => $r['encontrado_por'] === 'NOME')),
        'rgs_processados' => count($rgsParaBuscar),
        'cpfs_processados' => count($cpfsParaBuscar),
        'nomes_processados' => count($nomesParaBuscar)
    ];
    
    echo json_encode([
        'success' => true,
        'message' => count($resultados) . ' registros processados com busca fonética avançada PT-BR',
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