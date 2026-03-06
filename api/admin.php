<?php
require_once __DIR__ . '/../config/config.php';

Auth::requireAdmin();

$db = getDB();
$action = $_GET['action'] ?? 'stats';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod !== 'GET') {
    Response::error('Method not allowed', 405);
}

if ($action === 'stats') {
    $cacheKey = 'admin_stats';
    $cached = Cache::get($cacheKey);
    if ($cached) {
        Response::success('Admin stats fetched (cached)', $cached);
    }

    // Total users
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Total analyses
    $analysisCount = $db->query("SELECT COUNT(*) FROM analyses")->fetchColumn();
    
    // Monthly revenue (estimated from active subscriptions)
    $activeSubscriptions = (int) $db->query("SELECT COUNT(*) FROM users WHERE subscription_status = 'active'")->fetchColumn();
    $revenue = $activeSubscriptions * 29;
    
    // Recent analyses
    $stmt = $db->query("SELECT a.*, u.email FROM analyses a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 10");
    $recentAnalyses = $stmt->fetchAll();

    $stats = [
        'total_users' => $userCount,
        'total_analyses' => $analysisCount,
        'active_subscriptions' => $activeSubscriptions,
        'monthly_revenue' => $revenue,
        'recent_analyses' => $recentAnalyses
    ];

    Cache::set($cacheKey, $stats, 600); // Cache for 10 minutes

    Response::success('Admin stats fetched', $stats);
}

if ($action === 'users') {
    $stmt = $db->query(
        "SELECT id, email, full_name, role, subscription_status, subscription_plan, created_at
         FROM users
         ORDER BY created_at DESC
         LIMIT 200"
    );
    Response::success('Users fetched', ['items' => $stmt->fetchAll()]);
}

if ($action === 'payments') {
    $stmt = $db->query(
        "SELECT p.*, u.email
         FROM payments p
         JOIN users u ON u.id = p.user_id
         ORDER BY p.created_at DESC
         LIMIT 200"
    );
    Response::success('Payments fetched', ['items' => $stmt->fetchAll()]);
}

Response::error('Unknown action', 404);
