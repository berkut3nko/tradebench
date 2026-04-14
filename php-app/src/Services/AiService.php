<?php

namespace App\Services;

use Exception;

/**
 * @brief Service responsible for managing communications with the Google Gemini Large Language Model.
 */
class AiService {
    
    /**
     * @brief Generates an analytical insight based on the provided prompt.
     * @param string $prompt The formatted instructions to send to the AI.
     * @return string The text payload response from the AI.
     * @throws Exception If the API request fails, times out, or encounters safety blocks.
     */
    public function generateInsight(string $prompt): string {
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        
        if (empty($apiKey)) {
            throw new Exception("AI API key is missing from server configuration.");
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

        // IMPORTANT: Security settings to bypass strict financial content blocking algorithms
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "safetySettings" => [
                [
                    "category" => "HARM_CATEGORY_DANGEROUS_CONTENT",
                    "threshold" => "BLOCK_NONE"
                ],
                [
                    "category" => "HARM_CATEGORY_HARASSMENT",
                    "threshold" => "BLOCK_NONE"
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); 
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Advanced error logging and inspection
        if ($httpCode !== 200 || $response === false) {
            $errorMsg = "AI Server connection failed (HTTP Code: $httpCode).";
            if ($response) {
                $errData = json_decode($response, true);
                if (isset($errData['error']['message'])) {
                    $errorMsg .= " Google Details: " . $errData['error']['message'];
                }
            }
            throw new Exception($errorMsg);
        }

        $data = json_decode($response, true);
        
        // Inspect response payload to detect if Gemini halted execution due to internal safety algorithms
        if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
             throw new Exception("Google Gemini blocked the response due to its internal safety filters.");
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        throw new Exception("Received an empty or unparseable response from the AI.");
    }
}