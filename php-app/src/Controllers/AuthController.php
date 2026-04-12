<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use Firebase\JWT\JWT;

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
            Response::error("Email та пароль є обов'язковими", 400);
        }

        // ВАЛІДАЦІЯ ПОШТИ
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error("Невірний формат Email адреси", 422);
        }

        // ВАЛІДАЦІЯ ПАРОЛЯ
        if (strlen($password) < 8) {
            Response::error("Пароль має містити щонайменше 8 символів", 422);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $this->db->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
            $stmt->execute([$email, $hash]);
            Response::json(["message" => "Успішна реєстрація!"], 201);
        } catch (\PDOException $e) {
            if ($e->getCode() == 23505) { // Помилка унікальності
                Response::error("Цей Email вже зареєстровано", 409);
            }
            Response::error("Помилка реєстрації: " . $e->getMessage(), 500);
        }
    }

    public function login(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::error("Email та пароль є обов'язковими", 400);
        }

        try {
            $stmt = $this->db->prepare("SELECT id, password_hash, role, pro_expires_at FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                
                $role = $user['role'] ?? 'standard';
                
                // ПЕРЕВІРКА НА ЗАКІНЧЕННЯ ПІДПИСКИ
                if ($role === 'pro' && $user['pro_expires_at'] !== null) {
                    if (strtotime($user['pro_expires_at']) < time()) {
                        $role = 'standard';
                        $this->db->prepare("UPDATE users SET role = 'standard' WHERE id = ?")->execute([$user['id']]);
                    }
                }
                
                // Запасний варіант, якщо JWT_SECRET не задано у .env (запобігає 500 Fatal Error)
                $secret = $_ENV['JWT_SECRET'] ?? 'vortex_super_secret_key_2026';
                
                $accessPayload = [
                    'iss' => 'tradebench_api',
                    'sub' => $user['id'],
                    'role' => $role,
                    'iat' => time(),
                    'exp' => time() + (60 * 60 * 24) // Токен на 24 години
                ];
                $accessToken = JWT::encode($accessPayload, $secret, 'HS256');

                // Безпечний запис refresh_token (відловлюємо помилку, якщо таблиця відсутня)
                try {
                    $this->db->prepare("DELETE FROM refresh_tokens WHERE user_id = ?")->execute([$user['id']]);
                    $refreshToken = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 7));
                    $this->db->prepare("INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
                             ->execute([$user['id'], $refreshToken, $expiresAt]);
                } catch (\PDOException $e) {
                    // Якщо таблиці refresh_tokens немає, ми ігноруємо помилку і просто продовжуємо вхід
                }

                Response::json([
                    "message" => "Успішний вхід",
                    "token" => $accessToken,
                    "role" => $role
                ]);
            } else {
                Response::error("Неправильний email або пароль", 401);
            }
        } catch (\Exception $e) {
            // Тепер будь-яка критична помилка буде повертатися у консоль, а не "мовчати"
            Response::error("Внутрішня помилка сервера під час входу: " . $e->getMessage(), 500);
        }
    }

    public function logout(): void {
        Response::json(["message" => "Ви успішно вийшли з системи"]);
    }
}