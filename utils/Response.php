<?php
/**
 * API 응답 헬퍼 클래스
 *
 * 일관된 JSON 응답 형식을 제공합니다.
 */

class Response {
    /**
     * 성공 응답 반환
     *
     * @param mixed $data 응답 데이터
     * @param string $message 성공 메시지
     * @param int $statusCode HTTP 상태 코드
     * @param int $cacheTTL 캐시 TTL (초), 0이면 캐싱 안 함
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200, $cacheTTL = 0) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        // 캐싱 헤더 설정
        if ($cacheTTL > 0) {
            self::setCacheHeaders($cacheTTL);
        } else {
            self::setNoCacheHeaders();
        }

        echo json_encode([
            'success' => true,
            'data' => $data,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;
    }

    /**
     * 에러 응답 반환
     *
     * @param string $message 에러 메시지
     * @param string $code 에러 코드
     * @param int $statusCode HTTP 상태 코드
     */
    public static function error($message, $code = 'ERROR', $statusCode = 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;
    }

    /**
     * 인증 실패 응답
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 'UNAUTHORIZED', 401);
    }

    /**
     * 권한 없음 응답
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 'FORBIDDEN', 403);
    }

    /**
     * 리소스 없음 응답
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 'NOT_FOUND', 404);
    }

    /**
     * 유효성 검사 실패 응답
     */
    public static function validationError($message, $errors = []) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $message,
                'details' => $errors
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;
    }

    /**
     * 서버 오류 응답
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 'SERVER_ERROR', 500);
    }

    /**
     * 캐싱 헤더 설정
     *
     * @param int $ttl 캐시 유효 시간 (초)
     */
    private static function setCacheHeaders($ttl) {
        $expires = gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT';

        header("Cache-Control: public, max-age={$ttl}");
        header("Expires: {$expires}");
        header("Pragma: public");

        // ETag 생성 (선택적)
        $etag = md5(json_encode($_SERVER['REQUEST_URI']));
        header("ETag: \"{$etag}\"");
    }

    /**
     * 캐싱 비활성화 헤더 설정
     */
    private static function setNoCacheHeaders() {
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
}
