<?php
/**
 * User 모델 클래스
 */

require_once __DIR__ . '/../config/database.php';

class User {
    private $db;
    private $table = 'users';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 사용자 이름으로 조회
     */
    public function getByUsername($username) {
        $sql = "SELECT * FROM {$this->table} WHERE username = :username AND is_active = 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':username' => $username]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('User::getByUsername - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 사용자 ID로 조회
     */
    public function getById($userId) {
        $sql = "SELECT user_id, username, email, display_name, is_admin, created_at
                FROM {$this->table} WHERE user_id = :user_id AND is_active = 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('User::getById - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 사용자 생성
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (username, email, password_hash, display_name)
                VALUES (:username, :email, :password_hash, :display_name)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password_hash' => $data['password_hash'],
                ':display_name' => $data['display_name'] ?? $data['username']
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('User::create - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 마지막 로그인 시간 업데이트
     */
    public function updateLastLogin($userId) {
        $sql = "UPDATE {$this->table} SET last_login = NOW() WHERE user_id = :user_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log('User::updateLastLogin - ' . $e->getMessage());
            return false;
        }
    }
}
