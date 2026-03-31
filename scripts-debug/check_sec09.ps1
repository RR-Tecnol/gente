$base = "C:\Users\joaob\Desktop\sisgep-job-main"
Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\comunicados.php" 2>&1)
Write-Host (php -l "$base\routes\web.php" 2>&1)
Write-Host "Linhas web.php=$((Get-Content "$base\routes\web.php").Count)"
Write-Host "=== Whitelist em comunicados.php ==="
$c = Get-Content "$base\routes\comunicados.php" -Raw
Write-Host "in_array=$($c.Contains('in_array'))"
Write-Host "permitidas=$($c.Contains('permitidas'))"
Write-Host "COMUNICADO whitelist=$($c.Contains("'COMUNICADO'"))"
