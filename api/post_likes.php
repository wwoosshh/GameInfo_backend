<?php
/**
 * Post Likes API
 * 게시글 좋아요 관련 API 엔드포인트
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../models/PostLike.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$postLikeModel = new PostLike();

// 인증 확인
$user = Auth::getCurrentUser();
if (!$user) {
    Response::error('Authentication required', 401);
}

// URL 파라미터 파싱
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$postId = null;

// post_likes/{post_id} 형태에서 post_id 추출
$postLikesIndex = array_search('post_likes', $uriParts);
if ($postLikesIndex !== false && isset($uriParts[$postLikesIndex + 1])) {
    $postId = intval($uriParts[$postLikesIndex + 1]);
}

try {
    switch ($method) {
        case 'POST':
            // 좋아요 추가
            if (!$postId) {
                Response::error('Post ID is required', 400);
            }
            handleLike($postLikeModel, $postId, $user);
            break;

        case 'DELETE':
            // 좋아요 취소
            if (!$postId) {
                Response::error('Post ID is required', 400);
            }
            handleUnlike($postLikeModel, $postId, $user);
            break;

        case 'GET':
            // 좋아요 상태 확인
            if (!$postId) {
                Response::error('Post ID is required', 400);
            }
            handleGetLikeStatus($postLikeModel, $postId, $user);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Post Likes API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 좋아요 추가
 */
function handleLike($postLikeModel, $postId, $user) {
    $success = $postLikeModel->like($postId, $user['user_id']);

    if (!$success) {
        Response::error('Already liked or failed to like', 400);
    }

    // 알림 생성 (자기 게시글은 제외)
    $postModel = new Post();
    $post = $postModel->getById($postId);

    if ($post && $post['user_id'] !== $user['user_id']) {
        $notificationModel = new Notification();
        $notificationModel->createLikeNotification(
            $post['user_id'],
            $user['display_name'] ?? $user['username'],
            $postId,
            $post['title']
        );
    }

    Response::success([
        'liked' => true,
        'like_count' => $postLikeModel->getLikeCount($postId)
    ], 'Post liked successfully');
}

/**
 * 좋아요 취소
 */
function handleUnlike($postLikeModel, $postId, $user) {
    $success = $postLikeModel->unlike($postId, $user['user_id']);

    if (!$success) {
        Response::error('Not liked or failed to unlike', 400);
    }

    Response::success([
        'liked' => false,
        'like_count' => $postLikeModel->getLikeCount($postId)
    ], 'Post unliked successfully');
}

/**
 * 좋아요 상태 확인
 */
function handleGetLikeStatus($postLikeModel, $postId, $user) {
    $hasLiked = $postLikeModel->hasLiked($postId, $user['user_id']);
    $likeCount = $postLikeModel->getLikeCount($postId);

    Response::success([
        'liked' => $hasLiked,
        'like_count' => $likeCount
    ], 'Like status retrieved successfully');
}
