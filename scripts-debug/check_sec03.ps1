$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\web.php" 2>&1)
Write-Host (php -l "$base\app\Console\Kernel.php" 2>&1)
$mig = Get-ChildItem "$base\database\migrations" | Where-Object { $_.Name -match "login_attempts" }
if ($mig) { Write-Host (php -l $mig.FullName 2>&1) }
else { Write-Host "MIGRATION NAO ENCONTRADA" }

Write-Host "=== web.php ==="
$wlines = Get-Content "$base\routes\web.php"
Write-Host "Linhas=$($wlines.Count)"

Write-Host "=== LOGIN_ATTEMPTS no web.php ==="
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "LOGIN_ATTEMPTS|SEC-PROD-03|bloqueado_ip|TENTATIVA_EM") {
        Write-Host "L$($i+1): $($wlines[$i].Trim())"
    }
}

Write-Host "=== Posicao — antes ou apos recaptcha? ==="
$secLines = @()
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "SEC-PROD-0[23]|recaptcha_token|LOGIN_ATTEMPTS") {
        $secLines += "L$($i+1): $($wlines[$i].Trim())"
    }
}
$secLines | ForEach-Object { Write-Host $_ }

Write-Host "=== Kernel.php — schedule ==="
$k = Get-Content "$base\app\Console\Kernel.php" -Raw
Write-Host "LOGIN_ATTEMPTS schedule=$($k.Contains('LOGIN_ATTEMPTS'))"
Write-Host "daily=$($k.Contains('daily'))"

Write-Host "=== Migration ==="
if ($mig) {
    $mc = Get-Content $mig.FullName -Raw
    Write-Host "Nome=$($mig.Name)"
    Write-Host "IP=$($mc.Contains('IP'))"
    Write-Host "SUCESSO=$($mc.Contains('SUCESSO'))"
    Write-Host "TENTATIVA_EM=$($mc.Contains('TENTATIVA_EM'))"
    Write-Host "index=$($mc.Contains('index'))"
}
