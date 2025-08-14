<?php
/**
 * Script para Descobrir Valores de Parentesco
 * debug/descobrir_parentesco.php
 * 
 * Descobre quais valores são usados no campo parentesco do banco real
 */

echo "🔍 DESCOBRINDO VALORES DE PARENTESCO\n";
echo "====================================\n\n";

try {
    // Configuração de conexão (ajuste conforme necessário)
    $host = 'localhost';
    $dbname = 'superuser'; 
    $username = 'wwasse_cadastro';         
    $password = 'senhaForte123';             
    
    echo "Conectando ao banco: $dbname@$host...\n";
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conectado com sucesso!\n\n";
    
    // Descobre todos os valores únicos de parentesco
    echo "📋 VALORES ÚNICOS NO CAMPO 'parentesco':\n";
    echo "========================================\n";
    
    $stmt = $pdo->query("
        SELECT 
            parentesco,
            COUNT(*) as quantidade,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM Dependentes)), 2) as percentual
        FROM Dependentes 
        WHERE parentesco IS NOT NULL 
        AND parentesco != ''
        GROUP BY parentesco 
        ORDER BY quantidade DESC
    ");
    
    $parentescos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalDependentes = 0;
    foreach ($parentescos as $p) {
        $totalDependentes += $p['quantidade'];
    }
    
    echo "Total de dependentes com parentesco informado: $totalDependentes\n\n";
    
    foreach ($parentescos as $p) {
        $barra = str_repeat("█", min(50, round($p['percentual'])));
        echo sprintf(
            "%-20s | %6d (%5.1f%%) %s\n",
            "'{$p['parentesco']}'",
            $p['quantidade'],
            $p['percentual'],
            $barra
        );
    }
    
    echo "\n🎯 IDENTIFICANDO FILHOS(AS):\n";
    echo "============================\n";
    
    // Procura por padrões que podem ser filhos
    $padroes_filhos = [
        'filho', 'filha', 'child', 'son', 'daughter',
        'dependente', 'menor', 'criança', 'jovem',
        'herdeiro', 'prole', 'descendente'
    ];
    
    $possiveis_filhos = [];
    
    foreach ($parentescos as $p) {
        $parentesco_lower = strtolower($p['parentesco']);
        
        foreach ($padroes_filhos as $padrao) {
            if (strpos($parentesco_lower, $padrao) !== false) {
                $possiveis_filhos[] = $p;
                break;
            }
        }
    }
    
    if (count($possiveis_filhos) > 0) {
        echo "✅ Possíveis valores que representam filhos(as):\n\n";
        
        $total_filhos = 0;
        foreach ($possiveis_filhos as $pf) {
            echo "• '{$pf['parentesco']}' - {$pf['quantidade']} registros\n";
            $total_filhos += $pf['quantidade'];
        }
        
        echo "\nTotal de possíveis filhos(as): $total_filhos\n";
        
        // Gera query atualizada
        echo "\n📝 QUERY SUGERIDA PARA O SISTEMA:\n";
        echo "=================================\n";
        
        $valores_parentesco = array_map(function($pf) {
            return "'" . $pf['parentesco'] . "'";
        }, $possiveis_filhos);
        
        echo "WHERE parentesco IN (" . implode(', ', $valores_parentesco) . ")\n";
        
    } else {
        echo "❌ Nenhum padrão óbvio de 'filho(a)' encontrado.\n";
        echo "Verifique manualmente os valores acima.\n";
    }
    
    // Mostra alguns exemplos de dependentes para análise manual
    echo "\n🔍 EXEMPLOS DE DEPENDENTES PARA ANÁLISE:\n";
    echo "========================================\n";
    
    $stmt = $pdo->query("
        SELECT 
            d.nome,
            d.parentesco,
            d.data_nascimento,
            TIMESTAMPDIFF(YEAR, d.data_nascimento, CURDATE()) as idade,
            a.nome as responsavel
        FROM Dependentes d
        INNER JOIN Associados a ON d.associado_id = a.id
        WHERE d.data_nascimento IS NOT NULL
        ORDER BY RAND()
        LIMIT 10
    ");
    
    $exemplos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($exemplos as $ex) {
        echo sprintf(
            "• %s (%d anos) - Parentesco: '%s' - Responsável: %s\n",
            $ex['nome'],
            $ex['idade'],
            $ex['parentesco'],
            $ex['responsavel']
        );
    }
    
    // Analisa idades para identificar possíveis filhos
    echo "\n📊 ANÁLISE POR IDADE (para identificar filhos):\n";
    echo "===============================================\n";
    
    $stmt = $pdo->query("
        SELECT 
            parentesco,
            COUNT(*) as total,
            AVG(TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE())) as idade_media,
            MIN(TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE())) as idade_min,
            MAX(TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE())) as idade_max
        FROM Dependentes 
        WHERE data_nascimento IS NOT NULL
        AND parentesco IS NOT NULL
        GROUP BY parentesco
        HAVING COUNT(*) >= 10
        ORDER BY idade_media ASC
    ");
    
    $analise_idade = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Parentesco com idade média baixa (prováveis filhos):\n\n";
    
    foreach ($analise_idade as $ai) {
        if ($ai['idade_media'] <= 25) { // Foco em parentescos com idade média baixa
            echo sprintf(
                "• %-20s | Média: %4.1f anos | Min: %2d | Max: %2d | Total: %4d\n",
                "'{$ai['parentesco']}'",
                $ai['idade_media'],
                $ai['idade_min'],
                $ai['idade_max'],
                $ai['total']
            );
        }
    }
    
    echo "\n💡 RECOMENDAÇÕES:\n";
    echo "=================\n";
    echo "1. Identifique os valores de parentesco que representam filhos(as)\n";
    echo "2. Atualize a query no sistema para usar esses valores\n";
    echo "3. Teste novamente o sistema com os valores corretos\n";
    echo "4. Considere padronizar os valores de parentesco no futuro\n";
    
    echo "\n✅ Análise concluída!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Descoberta finalizada em " . date('H:i:s') . "\n";
?>