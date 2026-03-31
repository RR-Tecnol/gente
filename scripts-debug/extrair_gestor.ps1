# Extrai e substitui o bloco do PORTAL DO GESTOR pelo require
# O bloco comeca na linha 4455 (0-based: 4454) e fecha onde o Route::prefix fecha

$webPath   = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$routesDir = "C:\Users\joaob\Desktop\sisgep-job-main\routes"
$lines     = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Encontrar a linha exata do cabecalho do bloco PORTAL DO GESTOR
$startComment = -1
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "API V3.*PORTAL DO GESTOR") {
        # Volta 1 linha para pegar o // de cima
        $startComment = $i - 1
        break
    }
}
Write-Host "Bloco GESTOR encontrado em: linha $($startComment + 1) (1-based)"

# A partir do startComment, achar o Route::prefix e contar chaves ate fechar
$routeStart = -1
for ($i = $startComment; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "Route::prefix\('api/v3'\)") {
        $routeStart = $i
        break
    }
}
Write-Host "Route::prefix em: linha $($routeStart + 1)"

# Contar chaves a partir do routeStart
$depth = 0
$routeEnd = -1
for ($i = $routeStart; $i -lt $lines.Count; $i++) {
    $open  = ($lines[$i].ToCharArray() | Where-Object { $_ -eq '{' }).Count
    $close = ($lines[$i].ToCharArray() | Where-Object { $_ -eq '}' }).Count
    $depth += $open - $close
    if ($depth -eq 0 -and $i -gt $routeStart) {
        $routeEnd = $i
        break
    }
}
Write-Host "Bloco fecha em: linha $($routeEnd + 1)"
Write-Host "Tamanho do bloco: $($routeEnd - $routeStart + 1) linhas"

# Extrair conteudo interno (sem o Route::prefix e o }); externo) para gestor.php
$inner = @()
for ($i = $routeStart + 1; $i -lt $routeEnd; $i++) {
    $ln = $lines[$i]
    if ($ln.Length -ge 4) { $inner += $ln.Substring(4) } else { $inner += $ln }
}

$hdr = @(
    "<?php",
    "// PORTAL DO GESTOR + PONTO CONFIG + HOLERITES + COMUNICADOS INTERNOS",
    "// Extraido de web.php - herda prefix api/v3 + auth do grupo principal",
    ""
)
[System.IO.File]::WriteAllLines("$routesDir\gestor.php", ($hdr + $inner), [System.Text.Encoding]::UTF8)
Write-Host "gestor.php reescrito com $($inner.Count) linhas"

# Substituir no web.php: linhas startComment ate routeEnd por 4 linhas de require
$before = $lines[0..($startComment - 1)]
$replacement = @(
    "",
    "//",
    "//  API V3  PORTAL DO GESTOR",
    "//",
    "Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {",
    "    require __DIR__ . '/gestor.php';",
    "});"
)
$after = $lines[($routeEnd + 1)..($lines.Count - 1)]

$newLines = $before + $replacement + $after
Write-Host "Linhas antes: $($lines.Count) | Depois: $($newLines.Count)"

# Backup
Copy-Item $webPath "$webPath.bak2" -Force
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "web.php atualizado. Backup: web.php.bak2"
