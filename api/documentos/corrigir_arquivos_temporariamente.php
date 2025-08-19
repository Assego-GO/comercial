<?php
/**
 * CORREÇÃO TEMPORÁRIA - CRIAR ARQUIVOS DE TESTE
 * api/documentos/corrigir_arquivos_temporario.php
 */

// Headers para HTML
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>🔧 Correção Temporária</title>";
echo "<style>body{font-family:Arial;margin:20px;}.ok{color:green;}.erro{color:red;}.info{color:blue;}</style>";
echo "</head><body>";

echo "<h1>🔧 Correção Temporária - Criando Arquivos de Teste</h1>";

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Busca todos os documentos
    $sql = "SELECT id, associado_id, nome_arquivo, caminho_arquivo, tipo_origem FROM Documentos_Associado";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p class='info'>📋 Encontrados " . count($documentos) . " documentos na base</p>";
    
    $criados = 0;
    $erros = 0;
    
    foreach ($documentos as $doc) {
        $caminhoCompleto = "../../" . $doc['caminho_arquivo'];
        
        echo "<hr>";
        echo "<strong>ID {$doc['id']}</strong> - {$doc['nome_arquivo']}<br>";
        echo "Caminho: {$doc['caminho_arquivo']}<br>";
        
        if (file_exists($caminhoCompleto)) {
            echo "<span class='ok'>✓ Arquivo já existe</span><br>";
            continue;
        }
        
        // Cria diretório se necessário
        $diretorio = dirname($caminhoCompleto);
        if (!is_dir($diretorio)) {
            if (mkdir($diretorio, 0755, true)) {
                echo "<span class='ok'>✓ Diretório criado: {$diretorio}</span><br>";
            } else {
                echo "<span class='erro'>❌ Erro ao criar diretório: {$diretorio}</span><br>";
                $erros++;
                continue;
            }
        }
        
        // Determina o tipo de arquivo de teste a criar
        $extensao = strtolower(pathinfo($doc['nome_arquivo'], PATHINFO_EXTENSION));
        
        if ($extensao === 'pdf') {
            // Cria um PDF simples de teste
            $conteudoPDF = createSimplePDF($doc);
            if (file_put_contents($caminhoCompleto, $conteudoPDF)) {
                echo "<span class='ok'>✓ PDF de teste criado</span><br>";
                $criados++;
            } else {
                echo "<span class='erro'>❌ Erro ao criar PDF</span><br>";
                $erros++;
            }
        } else {
            // Cria arquivo de texto simples
            $conteudo = "ARQUIVO DE TESTE\n";
            $conteudo .= "=================\n";
            $conteudo .= "ID Documento: {$doc['id']}\n";
            $conteudo .= "Associado ID: {$doc['associado_id']}\n";
            $conteudo .= "Nome Original: {$doc['nome_arquivo']}\n";
            $conteudo .= "Tipo: {$doc['tipo_origem']}\n";
            $conteudo .= "Data Criação Teste: " . date('Y-m-d H:i:s') . "\n";
            $conteudo .= "\nEste é um arquivo de teste temporário.\n";
            $conteudo .= "Substitua pelo arquivo real quando disponível.\n";
            
            if (file_put_contents($caminhoCompleto, $conteudo)) {
                echo "<span class='ok'>✓ Arquivo de teste criado</span><br>";
                $criados++;
            } else {
                echo "<span class='erro'>❌ Erro ao criar arquivo</span><br>";
                $erros++;
            }
        }
    }
    
    echo "<hr>";
    echo "<h2>📊 Resumo:</h2>";
    echo "<p class='ok'>✓ Arquivos criados: {$criados}</p>";
    if ($erros > 0) {
        echo "<p class='erro'>❌ Erros: {$erros}</p>";
    }
    
    if ($criados > 0) {
        echo "<h2>🎉 Sucesso!</h2>";
        echo "<p>Agora você pode testar o download:</p>";
        echo "<ul>";
        echo "<li><a href='documentos_download_teste.php?id=6' target='_blank'>🧪 Testar Download ID 6</a></li>";
        echo "<li><a href='../documentos/documentos_download.php?id=6' target='_blank'>🔽 Download Final ID 6</a></li>";
        echo "<li><a href='../../pages/documentos_fluxo.php' target='_blank'>📄 Voltar para Página Principal</a></li>";
        echo "</ul>";
        
        echo "<div style='background:#e8f5e9;padding:15px;margin:20px 0;border-radius:5px;'>";
        echo "<h3>✅ Próximos Passos:</h3>";
        echo "<ol>";
        echo "<li><strong>Teste o download</strong> clicando nos links acima</li>";
        echo "<li><strong>Verifique se funciona</strong> na página principal</li>";
        echo "<li><strong>Substitua os arquivos de teste</strong> pelos originais quando disponível</li>";
        echo "<li><strong>Configure o processo de upload</strong> para evitar perder arquivos no futuro</li>";
        echo "</ol>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p class='erro'>❌ Erro: " . $e->getMessage() . "</p>";
}

