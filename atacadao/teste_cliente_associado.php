<?php
// atacadao/teste_cliente_associado.php
// Ativa todos os associados filiados no sistema Atacadão Dia a Dia

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

// Aumenta limites para execução longa
@set_time_limit(0);
@ini_set('memory_limit', '512M');

// Desabilita output buffering para ver progresso em tempo real
if (ob_get_level()) ob_end_flush();
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 'off');
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');

// Carrega config, DB e cliente reutilizável
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/Client.php';

// Funções utilitárias foram movidas para AtacadaoClient

try {
    $pdo = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Parâmetros opcionais via GET
    $limit = null;
    $offset = 0;
    $codgrupo = isset($_GET['codgrupo']) ? (string)$_GET['codgrupo'] : '58';
    $statusAtivacao = isset($_GET['status']) ? strtoupper((string)$_GET['status']) : 'A';
    
    if (isset($_GET['limit'])) {
        $limit = max(1, (int)$_GET['limit']);
    }
    if (isset($_GET['offset'])) {
        $offset = max(0, (int)$_GET['offset']);
    }

    // Busca associados FILIADOS com CPF válido
    // CONTINUANDO A PARTIR DO ID 4132
    $sql = "SELECT id, nome, cpf, situacao 
            FROM Associados 
            WHERE (situacao = 'Filiado' OR situacao = 'FILIADO')
              AND cpf IS NOT NULL 
              AND cpf <> ''
              AND id >= 4132
            ORDER BY id ASC";
    
    if ($limit !== null) {
        $sql .= " LIMIT :limit OFFSET :offset";
    }

    $stmt = $pdo->prepare($sql);
    if ($limit !== null) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();

    $associados = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cpf = only_digits((string)($row['cpf'] ?? ''));
        if ($cpf !== '' && strlen($cpf) >= 11) {
            $associados[] = [
                'id' => $row['id'],
                'nome' => $row['nome'] ?? '',
                'cpf' => substr($cpf, -11),
                'situacao' => $row['situacao'] ?? '',
            ];
        }
    }

    $total = count($associados);
    $sucesso = 0;
    $erros = 0;
    $forbidden = 0;

    echo "Ativação de Associados Filiados - Atacadão Dia a Dia\n";
    echo "=====================================================\n";
    echo "Total de associados a ativar: {$total}\n";
    echo "Status: {$statusAtivacao}\n";
    echo "Código Grupo: {$codgrupo}\n\n";
    flush();

    $bearer = AtacadaoClient::buildBearerToken();
    if ($bearer === null) {
        throw new Exception('JWT ausente: defina ATACADAO_SECRET no config.');
    }

    foreach ($associados as $i => $assoc) {
        $res = AtacadaoClient::ativarCliente($assoc['cpf'], $statusAtivacao, $codgrupo);

        $status = 'ERRO';
        if ($res['ok'] && is_array($res['data'])) {
            if (isset($res['data']['sucesso']) && $res['data']['sucesso'] === true) {
                $status = 'SUCESSO';
                $sucesso++;
            }
        } elseif ($res['http'] === 403) {
            $status = 'FORBIDDEN';
            $forbidden++;
        } else {
            $erros++;
        }

        $http = $res['http'] ?? 0;
        echo sprintf(
            "%05d/%05d | ID: %s | CPF: %s | HTTP: %s | %s\n",
            $i + 1,
            $total,
            str_pad((string)$assoc['id'], 6, ' ', STR_PAD_LEFT),
            $assoc['cpf'],
            str_pad((string)$http, 3, ' ', STR_PAD_LEFT),
            $status
        );
        flush();

        // Mostra resposta para erros e forbidden
        if ($status !== 'SUCESSO' && isset($res['raw']) && is_string($res['raw']) && $res['raw'] !== '') {
            echo "  RESPOSTA: " . trim($res['raw']) . "\n";
            flush();
        }

        // Pequeno delay para não sobrecarregar a API
        usleep(100000); // 100ms
    }

    echo "\n=====================================================\n";
    echo "Resumo:\n";
    echo "- Total processado: {$total}\n";
    echo "- Sucesso: {$sucesso}\n";
    echo "- Erros: {$erros}\n";
    echo "- Forbidden (403): {$forbidden}\n";

    if ($forbidden > 0) {
        echo "\n⚠️  Alguns retornaram 403 - verifique permissões do TOKEN/SECRET.\n";
    }

    echo "\nDica: Para testar com limite, use:\n";
    echo "?limit=10&codgrupo=58&status=A\n";
    flush();

} catch (Throwable $e) {
    http_response_code(500);
    echo "Erro: " . $e->getMessage() . "\n";
}
