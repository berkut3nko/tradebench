<?php

namespace App;

use Analyzer\AnalysisServiceClient;
use Analyzer\AnalysisRequest;
use Grpc\ChannelCredentials;

/**
 * @brief Client for interacting with the C++ Analytical Core via gRPC.
 */
class AnalysisClient {
    /** @var AnalysisServiceClient The underlying gRPC client instance. */
    private AnalysisServiceClient $client;

    /**
     * @brief Constructs the AnalysisClient and establishes an insecure channel.
     * @param string $hostname The address of the C++ gRPC server.
     */
    public function __construct(string $hostname = 'cpp-engine:50051') {
        $this->client = new AnalysisServiceClient($hostname, [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
    }

    /**
     * @brief Sends an asynchronous backtesting request to the C++ core.
     * @param string $userId The ID of the user requesting the analysis.
     * @param string $pair The trading pair (e.g., BTCUSDT).
     * @param string $strategy The strategy configuration payload.
     * @param string $taskId Unique identifier for the analysis task.
     * @param array $dateRange Associative array with 'start' and 'end' UNIX timestamps.
     * @param string $timeframe The trading timeframe (e.g., '1h', '15m').
     * @return array Associative array containing the success status and message/error.
     */
    public function requestAnalysis(string $userId, string $pair, string $strategy, string $taskId, array $dateRange = [], string $timeframe = '1h'): array {
        $request = new AnalysisRequest();
        $request->setUserId($userId);
        $request->setCurrencyPair($pair);
        $request->setStrategyName($strategy);
        
        // Use provided timestamps or default to the last 30 days
        $start = $dateRange['start'] ?? (time() - (86400 * 30));
        $end = $dateRange['end'] ?? time();
        
        $request->setStartTimestamp($start);
        $request->setEndTimestamp($end);
        $request->setTaskId($taskId);
        $request->setTimeframe($timeframe);

        // Implemented a timeout of 5 seconds (5,000,000 microseconds) to prevent PHP worker thread exhaustion
        list($response, $status) = $this->client->StartAnalysis($request, [], ['timeout' => 5000000])->wait();

        if ($status->code !== 0) {
            return [
                'success' => false,
                'error' => $status->details,
                'code' => $status->code
            ];
        }

        return [
            'success' => true,
            'task_id' => $response->getTaskId(),
            'message' => $response->getMessage()
        ];
    }
}