<?php
// init_db.php - Production-ready database initialization
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

try {
    $db = getDB();
    echo "Connected to database.\n";

    // Create tables
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $db->exec($sql);
    echo "Schema applied successfully.\n";

    // Create default admin user
    $adminEmail = 'admin@chartreader.io';
    $adminPass = envValue('DEFAULT_ADMIN_PASSWORD', '');
    $generatedPassword = false;
    if ($adminPass === '') {
        $adminPass = bin2hex(random_bytes(12));
        $generatedPassword = true;
    }
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    if (!$stmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$adminEmail, $hash, 'System Admin', 'admin']);
        echo "Default admin user created: $adminEmail\n";
        echo "Admin password: $adminPass\n";
        if ($generatedPassword) {
            echo "Tip: set DEFAULT_ADMIN_PASSWORD in .env to control this value.\n";
        }
    } else {
        echo "Admin user already exists.\n";
    }

    // Insert default patterns
    $patterns = [
        ['Bullish Engulfing', 'A large green candle that completely overlaps the previous small red candle.'],
        ['Bearish Engulfing', 'A large red candle that completely overlaps the previous small green candle.'],
        ['Hammer', 'A small body with a long lower wick, indicating a potential reversal at the bottom of a downtrend.'],
        ['Shooting Star', 'A small body with a long upper wick, indicating a potential reversal at the top of an uptrend.']
    ];

    $stmt = $db->prepare("INSERT OR IGNORE INTO patterns (name, description) VALUES (?, ?)");
    foreach ($patterns as $p) {
        $stmt->execute($p);
    }
    echo "Default patterns initialized.\n";

    echo "Database initialization complete.\n";
} catch (Exception $e) {
    echo "Initialization error: " . $e->getMessage() . "\n";
    exit(1);
}
