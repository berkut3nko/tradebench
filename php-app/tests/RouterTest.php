<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Router;
use Exception;

/**
 * @brief Dummy controller used strictly for testing the Router dispatch mechanism.
 */
class DummyController {
    /**
     * @brief Target action that throws an exception to prove it was executed.
     * * @param string $id Optional parameter extracted from the URL.
     * @throws Exception To verify the route was successfully reached.
     * @return void
     */
    public function testAction(string $id = ''): void {
        throw new Exception("Routed correctly. ID: " . $id);
    }

    /**
     * @brief Target action to test multiple parameters extraction.
     * * @param string $id The first extracted parameter.
     * @param string $postId The second extracted parameter.
     * @throws Exception To verify the parameters were passed correctly.
     * @return void
     */
    public function testMultipleParams(string $id, string $postId): void {
        throw new Exception("Params: $id, $postId");
    }
}

/**
 * @brief Unit test suite for the custom HTTP Router implementation.
 */
class RouterTest extends TestCase
{
    /**
     * @brief Tests if the router correctly matches an exact string path.
     * * @return void
     */
    public function testRouterMatchesExactPath(): void
    {
        $router = new Router();
        $router->add('GET', '/api/test', [DummyController::class, 'testAction']);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Routed correctly. ID: ");
        
        $router->dispatch('GET', '/api/test');
    }

    /**
     * @brief Tests if the router correctly parses and passes a single URL parameter.
     * * @return void
     */
    public function testRouterMatchesPathWithParameters(): void
    {
        $router = new Router();
        $router->add('GET', '/api/users/{id}', [DummyController::class, 'testAction']);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Routed correctly. ID: 42");
        
        $router->dispatch('GET', '/api/users/42');
    }

    /**
     * @brief Tests if the router respects HTTP methods (e.g., GET vs POST).
     * * @return void
     */
    public function testRouterRespectsHttpMethod(): void
    {
        $router = new Router();
        $router->add('POST', '/api/submit', [DummyController::class, 'testAction']);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Endpoint not found");
        
        $router->dispatch('GET', '/api/submit');
    }

    /**
     * @brief Tests if the router returns a 404 response for unknown paths.
     * * @return void
     */
    public function testRouterReturns404ForUnknownPath(): void
    {
        $router = new Router();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Endpoint not found");
        
        $router->dispatch('GET', '/api/unknown/route');
    }

    /**
     * @brief [NEW TEST] Tests if the router correctly extracts multiple regex parameters from the URL.
     * * @return void
     */
    public function testRouterExtractsMultipleParameters(): void
    {
        $router = new Router();
        $router->add('GET', '/api/users/{id}/posts/{postId}', [DummyController::class, 'testMultipleParams']);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Params: 42, 100");
        
        $router->dispatch('GET', '/api/users/42/posts/100');
    }
}