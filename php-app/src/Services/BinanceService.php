<?php

namespace App\Services;

use Exception;

/**
 * Service responsible for external communication with Binance API
 */
class BinanceService {
    
    private string $baseUrl = 'https://api.binance.com/api/v3/';

    /**
     * Fetch historical klines (candles) from Binance
     * * @param string $symbol e.g. BTCUSDT
     * @param string $interval e.g. 1h, 15m
     * @param int $startTimeMs Start time in milliseconds
     * @param int $endTimeMs End time in milliseconds
     * @param int $limit Maximum number of candles (up to 1000)
     * @return array
     * @throws Exception If API request fails
     */
    public function fetchHistoricalData(string $symbol, string $interval, int $startTimeMs, int $endTimeMs, int $limit = 1000): array {
        $endpoint = sprintf(
            "%sklines?symbol=%s&interval=%s&startTime=%d&endTime=%d&limit=%d",
            $this->baseUrl,
            $symbol,
            $interval,
            $startTimeMs,
            $endTimeMs,
            $limit
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /* Binance requires a User-Agent to prevent blocking */
        curl_setopt($ch, CURLOPT_USERAGENT, 'TradeBench/1.0 (PHP/8.2)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode !== 200) {
            throw new Exception("Failed to fetch data from Binance. HTTP Code: $httpCode");
        }

        $data = json_decode($response, true);
        
        if (!is_array($data)) {
            throw new Exception("Invalid response format from Binance");
        }

        return $data;
    }
}