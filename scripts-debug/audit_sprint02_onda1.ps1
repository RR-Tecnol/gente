$base = 'C:\Users\joaob\Desktop\sisgep-job-main'

Write-Host '=== O1-01 EscalaController redirect ==='
$ctrl = Get-Content "$base\app\Http\Controllers\EscalaController.php" -Raw
if ($ctrl -match "return redirect\('/escala-matriz-v3'\)") { Write-Host 'OK: redirect encontrado' }
else { Write-Host 'FALHOU: redirect nao encontrado' }
if ($ctrl -match "return view\('escala\.escala_view") { Write-Host 'PROBLEMA: view() antiga ainda existe' }
else { Write-Host 'OK: view() antiga removida' }

Write-Host ''
Write-Host '=== O1-02 DashboardLayout sidebar-profile ==='
$layout = Get-Content "$base\resources\gente-v3\src\layouts\DashboardLayout.vue" -Raw
if ($layout -match "sidebar-profile.*router\.push.*meu-perfil") { Write-Host 'OK: sidebar-profile com router.push' }
elseif ($layout -match "sidebar-profile") { Write-Host 'PROBLEMA: sidebar-profile existe mas sem router.push' }
else { Write-Host 'FALHOU: sidebar-profile nao encontrado' }

Write-Host ''
Write-Host '=== O1-03 ContraChequeController PESSOA_NOME ==='
$cc = Get-Content "$base\app\Http\Controllers\ContraChequeController.php" -Raw
if ($cc -match 'PESSOA_NOME') { Write-Host 'OK: PESSOA_NOME presente' }
else { Write-Host 'FALHOU: PESSOA_NOME ausente' }
if ($cc -match 'FUNCIONARIO_MATRICULA') { Write-Host 'OK: FUNCIONARIO_MATRICULA presente' }
else { Write-Host 'FALHOU: FUNCIONARIO_MATRICULA ausente' }
if ($cc -match "join.*PESSOA|PESSOA.*join") { Write-Host 'OK: join PESSOA presente' }
else { Write-Host 'FALHOU: join PESSOA ausente' }

Write-Host ''
Write-Host '=== O1-04 DeclaracoesView mockPedidos ==='
$decl = Get-Content "$base\resources\gente-v3\src\views\rh\DeclaracoesRequerimentosView.vue" -Raw
if ($decl -match 'mockPedidos') { Write-Host 'PROBLEMA: mockPedidos ainda existe' }
else { Write-Host 'OK: mockPedidos removido' }
if ($decl -match 'declaracoes.*download|download.*declaracoes') { Write-Host 'OK: rota de download presente' }
else { Write-Host 'VERIFICAR: padrao de download nao detectado' }
if ($decl -match "resp\.data\?\.id|resp\.data\.id|novoId") { Write-Host 'OK: usa ID real da resposta' }
else { Write-Host 'VERIFICAR: padrao de ID real nao detectado' }

Write-Host ''
Write-Host 'DONE'
