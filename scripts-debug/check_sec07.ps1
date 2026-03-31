$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT web.php ==="
Write-Host (php -l "$base\routes\web.php" 2>&1)
Write-Host "Linhas=$((Get-Content "$base\routes\web.php").Count)"

Write-Host "=== config/cors.php ==="
$c = Get-Content "$base\config\cors.php" -Raw
Write-Host "supports_credentials true=$($c -match "supports_credentials.*true")"
Write-Host "wildcard ausente=$(-not $c.Contains("'*'"))"
Write-Host "localhost 5173=$($c.Contains('5173'))"
Write-Host "localhost 8000=$($c.Contains('8000'))"
Write-Host "placeholder pmsaoluis=$($c.Contains('pmsaoluis'))"
Write-Host "comentado=$($c.Contains('//'))"
