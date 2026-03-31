$base = "C:\Users\joaob\Desktop\sisgep-job-main"
Write-Host "=== LINT ==="
Write-Host (php -l "$base\app\Console\Kernel.php" 2>&1)
Write-Host "=== Kernel arquivamento ==="
$k = Get-Content "$base\app\Console\Kernel.php" -Raw
Write-Host "monthlyOn=$($k.Contains('monthlyOn'))"
Write-Host "gzopen=$($k.Contains('gzopen'))"
Write-Host "quarterly=$($k.Contains('quarterly'))"
Write-Host "arquivo=$($k.Contains('arquivo'))"
