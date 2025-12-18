<?php
// atacadao/consulta_cliente_ativo.php
// Consulta CPFs na tabela Associados e verifica status no serviço Cliente Ativo

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

// Aumenta limites para execução longa
@set_time_limit(0);
@ini_set('memory_limit', '512M');

// Carrega config e conexão
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';

// Config específica do módulo
require_once __DIR__ . '/config.php';

function only_digits(string $v): string {
    return preg_replace('/\D+/', '', $v) ?? '';
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function build_bearer_token(): ?string {
    // 1) Se veio um JWT pronto via GET ou env, usar diretamente
    $jwtGet = isset($_GET['jwt']) ? trim((string)$_GET['jwt']) : '';
    $jwt = $jwtGet !== '' ? $jwtGet : (ATACADAO_JWT ?: '');
    if ($jwt !== '') return $jwt;

    // 2) Caso contrário, tentar gerar JWT HS256 com TOKEN/SECRET
    $token = defined('ATACADAO_TOKEN') ? (string)ATACADAO_TOKEN : '';
    // Permite override do SECRET via GET para testes pontuais
    $secret = isset($_GET['secret']) ? (string)$_GET['secret'] : ATACADAO_SECRET;

    if ($token === '' || $secret === '') {
        return null; // sem como assinar
    }

    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = ['iss' => $token];
    $h = base64url_encode(json_encode($header));
    $p = base64url_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $h . '.' . $p, $secret, true);
    $s = base64url_encode($signature);
    return $h . '.' . $p . '.' . $s;
}

function call_atacadao_api(string $cpf): array {
    $bearer = build_bearer_token();
    if ($bearer === null) {
        return [
            'ok' => false,
            'http' => 401,
            'error' => 'JWT ausente: defina ATACADAO_SECRET (ou passe ?secret=...) ou forneça ?jwt=... pronto.',
            'raw' => null,
        ];
    }
    $payload = json_encode(['cpf' => $cpf], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => ATACADAO_ENDPOINT,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
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
            'http' => $httpCode,
            'error' => 'curl_error: ' . $curlErr,
            'raw' => $responseBody,
        ];
    }

    $data = null;
    if (is_string($responseBody) && $responseBody !== '') {
        $decoded = json_decode($responseBody, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $data = $decoded;
        } else {
            // Algumas APIs retornam campos em maiúsculas ou sem JSON estrito, preserva o raw
            $data = ['raw' => $responseBody];
        }
    }

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'http' => $httpCode,
        'data' => $data,
        'raw' => $responseBody,
    ];
}

try {
    $pdo = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Parâmetros opcionais via query string: limit e offset (para testes)
    $limit = null;
    $offset = 0;
    if (isset($_GET['limit'])) {
        $limit = max(1, (int) $_GET['limit']);
    }
    if (isset($_GET['offset'])) {
        $offset = max(0, (int) $_GET['offset']);
    }

    $cpfs = [];
    $cpfParam = isset($_GET['cpf']) ? only_digits((string)$_GET['cpf']) : '';
    if ($cpfParam !== '' && strlen($cpfParam) >= 11) {
        $cpfs[] = substr($cpfParam, -11);
    } else {
        // Busca CPFs distintos e não vazios
        $sql = "SELECT DISTINCT cpf FROM Associados WHERE cpf IS NOT NULL AND cpf <> ''";
        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $pdo->prepare($sql);
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cpf = only_digits((string)($row['cpf'] ?? ''));
            if ($cpf !== '' && strlen($cpf) >= 11) {
                $cpfs[] = substr($cpf, -11); // garante 11 dígitos finais
            }
        }
    }

    $total = count($cpfs);
    $ativos = 0; // STATUS == SIM
    $nao = 0;    // STATUS == NAO
    $erros = 0;  // chamadas com falha

    echo "Consulta Cliente Ativo - Atacadão Dia a Dia\n";
    echo "-------------------------------------------\n";
    echo "CPFs a consultar: {$total}\n\n";

    foreach ($cpfs as $i => $cpf) {
        $res = call_atacadao_api($cpf);

        $statusStr = 'DESCONHECIDO';
        if ($res['ok'] && is_array($res['data'])) {
            // Tenta extrair STATUS
            $data = $res['data'];
            if (isset($data['STATUS'])) {
                $statusStr = strtoupper((string)$data['STATUS']);
            } elseif (isset($data['status'])) {
                $statusStr = strtoupper((string)$data['status']);
            } elseif (isset($data['raw']) && is_string($data['raw'])) {
                // fallback rudimentar
                if (stripos($data['raw'], 'SIM') !== false) $statusStr = 'SIM';
                if (stripos($data['raw'], 'NAO') !== false) $statusStr = 'NAO';
            }

            if ($statusStr === 'SIM') $ativos++;
            elseif ($statusStr === 'NAO') $nao++;
        } else {
            $erros++;
            // Tratamento especial para 403 com mensagem e request_id
            $http = (int)($res['http'] ?? 0);
            if ($http === 403 && isset($res['raw']) && is_string($res['raw'])) {
                $j = json_decode($res['raw'], true);
                if (is_array($j) && isset($j['message'])) {
                    $statusStr = 'NAO_AUTORIZADO';
                }
            }
        }

        // Imprime uma linha por CPF com status e http code
        $http = $res['http'] ?? 0;
        echo sprintf("%05d/%05d | CPF: %s | HTTP: %s | STATUS: %s\n", $i + 1, $total, $cpf, (string)$http, $statusStr);

        // Opcional: comentar a linha abaixo se quiser saídas menores
        if (isset($res['raw']) && is_string($res['raw']) && $res['raw'] !== '') {
            echo "  RESPOSTA: " . trim($res['raw']) . "\n";
        }
    }

    echo "\nResumo:\n";
    echo "- Total consultado: {$total}\n";
    echo "- Cadastrados/Ativos (SIM): {$ativos}\n";
    echo "- Não cadastrados (NAO): {$nao}\n";
    echo "- Erros: {$erros}\n";

    if ($erros > 0) {
        echo "\nDica: se receber 403 'You cannot consume this service',\n";
        echo "verifique com o provedor se o TOKEN/SECRET possuem permissão para o endpoint /consultaclienteativo.\n";
        echo "Inclua o 'request_id' exibido acima ao abrir o chamado.\n";
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo "Erro ao executar consulta: " . $e->getMessage() . "\n";
}
