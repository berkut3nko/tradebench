<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\AnalysisClient;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/* JWT Secret Key (in production, use .env) */
$jwtSecret = getenv('JWT_SECRET') ?: 'vortex_super_secret_key_2026';

try {
    $pdo = new PDO('pgsql:host=db;port=5432;dbname=analyzer_db', 'user', 'pass', [
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
    $authenticate = function() use ($jwtSecret) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(["error" => "Token not provided"]);
            exit;
        }

        $token = $matches[1];
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
        // Pass actual user_id to C++
        $grpcResult = $client->requestAnalysis((string)$userId, $pair, $strategy, $taskId);

        if (!$grpcResult['success']) {
            http_response_code(500);
            echo json_encode(["error" => "C++ Core unreachable", "details" => $grpcResult['error']]);
            exit;
        }

        echo json_encode(["task_id" => $taskId, "status" => "PENDING", "message" => "Analysis started"]);
        exit;
    }

    // ROUTE: GET /api/analysis/status/{taskId} (PROTECTED)
    if ($method === 'GET' && preg_match('#^/api/analysis/status/([a-f0-9\-]+)$#', $path, $matches)) {
        $userId = $authenticate();
        $taskId = $matches[1];
        
        // Ensure user can only see their own tasks
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

    // Default fallback
    echo json_encode(["status" => "online", "message" => "TradeBench API is running"]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error", "details" => $e->getMessage()]);
}