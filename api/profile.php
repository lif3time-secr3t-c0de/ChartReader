<?php
require_once __DIR__ . '/../config/config.php';

Auth::requireLogin();

$db = getDB();
$userModel = new User($db);
$userId = (int) Auth::getUserId();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'GET') {
    $user = $userModel->findById($userId);
    if (!$user) {
        Response::error('User not found', 404);
    }

    $stats = $userModel->getDashboardStats($userId);
    Response::success('Profile fetched', [
        'user' => $user,
        'stats' => $stats
    ]);
}

if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

Security::verifyCsrf();

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    Response::error('Invalid JSON payload');
}

$action = (string) ($data['action'] ?? '');
if (!in_array($action, ['update-profile', 'change-password'], true)) {
    Response::error('Unknown action', 404);
}

if ($action === 'update-profile') {
    $fullName = trim((string) ($data['fullName'] ?? ''));
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
        Response::error('Full name must be between 2 and 120 characters');
    }

    if (!$userModel->updateProfile($userId, Security::sanitize($fullName))) {
        Response::error('Failed to update profile', 500);
    }

    $updatedUser = $userModel->findById($userId);
    Response::success('Profile updated', ['user' => $updatedUser]);
}

$oldPassword = (string) ($data['oldPassword'] ?? '');
$newPassword = (string) ($data['newPassword'] ?? '');
$confirmPassword = (string) ($data['confirmPassword'] ?? '');

if ($oldPassword === '' || $newPassword === '') {
    Response::error('Current and new password are required');
}

if ($newPassword !== $confirmPassword) {
    Response::error('New password and confirmation do not match');
}

if (strlen($newPassword) < 8) {
    Response::error('New password must be at least 8 characters');
}

$authUser = $userModel->findAuthById($userId);
if (!$authUser || !password_verify($oldPassword, $authUser['password_hash'])) {
    Response::error('Current password is incorrect', 401);
}

if (!$userModel->updatePassword($userId, $newPassword)) {
    Response::error('Failed to change password', 500);
}

Response::success('Password changed successfully');
