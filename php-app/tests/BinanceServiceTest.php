<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\BinanceService;
use Exception;

/**
 * @brief Unit test suite for the Binance external API communication service.
 */
class BinanceServiceTest extends TestCase
{
    /**
     * @brief Tests if the BinanceService correctly throws an exception for unprocessable or invalid trading symbols.
     * @throws Exception Expected to be thrown when the Binance HTTP API rejects the malformed request.
     * @return void
     */
    public function testFetchHistoricalDataThrowsExceptionForInvalidSymbol(): void
    {
        $service = new BinanceService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to fetch data from Binance");

        $invalidSymbol = "INVALID_CRYPTO_PAIR_123";
        
        // Use a standard timeframe and random timestamps
        $startTime = (time() - 3600) * 1000;
        $endTime = time() * 1000;

        $service->fetchHistoricalData($invalidSymbol, "1h", $startTime, $endTime);
    }

    /**
     * @brief Tests successful instantiation of the BinanceService.
     * @return void
     */
    public function testBinanceServiceInstantiatesSuccessfully(): void
    {
        $service = new BinanceService();
        $this->assertInstanceOf(BinanceService::class, $service);
    }
}