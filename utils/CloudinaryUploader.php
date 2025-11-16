<?php

class CloudinaryUploader {
    private $cloudName;
    private $apiKey;
    private $apiSecret;
    private $uploadPreset;
    private $folder;

    public function __construct() {
        $this->cloudName = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? '';
        $this->apiKey = $_ENV['CLOUDINARY_API_KEY'] ?? '';
        $this->apiSecret = $_ENV['CLOUDINARY_API_SECRET'] ?? '';
        $this->uploadPreset = $_ENV['CLOUDINARY_UPLOAD_PRESET'] ?? '';
        $this->folder = $_ENV['CLOUDINARY_FOLDER'] ?? 'game_updates';
    }

    /**
     * 이미지를 Cloudinary에 업로드
     *
     * @param array $file $_FILES 배열의 단일 파일 정보
     * @param string|null $publicId 선택적 public ID (지정하지 않으면 자동 생성)
     * @return array ['success' => bool, 'url' => string, 'secure_url' => string, 'public_id' => string, 'error' => string]
     */
    public function uploadImage($file, $publicId = null) {
        try {
            // 파일 검증
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error']
                ];
            }

            // Cloudinary 업로드 URL
            $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";

            // 타임스탬프 생성
            $timestamp = time();

            // 업로드 파라미터
            $params = [
                'timestamp' => $timestamp,
                'folder' => $this->folder
            ];

            // public_id가 지정된 경우 추가
            if ($publicId) {
                $params['public_id'] = $publicId;
            }

            // upload_preset이 있으면 추가 (unsigned upload용)
            if ($this->uploadPreset) {
                $params['upload_preset'] = $this->uploadPreset;
            }

            // 서명 생성
            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
            $params['api_key'] = $this->apiKey;

            // 파일 데이터 준비
            $params['file'] = new CURLFile($file['tmp_name'], $file['type'], $file['name']);

            // cURL로 업로드
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 개발 환경용 (프로덕션에서는 true로)

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                return [
                    'success' => false,
                    'error' => 'cURL error: ' . $error
                ];
            }

            curl_close($ch);

            // 응답 처리
            $result = json_decode($response, true);

            if ($httpCode === 200 && isset($result['secure_url'])) {
                return [
                    'success' => true,
                    'url' => $result['url'],
                    'secure_url' => $result['secure_url'],
                    'public_id' => $result['public_id'],
                    'width' => $result['width'] ?? null,
                    'height' => $result['height'] ?? null,
                    'format' => $result['format'] ?? null,
                    'resource_type' => $result['resource_type'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error']['message'] ?? 'Upload failed',
                    'details' => $result
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Base64 인코딩된 이미지를 업로드
     *
     * @param string $base64Data Base64 인코딩된 이미지 데이터
     * @param string|null $publicId 선택적 public ID
     * @return array
     */
    public function uploadBase64($base64Data, $publicId = null) {
        try {
            $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";
            $timestamp = time();

            $params = [
                'timestamp' => $timestamp,
                'folder' => $this->folder
            ];

            if ($publicId) {
                $params['public_id'] = $publicId;
            }

            if ($this->uploadPreset) {
                $params['upload_preset'] = $this->uploadPreset;
            }

            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
            $params['api_key'] = $this->apiKey;
            $params['file'] = $base64Data;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 200 && isset($result['secure_url'])) {
                return [
                    'success' => true,
                    'url' => $result['url'],
                    'secure_url' => $result['secure_url'],
                    'public_id' => $result['public_id']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error']['message'] ?? 'Upload failed'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cloudinary에서 이미지 삭제
     *
     * @param string $publicId 삭제할 이미지의 public ID
     * @return array
     */
    public function deleteImage($publicId) {
        try {
            $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy";
            $timestamp = time();

            $params = [
                'public_id' => $publicId,
                'timestamp' => $timestamp
            ];

            $signature = $this->generateSignature($params);

            $postData = [
                'public_id' => $publicId,
                'signature' => $signature,
                'api_key' => $this->apiKey,
                'timestamp' => $timestamp
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            return [
                'success' => ($result['result'] ?? '') === 'ok',
                'result' => $result
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 파일 검증
     */
    private function validateFile($file) {
        // 파일 업로드 에러 체크
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => 'File upload error: ' . ($file['error'] ?? 'unknown')
            ];
        }

        // 파일 크기 체크
        $maxSize = $_ENV['UPLOAD_MAX_SIZE'] ?? 5242880; // 5MB
        if ($file['size'] > $maxSize) {
            return [
                'valid' => false,
                'error' => 'File size exceeds maximum allowed size'
            ];
        }

        // 파일 타입 체크
        $allowedTypes = explode(',', $_ENV['ALLOWED_IMAGE_TYPES'] ?? 'jpg,jpeg,png,gif,webp');
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedTypes)) {
            return [
                'valid' => false,
                'error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes)
            ];
        }

        // MIME 타입 체크
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            return [
                'valid' => false,
                'error' => 'Invalid file MIME type'
            ];
        }

        return ['valid' => true];
    }

    /**
     * API 서명 생성
     */
    private function generateSignature($params) {
        // signature에는 포함하지 않을 파라미터 제외
        unset($params['file']);
        unset($params['api_key']);
        unset($params['resource_type']);

        // 파라미터를 알파벳 순으로 정렬
        ksort($params);

        // key=value 형식으로 조합
        $signatureString = '';
        foreach ($params as $key => $value) {
            if (!empty($value)) {
                $signatureString .= $key . '=' . $value . '&';
            }
        }
        $signatureString = rtrim($signatureString, '&');

        // API secret을 추가하고 SHA-1 해시 생성
        $signatureString .= $this->apiSecret;

        return sha1($signatureString);
    }

    /**
     * 이미지 URL 변환 (크기 조정, 최적화 등)
     *
     * @param string $publicId Cloudinary public ID
     * @param array $transformations 변환 옵션 ['width' => 300, 'height' => 300, 'crop' => 'fill']
     * @return string
     */
    public function getTransformedUrl($publicId, $transformations = []) {
        $baseUrl = "https://res.cloudinary.com/{$this->cloudName}/image/upload/";

        $transformString = '';
        if (!empty($transformations)) {
            $parts = [];
            foreach ($transformations as $key => $value) {
                $parts[] = "{$key}_{$value}";
            }
            $transformString = implode(',', $parts) . '/';
        }

        return $baseUrl . $transformString . $publicId;
    }
}
