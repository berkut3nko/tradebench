<?php

namespace App\Services;

use Exception;

/**
 * @brief Service providing external communication capabilities with the Binance REST API.
 */
class BinanceService {
    
    /** @var string Base endpoint URL for Binance Market Data */
    private string $baseUrl = 'https://api.binance.com/api/v3/';

    /**
     * @brief Fetches historical klines (candlesticks) from Binance.
     * @param string $symbol Trading pair identifier (e.g., 'BTCUSDT').
     * @param string $interval Candlestick dimension (e.g., '1h', '15m').
     * @param int $startTimeMs UNIX Start time in milliseconds.
     * @param int $endTimeMs UNIX End time in milliseconds.
     * @param int $limit Maximum number of candles to return (Max 1000).
     * @return array Decoded JSON array of candlestick data.
     * @throws Exception If the cURL request fails or returns an invalid code.
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
        // User-Agent enforcement to prevent rate-limit blocking from Cloudflare/Binance edges
        curl_setopt($ch, CURLOPT_USERAGENT, 'TradeBench/1.0 (PHP/8.2)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode !== 200) {
            throw new Exception("Failed to fetch data from Binance. HTTP Code: $httpCode");
        }

        $data = json_decode($response, true);
        
        if (!is_array($data)) {
            throw new Exception("Invalid response format received from Binance endpoint.");
        }

        return $data;
    }
}