cd 'C:\Users\joaob\Desktop\sisgep-job-main'

Write-Host "=== MIGRATIONS PENDENTES ==="
php artisan migrate:status 2>&1 | Select-String "No "

Write-Host ""
Write-Host "=== TOTAL MIGRATIONS (Ran vs Pending) ==="
$status = php artisan migrate:status 2>&1
$ran = ($status | Select-String "^\|\s+Yes").Count
$pending = ($status | Select-String "^\|\s+No").Count
Write-Host "Ran: $ran"
Write-Host "Pending: $pending"

Write-Host ""
Write-Host "=== VITE / node_modules ok ==="
if (Test-Path 'C:\Users\joaob\Desktop\sisgep-job-main\resources\gente-v3\node_modules') {
    Write-Host "node_modules: EXISTS"
} else {
    Write-Host "node_modules: MISSING - npm install needed"
}

Write-Host ""
Write-Host "=== .env APP_ENV ==="
Select-String "APP_ENV|APP_URL|DB_CONNECTION" 'C:\Users\joaob\Desktop\sisgep-job-main\.env' | ForEach-Object { Write-Host $_.Line }
