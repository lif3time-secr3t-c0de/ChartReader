<?php
require_once __DIR__ . '/../config/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Response::error('Method not allowed', 405);
}

Auth::startSession();
Response::success('CSRF token', ['token' => Auth::generateCsrfToken()]);
