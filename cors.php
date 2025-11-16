<?php
/**
 * CORS 헤더 설정
 * 모든 API 파일에서 include하여 사용
 */

// Load environment config
$envFile = __DIR__ . '/../config/.env';
$allowedOrigins = ['http://localhost:8000', 'http://localhost:3000'];
$environment = 'development';

if (file_exists($envFile)) {
    // Use INI_SCANNER_RAW to avoid parsing issues with comments
    $env = @parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if ($env !== false) {
        if (isset($env['ALLOWED_ORIGINS'])) {
            $allowedOrigins = explode(',', $env['ALLOWED_ORIGINS']);
            $allowedOrigins = array_map('trim', $allowedOrigins);
        }
        if (isset($env['ENVIRONMENT'])) {
            $environment = $env['ENVIRONMENT'];
        }
    }
}

// Get origin from request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Check if origin is allowed
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // In development, allow all. In production, this should be restricted
    if ($environment === 'development') {
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
