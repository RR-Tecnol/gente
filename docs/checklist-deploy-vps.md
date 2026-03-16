# Checklist de Deploy — VPS (Laravel + Vue 3)

## Stack recomendada
- **VPS**: DigitalOcean Droplet / Vultr / Contabo (mín. 2GB RAM, 2 vCPUs)
- **Automação**: Laravel Forge (U$12/mês) ou Dokku (gratuito)
- **Web server**: Nginx + PHP 8.1-FPM
- **Banco**: SQL Server (manter o mesmo driver) ou migrar para PostgreSQL
- **SSL**: Let's Encrypt (automático via Forge/Certbot)

---

## 1. Preparação do servidor

```bash
# Atualizar o sistema
sudo apt update && sudo apt upgrade -y

# Instalar dependências base
sudo apt install -y git unzip curl nginx certbot python3-certbot-nginx

# Instalar PHP 8.1
sudo add-apt-repository ppa:ondrej/php
sudo apt install -y php8.1-fpm php8.1-cli php8.1-mbstring php8.1-xml \
  php8.1-curl php8.1-zip php8.1-bcmath php8.1-pdo php8.1-pdo-sqlsrv

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## 2. Clone e configuração do projeto

```bash
cd /var/www
sudo git clone <URL_DO_REPOSITORIO> sisgep
sudo chown -R www-data:www-data sisgep
cd sisgep

# Instalar dependências PHP
composer install --no-dev --optimize-autoloader

# Copiar e editar .env
cp .env.example .env
nano .env  # Editar DB_*, APP_URL, MAIL_*

# Gerar chave
php artisan key:generate

# Build do frontend Vue 3
cd resources/gente-v3
npm install
npm run build
cd ../..

# Cache de configuração
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 3. Configuração do Nginx

```nginx
# /etc/nginx/sites-available/sisgep
server {
    listen 80;
    server_name seudominio.com.br;
    root /var/www/sisgep/public;
    index index.php;

    # SPA Vue 3 — rotas do frontend
    location /gente/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/sisgep /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 4. SSL (Let's Encrypt)

```bash
sudo certbot --nginx -d seudominio.com.br
# Renovação automática já configurada pelo certbot
```

---

## 5. Permissões e storage

```bash
sudo chown -R www-data:www-data /var/www/sisgep/storage
sudo chmod -R 775 /var/www/sisgep/storage
sudo chmod -R 775 /var/www/sisgep/bootstrap/cache
php artisan storage:link
```

---

## 6. Queue worker (se usar jobs/notificações)

```bash
# Criar serviço systemd
sudo nano /etc/systemd/system/sisgep-queue.service
```

```ini
[Unit]
Description=SISGEP Queue Worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/sisgep
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable sisgep-queue
sudo systemctl start sisgep-queue
```

---

## 7. Verificação pós-deploy

- [ ] `/` carrega a aplicação sem erro 500
- [ ] `/api/csrf-cookie` responde 200
- [ ] Login funciona com admin
- [ ] Dashboard mostra dados reais
- [ ] `php artisan route:list | grep api` mostra todas as rotas
- [ ] `php artisan queue:work` sem erros

---

## 8. Via Docker (alternativa — mais simples)

Se preferir usar o `docker-compose.yml` já existente:

```bash
# No servidor VPS
sudo apt install -y docker.io docker-compose
cd /var/www/sisgep
cp .env.example .env  # editar variáveis
docker-compose up -d --build
```

Vantagem: ambiente idêntico ao local, sem configurar PHP/Nginx manualmente.

---

## Atenção — BOM em arquivos PHP

> Antes de subir para o VPS, verificar se não há BOM introduzido nos arquivos de rotas:
> ```powershell
> Get-Content 'routes\web.php' -Encoding Byte -TotalCount 3
> # OK: 60 63 112 (< ? p)
> # PROBLEMA: 239 187 191 (BOM — corrompe JSON)
> ```
> Ver `docs/historico-2026-03-10.md` para o procedimento de remoção.
