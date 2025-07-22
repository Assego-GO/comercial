<?php
/**
 * Classe de conexão com banco de dados
 * classes/Database.php
 */

class Database {
    private static $instances = [];
    private $conn;
    
    private function __construct($dbname) {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . $dbname . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            if (DEBUG_MODE) {
                die("Erro de conexão: " . $e->getMessage());
            } else {
                die("Erro ao conectar com o banco de dados.");
            }
        }
    }
    
    /**
     * Retorna instância única da conexão (Singleton)
     */
    public static function getInstance($dbname = DB_NAME_RELATORIOS) {
        if (!isset(self::$instances[$dbname])) {
            self::$instances[$dbname] = new self($dbname);
        }
        return self::$instances[$dbname];
    }
    
    /**
     * Retorna a conexão PDO
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Previne clonagem da instância
     */
    private function __clone() {}
    
    /**
     * Previne desserialização da instância
     */
    public function __wakeup() {
        throw new Exception("Não é possível desserializar singleton");
    }
}