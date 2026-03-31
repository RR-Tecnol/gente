$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)
$toRemove = @{}

for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "^\s+Route::(get|post|put|patch|delete).*(treinamentos|/cursos|/inscricao)") {
        $depth = 0; $end = -1
        for ($j = $i; $j -lt $lines.Count; $j++) {
            $o = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '{'}).Count
            $c = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '}'}).Count
            $depth += $o - $c
            if ($depth -eq 0 -and $j -gt $i) { $end = $j; break }
        }
        if ($end -lt 0) { continue }
        $start = $i
        for ($k = $i-1; $k -ge [Math]::Max(0,$i-3); $k--) {
            if ($lines[$k].Trim() -match "^//") { $start = $k } else { break }
        }
        Write-Host "Removendo L$($start+1)-L$($end+1): $($lines[$i].Trim())"
        for ($k = $start; $k -le $end; $k++) { $toRemove[$k] = $true }
        $i = $end
    }
}

Write-Host "Total a remover: $($toRemove.Count)"
if ($toRemove.Count -eq 0) { exit }

$newLines = @()
for ($i = 0; $i -lt $lines.Count; $i++) {
    if (-not $toRemove.ContainsKey($i)) { $newLines += $lines[$i] }
}
Copy-Item $webPath "$webPath.bak" -Force
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Linhas: $($lines.Count) -> $($newLines.Count)"
