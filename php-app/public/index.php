<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Router;
use App\Core\Response;
use App\Controllers\AuthController;
use App\Controllers\AnalysisController;
use App\Controllers\AdminController;

/* ---------------------------------------------------------
 * CORE SETTINGS & CORS
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
    /* Load Environment Variables */
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();

    /* Initialize MVC Router */
    $router = new Router();

    /* =========================================
     * REGISTER ROUTES
     * ========================================= */
    
    // Auth Module
    $router->add('POST', '/api/auth/register', [AuthController::class, 'register']);
    $router->add('POST', '/api/auth/login', [AuthController::class, 'login']);
    $router->add('POST', '/api/auth/refresh', [AuthController::class, 'refresh']);
    $router->add('POST', '/api/auth/logout', [AuthController::class, 'logout']);

    // Analysis Module
    $router->add('POST', '/api/analysis/start', [AnalysisController::class, 'start']);
    $router->add('GET', '/api/analysis/history', [AnalysisController::class, 'history']);
    $router->add('GET', '/api/analysis/stream', [AnalysisController::class, 'stream']);

    // Admin Module
    $router->add('GET', '/api/admin/users', [AdminController::class, 'getUsers']);
    $router->add('DELETE', '/api/admin/users/{id}', [AdminController::class, 'deleteUser']);
    $router->add('GET', '/api/admin/stats', [AdminController::class, 'getStats']);

    /* =========================================
     * DISPATCH REQUEST
     * ========================================= */
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    
    $router->dispatch($method, $uri);

} catch (\Throwable $e) {
    /* Global Error Handler */
    Response::error("Internal Server Error", 500, $e->getMessage());
}