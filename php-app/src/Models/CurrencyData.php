<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use Exception;

/**
 * @brief Data Access Object for handling market candlestick data.
 */
class CurrencyData
{
    /**
     * @brief Checks if the database already contains sufficient historical data for the requested period.
     * * @param string $pair The trading pair (e.g., BTCUSDT).
     * @param string $timeframe The timeframe (e.g., 1h, 15m).
     * @param int $startTime Start timestamp in milliseconds.
     * @param int $endTime End timestamp in milliseconds.
     * @return bool Returns true if we already have the data, false if we need to fetch from Binance.
     */
    public static function hasSufficientData(string $pair, string $timeframe, int $startTime, int $endTime): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as candle_count 
            FROM market_data 
            WHERE pair = :pair 
              AND timeframe = :timeframe 
              AND timestamp >= :start_time 
              AND timestamp <= :end_time
        ");

        $stmt->execute([
            ':pair' => $pair,
            ':timeframe' => $timeframe,
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $actualCount = (int) $result['candle_count'];

        // Calculate interval duration in milliseconds
        $intervalMs = match ($timeframe) {
            '15m' => 15 * 60 * 1000,
            '1h'  => 60 * 60 * 1000,
            '4h'  => 4 * 3600 * 1000,
            '1d'  => 24 * 3600 * 1000,
            default => 60 * 60 * 1000
        };

        // Calculate expected number of candles
        $durationMs = $endTime - $startTime;
        $expectedCount = (int) floor($durationMs / $intervalMs);

        // If actual count is at least 95% of expected (allowing for Binance API maintenance gaps), we have the data
        if ($expectedCount > 0 && $actualCount >= ($expectedCount * 0.95)) {
            return true;
        }

        return false;
    }

    // ... existing saveBatch and other methods ...
}