<?php
/**
 * CommentLike 모델 클래스
 */

require_once __DIR__ . '/../config/database.php';

class CommentLike {
    private $db;
    private $table = 'comment_likes';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 댓글에 좋아요 추가
     */
    public function like($commentId, $userId) {
        // 이미 좋아요를 눌렀는지 확인
        if ($this->hasLiked($commentId, $userId)) {
            return false;
        }

        $sql = "INSERT INTO {$this->table} (comment_id, user_id) VALUES (:comment_id, :user_id)";

        try {
            $this->db->beginTransaction();

            // 좋아요 추가
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':comment_id' => $commentId,
                ':user_id' => $userId
            ]);

            // comments 테이블의 like_count 증가
            $updateSql = "UPDATE comments SET like_count = like_count + 1 WHERE comment_id = :comment_id";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([':comment_id' => $commentId]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('CommentLike::like - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 댓글 좋아요 취소
     */
    public function unlike($commentId, $userId) {
        if (!$this->hasLiked($commentId, $userId)) {
            return false;
        }

        $sql = "DELETE FROM {$this->table} WHERE comment_id = :comment_id AND user_id = :user_id";

        try {
            $this->db->beginTransaction();

            // 좋아요 삭제
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':comment_id' => $commentId,
                ':user_id' => $userId
            ]);

            // comments 테이블의 like_count 감소
            $updateSql = "UPDATE comments SET like_count = GREATEST(0, like_count - 1) WHERE comment_id = :comment_id";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([':comment_id' => $commentId]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('CommentLike::unlike - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 사용자가 댓글에 좋아요를 눌렀는지 확인
     */
    public function hasLiked($commentId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}
                WHERE comment_id = :comment_id AND user_id = :user_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':comment_id' => $commentId,
                ':user_id' => $userId
            ]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log('CommentLike::hasLiked - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 여러 댓글에 대한 사용자의 좋아요 여부 확인
     */
    public function getUserLikesForComments($commentIds, $userId) {
        if (empty($commentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $sql = "SELECT comment_id FROM {$this->table}
                WHERE comment_id IN ($placeholders) AND user_id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $params = array_merge($commentIds, [$userId]);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $results;
        } catch (PDOException $e) {
            error_log('CommentLike::getUserLikesForComments - ' . $e->getMessage());
            return [];
        }
    }
}
