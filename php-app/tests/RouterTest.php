<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Router;
use Exception;

/**
 * Dummy controller used strictly for testing the Router dispatch mechanism.
 */
class DummyController {
    /**
     * Target action that throws an exception to prove it was executed.
     * @param string $id Optional parameter from URL.
     * @throws Exception
     */
    public function testAction(string $id = ''): void {
        throw new Exception("Routed correctly. ID: " . $id);
    }
}

class RouterTest extends TestCase
{
    /**
     * @brief Tests if the router correctly matches an exact string path.
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
     * @brief Tests if the router correctly parses and passes URL parameters.
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
     * @brief Tests if the router respects HTTP methods (GET vs POST).
     */
    public function testRouterRespectsHttpMethod(): void
    {
        $router = new Router();
        $router->add('POST', '/api/submit', [DummyController::class, 'testAction']);
        
        // We expect a 404 error because we registered POST, but dispatching GET
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Endpoint not found");
        
        $router->dispatch('GET', '/api/submit');
    }

    /**
     * @brief Tests if the router returns a 404 response (throws Exception in CLI) for unknown paths.
     */
    public function testRouterReturns404ForUnknownPath(): void
    {
        $router = new Router();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Endpoint not found");
        
        $router->dispatch('GET', '/api/unknown/route');
    }
}