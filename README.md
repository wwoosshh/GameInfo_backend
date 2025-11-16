# 게임 업데이트 트래커 - 백엔드 API

PHP 기반 REST API 서버

## 기술 스택

- **Language**: PHP 8.2+
- **Database**: PostgreSQL (Supabase)
- **Image Storage**: Cloudinary
- **Authentication**: JWT

## 설치 및 실행

### 1. 환경 변수 설정

```bash
cp .env.example ../config/.env
```

`.env` 파일을 열어 실제 값으로 수정:
- Supabase 연결 정보
- Cloudinary API 키
- JWT Secret 키

### 2. 로컬 실행 (PHP 내장 서버)

```bash
cd backend
php -S localhost:8080
```

### 3. Docker 실행

```bash
docker build -t game-tracker-backend .
docker run -p 8080:80 game-tracker-backend
```

## API 엔드포인트

### 인증 (Auth)
- `POST /api/auth/login` - 로그인
- `POST /api/auth/register` - 회원가입
- `GET /api/auth/me` - 현재 사용자 정보
- `POST /api/auth/logout` - 로그아웃

### 게임 (Games)
- `GET /api/games` - 게임 목록
- `GET /api/games/{id}` - 게임 상세
- `POST /api/games` - 게임 추가 (관리자)
- `PUT /api/games/{id}` - 게임 수정 (관리자)
- `DELETE /api/games/{id}` - 게임 삭제 (관리자)

### 버전 (Versions)
- `GET /api/versions?game_id={id}` - 게임별 버전 목록
- `GET /api/versions/{id}` - 버전 상세
- `GET /api/versions/{id}/items` - 버전별 업데이트 항목
- `POST /api/versions` - 버전 추가 (관리자)
- `PUT /api/versions/{id}` - 버전 수정 (관리자)
- `DELETE /api/versions/{id}` - 버전 삭제 (관리자)

### 업로드 (Upload)
- `POST /api/upload` - 이미지 업로드 (Cloudinary)

## Railway 배포

1. Railway 프로젝트 생성
2. GitHub 저장소 연결
3. 환경 변수 설정 (Settings → Variables)
4. 자동 배포 확인

## 환경 변수

| 변수명 | 설명 | 예시 |
|--------|------|------|
| DB_HOST | PostgreSQL 호스트 | aws-0-ap-northeast-2.pooler.supabase.com |
| DB_PORT | PostgreSQL 포트 | 6543 |
| DB_NAME | 데이터베이스명 | postgres |
| DB_USER | 사용자명 | postgres.xxx |
| DB_PASSWORD | 비밀번호 | your_password |
| CLOUDINARY_CLOUD_NAME | Cloudinary 클라우드명 | your_cloud |
| CLOUDINARY_API_KEY | Cloudinary API 키 | 123456789 |
| CLOUDINARY_API_SECRET | Cloudinary API 시크릿 | abcdefg |
| JWT_SECRET | JWT 시크릿 키 | random_secret_key |

## 라이선스

MIT
