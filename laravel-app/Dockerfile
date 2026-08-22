FROM php:8.2-fpm

# Instalar dependencias del sistema para extensiones PHP
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Configurar e instalar extensiones PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql gd mbstring intl zip bcmath

# Instalar Caddy, Composer y herramientas del sistema
RUN apt-get update && apt-get install -y \
    debian-keyring \
    debian-archive-keyring \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    gettext-base \
    git \
    unzip \
    && curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg \
    && curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list \
    && apt-get update && apt-get install -y caddy \
    && rm -rf /var/lib/apt/lists/* \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permisos de carpetas storage y bootstrap/cache de Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Script de inicio Caddy + PHP-FPM apuntando a /public de Laravel
RUN echo '#!/bin/bash\n\
PORT=${PORT:-80}\n\
echo "Iniciando Sistema OCR SENA en puerto: $PORT"\n\
cat > /etc/caddy/Caddyfile << EOF\n\
:${PORT} {\n\
    root * /var/www/html/public\n\
    php_fastcgi 127.0.0.1:9000\n\
    file_server\n\
    try_files {path} {path}/ /index.php?{query}\n\
}\n\
EOF\n\
php-fpm --daemonize\n\
caddy run --config /etc/caddy/Caddyfile --adapter caddyfile' > /start.sh && chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
