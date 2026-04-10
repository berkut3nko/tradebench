<?php
// Вимикаємо виведення HTML-помилок, щоб вони не ламали JSON відповіді
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use App\AnalysisClient;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;
use Predis\Client as RedisClient;

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/api/analysis/stream') {
    header('Content-Type: application/json');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
    $jwtSecret = $_ENV['JWT_SECRET'];

    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_DATABASE']);
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // ROUTE: POST /api/auth/register
    if ($method === 'POST' && $path === '/api/auth/register') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(["error" => "Email and password are required"]);
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?) RETURNING id");
            $stmt->execute([$email, $hash]);
            echo json_encode(["message" => "User registered successfully", "user_id" => $stmt->fetchColumn()]);
        } catch (\PDOException $e) {
            http_response_code(409);
            echo json_encode(["error" => "Email already exists"]);
        }
        exit;
    }

    // ROUTE: POST /api/auth/login
    if ($method === 'POST' && $path === '/api/auth/login') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $payload = [
                'iss' => 'tradebench_api',
                'sub' => $user['id'],
                'iat' => time(),
                'exp' => time() + (86400 * 7)
            ];
            echo json_encode(["token" => JWT::encode($payload, $jwtSecret, 'HS256'), "user_id" => $user['id']]);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Invalid email or password"]);
        }
        exit;
    }

    // Helper: Verify JWT
    $authenticate = function($tokenFromQuery = null) use ($jwtSecret) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = null;

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        } elseif ($tokenFromQuery) {
            $token = $tokenFromQuery;
        }
        
        if (!$token) {
            http_response_code(401);
            echo json_encode(["error" => "Token not provided"]);
            exit;
        }

        try {
            return JWT::decode($token, new Key($jwtSecret, 'HS256'))->sub;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(["error" => "Invalid or expired token"]);
            exit;
        }
    };

    // ROUTE: POST /api/data/sync
    if ($method === 'POST' && $path === '/api/data/sync') {
        $userId = $authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        $pair = $input['pair'] ?? 'BTCUSDT';
        if ($pair === 'BTCUSD') $pair = 'BTCUSDT';
        if ($pair === 'EURUSD') $pair = 'EURUSDT';
        
        $url = "https://api.binance.com/api/v3/klines?symbol={$pair}&interval=1h&limit=500";
        
        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            http_response_code(502);
            echo json_encode(["error" => "Failed to fetch data from Binance API"]);
            exit;
        }

        $data = json_decode($response, true);

        $stmt = $pdo->prepare("
            INSERT INTO currency_data (pair_name, tick_time, open_price, high_price, low_price, close_price, volume)
            VALUES (?, TO_TIMESTAMP(?), ?, ?, ?, ?, ?)
            ON CONFLICT (pair_name, tick_time) DO NOTHING
        ");

        $pdo->beginTransaction();
        $insertedCount = 0;
        
        foreach ($data as $candle) {
            $timeSec = $candle[0] / 1000;
            $stmt->execute([
                $pair, 
                $timeSec, 
                $candle[1], 
                $candle[2], 
                $candle[3], 
                $candle[4], 
                $candle[5]  
            ]);
            if ($stmt->rowCount() > 0) {
                $insertedCount++;
            }
        }
        $pdo->commit();

        echo json_encode([
            "message" => "Data synchronized successfully", 
            "pair" => $pair,
            "records_inserted" => $insertedCount
        ]);
        exit;
    }

    // ROUTE: POST /api/analysis/start
    if ($method === 'POST' && $path === '/api/analysis/start') {
        $userId = $authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        $pair = $input['pair'] ?? 'BTCUSDT';
        $strategy = $input['strategy'] ?? 'SMA_CROSS';

        $taskId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        $stmt = $pdo->prepare("INSERT INTO analysis_tasks (id, user_id, pair, status) VALUES (?, ?, ?, 'PENDING')");
        $stmt->execute([$taskId, $userId, $pair]);

        $client = new AnalysisClient('cpp-engine:50051');
        $grpcResult = $client->requestAnalysis((string)$userId, $pair, $strategy, $taskId);

        if (!$grpcResult['success']) {
            http_response_code(500);
            echo json_encode(["error" => "gRPC Error", "details" => $grpcResult['error'] ?? '']);
            exit;
        }

        echo json_encode(["task_id" => $taskId, "status" => "PENDING", "message" => "Analysis started"]);
        exit;
    }

    // ROUTE: GET /api/analysis/status/{taskId}
    if ($method === 'GET' && preg_match('#^/api/analysis/status/([a-f0-9\-]+)$#', $path, $matches)) {
        $userId = $authenticate();
        $taskId = $matches[1];
        
        $stmt = $pdo->prepare("SELECT status FROM analysis_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        $task = $stmt->fetch();

        if (!$task) {
            http_response_code(404);
            echo json_encode(["error" => "Task not found or access denied"]);
            exit;
        }

        echo json_encode(["task_id" => $taskId, "status" => $task['status']]);
        exit;
    }

    // ROUTE: GET /api/analysis/history (PROTECTED)
    if ($method === 'GET' && $path === '/api/analysis/history') {
        $userId = $authenticate();
        
        $stmt = $pdo->prepare("
            SELECT t.id as task_id, t.pair, t.status, t.created_at, r.result_data
            FROM analysis_tasks t
            LEFT JOIN analysis_results r ON t.id = r.task_id
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $history = $stmt->fetchAll();
        
        echo json_encode($history);
        exit;
    }

    // ROUTE: GET /api/analysis/stream
    if ($method === 'GET' && $path === '/api/analysis/stream') {
        $userId = $authenticate($_GET['token'] ?? null);

        if (function_exists('set_time_limit')) set_time_limit(0);
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

        $redis = new RedisClient(['scheme' => 'tcp', 'host' => 'redis', 'port' => 6379, 'read_write_timeout' => 0]);
        $pubsub = $redis->pubSubLoop();
        $pubsub->subscribe('analysis_events');
        
        foreach ($pubsub as $message) {
            if ($message->kind === 'message') {
                echo "data: " . $message->payload . "\n\n";
                flush();
            }
        }
        exit;
    }

    echo json_encode(["status" => "online", "message" => "TradeBench API is running"]);

} catch (\Throwable $e) {
    // Відловлюємо БУДЬ-ЯКУ помилку (навіть синтаксичну) і повертаємо як JSON
    http_response_code(500);
    echo json_encode([
        "error" => "Internal Server Error", 
        "details" => $e->getMessage()
    ]);
}