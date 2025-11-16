<?php
/**
 * 캐시 유틸리티 클래스
 *
 * APCu 사용 가능 시 메모리 캐싱, 불가능 시 파일 캐싱 사용
 */

class Cache {
    private static $instance = null;
    private $useApcu = false;
    private $cacheDir;
    private $defaultTTL = 300; // 5분

    private function __construct() {
        // APCu 사용 가능 여부 확인
        $this->useApcu = function_exists('apcu_fetch') && apcu_enabled();

        // 파일 캐시 디렉토리 설정
        $this->cacheDir = __DIR__ . '/../cache/';
        if (!$this->useApcu && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Singleton 인스턴스 반환
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 캐시에서 값 가져오기
     *
     * @param string $key 캐시 키
     * @return mixed|false 캐시된 값 또는 false
     */
    public function get($key) {
        if ($this->useApcu) {
            return apcu_fetch($key);
        }

        return $this->getFromFile($key);
    }

    /**
     * 캐시에 값 저장
     *
     * @param string $key 캐시 키
     * @param mixed $value 저장할 값
     * @param int $ttl 만료 시간 (초)
     * @return bool 성공 여부
     */
    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->defaultTTL;
        }

        if ($this->useApcu) {
            return apcu_store($key, $value, $ttl);
        }

        return $this->setToFile($key, $value, $ttl);
    }

    /**
     * 캐시 삭제
     *
     * @param string $key 캐시 키
     * @return bool 성공 여부
     */
    public function delete($key) {
        if ($this->useApcu) {
            return apcu_delete($key);
        }

        $filename = $this->getCacheFilename($key);
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return false;
    }

    /**
     * 패턴과 일치하는 모든 캐시 삭제
     *
     * @param string $pattern 패턴 (예: 'games_*', 'versions_*')
     * @return bool 성공 여부
     */
    public function deletePattern($pattern) {
        if ($this->useApcu) {
            $iterator = new APCUIterator('/^' . preg_quote($pattern, '/') . '/');
            return apcu_delete($iterator);
        }

        // 파일 캐시의 경우
        $files = glob($this->cacheDir . md5($pattern) . '*');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }

    /**
     * 모든 캐시 삭제
     *
     * @return bool 성공 여부
     */
    public function clear() {
        if ($this->useApcu) {
            return apcu_clear_cache();
        }

        $files = glob($this->cacheDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    /**
     * 캐시 또는 콜백 실행 결과 반환
     *
     * @param string $key 캐시 키
     * @param callable $callback 캐시 미스 시 실행할 함수
     * @param int $ttl 만료 시간 (초)
     * @return mixed 캐시된 값 또는 콜백 실행 결과
     */
    public function remember($key, $callback, $ttl = null) {
        $value = $this->get($key);

        if ($value !== false) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * 파일에서 캐시 읽기
     */
    private function getFromFile($key) {
        $filename = $this->getCacheFilename($key);

        if (!file_exists($filename)) {
            return false;
        }

        $data = file_get_contents($filename);
        $cache = unserialize($data);

        // 만료 확인
        if ($cache['expires'] < time()) {
            unlink($filename);
            return false;
        }

        return $cache['value'];
    }

    /**
     * 파일에 캐시 저장
     */
    private function setToFile($key, $value, $ttl) {
        $filename = $this->getCacheFilename($key);

        $cache = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        return file_put_contents($filename, serialize($cache)) !== false;
    }

    /**
     * 캐시 파일명 생성
     */
    private function getCacheFilename($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }

    /**
     * 캐시 통계 (APCu만 지원)
     */
    public function getStats() {
        if ($this->useApcu) {
            return apcu_cache_info();
        }

        $files = glob($this->cacheDir . '*');
        return [
            'type' => 'file',
            'num_entries' => count($files),
            'cache_dir' => $this->cacheDir
        ];
    }
}
