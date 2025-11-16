<?php
/**
 * PostBookmark 모델 클래스
 */

require_once __DIR__ . '/../config/database.php';

class PostBookmark {
    private $db;
    private $table = 'post_bookmarks';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 게시글 북마크 추가
     */
    public function bookmark($postId, $userId) {
        // 이미 북마크했는지 확인
        if ($this->hasBookmarked($postId, $userId)) {
            return false;
        }

        $sql = "INSERT INTO {$this->table} (post_id, user_id) VALUES (:post_id, :user_id)";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId
            ]);
        } catch (PDOException $e) {
            error_log('PostBookmark::bookmark - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 게시글 북마크 취소
     */
    public function unbookmark($postId, $userId) {
        if (!$this->hasBookmarked($postId, $userId)) {
            return false;
        }

        $sql = "DELETE FROM {$this->table} WHERE post_id = :post_id AND user_id = :user_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId
            ]);
        } catch (PDOException $e) {
            error_log('PostBookmark::unbookmark - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 사용자가 게시글을 북마크했는지 확인
     */
    public function hasBookmarked($postId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}
                WHERE post_id = :post_id AND user_id = :user_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId
            ]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log('PostBookmark::hasBookmarked - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 사용자의 북마크 목록 조회
     */
    public function getUserBookmarks($userId, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT p.*,
                    u.username,
                    u.display_name,
                    u.avatar_url,
                    g.game_name,
                    pb.created_at as bookmarked_at
                FROM {$this->table} pb
                JOIN posts p ON pb.post_id = p.post_id
                JOIN users u ON p.user_id = u.user_id
                LEFT JOIN games g ON p.game_id = g.game_id
                WHERE pb.user_id = :user_id AND p.is_deleted = false
                ORDER BY pb.created_at DESC
                LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('PostBookmark::getUserBookmarks - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 여러 게시글에 대한 사용자의 북마크 여부 확인
     */
    public function getUserBookmarksForPosts($postIds, $userId) {
        if (empty($postIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $sql = "SELECT post_id FROM {$this->table}
                WHERE post_id IN ($placeholders) AND user_id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $params = array_merge($postIds, [$userId]);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $results;
        } catch (PDOException $e) {
            error_log('PostBookmark::getUserBookmarksForPosts - ' . $e->getMessage());
            return [];
        }
    }
}
