$base = 'C:\Users\joaob\Desktop\sisgep-job-main'
$enc = New-Object System.Text.UTF8Encoding($false, $true)

Write-Host '=== F1-01 BancoHorasView.vue UTF-8 ==='
$path = "$base\resources\gente-v3\src\views\ponto\BancoHorasView.vue"
$bytes = [System.IO.File]::ReadAllBytes($path)
try { [void]$enc.GetString($bytes); Write-Host 'UTF8 OK' }
catch { Write-Host 'BAD UTF8' }

Write-Host ''
Write-Host '=== F1-05 EscalaSobreavisoView.vue ==='
$path2 = "$base\resources\gente-v3\src\views\ponto\EscalaSobreavisoView.vue"
$bytes2 = [System.IO.File]::ReadAllBytes($path2)
try { [void]$enc.GetString($bytes2); Write-Host 'UTF8 OK' }
catch { Write-Host 'BAD UTF8' }
$sob = [System.IO.File]::ReadAllText($path2, [System.Text.Encoding]::UTF8)
if ($sob -match 'push.*await|antes da resposta') { Write-Host 'Vue otimista: ENCONTRADO' }
else { Write-Host 'Vue otimista: nao detectado — verificar manualmente' }
$n = ($sob -split "`n").Count
Write-Host "Lines: $n"

Write-Host ''
Write-Host '=== F1-05 PlantoesExtrasView.vue ==='
$path3 = "$base\resources\gente-v3\src\views\ponto\PlantoesExtrasView.vue"
$sob3 = [System.IO.File]::ReadAllText($path3, [System.Text.Encoding]::UTF8)
if ($sob3 -match 'push.*await|antes da resposta') { Write-Host 'Vue otimista: ENCONTRADO' }
else { Write-Host 'Vue otimista: nao detectado' }
$n3 = ($sob3 -split "`n").Count
Write-Host "Lines: $n3"

Write-Host ''
Write-Host '=== F1-06 EscalaController ==='
$ctrl = Get-Content "$base\app\Http\Controllers\EscalaController.php" -Raw
$match = [regex]::Match($ctrl, "return view\(.*?\);")
if ($match.Success) { Write-Host "view() call: $($match.Value)" }
else { Write-Host 'No view() call found' }

Write-Host ''
Write-Host '=== F1-07 medicina_admin KPIs — ver o erro ==='
$med = Get-Content "$base\routes\medicina_admin.php" -Raw
$idx = $med.IndexOf('/kpis')
if ($idx -ge 0) { Write-Host $med.Substring([Math]::Max(0,$idx-50), [Math]::Min(200, $med.Length-$idx+50)) }

Write-Host ''
Write-Host 'DONE'
