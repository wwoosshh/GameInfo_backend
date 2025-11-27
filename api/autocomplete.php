<?php
/**
 * Autocomplete API
 * 검색어 자동완성 및 초성검색 지원
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? min(20, max(1, (int)$_GET['limit'])) : 10;

if (strlen($query) < 1) {
    Response::success(['suggestions' => []]);
    exit;
}

// 초성 매핑 테이블
$chosung = ['ㄱ','ㄲ','ㄴ','ㄷ','ㄸ','ㄹ','ㅁ','ㅂ','ㅃ','ㅅ','ㅆ','ㅇ','ㅈ','ㅉ','ㅊ','ㅋ','ㅌ','ㅍ','ㅎ'];

/**
 * 한글 문자열에서 초성 추출
 */
function extractChosung($str) {
    global $chosung;
    $result = '';

    for ($i = 0; $i < mb_strlen($str, 'UTF-8'); $i++) {
        $char = mb_substr($str, $i, 1, 'UTF-8');
        $code = mb_ord($char, 'UTF-8');

        // 한글 범위 (가 ~ 힣)
        if ($code >= 0xAC00 && $code <= 0xD7A3) {
            $chosungIndex = (int)(($code - 0xAC00) / 588);
            $result .= $chosung[$chosungIndex];
        } else {
            $result .= $char;
        }
    }

    return $result;
}

/**
 * 검색어가 초성만으로 구성되어 있는지 확인
 */
function isChosungOnly($str) {
    global $chosung;
    for ($i = 0; $i < mb_strlen($str, 'UTF-8'); $i++) {
        $char = mb_substr($str, $i, 1, 'UTF-8');
        if (!in_array($char, $chosung) && !preg_match('/[a-zA-Z0-9\s]/', $char)) {
            return false;
        }
    }
    return true;
}

