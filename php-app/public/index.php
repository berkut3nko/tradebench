<?php

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @brief Main entry point for the PHP Backend.
 * It handles basic routing and initializes the application.
 */

header('Content-Type: application/json');

$response = [
    "status" => "online",
    "message" => "TradeBench PHP API is running",
    "timestamp" => time()
];

echo json_encode($response);