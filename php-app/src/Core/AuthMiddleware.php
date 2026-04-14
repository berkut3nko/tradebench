<?php

namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

/**
 * @brief Handles JWT verification and Role-Based Access Control (RBAC).
 */
class AuthMiddleware {
    
    /**
     * @brief Authenticates a request using a Bearer token or Query parameter.
     * @param string|null $tokenFromQuery Optional token provided via URL parameters (for SSE).
     * @param string|null $requiredRole The minimum role required to proceed (e.g., 'admin', 'pro').
     * @return array Associative array containing the authenticated user's ID and role.
     */
    public static function authenticate(?string $tokenFromQuery = null, ?string $requiredRole = null): array {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = null;

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        } elseif ($tokenFromQuery) {
            $token = $tokenFromQuery;
        }
        
        if (!$token) {
            Response::error("Unauthorized: Token not provided", 401);
        }

        try {
            $secret = $_ENV['JWT_SECRET'];
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (ExpiredException $e) {
            Response::error("Unauthorized: Token expired", 401, "TOKEN_EXPIRED");
        } catch (\Exception $e) {
            // This catch block isolates token decoding failures
            Response::error("Unauthorized: Invalid token", 401);
        }

        // IMPORTANT: Role evaluation is moved outside the try-catch block 
        // to prevent overriding access-denied exceptions
        $role = $decoded->role ?? 'standard';
        
        if ($requiredRole && $role !== $requiredRole && $role !== 'admin') {
            Response::error("Forbidden: Insufficient permissions", 403);
        }

        return [
            'id' => $decoded->sub,
            'role' => $role
        ];
    }
}