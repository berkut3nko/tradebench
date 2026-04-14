<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Core\AuthMiddleware;
use Firebase\JWT\JWT;
use Exception;

/**
 * @brief Unit test suite for the Authentication Middleware and JWT verification.
 */
class AuthMiddlewareTest extends TestCase
{
    /**
     * @var string Secret key used solely for testing JWT generation and decoding.
     */
    private string $secret = 'test_secret_key_12345';

    /**
     * @brief Setup environment variables before each test iteration.
     * @return void
     */
    protected function setUp(): void
    {
        $_ENV['JWT_SECRET'] = $this->secret;
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    /**
     * @brief Tests if authentication fails when no token is provided.
     * @throws Exception Expected when the token is absent.
     * @return void
     */
    public function testAuthenticateThrowsExceptionWhenNoTokenProvided(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Token not provided");
        
        AuthMiddleware::authenticate();
    }

    /**
     * @brief Tests successful authentication and payload extraction with a valid JWT token.
     * @return void
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

        $token = JWT::encode($payload, $this->secret, 'HS256');
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        $result = AuthMiddleware::authenticate();

        $this->assertEquals(42, $result['id']);
        $this->assertEquals('pro', $result['role']);
    }

    /**
     * @brief Tests if authentication fails when the token lifetime has expired.
     * @throws Exception Expected when the token is expired.
     * @return void
     */
    public function testAuthenticateThrowsExceptionWithExpiredToken(): void
    {
        $payload = [
            'sub' => 1,
            'exp' => time() - 100 
        ];

        $token = JWT::encode($payload, $this->secret, 'HS256');
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Token expired");

        AuthMiddleware::authenticate();
    }

    /**
     * @brief Tests if authorization fails when the user lacks the required RBAC role.
     * @throws Exception Expected when the user role is insufficient for the requested resource.
     * @return void
     */
    public function testAuthenticateThrowsExceptionWithInsufficientPermissions(): void
    {
        $payload = [
            'sub' => 1,
            'role' => 'standard'
        ];

        $token = JWT::encode($payload, $this->secret, 'HS256');
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Insufficient permissions");

        AuthMiddleware::authenticate(null, 'admin');
    }

    /**
     * @brief Tests if the middleware correctly extracts the JWT token from the query string (used for SSE).
     * @return void
     */
    public function testAuthenticateExtractsTokenFromQueryParameter(): void
    {
        $payload = [
            'iss' => 'tradebench_api',
            'sub' => 99,
            'role' => 'standard',
            'iat' => time(),
            'exp' => time() + 3600
        ];

        $token = JWT::encode($payload, $this->secret, 'HS256');
        
        $result = AuthMiddleware::authenticate($token);

        $this->assertEquals(99, $result['id']);
        $this->assertEquals('standard', $result['role']);
    }

    /**
     * @brief Tests if authentication fails when a completely invalid or malformed token is provided.
     * @throws Exception Expected when the token cannot be parsed.
     * @return void
     */
    public function testAuthenticateThrowsExceptionWithMalformedToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer invalid.garbage.token";

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid token");

        AuthMiddleware::authenticate();
    }

    /**
     * @brief Tests if authentication fails when the token is signed with an incorrect secret key.
     * @throws Exception Expected when signature verification fails to prevent tampering.
     * @return void
     */
    public function testAuthenticateThrowsExceptionWithWrongSignatureSecret(): void
    {
        $payload = [
            'sub' => 1,
            'role' => 'standard',
            'exp' => time() + 3600
        ];

        $wrongSecret = 'malicious_attacker_secret_key';
        $token = JWT::encode($payload, $wrongSecret, 'HS256');
        
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid token");

        AuthMiddleware::authenticate();
    }

    /**
     * @brief Tests if authentication rejects the request when the Authorization header is missing the Bearer prefix.
     * @throws Exception Expected because the middleware strictly requires the Bearer schema.
     * @return void
     */
    public function testAuthenticateThrowsExceptionWithoutBearerPrefix(): void
    {
        $payload = [
            'sub' => 1,
            'exp' => time() + 3600
        ];

        $token = JWT::encode($payload, $this->secret, 'HS256');
        
        $_SERVER['HTTP_AUTHORIZATION'] = $token;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Token not provided");

        AuthMiddleware::authenticate();
    }
}