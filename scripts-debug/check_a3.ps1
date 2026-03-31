$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\medicina_admin.php" 2>&1)
Write-Host (php -l "$base\routes\web.php" 2>&1)

Write-Host "=== VIEW MedicinaAdminView ==="
$p = "$base\resources\gente-v3\src\views\rh\MedicinaAdminView.vue"
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
Write-Host "Router MedicinaAdminView=$($r.Contains('MedicinaAdminView'))"
Write-Host "Sidebar medicina-admin=$($d.Contains('medicina-admin') -or $d.Contains('MedicinaAdmin'))"
Write-Host "web.php require medicina_admin=$($w.Contains('medicina_admin.php'))"
Write-Host "web.php linhas=$($w.Split([char]10).Count)"

Write-Host "=== INLINE NO web.php ==="
$wlines = $w.Split([char]10)
$hits = @()
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "Route::(get|post|put|patch|delete).*'/(exames|agendamentos|kpis)'") {
        $hits += "L$($i+1): $($wlines[$i].Trim())"
    }
}
if ($hits.Count -eq 0) { Write-Host "Nenhuma rota inline de medicina no web.php" }
else { $hits | ForEach-Object { Write-Host $_ } }
