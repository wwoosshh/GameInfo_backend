<?php
/**
 * 이미지 업로드 API
 * Cloudinary를 사용한 이미지 업로드 처리
 */

// 에러 로깅 활성화
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/upload_errors.log');

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/CloudinaryUploader.php';

// 환경 변수 로드
$envFile = __DIR__ . '/../../config/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// POST: 이미지 업로드
if ($method === 'POST') {
    try {
        // 디버깅: 토큰 확인
        $token = Auth::getTokenFromRequest();
        error_log("Upload - Token received: " . ($token ? 'yes' : 'no'));

        $currentUser = Auth::getCurrentUser();
        error_log("Upload - Current user: " . json_encode($currentUser));

        // 관리자 권한 확인 (Auth::requireAdmin()이 인증과 권한을 모두 확인)
        $user = Auth::requireAdmin();
        $db = Database::getInstance()->getConnection();

        // 파일 업로드 확인
        if (!isset($_FILES['image'])) {
            Response::error('No image file provided', 400, 'NO_FILE');
            exit;
        }

        $file = $_FILES['image'];

        // 선택적 파라미터
        $publicId = $_POST['public_id'] ?? null;
        $folder = $_POST['folder'] ?? null;

        // Cloudinary 업로더 인스턴스 생성
        $uploader = new CloudinaryUploader();

        // 폴더가 지정된 경우 설정 변경
        if ($folder) {
            // 임시로 환경 변수 변경
            $_ENV['CLOUDINARY_FOLDER'] = $folder;
            $uploader = new CloudinaryUploader();
        }

        // 이미지 업로드
        $result = $uploader->uploadImage($file, $publicId);

        if ($result['success']) {
            // 업로드 성공 - uploaded_images 테이블에 기록
            $stmt = $db->prepare("
                INSERT INTO uploaded_images
                (uploaded_by, original_filename, cloudinary_public_id, cloudinary_url, file_size, mime_type, width, height)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $user['user_id'],
                $file['name'],
                $result['public_id'],
                $result['secure_url'],
                $file['size'],
                $file['type'],
                $result['width'] ?? null,
                $result['height'] ?? null
            ]);

            Response::success([
                'url' => $result['url'],
                'secure_url' => $result['secure_url'],
                'public_id' => $result['public_id'],
                'width' => $result['width'],
                'height' => $result['height'],
                'format' => $result['format']
            ], 'Image uploaded successfully');

        } else {
            Response::error($result['error'] ?? 'Upload failed', 500, 'UPLOAD_FAILED');
        }

    } catch (Exception $e) {
        Response::error($e->getMessage(), 500, 'SERVER_ERROR');
    }
}

// DELETE: 이미지 삭제
else if ($method === 'DELETE') {
    try {
        // 관리자 권한 확인
        $user = Auth::requireAdmin();
        $db = Database::getInstance()->getConnection();

        // public_id 파라미터 가져오기
        $data = json_decode(file_get_contents('php://input'), true);
        $publicId = $data['public_id'] ?? null;

        if (!$publicId) {
            Response::error('No public_id provided', 400, 'NO_PUBLIC_ID');
            exit;
        }

        // Cloudinary에서 이미지 삭제
        $uploader = new CloudinaryUploader();
        $result = $uploader->deleteImage($publicId);

        if ($result['success']) {
            // DB에서도 기록 삭제 (또는 소프트 삭제)
            $stmt = $db->prepare("DELETE FROM uploaded_images WHERE cloudinary_public_id = ?");
            $stmt->execute([$publicId]);

            Response::success(null, 'Image deleted successfully');
        } else {
            Response::error($result['error'] ?? 'Delete failed', 500, 'DELETE_FAILED');
        }

    } catch (Exception $e) {
        Response::error($e->getMessage(), 500, 'SERVER_ERROR');
    }
}

// GET: 업로드된 이미지 목록 조회
else if ($method === 'GET') {
    try {
        // 인증 확인
        $user = Auth::requireAuth();
        $db = Database::getInstance()->getConnection();

        // 페이지네이션
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;

        // 전체 개수 조회
        $stmt = $db->query("SELECT COUNT(*) FROM uploaded_images WHERE is_deleted = 0");
        $totalCount = $stmt->fetchColumn();

        // 이미지 목록 조회
        $stmt = $db->prepare("
            SELECT
                ui.*,
                u.username,
                u.display_name
            FROM uploaded_images ui
            LEFT JOIN users u ON ui.uploaded_by = u.user_id
            WHERE ui.is_deleted = 0
            ORDER BY ui.uploaded_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$limit, $offset]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'images' => $images,
            'pagination' => [
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ]);

    } catch (Exception $e) {
        Response::error($e->getMessage(), 500, 'SERVER_ERROR');
    }
}

else {
    Response::error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
}
