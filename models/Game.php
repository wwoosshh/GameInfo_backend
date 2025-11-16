<?php
/**
 * Game 모델 클래스
 *
 * 게임 정보 관련 데이터베이스 작업을 처리합니다.
 */

require_once __DIR__ . '/../config/database.php';

class Game {
    private $db;
    private $table = 'games';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 모든 게임 목록 조회 (페이지네이션)
     *
     * @param int $page 페이지 번호
     * @param int $limit 페이지당 항목 수
     * @param array $filters 필터 조건
     * @return array
     */
    public function getAll($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;

        // 기본 쿼리
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1";
        $params = [];

        // 필터 적용
        if (!empty($filters['platform'])) {
            $sql .= " AND platform = :platform";
            $params[':platform'] = $filters['platform'];
        }

        if (!empty($filters['genre'])) {
            $sql .= " AND genre = :genre";
            $params[':genre'] = $filters['genre'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (game_name LIKE :search OR game_name_en LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // 정렬 및 페이지네이션
        $sql .= " ORDER BY game_name ASC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);

            // 파라미터 바인딩
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

            $stmt->execute();
            $games = $stmt->fetchAll();

            // 전체 개수 조회
            $totalCount = $this->getTotalCount($filters);

            return [
                'games' => $games,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalCount / $limit),
                    'total_items' => $totalCount,
                    'items_per_page' => $limit
                ]
            ];
        } catch (PDOException $e) {
            error_log('Game::getAll - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 모든 게임 목록 조회 with 최신 버전 정보 (N+1 쿼리 방지)
     *
     * @param int $page 페이지 번호
     * @param int $limit 페이지당 항목 수
     * @param array $filters 필터 조건
     * @return array
     */
    public function getAllWithLatestVersion($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;

        // JOIN으로 최신 버전 정보를 한 번에 가져오기
        $sql = "SELECT g.*,
                    gv.version_id,
                    gv.version_number,
                    gv.version_name,
                    gv.release_date,
                    gv.is_current,
                    (SELECT COUNT(*) FROM version_update_items vui
                     WHERE vui.version_id = gv.version_id AND vui.category = 'new_character') as new_characters,
                    (SELECT COUNT(*) FROM version_update_items vui
                     WHERE vui.version_id = gv.version_id AND vui.category = 'new_event') as new_events,
                    (SELECT COUNT(*) FROM version_update_items vui
                     WHERE vui.version_id = gv.version_id) as total_items
                FROM {$this->table} g
                LEFT JOIN game_versions gv ON g.game_id = gv.game_id
                    AND gv.version_id = (
                        SELECT version_id
                        FROM game_versions
                        WHERE game_id = g.game_id
                        ORDER BY release_date DESC
                        LIMIT 1
                    )
                WHERE g.is_active = 1";
        $params = [];

        // 필터 적용
        if (!empty($filters['platform'])) {
            $sql .= " AND g.platform = :platform";
            $params[':platform'] = $filters['platform'];
        }

        if (!empty($filters['genre'])) {
            $sql .= " AND g.genre = :genre";
            $params[':genre'] = $filters['genre'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (g.game_name LIKE :search OR g.game_name_en LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // 정렬 및 페이지네이션
        $sql .= " ORDER BY g.game_name ASC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);

            // 파라미터 바인딩
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

            $stmt->execute();
            $games = $stmt->fetchAll();

            // 전체 개수 조회
            $totalCount = $this->getTotalCount($filters);

            return [
                'games' => $games,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalCount / $limit),
                    'total_items' => $totalCount,
                    'items_per_page' => $limit
                ]
            ];
        } catch (PDOException $e) {
            error_log('Game::getAllWithLatestVersion - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 게임 ID로 조회
     *
     * @param int $gameId
     * @return array|false
     */
    public function getById($gameId) {
        $sql = "SELECT * FROM {$this->table} WHERE game_id = :game_id AND is_active = 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Game::getById - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 새 게임 추가
     *
     * @param array $data
     * @return int|false 생성된 게임 ID 또는 false
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (game_name, game_name_en, developer, publisher, genre, platform,
                 official_website, description, thumbnail_url, release_date)
                VALUES
                (:game_name, :game_name_en, :developer, :publisher, :genre, :platform,
                 :official_website, :description, :thumbnail_url, :release_date)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':game_name' => $data['game_name'],
                ':game_name_en' => $data['game_name_en'] ?? null,
                ':developer' => $data['developer'] ?? null,
                ':publisher' => $data['publisher'] ?? null,
                ':genre' => $data['genre'] ?? null,
                ':platform' => $data['platform'] ?? null,
                ':official_website' => $data['official_website'] ?? null,
                ':description' => $data['description'] ?? null,
                ':thumbnail_url' => $data['thumbnail_url'] ?? null,
                ':release_date' => $data['release_date'] ?? null
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Game::create - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 게임 정보 업데이트
     *
     * @param int $gameId
     * @param array $data
     * @return bool
     */
    public function update($gameId, $data) {
        $sql = "UPDATE {$this->table} SET
                game_name = :game_name,
                game_name_en = :game_name_en,
                developer = :developer,
                publisher = :publisher,
                genre = :genre,
                platform = :platform,
                official_website = :official_website,
                description = :description,
                thumbnail_url = :thumbnail_url,
                release_date = :release_date,
                updated_at = CURRENT_TIMESTAMP
                WHERE game_id = :game_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':game_id' => $gameId,
                ':game_name' => $data['game_name'],
                ':game_name_en' => $data['game_name_en'] ?? null,
                ':developer' => $data['developer'] ?? null,
                ':publisher' => $data['publisher'] ?? null,
                ':genre' => $data['genre'] ?? null,
                ':platform' => $data['platform'] ?? null,
                ':official_website' => $data['official_website'] ?? null,
                ':description' => $data['description'] ?? null,
                ':thumbnail_url' => $data['thumbnail_url'] ?? null,
                ':release_date' => $data['release_date'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('Game::update - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 게임 삭제 (소프트 삭제)
     *
     * @param int $gameId
     * @return bool
     */
    public function delete($gameId) {
        $sql = "UPDATE {$this->table} SET is_active = 0 WHERE game_id = :game_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':game_id' => $gameId]);
        } catch (PDOException $e) {
            error_log('Game::delete - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 전체 게임 개수 조회
     *
     * @param array $filters
     * @return int
     */
    private function getTotalCount($filters = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE is_active = 1";
        $params = [];

        if (!empty($filters['platform'])) {
            $sql .= " AND platform = :platform";
            $params[':platform'] = $filters['platform'];
        }

        if (!empty($filters['genre'])) {
            $sql .= " AND genre = :genre";
            $params[':genre'] = $filters['genre'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (game_name LIKE :search OR game_name_en LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['count'];
        } catch (PDOException $e) {
            error_log('Game::getTotalCount - ' . $e->getMessage());
            return 0;
        }
    }
}
