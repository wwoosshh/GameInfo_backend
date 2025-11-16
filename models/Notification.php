<?php
/**
 * Notification 모델 클래스 - 커뮤니티 공지사항 관리
 */

require_once __DIR__ . '/../config/database.php';

class Notification {
    private $db;
    private $table = 'notifications';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 공지사항 생성 (관리자만)
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (user_id, type, title, content, link_url)
                VALUES
                (:user_id, :type, :title, :content, :link_url)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $data['user_id'], // 공지사항 작성자 (관리자)
                ':type' => 'announcement', // 공지사항 타입
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
     * 활성 공지사항 목록 조회
     */
    public function getActiveAnnouncements() {
        $sql = "SELECT n.*,
                    u.username,
                    u.display_name
                FROM {$this->table} n
                JOIN users u ON n.user_id = u.user_id
                WHERE n.type = 'announcement'
                ORDER BY n.created_at DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Notification::getActiveAnnouncements - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 공지사항 상세 조회
     */
    public function getById($notificationId) {
        $sql = "SELECT n.*,
                    u.username,
                    u.display_name
                FROM {$this->table} n
                JOIN users u ON n.user_id = u.user_id
                WHERE n.notification_id = :notification_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':notification_id' => $notificationId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Notification::getById - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 공지사항 수정
     */
    public function update($notificationId, $data) {
        $sql = "UPDATE {$this->table} SET
                title = :title,
                content = :content,
                link_url = :link_url
                WHERE notification_id = :notification_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':notification_id' => $notificationId,
                ':title' => $data['title'],
                ':content' => $data['content'] ?? null,
                ':link_url' => $data['link_url'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('Notification::update - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 공지사항 삭제
     */
    public function delete($notificationId) {
        $sql = "DELETE FROM {$this->table}
                WHERE notification_id = :notification_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':notification_id' => $notificationId]);
        } catch (PDOException $e) {
            error_log('Notification::delete - ' . $e->getMessage());
            return false;
        }
    }
}
