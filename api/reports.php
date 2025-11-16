<?php
/**
 * Reports API
 * 신고 관련 API 엔드포인트
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../models/Report.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$reportModel = new Report();

// 인증 확인
$user = Auth::getCurrentUser();
if (!$user) {
    Response::error('Authentication required', 401);
}

// URL 파라미터 파싱
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$reportId = null;

// reports/{report_id} 형태에서 report_id 추출
$reportsIndex = array_search('reports', $uriParts);
if ($reportsIndex !== false && isset($uriParts[$reportsIndex + 1])) {
    $reportId = intval($uriParts[$reportsIndex + 1]);
}

try {
    switch ($method) {
        case 'GET':
            if ($reportId) {
                // 신고 상세 조회 (관리자만)
                handleGetReportById($reportModel, $reportId, $user);
            } else {
                // 신고 목록 조회 (관리자만)
                handleGetReports($reportModel, $user);
            }
            break;

        case 'POST':
            // 신고 생성
            handleCreateReport($reportModel, $user);
            break;

        case 'PUT':
            // 신고 처리 (관리자만)
            if (!$reportId) {
                Response::error('Report ID is required', 400);
            }
            handleUpdateReport($reportModel, $reportId, $user);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Reports API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 신고 생성
 */
function handleCreateReport($reportModel, $user) {
    $input = json_decode(file_get_contents('php://input'), true);

    // 필수 필드 검증
    if (empty($input['reported_type']) || empty($input['reported_id']) || empty($input['reason'])) {
        Response::error('reported_type, reported_id, and reason are required', 400);
    }

    // reported_type 검증
    if (!in_array($input['reported_type'], ['post', 'comment'])) {
        Response::error('Invalid reported_type. Must be "post" or "comment"', 400);
    }

    // 이미 신고했는지 확인
    if ($reportModel->hasReported($user['user_id'], $input['reported_type'], $input['reported_id'])) {
        Response::error('You have already reported this content', 400);
    }

    // 신고 데이터 준비
    $reportData = [
        'reporter_user_id' => $user['user_id'],
        'reported_type' => $input['reported_type'],
        'reported_id' => intval($input['reported_id']),
        'reason' => trim($input['reason']),
        'description' => isset($input['description']) ? trim($input['description']) : null
    ];

    $reportId = $reportModel->create($reportData);

    if (!$reportId) {
        Response::error('Failed to create report', 500);
    }

    Response::success([
        'report_id' => $reportId
    ], 'Report created successfully', 201);
}

/**
 * 신고 목록 조회 (관리자만)
 */
function handleGetReports($reportModel, $user) {
    // 관리자 권한 확인
    if (!Auth::isAdmin($user)) {
        Response::error('Admin access required', 403);
    }

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;

    $filters = [];
    if (isset($_GET['status'])) {
        $filters['status'] = $_GET['status'];
    }
    if (isset($_GET['reported_type'])) {
        $filters['reported_type'] = $_GET['reported_type'];
    }

    $result = $reportModel->getAll($page, $limit, $filters);

    if ($result === false) {
        Response::error('Failed to fetch reports', 500);
    }

    Response::success($result, 'Reports retrieved successfully');
}

/**
 * 신고 상세 조회 (관리자만)
 */
function handleGetReportById($reportModel, $reportId, $user) {
    // 관리자 권한 확인
    if (!Auth::isAdmin($user)) {
        Response::error('Admin access required', 403);
    }

    $report = $reportModel->getById($reportId);

    if (!$report) {
        Response::error('Report not found', 404);
    }

    Response::success($report, 'Report retrieved successfully');
}

/**
 * 신고 처리 (관리자만)
 */
function handleUpdateReport($reportModel, $reportId, $user) {
    // 관리자 권한 확인
    if (!Auth::isAdmin($user)) {
        Response::error('Admin access required', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // 필수 필드 검증
    if (empty($input['status'])) {
        Response::error('status is required', 400);
    }

    // status 검증
    if (!in_array($input['status'], ['pending', 'approved', 'rejected'])) {
        Response::error('Invalid status. Must be "pending", "approved", or "rejected"', 400);
    }

    $success = $reportModel->updateStatus($reportId, $input['status'], $user['user_id']);

    if (!$success) {
        Response::error('Failed to update report', 500);
    }

    Response::success(null, 'Report updated successfully');
}
