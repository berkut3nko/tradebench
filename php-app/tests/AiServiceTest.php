<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\AiService;
use Exception;

class AiServiceTest extends TestCase
{
    /**
     * @brief Tests if the AI service correctly handles a missing API key.
     */
    public function testGenerateInsightThrowsExceptionWithoutApiKey(): void
    {
        // Temporarily remove the API key
        $originalKey = $_ENV['GEMINI_API_KEY'] ?? null;
        unset($_ENV['GEMINI_API_KEY']);
        
        $service = new AiService();

        // We expect the service to throw this specific exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("API ключ для ШІ не налаштовано на сервері.");
        
        try {
            $service->generateInsight("Analyze this data");
        } finally {
            // Restore key
            if ($originalKey !== null) {
                $_ENV['GEMINI_API_KEY'] = $originalKey;
            }
        }
    }
}