$base = 'C:\Users\joaob\Desktop\sisgep-job-main'

Write-Host '=== O3-01 afastamentos_v3.php ==='
if (Test-Path "$base\routes\afastamentos_v3.php") {
    $n = (Get-Content "$base\routes\afastamentos_v3.php").Count
    Write-Host "EXISTS $n lines"
} else { Write-Host 'MISSING' }

Write-Host ''
Write-Host '=== O3-02 escala_trabalho.php ==='
if (Test-Path "$base\routes\escala_trabalho.php") {
    $n = (Get-Content "$base\routes\escala_trabalho.php").Count
    Write-Host "EXISTS $n lines"
} else { Write-Host 'MISSING' }

Write-Host ''
Write-Host '=== O3-03 POST escalas e GET setores em escala_saude.php ==='
$esc = Get-Content "$base\routes\escala_saude.php" -Raw
if ($esc -match "Route::post") { Write-Host 'POST escalas: FOUND' } else { Write-Host 'POST escalas: MISSING' }
if ($esc -match "Route::get.*setores") { Write-Host 'GET setores: FOUND' } else { Write-Host 'GET setores: MISSING' }

Write-Host ''
Write-Host '=== O3-04 autocadastro_admin.php ==='
if (Test-Path "$base\routes\autocadastro_admin.php") {
    $n = (Get-Content "$base\routes\autocadastro_admin.php").Count
    Write-Host "EXISTS $n lines"
} else { Write-Host 'MISSING' }

Write-Host ''
Write-Host '=== O3-05 gestor.php JOIN com PESSOA ==='
$gest = Get-Content "$base\routes\gestor.php" -Raw
if ($gest -match 'PESSOA_NOME') { Write-Host 'PESSOA_NOME: FOUND' } else { Write-Host 'PESSOA_NOME: MISSING' }
if ($gest -match 'PESSOA') { Write-Host 'PESSOA ref: FOUND' } else { Write-Host 'PESSOA ref: MISSING' }

Write-Host ''
Write-Host '=== web.php novos requires Bloco1 ==='
$web = Get-Content "$base\routes\web.php"
$checks = @('afastamentos_v3','escala_trabalho','autocadastro_admin')
foreach ($r in $checks) {
    $found = $false
    for ($i = 790; $i -lt 945; $i++) {
        if ($web[$i] -match $r) { Write-Host "OK L$($i+1): $r"; $found = $true; break }
    }
    if (-not $found) { Write-Host "AUSENTE Bloco1: $r" }
}

Write-Host ''
Write-Host 'DONE'
