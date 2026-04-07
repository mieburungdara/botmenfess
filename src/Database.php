<?php

namespace App;

/**
 * Database connection class using PDO
 */
class Database {
    private $connection;

    public function __construct() {
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $db   = defined('DB_NAME') ? DB_NAME : 'menfess_bot';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';

        try {
            $dsn = 'mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8mb4';
            $this->connection = new \PDO($dsn, $user, $pass);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $this->connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        } catch (\PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}