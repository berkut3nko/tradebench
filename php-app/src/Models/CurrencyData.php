<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * @brief Data Access Object (DAO) for managing market candlestick data inside PostgreSQL.
 */
class CurrencyData {
    
    /**
     * @brief Safely bulk inserts historical market data into the database.
     * @param string $pair The market trading pair.
     * @param string $timeframe The candlestick timeframe.
     * @param array $klines The raw multi-dimensional array from the exchange API.
     * @return int Number of successfully inserted records.
     * @throws \Exception If the bulk transaction fails.
     */
    public static function saveBatch(string $pair, string $timeframe, array $klines): int {
        $db = Database::getConnection();
        $inserted = 0;

        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                INSERT INTO currency_data (pair_name, timeframe, tick_time, open_price, high_price, low_price, close_price, volume)
                VALUES (?, ?, TO_TIMESTAMP(?), ?, ?, ?, ?, ?)
                ON CONFLICT (pair_name, timeframe, tick_time) DO NOTHING
            ");

            foreach ($klines as $candle) {
                // Binance returns UNIX time in milliseconds, PostgreSQL expects seconds for TO_TIMESTAMP
                $timeSec = $candle[0] / 1000;
                $stmt->execute([
                    $pair, 
                    $timeframe, 
                    $timeSec, 
                    $candle[1], 
                    $candle[2], 
                    $candle[3], 
                    $candle[4], 
                    $candle[5]
                ]);
                $inserted += $stmt->rowCount();
            }
            
            $db->commit();
            return $inserted;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}