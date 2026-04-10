<?php
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

    if ($method === 'POST' && $path === '/api/auth/register') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        /* Sanitize inputs */
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        /* Basic emptiness check */
        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(["error" => "Email and password are required"]);
            exit;
        }

        /* Strict Email Validation */
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid email format"]);
            exit;
        }

        /* Password Strength Validation */
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(["error" => "Password must be at least 8 characters long"]);
            exit;
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            http_response_code(400);
            echo json_encode(["error" => "Password must contain at least one letter and one number"]);
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

    if ($method === 'POST' && $path === '/api/auth/login') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(["error" => "Email and password are required"]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $payload = [
                'iss' => 'tradebench_api',
                'sub' => $user['id'],
                'role' => $user['role'] ?? 'standard',
                'iat' => time(),
                'exp' => time() + (86400 * 7)
            ];
            echo json_encode([
                "token" => JWT::encode($payload, $jwtSecret, 'HS256'), 
                "user_id" => $user['id'],
                "role" => $user['role'] ?? 'standard'
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Invalid email or password"]);
        }
        exit;
    }

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
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
            return [
                'id' => $decoded->sub,
                'role' => $decoded->role ?? 'standard'
            ];
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(["error" => "Invalid or expired token"]);
            exit;
        }
    };

    // ROUTE: POST /api/analysis/start
    if ($method === 'POST' && $path === '/api/analysis/start') {
        $authData = $authenticate();
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
        
        if ($userRole !== 'pro') {
            if ($daysRequested > 30) {
                http_response_code(403);
                echo json_encode(["error" => "Standard accounts are limited to 30 days of backtesting. Upgrade to PRO to test larger datasets."]);
                exit;
            }
            if ($timeframe !== '1h') {
                http_response_code(403);
                echo json_encode(["error" => "Custom timeframes (like $timeframe) are available only for PRO accounts."]);
                exit;
            }
        }
        
        try {
            $startMs = $startTimestamp * 1000;
            $endMs = $endTimestamp * 1000;
            
            $url = "https://api.binance.com/api/v3/klines?symbol={$pair}&interval={$timeframe}&startTime={$startMs}&endTime={$endMs}&limit=1000";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response !== false && $httpCode === 200) {
                $data = json_decode($response, true);
                
                if (is_array($data) && count($data) > 0) {
                    $pdo->beginTransaction();
                    
                    $stmtSync = $pdo->prepare("
                        INSERT INTO currency_data (pair_name, tick_time, open_price, high_price, low_price, close_price, volume)
                        VALUES (?, TO_TIMESTAMP(?), ?, ?, ?, ?, ?)
                        ON CONFLICT (pair_name, tick_time) DO NOTHING
                    ");

                    foreach ($data as $candle) {
                        $stmtSync->execute([
                            $pair, 
                            $candle[0] / 1000, 
                            $candle[1], 
                            $candle[2], 
                            $candle[3], 
                            $candle[4], 
                            $candle[5]
                        ]);
                    }
                    $pdo->commit();
                }
            }
        } catch (\Throwable $syncError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
        
        /* Validate strategy parameters */
        $strategy = $input['strategy'] ?? 'SMA_CROSS';
        $fast_sma = intval($input['fast_sma'] ?? 9);
        $slow_sma = intval($input['slow_sma'] ?? 21);
        
        $strategyPayload = sprintf("%s:%d:%d", $strategy, $fast_sma, $slow_sma);

        $taskId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        $stmt = $pdo->prepare("INSERT INTO analysis_tasks (id, user_id, pair, status) VALUES (?, ?, ?, 'PENDING')");
        $stmt->execute([$taskId, $userId, $pair]);

        $client = new AnalysisClient('cpp-engine:50051');
        $grpcResult = $client->requestAnalysis((string)$userId, $pair, $strategyPayload, $taskId, [
            'start' => $startTimestamp,
            'end' => $endTimestamp
        ]);

        if (!$grpcResult['success']) {
            http_response_code(500);
            echo json_encode(["error" => "gRPC Error", "details" => $grpcResult['error'] ?? '']);
            exit;
        }

        echo json_encode(["task_id" => $taskId, "status" => "PENDING", "message" => "Analysis started"]);
        exit;
    }

    // ROUTE: GET /api/analysis/history (PROTECTED)
    if ($method === 'GET' && $path === '/api/analysis/history') {
        $authData = $authenticate();
        $userId = $authData['id'];
        
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
        $authData = $authenticate($_GET['token'] ?? null);
        $userId = $authData['id'];

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
                
                if (connection_aborted()) {
                    break;
                }
                
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

    echo json_encode(["status" => "online", "message" => "TradeBench API is running"]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error", "details" => $e->getMessage()]);
}