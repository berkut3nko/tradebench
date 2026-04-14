<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use Exception;
use PDO;
use ReflectionClass;

/**
 * @brief Unit test suite for the Database connection manager.
 */
class DatabaseTest extends TestCase
{
    /**
     * @brief Tests if the Database class throws an exception when provided with invalid credentials.
     * @throws Exception Expected to be thrown by Response::error in CLI mode during a failed connection.
     * @return void
     */
    public function testGetConnectionThrowsExceptionOnInvalidCredentials(): void
    {
        $_ENV['DB_HOST'] = 'invalid_host';
        $_ENV['DB_PORT'] = '5432';
        $_ENV['DB_DATABASE'] = 'dummy';
        $_ENV['DB_USERNAME'] = 'dummy';
        $_ENV['DB_PASSWORD'] = 'dummy';

        $reflection = new ReflectionClass(Database::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Database connection failed");

        Database::getConnection();
    }

    /**
     * @brief Tests if the Database connection returns a valid PDO instance with standard testing credentials.
     * @return void
     */
    public function testGetConnectionReturnsPdoInstance(): void
    {
        $_ENV['DB_HOST'] = 'db';
        $_ENV['DB_PORT'] = '5432';
        $_ENV['DB_DATABASE'] = 'analyzer_db';
        $_ENV['DB_USERNAME'] = 'user';
        $_ENV['DB_PASSWORD'] = 'pass';

        $reflection = new ReflectionClass(Database::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        try {
            $pdo = Database::getConnection();
            $this->assertInstanceOf(PDO::class, $pdo);
        } catch (Exception $e) {
            $this->markTestSkipped("Database is not reachable in this test environment. " . $e->getMessage());
        }
    }

    /**
     * @brief Tests if the Database class correctly implements the Singleton architectural pattern.
     * @return void
     */
    public function testDatabaseIsSingleton(): void
    {
        $_ENV['DB_HOST'] = 'db';
        $_ENV['DB_PORT'] = '5432';
        $_ENV['DB_DATABASE'] = 'analyzer_db';
        $_ENV['DB_USERNAME'] = 'user';
        $_ENV['DB_PASSWORD'] = 'pass';

        $reflection = new ReflectionClass(Database::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        try {
            $pdo1 = Database::getConnection();
            $pdo2 = Database::getConnection();
            $this->assertSame($pdo1, $pdo2, "Database::getConnection must return the exact same PDO instance memory reference.");
        } catch (Exception $e) {
            $this->markTestSkipped("Database is not reachable in this test environment. " . $e->getMessage());
        }
    }
}