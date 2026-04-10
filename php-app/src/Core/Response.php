<?php

namespace App\Core;

/**
 * Handles standardized JSON responses
 */
class Response {
    
    /**
     * Send a successful JSON response
     */
    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    /**
     * Send an error JSON response
     */
    public static function error(string $message, int $statusCode = 400, $details = null): void {
        http_response_code($statusCode);
        $response = ["error" => $message];
        if ($details) {
            $response["details"] = $details;
        }
        echo json_encode($response);
        exit;
    }
}