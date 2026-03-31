$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Contar chaves do bloco dashboard (L1850, 0-based: 1849)
$start = 1849
$depth = 0; $end = -1
for ($i = $start; $i -lt $lines.Count; $i++) {
    $o = ($lines[$i].ToCharArray() | Where-Object {$_ -eq '{'}).Count
    $c = ($lines[$i].ToCharArray() | Where-Object {$_ -eq '}'}).Count
    $depth += $o - $c
    if ($depth -eq 0 -and $i -gt $start) { $end = $i; break }
}

if ($end -lt 0) {
    Write-Host "BLOCO NAO FECHA - depth atual: $depth"
    Write-Host "Total linhas: $($lines.Count)"
    Write-Host "Ultima linha: $($lines[$lines.Count-1])"
} else {
    Write-Host "Bloco fecha em L$($end+1): $($lines[$end])"
}

# Mostrar linhas 3545-3560
Write-Host ""
Write-Host "=== Contexto L3545-3560 ==="
for ($i = 3544; $i -le [Math]::Min(3559, $lines.Count-1); $i++) {
    Write-Host "L$($i+1): $($lines[$i])"
}
