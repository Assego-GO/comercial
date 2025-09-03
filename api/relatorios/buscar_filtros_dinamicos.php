<?php
/**
 * API para buscar filtros dinâmicos dos relatórios
 * api/relatorios/buscar_filtros_dinamicos.php
 * 
 * Retorna valores únicos de corporações, patentes e lotações
 * diretamente do banco de dados
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Tipo de filtro solicitado
    $tipo = $_GET['tipo'] ?? 'todos';
    
    $resultado = [];
    
    // Buscar corporações únicas
    if ($tipo === 'corporacao' || $tipo === 'todos') {
        $stmt = $db->prepare("
            SELECT DISTINCT corporacao 
            FROM Militar 
            WHERE corporacao IS NOT NULL 
            AND corporacao != '' 
            AND corporacao NOT IN ('', '0', 'NULL')
            ORDER BY corporacao
        ");
        $stmt->execute();
        $corporacoes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Normalizar e remover duplicatas case-insensitive
        $corporacoesUnicas = [];
        $corporacoesLower = [];
        
        foreach ($corporacoes as $corp) {
            $corpLower = strtolower(trim($corp));
            if (!in_array($corpLower, $corporacoesLower)) {
                $corporacoesLower[] = $corpLower;
                $corporacoesUnicas[] = trim($corp);
            }
        }
        
        $resultado['corporacoes'] = $corporacoesUnicas;
    }
    
    // Buscar patentes únicas
    if ($tipo === 'patente' || $tipo === 'todos') {
        $stmt = $db->prepare("
            SELECT DISTINCT patente 
            FROM Militar 
            WHERE patente IS NOT NULL 
            AND patente != '' 
            AND patente NOT IN ('', '0', 'NULL')
        ");
        $stmt->execute();
        $patentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Normalizar patentes e remover duplicatas
        $patentesUnicas = [];
        $patentesNormalizadas = [];
        
        foreach ($patentes as $patente) {
            $patente = trim($patente);
            if (empty($patente)) continue;
            
            // Normalizar para comparação (remover espaços, hífens)
            $patenteNormalizada = strtolower(str_replace(['-', ' '], '', $patente));
            
            if (!in_array($patenteNormalizada, $patentesNormalizadas)) {
                $patentesNormalizadas[] = $patenteNormalizada;
                $patentesUnicas[] = $patente;
            }
        }
        
        // Ordenar patentes hierarquicamente
        usort($patentesUnicas, function($a, $b) {
            $ordem = [
                'aluno' => 1,
                'soldado' => 2,
                'cabo' => 3,
                'terceiro' => 4,
                'segundo' => 5,
                'primeiro' => 6,
                'subtenente' => 7,
                'suboficial' => 8,
                'aspirante' => 9,
                'tenente' => 10,
                'capitão' => 11,
                'capitao' => 11,
                'major' => 12,
                'coronel' => 13
            ];
            
            $aLower = strtolower($a);
            $bLower = strtolower($b);
            
            $aOrder = 99;
            $bOrder = 99;
            
            foreach ($ordem as $key => $value) {
                if (strpos($aLower, $key) !== false) {
                    $aOrder = $value;
                    break;
                }
            }
            
            foreach ($ordem as $key => $value) {
                if (strpos($bLower, $key) !== false) {
                    $bOrder = $value;
                    break;
                }
            }
            
            if ($aOrder === $bOrder) {
                return strcasecmp($a, $b);
            }
            
            return $aOrder - $bOrder;
        });
        
        $resultado['patentes'] = $patentesUnicas;
    }
    
    // Buscar lotações únicas (limitado às mais usadas)
    if ($tipo === 'lotacao' || $tipo === 'todos') {
        $limite = intval($_GET['limite'] ?? 100);
        
        $stmt = $db->prepare("
            SELECT lotacao, COUNT(*) as total 
            FROM Militar 
            WHERE lotacao IS NOT NULL 
            AND lotacao != '' 
            AND lotacao NOT IN ('', '0', 'NULL')
            GROUP BY lotacao 
            ORDER BY total DESC, lotacao ASC
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        $lotacoes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lotacoes[] = [
                'valor' => trim($row['lotacao']),
                'total' => intval($row['total'])
            ];
        }
        
        $resultado['lotacoes'] = $lotacoes;
        
        // Adicionar total de lotações únicas
        $stmtTotal = $db->prepare("
            SELECT COUNT(DISTINCT lotacao) as total 
            FROM Militar 
            WHERE lotacao IS NOT NULL 
            AND lotacao != '' 
            AND lotacao NOT IN ('', '0', 'NULL')
        ");
        $stmtTotal->execute();
        $resultado['total_lotacoes'] = intval($stmtTotal->fetch(PDO::FETCH_ASSOC)['total']);
    }
    
    // Buscar estatísticas adicionais
    if ($tipo === 'estatisticas' || $tipo === 'todos') {
        // Total de associados com dados militares
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT associado_id) as total 
            FROM Militar 
            WHERE (corporacao IS NOT NULL AND corporacao != '')
               OR (patente IS NOT NULL AND patente != '')
               OR (lotacao IS NOT NULL AND lotacao != '')
        ");
        $stmt->execute();
        $resultado['total_militares'] = intval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
        
        // Distribuição por corporação
        $stmt = $db->prepare("
            SELECT corporacao, COUNT(*) as total 
            FROM Militar 
            WHERE corporacao IS NOT NULL 
            AND corporacao != '' 
            GROUP BY corporacao 
            ORDER BY total DESC
        ");
        $stmt->execute();
        $resultado['distribuicao_corporacao'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Adicionar timestamp para cache
    $resultado['timestamp'] = date('Y-m-d H:i:s');
    $resultado['success'] = true;
    
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Erro ao buscar filtros dinâmicos: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar filtros',
        'error' => $e->getMessage()
    ]);
}
?>