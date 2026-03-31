cd "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== 1. LINT web.php ==="
$r = php -l routes/web.php 2>&1
Write-Host $r

Write-Host ""
Write-Host "=== 2. LINT 13 modulos ==="
$mods = @("declaracoes","gestor","medicina","ouvidoria","comunicados","meu_perfil",
    "cargos_salarios","ferias_v3","ponto_eletronico","plantoes_sobreaviso",
    "atestados_v3","contratos_v3","organograma_v3")
$ok=0; $err=0
foreach ($m in $mods) {
    $r = php -l "routes/$m.php" 2>&1
    if ($r -match "No syntax") { $ok++ }
    else { Write-Host "ERRO $m : $r"; $err++ }
}
Write-Host "Modulos: $ok OK | $err ERROS"

Write-Host ""
Write-Host "=== 3. LINT web.php dos modules ERP ja existentes ==="
$erp = @("consignacao","diarias","rpps","progressao_funcional","exoneracao",
    "hora_extra","verba_indenizatoria","estagiarios","acumulacao","transparencia",
    "pss","terceirizados","sagres","banco_horas","atestados","funcionarios",
    "folha","motor","esocial","contabilidade","controle_externo","execucao_despesa",
    "orcamento","receita_municipal","tesouraria")
$ok2=0; $err2=0
foreach ($m in $erp) {
    if (Test-Path "routes/$m.php") {
        $r = php -l "routes/$m.php" 2>&1
        if ($r -match "No syntax") { $ok2++ }
        else { Write-Host "ERRO $m : $r"; $err2++ }
    }
}
Write-Host "ERP existentes: $ok2 OK | $err2 ERROS"
