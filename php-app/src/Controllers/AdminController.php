<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\AuthMiddleware;

/**
 * @brief Controller managing administrative routes and data moderation tools.
 */
class AdminController {
    
    private \PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
        AuthMiddleware::authenticate(null, 'admin');
    }

    /**
     * @brief Retrieves the entire user catalog securely.
     */
    public function getUsers(): void {
        $stmt = $this->db->query("SELECT id, email, role, created_at FROM users ORDER BY id DESC");
        Response::json($stmt->fetchAll());
    }

    /**
     * @brief Facilitates cascade deletion of a user record and its associated history.
     * @param string $id Target User ID parameter.
     */
    public function deleteUser(string $id): void {
        $authData = AuthMiddleware::authenticate(null, 'admin');
        
        if ($id == $authData['id']) {
            Response::error("Cannot delete yourself", 400);
        }
        
        try {
            $this->db->beginTransaction();
            
            // 1. Delete all authorization tokens of the user
            $this->db->prepare("DELETE FROM refresh_tokens WHERE user_id = ?")->execute([$id]);

            // 2. Find all tasks (backtests) of the user
            $tasksStmt = $this->db->prepare("SELECT id FROM analysis_tasks WHERE user_id = ?");
            $tasksStmt->execute([$id]);
            $tasks = $tasksStmt->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($tasks)) {
                $placeholders = implode(',', array_fill(0, count($tasks), '?'));
                // 3. Delete analysis results linked to tasks
                $this->db->prepare("DELETE FROM analysis_results WHERE task_id IN ($placeholders)")->execute($tasks);
            }

            // 4. Delete the analysis tasks proper
            $this->db->prepare("DELETE FROM analysis_tasks WHERE user_id = ?")->execute([$id]);

            // 5. Finally, delete the user record
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

            $this->db->commit();
            Response::json(["message" => "User and all associated data deleted successfully"]);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Response::error("Failed to delete user", 500, $e->getMessage());
        }
    }

    /**
     * @brief Updates internal role permissions associated with target User entity.
     * @param string $id Database ID for the target user.
     */
    public function updateUser(string $id): void {
        $authData = AuthMiddleware::authenticate(null, 'admin');
        
        if ($id == $authData['id']) {
            Response::error("Cannot edit your own role", 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $newRole = $input['role'] ?? null;
        
        if (!in_array($newRole, ['standard', 'pro', 'admin'])) {
            Response::error("Invalid role provided", 400);
        }
        
        $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $id]);
        
        Response::json(["message" => "User role updated to " . strtoupper($newRole)]);
    }

    /**
     * @brief Computes generalized statistical counters for dashboard telemetry.
     */
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