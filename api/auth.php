<?php
/**
 * 인증 API 엔드포인트
 *
 * POST /api/auth/login - 로그인
 * POST /api/auth/register - 회원가입
 * POST /api/auth/logout - 로그아웃
 * GET /api/auth/me - 현재 사용자 정보
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';

$userModel = new User();
$method = $_SERVER['REQUEST_METHOD'];

// URL 파싱
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
// /api/auth/login -> ['api', 'auth', 'login'] -> $uriParts[2] = 'login'
$action = isset($uriParts[2]) ? $uriParts[2] : null;

try {
    switch ($method) {
        case 'POST':
            if ($action === 'login') {
                handleLogin($userModel);
            } elseif ($action === 'register') {
                handleRegister($userModel);
            } elseif ($action === 'logout') {
                handleLogout();
            } else {
                Response::error('Invalid action');
            }
            break;

        case 'GET':
            if ($action === 'me') {
                handleGetCurrentUser($userModel);
            } else {
                Response::error('Invalid action');
            }
            break;

        default:
            Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }
} catch (Exception $e) {
    error_log('Auth API Error: ' . $e->getMessage());
    Response::serverError('An unexpected error occurred');
}

/**
 * 로그인 처리
 */
function handleLogin($userModel) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['username']) || empty($input['password'])) {
        Response::validationError('Username and password are required', [
            'username' => empty($input['username']) ? 'This field is required' : null,
            'password' => empty($input['password']) ? 'This field is required' : null
        ]);
    }

    $user = $userModel->getByUsername($input['username']);

    if (!$user || !Auth::verifyPassword($input['password'], $user['password_hash'])) {
        Response::error('Invalid username or password', 'INVALID_CREDENTIALS', 401);
    }

    // 사용자 역할 조회 (RBAC)
    $roles = $userModel->getUserRoles($user['user_id']);

    // 토큰 생성 (역할 포함)
    $token = Auth::generateToken(
        $user['user_id'],
        $user['username'],
        $roles
    );

    // 마지막 로그인 시간 업데이트
    $userModel->updateLastLogin($user['user_id']);

    Response::success([
        'token' => $token,
        'user' => [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'display_name' => $user['display_name'],
            'roles' => $roles,
            'is_admin' => in_array('admin', $roles) || in_array('super_admin', $roles)
        ]
    ], 'Login successful');
}

/**
 * 회원가입 처리
 */
function handleRegister($userModel) {
    $input = json_decode(file_get_contents('php://input'), true);

    $errors = [];
    if (empty($input['username'])) {
        $errors['username'] = 'This field is required';
    }
    if (empty($input['email'])) {
        $errors['email'] = 'This field is required';
    }
    if (empty($input['password'])) {
        $errors['password'] = 'This field is required';
    }

    if (!empty($errors)) {
        Response::validationError('Validation failed', $errors);
    }

    // 중복 확인
    $existingUser = $userModel->getByUsername($input['username']);
    if ($existingUser) {
        Response::validationError('Username already exists', [
            'username' => 'This username is already taken'
        ]);
    }

    // 사용자 생성
    $userId = $userModel->create([
        'username' => $input['username'],
        'email' => $input['email'],
        'password_hash' => Auth::hashPassword($input['password']),
        'display_name' => $input['display_name'] ?? $input['username']
    ]);

    if (!$userId) {
        Response::serverError('Failed to create user');
    }

    // 기본 역할 부여 (user)
    $userModel->assignRole($userId, 'user');

    // 토큰 생성 (user 역할 포함)
    $token = Auth::generateToken($userId, $input['username'], ['user']);

    Response::success([
        'token' => $token,
        'user' => [
            'user_id' => $userId,
            'username' => $input['username'],
            'email' => $input['email'],
            'display_name' => $input['display_name'] ?? $input['username'],
            'roles' => ['user'],
            'is_admin' => false
        ]
    ], 'Registration successful', 201);
}

/**
 * 로그아웃 처리
 */
function handleLogout() {
    // 클라이언트에서 토큰 삭제하면 됨
    Response::success(null, 'Logout successful');
}

/**
 * 현재 사용자 정보 조회
 */
function handleGetCurrentUser($userModel) {
    $currentUser = Auth::requireAuth();

    $user = $userModel->getById($currentUser['user_id']);

    if (!$user) {
        Response::notFound('User not found');
    }

    Response::success([
        'user' => $user
    ], 'User information retrieved successfully');
}
