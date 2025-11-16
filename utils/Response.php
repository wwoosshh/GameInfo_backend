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
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

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
}
