<?php
/**
 * ChartReader.io Production Configuration
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
}

if (!function_exists('envValue')) {
    function envValue($key, $default = null) {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

if (!defined('APP_ENV')) {
    define('APP_ENV', envValue('APP_ENV', 'development'));
}

if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/../' . envValue('DB_PATH', 'database.sqlite'));
}

if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', __DIR__ . '/../public/uploads/');
}

if (!defined('BASE_URL')) {
    define('BASE_URL', envValue('BASE_URL', 'http://localhost:8000'));
}

if (!defined('MAX_UPLOAD_BYTES')) {
    define('MAX_UPLOAD_BYTES', (int) envValue('MAX_UPLOAD_BYTES', 5 * 1024 * 1024));
}

// Environment Variables
$env = [
    'GEMINI_API_KEY' => envValue('GEMINI_API_KEY', ''),
    'STRIPE_SECRET_KEY' => envValue('STRIPE_SECRET_KEY', ''),
    'STRIPE_WEBHOOK_SECRET' => envValue('STRIPE_WEBHOOK_SECRET', ''),
    'JWT_SECRET' => envValue('JWT_SECRET', 'default_secret'),
    'APP_ENV' => APP_ENV,
];

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

if (PHP_SAPI !== 'cli') {
    $allowedOrigin = envValue('APP_URL', '*');
    header("Access-Control-Allow-Origin: " . $allowedOrigin);
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");

    if ($allowedOrigin !== '*') {
        header('Vary: Origin');
    }

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// Autoloader for src/ classes (if not handled by composer)
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Database Connection
function getDB() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("PRAGMA foreign_keys = ON;"); // Enable foreign keys for SQLite
        return $pdo;
    } catch (PDOException $e) {
        if (APP_ENV === 'development') {
            die("Database connection failed: " . $e->getMessage());
        } else {
            error_log("DB Connection Error: " . $e->getMessage());
            die("Service unavailable. Please try again later.");
        }
    }
}
