<?php
require_once __DIR__ . '/../config/config.php';

Auth::requireLogin();
$db = getDB();
$userModel = new User($db);
$userId = Auth::getUserId();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!Security::checkRateLimit($db, $ipAddress, $userId)) {
    Response::error('Analysis limit reached. Please try again later.', 429);
}

if ($requestMethod === 'POST') {
    Security::verifyCsrf();

    global $env;
    if (empty($env['GEMINI_API_KEY'])) {
        Response::error('AI service is not configured', 503);
    }

    if (!isset($_FILES['chart'])) {
        Response::error('No image uploaded');
    }

    $file = $_FILES['chart'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        Response::error('Upload failed', 400);
    }

    if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        Response::error('File too large. Max size is ' . (int) (MAX_UPLOAD_BYTES / (1024 * 1024)) . 'MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedTypes[$mimeType])) {
        Response::error('Invalid file type. Only JPG, PNG and WEBP are allowed.');
    }

    $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
    $targetPath = UPLOAD_DIR . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        try {
            $ai = new AIProvider($env['GEMINI_API_KEY']);
            $analysis = $ai->analyzeChart($targetPath);
            if (isset($analysis['error'])) {
                @unlink($targetPath);
                Response::error('AI Analysis failed: ' . $analysis['error'], 502);
            }

            // Save to database
            $stmt = $db->prepare("INSERT INTO analyses (user_id, image_path, analysis_result, confidence_score, market_sentiment_score) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $fileName,
                json_encode($analysis),
                $analysis['confidence_score'] ?? 0,
                $analysis['sentiment_score'] ?? 50
            ]);

            Response::success('Analysis complete', [
                'id' => $db->lastInsertId(),
                'analysis' => $analysis,
                'image' => $fileName
            ]);
        } catch (Throwable $e) {
            @unlink($targetPath);
            if (APP_ENV === 'development') {
                Response::error('AI Analysis failed: ' . $e->getMessage(), 500);
            }

            Response::error('AI Analysis failed. Please try again.', 500);
        }
    } else {
        Response::error('Failed to save uploaded file');
    }
} elseif ($requestMethod === 'GET') {
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
    $history = $userModel->listAnalyses((int) $userId, $limit);
    Response::success('History fetched', $history);
} else {
    Response::error('Method not allowed', 405);
}
