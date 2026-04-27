<?php
/**
 * Database connection configuration using PDO
 * 
 * This file provides a secure database connection with proper error handling.
 * Credentials are kept here for simplicity (in production, use environment variables).
 */

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'timetracker');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');

// PDO options for security and error handling
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

/**
 * Get PDO database connection
 * 
 * @return PDO Database connection instance
 * @throws PDOException If connection fails
 */
function getDbConnection(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Log error securely (never expose credentials in production)
            error_log('Database connection failed: ' . $e->getMessage());
            
            // Throw a generic error message to avoid exposing sensitive information
            throw new PDOException(
                'Database connection failed. Please check the configuration.',
                (int)$e->getCode(),
                $e
            );
        }
    }
    
    return $pdo;
}

/**
 * Execute a prepared statement with named parameters
 * 
 * @param string $sql SQL query with named placeholders
 * @param array $params Associative array of parameters
 * @return PDOStatement Executed statement
 */
function executeQuery(string $sql, array $params = []): PDOStatement
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('Query execution failed: ' . $e->getMessage());
        throw $e;
    }
}