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
     * @brief Sends a backtesting request to the core.
     * @param string $userId
     * @param string $pair
     * @param string $strategy
     * @return array
     */
    public function requestAnalysis(string $userId, string $pair, string $strategy): array {
        $request = new AnalysisRequest();
        $request->setUserId($userId);
        $request->setCurrencyPair($pair);
        $request->setStrategyName($strategy);
        $request->setStartTimestamp(time() - 86400);
        $request->setEndTimestamp(time());

        list($response, $status) = $this->client->StartAnalysis($request)->wait();

        if ($status->code !== 0) {
            return [
                'success' => false,
                'error' => $status->details
            ];
        }

        return [
            'success' => true,
            'task_id' => $response->getTaskId(),
            'message' => $response->getMessage()
        ];
    }
}