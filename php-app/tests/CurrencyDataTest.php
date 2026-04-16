<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\CurrencyData;
use App\Core\Database;
use PDO;
use PDOStatement;
use ReflectionClass;

/**
 * @brief Unit test suite for the CurrencyData model.
 */
class CurrencyDataTest extends TestCase
{
    /**
     * @brief Tears down the mocked database instance to avoid state leakage between tests.
     * @return void
     */
    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(Database::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
        
        parent::tearDown();
    }

    /**
     * @brief Helper method to inject a mocked PDO database connection.
     * @param int $returnedCandleCount The mock count of rows that the DB should "return".
     * @return void
     */
    private function injectMockDatabase(int $returnedCandleCount): void
    {
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->expects($this->once())
                      ->method('execute')
                      ->willReturn(true);
        $mockStatement->expects($this->once())
                      ->method('fetch')
                      ->willReturn(['candle_count' => $returnedCandleCount]);

        $mockPdo = $this->createMock(PDO::class);
        $mockPdo->expects($this->once())
                ->method('prepare')
                ->willReturn($mockStatement);

        $reflection = new ReflectionClass(Database::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, $mockPdo);
    }

    /**
     * @brief Tests if the CurrencyData class exists and can be autoloaded.
     * @return void
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CurrencyData::class), "CurrencyData model class should exist and be autoloadable.");
    }

    /**
     * @brief Tests that hasSufficientData returns true when the dataset completeness is >= 95%.
     * @return void
     */
    public function testHasSufficientDataReturnsTrueWhenDataMeetsThreshold(): void
    {
        // Simulate a 10-hour duration -> Expected candles for 1h timeframe = 10
        $startTime = 100000000;
        $endTime = 100000000 + (10 * 3600 * 1000); 
        
        // Mock DB returning 10 candles (100% of the requested period)
        $this->injectMockDatabase(10);
        
        $hasData = CurrencyData::hasSufficientData('BTCUSDT', '1h', $startTime, $endTime);
        $this->assertTrue($hasData, "Method should return true when database contains enough data points.");
    }

    /**
     * @brief Tests that hasSufficientData returns false when the data points are missing.
     * @return void
     */
    public function testHasSufficientDataReturnsFalseWhenDataIsBelowThreshold(): void
    {
        // Simulate a 10-hour duration -> Expected candles for 1h timeframe = 10
        $startTime = 100000000;
        $endTime = 100000000 + (10 * 3600 * 1000); 
        
        // Mock DB returning 8 candles (80% of the period, which is below the 95% threshold)
        $this->injectMockDatabase(8);
        
        $hasData = CurrencyData::hasSufficientData('BTCUSDT', '1h', $startTime, $endTime);
        $this->assertFalse($hasData, "Method should return false when missing significant data points.");
    }

    /**
     * @brief Tests the hasSufficientData method handling of invalid timestamps.
     * @return void
     */
    public function testHasSufficientDataHandlesInvalidTimestampsGracefully(): void
    {
        // Database will return 0 rows for an invalid time range
        $this->injectMockDatabase(0);
        
        // Testing with inverted timestamps (end before start)
        // The expected mathematical count becomes negative, so it should safely return false
        $hasData = CurrencyData::hasSufficientData('BTCUSDT', '1h', 200000, 100000);
        $this->assertFalse($hasData, "Method should return false when end time is before start time.");
    }
}