$base = 'C:\Users\joaob\Desktop\sisgep-job-main'

Write-Host '=== F1-01 Icones corrompidos UTF-8 ==='
$files = @(
    'resources\gente-v3\src\views\rh\FeriasLicencasView.vue',
    'resources\gente-v3\src\views\rh\BancoHorasView.vue',
    'resources\gente-v3\src\views\rh\FuncionariosView.vue',
    'resources\gente-v3\src\views\rh\AutocadastroGestaoView.vue'
)
$enc = New-Object System.Text.UTF8Encoding($false, $true)
foreach ($f in $files) {
    $path = "$base\$f"
    if (-not (Test-Path $path)) { Write-Host "MISSING: $f"; continue }
    $bytes = [System.IO.File]::ReadAllBytes($path)
    try { [void]$enc.GetString($bytes); Write-Host "UTF8 OK: $f" }
    catch { Write-Host "BAD UTF8: $f" }
}

Write-Host ''
Write-Host '=== F1-02 Sidebar profile navegacao ==='
$layout = Get-Content "$base\resources\gente-v3\src\layouts\DashboardLayout.vue" -Raw
if ($layout -match 'sidebar-profile.*router\.push|router\.push.*sidebar-profile') {
    Write-Host 'sidebar-profile: HAS router.push'
} elseif ($layout -match 'sidebar-profile') {
    Write-Host 'sidebar-profile: EXISTS but NO router.push'
} else { Write-Host 'sidebar-profile: NOT FOUND' }

Write-Host ''
Write-Host '=== F1-03 Declaracoes IDs mock ==='
$decl = Get-Content "$base\resources\gente-v3\src\views\rh\DeclaracoesRequerimentosView.vue" -Raw
if ($decl -match 'mockPedidos|mock.*id.*1.*2.*3|id:.*1,') { Write-Host 'MOCK IDs: FOUND' }
else { Write-Host 'MOCK IDs: not detected' }

Write-Host ''
Write-Host '=== F1-04 Progressao admin seed ==='
$prog = Get-Content "$base\database\seeders\DatabaseSeeder.php" -Raw 2>$null
if ($prog -match 'CARREIRA_ID|ProgressaoSeeder') { Write-Host 'Progressao seed: EXISTS' }
else { Write-Host 'Progressao seed: NOT IN DatabaseSeeder' }

Write-Host ''
Write-Host '=== F1-05 Sobreaviso Vue otimista ==='
$sob = Get-Content "$base\resources\gente-v3\src\views\rh\PlantoesSobreavisoView.vue" -Raw 2>$null
if (-not $sob) { Write-Host 'FILE MISSING' }
elseif ($sob -match 'acionamentos\.value\.push.*antes|push.*await') { Write-Host 'Vue otimista: POSSIVEL' }
else { Write-Host 'Sobreaviso: verificar manualmente' }

Write-Host ''
Write-Host '=== F1-06 Escala Medica blade view ==='
$blade = Test-Path "$base\resources\views\escala\escala_view.blade.php"
Write-Host "escala_view.blade.php exists: $blade"
$ctrl = Get-Content "$base\app\Http\Controllers\EscalaController.php" -Raw 2>$null
if ($ctrl -match "return view\('escala\.escala_view") { Write-Host 'Controller: ainda aponta para blade' }
elseif ($ctrl -match 'redirect') { Write-Host 'Controller: ja usa redirect' }
else { Write-Host 'Controller: verificar manualmente' }

Write-Host ''
Write-Host '=== F1-07 SESMT Medicina KPIs ==='
$med = Get-Content "$base\routes\medicina_admin.php" -Raw 2>$null
if (-not $med) { Write-Host 'medicina_admin.php: MISSING' }
elseif ($med -match '/kpis') { Write-Host 'KPIs route: EXISTS' }
else { Write-Host 'KPIs route: NOT FOUND' }

Write-Host ''
Write-Host '=== F1-08 Holerites sem identificacao ==='
$cc = Get-Content "$base\app\Http\Controllers\ContraChequeController.php" -Raw 2>$null
if (-not $cc) { Write-Host 'ContraChequeController: MISSING' }
elseif ($cc -match 'PESSOA_NOME|pessoa.*NOME') { Write-Host 'PESSOA_NOME: JA INCLUIDO' }
else { Write-Host 'PESSOA_NOME: AUSENTE na response' }

Write-Host ''
Write-Host '=== F1-09 Diarias data no passado ==='
$diar = Get-Content "$base\routes\diarias.php" -Raw 2>$null
if ($diar -match 'toDateString|data_saida.*past|past.*data_saida') { Write-Host 'Validacao data: EXISTS' }
else { Write-Host 'Validacao data: AUSENTE' }

Write-Host ''
Write-Host 'DONE'
