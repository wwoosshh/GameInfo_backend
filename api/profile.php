<?php
/**
 * Profile API
 * 사용자 프로필 정보 관련 API 엔드포인트
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// 인증 확인
$user = Auth::getCurrentUser();
if (!$user) {
    Response::error('Authentication required', 401);
}

// URL 파라미터 파싱
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));

// profile/{action} 형태에서 action 추출
$profileIndex = array_search('profile', $uriParts);
$action = null;
if ($profileIndex !== false && isset($uriParts[$profileIndex + 1])) {
    $action = $uriParts[$profileIndex + 1];
}

try {
    if ($method !== 'GET') {
        Response::error('Method not allowed', 405);
    }

    switch ($action) {
        case null:
            // GET /api/profile - 사용자 정보
            handleGetProfile($user);
            break;

        case 'posts':
            // GET /api/profile/posts - 사용자 게시글 목록
            handleGetUserPosts($user);
            break;

        case 'comments':
            // GET /api/profile/comments - 사용자 댓글 목록
            handleGetUserComments($user);
            break;

        case 'likes':
            // GET /api/profile/likes - 사용자 좋아요 목록
            handleGetUserLikes($user);
            break;

        case 'reports':
            // GET /api/profile/reports - 사용자 신고 내역
            handleGetUserReports($user);
            break;

        default:
            Response::error('Invalid action', 400);
    }
} catch (Exception $e) {
    error_log('Profile API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 사용자 정보 조회
 */
function handleGetProfile($user) {
    $db = Database::getInstance()->getConnection();

    $sql = "SELECT user_id, username, email, display_name, avatar_url,
                   bio, is_admin, is_active, email_verified,
                   created_at, last_login_at
            FROM users
            WHERE user_id = :user_id";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $user['user_id']]);
        $profile = $stmt->fetch();

        if (!$profile) {
            Response::error('User not found', 404);
        }

        // 통계 정보 추가
        $stats = getUserStats($user['user_id']);
        $profile['stats'] = $stats;

        Response::success($profile, 'Profile retrieved successfully');
    } catch (PDOException $e) {
        error_log('handleGetProfile - ' . $e->getMessage());
        Response::error('Failed to retrieve profile', 500);
    }
}

/**
 * 사용자 통계 정보 조회
 */
function getUserStats($userId) {
    $db = Database::getInstance()->getConnection();

    try {
        // 게시글 수
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM posts WHERE user_id = :user_id AND is_deleted = false");
        $stmt->execute([':user_id' => $userId]);
        $postsCount = $stmt->fetch()['count'];

        // 댓글 수
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM comments WHERE user_id = :user_id AND is_deleted = false");
        $stmt->execute([':user_id' => $userId]);
        $commentsCount = $stmt->fetch()['count'];

        // 받은 좋아요 수 (게시글)
        $stmt = $db->prepare("SELECT COALESCE(SUM(p.like_count), 0) as count
                              FROM posts p
                              WHERE p.user_id = :user_id AND p.is_deleted = false");
        $stmt->execute([':user_id' => $userId]);
        $likesReceived = $stmt->fetch()['count'];

        // 북마크 수
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM post_bookmarks WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $bookmarksCount = $stmt->fetch()['count'];

        return [
            'posts_count' => (int)$postsCount,
            'comments_count' => (int)$commentsCount,
            'likes_received' => (int)$likesReceived,
            'bookmarks_count' => (int)$bookmarksCount
        ];
    } catch (PDOException $e) {
        error_log('getUserStats - ' . $e->getMessage());
        return [
            'posts_count' => 0,
            'comments_count' => 0,
            'likes_received' => 0,
            'bookmarks_count' => 0
        ];
    }
}

/**
 * 사용자 게시글 목록 조회
 */
function handleGetUserPosts($user) {
    $db = Database::getInstance()->getConnection();
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    $sql = "SELECT p.*,
                   u.username, u.display_name, u.avatar_url,
                   g.game_name
            FROM posts p
            JOIN users u ON p.user_id = u.user_id
            LEFT JOIN games g ON p.game_id = g.game_id
            WHERE p.user_id = :user_id AND p.is_deleted = false
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset";

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();

        // 전체 개수 조회
        $countSql = "SELECT COUNT(*) as count FROM posts WHERE user_id = :user_id AND is_deleted = false";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute([':user_id' => $user['user_id']]);
        $totalCount = $countStmt->fetch()['count'];

        Response::success([
            'posts' => $posts,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_items' => (int)$totalCount,
                'items_per_page' => $limit
            ]
        ], 'Posts retrieved successfully');
    } catch (PDOException $e) {
        error_log('handleGetUserPosts - ' . $e->getMessage());
        Response::error('Failed to retrieve posts', 500);
    }
}

