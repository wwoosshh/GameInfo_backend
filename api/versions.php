<?php
/**
 * 버전 API 엔드포인트
 *
 * GET /api/versions?game_id={id} - 특정 게임의 버전 목록
 * GET /api/versions/{version_id} - 특정 버전 상세 정보
 * GET /api/versions/{version_id}/items - 버전별 업데이트 항목
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Version.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

$db = Database::getInstance()->getConnection();
$versionModel = new Version();
$method = $_SERVER['REQUEST_METHOD'];

// URL 파싱
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));

// /api/versions/123/items 형태 파싱
// ['api', 'versions', '123', 'items']
// 인덱스: [0, 1, 2, 3]
$versionId = isset($uriParts[2]) && is_numeric($uriParts[2]) ? (int)$uriParts[2] : null;
$subResource = isset($uriParts[3]) ? $uriParts[3] : null;

try {
    switch ($method) {
        case 'GET':
            if ($versionId && $subResource === 'items') {
                // 특정 버전의 업데이트 항목 조회
                getVersionItems($db, $versionId);
            } elseif ($versionId) {
                // 특정 버전 상세 조회
                getVersionDetail($db, $versionId);
            } else {
                // 게임별 버전 목록 조회
                $gameId = isset($_GET['game_id']) ? (int)$_GET['game_id'] : null;
                if (!$gameId) {
                    Response::error('game_id is required');
                }
                getVersionsByGame($db, $gameId);
            }
            break;

        case 'POST':
            // 관리자 권한 필요
            Auth::requireAdmin();

            if ($versionId && $subResource === 'items') {
                // 업데이트 항목 추가
                createVersionItem($versionModel, $versionId);
            } else {
                // 새 버전 생성
                createVersion($versionModel);
            }
            break;

        case 'PUT':
            // 관리자 권한 필요
            Auth::requireAdmin();

            if (!$versionId) {
                Response::error('Version ID is required');
            }

            if ($subResource === 'items') {
                // 업데이트 항목 수정
                $itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
                if (!$itemId) {
                    Response::error('Item ID is required');
                }
                updateVersionItem($versionModel, $itemId);
            } else {
                // 버전 수정
                updateVersion($versionModel, $versionId);
            }
            break;

        case 'DELETE':
            // 관리자 권한 필요
            Auth::requireAdmin();

            if (!$versionId) {
                Response::error('Version ID is required');
            }

            if ($subResource === 'items') {
                // 업데이트 항목 삭제
                $itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
                if (!$itemId) {
                    Response::error('Item ID is required');
                }
                deleteVersionItem($versionModel, $itemId);
            } else {
                // 버전 삭제
                deleteVersion($versionModel, $versionId);
            }
            break;

        default:
            Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }
} catch (Exception $e) {
    error_log('Versions API Error: ' . $e->getMessage());
    Response::serverError('An unexpected error occurred');
}

/**
 * 게임별 버전 목록 조회
 */
function getVersionsByGame($db, $gameId) {
    $sql = "SELECT
                v.*,
                g.game_name,
                COUNT(DISTINCT vui.item_id) as total_items,
                COUNT(DISTINCT CASE WHEN vui.category = 'new_character' THEN vui.item_id END) as new_characters,
                COUNT(DISTINCT CASE WHEN vui.category = 'new_event' THEN vui.item_id END) as new_events
            FROM game_versions v
            JOIN games g ON v.game_id = g.game_id
            LEFT JOIN version_update_items vui ON v.version_id = vui.version_id
            WHERE v.game_id = :game_id
            GROUP BY v.version_id, g.game_name
            ORDER BY v.release_date DESC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':game_id' => $gameId]);
        $versions = $stmt->fetchAll();

        Response::success([
            'versions' => $versions,
            'total' => count($versions)
        ], 'Versions retrieved successfully');
    } catch (PDOException $e) {
        error_log('getVersionsByGame error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve versions');
    }
}

/**
 * 버전 상세 정보 조회
 */
