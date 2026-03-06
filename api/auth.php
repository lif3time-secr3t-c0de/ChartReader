<?php
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$action = $_GET['action'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userModel = new User($db);

if (!in_array($action, ['register', 'login', 'logout', 'me'], true)) {
    Response::error('Unknown action', 404);
}

if (in_array($action, ['register', 'login'], true) && !Security::checkRateLimit($db, $ipAddress)) {
    Response::error('Too many requests. Please try again later.', 429);
}

if (in_array($action, ['register', 'login'], true)) {
    if ($requestMethod !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    Security::verifyCsrf();

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        Response::error('Invalid JSON payload');
    }

    $email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($data['password'] ?? '');

    if (!$email || $password === '') {
        Response::error('Email and password are required');
    }

    if ($action === 'register') {
        $fullName = Security::sanitize((string) ($data['fullName'] ?? ''));

        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters long');
        }

        if ($userModel->findByEmail($email)) {
            Response::error('Email already exists');
        }

        if (!$userModel->create($email, $password, $fullName)) {
            Response::error('Registration failed');
        }

        $user = $userModel->findByEmail($email);
        Auth::login($user['id'], $user['role']);
        Response::success('Registration successful', ['user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name']
        ]]);
    }

    $user = $userModel->findByEmail($email);
    if ($user && password_verify($password, $user['password_hash'])) {
        Auth::login($user['id'], $user['role']);
        Response::success('Login successful', ['user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]]);
    }

    Response::error('Invalid email or password', 401);
}

if ($action === 'logout') {
    if ($requestMethod !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    Security::verifyCsrf();
    Auth::logout();
    Response::success('Logged out');
}

if ($action === 'me') {
    if ($requestMethod !== 'GET') {
        Response::error('Method not allowed', 405);
    }

    if (!Auth::isLoggedIn()) {
        Response::error('Not logged in', 401);
    }

    $user = $userModel->findById(Auth::getUserId());
    Response::success('User data', ['user' => $user]);
}
