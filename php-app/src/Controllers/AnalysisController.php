<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\AuthMiddleware;
use App\AnalysisClient;
use Predis\Client as RedisClient;

class AnalysisController {
    
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function start(): void {
        $authData = AuthMiddleware::authenticate();
        $userId = $authData['id'];
        $userRole = $authData['role'];

        $input = json_decode(file_get_contents('php://input'), true);
        $pair = $input['pair'] ?? 'BTCUSDT';
        
        $startDate = !empty($input['startDate']) ? $input['startDate'] : null;
        $endDate = !empty($input['endDate']) ? $input['endDate'] : null;
        $timeframe = $input['timeframe'] ?? '1h';
        
        $startTimestamp = $startDate ? strtotime($startDate . ' 00:00:00') : (time() - 86400 * 30);
        $endTimestamp = $endDate ? strtotime($endDate . ' 23:59:59') : time();
        
        $daysRequested = ($endTimestamp - $startTimestamp) / 86400;
        
        if ($userRole !== 'pro' && $userRole !== 'admin') {
            if ($daysRequested > 30) {
                Response::error("Standard accounts are limited to 30 days of backtesting.", 403);
            }
            if ($timeframe !== '1h') {
                Response::error("Custom timeframes are available only for PRO accounts.", 403);
            }
        }
        
        try {
            $startMs = $startTimestamp * 1000;
            $endMs = $endTimestamp * 1000;
            $url = "https://api.binance.com/api/v3/klines?symbol={$pair}&interval={$timeframe}&startTime={$startMs}&endTime={$endMs}&limit=1000";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response !== false && $httpCode === 200) {
                $data = json_decode($response, true);
                if (is_array($data) && count($data) > 0) {
                    $this->db->beginTransaction();
                    $stmtSync = $this->db->prepare("
                        INSERT INTO currency_data (pair_name, timeframe, tick_time, open_price, high_price, low_price, close_price, volume)
                        VALUES (?, ?, TO_TIMESTAMP(?), ?, ?, ?, ?, ?)
                        ON CONFLICT (pair_name, timeframe, tick_time) DO NOTHING
                    ");
                    foreach ($data as $candle) {
                        $stmtSync->execute([$pair, $timeframe, $candle[0] / 1000, $candle[1], $candle[2], $candle[3], $candle[4], $candle[5]]);
                    }
                    $this->db->commit();
                }
            }
        } catch (\Throwable $syncError) {
            if ($this->db->inTransaction()) $this->db->rollBack();
        }
        
        /* ВАЖЛИВО: Гнучке парсіння параметрів (array -> string) */
        $strategy = $input['strategy'] ?? 'SMA_CROSS';
        $params = $input['params'] ?? [9, 21]; // Default fallback
        
        // Збираємо строку на кшталт "RSI:14:70:30"
        $strategyPayload = $strategy;
        foreach ($params as $param) {
            $strategyPayload .= ":" . intval($param);
        }

        $taskId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        $stmt = $this->db->prepare("INSERT INTO analysis_tasks (id, user_id, pair, status) VALUES (?, ?, ?, 'PENDING')");
        $stmt->execute([$taskId, $userId, $pair]);

        $client = new AnalysisClient('cpp-engine:50051');
        $grpcResult = $client->requestAnalysis((string)$userId, $pair, $strategyPayload, $taskId, [
            'start' => $startTimestamp,
            'end' => $endTimestamp
        ], $timeframe);

        if (!$grpcResult['success']) {
            Response::error("gRPC Engine Error: " . ($grpcResult['error'] ?? 'Unknown'), 500);
        }

        Response::json(["task_id" => $taskId, "status" => "PENDING", "message" => "Analysis started"], 202);
    }

    public function history(): void {
        $authData = AuthMiddleware::authenticate();
        $userId = $authData['id'];
        
        $stmt = $this->db->prepare("
            SELECT t.id as task_id, t.pair, t.status, t.created_at, r.result_data
            FROM analysis_tasks t
            LEFT JOIN analysis_results r ON t.id = r.task_id
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        Response::json($stmt->fetchAll());
    }

    public function stream(): void {
        $token = $_GET['token'] ?? null;
        AuthMiddleware::authenticate($token);

        if (function_exists('set_time_limit')) set_time_limit(0);
        ignore_user_abort(false); 
        
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) ob_end_clean();
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ":" . str_repeat(" ", 2048) . "\n\n";
        echo "event: ping\ndata: connected\n\n";
        flush();

        $redis = new RedisClient([
            'scheme' => 'tcp', 
            'host' => 'redis', 
            'port' => 6379, 
            'read_write_timeout' => 3
        ]);

        while (true) {
            try {
                $pubsub = $redis->pubSubLoop();
                $pubsub->subscribe('analysis_events');
                
                foreach ($pubsub as $message) {
                    if ($message->kind === 'message') {
                        echo "data: " . $message->payload . "\n\n";
                        @ob_flush();
                        flush();
                    }
                }
            } catch (\Throwable $e) {
                echo ": keepalive\n\n";
                @ob_flush();
                flush();
                
                if (connection_aborted()) break;
                
                try {
                    $redis->disconnect();
                    $redis->connect();
                } catch (\Throwable $e2) {
                    sleep(1);
                }
            }
        }
        exit;
    }
}