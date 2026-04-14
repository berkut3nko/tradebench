<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Response;
use Exception;

class ResponseTest extends TestCase
{
    /**
     * @brief Tests if the Response::error method properly throws an Exception in CLI mode.
     * This ensures that our test runner doesn't exit prematurely when encountering an API error.
     */
    public function testErrorMethodThrowsExceptionInCliEnvironment(): void
    {
        $errorMessage = "Simulated validation error";
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($errorMessage);
        
        Response::error($errorMessage, 422);
    }
}