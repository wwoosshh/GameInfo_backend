<?php
/**
 * Notification 모델 클래스
 */

require_once __DIR__ . '/../config/database.php';

class Notification {
    private $db;
    private $table = 'notifications';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 알림 생성
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (user_id, type, title, content, link_url)
                VALUES
                (:user_id, :type, :title, :content, :link_url)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':type' => $data['type'], // 'comment', 'like', 'mention', 'system' 등
                ':title' => $data['title'],
                ':content' => $data['content'] ?? null,
                ':link_url' => $data['link_url'] ?? null
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Notification::create - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 사용자의 알림 목록 조회
     */
    public function getUserNotifications($userId, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Notification::getUserNotifications - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 읽지 않은 알림 수 조회
     */
    public function getUnreadCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}
                WHERE user_id = :user_id AND is_read = false";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch();
            return $result['count'];
        } catch (PDOException $e) {
            error_log('Notification::getUnreadCount - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 알림 읽음 처리
     */
    public function markAsRead($notificationId, $userId) {
        $sql = "UPDATE {$this->table} SET is_read = true
                WHERE notification_id = :notification_id AND user_id = :user_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':notification_id' => $notificationId,
                ':user_id' => $userId
            ]);
        } catch (PDOException $e) {
            error_log('Notification::markAsRead - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 모든 알림 읽음 처리
     */
    public function markAllAsRead($userId) {
        $sql = "UPDATE {$this->table} SET is_read = true
                WHERE user_id = :user_id AND is_read = false";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log('Notification::markAllAsRead - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 알림 삭제
     */
    public function delete($notificationId, $userId) {
        $sql = "DELETE FROM {$this->table}
                WHERE notification_id = :notification_id AND user_id = :user_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':notification_id' => $notificationId,
                ':user_id' => $userId
            ]);
        } catch (PDOException $e) {
            error_log('Notification::delete - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 댓글 알림 생성 헬퍼
     */
    public function createCommentNotification($postAuthorId, $commenterName, $postId, $postTitle) {
        return $this->create([
            'user_id' => $postAuthorId,
            'type' => 'comment',
            'title' => '새 댓글',
            'content' => "{$commenterName}님이 '{$postTitle}'에 댓글을 남겼습니다.",
            'link_url' => "/community_post.html?id={$postId}"
        ]);
    }

    /**
     * 좋아요 알림 생성 헬퍼
     */
    public function createLikeNotification($postAuthorId, $likerName, $postId, $postTitle) {
        return $this->create([
            'user_id' => $postAuthorId,
            'type' => 'like',
            'title' => '좋아요',
            'content' => "{$likerName}님이 '{$postTitle}'을 좋아합니다.",
            'link_url' => "/community_post.html?id={$postId}"
        ]);
    }
}
