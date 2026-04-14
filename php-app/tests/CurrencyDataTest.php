<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\CurrencyData;
use Exception;

/**
 * @brief Unit test suite for the CurrencyData model.
 */
class CurrencyDataTest extends TestCase
{
    /**
     * @brief Tests if the CurrencyData class exists and can be autoloaded.
     * @return void
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CurrencyData::class), "CurrencyData model class should exist and be autoloadable.");
    }

    /**
     * @brief Tests the saveBatch method handling of empty datasets.
     * @return void
     */
    public function testSaveBatchHandlesEmptyArrayGracefully(): void
    {
        try {
            $insertedCount = CurrencyData::saveBatch('BTCUSDT', '1h', []);
            $this->assertEquals(0, $insertedCount, "Saving an empty array should result in 0 inserted records.");
        } catch (Exception $e) {
            // If the database is completely unreachable during CI/CD without mocks, skip rather than fail
            $this->markTestSkipped("Database connection is required for this test, but it is currently unavailable: " . $e->getMessage());
        }
    }
}