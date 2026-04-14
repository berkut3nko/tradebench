<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\AiService;
use Exception;

/**
 * @brief Unit test suite for the AiService class.
 */
class AiServiceTest extends TestCase
{
    /**
     * @brief Tests if the AI service correctly handles a missing API key.
     * @throws Exception Expected to be thrown due to missing configuration.
     * @return void
     */
    public function testGenerateInsightThrowsExceptionWithoutApiKey(): void
    {
        $originalKey = $_ENV['GEMINI_API_KEY'] ?? null;
        unset($_ENV['GEMINI_API_KEY']);
        
        $service = new AiService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("AI API key is missing from server configuration.");
        
        try {
            $service->generateInsight("Analyze this data");
        } finally {
            if ($originalKey !== null) {
                $_ENV['GEMINI_API_KEY'] = $originalKey;
            }
        }
    }
}