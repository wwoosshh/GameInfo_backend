<?php
/**
 * 데이터베이스 연결 설정 및 PDO 인스턴스 생성
 *
 * PDO를 사용하여 안전한 데이터베이스 연결을 제공합니다.
 * Prepared Statements를 통해 SQL Injection을 방지합니다.
 */

class Database {
    private static $instance = null;
    private $connection;
    private $config;

    private function __construct() {
        $this->connect();
    }

    /**
     * Singleton 패턴으로 데이터베이스 인스턴스 반환
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 데이터베이스 연결 생성
     */
    private function connect() {
        // 환경 변수 로드
        $this->config = $this->loadConfig();

        $host = $this->config['DB_HOST'];
        $port = $this->config['DB_PORT'];
        $dbname = $this->config['DB_NAME'];
        $username = $this->config['DB_USER'];
        $password = $this->config['DB_PASSWORD'];

        // PostgreSQL (Supabase) 연결 DSN
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            $this->handleConnectionError($e);
        }
    }

    /**
     * PDO 연결 객체 반환
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * 설정 파일 로드 (파일 또는 환경 변수)
     */
    private function loadConfig() {
        $config = [];

        // Railway 등 클라우드 환경에서는 환경 변수 우선 사용
        if (getenv('DB_HOST')) {
            $config['DB_HOST'] = getenv('DB_HOST');
            $config['DB_PORT'] = getenv('DB_PORT') ?: '6543';
            $config['DB_NAME'] = getenv('DB_NAME') ?: 'postgres';
            $config['DB_USER'] = getenv('DB_USER');
            $config['DB_PASSWORD'] = getenv('DB_PASSWORD');
            $config['DB_CHARSET'] = getenv('DB_CHARSET') ?: 'utf8';
            $config['ENVIRONMENT'] = getenv('ENVIRONMENT') ?: 'production';
            $config['JWT_SECRET'] = getenv('JWT_SECRET');
            $config['JWT_EXPIRATION'] = getenv('JWT_EXPIRATION') ?: '86400';
            $config['CLOUDINARY_CLOUD_NAME'] = getenv('CLOUDINARY_CLOUD_NAME');
            $config['CLOUDINARY_API_KEY'] = getenv('CLOUDINARY_API_KEY');
            $config['CLOUDINARY_API_SECRET'] = getenv('CLOUDINARY_API_SECRET');

            return $config;
        }

        // 로컬 환경에서는 .env 파일 사용
        $configFile = __DIR__ . '/../../config/.env';

        if (!file_exists($configFile)) {
            die('Configuration file not found. Please copy .env.example to .env and configure it.');
        }

        $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // 주석 제거
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // KEY=VALUE 파싱
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }

        return $config;
    }

    /**
     * 연결 오류 처리
     */
    private function handleConnectionError($e) {
        error_log('Database Connection Error: ' . $e->getMessage());

        $environment = isset($this->config['ENVIRONMENT']) ? $this->config['ENVIRONMENT'] : 'production';

        if ($environment === 'development') {
            die('Database connection failed: ' . $e->getMessage());
        } else {
            die('Database connection failed. Please contact the administrator.');
        }
    }

    /**
     * Clone 방지 (Singleton)
     */
    private function __clone() {}

    /**
     * Unserialize 방지 (Singleton)
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
