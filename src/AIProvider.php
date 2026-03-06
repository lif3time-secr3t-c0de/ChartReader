<?php

class AIProvider {
    private $apiKey;
    private $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent";

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function analyzeChart($imagePath) {
        if (!is_readable($imagePath)) {
            throw new Exception('Uploaded file is not readable.');
        }

        if (empty($this->apiKey)) {
            throw new Exception('Gemini API key is not configured.');
        }

        $imageData = base64_encode((string) file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath);

        $prompt = "Act as an expert Wall Street financial analyst and professional technical chartist. Analyze this trading chart image with high precision.
        Provide a detailed technical report in STRICT JSON format with the following keys:
        1. 'candlestick_patterns': Array of identified patterns (e.g., ['Bullish Engulfing', 'Doji']) with brief explanations.
        2. 'trend_lines': Detailed description of the current trend (bullish, bearish, sideways) and strength.
        3. 'levels': Object with 'support' and 'resistance' arrays containing key price levels identified.
        4. 'sentiment_score': Integer from 0 (Extreme Fear/Bearish) to 100 (Extreme Greed/Bullish).
        5. 'confidence_score': Float from 0.0 to 1.0 representing analysis certainty.
        6. 'risk_assessment': A string evaluating the current risk/reward ratio based on the chart.
        7. 'trade_idea': A brief suggestion (Buy/Sell/Hold) with entry, stop-loss, and take-profit zones if applicable.
        8. 'summary': A professional 2-3 sentence executive summary.
        9. 'visual_annotations': Array of objects with 'label', 'x', 'y' (coordinates 0-1000) for UI markers.

        Rules:
        - Be objective and data-driven.
        - If the image is not a financial chart, return {\"error\": \"Not a valid trading chart\"}.
        - ONLY return the JSON object, no markdown formatting.";

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                        [
                            "inline_data" => [
                                "mime_type" => $mimeType,
                                "data" => $imageData
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init($this->apiUrl . "?key=" . $this->apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $message = $result['error']['message'] ?? 'Gemini request failed.';
            throw new Exception($message);
        }

        if (!is_array($result)) {
            throw new Exception('Invalid Gemini response payload.');
        }

        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text === '') {
            return ['error' => 'Gemini returned an empty response'];
        }

        // Gemini sometimes wraps JSON in fenced blocks.
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
            $text = $matches[1];
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            return ['error' => 'Failed to parse AI response', 'raw' => $text];
        }

        return $decoded;
    }
}
