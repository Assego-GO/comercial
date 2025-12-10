<!DOCTYPE html>
<html>
<head>
    <title>Ver Resposta Completa</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        pre { background: #f5f5f5; padding: 20px; overflow-x: auto; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🔍 Capturar Resposta do POST</h1>
    
    <form id="uploadForm" enctype="multipart/form-data">
        <p>
            <label>Arquivo CSV:</label><br>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
        </p>
        <p>
            <button type="submit">Enviar e Ver Resposta Completa</button>
        </p>
    </form>
    
    <div id="resultado"></div>
    
    <script>
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const resultDiv = document.getElementById('resultado');
            resultDiv.innerHTML = '<p>⏳ Enviando...</p>';
            
            const formData = new FormData();
            formData.append('csv_file', document.getElementById('csv_file').files[0]);
            formData.append('action', 'processar_csv');
            
            try {
                const response = await fetch('importar_quitacao.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Pegar o texto RAW
                const rawText = await response.text();
                
                // Mostrar informações
                let html = '<h2>📊 Informações da Resposta</h2>';
                html += '<p><strong>Status HTTP:</strong> ' + response.status + '</p>';
                html += '<p><strong>Content-Type:</strong> ' + response.headers.get('Content-Type') + '</p>';
                html += '<p><strong>Tamanho:</strong> ' + rawText.length + ' bytes</p>';
                
                html += '<h2>📝 Primeiros 500 Caracteres</h2>';
                html += '<pre>' + escapeHtml(rawText.substring(0, 500)) + '</pre>';
                
                html += '<h2>📄 Resposta Completa (RAW)</h2>';
                html += '<pre>' + escapeHtml(rawText) + '</pre>';
                
                // Tentar parsear como JSON
                html += '<h2>🔍 Tentativa de Parse JSON</h2>';
                try {
                    const json = JSON.parse(rawText);
                    html += '<p class="success">✅ JSON válido!</p>';
                    html += '<pre>' + JSON.stringify(json, null, 2) + '</pre>';
                } catch (jsonError) {
                    html += '<p class="error">❌ NÃO é JSON válido!</p>';
                    html += '<p>Erro: ' + jsonError.message + '</p>';
                    
                    // Mostrar onde está o problema
                    const match = rawText.match(/[^{]*({.*)/);
                    if (match) {
                        html += '<h3>🔎 Possível início do JSON:</h3>';
                        html += '<pre>' + escapeHtml(match[1].substring(0, 500)) + '</pre>';
                    }
                    
                    // Mostrar o que vem ANTES do JSON
                    const beforeJson = rawText.substring(0, rawText.indexOf('{'));
                    if (beforeJson) {
                        html += '<h3 class="error">⚠️  LIXO ANTES DO JSON:</h3>';
                        html += '<pre>' + escapeHtml(beforeJson) + '</pre>';
                        html += '<p>Bytes em hex: ' + stringToHex(beforeJson) + '</p>';
                    }
                }
                
                resultDiv.innerHTML = html;
                
            } catch (error) {
                resultDiv.innerHTML = '<p class="error">❌ Erro na requisição: ' + error.message + '</p>';
            }
        });
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        function stringToHex(str) {
            let hex = '';
            for (let i = 0; i < Math.min(str.length, 100); i++) {
                hex += str.charCodeAt(i).toString(16).padStart(2, '0') + ' ';
            }
            return hex;
        }
    </script>
</body>
</html>