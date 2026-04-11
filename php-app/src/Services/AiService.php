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

        // ВАЖЛИВО: Налаштування безпеки для обходу блокувань фінансового контенту
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
        curl_close($ch);

        // Покращене логування помилок
        if ($httpCode !== 200 || $response === false) {
            $errorMsg = "Помилка зв'язку з сервером ШІ (Код: $httpCode).";
            if ($response) {
                $errData = json_decode($response, true);
                if (isset($errData['error']['message'])) {
                    $errorMsg .= " Деталі від Google: " . $errData['error']['message'];
                }
            }
            throw new Exception($errorMsg);
        }

        $data = json_decode($response, true);
        
        // Перевірка, чи не заблокував фільтр (Finish Reason)
        if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
             throw new Exception("Google Gemini заблокував відповідь через свої внутрішні фільтри безпеки.");
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        throw new Exception("Отримано порожню або нерозібрану відповідь від ШІ.");
    }
}