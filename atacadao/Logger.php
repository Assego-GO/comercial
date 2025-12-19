<?php
// atacadao/Logger.php
// Logger dedicado para rastreamento de integrações com Atacadão

declare(strict_types=1);

class AtacadaoLogger {
    private static string $logFile = '';
    private static string $errorLogFile = '';

    public static function initialize(): void {
        $logsDir = __DIR__ . '/../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        self::$logFile = $logsDir . '/atacadao_integracao.log';
        self::$errorLogFile = $logsDir . '/atacadao_erros.log';
    }

    /**
     * Registra ativação de cliente
     */
    public static function logAtivacao(
        int $associadoId,
        string $cpf,
        string $status,
        string $codgrupo,
        int $httpCode,
        bool $sucesso,
        ?array $resposta = null,
        ?string $erro = null
    ): void {
        self::initialize();

        $timestamp = date('Y-m-d H:i:s');
        $cpfMascarado = self::mascararCpf($cpf);
        $statusStr = $sucesso ? '✅ SUCESSO' : '❌ FALHA';

        $mensagem = sprintf(
            "[%s] %s | ID: %d | CPF: %s | Status: %s | CodGrupo: %s | HTTP: %d",
            $timestamp,
            $statusStr,
            $associadoId,
            $cpfMascarado,
            $status,
            $codgrupo,
            $httpCode
        );

        if ($resposta) {
            $mensagem .= " | Resposta: " . json_encode($resposta, JSON_UNESCAPED_UNICODE);
        }

        if ($erro) {
            $mensagem .= " | Erro: " . $erro;
        }

        $mensagem .= PHP_EOL;

        // Log no arquivo principal
        file_put_contents(self::$logFile, $mensagem, FILE_APPEND);

        // Log em error_log do PHP também
        error_log($mensagem);

        // Se falha, registra também no arquivo de erros
        if (!$sucesso) {
            file_put_contents(self::$errorLogFile, $mensagem, FILE_APPEND);
        }
    }

    /**
     * Registra consulta de status
     */
    public static function logConsulta(
        string $cpf,
        int $httpCode,
        bool $sucesso,
        ?string $status = null,
        ?string $erro = null
    ): void {
        self::initialize();

        $timestamp = date('Y-m-d H:i:s');
        $cpfMascarado = self::mascararCpf($cpf);
        $statusStr = $sucesso ? '✅ CONSULTA OK' : '❌ CONSULTA FALHA';

        $mensagem = sprintf(
            "[%s] %s | CPF: %s | HTTP: %d",
            $timestamp,
            $statusStr,
            $cpfMascarado,
            $httpCode
        );

        if ($status) {
            $mensagem .= " | Status Atacadão: " . $status;
        }

        if ($erro) {
            $mensagem .= " | Erro: " . $erro;
        }

        $mensagem .= PHP_EOL;

        file_put_contents(self::$logFile, $mensagem, FILE_APPEND);
        error_log($mensagem);

        if (!$sucesso) {
            file_put_contents(self::$errorLogFile, $mensagem, FILE_APPEND);
        }
    }

    /**
     * Registra erro crítico
     */
    public static function logErro(string $operacao, string $mensagem, ?int $associadoId = null): void {
        self::initialize();

        $timestamp = date('Y-m-d H:i:s');
        $linhaErro = sprintf(
            "[%s] 🚨 ERRO CRÍTICO | Operação: %s | ID Associado: %s | Mensagem: %s%s",
            $timestamp,
            $operacao,
            $associadoId ?? 'N/A',
            $mensagem,
            PHP_EOL
        );

        file_put_contents(self::$errorLogFile, $linhaErro, FILE_APPEND);
        file_put_contents(self::$logFile, $linhaErro, FILE_APPEND);
        error_log($linhaErro);
    }

    /**
     * Registra atualização de status no banco
     */
    public static function logAtualizacaoBanco(
        int $associadoId,
        int $novoStatus,
        bool $sucesso,
        ?string $erro = null
    ): void {
        self::initialize();

        $timestamp = date('Y-m-d H:i:s');
        $statusStr = $sucesso ? '✅ BANCO ATUALIZADO' : '❌ FALHA AO ATUALIZAR BANCO';

        $mensagem = sprintf(
            "[%s] %s | ID: %d | ativo_atacadao = %d",
            $timestamp,
            $statusStr,
            $associadoId,
            $novoStatus
        );

        if ($erro) {
            $mensagem .= " | Erro: " . $erro;
        }

        $mensagem .= PHP_EOL;

        file_put_contents(self::$logFile, $mensagem, FILE_APPEND);
        error_log($mensagem);

        if (!$sucesso) {
            file_put_contents(self::$errorLogFile, $mensagem, FILE_APPEND);
        }
    }

    /**
     * Mascara CPF para logs (ex: 123.456.789-** )
     */
    private static function mascararCpf(string $cpf): string {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11) {
            return '***.***.***-**';
        }
        return substr($cpf, 0, 3) . '.' .
               substr($cpf, 3, 3) . '.' .
               substr($cpf, 6, 3) . '-**';
    }

    /**
     * Retorna caminho do arquivo de log (útil para debugging)
     */
    public static function getLogFile(): string {
        self::initialize();
        return self::$logFile;
    }

    public static function getErrorLogFile(): string {
        self::initialize();
        return self::$errorLogFile;
    }

    /**
     * Lê últimas N linhas do log
     */
    public static function getUltimas(int $linhas = 50): array {
        self::initialize();

        if (!file_exists(self::$logFile)) {
            return [];
        }

        $arquivo = file(self::$logFile);
        return array_slice($arquivo, -$linhas);
    }

    /**
     * Lê últimos erros
     */
    public static function getUltimosErros(int $linhas = 30): array {
        self::initialize();

        if (!file_exists(self::$errorLogFile)) {
            return [];
        }

        $arquivo = file(self::$errorLogFile);
        return array_slice($arquivo, -$linhas);
    }
}
?>