function getVersionDetail($db, $versionId) {
    $sql = "SELECT
                v.*,
                g.game_name,
                g.game_name_en
            FROM game_versions v
            JOIN games g ON v.game_id = g.game_id
            WHERE v.version_id = :version_id";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':version_id' => $versionId]);
        $version = $stmt->fetch();

        if (!$version) {
            Response::notFound('Version not found');
        }

        // 통계 정보 추가
        $statsSql = "SELECT
                        category,
                        COUNT(*) as count
                     FROM version_update_items
                     WHERE version_id = :version_id
                     GROUP BY category";

        $statsStmt = $db->prepare($statsSql);
        $statsStmt->execute([':version_id' => $versionId]);
        $stats = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $version['statistics'] = $stats;

        Response::success($version, 'Version details retrieved successfully');
    } catch (PDOException $e) {
        error_log('getVersionDetail error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve version details');
    }
}

/**
 * 버전별 업데이트 항목 조회
 */
function getVersionItems($db, $versionId) {
    $category = isset($_GET['category']) ? $_GET['category'] : null;

    $sql = "SELECT vui.*
            FROM version_update_items vui
            WHERE vui.version_id = :version_id";

    if ($category) {
        $sql .= " AND vui.category = :category";
    }

    $sql .= " ORDER BY vui.is_featured DESC, vui.sort_order ASC, vui.item_id ASC";

    try {
        $stmt = $db->prepare($sql);
        $params = [':version_id' => $versionId];
        if ($category) {
            $params[':category'] = $category;
        }
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // 카테고리별로 그룹화
        $grouped = [];
        foreach ($items as $item) {
            $cat = $item['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $item;
        }

        Response::success([
            'items' => $items,
            'grouped' => $grouped,
            'total' => count($items)
        ], 'Version items retrieved successfully');
    } catch (PDOException $e) {
        error_log('getVersionItems error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve version items');
    }
}

/**
 * 버전 생성
 */
function createVersion($versionModel) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['game_id']) || empty($input['version_number']) || empty($input['release_date'])) {
        Response::validationError('Required fields are missing', [
            'game_id' => empty($input['game_id']) ? 'This field is required' : null,
            'version_number' => empty($input['version_number']) ? 'This field is required' : null,
            'release_date' => empty($input['release_date']) ? 'This field is required' : null
        ]);
    }

    $versionId = $versionModel->create($input);

    if (!$versionId) {
        Response::serverError('Failed to create version');
    }

    $version = $versionModel->getById($versionId);
    Response::success($version, 'Version created successfully', 201);
}

/**
 * 버전 수정
 */
function updateVersion($versionModel, $versionId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['version_number']) || empty($input['release_date'])) {
        Response::validationError('Required fields are missing', [
            'version_number' => empty($input['version_number']) ? 'This field is required' : null,
            'release_date' => empty($input['release_date']) ? 'This field is required' : null
        ]);
    }

    $success = $versionModel->update($versionId, $input);

    if (!$success) {
        Response::serverError('Failed to update version');
    }

    $version = $versionModel->getById($versionId);
    Response::success($version, 'Version updated successfully');
}

/**
 * 버전 삭제
 */
function deleteVersion($versionModel, $versionId) {
    $success = $versionModel->delete($versionId);

    if (!$success) {
        Response::serverError('Failed to delete version');
    }

    Response::success(null, 'Version deleted successfully');
}

/**
 * 업데이트 항목 생성
 */
function createVersionItem($versionModel, $versionId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['category']) || empty($input['item_name'])) {
        Response::validationError('Required fields are missing', [
            'category' => empty($input['category']) ? 'This field is required' : null,
            'item_name' => empty($input['item_name']) ? 'This field is required' : null
        ]);
    }

    $input['version_id'] = $versionId;
    $itemId = $versionModel->addItem($input);

    if (!$itemId) {
        Response::serverError('Failed to create version item');
    }

    Response::success(['item_id' => $itemId], 'Version item created successfully', 201);
}

/**
 * 업데이트 항목 수정
 */
function updateVersionItem($versionModel, $itemId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['category']) || empty($input['item_name'])) {
        Response::validationError('Required fields are missing', [
            'category' => empty($input['category']) ? 'This field is required' : null,
            'item_name' => empty($input['item_name']) ? 'This field is required' : null
        ]);
    }

    $success = $versionModel->updateItem($itemId, $input);

    if (!$success) {
        Response::serverError('Failed to update version item');
    }

    Response::success(null, 'Version item updated successfully');
}

/**
 * 업데이트 항목 삭제
 */
function deleteVersionItem($versionModel, $itemId) {
    $success = $versionModel->deleteItem($itemId);

    if (!$success) {
        Response::serverError('Failed to delete version item');
    }

    Response::success(null, 'Version item deleted successfully');
}
