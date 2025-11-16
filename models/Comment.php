<?php
/**
 * Comment 모델 클래스
 */

require_once __DIR__ . '/../config/database.php';

class Comment {
    private $db;
    private $table = 'comments';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 게시글의 댓글 목록 조회
     */
    public function getByPostId($postId) {
        $sql = "SELECT c.*,
                    u.username,
                    u.display_name,
                    u.avatar_url
                FROM {$this->table} c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.post_id = :post_id AND c.is_deleted = false
                ORDER BY c.created_at ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':post_id' => $postId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Comment::getByPostId - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 댓글 ID로 조회
     */
    public function getById($commentId) {
        $sql = "SELECT c.*,
                    u.username,
                    u.display_name,
                    u.avatar_url
                FROM {$this->table} c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.comment_id = :comment_id AND c.is_deleted = false";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':comment_id' => $commentId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Comment::getById - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 댓글 생성
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (post_id, user_id, content, parent_comment_id)
                VALUES
                (:post_id, :user_id, :content, :parent_comment_id)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':post_id' => $data['post_id'],
                ':user_id' => $data['user_id'],
                ':content' => $data['content'],
                ':parent_comment_id' => $data['parent_comment_id'] ?? null
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Comment::create - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 댓글 수정
     */
    public function update($commentId, $content) {
        $sql = "UPDATE {$this->table} SET
                content = :content,
                updated_at = CURRENT_TIMESTAMP
                WHERE comment_id = :comment_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':comment_id' => $commentId,
                ':content' => $content
            ]);
        } catch (PDOException $e) {
            error_log('Comment::update - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 댓글 삭제 (소프트 삭제)
     */
    public function delete($commentId) {
        $sql = "UPDATE {$this->table} SET
                is_deleted = true,
                deleted_at = CURRENT_TIMESTAMP
                WHERE comment_id = :comment_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':comment_id' => $commentId]);
        } catch (PDOException $e) {
            error_log('Comment::delete - ' . $e->getMessage());
            return false;
        }
    }
}
