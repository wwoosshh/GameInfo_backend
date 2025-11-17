<?php
/**
 * Admin Posts API
 * 관리자 게시글 관리 API
 */

require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// 관리자 권한 확인
$user = Auth::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

// URL 파라미터 파싱
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$postId = null;

$postsIndex = array_search('posts', $uriParts);
if ($postsIndex !== false && isset($uriParts[$postsIndex + 1])) {
    $postId = intval($uriParts[$postsIndex + 1]);
}

try {
    switch ($method) {
        case 'GET':
            if ($postId) {
                // 특정 게시글 상세 조회
                handleGetPost($db, $postId);
            } else {
                // 게시글 목록 조회
                handleGetPosts($db);
            }
            break;

        case 'PUT':
            // 게시글 정보 수정 (공지 설정, 잠금 등)
            if (!$postId) {
                Response::error('Post ID is required', 400);
            }
            handleUpdatePost($db, $postId);
            break;

        case 'DELETE':
            // 게시글 삭제
            if (!$postId) {
                Response::error('Post ID is required', 400);
            }
            handleDeletePost($db, $postId);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin Posts API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 게시글 목록 조회
 */
function handleGetPosts($db) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? $_GET['search'] : null;
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    $isDeleted = isset($_GET['is_deleted']) ? $_GET['is_deleted'] : 'false';
    $isPinned = isset($_GET['is_pinned']) ? $_GET['is_pinned'] : null;

    $sql = "SELECT
                p.*,
                u.username,
                u.display_name,
                g.game_name
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.user_id
            LEFT JOIN games g ON p.game_id = g.game_id
            WHERE 1=1";

    $params = [];

    if ($search) {
        $sql .= " AND (p.title LIKE :search OR p.content LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($category) {
        $sql .= " AND p.category = :category";
        $params[':category'] = $category;
    }

    if ($isDeleted !== null) {
        $sql .= " AND p.is_deleted = :is_deleted";
        $params[':is_deleted'] = $isDeleted === 'true' ? 1 : 0;
    }

    if ($isPinned !== null) {
        $sql .= " AND p.is_pinned = :is_pinned";
        $params[':is_pinned'] = $isPinned === 'true' ? 1 : 0;
    }

    $sql .= " ORDER BY p.created_at DESC
              LIMIT :limit OFFSET :offset";

    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();

        // 전체 개수 조회
        $countSql = "SELECT COUNT(*) FROM posts p WHERE 1=1";
        if ($search) {
            $countSql .= " AND (p.title LIKE :search OR p.content LIKE :search)";
        }
        if ($category) {
            $countSql .= " AND p.category = :category";
        }
        if ($isDeleted !== null) {
            $countSql .= " AND p.is_deleted = :is_deleted";
        }
        if ($isPinned !== null) {
            $countSql .= " AND p.is_pinned = :is_pinned";
        }

        $countStmt = $db->prepare($countSql);
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $countStmt->bindValue($key, $value);
            }
        }
        $countStmt->execute();
        $total = $countStmt->fetchColumn();

        Response::success([
            'posts' => $posts,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_items' => (int)$total,
                'items_per_page' => $limit
            ]
        ]);
    } catch (PDOException $e) {
        error_log('handleGetPosts error: ' . $e->getMessage());
        Response::error('Failed to fetch posts', 500);
    }
}

/**
 * 특정 게시글 상세 조회
 */
function handleGetPost($db, $postId) {
    $sql = "SELECT
                p.*,
                u.username,
                u.display_name,
                u.email,
                g.game_name
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.user_id
            LEFT JOIN games g ON p.game_id = g.game_id
            WHERE p.post_id = :post_id";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':post_id' => $postId]);
        $post = $stmt->fetch();

        if (!$post) {
            Response::error('Post not found', 404);
        }

        Response::success($post);
    } catch (PDOException $e) {
        error_log('handleGetPost error: ' . $e->getMessage());
        Response::error('Failed to fetch post', 500);
    }
}

/**
 * 게시글 정보 수정 (공지, 잠금 등)
 */
function handleUpdatePost($db, $postId) {
    $data = json_decode(file_get_contents('php://input'), true);

    $fields = [];
    $params = [':post_id' => $postId];

    if (isset($data['is_pinned'])) {
        $fields[] = "is_pinned = :is_pinned";
        $params[':is_pinned'] = $data['is_pinned'] ? 1 : 0;
    }

    if (isset($data['is_locked'])) {
        $fields[] = "is_locked = :is_locked";
        $params[':is_locked'] = $data['is_locked'] ? 1 : 0;
    }

    if (isset($data['is_deleted'])) {
        $fields[] = "is_deleted = :is_deleted";
        $params[':is_deleted'] = $data['is_deleted'] ? 1 : 0;

        if ($data['is_deleted']) {
            $fields[] = "deleted_at = NOW()";
        }
    }

    if (isset($data['category'])) {
        $fields[] = "category = :category";
        $params[':category'] = $data['category'];
    }

    if (empty($fields)) {
        Response::error('No data to update', 400);
    }

    $fields[] = "updated_at = NOW()";

    $sql = "UPDATE posts SET " . implode(', ', $fields) . " WHERE post_id = :post_id";

    try {
        $stmt = $db->prepare($sql);
        $success = $stmt->execute($params);

        if ($success) {
            // 수정된 게시글 정보 반환
            handleGetPost($db, $postId);
        } else {
            Response::error('Failed to update post', 500);
        }
    } catch (PDOException $e) {
        error_log('handleUpdatePost error: ' . $e->getMessage());
        Response::error('Failed to update post', 500);
    }
}

/**
 * 게시글 삭제 (소프트 삭제)
 */
function handleDeletePost($db, $postId) {
    $sql = "UPDATE posts SET is_deleted = true, deleted_at = NOW(), updated_at = NOW() WHERE post_id = :post_id";

    try {
        $stmt = $db->prepare($sql);
        $success = $stmt->execute([':post_id' => $postId]);

        if ($success) {
            Response::success(null, 'Post deleted successfully');
        } else {
            Response::error('Failed to delete post', 500);
        }
    } catch (PDOException $e) {
        error_log('handleDeletePost error: ' . $e->getMessage());
        Response::error('Failed to delete post', 500);
    }
}
