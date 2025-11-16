<?php
/**
 * 게임 API 엔드포인트
 *
 * GET /api/games - 게임 목록 조회
 * GET /api/games/{id} - 게임 상세 조회
 * POST /api/games - 게임 추가 (관리자)
 * PUT /api/games/{id} - 게임 수정 (관리자)
 * DELETE /api/games/{id} - 게임 삭제 (관리자)
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../models/Game.php';
require_once __DIR__ . '/../utils/Response.php';

$gameModel = new Game();
$method = $_SERVER['REQUEST_METHOD'];

// URL에서 게임 ID 추출
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', trim($requestUri, '/'));
$gameId = isset($uriParts[3]) ? (int)$uriParts[3] : null;

try {
    switch ($method) {
        case 'GET':
            if ($gameId) {
                // 특정 게임 조회
                $game = $gameModel->getById($gameId);

                if ($game) {
                    Response::success($game, 'Game retrieved successfully');
                } else {
                    Response::notFound('Game not found');
                }
            } else {
                // 게임 목록 조회
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $withVersions = isset($_GET['with_versions']) && $_GET['with_versions'] === 'true';

                $filters = [
                    'platform' => $_GET['platform'] ?? null,
                    'genre' => $_GET['genre'] ?? null,
                    'search' => $_GET['search'] ?? null
                ];

                // N+1 쿼리 방지: with_versions 파라미터가 true면 버전 정보 포함
                if ($withVersions) {
                    $result = $gameModel->getAllWithLatestVersion($page, $limit, $filters);
                } else {
                    $result = $gameModel->getAll($page, $limit, $filters);
                }

                if ($result !== false) {
                    Response::success($result, 'Games retrieved successfully');
                } else {
                    Response::serverError('Failed to retrieve games');
                }
            }
            break;

        case 'POST':
            // 게임 추가 (인증 필요 - 실제로는 토큰 검증 필요)
            $input = json_decode(file_get_contents('php://input'), true);

            // 유효성 검사
            if (empty($input['game_name'])) {
                Response::validationError('Game name is required', [
                    'game_name' => 'This field is required'
                ]);
            }

            $gameId = $gameModel->create($input);

            if ($gameId) {
                $newGame = $gameModel->getById($gameId);
                Response::success($newGame, 'Game created successfully', 201);
            } else {
                Response::serverError('Failed to create game');
            }
            break;

        case 'PUT':
            // 게임 수정 (인증 필요)
            if (!$gameId) {
                Response::error('Game ID is required');
            }

            $input = json_decode(file_get_contents('php://input'), true);

            // 유효성 검사
            if (empty($input['game_name'])) {
                Response::validationError('Game name is required', [
                    'game_name' => 'This field is required'
                ]);
            }

            $success = $gameModel->update($gameId, $input);

            if ($success) {
                $updatedGame = $gameModel->getById($gameId);
                Response::success($updatedGame, 'Game updated successfully');
            } else {
                Response::serverError('Failed to update game');
            }
            break;

        case 'DELETE':
            // 게임 삭제 (인증 필요)
            if (!$gameId) {
                Response::error('Game ID is required');
            }

            $success = $gameModel->delete($gameId);

            if ($success) {
                Response::success(null, 'Game deleted successfully');
            } else {
                Response::serverError('Failed to delete game');
            }
            break;

        default:
            Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    Response::serverError('An unexpected error occurred');
}
