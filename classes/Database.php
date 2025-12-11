<?php
/**
 * Classe Database Simples
 * classes/Database.php
 */

class Database {
    private static $instances = [];
    private $connection;
    
    private function __construct($dbname) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $dbname . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            // Configurar timezone do MySQL para America/Sao_Paulo
            $this->connection->exec("SET time_zone = '-03:00'");
            
            error_log("✅ Conexão Database estabelecida: " . $dbname);
        } catch (PDOException $e) {
            error_log("❌ Erro na conexão Database: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function getInstance($dbname) {
        if (!isset(self::$instances[$dbname])) {
            self::$instances[$dbname] = new self($dbname);
        }
        return self::$instances[$dbname];
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Previne clonagem
    private function __clone() {}
    
    // Previne deserialização
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}