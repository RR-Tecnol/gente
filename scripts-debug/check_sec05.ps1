$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\app\Http\Middleware\ValidateFileUpload.php" 2>&1)
Write-Host (php -l "$base\app\Http\Kernel.php" 2>&1)
Write-Host (php -l "$base\routes\web.php" 2>&1)

Write-Host "=== ValidateFileUpload.php ==="
$m = Get-Content "$base\app\Http\Middleware\ValidateFileUpload.php" -Raw
Write-Host "Existe=$((Test-Path "$base\app\Http\Middleware\ValidateFileUpload.php"))"
Write-Host "mime_content_type=$($m.Contains('mime_content_type'))"
Write-Host "getSize=$($m.Contains('getSize'))"
Write-Host "extensao dupla=$($m.Contains('php') -and $m.Contains('explode'))"
Write-Host "10MB=$($m.Contains('10') -and $m.Contains('1024'))"
Write-Host "Linhas=$((Get-Content "$base\app\Http\Middleware\ValidateFileUpload.php").Count)"

Write-Host "=== Kernel.php upload.safe ==="
$k = Get-Content "$base\app\Http\Kernel.php" -Raw
Write-Host "upload.safe=$($k.Contains('upload.safe'))"
Write-Host "ValidateFileUpload=$($k.Contains('ValidateFileUpload'))"
Write-Host "routeMiddleware=$($k.Contains('routeMiddleware'))"

Write-Host "=== web.php linhas e upload.safe ==="
$wlines = Get-Content "$base\routes\web.php"
Write-Host "Linhas=$($wlines.Count)"
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "upload\.safe") {
        Write-Host "L$($i+1): $($wlines[$i].Trim())"
    }
}

Write-Host "=== upload.safe nos modulos ==="
$mods = @("atestados_v3.php","atestados.php","declaracoes.php")
foreach ($mod in $mods) {
    $p = "$base\routes\$mod"
    if (Test-Path $p) {
        $mc = Get-Content $p -Raw
        Write-Host "$mod upload.safe=$($mc.Contains('upload.safe'))"
    }
}
