<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\AnalysisClient;

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

try {
    $pdo = new PDO('pgsql:host=db;port=5432;dbname=analyzer_db', 'user', 'pass', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Ensure test user exists
    $pdo->exec("INSERT INTO users (id, email, password_hash) VALUES (1, 'test@tradebench.com', 'hash123') ON CONFLICT DO NOTHING");

    // ROUTE: POST /api/analysis/start
    if ($method === 'POST' && $path === '/api/analysis/start') {
        $input = json_decode(file_get_contents('php://input'), true);
        $pair = $input['pair'] ?? 'EURUSD';
        $strategy = $input['strategy'] ?? 'SMA_CROSS';

        $taskId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        $stmt = $pdo->prepare("INSERT INTO analysis_tasks (id, user_id, pair, status) VALUES (?, 1, ?, 'PENDING')");
        $stmt->execute([$taskId, $pair]);

        $client = new AnalysisClient('cpp-engine:50051');
        $grpcResult = $client->requestAnalysis('1', $pair, $strategy, $taskId);

        if (!$grpcResult['success']) {
            http_response_code(500);
            echo json_encode(["error" => "C++ Core unreachable", "details" => $grpcResult['error']]);
            exit;
        }

        echo json_encode(["task_id" => $taskId, "status" => "PENDING", "message" => "Analysis started"]);
        exit;
    }

    // ROUTE: GET /api/analysis/status/{taskId}
    if ($method === 'GET' && preg_match('#^/api/analysis/status/([a-f0-9\-]+)$#', $path, $matches)) {
        $taskId = $matches[1];
        
        $stmt = $pdo->prepare("SELECT status FROM analysis_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            http_response_code(404);
            echo json_encode(["error" => "Task not found"]);
            exit;
        }

        echo json_encode(["task_id" => $taskId, "status" => $task['status']]);
        exit;
    }

    // Default fallback
    echo json_encode(["status" => "online", "message" => "TradeBench API is running"]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error", "details" => $e->getMessage()]);
}