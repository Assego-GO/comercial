<?php
echo "<h3>Arquivos na pasta rend/:</h3><ul>";
$files = scandir('.');
foreach($files as $file) {
    if($file != '.' && $file != '..') {
        echo "<li>$file</li>";
    }
}
echo "</ul>";
?>