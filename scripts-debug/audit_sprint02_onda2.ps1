$base = 'C:\Users\joaob\Desktop\sisgep-job-main'

Write-Host '=== O2-01 Sobreaviso — push apos await ==='
$sob = Get-Content "$base\resources\gente-v3\src\views\ponto\EscalaSobreavisoView.vue" -Raw
$lines = $sob -split "`n"
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match '\.push\(') {
        $ctx = $lines[[Math]::Max(0,$i-3)..$i] -join ' | '
        Write-Host "L$($i+1) push: $ctx"
    }
}

Write-Host ''
Write-Host '=== O2-01 PlantoesExtras — push apos await ==='
$plt = Get-Content "$base\resources\gente-v3\src\views\ponto\PlantoesExtrasView.vue" -Raw
$lines2 = $plt -split "`n"
for ($i = 0; $i -lt $lines2.Count; $i++) {
    if ($lines2[$i] -match '\.push\(') {
        $ctx = $lines2[[Math]::Max(0,$i-3)..$i] -join ' | '
        Write-Host "L$($i+1) push: $ctx"
    }
}

Write-Host ''
Write-Host '=== O2-02 SESMT medicina_admin kpis — colunas usadas ==='
$med = Get-Content "$base\routes\medicina_admin.php" -Raw
$idx = $med.IndexOf('/kpis')
if ($idx -ge 0) {
    $snippet = $med.Substring($idx, [Math]::Min(600, $med.Length - $idx))
    Write-Host $snippet
}

Write-Host ''
Write-Host '=== O2-03 Icones ?? nos arquivos ==='
$vueFiles = @(
    'resources\gente-v3\src\views\rh\FeriasLicencasView.vue',
    'resources\gente-v3\src\views\ponto\BancoHorasView.vue',
    'resources\gente-v3\src\views\rh\FuncionariosView.vue',
    'resources\gente-v3\src\views\rh\AutocadastroGestaoView.vue'
)
foreach ($f in $vueFiles) {
    $content = Get-Content "$base\$f" -Raw 2>$null
    if (-not $content) { Write-Host "MISSING: $f"; continue }
    if ($content -match '\?\?') { Write-Host "?? FOUND: $f" }
    else { Write-Host "OK no ??: $f" }
}

Write-Host ''
Write-Host 'DONE'