// Função para criar PDF simples
function createSimplePDF($doc) {
    // PDF básico manualmente (header + conteúdo + footer)
    $pdf = "%PDF-1.4\n";
    $pdf .= "1 0 obj\n";
    $pdf .= "<<\n";
    $pdf .= "/Type /Catalog\n";
    $pdf .= "/Pages 2 0 R\n";
    $pdf .= ">>\n";
    $pdf .= "endobj\n";
    
    $pdf .= "2 0 obj\n";
    $pdf .= "<<\n";
    $pdf .= "/Type /Pages\n";
    $pdf .= "/Kids [3 0 R]\n";
    $pdf .= "/Count 1\n";
    $pdf .= ">>\n";
    $pdf .= "endobj\n";
    
    $pdf .= "3 0 obj\n";
    $pdf .= "<<\n";
    $pdf .= "/Type /Page\n";
    $pdf .= "/Parent 2 0 R\n";
    $pdf .= "/MediaBox [0 0 612 792]\n";
    $pdf .= "/Contents 4 0 R\n";
    $pdf .= "/Resources <<\n";
    $pdf .= "/Font <<\n";
    $pdf .= "/F1 <<\n";
    $pdf .= "/Type /Font\n";
    $pdf .= "/Subtype /Type1\n";
    $pdf .= "/BaseFont /Helvetica\n";
    $pdf .= ">>\n";
    $pdf .= ">>\n";
    $pdf .= ">>\n";
    $pdf .= ">>\n";
    $pdf .= "endobj\n";
    
    $content = "BT\n";
    $content .= "/F1 24 Tf\n";
    $content .= "100 700 Td\n";
    $content .= "(ARQUIVO DE TESTE) Tj\n";
    $content .= "0 -30 Td\n";
    $content .= "/F1 12 Tf\n";
    $content .= "(ID Documento: {$doc['id']}) Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(Associado: {$doc['associado_id']}) Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(Nome: {$doc['nome_arquivo']}) Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(Tipo: {$doc['tipo_origem']}) Tj\n";
    $content .= "0 -40 Td\n";
    $content .= "(Este e um arquivo PDF de teste.) Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(Substitua pelo arquivo real.) Tj\n";
    $content .= "ET\n";
    
    $pdf .= "4 0 obj\n";
    $pdf .= "<<\n";
    $pdf .= "/Length " . strlen($content) . "\n";
    $pdf .= ">>\n";
    $pdf .= "stream\n";
    $pdf .= $content;
    $pdf .= "endstream\n";
    $pdf .= "endobj\n";
    
    $pdf .= "xref\n";
    $pdf .= "0 5\n";
    $pdf .= "0000000000 65535 f \n";
    $pdf .= "0000000009 65535 n \n";
    $pdf .= "0000000074 65535 n \n";
    $pdf .= "0000000131 65535 n \n";
    $pdf .= "0000000304 65535 n \n";
    
    $pdf .= "trailer\n";
    $pdf .= "<<\n";
    $pdf .= "/Size 5\n";
    $pdf .= "/Root 1 0 R\n";
    $pdf .= ">>\n";
    $pdf .= "startxref\n";
    $pdf .= (304 + strlen($content) + 40) . "\n";
    $pdf .= "%%EOF\n";
    
    return $pdf;
}

echo "</body></html>";
?>