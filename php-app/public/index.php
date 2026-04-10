<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\AnalysisClient;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;
use Predis\Client as RedisClient;

header('Access-Control-Allow-Origin: *');
// We don't set JSON header globally anymore, because SSE needs text/event-stream
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/api/analysis/stream') {
    header('Content-Type: application/json');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/* Load environment variables from .env file */
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

/* Retrieve secret key from environment */
$jwtSecret = $_ENV['JWT_SECRET'];

try {
    /* Connect to database using environment variables */
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_DATABASE']);
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    /* =========================================
       AUTH ROUTES
       ========================================= */

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
            $userId = $stmt->fetchColumn();

            echo json_encode(["message" => "User registered successfully", "user_id" => $userId]);
        } catch (\PDOException $e) {
            http_response_code(409); // Conflict
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
                'exp' => time() + (86400 * 7) // 7 days expiration
            ];

            $jwt = JWT::encode($payload, $jwtSecret, 'HS256');
            echo json_encode(["token" => $jwt, "user_id" => $user['id']]);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Invalid email or password"]);
        }
        exit;
    }

    /* =========================================
       PROTECTED ROUTES
       ========================================= */

    /* Helper function to verify JWT */
    $authenticate = function($tokenFromQuery = null) use ($jwtSecret) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = null;

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        } elseif ($tokenFromQuery) {
            $token = $tokenFromQuery; // Support token via GET for SSE
        }
        
        if (!$token) {
            http_response_code(401);
            echo json_encode(["error" => "Token not provided"]);
            exit;
        }

        try {
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
            return $decoded->sub; // Return user_id
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(["error" => "Invalid or expired token"]);
            exit;
        }
    };

    // ROUTE: POST /api/analysis/start (PROTECTED)
    if ($method === 'POST' && $path === '/api/analysis/start') {
        $userId = $authenticate(); // Require auth!
        
        $input = json_decode(file_get_contents('php://input'), true);
        $pair = $input['pair'] ?? 'EURUSD';
        $strategy = $input['strategy'] ?? 'SMA_CROSS';

        $taskId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        $stmt = $pdo->prepare("INSERT INTO analysis_tasks (id, user_id, pair, status) VALUES (?, ?, ?, 'PENDING')");
        $stmt->execute([$taskId, $userId, $pair]);

        $client = new AnalysisClient('cpp-engine:50051');
        $grpcResult = $client->requestAnalysis((string)$userId, $pair, $strategy, $taskId);

        if (!$grpcResult['success']) {
            http_response_code(500);
            echo json_encode([
                "error" => "gRPC Error (Code " . ($grpcResult['code'] ?? 'Unknown') . "): " . ($grpcResult['error'] ?? 'No details'),
                "details" => $grpcResult['error'] ?? ''
            ]);
            exit;
        }

        echo json_encode(["task_id" => $taskId, "status" => "PENDING", "message" => "Analysis started"]);
        exit;
    }

    // ROUTE: GET /api/analysis/status/{taskId} (PROTECTED) - Kept for fallback/initial check
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

    /* =========================================
       REAL-TIME SSE STREAM ROUTE
       ========================================= */
    
    // ROUTE: GET /api/analysis/stream
    if ($method === 'GET' && $path === '/api/analysis/stream') {
        // SSE can't easily send custom headers, so we pass token in URL
        $token = $_GET['token'] ?? null;
        $userId = $authenticate($token);

        // Turn off output buffering and set required headers for SSE
        if (function_exists('set_time_limit')) set_time_limit(0); // Prevent PHP from timing out
        while (ob_get_level() > 0) ob_end_flush();
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // MAGIC FIX: Tells Nginx NOT to buffer this connection!

        // Connect to Redis
        $redis = new RedisClient([
            'scheme' => 'tcp',
            'host'   => 'redis',
            'port'   => 6379,
            'read_write_timeout' => 0 // Disable timeout for Pub/Sub
        ]);

        // Subscribe to the channel that C++ publishes to
        $pubsub = $redis->pubSubLoop();
        $pubsub->subscribe('analysis_events');
        
        // Send an initial ping so the browser immediately knows the connection is alive
        echo "event: ping\ndata: connected\n\n";
        @ob_flush();
        flush();

        // Keep connection open and wait for messages
        foreach ($pubsub as $message) {
            if ($message->kind === 'message') {
                // Instantly forward the C++ event to the browser
                echo "data: " . $message->payload . "\n\n";
                @ob_flush();
                flush();
            }
        }
        exit;
    }

    // Default fallback
    echo json_encode(["status" => "online", "message" => "TradeBench API is running"]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error", "details" => $e->getMessage()]);
}