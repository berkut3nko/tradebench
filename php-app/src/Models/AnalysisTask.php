<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Model representing the analysis_tasks and analysis_results tables
 */
class AnalysisTask {

    /**
     * Create a new task in PENDING state
     */
    public static function create(string $taskId, int $userId, string $pair): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO analysis_tasks (id, user_id, pair, status) VALUES (?, ?, ?, 'PENDING')");
        return $stmt->execute([$taskId, $userId, $pair]);
    }

    /**
     * Fetch recent task history for a user, including results
     */
    public static function getHistoryByUser(int $userId, int $limit = 10): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT t.id as task_id, t.pair, t.status, t.created_at, r.result_data
            FROM analysis_tasks t
            LEFT JOIN analysis_results r ON t.id = r.task_id
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        /* Explicitly bind parameters to handle limit type */
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}