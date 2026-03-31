$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Remover L3553-L3561 (0-based: 3552-3560) - fragmento orfao de cargos
$removeStart = 3552  # 0-based
$removeEnd   = 3560  # 0-based

Write-Host "Removendo L$($removeStart+1) ate L$($removeEnd+1)"
for ($i = $removeStart; $i -le $removeEnd; $i++) {
    Write-Host "  $($lines[$i])"
}

$newLines = $lines[0..($removeStart-1)] + $lines[($removeEnd+1)..($lines.Count-1)]
Copy-Item $webPath "$webPath.bak8" -Force
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Concluido. Linhas: $($lines.Count) -> $($newLines.Count)"
