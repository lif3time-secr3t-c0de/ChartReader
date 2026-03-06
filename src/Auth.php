<?php

class Auth {
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => $isHttps,
                'cookie_samesite' => 'Lax',
            ]);
        }
    }

    public static function login($userId, $role = 'user') {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $role;
        $_SESSION['last_activity'] = time();
        unset($_SESSION['csrf_token']);
        self::generateCsrfToken();
    }

    public static function logout() {
        self::startSession();
        session_unset();
        session_destroy();
    }

    public static function isLoggedIn() {
        self::startSession();
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $lifetime = isset($_ENV['SESSION_LIFETIME']) ? (int) $_ENV['SESSION_LIFETIME'] : 3600;
        $lastActivity = $_SESSION['last_activity'] ?? 0;
        if ($lastActivity > 0 && (time() - $lastActivity) > $lifetime) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function getUserId() {
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }

    public static function isAdmin() {
        self::startSession();
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public static function generateCsrfToken() {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken($token) {
        self::startSession();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            Response::error('Unauthorized', 401);
        }
    }

    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            Response::error('Forbidden', 403);
        }
    }
}
