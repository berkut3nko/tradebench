<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\AuthMiddleware;
use Firebase\JWT\JWT;

class SubscriptionController {
    
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function upgrade(): void {
        $authData = AuthMiddleware::authenticate();
        $userId = $authData['id'];

        $input = json_decode(file_get_contents('php://input'), true);
        $months = (int)($input['months'] ?? 1);

        if (!in_array($months, [1, 6, 12])) {
            Response::error("Недійсний період підписки.", 400);
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT role, pro_expires_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                Response::error("Користувача не знайдено.", 404);
            }
            
            $updateQuery = "
                UPDATE users 
                SET role = CASE WHEN role = 'admin' THEN 'admin' ELSE 'pro' END, 
                    pro_expires_at = CASE 
                        WHEN pro_expires_at > NOW() THEN pro_expires_at + INTERVAL '$months months'
                        ELSE NOW() + INTERVAL '$months months'
                    END
                WHERE id = ?
                RETURNING role, pro_expires_at
            ";
            
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute([$userId]);
            $result = $updateStmt->fetch(\PDO::FETCH_ASSOC);
            
            $newRole = $result['role'];
            $newExpiration = $result['pro_expires_at'];

            $this->db->commit();

            // Генеруємо токен з правильною роллю (admin або pro)
            $secret = $_ENV['JWT_SECRET'] ?? 'vortex_super_secret_key_2026';
            $accessPayload = [
                'iss' => 'tradebench_api',
                'sub' => $userId,
                'role' => $newRole,
                'iat' => time(),
                'exp' => time() + (60 * 60 * 24) 
            ];
            $newAccessToken = JWT::encode($accessPayload, $secret, 'HS256');

            $formattedDate = date('d.m.Y', strtotime($newExpiration));

            Response::json([
                "message" => "Підписку успішно оформлено до $formattedDate!",
                "token" => $newAccessToken,
                "role" => $newRole,
                "expires_at" => $formattedDate
            ]);

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Response::error("Помилка оформлення підписки: " . $e->getMessage(), 500);
        }
    }
}