<?php
/**
 * Search API
 * 게임, 버전, 항목을 검색하여 버전 단위로 결과 반환
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

if (strlen($query) < 1) {
    Response::error('Search query is required (minimum 1 character)', 400);
}

try {
    $db = Database::getInstance()->getConnection();

    // 검색어 준비 (LIKE 패턴)
    $searchPattern = '%' . $query . '%';

    // 버전 검색 쿼리
    // 게임 이름, 버전 번호/이름, 항목 이름에서 검색하여 버전 단위로 결과 반환
    $sql = "SELECT DISTINCT
                gv.version_id,
                gv.game_id,
                gv.version_number,
                gv.version_name,
                gv.version_name_en,
                gv.release_date,
                gv.end_date,
                gv.banner_image_url,
                gv.thumbnail_url,
                gv.description,
                gv.is_current,
                g.game_name,
                g.game_name_en,
                g.thumbnail_url as game_thumbnail_url,
                -- 매칭된 항목 수 (검색어와 매칭되는 항목)
                (
                    SELECT COUNT(*)
                    FROM version_update_items vui
                    WHERE vui.version_id = gv.version_id
                    AND (
                        vui.item_name ILIKE :search_pattern
                        OR vui.item_name_en ILIKE :search_pattern2
                    )
                ) as matched_items_count
            FROM game_versions gv
            JOIN games g ON gv.game_id = g.game_id
            LEFT JOIN version_update_items vui ON gv.version_id = vui.version_id
            WHERE g.is_active = :is_active
            AND (
                -- 게임 이름 검색
                g.game_name ILIKE :search_pattern3
                OR g.game_name_en ILIKE :search_pattern4
                -- 버전 번호/이름 검색
                OR gv.version_number ILIKE :search_pattern5
                OR gv.version_name ILIKE :search_pattern6
                OR gv.version_name_en ILIKE :search_pattern7
                -- 항목 이름 검색
                OR vui.item_name ILIKE :search_pattern8
                OR vui.item_name_en ILIKE :search_pattern9
            )
            ORDER BY gv.release_date DESC, g.game_name ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':search_pattern', $searchPattern);
    $stmt->bindValue(':search_pattern2', $searchPattern);
    $stmt->bindValue(':search_pattern3', $searchPattern);
    $stmt->bindValue(':search_pattern4', $searchPattern);
    $stmt->bindValue(':search_pattern5', $searchPattern);
    $stmt->bindValue(':search_pattern6', $searchPattern);
    $stmt->bindValue(':search_pattern7', $searchPattern);
    $stmt->bindValue(':search_pattern8', $searchPattern);
    $stmt->bindValue(':search_pattern9', $searchPattern);
    $stmt->bindValue(':is_active', true, PDO::PARAM_BOOL);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $versions = $stmt->fetchAll();

    // 각 버전에 대해 매칭된 항목 정보 추가 (상위 5개만)
    foreach ($versions as &$version) {
        $itemsSql = "SELECT
                        item_id, item_name, item_name_en, category,
                        image_url, icon_url, rarity
                     FROM version_update_items
                     WHERE version_id = :version_id
                     AND (
                         item_name ILIKE :search_pattern
                         OR item_name_en ILIKE :search_pattern2
                     )
                     LIMIT 5";

        $itemsStmt = $db->prepare($itemsSql);
        $itemsStmt->bindValue(':version_id', $version['version_id'], PDO::PARAM_INT);
        $itemsStmt->bindValue(':search_pattern', $searchPattern);
        $itemsStmt->bindValue(':search_pattern2', $searchPattern);
        $itemsStmt->execute();

        $version['matched_items'] = $itemsStmt->fetchAll();
    }

    // 전체 개수 조회
    $countSql = "SELECT COUNT(DISTINCT gv.version_id)
                 FROM game_versions gv
                 JOIN games g ON gv.game_id = g.game_id
                 LEFT JOIN version_update_items vui ON gv.version_id = vui.version_id
                 WHERE g.is_active = :is_active
                 AND (
                     g.game_name ILIKE :search_pattern
                     OR g.game_name_en ILIKE :search_pattern2
                     OR gv.version_number ILIKE :search_pattern3
                     OR gv.version_name ILIKE :search_pattern4
                     OR gv.version_name_en ILIKE :search_pattern5
                     OR vui.item_name ILIKE :search_pattern6
                     OR vui.item_name_en ILIKE :search_pattern7
                 )";

    $countStmt = $db->prepare($countSql);
    $countStmt->bindValue(':search_pattern', $searchPattern);
    $countStmt->bindValue(':search_pattern2', $searchPattern);
    $countStmt->bindValue(':search_pattern3', $searchPattern);
    $countStmt->bindValue(':search_pattern4', $searchPattern);
    $countStmt->bindValue(':search_pattern5', $searchPattern);
    $countStmt->bindValue(':search_pattern6', $searchPattern);
    $countStmt->bindValue(':search_pattern7', $searchPattern);
    $countStmt->bindValue(':is_active', true, PDO::PARAM_BOOL);
    $countStmt->execute();

    $totalCount = $countStmt->fetchColumn();

    Response::success([
        'query' => $query,
        'versions' => $versions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalCount / $limit),
            'total_items' => (int)$totalCount,
            'items_per_page' => $limit
        ]
    ]);

} catch (PDOException $e) {
    error_log('Search API error: ' . $e->getMessage());
    Response::error('Search failed', 500);
}
