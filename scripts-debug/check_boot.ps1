cd 'C:\Users\joaob\Desktop\sisgep-job-main'
Write-Host "=== 1. PHP version ==="
php --version 2>&1

Write-Host ""
Write-Host "=== 2. Artisan can boot (route:list smoke test) ==="
php artisan route:list --path=api/v3/almoxarifado 2>&1

Write-Host ""
Write-Host "=== 3. Migration status - almoxarifado ==="
php artisan migrate:status 2>&1 | Select-String "almox"

Write-Host ""
Write-Host "=== 4. Config cache ok ==="
php artisan config:show app.name 2>&1
