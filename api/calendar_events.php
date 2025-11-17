<?php
/**
 * Calendar Events API
 * 사용자 캘린더 일정 관련 API 엔드포인트
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../models/CalendarEvent.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$eventModel = new CalendarEvent();

// URL 파라미터 파싱 (calendar-events/{id} 형태)
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$eventId = null;

// API 경로에서 calendar-events 이후의 ID 추출
$eventsIndex = array_search('calendar-events', $uriParts);
if ($eventsIndex !== false && isset($uriParts[$eventsIndex + 1])) {
    $eventId = intval($uriParts[$eventsIndex + 1]);
}

try {
    switch ($method) {
        case 'GET':
            if ($eventId) {
                // 특정 일정 조회
                handleGetEventById($eventModel, $eventId);
            } else {
                // 일정 목록 조회
                handleGetEvents($eventModel);
            }
            break;

        case 'POST':
            // 일정 생성 (인증 필요)
            handleCreateEvent($eventModel);
            break;

        case 'PUT':
            // 일정 수정 (인증 + 소유권 필요)
            if (!$eventId) {
                Response::error('Event ID is required', 400);
            }
            handleUpdateEvent($eventModel, $eventId);
            break;

        case 'DELETE':
            // 일정 삭제 (인증 + 소유권 필요)
            if (!$eventId) {
                Response::error('Event ID is required', 400);
            }
            handleDeleteEvent($eventModel, $eventId);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Calendar Events API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

/**
 * 일정 목록 조회
 */
function handleGetEvents($eventModel) {
    // 인증 확인 (자신의 일정만 볼 수 있음)
    $userId = Auth::getUserId();
    if (!$userId) {
        Response::error('Unauthorized', 401);
    }

    // 날짜 범위 필터링 (선택적)
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

    $result = $eventModel->getUserEvents($userId, $startDate, $endDate);

    if ($result === false) {
        Response::error('Failed to fetch events', 500);
    }

    Response::success($result);
}

/**
 * 특정 일정 조회
 */
function handleGetEventById($eventModel, $eventId) {
    // 인증 확인
    $userId = Auth::getUserId();
    if (!$userId) {
        Response::error('Unauthorized', 401);
    }

    $event = $eventModel->getById($eventId);

    if (!$event) {
        Response::error('Event not found', 404);
    }

    // 본인의 일정인지 확인
    if ($event['user_id'] != $userId) {
        Response::error('Forbidden', 403);
    }

    Response::success($event);
}

/**
 * 일정 생성
 */
function handleCreateEvent($eventModel) {
    // 인증 확인
    $userId = Auth::getUserId();
    if (!$userId) {
        Response::error('Unauthorized', 401);
    }

    // 요청 데이터 파싱
    $data = json_decode(file_get_contents('php://input'), true);

    // 디버깅: 받은 데이터 로깅
    error_log('Calendar Event Create - Received data: ' . json_encode($data));

    // 필수 필드 검증
    if (empty($data['event_title']) || empty($data['event_date'])) {
        error_log('Calendar Event Create - Missing required fields');
        Response::error('Title and date are required', 400);
    }

    // 날짜 형식 검증
    $eventDate = $data['event_date'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        Response::error('Invalid date format (expected YYYY-MM-DD)', 400);
    }

    // 시간 형식 검증 (선택적)
    $eventTime = isset($data['event_time']) && $data['event_time'] !== '' ? $data['event_time'] : null;
    if ($eventTime !== null) {
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $eventTime)) {
            Response::error('Invalid time format (expected HH:MM or HH:MM:SS)', 400);
        }
    }

    // 일정 데이터 준비
    $eventData = [
        'user_id' => $userId,
        'event_title' => trim($data['event_title']),
        'event_description' => isset($data['event_description']) && $data['event_description'] !== '' ? trim($data['event_description']) : null,
        'event_date' => $eventDate,
        'event_time' => $eventTime,
        // event_type은 생략하여 DB 기본값('personal') 사용
        'is_all_day' => isset($data['is_all_day']) ? (bool)$data['is_all_day'] : false
    ];

    error_log('Calendar Event Create - Attempting to create event: ' . json_encode($eventData));
    $eventId = $eventModel->create($eventData);

    if (!$eventId) {
        error_log('Calendar Event Create - Failed to create event in database');
        Response::error('Failed to create event', 500);
    }

    error_log('Calendar Event Create - Successfully created event with ID: ' . $eventId);

    // 생성된 일정 조회
    $event = $eventModel->getById($eventId);

    Response::success($event, 'Event created successfully', 201);
}

/**
 * 일정 수정
 */
function handleUpdateEvent($eventModel, $eventId) {
    // 인증 확인
    $userId = Auth::getUserId();
    if (!$userId) {
        Response::error('Unauthorized', 401);
    }

    // 기존 일정 조회
    $existingEvent = $eventModel->getById($eventId);
    if (!$existingEvent) {
        Response::error('Event not found', 404);
    }

    // 소유권 확인
    if ($existingEvent['user_id'] != $userId) {
        Response::error('Forbidden', 403);
    }

    // 요청 데이터 파싱
    $data = json_decode(file_get_contents('php://input'), true);

    // 업데이트할 데이터 준비
    $updateData = [];

    if (isset($data['event_title'])) {
        if (empty(trim($data['event_title']))) {
            Response::error('Title cannot be empty', 400);
        }
        $updateData['event_title'] = trim($data['event_title']);
    }

    if (isset($data['event_description'])) {
        $updateData['event_description'] = ($data['event_description'] !== '' ? trim($data['event_description']) : null);
    }

    if (isset($data['event_date'])) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['event_date'])) {
            Response::error('Invalid date format (expected YYYY-MM-DD)', 400);
        }
        $updateData['event_date'] = $data['event_date'];
    }

    if (isset($data['event_time'])) {
        $timeValue = ($data['event_time'] !== '' && $data['event_time'] !== null) ? $data['event_time'] : null;
        if ($timeValue !== null && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeValue)) {
            Response::error('Invalid time format (expected HH:MM or HH:MM:SS)', 400);
        }
        $updateData['event_time'] = $timeValue;
    }

    if (isset($data['event_type'])) {
        $updateData['event_type'] = $data['event_type'];
    }

    if (isset($data['is_all_day'])) {
        $updateData['is_all_day'] = (bool)$data['is_all_day'];
    }

    if (empty($updateData)) {
        Response::error('No data to update', 400);
    }

    $success = $eventModel->update($eventId, $updateData);

    if (!$success) {
        Response::error('Failed to update event', 500);
    }

    // 수정된 일정 조회
    $event = $eventModel->getById($eventId);

    Response::success($event, 'Event updated successfully');
}

/**
 * 일정 삭제
 */
function handleDeleteEvent($eventModel, $eventId) {
    // 인증 확인
    $userId = Auth::getUserId();
    if (!$userId) {
        Response::error('Unauthorized', 401);
    }

    // 기존 일정 조회
    $existingEvent = $eventModel->getById($eventId);
    if (!$existingEvent) {
        Response::error('Event not found', 404);
    }

    // 소유권 확인
    if ($existingEvent['user_id'] != $userId) {
        Response::error('Forbidden', 403);
    }

    $success = $eventModel->delete($eventId);

    if (!$success) {
        Response::error('Failed to delete event', 500);
    }

    Response::success(null, 'Event deleted successfully');
}
