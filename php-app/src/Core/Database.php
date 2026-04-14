<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * @brief Singleton Database Connection Manager for PostgreSQL.
 */
class Database {
    /** @var PDO|null The single PDO instance used across the application. */
    private static ?PDO $instance = null;

    /**
     * @brief Retrieves the active PDO connection instance or establishes a new one.
     * @return PDO The active database connection.
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s', 
                    $_ENV['DB_HOST'], 
                    $_ENV['DB_PORT'], 
                    $_ENV['DB_DATABASE']
                );
                
                self::$instance = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);

                self::runMigrations(self::$instance);

            } catch (PDOException $e) {
                Response::error("Database connection failed", 500, $e->getMessage());
            }
        }
        return self::$instance;
    }

    /**
     * @brief Ensures that necessary dynamic tables exist upon connection.
     * @param PDO $pdo The active PDO instance.
     */
    private static function runMigrations(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                token VARCHAR(255) UNIQUE NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }
}