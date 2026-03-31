$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\seguranca_trabalho.php" 2>&1)
Write-Host (php -l "$base\routes\web.php" 2>&1)

Write-Host "=== VIEW SegurancaAdminView ==="
$p = "$base\resources\gente-v3\src\views\rh\SegurancaAdminView.vue"
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
Write-Host "Router SegurancaAdminView=$($r.Contains('SegurancaAdminView'))"
Write-Host "Sidebar seguranca-admin=$($d.Contains('seguranca-admin'))"
Write-Host "web.php require seguranca=$($w.Contains('seguranca_trabalho.php'))"
$wn = $w.Split([char]10).Count
Write-Host "web.php linhas=$wn"

Write-Host "=== INLINE SEGURANCA no web.php ==="
$wlines = $w.Split([char]10)
$hits = 0
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "Route::(get|post|put|patch|delete).*(epi|acidente|laudo|seguranca)") {
        Write-Host "L$($i+1): $($wlines[$i].Trim())"
        $hits++
    }
}
if ($hits -eq 0) { Write-Host "Nenhuma rota inline de seguranca no web.php" }

Write-Host "=== AUTO-MIGRATION seguranca_trabalho.php ==="
$s = [System.IO.File]::ReadAllText("$base\routes\seguranca_trabalho.php", [System.Text.Encoding]::UTF8)
Write-Host "EPI_REGISTRO=$($s.Contains('EPI_REGISTRO'))"
Write-Host "ACIDENTE_TRABALHO=$($s.Contains('ACIDENTE_TRABALHO'))"
Write-Host "LAUDO_SST=$($s.Contains('LAUDO_SST'))"
Write-Host "Schema::hasTable=$($s.Contains('Schema::hasTable'))"
$sn = $s.Split([char]10).Count
Write-Host "Linhas seguranca_trabalho.php=$sn"
