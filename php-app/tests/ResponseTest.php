<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Response;
use Exception;

/**
 * @brief Unit test suite for the HTTP Response formatter.
 */
class ResponseTest extends TestCase
{
    /**
     * @brief Tests if the Response::error method properly throws an Exception in CLI mode.
     * * @return void
     */
    public function testErrorMethodThrowsExceptionInCliEnvironment(): void
    {
        $errorMessage = "Simulated validation error";
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($errorMessage);
        
        Response::error($errorMessage, 422);
    }

    /**
     * @brief [NEW TEST] Tests if the Response::error method includes additional details in the JSON output.
     * * @return void
     */
    public function testErrorMethodIncludesDetailsInOutput(): void
    {
        $this->expectException(Exception::class);
        
        ob_start();
        try {
            Response::error("Test error", 400, "Validation failed");
        } catch (Exception $e) {
            $output = ob_get_clean();
            $data = json_decode($output, true);
            
            $this->assertIsArray($data);
            $this->assertArrayHasKey('error', $data);
            $this->assertEquals("Test error", $data['error']);
            $this->assertArrayHasKey('details', $data);
            $this->assertEquals("Validation failed", $data['details']);
            
            throw $e; 
        }
    }
}