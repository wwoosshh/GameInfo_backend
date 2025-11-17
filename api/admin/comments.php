<?php
/**
 * Admin Comments API
 * 관리자 댓글 관리 API
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
$commentId = null;

$commentsIndex = array_search('comments', $uriParts);
if ($commentsIndex !== false && isset($uriParts[$commentsIndex + 1])) {
    $commentId = intval($uriParts[$commentsIndex + 1]);
}

try {
    switch ($method) {
        case 'GET':
            if ($commentId) {
                // 특정 댓글 상세 조회
                handleGetComment($db, $commentId);
            } else {
                // 댓글 목록 조회
                handleGetComments($db);
            }
            break;

        case 'DELETE':
            // 댓글 삭제
            if (!$commentId) {
                Response::error('Comment ID is required', 400);
            }
            handleDeleteComment($db, $commentId);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin Comments API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 댓글 목록 조회
 */
function handleGetComments($db) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? $_GET['search'] : null;
    $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : null;
    $isDeleted = isset($_GET['is_deleted']) ? $_GET['is_deleted'] : 'false';

    $sql = "SELECT
                c.*,
                u.username,
                u.display_name,
                p.title as post_title
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN posts p ON c.post_id = p.post_id
            WHERE 1=1";

    $params = [];

    if ($search) {
        $sql .= " AND c.content LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    if ($postId) {
        $sql .= " AND c.post_id = :post_id";
        $params[':post_id'] = $postId;
    }

    if ($isDeleted !== null) {
        $sql .= " AND c.is_deleted = :is_deleted";
        $params[':is_deleted'] = $isDeleted === 'true' ? 1 : 0;
    }

    $sql .= " ORDER BY c.created_at DESC
              LIMIT :limit OFFSET :offset";

    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $comments = $stmt->fetchAll();

        // 전체 개수 조회
        $countSql = "SELECT COUNT(*) FROM comments c WHERE 1=1";
        if ($search) {
            $countSql .= " AND c.content LIKE :search";
        }
        if ($postId) {
            $countSql .= " AND c.post_id = :post_id";
        }
        if ($isDeleted !== null) {
            $countSql .= " AND c.is_deleted = :is_deleted";
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
            'comments' => $comments,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_items' => (int)$total,
                'items_per_page' => $limit
            ]
        ]);
    } catch (PDOException $e) {
        error_log('handleGetComments error: ' . $e->getMessage());
        Response::error('Failed to fetch comments', 500);
    }
}

/**
 * 특정 댓글 상세 조회
 */
function handleGetComment($db, $commentId) {
    $sql = "SELECT
                c.*,
                u.username,
                u.display_name,
                u.email,
                p.title as post_title,
                p.post_id
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN posts p ON c.post_id = p.post_id
            WHERE c.comment_id = :comment_id";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':comment_id' => $commentId]);
        $comment = $stmt->fetch();

        if (!$comment) {
            Response::error('Comment not found', 404);
        }

        Response::success($comment);
    } catch (PDOException $e) {
        error_log('handleGetComment error: ' . $e->getMessage());
        Response::error('Failed to fetch comment', 500);
    }
}

/**
 * 댓글 삭제 (소프트 삭제)
 */
function handleDeleteComment($db, $commentId) {
    $sql = "UPDATE comments SET is_deleted = true, deleted_at = NOW(), updated_at = NOW() WHERE comment_id = :comment_id";

    try {
        $stmt = $db->prepare($sql);
        $success = $stmt->execute([':comment_id' => $commentId]);

        if ($success) {
            // 게시글의 댓글 수 업데이트
            $updatePostSql = "UPDATE posts p
                             SET comment_count = (
                                 SELECT COUNT(*) FROM comments
                                 WHERE post_id = p.post_id AND is_deleted = false
                             )
                             WHERE p.post_id = (
                                 SELECT post_id FROM comments WHERE comment_id = :comment_id
                             )";
            $updateStmt = $db->prepare($updatePostSql);
            $updateStmt->execute([':comment_id' => $commentId]);

            Response::success(null, 'Comment deleted successfully');
        } else {
            Response::error('Failed to delete comment', 500);
        }
    } catch (PDOException $e) {
        error_log('handleDeleteComment error: ' . $e->getMessage());
        Response::error('Failed to delete comment', 500);
    }
}
