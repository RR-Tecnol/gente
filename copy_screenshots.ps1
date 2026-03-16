$src = "C:\Users\joaob\.gemini\antigravity\brain\25cffcc2-98e3-4806-a955-d81feef02468"
$dst = "C:\Users\joaob\OneDrive\Desktop\documento sitema"

New-Item -ItemType Directory -Force -Path $dst | Out-Null

$map = @{
    "01_dashboard_*"            = "01-dashboard.png"
    "02_funcionarios_*"         = "02-funcionarios.png"
    "03_meu_perfil_*"           = "03-meu-perfil.png"
    "04_contratos_vinculos_*"   = "04-contratos-vinculos.png"
    "05_ferias_licencas_*"      = "05-ferias-licencas.png"
    "06_declaracoes_*"          = "06-declaracoes.png"
    "07_holerites_*"            = "07-holerites.png"
    "08_progressao_funcional_*" = "08-progressao-funcional.png"
    "09_ponto_eletronico_*"     = "09-ponto-eletronico.png"
    "10_banco_horas_*"          = "10-banco-horas.png"
    "11_escala_trabalho_*"      = "11-escala-trabalho.png"
    "12_plantoes_extras_*"      = "12-plantoes-extras.png"
    "13_medicina_trabalho_*"    = "13-medicina-trabalho.png"
    "14_faltas_atrasos_*"       = "14-faltas-atrasos.png"
    "15_abono_faltas_*"         = "15-abono-faltas.png"
    "16_organograma_*"          = "16-organograma.png"
    "17_relatorios_*"           = "17-relatorios.png"
    "18_folha_pagamento_*"      = "18-folha-pagamento.png"
    "19_configuracoes_*"        = "19-configuracoes.png"
    "20_ouvidoria_*"            = "20-ouvidoria.png"
}

foreach ($pattern in $map.Keys) {
    $file = Get-ChildItem -Path $src -Filter $pattern -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($file) {
        Copy-Item $file.FullName -Destination (Join-Path $dst $map[$pattern]) -Force
        Write-Host "Copiado: $($map[$pattern])"
    }
    else {
        Write-Host "NAO ENCONTRADO: $pattern"
    }
}

Write-Host "`nTotal copiado: $(Get-ChildItem $dst -Filter '*.png').Count arquivos"
