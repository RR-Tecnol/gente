FROM php:8.0-fpm

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

# Configura odbcinst.ini para desabilitar Encrypt por padrão no ODBC Driver 18
# Necessário para conexão com SQL Server sem TLS em ambiente de desenvolvimento Docker
RUN printf '[ODBC Driver 18 for SQL Server]\nDescription=Microsoft ODBC Driver 18 for SQL Server\nDriver=/opt/microsoft/msodbcsql18/lib64/libmsodbcsql-18.6.so.1.1\nUsageCount=1\nEncrypt=no\nTrustServerCertificate=yes\n' > /etc/odbcinst.ini

# Extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo mbstring exif pcntl bcmath xml zip gd

# pdo_sqlsrv 5.10.1 — última versão compatível com PHP 8.0
RUN pecl install pdo_sqlsrv-5.10.1 sqlsrv-5.10.1 \
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
