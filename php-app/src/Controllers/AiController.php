<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\AuthMiddleware;
use App\Services\AiService;

/**
 * Controller for handling AI insights generation
 */
class AiController {
    
    private \PDO $db;
    private AiService $aiService;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->aiService = new AiService();
    }

    /**
     * Analyzes backtest results using Gemini AI
     */
    public function analyzeResult(): void {
        $authData = AuthMiddleware::authenticate();
        $userRole = $authData['role'];
        
        if (!in_array($userRole, ['pro', 'admin'])) {
            Response::error("Ця функція доступна лише для користувачів з підпискою PRO.", 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $taskId = $input['task_id'] ?? '';
        $isOptimized = $input['is_optimized'] ?? false;

        if (empty($taskId)) {
            Response::error("Task ID не вказано.", 400);
        }

        $stmt = $this->db->prepare("
            SELECT t.pair, r.result_data 
            FROM analysis_tasks t
            JOIN analysis_results r ON t.id = r.task_id
            WHERE t.id = ? AND t.user_id = ?
        ");
        $stmt->execute([$taskId, $authData['id']]);
        $task = $stmt->fetch();

        if (!$task) {
            Response::error("Завдання не знайдено або у вас немає доступу.", 404);
        }

        $resultData = json_decode($task['result_data'], true);
        
        $pair = $task['pair'];
        $timeframe = $resultData['timeframe'] ?? 'Невідомо';
        $strategy = $resultData['strategy'] ?? 'Невідомо';
        $profit = number_format($resultData['profit'] ?? 0, 2);
        $winRate = number_format($resultData['win_rate'] ?? 0, 1);
        $drawdown = number_format($resultData['drawdown'] ?? 0, 1);
        $trades = $resultData['trades'] ?? 0;

        /* Extract start and end dates from timestamps array */
        $timestamps = $resultData['timestamps'] ?? [];
        $periodString = "Невідомо";
        if (!empty($timestamps)) {
            $startTs = $timestamps[0];
            $endTs = $timestamps[count($timestamps) - 1];
            $periodString = date('d.m.Y', $startTs) . " - " . date('d.m.Y', $endTs);
        }

        /* Build the base system prompt */
        $prompt = "Ти професійний фінансовий квант-аналітик. Твоє завдання - проаналізувати результати бектестингу торгового алгоритму.
        Ось дані:
        - Валютна пара: {$pair}
        - Період тестування: {$periodString}
        - Таймфрейм: {$timeframe}
        - Використана стратегія: {$strategy}
        - Чистий прибуток: {$profit}$
        - Кількість угод: {$trades}
        - Відсоток успішних угод (Win Rate): {$winRate}%
        - Максимальне просідання (Drawdown): {$drawdown}%
        
        Зроби короткий висновок (3-4 речення) українською мовою. ";

        /* Dynamic instructions based on execution context */
        if ($isOptimized) {
            $prompt .= "ВАЖЛИВО: Параметри цієї стратегії були знайдені за допомогою потужного Генетичного алгоритму на C++, який еволюційним методом перебрав тисячі комбінацій для пошуку глобального оптимуму. Тому КАТЕГОРИЧНО ЗАБОРОНЕНО писати, що це 'overfit', 'перенавчання' або що вибірка 'статистично ненадійна' через малу кількість угод. Навпаки, поясни користувачу, що ці нестандартні параметри є результатом глибокого машинного пошуку і вони найкраще адаптовані під ринковий цикл вказаного періоду ({$periodString}). Оціни співвідношення ризику до прибутку.";
        } else {
            $prompt .= "Враховуючи вказаний період ({$periodString}), дай чесну оцінку. Якщо кількість угод дуже мала (менше 15), обов'язково попередь користувача про високий ризик статистичної випадковості та можливий 'overfit'. Дай критичну оцінку: чи варто використовувати цю стратегію з такими параметрами, чи можливо ринок у цей період був специфічним (сильний тренд або флет), і стратегію варто змінити. Оціни співвідношення ризику (Drawdown) до прибутку.";
        }

        $prompt .= " Не використовуй складну Markdown розмітку (зірочки тощо), пиши простим текстом.";

        try {
            $insight = $this->aiService->generateInsight($prompt);
            Response::json(["insight" => $insight]);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}