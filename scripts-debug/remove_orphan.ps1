$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Remover linhas 3552-3568 inclusive (0-based: 3551-3567)
$removeStart = 3551  # 0-based
$removeEnd   = 3567  # 0-based inclusive

$newLines = $lines[0..($removeStart-1)] + $lines[($removeEnd+1)..($lines.Count-1)]

Copy-Item $webPath "$webPath.bak6" -Force
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Removidas $($removeEnd - $removeStart + 1) linhas. Total: $($newLines.Count)"
