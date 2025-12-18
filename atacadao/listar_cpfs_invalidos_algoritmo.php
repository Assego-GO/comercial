<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';

function only_digits(string $v): string {
    return preg_replace('/\D+/', '', $v) ?? '';
}

function cpf_is_valid(string $cpf): bool {
    $cpf = only_digits($cpf);
    if (strlen($cpf) != 11) return false;
    if (preg_match('/^(\d)\1{10}$/', $cpf)) return false; // rejeita sequências

    // cálculo DV
    $sum = 0;
    for ($i = 0, $w = 10; $i < 9; $i++, $w--) $sum += intval($cpf[$i]) * $w;
    $rest = $sum % 11;
    $dv1 = ($rest < 2) ? 0 : 11 - $rest;
    if ($dv1 != intval($cpf[9])) return false;

    $sum = 0;
    for ($i = 0, $w = 11; $i < 10; $i++, $w--) $sum += intval($cpf[$i]) * $w;
    $rest = $sum % 11;
    $dv2 = ($rest < 2) ? 0 : 11 - $rest;
    if ($dv2 != intval($cpf[10])) return false;

    return true;
}

try {
    $pdo = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    $stmt = $pdo->prepare("SELECT id, nome, cpf FROM Associados WHERE (situacao IN ('Filiado','FILIADO')) AND cpf IS NOT NULL AND cpf <> ''");
    $stmt->execute();

    $total = 0; $invalidos = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $total++;
        $cpf = only_digits($row['cpf'] ?? '');
        if ($cpf === '' || strlen($cpf) != 11 || !cpf_is_valid($cpf)) {
            $invalidos[] = [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'cpf' => $cpf
            ];
        }
    }

    echo "Validação local de CPF (algoritmo)\n";
    echo "===================================\n";
    echo "Total filiados verificados: {$total}\n";
    echo "CPFs inválidos pelo algoritmo: " . count($invalidos) . "\n\n";

    if (!empty($invalidos)) {
        echo "Lista (primeiros 100):\n";
        foreach (array_slice($invalidos, 0, 100) as $i) {
            echo sprintf("ID: %-7s | CPF: %s | Nome: %s\n", $i['id'], $i['cpf'], substr($i['nome'], 0, 60));
        }
        if (count($invalidos) > 100) {
            echo "... e mais " . (count($invalidos) - 100) . " registros.\n";
        }
    } else {
        echo "✅ Nenhum CPF inválido encontrado.\n";
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo "Erro: " . $e->getMessage() . "\n";
}
