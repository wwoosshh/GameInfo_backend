<?php
/**
 * CORS 헤더 설정
 * 모든 API 파일에서 include하여 사용
 */

// Load allowed origins from environment variable or use defaults
$allowedOrigins = getenv('ALLOWED_ORIGINS')
    ? explode(',', getenv('ALLOWED_ORIGINS'))
    : [
        'http://localhost:8000',
        'http://localhost:3000',
        'https://game-info-frontend.vercel.app',
        'https://game-info-frontend-g3y34b47q-wwoosshhs-projects.vercel.app'
    ];

$allowedOrigins = array_map('trim', $allowedOrigins);

// Get origin from request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Check if origin is allowed
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Allow all Vercel preview deployments
    if (preg_match('/\.vercel\.app$/', $origin)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        // Fallback: allow all (can be restricted later)
        header('Access-Control-Allow-Origin: *');
    }
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
