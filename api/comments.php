<?php
/**
 * Comments API
 * 댓글 관련 API 엔드포인트
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../models/Comment.php';
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../models/ActivityLog.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$commentModel = new Comment();

// URL 파라미터 파싱 (comments/{id} 형태)
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$commentId = null;

// API 경로에서 comments 이후의 ID 추출
$commentsIndex = array_search('comments', $uriParts);
if ($commentsIndex !== false && isset($uriParts[$commentsIndex + 1])) {
    $commentId = intval($uriParts[$commentsIndex + 1]);
}

try {
    switch ($method) {
        case 'GET':
            if ($commentId) {
                // 댓글 단일 조회
                handleGetCommentById($commentModel, $commentId);
            } else {
                // 게시글의 댓글 목록 조회 (post_id 필수)
                handleGetComments($commentModel);
            }
            break;

        case 'POST':
            // 댓글 생성 (인증 필요)
            handleCreateComment($commentModel);
            break;

        case 'PUT':
            // 댓글 수정 (인증 + 소유권 필요)
            if (!$commentId) {
                Response::error('Comment ID is required', 400);
            }
            handleUpdateComment($commentModel, $commentId);
            break;

        case 'DELETE':
            // 댓글 삭제 (인증 + 소유권 필요)
            if (!$commentId) {
                Response::error('Comment ID is required', 400);
            }
            handleDeleteComment($commentModel, $commentId);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Comments API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 게시글의 댓글 목록 조회
 */
function handleGetComments($commentModel) {
    // post_id 파라미터 필수
    if (!isset($_GET['post_id']) || empty($_GET['post_id'])) {
        Response::error('post_id parameter is required', 400);
    }

    $postId = intval($_GET['post_id']);

    $comments = $commentModel->getByPostId($postId);

    if ($comments === false) {
        Response::error('Failed to fetch comments', 500);
    }

    Response::success($comments, 'Comments retrieved successfully');
}

/**
 * 댓글 단일 조회
 */
function handleGetCommentById($commentModel, $commentId) {
    $comment = $commentModel->getById($commentId);

    if (!$comment) {
        Response::error('Comment not found', 404);
    }

    Response::success($comment, 'Comment retrieved successfully');
}

/**
 * 댓글 생성
 */
function handleCreateComment($commentModel) {
    // 인증 확인
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Authentication required', 401);
    }

    // 입력 데이터 파싱
    $input = json_decode(file_get_contents('php://input'), true);

    // 필수 필드 검증
    if (empty($input['post_id'])) {
        Response::error('post_id is required', 400);
    }

    if (empty($input['content'])) {
        Response::error('Content is required', 400);
    }

    // 내용 길이 검증
    if (mb_strlen($input['content']) > 5000) {
        Response::error('Content is too long (max 5000 characters)', 400);
    }

    // 댓글 데이터 준비
    $commentData = [
        'post_id' => intval($input['post_id']),
        'user_id' => $user['user_id'],
        'content' => trim($input['content']),
        'parent_comment_id' => !empty($input['parent_comment_id']) ? intval($input['parent_comment_id']) : null
    ];

    // 대댓글인 경우 부모 댓글 존재 확인
    if ($commentData['parent_comment_id']) {
        $parentComment = $commentModel->getById($commentData['parent_comment_id']);
        if (!$parentComment) {
            Response::error('Parent comment not found', 404);
        }

        // 부모 댓글의 post_id가 일치하는지 확인
        if ($parentComment['post_id'] !== $commentData['post_id']) {
            Response::error('Parent comment does not belong to the same post', 400);
        }
    }

    // 댓글 생성
    $commentId = $commentModel->create($commentData);

    if (!$commentId) {
        Response::error('Failed to create comment', 500);
    }

    // 활동 로그 기록
    $activityLog = new ActivityLog();
    $activityLog->logComment($user['user_id'], $commentId, $commentData['post_id']);

    // 생성된 댓글 조회
    $comment = $commentModel->getById($commentId);

    Response::success($comment, 'Comment created successfully', 201);
}

/**
 * 댓글 수정
 */
function handleUpdateComment($commentModel, $commentId) {
    // 인증 확인
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Authentication required', 401);
    }

    // 댓글 존재 확인
    $comment = $commentModel->getById($commentId);
    if (!$comment) {
        Response::error('Comment not found', 404);
    }

    // 소유권 확인 (관리자는 모든 댓글 수정 가능)
    if ($comment['user_id'] !== $user['user_id'] && !Auth::isAdmin($user)) {
        Response::error('You do not have permission to edit this comment', 403);
    }

    // 입력 데이터 파싱
    $input = json_decode(file_get_contents('php://input'), true);

    // 필수 필드 검증
    if (empty($input['content'])) {
        Response::error('Content is required', 400);
    }

    // 내용 길이 검증
    if (mb_strlen($input['content']) > 5000) {
        Response::error('Content is too long (max 5000 characters)', 400);
    }

    // 댓글 수정
    $success = $commentModel->update($commentId, trim($input['content']));

    if (!$success) {
        Response::error('Failed to update comment', 500);
    }

    // 수정된 댓글 조회
    $updatedComment = $commentModel->getById($commentId);

    Response::success($updatedComment, 'Comment updated successfully');
}

/**
 * 댓글 삭제
 */
function handleDeleteComment($commentModel, $commentId) {
    // 인증 확인
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Authentication required', 401);
    }

    // 댓글 존재 확인
    $comment = $commentModel->getById($commentId);
    if (!$comment) {
        Response::error('Comment not found', 404);
    }

    // 소유권 확인 (관리자는 모든 댓글 삭제 가능)
    if ($comment['user_id'] !== $user['user_id'] && !Auth::isAdmin($user)) {
        Response::error('You do not have permission to delete this comment', 403);
    }

    // 댓글 삭제 (소프트 삭제)
    $success = $commentModel->delete($commentId);

    if (!$success) {
        Response::error('Failed to delete comment', 500);
    }

    Response::success(null, 'Comment deleted successfully');
}
