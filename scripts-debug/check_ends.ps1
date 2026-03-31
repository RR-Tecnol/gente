$routesDir = "C:\Users\joaob\Desktop\sisgep-job-main\routes"
$files = @("ouvidoria.php","comunicados.php","meu_perfil.php","cargos_salarios.php",
    "ferias_v3.php","ponto_eletronico.php","plantoes_sobreaviso.php",
    "atestados_v3.php","contratos_v3.php","organograma_v3.php","gestor.php")
foreach ($f in $files) {
    $path = "$routesDir\$f"
    $lines = [System.IO.File]::ReadAllLines($path, [System.Text.Encoding]::UTF8)
    $n = $lines.Count
    $l1 = $lines[$n-3]; $l2 = $lines[$n-2]; $l3 = $lines[$n-1]
    Write-Host "=== $f ($n linhas) | last3: [$l1] | [$l2] | [$l3]"
}
