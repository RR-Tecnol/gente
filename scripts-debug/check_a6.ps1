$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\pesquisa.php" 2>&1)
Write-Host (php -l "$base\routes\ouvidoria_admin.php" 2>&1)
Write-Host (php -l "$base\routes\relatorios.php" 2>&1)
Write-Host (php -l "$base\routes\web.php" 2>&1)

Write-Host "=== web.php ==="
$wn = (Get-Content "$base\routes\web.php").Count
Write-Host "Linhas=$wn"
$w = Get-Content "$base\routes\web.php" -Raw
Write-Host "require pesquisa=$($w.Contains('pesquisa.php'))"
Write-Host "require ouvidoria_admin=$($w.Contains('ouvidoria_admin.php'))"
Write-Host "require relatorios=$($w.Contains('relatorios.php'))"

Write-Host "=== Inline no web.php ==="
$wlines = Get-Content "$base\routes\web.php"
$hits = 0
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "Route::(get|post|put|patch|delete).*(pesquisa|ouvidoria/admin|relatorio)") {
        Write-Host "L$($i+1): $($wlines[$i].Trim())"
        $hits++
    }
}
if ($hits -eq 0) { Write-Host "Nenhuma rota inline dos novos modulos" }

Write-Host "=== Conteudo dos modulos ==="
$p = Get-Content "$base\routes\pesquisa.php" -Raw
Write-Host "PESQUISA=$($p.Contains('PESQUISA'))"
Write-Host "PESQUISA_RESPOSTA=$($p.Contains('PESQUISA_RESPOSTA'))"
Write-Host "hasTable=$($p.Contains('hasTable'))"
Write-Host "formato=csv=$($p.Contains('formato'))"

$o = Get-Content "$base\routes\ouvidoria_admin.php" -Raw
Write-Host "ouvidoria/admin=$($o.Contains('ouvidoria/admin') -or $o.Contains('/admin'))"
Write-Host "protocolo=$($o.Contains('protocolo'))"
Write-Host "responder=$($o.Contains('responder'))"

$r = Get-Content "$base\routes\relatorios.php" -Raw
Write-Host "quadro-servidores=$($r.Contains('quadro-servidores'))"
Write-Host "lrf-pessoal=$($r.Contains('lrf'))"
Write-Host "UTF-8 BOM=$($r.Contains('BOM') -or $r.Contains('feff') -or $r.Contains('utf-8'))"
