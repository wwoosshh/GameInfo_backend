# PHP 8.2 with Apache
FROM php:8.2-apache

# 시스템 패키지 업데이트 및 필수 라이브러리 설치
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# PHP 확장 설치
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip

# Apache 모듈 활성화
RUN a2enmod rewrite headers

# Apache 설정 - DocumentRoot를 /var/www/html/public으로 변경
ENV APACHE_DOCUMENT_ROOT=/var/www/html

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 작업 디렉토리 설정
WORKDIR /var/www/html

# 백엔드 파일 복사
COPY . /var/www/html/

# 권한 설정
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# .htaccess 파일 생성 (URL 리라이트)
RUN echo '<IfModule mod_rewrite.c>\n\
    RewriteEngine On\n\
    RewriteCond %{REQUEST_FILENAME} !-f\n\
    RewriteCond %{REQUEST_FILENAME} !-d\n\
    RewriteRule ^api/(.*)$ router.php [QSA,L]\n\
</IfModule>' > /var/www/html/.htaccess

# 포트 노출
EXPOSE 80

# Apache 실행
CMD ["apache2-foreground"]
