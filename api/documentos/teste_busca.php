<?php
header('Content-Type: application/json');
echo json_encode([
    'get' => $_GET,
    'busca_recebida' => $_GET['busca'] ?? 'NENHUMA',
    'busca_vazia' => empty($_GET['busca'] ?? ''),
    'raw_query' => $_SERVER['QUERY_STRING'] ?? ''
]);
