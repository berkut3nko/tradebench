<?php

namespace App\Controllers;

use App\Core\Response;
use Throwable;

/**
 * @brief Controller for checking system health and microservices status.
 */
class SystemController {
    
    /**
     * @brief Checks if the C++ gRPC engine is reachable via TCP socket.
     * @return void
     */
    public function getStatus(): void {
        $cppHost = 'cpp-engine';
        $cppPort = 50051;
        $cppActive = false;
        $debugError = '';

        try {
            $socket = @fsockopen($cppHost, $cppPort, $errno, $errstr, 2);
            
            if (is_resource($socket)) {
                $cppActive = true;
                fclose($socket);
            } else {
                $debugError = "TCP Ping failed: $errstr ($errno)";
            }
        } catch (Throwable $e) {
            $debugError = "System error: " . $e->getMessage();
        }

        Response::json([
            'cpp_core_active' => $cppActive,
            'debug' => $debugError
        ]);
    }
}