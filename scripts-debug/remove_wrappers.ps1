# Remove wrappers standalone duplicados do web.php
# Mantém blocos com código inline real; remove apenas os que só contem require

$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)
$total   = $lines.Count
Write-Host "Linhas iniciais: $total"

# Identificar ranges a remover: blocos Route::prefix+auth que so contem require
$toRemove = @{}

for ($i = 0; $i -lt $lines.Count; $i++) {
    # So processa blocos com middleware auth (nao o autocadastro web-only)
    if ($lines[$i] -match "Route::prefix\('api/v3'\)->middleware\(\['web', 'auth'\]\)->group") {
        # Contar chaves pra achar o fechamento
        $depth = 0; $end = -1
        for ($j = $i; $j -lt $lines.Count; $j++) {
            $open  = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '{'}).Count
            $close = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '}'}).Count
            $depth += $open - $close
            if ($depth -eq 0 -and $j -gt $i) { $end = $j; break }
        }
        if ($end -lt 0) { continue }

        # Verificar se o bloco contem APENAS requires (sem Route:: inline)
        $innerLines = $lines[($i+1)..($end-1)]
        $hasInlineRoute = $innerLines | Where-Object { $_ -match "Route::" }
        $hasRequire     = $innerLines | Where-Object { $_ -match "require" }

        if ($hasRequire -and -not $hasInlineRoute) {
            # Bloco puro de requires — marcar para remover (incluindo comentario acima)
            $blockStart = $i
            # Voltar até 3 linhas para pegar o comentario
            for ($k = $i-1; $k -ge [Math]::Max(0,$i-4); $k--) {
                if ($lines[$k] -match "^\s*//|^\s*$") { $blockStart = $k } else { break }
            }
            for ($k = $blockStart; $k -le $end; $k++) { $toRemove[$k] = $true }
            Write-Host "  Removendo linhas $($blockStart+1) a $($end+1): $($innerLines | Where-Object {$_ -match 'require'})"
        }
    }
}

Write-Host "Total linhas a remover: $($toRemove.Count)"

# Reconstruir arquivo sem as linhas marcadas
$newLines = @()
for ($i = 0; $i -lt $lines.Count; $i++) {
    if (-not $toRemove.ContainsKey($i)) { $newLines += $lines[$i] }
}

Copy-Item $webPath "$webPath.bak3" -Force
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Concluido. Linhas finais: $($newLines.Count) (removidas: $($total - $newLines.Count))"
