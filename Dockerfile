FROM php:8.4-fpm

# Dependências base
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libzip-dev \
    zip unzip gnupg2 apt-transport-https ca-certificates \
    libfreetype6-dev libjpeg62-turbo-dev \
    && rm -rf /var/lib/apt/lists/*

# Repositório Microsoft para drivers SQL Server
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/11/prod bullseye main" \
    > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 unixodbc-dev \
    && rm -rf /var/lib/apt/lists/*

# Evita erro "dubious ownership" ao executar Composer em bind mount
RUN git config --global --add safe.directory /var/www

# Extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo mbstring exif pcntl bcmath xml zip gd

# Drivers SQL Server via PECL (compatível com PHP 8.4)
RUN pecl install pdo_sqlsrv sqlsrv \
    && docker-php-ext-enable pdo_sqlsrv sqlsrv

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Dependências PHP (layer cacheada)
COPY composer.json composer.lock ./
RUN mkdir -p bootstrap/cache storage/logs storage/framework/sessions \
    storage/framework/views storage/framework/cache \
    && composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

# Código do projeto
COPY . .
RUN composer dump-autoload --optimize --no-scripts --ignore-platform-reqs

# Permissões
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
