<?php
/**
 * @brief Application Entry Point.
 * Initializes environment configuration, core middleware, and dispatches HTTP requests.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Router;
use App\Core\Response;
use App\Controllers\AuthController;
use App\Controllers\AnalysisController;
use App\Controllers\AdminController;
use App\Controllers\AiController;
use App\Controllers\SubscriptionController;

/* ---------------------------------------------------------
 * CORE SETTINGS & CORS CONFIGURATION
 * --------------------------------------------------------- */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/api/analysis/stream') {
    header('Content-Type: application/json');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

try {
    /* Initialize Environment Context */
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();

    /* =========================================
     * UI ROUTES (STATIC PAGES)
     * Direct HTML rendering block bypassing API router
     * ========================================= */
    $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($uriPath === '/' || $uriPath === '/landing') {
            header('Content-Type: text/html');
            require_once __DIR__ . '/landing.html';
            exit;
        }
        if ($uriPath === '/dashboard') {
            header('Content-Type: text/html');
            require_once __DIR__ . '/dashboard.html';
            exit;
        }
        if ($uriPath === '/admin') {
            header('Content-Type: text/html');
            require_once __DIR__ . '/admin.html';
            exit;
        }
    }

    /* Compile Dynamic MVC Router Context */
    $router = new Router();

    /* =========================================
     * APPLICATION API ROUTES
     * ========================================= */
    
    /* Security & Auth Module */
    $router->add('POST', '/api/auth/register', [AuthController::class, 'register']);
    $router->add('POST', '/api/auth/login', [AuthController::class, 'login']);
    $router->add('POST', '/api/auth/refresh', [AuthController::class, 'refresh']);
    $router->add('POST', '/api/auth/logout', [AuthController::class, 'logout']);

    /* Analytics Pipeline Module */
    $router->add('POST', '/api/analysis/start', [AnalysisController::class, 'start']);
    $router->add('GET', '/api/analysis/history', [AnalysisController::class, 'history']);
    $router->add('DELETE', '/api/analysis/history/{id}', [AnalysisController::class, 'deleteHistory']);
    $router->add('GET', '/api/analysis/stream', [AnalysisController::class, 'stream']);

    /* LLM Integration Module */
    $router->add('POST', '/api/ai/analyze-result', [AiController::class, 'analyzeResult']);

    /* Billing & Subscriptions Module */
    $router->add('POST', '/api/subscription/upgrade', [SubscriptionController::class, 'upgrade']);

    /* Administrative Module */
    $router->add('GET', '/api/admin/users', [AdminController::class, 'getUsers']);
    $router->add('PUT', '/api/admin/users/{id}', [AdminController::class, 'updateUser']);
    $router->add('DELETE', '/api/admin/users/{id}', [AdminController::class, 'deleteUser']);
    $router->add('GET', '/api/admin/stats', [AdminController::class, 'getStats']);

    /* =========================================
     * DISPATCH PROCESSOR
     * ========================================= */
    $method = $_SERVER['REQUEST_METHOD'];
    
    $router->dispatch($method, $uriPath);

} catch (\Throwable $e) {
    /* Global Catch-all Error Handling */
    http_response_code(500);
    echo json_encode([
        "error" => "System Error",
        "details" => $e->getMessage(),
        "hint" => "Check your PSR-4 namespaces and execution environment."
    ]);
    exit;
}