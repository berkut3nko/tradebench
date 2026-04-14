<?php

namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

/**
 * Handles JWT verification and role checks
 */
class AuthMiddleware {
    
    /**
     * Authenticate request using Bearer token or Query param
     * * @param string|null $tokenFromQuery
     * @param string|null $requiredRole
     * @return array User data ['id', 'role']
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
            // Тепер цей блок перехоплює лише помилки декодування JWT
            Response::error("Unauthorized: Invalid token", 401);
        }

        // ВАЖЛИВО: Перевірка ролей винесена за межі try-catch
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