$base = 'C:\Users\joaob\Desktop\sisgep-job-main'

Write-Host '=== Contexto ?? em FeriasLicencasView.vue ==='
$lines = Get-Content "$base\resources\gente-v3\src\views\rh\FeriasLicencasView.vue"
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match '\?\?') {
        Write-Host "L$($i+1): $($lines[$i].Trim())"
    }
}

Write-Host ''
Write-Host '=== Contexto ?? em BancoHorasView.vue ==='
$lines2 = Get-Content "$base\resources\gente-v3\src\views\ponto\BancoHorasView.vue"
for ($i = 0; $i -lt $lines2.Count; $i++) {
    if ($lines2[$i] -match '\?\?') {
        Write-Host "L$($i+1): $($lines2[$i].Trim())"
    }
}

Write-Host ''
Write-Host '=== Sobreaviso — como novo item e adicionado a lista ==='
$sob = Get-Content "$base\resources\gente-v3\src\views\ponto\EscalaSobreavisoView.vue"
for ($i = 0; $i -lt $sob.Count; $i++) {
    if ($sob[$i] -match 'push|acionamento|sobreaviso.*post|post.*sobreaviso|confirmar|registrar') {
        Write-Host "L$($i+1): $($sob[$i].Trim())"
    }
}

Write-Host ''
Write-Host 'DONE'
