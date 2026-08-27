<?php
/**
 * Database Connection Class (PDO)
 * 
 * Provides a secure, centralized PDO database connection using best practices:
 * - Emulate prepares disabled (forces server-side prepared statements)
 * - Exception error mode enabled
 * - UTF-8 character encoding set strictly
 */
class Database {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            $host = 'localhost';
            $db   = 'internship management system';
            $user = 'root';
            $pass = '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                // Set error mode to throw exceptions
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Set default fetch mode to associative array
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Disable emulation of prepared statements to prevent SQL Injection
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (\PDOException $e) {
                // Return secure JSON error and terminate cleanly (never output raw stack traces)
                header('Content-Type: application/json', true, 500);
                echo json_encode([
                    'error' => 'Database connection failed securely.'
                ]);
                exit;
            }
        }
        return self::$pdo;
    }
}
