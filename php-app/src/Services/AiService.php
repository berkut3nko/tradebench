<?php

namespace App\Services;

use Exception;

/**
 * Service responsible for communicating with Google Gemini API
 */
class AiService {
    
    /**
     * @param string $prompt The prompt to send to the AI
     * @return string The AI's response text
     * @throws Exception If the API request fails
     */
    public function generateInsight(string $prompt): string {
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        
        if (empty($apiKey)) {
            throw new Exception("API ключ для ШІ не налаштовано на сервері.");
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // ШІ може думати кілька секунд
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            throw new Exception("Помилка зв'язку з сервером ШІ.");
        }

        $data = json_decode($response, true);
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        throw new Exception("Отримано порожню відповідь від ШІ.");
    }
}