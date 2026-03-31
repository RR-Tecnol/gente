$routesDir = "C:\Users\joaob\Desktop\sisgep-job-main\routes"
$files = @("ouvidoria.php","comunicados.php","meu_perfil.php","cargos_salarios.php",
    "ferias_v3.php","ponto_eletronico.php","plantoes_sobreaviso.php",
    "atestados_v3.php","contratos_v3.php","organograma_v3.php","gestor.php")

foreach ($f in $files) {
    $path = "$routesDir\$f"
    $lines = [System.IO.File]::ReadAllLines($path, [System.Text.Encoding]::UTF8)

    # Encontrar o ultimo indice de uma linha que eh exatamente ");" ou "});"
    # Isso eh o fechamento do Route::prefix que nao deveria estar aqui
    $lastCloser = -1
    for ($i = $lines.Count - 1; $i -ge 0; $i--) {
        $t = $lines[$i].Trim()
        if ($t -eq "});" -or $t -eq ");") {
            $lastCloser = $i
            break
        }
    }

    if ($lastCloser -ge 0) {
        # Remover essa linha e tudo apos ela
        $trimmed = $lines[0..($lastCloser - 1)]
        # Remover linhas vazias no final
        while ($trimmed.Count -gt 0 -and $trimmed[$trimmed.Count-1].Trim() -eq "") {
            $trimmed = $trimmed[0..($trimmed.Count-2)]
        }
        [System.IO.File]::WriteAllLines($path, $trimmed, [System.Text.Encoding]::UTF8)
        Write-Host "Corrigido $f : removida linha $($lastCloser+1) ($($lines[$lastCloser]))"
    } else {
        Write-Host "Sem trailing: $f"
    }
}
Write-Host "Pronto."
