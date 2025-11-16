<?php
/**
 * PostLike 모델 클래스
 */

require_once __DIR__ . '/../config/database.php';

class PostLike {
    private $db;
    private $table = 'post_likes';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 게시글에 좋아요 추가
     */
    public function like($postId, $userId) {
        // 이미 좋아요를 눌렀는지 확인
        if ($this->hasLiked($postId, $userId)) {
            return false;
        }

        $sql = "INSERT INTO {$this->table} (post_id, user_id) VALUES (:post_id, :user_id)";

        try {
            $this->db->beginTransaction();

            // 좋아요 추가
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId
            ]);

            // posts 테이블의 like_count 증가
            $updateSql = "UPDATE posts SET like_count = like_count + 1 WHERE post_id = :post_id";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([':post_id' => $postId]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('PostLike::like - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 게시글 좋아요 취소
     */
    public function unlike($postId, $userId) {
        if (!$this->hasLiked($postId, $userId)) {
            return false;
        }

        $sql = "DELETE FROM {$this->table} WHERE post_id = :post_id AND user_id = :user_id";

        try {
            $this->db->beginTransaction();

            // 좋아요 삭제
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':post_id' => $postId,
                ':user_id' => $userId
            ]);

            // posts 테이블의 like_count 감소
            $updateSql = "UPDATE posts SET like_count = GREATEST(0, like_count - 1) WHERE post_id = :post_id";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([':post_id' => $postId]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('PostLike::unlike - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 사용자가 게시글에 좋아요를 눌렀는지 확인
     */
    public function hasLiked($postId, $userId) {
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
            error_log('PostLike::hasLiked - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 게시글의 좋아요 수 조회
     */
    public function getLikeCount($postId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE post_id = :post_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':post_id' => $postId]);
            $result = $stmt->fetch();
            return $result['count'];
        } catch (PDOException $e) {
            error_log('PostLike::getLikeCount - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 여러 게시글에 대한 사용자의 좋아요 여부 확인
     */
    public function getUserLikesForPosts($postIds, $userId) {
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
            error_log('PostLike::getUserLikesForPosts - ' . $e->getMessage());
            return [];
        }
    }
}
