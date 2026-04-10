<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use Firebase\JWT\JWT;
use PDOException;

/**
 * Handles Authentication logic (Login, Register, Refresh, Logout)
 */
class AuthController {
    
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function register(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::error("Email and password are required", 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error("Invalid email format", 422);
        }

        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            Response::error("Password must be at least 8 characters long and contain both letters and numbers", 422);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            /* First user becomes admin, others are standard */
            $roleCheck = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $role = ($roleCheck == 0) ? 'admin' : 'standard';

            $stmt = $this->db->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?) RETURNING id");
            $stmt->execute([$email, $hash, $role]);
            
            Response::json(["message" => "User registered successfully", "user_id" => $stmt->fetchColumn()], 201);
        } catch (PDOException $e) {
            Response::error("Email already exists", 409);
        }
    }

    public function login(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::error("Email and password are required", 400);
        }

        $stmt = $this->db->prepare("SELECT id, password_hash, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            
            $secret = $_ENV['JWT_SECRET'];
            
            /* Access Token (15 min) */
            $accessPayload = [
                'iss' => 'tradebench_api',
                'sub' => $user['id'],
                'role' => $user['role'] ?? 'standard',
                'iat' => time(),
                'exp' => time() + (60 * 15) 
            ];
            $accessToken = JWT::encode($accessPayload, $secret, 'HS256');

            /* Refresh Token (7 days) */
            $refreshToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 7));

            $stmt = $this->db->prepare("INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $refreshToken, $expiresAt]);

            setcookie('refresh_token', $refreshToken, [
                'expires' => time() + (86400 * 7),
                'path' => '/',
                'secure' => false, // Set true in production
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            Response::json([
                "token" => $accessToken, 
                "user_id" => $user['id'],
                "role" => $user['role'] ?? 'standard'
            ]);
        } else {
            Response::error("Invalid email or password", 401);
        }
    }

    public function refresh(): void {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;
        
        if (!$refreshToken) {
            Response::error("No refresh token provided", 401);
        }

        $stmt = $this->db->prepare("
            SELECT r.user_id, u.role, r.expires_at 
            FROM refresh_tokens r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.token = ?
        ");
        $stmt->execute([$refreshToken]);
        $tokenData = $stmt->fetch();

        if (!$tokenData || strtotime($tokenData['expires_at']) < time()) {
            setcookie('refresh_token', '', time() - 3600, '/');
            Response::error("Invalid or expired refresh token", 401);
        }

        $accessPayload = [
            'iss' => 'tradebench_api',
            'sub' => $tokenData['user_id'],
            'role' => $tokenData['role'],
            'iat' => time(),
            'exp' => time() + (60 * 15)
        ];
        $newAccessToken = JWT::encode($accessPayload, $_ENV['JWT_SECRET'], 'HS256');

        Response::json(["token" => $newAccessToken, "role" => $tokenData['role']]);
    }

    public function logout(): void {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;
        if ($refreshToken) {
            $stmt = $this->db->prepare("DELETE FROM refresh_tokens WHERE token = ?");
            $stmt->execute([$refreshToken]);
            setcookie('refresh_token', '', time() - 3600, '/');
        }
        Response::json(["message" => "Logged out successfully"]);
    }
}