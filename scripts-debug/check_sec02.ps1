$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\web.php" 2>&1)

Write-Host "=== web.php linhas e reCAPTCHA ==="
$wlines = Get-Content "$base\routes\web.php"
Write-Host "Linhas=$($wlines.Count)"
$hits = $wlines | Select-String "recaptcha" -CaseSensitive:$false
Write-Host "Ocorrencias recaptcha=$($hits.Count)"
$hits | ForEach-Object { Write-Host "  L$($_.LineNumber): $($_.Line.Trim())" }

Write-Host "=== Posicao no handler de login ==="
$inLogin = $false
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "api/auth/login|POST.*login") { $inLogin = $true }
    if ($inLogin -and $wlines[$i] -match "recaptcha") {
        Write-Host "  L$($i+1) dentro do login: $($wlines[$i].Trim())"
    }
    if ($inLogin -and $wlines[$i] -match "^\}") { $inLogin = $false }
}

Write-Host "=== LoginView.vue ==="
$v = [System.IO.File]::ReadAllText("$base\resources\gente-v3\src\views\auth\LoginView.vue", [System.Text.Encoding]::UTF8)
Write-Host "grecaptcha=$($v.Contains('grecaptcha'))"
Write-Host "recaptcha_token=$($v.Contains('recaptcha_token'))"
Write-Host "VITE_RECAPTCHA=$($v.Contains('VITE_RECAPTCHA'))"

Write-Host "=== .env variaveis ==="
$env = Get-Content "$base\.env"
$envVars = $env | Select-String "RECAPTCHA"
if ($envVars) { $envVars | ForEach-Object { Write-Host "  $_" } }
else { Write-Host "  Nenhuma variavel RECAPTCHA no .env" }
