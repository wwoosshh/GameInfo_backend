<?php
/**
 * ActivityLog 모델 클래스 - 사용자 활동 로그 관리
 */

require_once __DIR__ . '/../config/database.php';

class ActivityLog {
    private $db;
    private $table = 'activity_logs';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 활동 로그 생성
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (user_id, action_type, table_name, record_id, ip_address, user_agent)
                VALUES
                (:user_id, :action_type, :table_name, :record_id, :ip_address, :user_agent)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $data['user_id'] ?? null,
                ':action_type' => $data['action_type'], // 'comment', 'like', 'bookmark', 'post_create' 등
                ':table_name' => $data['table_name'] ?? null,
                ':record_id' => $data['record_id'] ?? null,
                ':ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('ActivityLog::create - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 댓글 작성 로그
     */
    public function logComment($userId, $commentId, $postId) {
        return $this->create([
            'user_id' => $userId,
            'action_type' => 'comment_create',
            'table_name' => 'comments',
            'record_id' => $commentId
        ]);
    }

    /**
     * 게시글 좋아요 로그
     */
    public function logPostLike($userId, $postId) {
        return $this->create([
            'user_id' => $userId,
            'action_type' => 'post_like',
            'table_name' => 'posts',
            'record_id' => $postId
        ]);
    }

    /**
     * 댓글 좋아요 로그
     */
    public function logCommentLike($userId, $commentId) {
        return $this->create([
            'user_id' => $userId,
            'action_type' => 'comment_like',
            'table_name' => 'comments',
            'record_id' => $commentId
        ]);
    }

    /**
     * 북마크 로그
     */
    public function logBookmark($userId, $postId) {
        return $this->create([
            'user_id' => $userId,
            'action_type' => 'bookmark_create',
            'table_name' => 'posts',
            'record_id' => $postId
        ]);
    }

    /**
     * 게시글 작성 로그
     */
    public function logPostCreate($userId, $postId) {
        return $this->create([
            'user_id' => $userId,
            'action_type' => 'post_create',
            'table_name' => 'posts',
            'record_id' => $postId
        ]);
    }

    /**
     * 신고 로그
     */
    public function logReport($userId, $reportId, $reportedType) {
        return $this->create([
            'user_id' => $userId,
            'action_type' => 'report_create',
            'table_name' => 'reports',
            'record_id' => $reportId
        ]);
    }

    /**
     * 사용자의 활동 로그 조회
     */
    public function getUserLogs($userId, $page = 1, $limit = 50) {
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
            error_log('ActivityLog::getUserLogs - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 특정 액션의 로그 조회 (관리자용)
     */
    public function getLogsByAction($actionType, $page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT al.*, u.username, u.display_name
                FROM {$this->table} al
                LEFT JOIN users u ON al.user_id = u.user_id
                WHERE al.action_type = :action_type
                ORDER BY al.created_at DESC
                LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':action_type', $actionType);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('ActivityLog::getLogsByAction - ' . $e->getMessage());
            return [];
        }
    }
}
