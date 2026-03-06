<?php

class Security {
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }

        if (!is_string($data)) {
            return $data;
        }

        return htmlspecialchars(trim($data), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function checkRateLimit($db, $ip, $userId = null) {
        $stmt = $db->prepare("SELECT * FROM rate_limits WHERE ip_address = ? OR (user_id = ? AND user_id IS NOT NULL)");
        $stmt->execute([$ip, $userId]);
        $limit = $stmt->fetch();

        $now = new DateTime();
        $maxRequestsPerHour = isset($_ENV['MAX_REQUESTS_PER_HOUR']) ? (int) $_ENV['MAX_REQUESTS_PER_HOUR'] : 100;
        if ($limit) {
            $lastRequest = new DateTime($limit['last_request']);
            $interval = $now->getTimestamp() - $lastRequest->getTimestamp();

            // Reset count if last request was more than 1 hour ago
            if ($interval > 3600) {
                $stmt = $db->prepare("UPDATE rate_limits SET request_count = 1, last_request = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$limit['id']]);
                return true;
            }

            if ($limit['request_count'] >= $maxRequestsPerHour) {
                return false;
            }

            $stmt = $db->prepare("UPDATE rate_limits SET request_count = request_count + 1, last_request = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$limit['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO rate_limits (ip_address, user_id) VALUES (?, ?)");
            $stmt->execute([$ip, $userId]);
        }

        return true;
    }

    public static function verifyCsrf() {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::verifyCsrfToken($token)) {
            Response::error('Invalid CSRF token', 403);
        }
    }
}
