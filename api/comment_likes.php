<?php
/**
 * Comment Likes API
 * 댓글 좋아요 관련 API 엔드포인트
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../models/CommentLike.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$commentLikeModel = new CommentLike();

// 인증 확인
$user = Auth::getCurrentUser();
if (!$user) {
    Response::error('Authentication required', 401);
}

// URL 파라미터 파싱
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$commentId = null;

// comment_likes/{comment_id} 형태에서 comment_id 추출
$commentLikesIndex = array_search('comment_likes', $uriParts);
if ($commentLikesIndex !== false && isset($uriParts[$commentLikesIndex + 1])) {
    $commentId = intval($uriParts[$commentLikesIndex + 1]);
}

try {
    switch ($method) {
        case 'POST':
            // 좋아요 추가
            if (!$commentId) {
                Response::error('Comment ID is required', 400);
            }
            handleLike($commentLikeModel, $commentId, $user);
            break;

        case 'DELETE':
            // 좋아요 취소
            if (!$commentId) {
                Response::error('Comment ID is required', 400);
            }
            handleUnlike($commentLikeModel, $commentId, $user);
            break;

        case 'GET':
            // 좋아요 상태 확인
            if (!$commentId) {
                Response::error('Comment ID is required', 400);
            }
            handleGetLikeStatus($commentLikeModel, $commentId, $user);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Comment Likes API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 좋아요 추가
 */
function handleLike($commentLikeModel, $commentId, $user) {
    $success = $commentLikeModel->like($commentId, $user['user_id']);

    if (!$success) {
        Response::error('Already liked or failed to like', 400);
    }

    Response::success([
        'liked' => true
    ], 'Comment liked successfully');
}

/**
 * 좋아요 취소
 */
function handleUnlike($commentLikeModel, $commentId, $user) {
    $success = $commentLikeModel->unlike($commentId, $user['user_id']);

    if (!$success) {
        Response::error('Not liked or failed to unlike', 400);
    }

    Response::success([
        'liked' => false
    ], 'Comment unliked successfully');
}

/**
 * 좋아요 상태 확인
 */
function handleGetLikeStatus($commentLikeModel, $commentId, $user) {
    $hasLiked = $commentLikeModel->hasLiked($commentId, $user['user_id']);

    Response::success([
        'liked' => $hasLiked
    ], 'Like status retrieved successfully');
}
