$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)
$toRemove = @{}

# Remover bloco L4184-L4283 (declaracoes duplicado)
# Remover bloco L4536-L4669 (comunicados duplicado)
# Os ranges sao 0-based

# Bloco declaracoes: começa em L4184 (0-based: 4183), fecha em L4283 (0-based: 4282)
# Incluir comentario de cabecalho acima (ate 4 linhas)
$targets = @(
    @{ Start = 4180; End = 4283 },  # declaracoes + header
    @{ Start = 4532; End = 4670 }   # comunicados + header (ajustar apos remover declaracoes)
)

# Como remover o primeiro bloco vai deslocar os indices, procuramos pelos padroes diretamente
$removeRanges = @()

# Encontrar bloco declaracoes standalone (inline, sem require)
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

        $inner = $lines[($i+1)..($end-1)]
        $hasInline  = ($inner | Where-Object { $_ -match "^\s+Route::" }).Count
        $hasRequire = ($inner | Where-Object { $_ -match "require" }).Count
        $isDecl = ($inner | Where-Object { $_ -match "'/declaracoes'" -and $_ -match "Route::" }).Count
        $isComun = ($inner | Where-Object { $_ -match "'/comunicados'" -and $_ -match "Route::" }).Count

        # Somente blocos puros (sem require) que sao declaracoes ou comunicados
        if ($hasInline -gt 0 -and $hasRequire -eq 0 -and ($isDecl -gt 0 -or $isComun -gt 0)) {
            # Subir para pegar o header
            $start = $i
            for ($k = $i-1; $k -ge [Math]::Max(0,$i-6); $k--) {
                if ($lines[$k] -match "^//|^$") { $start = $k } else { break }
            }
            $removeRanges += [PSCustomObject]@{ Start=$start; End=$end }
            Write-Host "Marcado para remover: L$($start+1) ate L$($end+1) (inline=$hasInline, decl=$isDecl, comun=$isComun)"
        }
    }
}

# Marcar todas as linhas dos ranges
foreach ($r in $removeRanges) {
    for ($i = $r.Start; $i -le $r.End; $i++) { $toRemove[$i] = $true }
}

Write-Host "Linhas a remover: $($toRemove.Count)"

$newLines = @()
for ($i = 0; $i -lt $lines.Count; $i++) {
    if (-not $toRemove.ContainsKey($i)) { $newLines += $lines[$i] }
}

Copy-Item $webPath "$webPath.bak4" -Force
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Concluido. Linhas finais: $($newLines.Count) (removidas: $($lines.Count - $newLines.Count))"
