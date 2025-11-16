<?php
/**
 * Posts API
 * 커뮤니티 게시글 관련 API 엔드포인트
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$postModel = new Post();

// URL 파라미터 파싱 (posts/{id} 형태)
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$postId = null;

// API 경로에서 posts 이후의 ID 추출
$postsIndex = array_search('posts', $uriParts);
if ($postsIndex !== false && isset($uriParts[$postsIndex + 1])) {
    $postId = intval($uriParts[$postsIndex + 1]);
}

try {
    switch ($method) {
        case 'GET':
            if ($postId) {
                // 게시글 상세 조회
                handleGetPostById($postModel, $postId);
            } else {
                // 게시글 목록 조회
                handleGetPosts($postModel);
            }
            break;

        case 'POST':
            // 게시글 생성 (인증 필요)
            handleCreatePost($postModel);
            break;

        case 'PUT':
            // 게시글 수정 (인증 + 소유권 필요)
            if (!$postId) {
                Response::error('Post ID is required', 400);
            }
            handleUpdatePost($postModel, $postId);
            break;

        case 'DELETE':
            // 게시글 삭제 (인증 + 소유권 필요)
            if (!$postId) {
                Response::error('Post ID is required', 400);
            }
            handleDeletePost($postModel, $postId);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Posts API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 게시글 목록 조회
 */
function handleGetPosts($postModel) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;

    $filters = [];

    // 카테고리 필터
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $filters['category'] = $_GET['category'];
    }

    // 게임 필터
    if (isset($_GET['game_id']) && !empty($_GET['game_id'])) {
        $filters['game_id'] = intval($_GET['game_id']);
    }

    // 검색
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $filters['search'] = $_GET['search'];
    }

    // 고정글 우선 정렬
    if (isset($_GET['pinned_first'])) {
        $filters['pinned_first'] = true;
    }

    $result = $postModel->getAll($page, $limit, $filters);

    if ($result === false) {
        Response::error('Failed to fetch posts', 500);
    }

    Response::success($result, 'Posts retrieved successfully');
}

/**
 * 게시글 상세 조회
 */
function handleGetPostById($postModel, $postId) {
    $post = $postModel->getById($postId);

    if (!$post) {
        Response::error('Post not found', 404);
    }

    // 조회수 증가
    $postModel->incrementViewCount($postId);

    Response::success($post, 'Post retrieved successfully');
}

/**
 * 게시글 생성
 */
function handleCreatePost($postModel) {
    // 인증 확인
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Authentication required', 401);
    }

    // 입력 데이터 파싱
    $input = json_decode(file_get_contents('php://input'), true);

    // 필수 필드 검증
    if (empty($input['title']) || empty($input['content'])) {
        Response::error('Title and content are required', 400);
    }

    // 제목/내용 길이 검증
    if (mb_strlen($input['title']) > 200) {
        Response::error('Title is too long (max 200 characters)', 400);
    }

    if (mb_strlen($input['content']) > 50000) {
        Response::error('Content is too long (max 50000 characters)', 400);
    }

    // 카테고리 검증
    $validCategories = ['discussion', 'guide', 'news', 'question', 'humor', 'fanart'];
    if (isset($input['category']) && !in_array($input['category'], $validCategories)) {
        Response::error('Invalid category', 400);
    }

    // 게시글 데이터 준비
    $postData = [
        'user_id' => $user['user_id'],
        'title' => trim($input['title']),
        'content' => trim($input['content']),
        'category' => $input['category'] ?? 'discussion',
        'game_id' => !empty($input['game_id']) ? intval($input['game_id']) : null,
        'tags' => !empty($input['tags']) && is_array($input['tags']) ? $input['tags'] : []
    ];

    // 게시글 생성
    $postId = $postModel->create($postData);

    if (!$postId) {
        Response::error('Failed to create post', 500);
    }

    // 생성된 게시글 조회
    $post = $postModel->getById($postId);

    Response::success($post, 'Post created successfully', 201);
}

/**
 * 게시글 수정
 */
function handleUpdatePost($postModel, $postId) {
    // 인증 확인
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Authentication required', 401);
    }

    // 게시글 존재 확인
    $post = $postModel->getById($postId);
    if (!$post) {
        Response::error('Post not found', 404);
    }

    // 소유권 확인 (관리자는 모든 게시글 수정 가능)
    if ($post['user_id'] !== $user['user_id'] && !Auth::isAdmin($user)) {
        Response::error('You do not have permission to edit this post', 403);
    }

    // 입력 데이터 파싱
    $input = json_decode(file_get_contents('php://input'), true);

    // 필수 필드 검증
    if (empty($input['title']) || empty($input['content'])) {
        Response::error('Title and content are required', 400);
    }

    // 제목/내용 길이 검증
    if (mb_strlen($input['title']) > 200) {
        Response::error('Title is too long (max 200 characters)', 400);
    }

    if (mb_strlen($input['content']) > 50000) {
        Response::error('Content is too long (max 50000 characters)', 400);
    }

    // 카테고리 검증
    $validCategories = ['discussion', 'guide', 'news', 'question', 'humor', 'fanart'];
    if (isset($input['category']) && !in_array($input['category'], $validCategories)) {
        Response::error('Invalid category', 400);
    }

    // 게시글 데이터 준비
    $postData = [
        'title' => trim($input['title']),
        'content' => trim($input['content']),
        'category' => $input['category'] ?? $post['category'],
        'game_id' => isset($input['game_id']) ? intval($input['game_id']) : $post['game_id'],
        'tags' => !empty($input['tags']) && is_array($input['tags']) ? $input['tags'] : []
    ];

    // 게시글 수정
    $success = $postModel->update($postId, $postData);

    if (!$success) {
        Response::error('Failed to update post', 500);
    }

    // 수정된 게시글 조회
    $updatedPost = $postModel->getById($postId);

    Response::success($updatedPost, 'Post updated successfully');
}

/**
 * 게시글 삭제
 */
function handleDeletePost($postModel, $postId) {
    // 인증 확인
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Authentication required', 401);
    }

    // 게시글 존재 확인
    $post = $postModel->getById($postId);
    if (!$post) {
        Response::error('Post not found', 404);
    }

    // 소유권 확인 (관리자는 모든 게시글 삭제 가능)
    if ($post['user_id'] !== $user['user_id'] && !Auth::isAdmin($user)) {
        Response::error('You do not have permission to delete this post', 403);
    }

    // 게시글 삭제 (소프트 삭제)
    $success = $postModel->delete($postId);

    if (!$success) {
        Response::error('Failed to delete post', 500);
    }

    Response::success(null, 'Post deleted successfully');
}
