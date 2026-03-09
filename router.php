<?php

declare(strict_types=1);

$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicPath = __DIR__ . '/public' . $uri;

if ($uri !== '/' && is_file($publicPath)) {
    return false;
}

if (str_starts_with($uri, '/api/')) {
    $apiPath = __DIR__ . $uri;
    if (!is_file($apiPath) && is_file($apiPath . '.php')) {
        $apiPath .= '.php';
    }

    if (is_file($apiPath)) {
        require $apiPath;
        return true;
    }

    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'API endpoint not found']);
    return true;
}

if (pathinfo($uri, PATHINFO_EXTENSION) !== '') {
    http_response_code(404);
    echo 'Not Found';
    return true;
}

require __DIR__ . '/public/index.php';
return true;
