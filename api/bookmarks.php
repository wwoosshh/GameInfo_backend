<?php
/**
 * Bookmarks API
 * 북마크 관련 API 엔드포인트
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../models/PostBookmark.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$bookmarkModel = new PostBookmark();

// 인증 확인
$user = Auth::getCurrentUser();
if (!$user) {
    Response::error('Authentication required', 401);
}

// URL 파라미터 파싱
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$postId = null;

// bookmarks/{post_id} 형태에서 post_id 추출
$bookmarksIndex = array_search('bookmarks', $uriParts);
if ($bookmarksIndex !== false && isset($uriParts[$bookmarksIndex + 1])) {
    $postId = intval($uriParts[$bookmarksIndex + 1]);
}

try {
    switch ($method) {
        case 'GET':
            if ($postId) {
                // 북마크 상태 확인
                handleGetBookmarkStatus($bookmarkModel, $postId, $user);
            } else {
                // 사용자의 북마크 목록
                handleGetUserBookmarks($bookmarkModel, $user);
            }
            break;

        case 'POST':
            // 북마크 추가
            if (!$postId) {
                Response::error('Post ID is required', 400);
            }
            handleBookmark($bookmarkModel, $postId, $user);
            break;

        case 'DELETE':
            // 북마크 취소
            if (!$postId) {
                Response::error('Post ID is required', 400);
            }
            handleUnbookmark($bookmarkModel, $postId, $user);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Bookmarks API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 북마크 추가
 */
function handleBookmark($bookmarkModel, $postId, $user) {
    $success = $bookmarkModel->bookmark($postId, $user['user_id']);

    if (!$success) {
        Response::error('Already bookmarked or failed to bookmark', 400);
    }

    Response::success([
        'bookmarked' => true
    ], 'Post bookmarked successfully');
}

/**
 * 북마크 취소
 */
function handleUnbookmark($bookmarkModel, $postId, $user) {
    $success = $bookmarkModel->unbookmark($postId, $user['user_id']);

    if (!$success) {
        Response::error('Not bookmarked or failed to unbookmark', 400);
    }

    Response::success([
        'bookmarked' => false
    ], 'Bookmark removed successfully');
}

/**
 * 북마크 상태 확인
 */
function handleGetBookmarkStatus($bookmarkModel, $postId, $user) {
    $hasBookmarked = $bookmarkModel->hasBookmarked($postId, $user['user_id']);

    Response::success([
        'bookmarked' => $hasBookmarked
    ], 'Bookmark status retrieved successfully');
}

/**
 * 사용자의 북마크 목록 조회
 */
function handleGetUserBookmarks($bookmarkModel, $user) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;

    $bookmarks = $bookmarkModel->getUserBookmarks($user['user_id'], $page, $limit);

    Response::success([
        'bookmarks' => $bookmarks
    ], 'Bookmarks retrieved successfully');
}
