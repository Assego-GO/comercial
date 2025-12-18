<?php
// atacadao/config.php
// Defina aqui suas credenciais para a autenticação JWT do DDConnect

// TOKEN (valor que vai no campo iss do payload)
// O usuário informou este TOKEN:
define('ATACADAO_TOKEN', 'cf9pqLQUP9mqAc1GZNrsA0iEhis0z38x');

// SECRET (chave usada para assinar o JWT com HS256)
// Secret fornecido pelo usuário/fornecedor
if (!defined('ATACADAO_SECRET')) define('ATACADAO_SECRET', 'w5GMI9T7lOt5fAlN1oDr8gKtajW2z7z9');
// Opcional: permitir override via variável de ambiente (se desejar)
if (getenv('ATACADAO_SECRET')) {
    // Somente aplica override se variável estiver definida e não vazia
    $envSecret = getenv('ATACADAO_SECRET');
    if (is_string($envSecret) && $envSecret !== '') {
        define('ATACADAO_SECRET', $envSecret);
    }
}

// Opcional: caso já possua um JWT pronto (header.payload.signature),
// você pode definir via env ATACADAO_JWT ou GET ?jwt=... na execução.
if (!defined('ATACADAO_JWT')) {
    $envJwt = getenv('ATACADAO_JWT') ?: '';
    define('ATACADAO_JWT', $envJwt);
}

// Endpoint do serviço Cliente Ativo
if (!defined('ATACADAO_ENDPOINT')) {
    define('ATACADAO_ENDPOINT', 'https://ddconnect.atacadaodiaadia.com.br/consultaclienteativo');
}
