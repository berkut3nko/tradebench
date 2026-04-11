<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Model representing the currency_data table
 */
class CurrencyData {
    
    /**
     * Safely bulk insert market data
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
                /* Binance returns time in ms, PostgreSQL expects seconds for TO_TIMESTAMP */
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