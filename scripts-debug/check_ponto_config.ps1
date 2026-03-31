$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\web.php" 2>&1)
Write-Host "Linhas=$((Get-Content "$base\routes\web.php").Count)"

Write-Host "=== Passo 1 — Migration ==="
$mig = Get-ChildItem "$base\database\migrations" | Where-Object { $_.Name -match "ponto_config" }
$mig | ForEach-Object { Write-Host "  $($_.Name)" }
if ($mig) {
    $mc = Get-Content $mig[0].FullName -Raw
    Write-Host "  INTERVALO_ALMOCO=$($mc.Contains('INTERVALO_ALMOCO'))"
    Write-Host "  JORNADA_FINANCEIRA_HORAS=$($mc.Contains('JORNADA_FINANCEIRA_HORAS'))"
    Write-Host "  JORNADA_FINANCEIRA_OBS=$($mc.Contains('JORNADA_FINANCEIRA_OBS'))"
    Write-Host "  hasColumn=$($mc.Contains('hasColumn'))"
}

Write-Host "=== Passo 2 — ApuracaoPontoService ==="
$ap = Get-Content "$base\app\Services\ApuracaoPontoService.php" -Raw
Write-Host "  PONTO_CONFIG_FUNCIONARIO=$($ap.Contains('PONTO_CONFIG_FUNCIONARIO'))"
Write-Host "  REGIME=$($ap.Contains('REGIME'))"
Write-Host "  INTERVALO_ALMOCO=$($ap.Contains('INTERVALO_ALMOCO'))"

Write-Host "=== Passo 3 — FolhaParserService ==="
$fp = Get-Content "$base\app\Services\FolhaParserService.php" -Raw
Write-Host "  JORNADA_FINANCEIRA_HORAS=$($fp.Contains('JORNADA_FINANCEIRA_HORAS'))"
Write-Host "  Log auditoria=$($fp.Contains('jornada_financeira'))"

Write-Host "=== Passo 4 — PUT admin endpoint ==="
$wlines = Get-Content "$base\routes\web.php"
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "JORNADA_FINANCEIRA|admin.*jornada|403.*jornada") {
        Write-Host "  L$($i+1): $($wlines[$i].Trim())"
    }
}

Write-Host "=== Passo 5 — GET /ponto/config response ==="
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "ponto/config" -and $wlines[$i] -match "Route::get") {
        Write-Host "  GET config em L$($i+1)"
        for ($j = $i; $j -le [Math]::Min($i+15, $wlines.Count-1); $j++) {
            if ($wlines[$j] -match "intervalo_almoco|jornada_financeira") {
                Write-Host "  L$($j+1): $($wlines[$j].Trim())"
            }
        }
    }
}

Write-Host "=== Passo 6 — ConfiguracoesView.vue ==="
$cv = Get-Content "$base\resources\gente-v3\src\views\config\ConfiguracoesView.vue" -Raw
Write-Host "  INTERVALO_ALMOCO=$($cv.Contains('INTERVALO_ALMOCO'))"
Write-Host "  JORNADA_FINANCEIRA=$($cv.Contains('JORNADA_FINANCEIRA'))"
Write-Host "  isAdmin=$($cv.Contains('isAdmin'))"
Write-Host "  badge visual=$($cv.Contains('badge') -or $cv.Contains('acordo'))"
