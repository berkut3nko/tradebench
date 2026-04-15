<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * @brief Data Access Object for handling market candlestick data.
 */
class CurrencyData
{
    /**
     * @brief Checks if the database already contains sufficient historical data.
     * @param string $pair The trading pair.
     * @param string $timeframe The timeframe.
     * @param int $startTime Start timestamp in milliseconds.
     * @param int $endTime End timestamp in milliseconds.
     * @return bool True if we have the data, false if we need to fetch from Binance.
     */
    public static function hasSufficientData(string $pair, string $timeframe, int $startTime, int $endTime): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as candle_count 
            FROM currency_data 
            WHERE pair_name = :pair 
              AND timeframe = :timeframe 
              AND tick_time >= TO_TIMESTAMP(:start_time)
              AND tick_time <= TO_TIMESTAMP(:end_time)
        ");

        $stmt->execute([
            ':pair' => $pair,
            ':timeframe' => $timeframe,
            // Convert milliseconds back to seconds for PostgreSQL TO_TIMESTAMP
            ':start_time' => $startTime / 1000,
            ':end_time' => $endTime / 1000
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $actualCount = (int) $result['candle_count'];

        $intervalMs = match ($timeframe) {
            '15m' => 15 * 60 * 1000,
            '1h'  => 60 * 60 * 1000,
            '4h'  => 4 * 3600 * 1000,
            '1d'  => 24 * 3600 * 1000,
            default => 60 * 60 * 1000
        };

        $durationMs = $endTime - $startTime;
        $expectedCount = (int) floor($durationMs / $intervalMs);

        if ($expectedCount > 0 && $actualCount >= ($expectedCount * 0.95)) {
            return true;
        }

        return false;
    }
}