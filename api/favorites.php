<?php
require_once __DIR__ . '/../config/config.php';

Auth::requireLogin();

$db = getDB();
$userModel = new User($db);
$userId = (int) Auth::getUserId();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'GET') {
    $favorites = $userModel->listFavorites($userId);
    Response::success('Favorites fetched', ['items' => $favorites]);
}

if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

Security::verifyCsrf();

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    Response::error('Invalid JSON payload');
}

$analysisId = (int) ($payload['analysisId'] ?? 0);
$action = (string) ($payload['action'] ?? 'toggle');

if ($analysisId <= 0) {
    Response::error('Valid analysisId is required');
}

$analysis = $userModel->findAnalysisForUser($analysisId, $userId);
if (!$analysis) {
    Response::error('Analysis not found', 404);
}

if ($action === 'add') {
    $userModel->addFavorite($userId, $analysisId);
    Response::success('Favorite added', ['analysisId' => $analysisId, 'is_favorite' => true]);
}

if ($action === 'remove') {
    $userModel->removeFavorite($userId, $analysisId);
    Response::success('Favorite removed', ['analysisId' => $analysisId, 'is_favorite' => false]);
}

if ($action !== 'toggle') {
    Response::error('Unknown action', 404);
}

$existing = $db->prepare("SELECT id FROM favorites WHERE user_id = ? AND analysis_id = ?");
$existing->execute([$userId, $analysisId]);

if ($existing->fetch()) {
    $userModel->removeFavorite($userId, $analysisId);
    Response::success('Favorite removed', ['analysisId' => $analysisId, 'is_favorite' => false]);
}

$userModel->addFavorite($userId, $analysisId);
Response::success('Favorite added', ['analysisId' => $analysisId, 'is_favorite' => true]);