/**
 * 사용자 댓글 목록 조회
 */
function handleGetUserComments($user) {
    $db = Database::getInstance()->getConnection();
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    $sql = "SELECT c.*,
                   u.username, u.display_name, u.avatar_url,
                   p.post_id, p.title as post_title
            FROM comments c
            JOIN users u ON c.user_id = u.user_id
            JOIN posts p ON c.post_id = p.post_id
            WHERE c.user_id = :user_id AND c.is_deleted = false
            ORDER BY c.created_at DESC
            LIMIT :limit OFFSET :offset";

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $comments = $stmt->fetchAll();

        // 전체 개수 조회
        $countSql = "SELECT COUNT(*) as count FROM comments WHERE user_id = :user_id AND is_deleted = false";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute([':user_id' => $user['user_id']]);
        $totalCount = $countStmt->fetch()['count'];

        Response::success([
            'comments' => $comments,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_items' => (int)$totalCount,
                'items_per_page' => $limit
            ]
        ], 'Comments retrieved successfully');
    } catch (PDOException $e) {
        error_log('handleGetUserComments - ' . $e->getMessage());
        Response::error('Failed to retrieve comments', 500);
    }
}

/**
 * 사용자가 좋아요한 게시글 목록 조회
 */
function handleGetUserLikes($user) {
    $db = Database::getInstance()->getConnection();
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    $sql = "SELECT p.*,
                   u.username, u.display_name, u.avatar_url,
                   g.game_name,
                   pl.created_at as liked_at
            FROM post_likes pl
            JOIN posts p ON pl.post_id = p.post_id
            JOIN users u ON p.user_id = u.user_id
            LEFT JOIN games g ON p.game_id = g.game_id
            WHERE pl.user_id = :user_id AND p.is_deleted = false
            ORDER BY pl.created_at DESC
            LIMIT :limit OFFSET :offset";

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $likes = $stmt->fetchAll();

        // 전체 개수 조회
        $countSql = "SELECT COUNT(*) as count
                     FROM post_likes pl
                     JOIN posts p ON pl.post_id = p.post_id
                     WHERE pl.user_id = :user_id AND p.is_deleted = false";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute([':user_id' => $user['user_id']]);
        $totalCount = $countStmt->fetch()['count'];

        Response::success([
            'likes' => $likes,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_items' => (int)$totalCount,
                'items_per_page' => $limit
            ]
        ], 'Liked posts retrieved successfully');
    } catch (PDOException $e) {
        error_log('handleGetUserLikes - ' . $e->getMessage());
        Response::error('Failed to retrieve liked posts', 500);
    }
}

/**
 * 사용자 신고 내역 조회
 */
function handleGetUserReports($user) {
    $db = Database::getInstance()->getConnection();
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    $sql = "SELECT r.*,
                   CASE
                       WHEN r.reported_type = 'post' THEN p.title
                       WHEN r.reported_type = 'comment' THEN CONCAT('댓글: ', SUBSTRING(c.content, 1, 50))
                   END as reported_content,
                   CASE
                       WHEN r.reported_type = 'post' THEN pu.username
                       WHEN r.reported_type = 'comment' THEN cu.username
                   END as reported_user_username
            FROM reports r
            LEFT JOIN posts p ON r.reported_type = 'post' AND r.reported_id = p.post_id
            LEFT JOIN comments c ON r.reported_type = 'comment' AND r.reported_id = c.comment_id
            LEFT JOIN users pu ON p.user_id = pu.user_id
            LEFT JOIN users cu ON c.user_id = cu.user_id
            WHERE r.reporter_id = :user_id
            ORDER BY r.created_at DESC
            LIMIT :limit OFFSET :offset";

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $reports = $stmt->fetchAll();

        // 전체 개수 조회
        $countSql = "SELECT COUNT(*) as count FROM reports WHERE reporter_id = :user_id";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute([':user_id' => $user['user_id']]);
        $totalCount = $countStmt->fetch()['count'];

        Response::success([
            'reports' => $reports,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_items' => (int)$totalCount,
                'items_per_page' => $limit
            ]
        ], 'Reports retrieved successfully');
    } catch (PDOException $e) {
        error_log('handleGetUserReports - ' . $e->getMessage());
        Response::error('Failed to retrieve reports', 500);
    }
}
