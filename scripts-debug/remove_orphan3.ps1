$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Remover L3562-L8021 (0-based: 3561-8020) - codigo orfao das rotas extraidas
$removeStart = 3561  # 0-based (L3562)
$removeEnd   = 8020  # 0-based (L8021)

Write-Host "Removendo L$($removeStart+1) ate L$($removeEnd+1) ($($removeEnd-$removeStart+1) linhas)"
Write-Host "L$($removeStart+1): $($lines[$removeStart])"
Write-Host "L$($removeEnd+1): $($lines[$removeEnd])"
Write-Host "L$($removeEnd+2): $($lines[$removeEnd+1])"

$newLines = $lines[0..($removeStart-1)] + $lines[($removeEnd+1)..($lines.Count-1)]

Copy-Item $webPath "$webPath.bak7" -Force
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Concluido. Linhas: $($lines.Count) -> $($newLines.Count)"
