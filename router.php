<?php
/**
 * PHP Built-in Server Router
 * URL 재작성을 처리합니다
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// /api/auth/login -> /api/auth.php (login 처리)
// /api/games -> /api/games.php
// /api/versions/123/items -> /api/versions.php (123과 items 처리)
if (preg_match('/^\/api\/(\w+)/', $uri, $matches)) {
    $endpoint = $matches[1];

    // API 파일 경로
    $apiFile = __DIR__ . "/api/{$endpoint}.php";

    if (file_exists($apiFile)) {
        require $apiFile;
        return true;
    }
}

// 정적 파일이면 그대로 처리
if (file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    return false;
}

// 404
http_response_code(404);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error' => [
        'code' => 'NOT_FOUND',
        'message' => 'Endpoint not found'
    ]
]);
return true;
