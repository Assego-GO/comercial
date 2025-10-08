<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['pode_editar' => false]);
    exit;
}

$usuarioLogado = $auth->getUser();
$podeEditar = false;

// Presidência pode editar
if ($usuarioLogado['departamento_id'] == 1) {
    $podeEditar = true;
}

// TODO DEPARTAMENTO COMERCIAL pode editar (não só diretor)
if ($usuarioLogado['departamento_id'] == 10) {
    $podeEditar = true;
}


header('Content-Type: application/json');
echo json_encode(['pode_editar' => $podeEditar]);