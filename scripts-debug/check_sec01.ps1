$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\app\Http\Middleware\SecurityHeaders.php" 2>&1)
Write-Host (php -l "$base\app\Http\Kernel.php" 2>&1)
Write-Host (php -l "$base\routes\web.php" 2>&1)

Write-Host "=== SecurityHeaders.php ==="
$exists = Test-Path "$base\app\Http\Middleware\SecurityHeaders.php"
Write-Host "Existe=$exists"
if ($exists) {
    $m = [System.IO.File]::ReadAllText("$base\app\Http\Middleware\SecurityHeaders.php", [System.Text.Encoding]::UTF8)
    $mn = $m.Split([char]10).Count
    Write-Host "Linhas=$mn"
    Write-Host "X-Frame-Options=$($m.Contains('X-Frame-Options'))"
    Write-Host "X-Content-Type=$($m.Contains('X-Content-Type-Options'))"
    Write-Host "Referrer-Policy=$($m.Contains('Referrer-Policy'))"
    Write-Host "Permissions-Policy=$($m.Contains('Permissions-Policy'))"
    Write-Host "CSP=$($m.Contains('Content-Security-Policy'))"
    Write-Host "HSTS comentado=$($m.Contains('Strict-Transport-Security'))"
    Write-Host "Remove X-Powered-By=$($m.Contains('X-Powered-By'))"
}

Write-Host "=== Kernel.php ==="
$k = [System.IO.File]::ReadAllText("$base\app\Http\Kernel.php", [System.Text.Encoding]::UTF8)
Write-Host "SecurityHeaders=$($k.Contains('SecurityHeaders'))"

Write-Host "=== web.php ==="
$wn = (Get-Content "$base\routes\web.php").Count
Write-Host "Linhas=$wn"
