$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Encontrar o grupo principal (comeca em L668, 0-based: 667)
# e contar chaves para achar onde fecha
$mainStart = 667  # 0-based
$depth = 0; $mainEnd = -1
for ($i = $mainStart; $i -lt $lines.Count; $i++) {
    $o = ($lines[$i].ToCharArray() | Where-Object {$_ -eq '{'}).Count
    $c = ($lines[$i].ToCharArray() | Where-Object {$_ -eq '}'}).Count
    $depth += $o - $c
    if ($depth -eq 0 -and $i -gt $mainStart) {
        $mainEnd = $i
        break
    }
}
Write-Host "Grupo principal: L$($mainStart+1) ate L$($mainEnd+1)"
Write-Host "Linha fechamento: $($lines[$mainEnd])"
Write-Host ""

# Mostrar as 5 linhas apos o fechamento
Write-Host "=== 5 linhas apos fechamento do grupo principal ==="
for ($i = $mainEnd+1; $i -le [Math]::Min($mainEnd+5, $lines.Count-1); $i++) {
    Write-Host "L$($i+1): $($lines[$i])"
}
Write-Host ""

# Mapear todos os Route::prefix raiz apos o grupo principal
Write-Host "=== Route::prefix standalone apos grupo principal ==="
for ($i = $mainEnd+1; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "^Route::prefix") {
        $depth2 = 0; $end2 = -1
        for ($j = $i; $j -lt $lines.Count; $j++) {
            $o = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '{'}).Count
            $c = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '}'}).Count
            $depth2 += $o - $c
            if ($depth2 -eq 0 -and $j -gt $i) { $end2 = $j; break }
        }
        $inner = if ($end2 -gt 0) { $lines[($i+1)..($end2-1)] | Where-Object {$_.Trim() -ne ""} } else { @() }
        $hasReq = ($inner | Where-Object {$_ -match "require"}).Count
        $hasInl = ($inner | Where-Object {$_ -match "^\s+Route::"}).Count
        $preview = ($inner | Where-Object {$_ -match "Route::|require"} | Select-Object -First 1)
        Write-Host "L$($i+1)-L$($end2+1): req=$hasReq inline=$hasInl | $($preview)"
    }
}
