<?php

class Response {
    public static function json($data, $status = 200) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        http_response_code($status);

        try {
            echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            http_response_code(500);
            echo '{"error":"Failed to encode response"}';
        }

        exit;
    }

    public static function error($message, $status = 400) {
        self::json(['error' => $message], $status);
    }

    public static function success($message, $data = []) {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}
