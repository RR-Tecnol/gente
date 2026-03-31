$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)
Write-Host "=== ESTADO FINAL web.php ==="
Write-Host "Total linhas: $($lines.Count)"
Write-Host ""

# Encontrar todos os Route::prefix('api/v3') e classificar
Write-Host "=== BLOCOS Route::prefix('api/v3') ==="
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "Route::prefix\('api/v3'\)") {
        # Olhar 3 linhas abaixo para classificar
        $preview = ""
        for ($j = $i+1; $j -le [Math]::Min($i+3, $lines.Count-1); $j++) {
            $preview += $lines[$j].Trim() + " | "
        }
        Write-Host "L$($i+1): $($preview.Substring(0, [Math]::Min(100, $preview.Length)))"
    }
}
Write-Host ""

# Contar quantas vezes cada endpoint aparece
Write-Host "=== ENDPOINTS /declaracoes ==="
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "Route::(get|post|put|delete|patch).*'/declaracoes'") {
        Write-Host "L$($i+1): $($lines[$i].Trim())"
    }
}

Write-Host ""
Write-Host "=== ENDPOINTS /comunicados ==="
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "Route::(get|post|put|delete|patch).*'/comunicados'") {
        Write-Host "L$($i+1): $($lines[$i].Trim())"
    }
}

Write-Host ""
Write-Host "=== ENDPOINTS /perfil ==="
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "Route::(get|post|put|delete|patch).*'/perfil'") {
        Write-Host "L$($i+1): $($lines[$i].Trim())"
    }
}
