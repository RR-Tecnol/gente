$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\treinamentos.php" 2>&1)
Write-Host (php -l "$base\routes\web.php" 2>&1)

Write-Host "=== VIEW TreinamentosAdminView ==="
$p = "$base\resources\gente-v3\src\views\rh\TreinamentosAdminView.vue"
if (Test-Path $p) {
    $c = [System.IO.File]::ReadAllText($p, [System.Text.Encoding]::UTF8)
    $n = $c.Split([char]10).Count
    Write-Host "Linhas=$n template=$($c.Contains('</template>')) script=$($c.Contains('</script>')) style=$($c.Contains('</style>'))"
    Write-Host "api=$($c.Contains('api/v3')) hero=$($c.Contains('hero')) modal=$($c.Contains('modal')) tabs=$($c.Contains('tab'))"
} else { Write-Host "ARQUIVO NAO EXISTE" }

Write-Host "=== ECOSSISTEMA ==="
$r = [System.IO.File]::ReadAllText("$base\resources\gente-v3\src\router\index.js", [System.Text.Encoding]::UTF8)
$d = [System.IO.File]::ReadAllText("$base\resources\gente-v3\src\layouts\DashboardLayout.vue", [System.Text.Encoding]::UTF8)
$w = [System.IO.File]::ReadAllText("$base\routes\web.php", [System.Text.Encoding]::UTF8)
Write-Host "Router TreinamentosAdminView=$($r.Contains('TreinamentosAdminView'))"
Write-Host "Sidebar treinamentos=$($d.Contains('treinamentos-admin') -or $d.Contains('Treinamentos'))"
Write-Host "web.php require treinamentos=$($w.Contains('treinamentos.php'))"
$wn = $w.Split([char]10).Count
Write-Host "web.php linhas=$wn"

Write-Host "=== INLINE TREINAMENTOS no web.php ==="
$wlines = $w.Split([char]10)
$hits = 0
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "Route::(get|post|put|patch|delete).*(treinamento|cursos|inscricao)") {
        Write-Host "L$($i+1): $($wlines[$i].Trim())"
        $hits++
    }
}
if ($hits -eq 0) { Write-Host "Nenhuma rota inline de treinamentos no web.php" }

Write-Host "=== BACKEND treinamentos.php ==="
$t = [System.IO.File]::ReadAllText("$base\routes\treinamentos.php", [System.Text.Encoding]::UTF8)
Write-Host "TREINAMENTO=$($t.Contains('TREINAMENTO'))"
Write-Host "TREINAMENTO_INSCRICAO=$($t.Contains('TREINAMENTO_INSCRICAO'))"
Write-Host "Schema::hasTable=$($t.Contains('Schema::hasTable'))"
Write-Host "Linhas=$($t.Split([char]10).Count)"
