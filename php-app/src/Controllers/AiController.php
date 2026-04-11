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

        if (isset($resultData['ai_insight']) && !empty($resultData['ai_insight'])) {
            Response::json(["insight" => $resultData['ai_insight']]);
            return;
        }
        
        $pair = $task['pair'];
        $timeframe = $resultData['timeframe'] ?? 'Невідомо';
        $strategy = $resultData['strategy'] ?? 'Невідомо';
        $profit = number_format($resultData['profit'] ?? 0, 2);
        $winRate = number_format($resultData['win_rate'] ?? 0, 1);
        $drawdown = number_format($resultData['drawdown'] ?? 0, 1);
        $trades = $resultData['trades'] ?? 0;

        $timestamps = $resultData['timestamps'] ?? [];
        $periodString = "Невідомо";
        if (!empty($timestamps)) {
            $startTs = $timestamps[0];
            $endTs = $timestamps[count($timestamps) - 1];
            $periodString = date('d.m.Y', $startTs) . " - " . date('d.m.Y', $endTs);
        }

        // ВАЖЛИВО: Новий, глибокий та професійний промпт для кількісного аналізу
        $prompt = "Ти — Senior Quant Analyst. Твоя мета — дати глибоку експертну оцінку результатам історичного тестування алгоритму. 
        Дані симуляції:
        - Пара: {$pair}, Період: {$periodString}, Таймфрейм: {$timeframe}
        - Стратегія: {$strategy}
        - Прибуток: {$profit}$, Угод: {$trades}, Win Rate: {$winRate}%, Просідання (Drawdown): {$drawdown}%
        
        Зроби професійний, змістовний висновок (4-5 речень) українською мовою. ";

        if ($isOptimized) {
            $prompt .= "
            Контекст: Ці параметри були знайдені еволюційним Генетичним алгоритмом, який перебрав тисячі комбінацій.
            Твоє завдання: 
            1. Поясни, що алгоритм зміг ідеально адаптуватися під ринковий цикл вказаного періоду.
            2. Оціни профіль ризику: чи виправдовує отриманий прибуток таке максимальне просідання ({$drawdown}%)?
            3. Зроби припущення, які саме ринкові умови (висока/низька волатильність, тренд чи боковик) дозволили цій оптимізованій стратегії показати такий результат. Не пиши про 'малу вибірку' або 'overfit', оскільки це результат навмисного глибокого пошуку.";
        } else {
            $prompt .= "
            Твоє завдання:
            1. Проаналізуй логіку ринку: враховуючи специфіку стратегії (наприклад, SMA/MACD працюють у тренді, а RSI/Bollinger - у флеті/боковику) та фінальний прибуток, припусти, у якій фазі перебував ринок у вказаний період. Якщо стратегія дала мінус, прямо скажи, що ринкова фаза не відповідала типу стратегії (наприклад, 'ймовірно ринок був у боковику, що згенерувало хибні сигнали для MACD').
            2. Оціни ризик-менеджмент: проаналізуй співвідношення Drawdown ({$drawdown}%) до прибутку. Чи є ця система стабільною?
            3. Статистична валідність: Якщо угод менше 15-20, попередь трейдера, що вибірка занадто мала, щоб робити висновки про надійність алгоритму, і є високий ризик випадковості.
            Твоя відповідь має допомогти трейдеру зрозуміти механіку ринку.";
        }

        $prompt .= " Уникай загальних фраз. Пиши як професіонал для професіонала. Форматування: простий текст без Markdown (без символів * чи #).";

        try {
            $insight = $this->aiService->generateInsight($prompt);

            $resultData['ai_insight'] = $insight;
            $updateStmt = $this->db->prepare("UPDATE analysis_results SET result_data = ? WHERE task_id = ?");
            $updateStmt->execute([json_encode($resultData), $taskId]);

            Response::json(["insight" => $insight]);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}