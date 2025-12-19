<?php
// atacadao/enviar_cpfs_normalizados.php
// Envia apenas os CPFs normalizados (com zeros à esquerda) para a API do Atacadão

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

// Aumenta limites para execução longa
@set_time_limit(0);
@ini_set('memory_limit', '512M');

// Desabilita output buffering para ver progresso em tempo real
if (ob_get_level()) ob_end_flush();
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 'off');
@apache_setenv('no-gzip', '1');

// Carrega config, DB e JWT builder
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/config.php';

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function build_bearer_token(): ?string {
    $token = defined('ATACADAO_TOKEN') ? (string)ATACADAO_TOKEN : '';
    $secret = defined('ATACADAO_SECRET') ? (string)ATACADAO_SECRET : '';

    if ($token === '' || $secret === '') {
        return null;
    }

    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = ['iss' => $token];
    $h = base64url_encode(json_encode($header));
    $p = base64url_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $h . '.' . $p, $secret, true);
    $s = base64url_encode($signature);
    return $h . '.' . $p . '.' . $s;
}

function only_digits(string $v): string {
    return preg_replace('/\D+/', '', $v) ?? '';
}

function call_ativar_cliente(string $cpf, string $status, string $codgrupo, string $bearer): array {
    $endpoint = 'https://ddconnect.atacadaodiaadia.com.br/AtivarCliente';
    
    $payload = json_encode([
        'cpf' => $cpf,
        'status' => $status,
        'codgrupo' => $codgrupo
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $bearer,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Comercial-Integracao/1.0',
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    $responseBody = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        return [
            'ok' => false,
            'http' => 0,
            'error' => 'curl_error: ' . $curlErr,
            'raw' => null,
        ];
    }

    $data = null;
    if (is_string($responseBody) && $responseBody !== '') {
        $decoded = json_decode($responseBody, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $data = $decoded;
        }
    }

    return [
        'ok' => $httpCode === 200,
        'http' => $httpCode,
        'data' => $data,
        'raw' => $responseBody,
    ];
}

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

    // Busca associados FILIADOS com CPF não nulo/vazio
    $sql = "SELECT id, nome, cpf, situacao 
            FROM Associados 
            WHERE (situacao = 'Filiado' OR situacao = 'FILIADO')
              AND cpf IS NOT NULL 
              AND cpf <> ''";
    
    if ($limit !== null) {
        $sql .= " LIMIT :limit OFFSET :offset";
    }

    $stmt = $pdo->prepare($sql);
    if ($limit !== null) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();

    // Filtra apenas CPFs com menos de 11 dígitos e normaliza
    $associados = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cpf = only_digits((string)($row['cpf'] ?? ''));
        $tamanho = strlen($cpf);
        
        // APENAS CPFs com menos de 11 dígitos (zeros removidos)
        if ($tamanho > 0 && $tamanho < 11) {
            $cpfNormalizado = str_pad($cpf, 11, '0', STR_PAD_LEFT);
            $associados[] = [
                'id' => $row['id'],
                'nome' => $row['nome'] ?? '',
                'cpf_original' => $cpf,
                'cpf' => $cpfNormalizado,
                'situacao' => $row['situacao'] ?? '',
                'zeros_adicionados' => 11 - $tamanho
            ];
        }
    }

    $total = count($associados);
    $sucesso = 0;
    $erros = 0;
    $forbidden = 0;

    echo "Ativação de CPFs Normalizados - Atacadão Dia a Dia\n";
    echo "===================================================\n";
    echo "Total de CPFs a normalizar e enviar: {$total}\n";
    echo "Status: {$statusAtivacao}\n";
    echo "Código Grupo: {$codgrupo}\n\n";
    flush();

    if ($total === 0) {
        echo "✅ Não há CPFs com menos de 11 dígitos para processar.\n";
        echo "Todos os associados filiados já têm CPF válido!\n";
        exit(0);
    }

    $bearer = build_bearer_token();
    if ($bearer === null) {
        throw new Exception('JWT ausente: defina ATACADAO_SECRET no config.');
    }

    foreach ($associados as $i => $assoc) {
        $res = call_ativar_cliente($assoc['cpf'], $statusAtivacao, $codgrupo, $bearer);

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
            "%05d/%05d | ID: %s | CPF: %s (+%dz) | HTTP: %s | %s\n",
            $i + 1,
            $total,
            str_pad((string)$assoc['id'], 6, ' ', STR_PAD_LEFT),
            $assoc['cpf'],
            $assoc['zeros_adicionados'],
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

    echo "\n===================================================\n";
    echo "Resumo:\n";
    echo "- Total processado: {$total}\n";
    echo "- Sucesso: {$sucesso}\n";
    echo "- Erros: {$erros}\n";
    echo "- Forbidden (403): {$forbidden}\n";

    if ($forbidden > 0) {
        echo "\n⚠️  Alguns retornaram 403 - verifique permissões do TOKEN/SECRET.\n";
    }

    if ($sucesso > 0) {
        echo "\n✅ {$sucesso} CPFs normalizados foram ativados com sucesso!\n";
    }

    echo "\nDica: Para testar com limite, use:\n";
    echo "?limit=10&codgrupo=58&status=A\n";
    flush();

} catch (Throwable $e) {
    http_response_code(500);
    echo "Erro: " . $e->getMessage() . "\n";
}
