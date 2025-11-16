<?php
/**
 * Post 모델 클래스
 */

require_once __DIR__ . '/../config/database.php';

class Post {
    private $db;
    private $table = 'posts';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 게시글 목록 조회 (페이지네이션)
     */
    public function getAll($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT p.*,
                    u.username,
                    u.display_name,
                    u.avatar_url,
                    g.game_name
                FROM {$this->table} p
                JOIN users u ON p.user_id = u.user_id
                LEFT JOIN games g ON p.game_id = g.game_id
                WHERE p.is_deleted = false";

        $params = [];

        // 카테고리 필터
        if (!empty($filters['category'])) {
            $sql .= " AND p.category = :category";
            $params[':category'] = $filters['category'];
        }

        // 게임 필터
        if (!empty($filters['game_id'])) {
            $sql .= " AND p.game_id = :game_id";
            $params[':game_id'] = $filters['game_id'];
        }

        // 검색
        if (!empty($filters['search'])) {
            $sql .= " AND (p.title LIKE :search OR p.content LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // 정렬
        if (!empty($filters['pinned_first'])) {
            $sql .= " ORDER BY p.is_pinned DESC, p.created_at DESC";
        } else {
            $sql .= " ORDER BY p.created_at DESC";
        }

        $sql .= " LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            $posts = $stmt->fetchAll();

            return [
                'posts' => $posts,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($this->getTotalCount($filters) / $limit),
                    'total_items' => $this->getTotalCount($filters),
                    'items_per_page' => $limit
                ]
            ];
        } catch (PDOException $e) {
            error_log('Post::getAll - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 게시글 상세 조회
     */
    public function getById($postId) {
        $sql = "SELECT p.*,
                    u.username,
                    u.display_name,
                    u.avatar_url,
                    g.game_name
                FROM {$this->table} p
                JOIN users u ON p.user_id = u.user_id
                LEFT JOIN games g ON p.game_id = g.game_id
                WHERE p.post_id = :post_id AND p.is_deleted = false";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':post_id' => $postId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Post::getById - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 게시글 생성
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (user_id, title, content, category, game_id, tags)
                VALUES
                (:user_id, :title, :content, :category, :game_id, :tags)";

        try {
            // PostgreSQL ARRAY 형식으로 변환
            $tags = null;
            if (isset($data['tags']) && is_array($data['tags']) && !empty($data['tags'])) {
                $tags = '{' . implode(',', array_map(function($tag) {
                    return '"' . str_replace('"', '\"', $tag) . '"';
                }, $data['tags'])) . '}';
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':title' => $data['title'],
                ':content' => $data['content'],
                ':category' => $data['category'] ?? 'discussion',
                ':game_id' => $data['game_id'] ?? null,
                ':tags' => $tags
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Post::create - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 게시글 수정
     */
    public function update($postId, $data) {
        $sql = "UPDATE {$this->table} SET
                title = :title,
                content = :content,
                category = :category,
                game_id = :game_id,
                tags = :tags,
                updated_at = CURRENT_TIMESTAMP
                WHERE post_id = :post_id";

        try {
            // PostgreSQL ARRAY 형식으로 변환
            $tags = null;
            if (isset($data['tags']) && is_array($data['tags']) && !empty($data['tags'])) {
                $tags = '{' . implode(',', array_map(function($tag) {
                    return '"' . str_replace('"', '\"', $tag) . '"';
                }, $data['tags'])) . '}';
            }

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':post_id' => $postId,
                ':title' => $data['title'],
                ':content' => $data['content'],
                ':category' => $data['category'],
                ':game_id' => $data['game_id'] ?? null,
                ':tags' => $tags
            ]);
        } catch (PDOException $e) {
            error_log('Post::update - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 게시글 삭제 (소프트 삭제)
     */
    public function delete($postId) {
        $sql = "UPDATE {$this->table} SET
                is_deleted = true,
                deleted_at = CURRENT_TIMESTAMP
                WHERE post_id = :post_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':post_id' => $postId]);
        } catch (PDOException $e) {
            error_log('Post::delete - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 조회수 증가
     */
    public function incrementViewCount($postId) {
        $sql = "UPDATE {$this->table} SET view_count = view_count + 1
                WHERE post_id = :post_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':post_id' => $postId]);
        } catch (PDOException $e) {
            error_log('Post::incrementViewCount - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 전체 게시글 수
     */
    private function getTotalCount($filters = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE is_deleted = false";
        $params = [];

        if (!empty($filters['category'])) {
            $sql .= " AND category = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['game_id'])) {
            $sql .= " AND game_id = :game_id";
            $params[':game_id'] = $filters['game_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (title LIKE :search OR content LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['count'];
        } catch (PDOException $e) {
            error_log('Post::getTotalCount - ' . $e->getMessage());
            return 0;
        }
    }
}
