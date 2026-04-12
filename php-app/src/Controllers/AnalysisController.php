<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\AuthMiddleware;
use App\AnalysisClient;
use App\Services\BinanceService;
use Predis\Client as RedisClient;

class AnalysisController {
    
    private \PDO $db;
    private BinanceService $binanceService;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->binanceService = new BinanceService();
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
        
        $strategy = $input['strategy'] ?? 'SMA_CROSS';

        if ($userRole !== 'pro' && $userRole !== 'admin') {
            if ($daysRequested > 30) {
                Response::error("Standard accounts are limited to 30 days of backtesting.", 403);
            }
            if ($timeframe !== '1h') {
                Response::error("Custom timeframes are available only for PRO accounts.", 403);
            }
            if ($strategy === 'OPTIMIZE') {
                Response::error("Генетичний автопідбір доступний лише для користувачів з підпискою PRO.", 403);
            }
        }

        try {
            $this->db->beginTransaction();
            $oldTasksStmt = $this->db->query("SELECT id FROM analysis_tasks WHERE created_at < NOW() - INTERVAL '30 days'");
            $oldTasks = $oldTasksStmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (count($oldTasks) > 0) {
                $placeholders = implode(',', array_fill(0, count($oldTasks), '?'));
                $this->db->prepare("DELETE FROM analysis_results WHERE task_id IN ($placeholders)")->execute($oldTasks);
                $this->db->prepare("DELETE FROM analysis_tasks WHERE id IN ($placeholders)")->execute($oldTasks);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
        }
        
        try {
            $klines = $this->binanceService->fetchHistoricalData(
                $pair, 
                $timeframe, 
                $startTimestamp * 1000, 
                $endTimestamp * 1000
            );
            
            if (!empty($klines)) {
                $this->db->beginTransaction();
                $stmtSync = $this->db->prepare("
                    INSERT INTO currency_data (pair_name, timeframe, tick_time, open_price, high_price, low_price, close_price, volume)
                    VALUES (?, ?, TO_TIMESTAMP(?), ?, ?, ?, ?, ?)
                    ON CONFLICT (pair_name, timeframe, tick_time) DO NOTHING
                ");
                foreach ($klines as $candle) {
                    $stmtSync->execute([$pair, $timeframe, $candle[0] / 1000, $candle[1], $candle[2], $candle[3], $candle[4], $candle[5]]);
                }
                $this->db->commit();
            }
        } catch (\Throwable $syncError) {
            if ($this->db->inTransaction()) $this->db->rollBack();
        }
        
        $params = $input['params'] ?? [];
        $strategyPayload = $strategy;
        
        if ($strategy !== 'OPTIMIZE') {
            foreach ($params as $param) {
                $strategyPayload .= ":" . floatval($param);
            }
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
            $errorDetails = $grpcResult['error'] ?? 'Unknown';
            if (strpos($errorDetails, 'failed to connect') !== false || strpos($errorDetails, 'Connection refused') !== false) {
                Response::error("Обчислювальне ядро (C++) наразі недоступне. Будь ласка, перевірте, чи запущено сервер аналітики.", 503);
            }
            Response::error("Помилка обчислювального ядра: " . $errorDetails, 500);
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
            LIMIT 20
        ");
        $stmt->execute([$userId]);
        Response::json($stmt->fetchAll());
    }

    public function deleteHistory(string $taskId): void {
        $authData = AuthMiddleware::authenticate();
        $userId = $authData['id'];

        $stmt = $this->db->prepare("SELECT user_id FROM analysis_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            Response::error("Task not found", 404);
        }
        
        if ($task['user_id'] != $userId && $authData['role'] !== 'admin') {
            Response::error("Unauthorized to delete this task", 403);
        }

        try {
            $this->db->beginTransaction();
            $this->db->prepare("DELETE FROM analysis_results WHERE task_id = ?")->execute([$taskId]);
            $this->db->prepare("DELETE FROM analysis_tasks WHERE id = ?")->execute([$taskId]);
            $this->db->commit();
            
            Response::json(["message" => "Backtest deleted successfully"]);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            Response::error("Failed to delete backtest", 500);
        }
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