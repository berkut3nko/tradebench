<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Core\AuthMiddleware;
use Firebase\JWT\JWT;
use Exception;

class AuthMiddlewareTest extends TestCase
{
    private string $secret = 'test_secret_key_12345';

    /**
     * @brief Setup environment for each test.
     */
    protected function setUp(): void
    {
        // Define secret for JWT decoding used in AuthMiddleware
        $_ENV['JWT_SECRET'] = $this->secret;
        
        // Clear authorization headers from global state
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    /**
     * @brief Tests if authentication fails when no token is provided.
     */
    public function testAuthenticateThrowsExceptionWhenNoTokenProvided(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Token not provided");
        
        AuthMiddleware::authenticate();
    }

    /**
     * @brief Tests successful authentication with a valid JWT token.
     */
    public function testAuthenticateReturnsUserDataWithValidToken(): void
    {
        $payload = [
            'iss' => 'tradebench_api',
            'sub' => 42,
            'role' => 'pro',
            'iat' => time(),
            'exp' => time() + 3600
        ];

        // Generate valid token
        $token = JWT::encode($payload, $this->secret, 'HS256');
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        $result = AuthMiddleware::authenticate();

        $this->assertEquals(42, $result['id']);
        $this->assertEquals('pro', $result['role']);
    }

    /**
     * @brief Tests if authentication fails when the token has expired.
     */
    public function testAuthenticateThrowsExceptionWithExpiredToken(): void
    {
        $payload = [
            'sub' => 1,
            'exp' => time() - 100 // Expired 100 seconds ago
        ];

        $token = JWT::encode($payload, $this->secret, 'HS256');
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Token expired");

        AuthMiddleware::authenticate();
    }

    /**
     * @brief Tests if authorization fails when the user lacks the required role.
     */
    public function testAuthenticateThrowsExceptionWithInsufficientPermissions(): void
    {
        $payload = [
            'sub' => 1,
            'role' => 'standard'
        ];

        $token = JWT::encode($payload, $this->secret, 'HS256');
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        // User is 'standard', but we request 'admin' access
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Insufficient permissions");

        AuthMiddleware::authenticate(null, 'admin');
    }
}