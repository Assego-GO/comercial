<?php
/**
 * Debug de caminhos - coloque este arquivo na raiz do projeto comercial
 * debug_paths.php
 */

echo "<h2>Debug de Caminhos</h2>";
echo "<pre>";

// Informações básicas
echo "Script atual: " . __FILE__ . "\n";
echo "Diretório atual: " . __DIR__ . "\n";
echo "dirname(__DIR__): " . dirname(__DIR__) . "\n";
echo "\n";

// Caminhos importantes
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "\n";

// Teste de caminhos para uploads
$testePaths = [
    'dirname(__DIR__) . "/uploads/documentos/"' => dirname(__DIR__) . '/uploads/documentos/',
    '__DIR__ . "/uploads/documentos/"' => __DIR__ . '/uploads/documentos/',
    'realpath(__DIR__ . "/uploads/documentos/")' => realpath(__DIR__ . '/uploads/documentos/'),
    '$_SERVER["DOCUMENT_ROOT"] . "/matheus/comercial/uploads/documentos/"' => $_SERVER['DOCUMENT_ROOT'] . '/matheus/comercial/uploads/documentos/'
];

echo "Teste de caminhos:\n";
foreach ($testePaths as $descricao => $caminho) {
    echo "\n$descricao:\n";
    echo "  Caminho: $caminho\n";
    echo "  Existe? " . (file_exists($caminho) ? "SIM" : "NÃO") . "\n";
    echo "  É diretório? " . (is_dir($caminho) ? "SIM" : "NÃO") . "\n";
    echo "  É gravável? " . (is_writable($caminho) ? "SIM" : "NÃO") . "\n";
}

// Verificar estrutura de diretórios
echo "\n\nEstrutura de diretórios:\n";
$baseDir = __DIR__;
$dirs = ['uploads', 'uploads/documentos', 'uploads/temp', 'uploads/backup'];

foreach ($dirs as $dir) {
    $fullPath = $baseDir . '/' . $dir;
    if (file_exists($fullPath)) {
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        $owner = posix_getpwuid(fileowner($fullPath));
        echo "  ✓ $dir (Permissões: $perms, Dono: {$owner['name']})\n";
    } else {
        echo "  ✗ $dir (NÃO EXISTE)\n";
    }
}

echo "</pre>";
?>