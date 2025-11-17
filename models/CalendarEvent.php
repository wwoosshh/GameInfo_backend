<?php
/**
 * CalendarEvent 모델 클래스
 */

require_once __DIR__ . '/../config/database.php';

class CalendarEvent {
    private $db;
    private $table = 'user_calendar_events';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 사용자의 일정 목록 조회
     */
    public function getUserEvents($userId, $startDate = null, $endDate = null) {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = :user_id";

        $params = [':user_id' => $userId];

        // 날짜 범위 필터링
        if ($startDate) {
            $sql .= " AND event_date >= :start_date";
            $params[':start_date'] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND event_date <= :end_date";
            $params[':end_date'] = $endDate;
        }

        $sql .= " ORDER BY event_date ASC, event_time ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('CalendarEvent::getUserEvents - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 특정 일정 조회
     */
    public function getById($eventId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE event_id = :event_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':event_id' => $eventId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('CalendarEvent::getById - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 일정 생성
     */
    public function create($data) {
        // event_type은 데이터베이스 기본값 사용
        $sql = "INSERT INTO {$this->table}
                (user_id, event_title, event_description, event_date, event_time, is_all_day)
                VALUES
                (:user_id, :event_title, :event_description, :event_date, :event_time, :is_all_day)";

        try {
            $stmt = $this->db->prepare($sql);

            // Boolean을 정수로 변환 (PostgreSQL 호환)
            $isAllDay = $data['is_all_day'] ?? false;

            $stmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':event_title', $data['event_title'], PDO::PARAM_STR);
            $stmt->bindValue(':event_description', $data['event_description'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':event_date', $data['event_date'], PDO::PARAM_STR);
            $stmt->bindValue(':event_time', $data['event_time'] ?? null, PDO::PARAM_STR);
            $stmt->bindValue(':is_all_day', $isAllDay ? 1 : 0, PDO::PARAM_INT);

            $result = $stmt->execute();

            if ($result) {
                $lastId = $this->db->lastInsertId();
                error_log('CalendarEvent::create - Successfully created event with ID: ' . $lastId);
                return $lastId;
            }
            return false;
        } catch (PDOException $e) {
            error_log('CalendarEvent::create - PDO Exception: ' . $e->getMessage());
            error_log('CalendarEvent::create - SQL State: ' . $e->getCode());
            return false;
        }
    }

    /**
     * 일정 수정
     */
    public function update($eventId, $data) {
        $fields = [];
        $params = [':event_id' => $eventId];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        // updated_at 자동 업데이트
        $fields[] = "updated_at = NOW()";

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . "
                WHERE event_id = :event_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('CalendarEvent::update - ' . $e->getMessage());
            error_log('CalendarEvent::update - SQL: ' . $sql);
            error_log('CalendarEvent::update - Data: ' . json_encode($data));
            return false;
        }
    }

    /**
     * 일정 삭제
     */
    public function delete($eventId) {
        $sql = "DELETE FROM {$this->table}
                WHERE event_id = :event_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':event_id' => $eventId]);
        } catch (PDOException $e) {
            error_log('CalendarEvent::delete - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 특정 날짜의 일정 조회
     */
    public function getByDate($userId, $date) {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = :user_id AND event_date = :event_date
                ORDER BY event_time ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':event_date' => $date
            ]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('CalendarEvent::getByDate - ' . $e->getMessage());
            return false;
        }
    }
}
