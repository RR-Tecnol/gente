$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\beneficios.php" 2>&1)
Write-Host (php -l "$base\routes\web.php" 2>&1)

Write-Host "=== VIEW ==="
$p = "$base\resources\gente-v3\src\views\rh\BeneficiosAdminView.vue"
if (Test-Path $p) {
    $c = [System.IO.File]::ReadAllText($p, [System.Text.Encoding]::UTF8)
    $n = $c.Split([char]10).Count
    Write-Host "Linhas=$n template=$($c.Contains('</template>')) script=$($c.Contains('</script>')) style=$($c.Contains('</style>'))"
    Write-Host "api=$($c.Contains('api/v3/beneficios')) hero=$($c.Contains('hero')) modal=$($c.Contains('modal')) tabs=$($c.Contains('tab'))"
} else { Write-Host "ARQUIVO NAO EXISTE" }

Write-Host "=== ECOSSISTEMA ==="
$r = [System.IO.File]::ReadAllText("$base\resources\gente-v3\src\router\index.js", [System.Text.Encoding]::UTF8)
$d = [System.IO.File]::ReadAllText("$base\resources\gente-v3\src\layouts\DashboardLayout.vue", [System.Text.Encoding]::UTF8)
$w = [System.IO.File]::ReadAllText("$base\routes\web.php", [System.Text.Encoding]::UTF8)
$b = [System.IO.File]::ReadAllText("$base\routes\beneficios.php", [System.Text.Encoding]::UTF8)
Write-Host "Router BeneficiosAdminView=$($r.Contains('BeneficiosAdminView'))"
Write-Host "Sidebar beneficios=$($d.Contains('beneficio') -or $d.Contains('Beneficio'))"
Write-Host "web.php require=$($w.Contains('beneficios.php'))"
Write-Host "Schema::hasTable=$($b.Contains('Schema::hasTable')) Schema::create=$($b.Contains('Schema::create'))"
Write-Host "BENEFICIO=$($b.Contains('BENEFICIO')) FUNC_BEN=$($b.Contains('FUNCIONARIO_BENEFICIO'))"
$bn = $b.Split([char]10).Count
Write-Host "Linhas beneficios.php=$bn"
