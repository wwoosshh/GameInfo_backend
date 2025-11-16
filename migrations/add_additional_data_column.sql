-- 항목별 특화 데이터를 저장하기 위한 JSON 컬럼 추가
ALTER TABLE version_update_items
ADD COLUMN additional_data JSON DEFAULT NULL COMMENT '카테고리별 추가 데이터 (JSON)';

-- rarity 컬럼의 용도 변경 (이제 additional_data에 카테고리별로 저장)
-- 기존 rarity는 legacy로 유지하되, 새로운 데이터는 additional_data 사용

-- 인덱스 추가 (JSON 검색 성능 향상)
-- MySQL 5.7+ 에서 JSON 컬럼에 대한 가상 컬럼 인덱스 생성 가능
-- ALTER TABLE version_update_items ADD rarity_json VARCHAR(20) AS (JSON_UNQUOTE(JSON_EXTRACT(additional_data, '$.rarity'))) VIRTUAL;
-- CREATE INDEX idx_rarity_json ON version_update_items(rarity_json);

COMMIT;
