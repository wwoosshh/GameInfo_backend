<?php
/**
 * Report 모델 클래스
 */

require_once __DIR__ . '/../config/database.php';

class Report {
    private $db;
    private $table = 'reports';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 신고 생성
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (reporter_user_id, reported_type, reported_id, reason, description)
                VALUES
                (:reporter_user_id, :reported_type, :reported_id, :reason, :description)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':reporter_user_id' => $data['reporter_user_id'],
                ':reported_type' => $data['reported_type'], // 'post' or 'comment'
                ':reported_id' => $data['reported_id'],
                ':reason' => $data['reason'],
                ':description' => $data['description'] ?? null
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Report::create - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 신고 목록 조회 (관리자)
     */
    public function getAll($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT r.*,
                    u.username as reporter_username,
                    u.display_name as reporter_display_name,
                    reviewer.username as reviewer_username
                FROM {$this->table} r
                JOIN users u ON r.reporter_user_id = u.user_id
                LEFT JOIN users reviewer ON r.reviewed_by = reviewer.user_id
                WHERE 1=1";

        $params = [];

        // 상태 필터
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params[':status'] = $filters['status'];
        }

        // 타입 필터
        if (!empty($filters['reported_type'])) {
            $sql .= " AND r.reported_type = :reported_type";
            $params[':reported_type'] = $filters['reported_type'];
        }

        $sql .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            $reports = $stmt->fetchAll();

            return [
                'reports' => $reports,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($this->getTotalCount($filters) / $limit),
                    'total_items' => $this->getTotalCount($filters),
                    'items_per_page' => $limit
                ]
            ];
        } catch (PDOException $e) {
            error_log('Report::getAll - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 신고 상세 조회
     */
    public function getById($reportId) {
        $sql = "SELECT r.*,
                    u.username as reporter_username,
                    u.display_name as reporter_display_name,
                    reviewer.username as reviewer_username
                FROM {$this->table} r
                JOIN users u ON r.reporter_user_id = u.user_id
                LEFT JOIN users reviewer ON r.reviewed_by = reviewer.user_id
                WHERE r.report_id = :report_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':report_id' => $reportId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Report::getById - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 신고 처리 (관리자)
     */
    public function updateStatus($reportId, $status, $reviewedBy) {
        $sql = "UPDATE {$this->table} SET
                status = :status,
                reviewed_by = :reviewed_by,
                reviewed_at = CURRENT_TIMESTAMP
                WHERE report_id = :report_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':report_id' => $reportId,
                ':status' => $status, // 'pending', 'approved', 'rejected'
                ':reviewed_by' => $reviewedBy
            ]);
        } catch (PDOException $e) {
            error_log('Report::updateStatus - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 사용자가 이미 신고했는지 확인
     */
    public function hasReported($reporterUserId, $reportedType, $reportedId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}
                WHERE reporter_user_id = :reporter_user_id
                AND reported_type = :reported_type
                AND reported_id = :reported_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':reporter_user_id' => $reporterUserId,
                ':reported_type' => $reportedType,
                ':reported_id' => $reportedId
            ]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log('Report::hasReported - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 전체 신고 수
     */
    private function getTotalCount($filters = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['reported_type'])) {
            $sql .= " AND reported_type = :reported_type";
            $params[':reported_type'] = $filters['reported_type'];
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['count'];
        } catch (PDOException $e) {
            error_log('Report::getTotalCount - ' . $e->getMessage());
            return 0;
        }
    }
}
