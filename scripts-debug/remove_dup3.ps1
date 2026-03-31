$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)
$toRemove = @{}

# Remover apenas blocos pequenos (< 20 inline routes) que sao SOMENTE decl ou comunicados
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -eq "Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {") {
        $depth = 0; $end = -1
        for ($j = $i; $j -lt $lines.Count; $j++) {
            $o = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '{'}).Count
            $c = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '}'}).Count
            $depth += $o - $c
            if ($depth -eq 0 -and $j -gt $i) { $end = $j; break }
        }
        if ($end -lt 0) { continue }
        $blockSize = $end - $i

        # Ignorar blocos grandes (dashboard tem > 1600 linhas)
        if ($blockSize -gt 200) {
            Write-Host "Ignorando bloco grande L$($i+1)-L$($end+1) ($blockSize linhas)"
            continue
        }

        $inner = $lines[($i+1)..($end-1)]
        $hasInline  = ($inner | Where-Object { $_ -match "^\s+Route::" }).Count
        $hasRequire = ($inner | Where-Object { $_ -match "require" }).Count
        $firstRoute = ($inner | Where-Object { $_ -match "^\s+Route::(get|post)" } | Select-Object -First 1)

        # Remover apenas se: sem require E primeiro endpoint eh decl ou comunicados
        if ($hasInline -gt 0 -and $hasRequire -eq 0 -and
            ($firstRoute -match "'/declaracoes'" -or $firstRoute -match "'/comunicados'")) {
            $start = $i
            for ($k = $i-1; $k -ge [Math]::Max(0,$i-6); $k--) {
                if ($lines[$k] -match "^//|^$") { $start = $k } else { break }
            }
            for ($k = $start; $k -le $end; $k++) { $toRemove[$k] = $true }
            Write-Host "REMOVENDO L$($start+1)-L$($end+1): $firstRoute"
        } else {
            Write-Host "Mantendo L$($i+1)-L$($end+1) ($blockSize linhas, inline=$hasInline req=$hasRequire)"
        }
    }
}

Write-Host ""
Write-Host "Linhas a remover: $($toRemove.Count)"
if ($toRemove.Count -eq 0) { Write-Host "Nada a remover."; exit }

$newLines = @()
for ($i = 0; $i -lt $lines.Count; $i++) {
    if (-not $toRemove.ContainsKey($i)) { $newLines += $lines[$i] }
}

Copy-Item $webPath "$webPath.bak5" -Force
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Concluido. Linhas: $($lines.Count) -> $($newLines.Count)"
