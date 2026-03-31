$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT web.php ==="
Write-Host (php -l "$base\routes\web.php" 2>&1)
$wn = (Get-Content "$base\routes\web.php").Count
Write-Host "Linhas=$wn"

Write-Host "=== config/session.php ==="
$s = Get-Content "$base\config\session.php" -Raw
Write-Host "lifetime 120=$($s -match "lifetime.*120")"
Write-Host "http_only true=$($s -match "http_only.*true")"
Write-Host "same_site lax=$($s -match "same_site.*lax")"
Write-Host "SESSION_SECURE_COOKIE=$($s.Contains('SESSION_SECURE_COOKIE'))"

Write-Host "=== .env SESSION ==="
$env = Get-Content "$base\.env" -Raw
Write-Host "SESSION_SECURE_COOKIE=$($env.Contains('SESSION_SECURE_COOKIE'))"
Write-Host "Valor=$($env | Select-String 'SESSION_SECURE_COOKIE')"

Write-Host "=== axios.js interceptor ==="
$ax = Get-Content "$base\resources\gente-v3\src\plugins\axios.js" -Raw
Write-Host "interceptors.response=$($ax.Contains('interceptors.response'))"
Write-Host "status 401=$($ax.Contains('401'))"
Write-Host "status 419=$($ax.Contains('419'))"
Write-Host "sessao_expirada=$($ax.Contains('sessao_expirada'))"
Write-Host "window.location=$($ax.Contains('window.location'))"

Write-Host "=== LoginView.vue sessao_expirada ==="
$v = Get-Content "$base\resources\gente-v3\src\views\auth\LoginView.vue" -Raw
Write-Host "sessao_expirada=$($v.Contains('sessao_expirada'))"
Write-Host "useRoute=$($v.Contains('useRoute'))"
