<?php
/**
 * PHP Built-in Server Router
 * URL 재작성을 처리합니다
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// /api/admin/users -> /api/admin/users.php
// /api/admin/posts -> /api/admin/posts.php
if (preg_match('/^\/api\/admin\/([\w-]+)/', $uri, $matches)) {
    $endpoint = $matches[1];

    // 하이픈을 언더스코어로 변환 (파일명 규칙)
    $filename = str_replace('-', '_', $endpoint);

    // Admin API 파일 경로
    $apiFile = __DIR__ . "/api/admin/{$filename}.php";

    if (file_exists($apiFile)) {
        require $apiFile;
        return true;
    }
}

// /api/auth/login -> /api/auth.php (login 처리)
// /api/games -> /api/games.php
// /api/versions/123/items -> /api/versions.php (123과 items 처리)
// /api/calendar-events -> /api/calendar_events.php
if (preg_match('/^\/api\/([\w-]+)/', $uri, $matches)) {
    $endpoint = $matches[1];

    // 하이픈을 언더스코어로 변환 (파일명 규칙)
    $filename = str_replace('-', '_', $endpoint);

    // API 파일 경로
    $apiFile = __DIR__ . "/api/{$filename}.php";

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
