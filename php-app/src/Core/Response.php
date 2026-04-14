<?php

namespace App\Core;

/**
 * @brief Utility class for standardizing JSON HTTP responses.
 */
class Response {
    
    /**
     * @brief Sends a successful JSON response and terminates execution.
     * @param array $data The payload to return.
     * @param int $statusCode HTTP status code (default: 200).
     */
    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    /**
     * @brief Sends an error JSON response and either throws an exception (in CLI) or terminates execution.
     * @param string $message The error message to display.
     * @param int $statusCode HTTP status code (default: 400).
     * @param mixed $details Additional debugging information.
     * @throws \Exception When running in CLI mode (PHPUnit compatibility).
     */
    public static function error(string $message, int $statusCode = 400, $details = null): void {
        http_response_code($statusCode);
        $response = ["error" => $message];
        if ($details) {
            $response["details"] = $details;
        }
        echo json_encode($response);
        
        // Prevent script termination during PHPUnit tests
        if (php_sapi_name() === 'cli') {
            throw new \Exception($message);
        }
        
        exit;
    }
}