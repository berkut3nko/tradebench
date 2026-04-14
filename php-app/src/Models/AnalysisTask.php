<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * @brief Data Access Object (DAO) for managing analysis_tasks and analysis_results tables.
 */
class AnalysisTask {

    /**
     * @brief Creates a new analysis task record in PENDING state.
     * @param string $taskId Unique UUID for the task.
     * @param int $userId The ID of the task owner.
     * @param string $pair The trading pair being analyzed.
     * @return bool True if insertion succeeds, false otherwise.
     */
    public static function create(string $taskId, int $userId, string $pair): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO analysis_tasks (id, user_id, pair, status) VALUES (?, ?, ?, 'PENDING')");
        return $stmt->execute([$taskId, $userId, $pair]);
    }

    /**
     * @brief Retrieves recent task history for a specific user, including merged result data.
     * @param int $userId The ID of the requesting user.
     * @param int $limit Maximum number of records to return.
     * @return array Array of associative arrays representing task records.
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
        
        // Explicitly bind parameters to maintain data type integrity for PostgreSQL
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}