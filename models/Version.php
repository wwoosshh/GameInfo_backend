<?php
/**
 * Version 모델 클래스
 */

require_once __DIR__ . '/../config/database.php';

class Version {
    private $db;
    private $versionsTable = 'game_versions';
    private $itemsTable = 'version_update_items';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 버전 생성
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->versionsTable}
                (game_id, version_number, version_name, version_name_en, release_date,
                 end_date, duration_days, banner_image_url, thumbnail_url, description, is_current)
                VALUES
                (:game_id, :version_number, :version_name, :version_name_en, :release_date,
                 :end_date, :duration_days, :banner_image_url, :thumbnail_url, :description, :is_current)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':game_id' => $data['game_id'],
                ':version_number' => $data['version_number'],
                ':version_name' => $data['version_name'] ?? null,
                ':version_name_en' => $data['version_name_en'] ?? null,
                ':release_date' => $data['release_date'],
                ':end_date' => $data['end_date'] ?? null,
                ':duration_days' => $data['duration_days'] ?? null,
                ':banner_image_url' => $data['banner_image_url'] ?? null,
                ':thumbnail_url' => $data['thumbnail_url'] ?? null,
                ':description' => $data['description'] ?? null,
                ':is_current' => $data['is_current'] ?? 0
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Version::create - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 버전 업데이트
     */
    public function update($versionId, $data) {
        $sql = "UPDATE {$this->versionsTable} SET
                version_number = :version_number,
                version_name = :version_name,
                version_name_en = :version_name_en,
                release_date = :release_date,
                end_date = :end_date,
                duration_days = :duration_days,
                banner_image_url = :banner_image_url,
                thumbnail_url = :thumbnail_url,
                description = :description,
                is_current = :is_current,
                updated_at = CURRENT_TIMESTAMP
                WHERE version_id = :version_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':version_id' => $versionId,
                ':version_number' => $data['version_number'],
                ':version_name' => $data['version_name'] ?? null,
                ':version_name_en' => $data['version_name_en'] ?? null,
                ':release_date' => $data['release_date'],
                ':end_date' => $data['end_date'] ?? null,
                ':duration_days' => $data['duration_days'] ?? null,
                ':banner_image_url' => $data['banner_image_url'] ?? null,
                ':thumbnail_url' => $data['thumbnail_url'] ?? null,
                ':description' => $data['description'] ?? null,
                ':is_current' => $data['is_current'] ?? 0
            ]);
        } catch (PDOException $e) {
            error_log('Version::update - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 버전 삭제
     */
    public function delete($versionId) {
        $sql = "DELETE FROM {$this->versionsTable} WHERE version_id = :version_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':version_id' => $versionId]);
        } catch (PDOException $e) {
            error_log('Version::delete - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 버전 ID로 조회
     */
    public function getById($versionId) {
        $sql = "SELECT * FROM {$this->versionsTable} WHERE version_id = :version_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':version_id' => $versionId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Version::getById - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 업데이트 항목 추가
     */
    public function addItem($data) {
        $sql = "INSERT INTO {$this->itemsTable}
                (version_id, category, item_name, item_name_en, description,
                 image_url, icon_url, rarity, start_date, end_date, is_featured, sort_order, additional_data)
                VALUES
                (:version_id, :category, :item_name, :item_name_en, :description,
                 :image_url, :icon_url, :rarity, :start_date, :end_date, :is_featured, :sort_order, :additional_data)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':version_id' => $data['version_id'],
                ':category' => $data['category'],
                ':item_name' => $data['item_name'],
                ':item_name_en' => $data['item_name_en'] ?? null,
                ':description' => $data['description'] ?? null,
                ':image_url' => $data['image_url'] ?? null,
                ':icon_url' => $data['icon_url'] ?? null,
                ':rarity' => $data['rarity'] ?? 'common',
                ':start_date' => $data['start_date'] ?? null,
                ':end_date' => $data['end_date'] ?? null,
                ':is_featured' => $data['is_featured'] ?? 0,
                ':sort_order' => $data['sort_order'] ?? 0,
                ':additional_data' => $data['additional_data'] ?? null
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Version::addItem - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 업데이트 항목 수정
     */
    public function updateItem($itemId, $data) {
        $sql = "UPDATE {$this->itemsTable} SET
                category = :category,
                item_name = :item_name,
                item_name_en = :item_name_en,
                description = :description,
                image_url = :image_url,
                icon_url = :icon_url,
                rarity = :rarity,
                start_date = :start_date,
                end_date = :end_date,
                is_featured = :is_featured,
                sort_order = :sort_order,
                additional_data = :additional_data,
                updated_at = CURRENT_TIMESTAMP
                WHERE item_id = :item_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':item_id' => $itemId,
                ':category' => $data['category'],
                ':item_name' => $data['item_name'],
                ':item_name_en' => $data['item_name_en'] ?? null,
                ':description' => $data['description'] ?? null,
                ':image_url' => $data['image_url'] ?? null,
                ':icon_url' => $data['icon_url'] ?? null,
                ':rarity' => $data['rarity'] ?? 'common',
                ':start_date' => $data['start_date'] ?? null,
                ':end_date' => $data['end_date'] ?? null,
                ':is_featured' => $data['is_featured'] ?? 0,
                ':sort_order' => $data['sort_order'] ?? 0,
                ':additional_data' => $data['additional_data'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('Version::updateItem - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 업데이트 항목 삭제
     */
    public function deleteItem($itemId) {
        $sql = "DELETE FROM {$this->itemsTable} WHERE item_id = :item_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':item_id' => $itemId]);
        } catch (PDOException $e) {
            error_log('Version::deleteItem - ' . $e->getMessage());
            return false;
        }
    }
}
