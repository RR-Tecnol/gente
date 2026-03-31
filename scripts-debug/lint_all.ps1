cd "C:\Users\joaob\Desktop\sisgep-job-main"
$mods = @("declaracoes","gestor","medicina","ouvidoria","comunicados","meu_perfil",
    "cargos_salarios","ferias_v3","ponto_eletronico","plantoes_sobreaviso",
    "atestados_v3","contratos_v3","organograma_v3")
$ok = 0; $err = 0
foreach ($m in $mods) {
    $r = php -l "routes/$m.php" 2>&1
    if ($r -match "No syntax") {
        Write-Host "OK $m"
        $ok++
    } else {
        Write-Host "ERRO $m : $r"
        $err++
    }
}
Write-Host ""
Write-Host "TOTAL: $ok OK | $err ERROS"
