<?php
// atacadao/Client.php
// Cliente reutilizável para integração com DDConnect (Atacadão Dia a Dia)

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class AtacadaoClient {
    public static function onlyDigits(string $v): string {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    private static function base64urlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function buildBearerToken(): ?string {
        $token = defined('ATACADAO_TOKEN') ? (string)ATACADAO_TOKEN : '';
        $secret = defined('ATACADAO_SECRET') ? (string)ATACADAO_SECRET : '';

        if ($token === '' || $secret === '') {
            return null;
        }

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = ['iss' => $token];
        $h = self::base64urlEncode(json_encode($header));
        $p = self::base64urlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $h . '.' . $p, $secret, true);
        $s = self::base64urlEncode($signature);
        return $h . '.' . $p . '.' . $s;
    }

    /**
     * Ativa/cadastra cliente no Atacadão.
     * @param string $cpf CPF (serão usados os 11 dígitos finais)
     * @param string $status 'A' para ativo
     * @param string $codgrupo Código do grupo (ex.: '58')
     * @return array Resultado com chaves: ok, http, data, raw, error
     */
    public static function ativarCliente(string $cpf, string $status = 'A', string $codgrupo = '58'): array {
        $bearer = self::buildBearerToken();
        if ($bearer === null) {
            return [
                'ok' => false,
                'http' => 401,
                'error' => 'JWT ausente: defina ATACADAO_SECRET/TOKEN em atacadao/config.php',
                'raw' => null,
            ];
        }

        $cpfDigits = self::onlyDigits($cpf);
        if ($cpfDigits !== '' && strlen($cpfDigits) >= 11) {
            $cpfDigits = substr($cpfDigits, -11);
        }

        $endpoint = 'https://ddconnect.atacadaodiaadia.com.br/AtivarCliente';
        $payload = json_encode([
            'cpf' => $cpfDigits,
            'status' => $status,
            'codgrupo' => $codgrupo,
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
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

    /** Consulta cliente ativo (status SIM/NAO) */
    public static function consultaClienteAtivo(string $cpf): array {
        $bearer = self::buildBearerToken();
        if ($bearer === null) {
            return [
                'ok' => false,
                'http' => 401,
                'error' => 'JWT ausente: defina ATACADAO_SECRET/TOKEN em atacadao/config.php',
                'raw' => null,
            ];
        }
        $cpfDigits = self::onlyDigits($cpf);
        if ($cpfDigits !== '' && strlen($cpfDigits) >= 11) {
            $cpfDigits = substr($cpfDigits, -11);
        }

        $endpoint = defined('ATACADAO_ENDPOINT') ? (string)ATACADAO_ENDPOINT : 'https://ddconnect.atacadaodiaadia.com.br/consultaclienteativo';
        $payload = json_encode(['cpf' => $cpfDigits], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
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
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
}

?>