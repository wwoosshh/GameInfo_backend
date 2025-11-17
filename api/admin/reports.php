<?php
/**
 * Admin Reports API
 * 관리자 신고 관리 API
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
$reportId = null;

$reportsIndex = array_search('reports', $uriParts);
if ($reportsIndex !== false && isset($uriParts[$reportsIndex + 1])) {
    $reportId = intval($uriParts[$reportsIndex + 1]);
}

try {
    switch ($method) {
        case 'GET':
            if ($reportId) {
                // 특정 신고 상세 조회
                handleGetReport($db, $reportId);
            } else {
                // 신고 목록 조회
                handleGetReports($db);
            }
            break;

        case 'PUT':
            // 신고 처리 (승인, 거부 등)
            if (!$reportId) {
                Response::error('Report ID is required', 400);
            }
            handleUpdateReport($db, $reportId, $user['user_id']);
            break;

        case 'DELETE':
            // 신고 삭제
            if (!$reportId) {
                Response::error('Report ID is required', 400);
            }
            handleDeleteReport($db, $reportId);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin Reports API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 신고 목록 조회
 */
function handleGetReports($db) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $reportedType = isset($_GET['reported_type']) ? $_GET['reported_type'] : null;

    $sql = "SELECT
                r.*,
                reporter.username as reporter_username,
                reporter.display_name as reporter_display_name,
                reviewer.username as reviewer_username,
                reviewer.display_name as reviewer_display_name,
                CASE
                    WHEN r.reported_type = 'post' THEN (SELECT title FROM posts WHERE post_id = r.reported_id)
                    WHEN r.reported_type = 'comment' THEN (SELECT content FROM comments WHERE comment_id = r.reported_id)
                    ELSE NULL
                END as reported_content
            FROM reports r
            LEFT JOIN users reporter ON r.reporter_user_id = reporter.user_id
            LEFT JOIN users reviewer ON r.reviewed_by = reviewer.user_id
            WHERE 1=1";

    $params = [];

    if ($status) {
        $sql .= " AND r.status = :status";
        $params[':status'] = $status;
    }

    if ($reportedType) {
        $sql .= " AND r.reported_type = :reported_type";
        $params[':reported_type'] = $reportedType;
    }

    $sql .= " ORDER BY
                CASE r.status
                    WHEN 'pending' THEN 1
                    WHEN 'approved' THEN 2
                    WHEN 'rejected' THEN 3
                    ELSE 4
                END,
                r.created_at DESC
              LIMIT :limit OFFSET :offset";

    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $reports = $stmt->fetchAll();

        // 전체 개수 조회
        $countSql = "SELECT COUNT(*) FROM reports r WHERE 1=1";
        if ($status) {
            $countSql .= " AND r.status = :status";
        }
        if ($reportedType) {
            $countSql .= " AND r.reported_type = :reported_type";
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
            'reports' => $reports,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_items' => (int)$total,
                'items_per_page' => $limit
            ]
        ]);
    } catch (PDOException $e) {
        error_log('handleGetReports error: ' . $e->getMessage());
        Response::error('Failed to fetch reports', 500);
    }
}

/**
 * 특정 신고 상세 조회
 */
function handleGetReport($db, $reportId) {
    $sql = "SELECT
                r.*,
                reporter.username as reporter_username,
                reporter.display_name as reporter_display_name,
                reporter.email as reporter_email,
                reviewer.username as reviewer_username,
                reviewer.display_name as reviewer_display_name,
                CASE
                    WHEN r.reported_type = 'post' THEN (
                        SELECT json_build_object(
                            'post_id', post_id,
                            'title', title,
                            'content', content,
                            'user_id', user_id
                        )::text FROM posts WHERE post_id = r.reported_id
                    )
                    WHEN r.reported_type = 'comment' THEN (
                        SELECT json_build_object(
                            'comment_id', comment_id,
                            'content', content,
                            'user_id', user_id,
                            'post_id', post_id
                        )::text FROM comments WHERE comment_id = r.reported_id
                    )
                    ELSE NULL
                END as reported_item
            FROM reports r
            LEFT JOIN users reporter ON r.reporter_user_id = reporter.user_id
            LEFT JOIN users reviewer ON r.reviewed_by = reviewer.user_id
            WHERE r.report_id = :report_id";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':report_id' => $reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            Response::error('Report not found', 404);
        }

        // reported_item을 JSON으로 파싱
        if ($report['reported_item']) {
            $report['reported_item'] = json_decode($report['reported_item'], true);
        }

        Response::success($report);
    } catch (PDOException $e) {
        error_log('handleGetReport error: ' . $e->getMessage());
        Response::error('Failed to fetch report', 500);
    }
}

/**
 * 신고 처리
 */
function handleUpdateReport($db, $reportId, $reviewerId) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['status'])) {
        Response::error('Status is required', 400);
    }

    $allowedStatuses = ['pending', 'approved', 'rejected'];
    if (!in_array($data['status'], $allowedStatuses)) {
        Response::error('Invalid status', 400);
    }

    $sql = "UPDATE reports
            SET status = :status,
                reviewed_by = :reviewed_by,
                reviewed_at = NOW()
            WHERE report_id = :report_id";

    try {
        // 신고 처리
        $stmt = $db->prepare($sql);
        $success = $stmt->execute([
            ':status' => $data['status'],
            ':reviewed_by' => $reviewerId,
            ':report_id' => $reportId
        ]);

        if (!$success) {
            Response::error('Failed to update report', 500);
        }

        // 신고가 승인된 경우, 신고된 컨텐츠 처리
        if ($data['status'] === 'approved') {
            // 신고 정보 조회
            $reportStmt = $db->prepare("SELECT reported_type, reported_id FROM reports WHERE report_id = :report_id");
            $reportStmt->execute([':report_id' => $reportId]);
            $report = $reportStmt->fetch();

            if ($report) {
                if ($report['reported_type'] === 'post') {
                    // 게시글 삭제
                    $deletePostSql = "UPDATE posts SET is_deleted = true, deleted_at = NOW() WHERE post_id = :reported_id";
                    $deleteStmt = $db->prepare($deletePostSql);
                    $deleteStmt->execute([':reported_id' => $report['reported_id']]);
                } elseif ($report['reported_type'] === 'comment') {
                    // 댓글 삭제
                    $deleteCommentSql = "UPDATE comments SET is_deleted = true, deleted_at = NOW() WHERE comment_id = :reported_id";
                    $deleteStmt = $db->prepare($deleteCommentSql);
                    $deleteStmt->execute([':reported_id' => $report['reported_id']]);
                }
            }
        }

        // 수정된 신고 정보 반환
        handleGetReport($db, $reportId);
    } catch (PDOException $e) {
        error_log('handleUpdateReport error: ' . $e->getMessage());
        Response::error('Failed to update report', 500);
    }
}

/**
 * 신고 삭제
 */
function handleDeleteReport($db, $reportId) {
    $sql = "DELETE FROM reports WHERE report_id = :report_id";

    try {
        $stmt = $db->prepare($sql);
        $success = $stmt->execute([':report_id' => $reportId]);

        if ($success) {
            Response::success(null, 'Report deleted successfully');
        } else {
            Response::error('Failed to delete report', 500);
        }
    } catch (PDOException $e) {
        error_log('handleDeleteReport error: ' . $e->getMessage());
        Response::error('Failed to delete report', 500);
    }
}
