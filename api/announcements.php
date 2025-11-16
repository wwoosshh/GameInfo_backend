<?php
/**
 * Announcements API
 * 커뮤니티 공지사항 관련 API 엔드포인트
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$notificationModel = new Notification();

// URL 파라미터 파싱
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$announcementId = null;

// announcements/{id} 형태에서 ID 추출
$announcementsIndex = array_search('announcements', $uriParts);
if ($announcementsIndex !== false && isset($uriParts[$announcementsIndex + 1])) {
    $announcementId = intval($uriParts[$announcementsIndex + 1]);
}

try {
    switch ($method) {
        case 'GET':
            // 공지사항 조회 (모든 사용자)
            if ($announcementId) {
                handleGetAnnouncementById($notificationModel, $announcementId);
            } else {
                handleGetAnnouncements($notificationModel);
            }
            break;

        case 'POST':
            // 공지사항 생성 (관리자만)
            handleCreateAnnouncement($notificationModel);
            break;

        case 'PUT':
            // 공지사항 수정 (관리자만)
            if (!$announcementId) {
                Response::error('Announcement ID is required', 400);
            }
            handleUpdateAnnouncement($notificationModel, $announcementId);
            break;

        case 'DELETE':
            // 공지사항 삭제 (관리자만)
            if (!$announcementId) {
                Response::error('Announcement ID is required', 400);
            }
            handleDeleteAnnouncement($notificationModel, $announcementId);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Announcements API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 공지사항 목록 조회
 */
function handleGetAnnouncements($notificationModel) {
    $announcements = $notificationModel->getActiveAnnouncements();

    if ($announcements === false) {
        Response::error('Failed to fetch announcements', 500);
    }

    Response::success($announcements, 'Announcements retrieved successfully');
}

/**
 * 공지사항 상세 조회
 */
function handleGetAnnouncementById($notificationModel, $announcementId) {
    $announcement = $notificationModel->getById($announcementId);

    if (!$announcement) {
        Response::error('Announcement not found', 404);
    }

    Response::success($announcement, 'Announcement retrieved successfully');
}

/**
 * 공지사항 생성 (관리자만)
 */
function handleCreateAnnouncement($notificationModel) {
    // 인증 확인
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Authentication required', 401);
    }

    // 관리자 권한 확인
    if (!Auth::isAdmin($user)) {
        Response::error('Admin access required', 403);
    }

    // 입력 데이터 파싱
    $input = json_decode(file_get_contents('php://input'), true);

    // 필수 필드 검증
    if (empty($input['title'])) {
        Response::error('Title is required', 400);
    }

    // 공지사항 데이터 준비
    $announcementData = [
        'user_id' => $user['user_id'],
        'title' => trim($input['title']),
        'content' => isset($input['content']) ? trim($input['content']) : null,
        'link_url' => isset($input['link_url']) ? trim($input['link_url']) : null
    ];

    // 공지사항 생성
    $announcementId = $notificationModel->create($announcementData);

    if (!$announcementId) {
        Response::error('Failed to create announcement', 500);
    }

    // 생성된 공지사항 조회
    $announcement = $notificationModel->getById($announcementId);

    Response::success($announcement, 'Announcement created successfully', 201);
}

/**
 * 공지사항 수정 (관리자만)
 */
function handleUpdateAnnouncement($notificationModel, $announcementId) {
    // 인증 확인
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Authentication required', 401);
    }

    // 관리자 권한 확인
    if (!Auth::isAdmin($user)) {
        Response::error('Admin access required', 403);
    }

    // 공지사항 존재 확인
    $announcement = $notificationModel->getById($announcementId);
    if (!$announcement) {
        Response::error('Announcement not found', 404);
    }

    // 입력 데이터 파싱
    $input = json_decode(file_get_contents('php://input'), true);

    // 필수 필드 검증
    if (empty($input['title'])) {
        Response::error('Title is required', 400);
    }

    // 공지사항 데이터 준비
    $announcementData = [
        'title' => trim($input['title']),
        'content' => isset($input['content']) ? trim($input['content']) : null,
        'link_url' => isset($input['link_url']) ? trim($input['link_url']) : null
    ];

    // 공지사항 수정
    $success = $notificationModel->update($announcementId, $announcementData);

    if (!$success) {
        Response::error('Failed to update announcement', 500);
    }

    // 수정된 공지사항 조회
    $updatedAnnouncement = $notificationModel->getById($announcementId);

    Response::success($updatedAnnouncement, 'Announcement updated successfully');
}

/**
 * 공지사항 삭제 (관리자만)
 */
function handleDeleteAnnouncement($notificationModel, $announcementId) {
    // 인증 확인
    $user = Auth::getCurrentUser();
    if (!$user) {
        Response::error('Authentication required', 401);
    }

    // 관리자 권한 확인
    if (!Auth::isAdmin($user)) {
        Response::error('Admin access required', 403);
    }

    // 공지사항 존재 확인
    $announcement = $notificationModel->getById($announcementId);
    if (!$announcement) {
        Response::error('Announcement not found', 404);
    }

    // 공지사항 삭제
    $success = $notificationModel->delete($announcementId);

    if (!$success) {
        Response::error('Failed to delete announcement', 500);
    }

    Response::success(null, 'Announcement deleted successfully');
}
