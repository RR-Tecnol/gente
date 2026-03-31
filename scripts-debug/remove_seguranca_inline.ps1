$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)
$toRemove = @{}

# Encontrar blocos Route:: de seguranca inline (L3666-3742, 0-based: 3665-3741)
# Procurar todos os Routes de /seguranca ou /epi ou /incidente inline no bloco dashboard
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "^\s+Route::(get|post|put|patch|delete).*(seguranca|/epis|/incidentes|/laudos)") {
        # Contar chaves para achar o fechamento deste Route
        $depth = 0; $end = -1
        for ($j = $i; $j -lt $lines.Count; $j++) {
            $o = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '{'}).Count
            $c = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '}'}).Count
            $depth += $o - $c
            if ($depth -eq 0 -and $j -gt $i) { $end = $j; break }
        }
        if ($end -lt 0) { continue }

        # Incluir comentario acima se existir
        $start = $i
        for ($k = $i-1; $k -ge [Math]::Max(0,$i-3); $k--) {
            if ($lines[$k].Trim() -match "^//") { $start = $k } else { break }
        }

        Write-Host "Removendo L$($start+1)-L$($end+1): $($lines[$i].Trim())"
        for ($k = $start; $k -le $end; $k++) { $toRemove[$k] = $true }
        $i = $end  # pular para apos o bloco
    }
}

Write-Host "Total linhas a remover: $($toRemove.Count)"
if ($toRemove.Count -eq 0) { Write-Host "Nada a remover."; exit }

$newLines = @()
for ($i = 0; $i -lt $lines.Count; $i++) {
    if (-not $toRemove.ContainsKey($i)) { $newLines += $lines[$i] }
}

Copy-Item $webPath "$webPath.bak11" -Force
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Concluido. Linhas: $($lines.Count) -> $($newLines.Count)"
