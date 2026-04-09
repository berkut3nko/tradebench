<?php

namespace App;

use Analyzer\AnalysisServiceClient;
use Analyzer\AnalysisRequest;
use Grpc\ChannelCredentials;

/**
 * @brief Client for interacting with the C++ Analytical Core.
 */
class AnalysisClient {
    private AnalysisServiceClient $client;

    /**
     * @param string $hostname The address of the C++ gRPC server.
     */
    public function __construct(string $hostname = 'cpp-engine:50051') {
        $this->client = new AnalysisServiceClient($hostname, [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
    }

    /**
     * @brief Sends a backtesting request to the core with dynamic parameters.
     * @param string $userId
     * @param string $pair
     * @param string $strategy
     * @param string $taskId
     * @param array $dateRange Associative array with 'start' and 'end' timestamps
     * @return array
     */
    public function requestAnalysis(string $userId, string $pair, string $strategy, string $taskId, array $dateRange = []): array {
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

        // Added a timeout of 5 seconds (5,000,000 microseconds) to prevent PHP from hanging
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