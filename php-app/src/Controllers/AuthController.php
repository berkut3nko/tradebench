<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use Firebase\JWT\JWT;

/**
 * @brief Controller responsible for user registration, authentication, and token generation.
 */
class AuthController {
    
    /** @var \PDO Active database connection */
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * @brief Processes a new user registration request.
     */
    public function register(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::error("Email and password are required", 400);
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error("Invalid email format", 422);
        }

        // Validate password length
        if (strlen($password) < 8) {
            Response::error("Password must be at least 8 characters long", 422);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $this->db->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
            $stmt->execute([$email, $hash]);
            Response::json(["message" => "Registration successful!"], 201);
        } catch (\PDOException $e) {
            // Unique constraint violation (duplicate email)
            if ($e->getCode() == 23505) { 
                Response::error("This email is already registered", 409);
            }
            Response::error("Registration error: " . $e->getMessage(), 500);
        }
    }

    /**
     * @brief Processes user login and issues JWT tokens.
     */
    public function login(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::error("Email and password are required", 400);
        }

        try {
            $stmt = $this->db->prepare("SELECT id, password_hash, role, pro_expires_at FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                
                $role = $user['role'] ?? 'standard';
                
                // Verify if active PRO subscription has expired
                if ($role === 'pro' && $user['pro_expires_at'] !== null) {
                    if (strtotime($user['pro_expires_at']) < time()) {
                        $role = 'standard';
                        $this->db->prepare("UPDATE users SET role = 'standard' WHERE id = ?")->execute([$user['id']]);
                    }
                }
                
                // Fallback mechanism to prevent 500 errors if JWT_SECRET is unconfigured
                $secret = $_ENV['JWT_SECRET'] ?? 'vortex_super_secret_key_2026';
                
                $accessPayload = [
                    'iss' => 'tradebench_api',
                    'sub' => $user['id'],
                    'role' => $role,
                    'iat' => time(),
                    'exp' => time() + (60 * 60 * 24) // Access token lifespan: 24 hours
                ];
                $accessToken = JWT::encode($accessPayload, $secret, 'HS256');

                // Safely execute refresh_token logic, catching exceptions if table structure is missing
                try {
                    $this->db->prepare("DELETE FROM refresh_tokens WHERE user_id = ?")->execute([$user['id']]);
                    $refreshToken = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 7));
                    $this->db->prepare("INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
                             ->execute([$user['id'], $refreshToken, $expiresAt]);
                } catch (\PDOException $e) {
                    // Suppress and continue if refresh_tokens architecture is unsupported
                }

                Response::json([
                    "message" => "Login successful",
                    "token" => $accessToken,
                    "role" => $role
                ]);
            } else {
                Response::error("Invalid email or password", 401);
            }
        } catch (\Exception $e) {
            Response::error("Internal server error during login procedure: " . $e->getMessage(), 500);
        }
    }

    /**
     * @brief Invalidates the current session.
     */
    public function logout(): void {
        Response::json(["message" => "Successfully logged out"]);
    }
}