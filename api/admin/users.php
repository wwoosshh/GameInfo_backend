<?php
/**
 * Admin Users API
 * 관리자 유저 관리 API
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
$userId = null;

$usersIndex = array_search('users', $uriParts);
if ($usersIndex !== false && isset($uriParts[$usersIndex + 1])) {
    $userId = intval($uriParts[$usersIndex + 1]);
}

try {
    switch ($method) {
        case 'GET':
            if ($userId) {
                // 특정 유저 상세 조회
                handleGetUser($db, $userId);
            } else {
                // 유저 목록 조회
                handleGetUsers($db);
            }
            break;

        case 'PUT':
            // 유저 정보 수정 (활성화/비활성화 등)
            if (!$userId) {
                Response::error('User ID is required', 400);
            }
            handleUpdateUser($db, $userId);
            break;

        case 'DELETE':
            // 유저 삭제
            if (!$userId) {
                Response::error('User ID is required', 400);
            }
            handleDeleteUser($db, $userId);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin Users API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 유저 목록 조회
 */
function handleGetUsers($db) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? $_GET['search'] : null;
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : null;

    $sql = "SELECT
                u.user_id,
                u.username,
                u.email,
                u.display_name,
                u.is_active,
                u.created_at,
                u.last_login,
                u.post_count,
                u.comment_count,
                u.reputation_score,
                COALESCE(
                    json_agg(
                        DISTINCT jsonb_build_object(
                            'role_id', r.role_id,
                            'role_name', r.role_name
                        )
                    ) FILTER (WHERE r.role_id IS NOT NULL),
                    '[]'
                ) as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.role_id
            WHERE 1=1";

    $params = [];

    if ($search) {
        $sql .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.display_name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($isActive !== null) {
        $sql .= " AND u.is_active = :is_active";
        $params[':is_active'] = $isActive === 'true' ? 1 : 0;
    }

    $sql .= " GROUP BY u.user_id
              ORDER BY u.created_at DESC
              LIMIT :limit OFFSET :offset";

    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();

        // 각 유저의 roles를 JSON 디코딩
        foreach ($users as &$user) {
            $user['roles'] = json_decode($user['roles'], true);
        }

        // 전체 개수 조회
        $countSql = "SELECT COUNT(*) FROM users u WHERE 1=1";
        if ($search) {
            $countSql .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.display_name LIKE :search)";
        }
        if ($isActive !== null) {
            $countSql .= " AND u.is_active = :is_active";
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
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_items' => (int)$total,
                'items_per_page' => $limit
            ]
        ]);
    } catch (PDOException $e) {
        error_log('handleGetUsers error: ' . $e->getMessage());
        Response::error('Failed to fetch users', 500);
    }
}

/**
 * 특정 유저 상세 조회
 */
function handleGetUser($db, $userId) {
    $sql = "SELECT
                u.*,
                COALESCE(
                    json_agg(
                        DISTINCT jsonb_build_object(
                            'role_id', r.role_id,
                            'role_name', r.role_name,
                            'granted_at', ur.granted_at
                        )
                    ) FILTER (WHERE r.role_id IS NOT NULL),
                    '[]'
                ) as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.role_id
            WHERE u.user_id = :user_id
            GROUP BY u.user_id";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('User not found', 404);
        }

        $user['roles'] = json_decode($user['roles'], true);

        Response::success($user);
    } catch (PDOException $e) {
        error_log('handleGetUser error: ' . $e->getMessage());
        Response::error('Failed to fetch user', 500);
    }
}

/**
 * 유저 정보 수정
 */
function handleUpdateUser($db, $userId) {
    $data = json_decode(file_get_contents('php://input'), true);

    $fields = [];
    $params = [':user_id' => $userId];

    if (isset($data['is_active'])) {
        $fields[] = "is_active = :is_active";
        $params[':is_active'] = $data['is_active'] ? 1 : 0;
    }

    if (isset($data['display_name'])) {
        $fields[] = "display_name = :display_name";
        $params[':display_name'] = $data['display_name'];
    }

    if (isset($data['bio'])) {
        $fields[] = "bio = :bio";
        $params[':bio'] = $data['bio'];
    }

    if (empty($fields)) {
        Response::error('No data to update', 400);
    }

    $fields[] = "updated_at = NOW()";

    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = :user_id";

    try {
        $stmt = $db->prepare($sql);
        $success = $stmt->execute($params);

        if ($success) {
            // 수정된 유저 정보 반환
            handleGetUser($db, $userId);
        } else {
            Response::error('Failed to update user', 500);
        }
    } catch (PDOException $e) {
        error_log('handleUpdateUser error: ' . $e->getMessage());
        Response::error('Failed to update user', 500);
    }
}

/**
 * 유저 삭제 (소프트 삭제 - is_active = false)
 */
function handleDeleteUser($db, $userId) {
    $sql = "UPDATE users SET is_active = false, updated_at = NOW() WHERE user_id = :user_id";

    try {
        $stmt = $db->prepare($sql);
        $success = $stmt->execute([':user_id' => $userId]);

        if ($success) {
            Response::success(null, 'User deactivated successfully');
        } else {
            Response::error('Failed to deactivate user', 500);
        }
    } catch (PDOException $e) {
        error_log('handleDeleteUser error: ' . $e->getMessage());
        Response::error('Failed to deactivate user', 500);
    }
}
