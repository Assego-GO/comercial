<?php
// atacadao/verificar_cpfs_invalidos.php
// Verifica CPFs filiados com menos de 11 dígitos (zeros à esquerda removidos)

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';

function only_digits(string $v): string {
    return preg_replace('/\D+/', '', $v) ?? '';
}

try {
    $pdo = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    echo "Verificação de CPFs Inválidos - Associados Filiados\n";
    echo "====================================================\n\n";

    // Busca associados filiados com CPF não nulo/vazio
    $stmt = $pdo->prepare("
        SELECT id, nome, cpf, situacao 
        FROM Associados 
        WHERE (situacao = 'Filiado' OR situacao = 'FILIADO')
          AND cpf IS NOT NULL 
          AND cpf <> ''
    ");
    $stmt->execute();

    $total = 0;
    $validos = 0;
    $invalidos = 0;
    $cpfsInvalidos = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $total++;
        $cpf = only_digits((string)($row['cpf'] ?? ''));
        $tamanho = strlen($cpf);

        if ($tamanho === 11) {
            $validos++;
        } elseif ($tamanho > 0 && $tamanho < 11) {
            $invalidos++;
            $cpfNormalizado = str_pad($cpf, 11, '0', STR_PAD_LEFT);
            $cpfsInvalidos[] = [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'cpf_original' => $cpf,
                'tamanho' => $tamanho,
                'cpf_normalizado' => $cpfNormalizado,
                'zeros_faltantes' => 11 - $tamanho
            ];
        }
    }

    echo "RESUMO GERAL:\n";
    echo "- Total de associados filiados com CPF: {$total}\n";
    echo "- CPFs válidos (11 dígitos): {$validos}\n";
    echo "- CPFs inválidos (< 11 dígitos): {$invalidos}\n\n";

    if ($invalidos > 0) {
        echo "DETALHAMENTO DOS CPFs INVÁLIDOS:\n";
        echo "==================================\n\n";

        // Agrupa por quantidade de zeros faltantes
        $porZeros = [];
        foreach ($cpfsInvalidos as $item) {
            $z = $item['zeros_faltantes'];
            if (!isset($porZeros[$z])) {
                $porZeros[$z] = 0;
            }
            $porZeros[$z]++;
        }

        echo "Distribuição por zeros faltantes:\n";
        foreach ($porZeros as $zeros => $qtd) {
            echo "- Faltam {$zeros} zero(s): {$qtd} CPFs\n";
        }

        echo "\nPrimeiros 20 exemplos:\n";
        echo str_repeat("-", 80) . "\n";
        echo sprintf("%-8s | %-12s | %-3s | %-12s | %s\n", 
            "ID", "CPF Original", "Dig", "Normalizado", "Nome");
        echo str_repeat("-", 80) . "\n";

        $mostrar = array_slice($cpfsInvalidos, 0, 20);
        foreach ($mostrar as $item) {
            echo sprintf(
                "%-8s | %-12s | %3d | %-12s | %s\n",
                $item['id'],
                $item['cpf_original'],
                $item['tamanho'],
                $item['cpf_normalizado'],
                substr($item['nome'], 0, 30)
            );
        }

        if ($invalidos > 20) {
            echo "\n... e mais " . ($invalidos - 20) . " registros.\n";
        }

        echo "\n\nPróximo passo:\n";
        echo "Execute: atacadao/enviar_cpfs_normalizados.php\n";
        echo "Para enviar esses {$invalidos} CPFs corrigidos para a API do Atacadão.\n";
    } else {
        echo "✅ Todos os CPFs estão válidos (11 dígitos)!\n";
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo "Erro: " . $e->getMessage() . "\n";
}
