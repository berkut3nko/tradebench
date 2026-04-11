<?php

namespace App\Controllers;

use App\Core\Response;
use App\Core\AuthMiddleware;
use App\AnalysisClient;
use App\Services\BinanceService;
use App\Models\CurrencyData;
use App\Models\AnalysisTask;
use Predis\Client as RedisClient;

/**
 * Handles Backtesting Core logic and Streaming (Slim Controller)
 */
class AnalysisController {
    
    private BinanceService $binanceService;

    public function __construct() {
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
        
        /* 1. Authorization checks */
        if ($userRole !== 'pro' && $userRole !== 'admin') {
            if ($daysRequested > 30) {
                Response::error("Standard accounts are limited to 30 days of backtesting.", 403);
            }
            if ($timeframe !== '1h') {
                Response::error("Custom timeframes are available only for PRO accounts.", 403);
            }
        }
        
        /* 2. Data Ingestion (Delegated to Service and Model) */
        try {
            $klines = $this->binanceService->fetchHistoricalData(
                $pair, 
                $timeframe, 
                $startTimestamp * 1000, 
                $endTimestamp * 1000
            );
            
            if (!empty($klines)) {
                CurrencyData::saveBatch($pair, $timeframe, $klines);
            }
        } catch (\Exception $e) {
            // We ignore ingestion errors, core will use existing DB data
        }
        
        /* 3. Strategy Configuration */
        $strategy = $input['strategy'] ?? 'SMA_CROSS';
        $params = $input['params'] ?? [9, 21]; 
        
        $strategyPayload = $strategy;
        foreach ($params as $param) {
            $strategyPayload .= ":" . intval($param);
        }

        /* 4. Task Registration (Delegated to Model) */
        $taskId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
        AnalysisTask::create($taskId, $userId, $pair);

        /* 5. Trigger gRPC Core */
        $client = new AnalysisClient('cpp-engine:50051');
        $grpcResult = $client->requestAnalysis((string)$userId, $pair, $strategyPayload, $taskId, [
            'start' => $startTimestamp,
            'end' => $endTimestamp
        ], $timeframe);

        if (!$grpcResult['success']) {
            Response::error("gRPC Engine Error: " . ($grpcResult['error'] ?? 'Unknown'), 500);
        }

        /* 6. Return Response */
        Response::json(["task_id" => $taskId, "status" => "PENDING", "message" => "Analysis started"], 202);
    }

    public function history(): void {
        $authData = AuthMiddleware::authenticate();
        $userId = $authData['id'];
        
        /* Delegated to Model */
        $history = AnalysisTask::getHistoryByUser($userId);
        
        Response::json($history);
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