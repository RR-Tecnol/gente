$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Encontrar todos os blocos Route:: com /beneficios DENTRO do grupo dashboard
# Bloco 1 inicia em L2739 (0-based: 2738)
# Bloco 2 inicia em L4172 (0-based: 4171)
# Precisamos achar os starts exatos e ends de cada sub-bloco

$toRemove = @{}

$targets = @(2737, 4167)  # 0-based linha do comentario antes de cada Route::get('/beneficios')

foreach ($t in $targets) {
    # Achar o inicio real do Route:: a partir do target
    $routeStart = -1
    for ($i = $t; $i -le $t+5; $i++) {
        if ($lines[$i] -match "Route::(get|post|put|patch|delete).*'/beneficios'") {
            $routeStart = $i; break
        }
    }
    if ($routeStart -lt 0) { Write-Host "Route nao encontrado perto de L$($t+1)"; continue }

    # Contar chaves para achar o fechamento do Route::
    $depth = 0; $routeEnd = -1
    for ($i = $routeStart; $i -lt $lines.Count; $i++) {
        $o = ($lines[$i].ToCharArray() | Where-Object {$_ -eq '{'}).Count
        $c = ($lines[$i].ToCharArray() | Where-Object {$_ -eq '}'}).Count
        $depth += $o - $c
        if ($depth -eq 0 -and $i -gt $routeStart) { $routeEnd = $i; break }
    }

    # Verificar se ha mais Routes de beneficios apos esse
    $nextRoute = -1
    for ($i = $routeEnd+1; $i -le $routeEnd+5; $i++) {
        if ($lines[$i] -match "Route::(get|post|put|patch|delete).*'/beneficios'") {
            $nextRoute = $i; break
        }
    }

    # Calcular inicio do bloco (com comentario acima)
    $blockStart = $t
    for ($k = $t-1; $k -ge [Math]::Max(0,$t-4); $k--) {
        if ($lines[$k].Trim() -eq "" -or $lines[$k] -match "^\s*//") { $blockStart = $k }
        else { break }
    }

    Write-Host "Bloco beneficios: L$($blockStart+1) ate L$($routeEnd+1) (Route em L$($routeStart+1))"

    # Se tem mais routes de beneficios em sequencia, continuar ate o ultimo
    $finalEnd = $routeEnd
    if ($nextRoute -gt 0) {
        # Contar ate fechar o proximo Route
        $depth2 = 0; $end2 = -1
        for ($i = $nextRoute; $i -lt $lines.Count; $i++) {
            $o = ($lines[$i].ToCharArray() | Where-Object {$_ -eq '{'}).Count
            $c = ($lines[$i].ToCharArray() | Where-Object {$_ -eq '}'}).Count
            $depth2 += $o - $c
            if ($depth2 -eq 0 -and $i -gt $nextRoute) { $end2 = $i; break }
        }
        if ($end2 -gt 0) {
            $finalEnd = $end2
            Write-Host "  Extendido ate L$($finalEnd+1) (inclui Route seguinte)"
        }
    }

    for ($i = $blockStart; $i -le $finalEnd; $i++) { $toRemove[$i] = $true }
}

Write-Host "Total linhas a remover: $($toRemove.Count)"

$newLines = @()
for ($i = 0; $i -lt $lines.Count; $i++) {
    if (-not $toRemove.ContainsKey($i)) { $newLines += $lines[$i] }
}

Copy-Item $webPath "$webPath.bak10" -Force
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Concluido. Linhas: $($lines.Count) -> $($newLines.Count)"