try {
    $db = Database::getInstance()->getConnection();
    $suggestions = [];
    $isChosung = isChosungOnly($query);

    // 1. 게임 이름 검색
    $gamesSql = "SELECT DISTINCT game_name, game_name_en, 'game' as type, game_id as id
                 FROM games
                 WHERE is_active = :is_active
                 LIMIT 100";

    $gamesStmt = $db->prepare($gamesSql);
    $gamesStmt->bindValue(':is_active', true, PDO::PARAM_BOOL);
    $gamesStmt->execute();
    $games = $gamesStmt->fetchAll();

    foreach ($games as $game) {
        $gameName = $game['game_name'];
        $gameNameEn = $game['game_name_en'];

        $matched = false;
        $matchScore = 0;

        if ($isChosung) {
            // 초성 검색
            $gameChosung = extractChosung($gameName);
            if (mb_strpos($gameChosung, $query, 0, 'UTF-8') === 0) {
                $matched = true;
                $matchScore = 100; // 초성 시작 매칭
            } elseif (mb_strpos($gameChosung, $query, 0, 'UTF-8') !== false) {
                $matched = true;
                $matchScore = 50; // 초성 포함 매칭
            }
        } else {
            // 일반 검색
            $lowerQuery = mb_strtolower($query, 'UTF-8');
            $lowerName = mb_strtolower($gameName, 'UTF-8');
            $lowerNameEn = $gameNameEn ? mb_strtolower($gameNameEn, 'UTF-8') : '';

            if (mb_strpos($lowerName, $lowerQuery, 0, 'UTF-8') === 0) {
                $matched = true;
                $matchScore = 100; // 시작 매칭
            } elseif ($lowerNameEn && mb_strpos($lowerNameEn, $lowerQuery, 0, 'UTF-8') === 0) {
                $matched = true;
                $matchScore = 95;
            } elseif (mb_strpos($lowerName, $lowerQuery, 0, 'UTF-8') !== false) {
                $matched = true;
                $matchScore = 50; // 포함 매칭
            } elseif ($lowerNameEn && mb_strpos($lowerNameEn, $lowerQuery, 0, 'UTF-8') !== false) {
                $matched = true;
                $matchScore = 45;
            }
        }

        if ($matched) {
            $suggestions[] = [
                'text' => $gameName,
                'type' => 'game',
                'id' => $game['id'],
                'score' => $matchScore
            ];
        }
    }

    // 2. 버전 이름 검색
    $versionsSql = "SELECT DISTINCT gv.version_name, gv.version_name_en, gv.version_number,
                           g.game_name, gv.version_id as id
                    FROM game_versions gv
                    JOIN games g ON gv.game_id = g.game_id
                    WHERE g.is_active = :is_active AND gv.version_name IS NOT NULL
                    LIMIT 200";

    $versionsStmt = $db->prepare($versionsSql);
    $versionsStmt->bindValue(':is_active', true, PDO::PARAM_BOOL);
    $versionsStmt->execute();
    $versions = $versionsStmt->fetchAll();

    foreach ($versions as $version) {
        $versionName = $version['version_name'];
        if (!$versionName) continue;

        $matched = false;
        $matchScore = 0;

        if ($isChosung) {
            $versionChosung = extractChosung($versionName);
            if (mb_strpos($versionChosung, $query, 0, 'UTF-8') === 0) {
                $matched = true;
                $matchScore = 90;
            } elseif (mb_strpos($versionChosung, $query, 0, 'UTF-8') !== false) {
                $matched = true;
                $matchScore = 40;
            }
        } else {
            $lowerQuery = mb_strtolower($query, 'UTF-8');
            $lowerName = mb_strtolower($versionName, 'UTF-8');

            if (mb_strpos($lowerName, $lowerQuery, 0, 'UTF-8') === 0) {
                $matched = true;
                $matchScore = 90;
            } elseif (mb_strpos($lowerName, $lowerQuery, 0, 'UTF-8') !== false) {
                $matched = true;
                $matchScore = 40;
            }
        }

        if ($matched) {
            $suggestions[] = [
                'text' => $versionName,
                'subtext' => $version['game_name'] . ' - 버전 ' . $version['version_number'],
                'type' => 'version',
                'id' => $version['id'],
                'score' => $matchScore
            ];
        }
    }

    // 3. 항목(캐릭터/아이템) 검색
    $itemsSql = "SELECT DISTINCT vui.item_name, vui.item_name_en, vui.category, vui.icon_url,
                        gv.version_number, g.game_name, gv.version_id
                 FROM version_update_items vui
                 JOIN game_versions gv ON vui.version_id = gv.version_id
                 JOIN games g ON gv.game_id = g.game_id
                 WHERE g.is_active = :is_active
                 LIMIT 500";

    $itemsStmt = $db->prepare($itemsSql);
    $itemsStmt->bindValue(':is_active', true, PDO::PARAM_BOOL);
    $itemsStmt->execute();
    $items = $itemsStmt->fetchAll();

    foreach ($items as $item) {
        $itemName = $item['item_name'];

        $matched = false;
        $matchScore = 0;

        if ($isChosung) {
            $itemChosung = extractChosung($itemName);
            if (mb_strpos($itemChosung, $query, 0, 'UTF-8') === 0) {
                $matched = true;
                $matchScore = 80;
            } elseif (mb_strpos($itemChosung, $query, 0, 'UTF-8') !== false) {
                $matched = true;
                $matchScore = 30;
            }
        } else {
            $lowerQuery = mb_strtolower($query, 'UTF-8');
            $lowerName = mb_strtolower($itemName, 'UTF-8');
            $lowerNameEn = $item['item_name_en'] ? mb_strtolower($item['item_name_en'], 'UTF-8') : '';

            if (mb_strpos($lowerName, $lowerQuery, 0, 'UTF-8') === 0) {
                $matched = true;
                $matchScore = 80;
            } elseif ($lowerNameEn && mb_strpos($lowerNameEn, $lowerQuery, 0, 'UTF-8') === 0) {
                $matched = true;
                $matchScore = 75;
            } elseif (mb_strpos($lowerName, $lowerQuery, 0, 'UTF-8') !== false) {
                $matched = true;
                $matchScore = 30;
            } elseif ($lowerNameEn && mb_strpos($lowerNameEn, $lowerQuery, 0, 'UTF-8') !== false) {
                $matched = true;
                $matchScore = 25;
            }
        }

        if ($matched) {
            $categoryNames = [
                'character' => '캐릭터',
                'weapon' => '무기',
                'item' => '아이템',
                'event' => '이벤트',
                'banner' => '배너',
                'other' => '기타'
            ];
            $categoryName = $categoryNames[$item['category']] ?? $item['category'];

            $suggestions[] = [
                'text' => $itemName,
                'subtext' => $item['game_name'] . ' - ' . $categoryName,
                'type' => 'item',
                'category' => $item['category'],
                'icon' => $item['icon_url'],
                'version_id' => $item['version_id'],
                'score' => $matchScore
            ];
        }
    }

    // 점수순 정렬 후 상위 N개만 반환
    usort($suggestions, function($a, $b) {
        return $b['score'] - $a['score'];
    });

    // 중복 제거 (같은 텍스트)
    $seen = [];
    $uniqueSuggestions = [];
    foreach ($suggestions as $suggestion) {
        $key = $suggestion['text'] . '_' . $suggestion['type'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            unset($suggestion['score']); // 점수는 내부용이므로 제거
            $uniqueSuggestions[] = $suggestion;
        }
        if (count($uniqueSuggestions) >= $limit) break;
    }

    Response::success([
        'query' => $query,
        'is_chosung' => $isChosung,
        'suggestions' => $uniqueSuggestions
    ]);

} catch (PDOException $e) {
    error_log('Autocomplete API error: ' . $e->getMessage());
    Response::error('Autocomplete failed', 500);
}
