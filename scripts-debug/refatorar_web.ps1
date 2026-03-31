# Refatoracao web.php - extrai blocos standalone api/v3
# RR TECNOL | 30/03/2026

$webPath   = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$routesDir = "C:\Users\joaob\Desktop\sisgep-job-main\routes"

$allLines = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)
Write-Host "web.php: $($allLines.Count) linhas"

function Extract-Block {
    param([int]$s, [int]$e, [string]$out, [string]$lbl)
    $inner = @()
    for ($i = $s + 1; $i -lt $e; $i++) {
        $ln = $allLines[$i]
        if ($ln.Length -ge 4) { $inner += $ln.Substring(4) } else { $inner += $ln }
    }
    $hdr = @("<?php", "// $lbl", "// Extraido de web.php - herda prefix api/v3 + auth do grupo principal", "")
    [System.IO.File]::WriteAllLines("$routesDir\$out", ($hdr + $inner), [System.Text.Encoding]::UTF8)
    Write-Host "  OK $out ($($inner.Count) linhas)"
}

Extract-Block -s 3543 -e 3756 -out "cargos_salarios.php"    -lbl "CARGOS E SALARIOS - /cargos /funcoes"
Extract-Block -s 3760 -e 3836 -out "ferias_v3.php"          -lbl "FERIAS CRUD - POST/PUT/DELETE /ferias"
Extract-Block -s 3842 -e 3957 -out "comunicados.php"        -lbl "COMUNICADOS - GET/POST/PUT/DELETE /comunicados"
Extract-Block -s 3962 -e 4062 -out "meu_perfil.php"         -lbl "PERFIL FUNCIONARIO - GET/PUT /perfil"
Extract-Block -s 4067 -e 4168 -out "ponto_eletronico.php"   -lbl "PONTO ELETRONICO - GET /ponto POST /ponto/justificativa"
Extract-Block -s 4172 -e 4303 -out "plantoes_sobreaviso.php" -lbl "PLANTOES EXTRAS SOBREAVISO"
Extract-Block -s 4308 -e 4410 -out "atestados_v3.php"       -lbl "ATESTADOS MEDICOS standalone"
Extract-Block -s 4415 -e 4522 -out "contratos_v3.php"       -lbl "CONTRATOS VINCULOS PROGRESSAO standalone"
Extract-Block -s 4633 -e 4738 -out "medicina.php"           -lbl "MEDICINA DO TRABALHO - GET /medicina POST /medicina/agendar"
Extract-Block -s 4744 -e 4808 -out "declaracoes.php"        -lbl "DECLARACOES REQUERIMENTOS - GET/POST /declaracoes"
Extract-Block -s 4814 -e 4886 -out "ouvidoria.php"          -lbl "OUVIDORIA - GET/POST /ouvidoria"
Extract-Block -s 4892 -e 5049 -out "gestor.php"             -lbl "PORTAL DO GESTOR - GET /gestor POST /gestor/aprovar"
Extract-Block -s 5054 -e 5297 -out "organograma_v3.php"     -lbl "ORGANOGRAMA CRUD DE SETORES"

Write-Host "Extracao concluida."
