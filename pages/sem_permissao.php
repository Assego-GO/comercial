<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Acesso Negado - ASSEGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
        }
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-lock"></i>
        </div>
        <h1>Acesso Negado</h1>
        <p class="lead">Você não tem permissão para acessar esta página.</p>
        <p>Entre em contato com o administrador se acredita que isso é um erro.</p>
        <a href="../index.php" class="btn btn-primary mt-3">
            <i class="fas fa-home"></i> Voltar ao Início
        </a>
    </div>
    
    <script src="https://kit.fontawesome.com/your-kit.js"></script>
</body>
</html>