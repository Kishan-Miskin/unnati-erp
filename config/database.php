<?php
/**
 * Database Configuration — Unnatti Enterprises Mini ERP
 * PHP 8.4.27+ Required - Security Patched
 */

define('DB_HOST',    '127.0.0.1');
define('DB_NAME',    'mini_erp');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn     = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone='+05:30'",
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed. Please check configuration.']));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /** Shorthand: prepare + execute + return statement */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch single row */
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    /** Fetch all rows */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Insert and return last insert ID */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    /** Execute DML (UPDATE/DELETE) and return affected rows */
    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    /** Begin transaction */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /** Commit transaction */
    public function commit() {
        return $this->pdo->commit();
    }

    /** Rollback transaction */
    public function rollback() {
        return $this->pdo->rollBack();
    }

    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize singleton."); }
}

/** Global helper */
function db() {
    return Database::getInstance();
}
