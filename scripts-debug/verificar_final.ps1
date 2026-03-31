$base = "C:\Users\joaob\Desktop\sisgep-job-main"
$webPath = "$base\routes\web.php"
$viewsPath = "$base\resources\gente-v3\src\views"

Write-Host "LINT web.php:"
Write-Host (php -l $webPath 2>&1)

$lines = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)
Write-Host "Linhas web.php: $($lines.Count)"

$decl  = ($lines | Where-Object { $_ -match "declaracoes" -and $_ -match "Route::" }).Count
$comun = ($lines | Where-Object { $_ -match "comunicados" -and $_ -match "Route::" }).Count
$perf  = ($lines | Where-Object { $_ -match "'/perfil'" -and $_ -match "Route::" }).Count
$feri  = ($lines | Where-Object { $_ -match "'/ferias'" -and $_ -match "Route::" }).Count
Write-Host "Rotas web.php: decl=$decl comun=$comun perfil=$perf ferias=$feri"

Write-Host "Chamadas Vue:"
$paths = @("medicina","ouvidoria","comunicados","perfil","cargos","ferias","ponto","gestor","declaracoes")
foreach ($p in $paths) {
    $n = (Get-ChildItem -Recurse -Path $viewsPath -Filter "*.vue" |
        Select-String -Pattern "api/v3/$p" -ErrorAction SilentlyContinue).Count
    Write-Host "  $p => $n views"
}
