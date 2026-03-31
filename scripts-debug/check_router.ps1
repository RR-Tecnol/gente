$base = "C:\Users\joaob\Desktop\sisgep-job-main\resources\gente-v3\src"

$router = [System.IO.File]::ReadAllText("$base\router\index.js", [System.Text.Encoding]::UTF8)
Write-Host "Router AvaliacaoGestor: $($router.Contains('AvaliacaoGestorView'))"
Write-Host "Router roles gestor   : $($router.Contains('gestor'))"

$sidebar = Get-ChildItem -Recurse -Path "$base\components" -Filter "*.vue" |
    Where-Object { $_.Name -match "Sidebar|Layout|Menu|Nav" }
foreach ($f in $sidebar) {
    $c = [System.IO.File]::ReadAllText($f.FullName, [System.Text.Encoding]::UTF8)
    $has = $c.Contains('AvaliacaoGestor') -or $c.Contains('avaliacao-gestor') -or $c.Contains('Avaliacoes da Equipe')
    Write-Host "Sidebar $($f.Name): mencionado=$has"
}
