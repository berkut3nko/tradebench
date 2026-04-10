<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\AuthMiddleware;

/**
 * Handles Administration Panel routes
 */
class AdminController {
    
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
        /* Ensure only admins can initialize this controller */
        AuthMiddleware::authenticate(null, 'admin');
    }

    public function getUsers(): void {
        $stmt = $this->db->query("SELECT id, email, role, created_at FROM users ORDER BY id DESC");
        Response::json($stmt->fetchAll());
    }

    public function deleteUser(string $id): void {
        $authData = AuthMiddleware::authenticate(null, 'admin');
        
        if ($id == $authData['id']) {
            Response::error("Cannot delete yourself", 400);
        }
        
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        Response::json(["message" => "User deleted successfully"]);
    }

    public function getStats(): void {
        $usersCount = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $tasksCount = $this->db->query("SELECT COUNT(*) FROM analysis_tasks")->fetchColumn();
        $dataPoints = $this->db->query("SELECT COUNT(*) FROM currency_data")->fetchColumn();
        
        Response::json([
            "total_users" => $usersCount,
            "total_analyses_run" => $tasksCount,
            "market_data_points" => $dataPoints
        ]);
    }
}