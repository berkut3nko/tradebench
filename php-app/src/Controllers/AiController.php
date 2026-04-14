<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\AuthMiddleware;
use App\Services\AiService;

/**
 * @brief Controller for handling AI insight generation logic and permission enforcement.
 */
class AiController {
    
    private \PDO $db;
    private AiService $aiService;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->aiService = new AiService();
    }

    /**
     * @brief Analyzes historical backtest results and queries Google Gemini for professional insights.
     */
    public function analyzeResult(): void {
        $authData = AuthMiddleware::authenticate();
        $userRole = $authData['role'];
        
        if (!in_array($userRole, ['pro', 'admin'])) {
            Response::error("This feature is restricted to PRO accounts.", 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $taskId = $input['task_id'] ?? '';
        $isOptimized = $input['is_optimized'] ?? false;

        if (empty($taskId)) {
            Response::error("Task ID missing from request.", 400);
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
            Response::error("Task not found or access denied.", 404);
        }

        $resultData = json_decode($task['result_data'], true);

        if (isset($resultData['ai_insight']) && !empty($resultData['ai_insight'])) {
            Response::json(["insight" => $resultData['ai_insight']]);
            return;
        }
        
        $pair = $task['pair'];
        $timeframe = $resultData['timeframe'] ?? 'Unknown';
        $strategy = $resultData['strategy'] ?? 'Unknown';
        $profit = number_format($resultData['profit'] ?? 0, 2);
        $winRate = number_format($resultData['win_rate'] ?? 0, 1);
        $drawdown = number_format($resultData['drawdown'] ?? 0, 1);
        $trades = $resultData['trades'] ?? 0;

        $timestamps = $resultData['timestamps'] ?? [];
        $periodString = "Unknown";
        if (!empty($timestamps)) {
            $startTs = $timestamps[0];
            $endTs = $timestamps[count($timestamps) - 1];
            $periodString = date('d.m.Y', $startTs) . " - " . date('d.m.Y', $endTs);
        }

        // IMPORTANT: Advanced, deep and professional prompt formulation for quantitative analysis
        $prompt = "You are a Senior Quant Analyst. Your objective is to provide a deep expert assessment of a historical algorithm simulation. 
        Simulation data:
        - Asset: {$pair}, Period: {$periodString}, Timeframe: {$timeframe}
        - Strategy structure: {$strategy}
        - PnL: {$profit}$, Trades: {$trades}, Win Rate: {$winRate}%, Maximum Drawdown: {$drawdown}%
        
        Generate a professional, substantive conclusion (4-5 sentences) in Ukrainian. ";

        if ($isOptimized) {
            $prompt .= "
            Context: These parameters were discovered by an Evolutionary Genetic algorithm that iterated over thousands of combinations.
            Your task: 
            1. Explain that the algorithm adapted perfectly to the market cycle during this period.
            2. Evaluate the risk profile: does the acquired PnL justify the maximum drawdown ({$drawdown}%)?
            3. Make an assumption regarding the market conditions (volatility, trending vs ranging) that allowed this optimized strategy to thrive. Do not mention 'small sample size' or 'overfitting' since this is an intended result of deep search parameters.";
        } else {
            $prompt .= "
            Your task:
            1. Analyze market logic: considering the specifics of the strategy (e.g., SMA/MACD succeed in trends, while RSI/Bollinger succeed in ranging markets) and the final PnL, hypothesize the dominant market phase during the specified period. If the strategy lost money, explicitly state that the market phase contradicted the strategy archetype.
            2. Assess Risk Management: analyze the ratio of Drawdown ({$drawdown}%) to PnL. Is this system robust?
            3. Statistical Validity: If trades are fewer than 15-20, warn the trader that the sample is too small to draw conclusions about algorithmic reliability, indicating a high risk of variance.
            Your response must clarify market mechanics for the trader.";
        }

        $prompt .= " Avoid generic phrases. Write as a professional addressing another professional. Formatting requirement: plain text, strictly no Markdown (no * or # symbols).";

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