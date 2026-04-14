<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\AnalysisClient;

/**
 * @brief Unit test suite for the gRPC Analysis Client interacting with the C++ core.
 */
class AnalysisClientTest extends TestCase
{
    /**
     * @brief Tests if the AnalysisClient handles unreachable gRPC servers correctly.
     * @return void
     */
    public function testRequestAnalysisReturnsFalseOnConnectionFailure(): void
    {
        // Point to an invalid or unassigned port to force a gRPC connection failure
        $client = new AnalysisClient('localhost:99999');

        $result = $client->requestAnalysis(
            'user_123',
            'BTCUSDT',
            'SMA_CROSS:9:21',
            'dummy-task-id-001'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success'], "The request should fail since the gRPC server is unreachable on this port.");
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * @brief Tests successful instantiation of the gRPC client wrapper.
     * @return void
     */
    public function testAnalysisClientInstantiatesSuccessfully(): void
    {
        $client = new AnalysisClient('localhost:50051');
        $this->assertInstanceOf(AnalysisClient::class, $client);
    }
}