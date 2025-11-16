<?php
/**
 * 인증 및 권한 검증 유틸리티
 */

class Auth {
    private static $secretKey;

    /**
     * Initialize secret key from environment
     */
    private static function getSecretKey() {
        if (self::$secretKey === null) {
            // Load from environment variable or config
            $envFile = __DIR__ . '/../../config/.env';
            if (file_exists($envFile)) {
                // Use INI_SCANNER_RAW to avoid parsing issues with comments
                $env = @parse_ini_file($envFile, false, INI_SCANNER_RAW);
                if ($env !== false && isset($env['JWT_SECRET'])) {
                    self::$secretKey = $env['JWT_SECRET'];
                } else {
                    self::$secretKey = 'your_jwt_secret_key_change_this_in_production';
                }
            } else {
                self::$secretKey = 'your_jwt_secret_key_change_this_in_production';
            }
        }
        return self::$secretKey;
    }

    /**
     * JWT 토큰 생성
     */
    public static function generateToken($userId, $username, $isAdmin = false) {
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));

        $payload = base64_encode(json_encode([
            'user_id' => $userId,
            'username' => $username,
            'is_admin' => $isAdmin,
            'iat' => time(),
            'exp' => time() + (7 * 24 * 60 * 60) // 7일
        ]));

        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", self::getSecretKey(), true));

        return "$header.$payload.$signature";
    }

    /**
     * JWT 토큰 검증 및 디코딩
     */
    public static function verifyToken($token) {
        if (!$token) {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        $validSignature = base64_encode(hash_hmac('sha256', "$header.$payload", self::getSecretKey(), true));

        if ($signature !== $validSignature) {
            return false;
        }

        $payloadData = json_decode(base64_decode($payload), true);

        // 만료 확인
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return false;
        }

        return $payloadData;
    }

    /**
     * 요청 헤더에서 토큰 추출
     */
    public static function getTokenFromRequest() {
        // getallheaders() fallback for non-Apache environments
        if (!function_exists('getallheaders')) {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        } else {
            $headers = getallheaders();
        }

        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * 현재 사용자 정보 가져오기
     */
    public static function getCurrentUser() {
        $token = self::getTokenFromRequest();
        if (!$token) {
            return null;
        }

        return self::verifyToken($token);
    }

    /**
     * 관리자 권한 확인
     */
    public static function requireAdmin() {
        $user = self::getCurrentUser();

        if (!$user || !$user['is_admin']) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Admin access required'
                ]
            ]);
            exit;
        }

        return $user;
    }

    /**
     * 로그인 필수 확인
     */
    public static function requireAuth() {
        $user = self::getCurrentUser();

        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Authentication required'
                ]
            ]);
            exit;
        }

        return $user;
    }

    /**
     * 비밀번호 해싱
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * 비밀번호 검증
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
