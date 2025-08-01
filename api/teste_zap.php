<?php
/**
 * Arquivo de teste para ZapSign API
 * Coloque este arquivo na pasta api/ e acesse pelo browser
 */

// Ativa logs de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste ZapSign API</h1>";

// Inclui o arquivo da API
try {
    require_once 'zapsign_api.php';
    echo "<p>✓ Arquivo zapsign_api.php incluído com sucesso</p>";
} catch (Exception $e) {
    echo "<p>✗ Erro ao incluir zapsign_api.php: " . $e->getMessage() . "</p>";
    exit;
}

// Verifica se a função existe
if (function_exists('enviarParaZapSign')) {
    echo "<p>✓ Função enviarParaZapSign() encontrada</p>";
} else {
    echo "<p>✗ Função enviarParaZapSign() NÃO encontrada</p>";
    exit;
}

// Dados de teste
$dadosTeste = [
    'meta' => [
        'id_associado' => 999,
        'operacao' => 'CREATE'
    ],
    'dados_pessoais' => [
        'nome_completo' => 'João da Silva Teste',
        'email' => 'teste@email.com',
        'telefone' => '62999887766',
        'telefone_numeros' => '62999887766',
        'cpf' => '12345678901',
        'rg' => '1234567',
        'data_nascimento' => '1980-01-01',
        'data_filiacao' => '2024-01-01',
        'escolaridade' => 'Superior Completo',
        'indicado_por' => 'Teste Sistema'
    ],
    'endereco' => [
        'logradouro' => 'Rua das Flores',
        'numero' => '123',
        'bairro' => 'Centro',
        'cidade' => 'Goiânia',
        'estado' => 'GO',
        'cep' => '74000000'
    ],
    'dados_militares' => [
        'corporacao' => 'PM',
        'lotacao' => 'Batalhão Teste',
        'telefone_lotacao' => '6233334444'
    ],
    'dados_financeiros' => [
        'vinculo_servidor' => 'Ativo'
    ],
    'dependentes' => [
        [
            'nome' => 'Maria da Silva',
            'parentesco' => 'Cônjuge',
            'data_nascimento' => '1985-05-10',
            'telefone' => '62988776655'
        ]
    ]
];

echo "<h2>Executando teste...</h2>";

// Testa a função
try {
    $resultado = enviarParaZapSign($dadosTeste);
    
    echo "<h3>Resultado:</h3>";
    echo "<pre>" . json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    
    if ($resultado['sucesso']) {
        echo "<p style='color: green;'>✓ SUCESSO: Documento enviado para ZapSign!</p>";
        echo "<p>Documento ID: " . ($resultado['documento_id'] ?? 'N/A') . "</p>";
        echo "<p>Link: " . ($resultado['link_assinatura'] ?? 'N/A') . "</p>";
    } else {
        echo "<p style='color: red;'>✗ ERRO: " . ($resultado['erro'] ?? 'Erro desconhecido') . "</p>";
        echo "<p>HTTP Code: " . ($resultado['http_code'] ?? 'N/A') . "</p>";
        
        if (isset($resultado['resposta_completa'])) {
            echo "<h4>Resposta completa da API:</h4>";
            echo "<pre>" . json_encode($resultado['resposta_completa'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ EXCEÇÃO: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<p><strong>Verifique os logs do servidor para mais detalhes.</strong></p>";

?>