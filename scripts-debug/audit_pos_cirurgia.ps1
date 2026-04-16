$webphp = 'C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php'
$lines = Get-Content $webphp
Write-Host "Total lines: $($lines.Count)"
$seen = @{}; $dups = 0; $total = 0
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "require __DIR__ \. '/(.*?)'") {
        $f = $Matches[1]; $total++
        if ($seen.ContainsKey($f)) {
            Write-Host "DUPLICATA: $f  L$($seen[$f]) e L$($i+1)"; $dups++
        } else { $seen[$f] = $i+1 }
    }
}
Write-Host "Total requires: $total"
if ($dups -eq 0) { Write-Host "OK - zero duplicatas" } else { Write-Host "FALHOU - $dups duplicatas" }
# Verificar que avaliacao_desempenho e ERP estao no bloco 1
$blocoOk = $true
'avaliacao_desempenho','orcamento','execucao_despesa','contabilidade','tesouraria','receita_municipal','controle_externo' | ForEach-Object {
    $pat = "require __DIR__ \. '/$_\.php'"
    $found = $false
    for ($i = 790; $i -lt 935; $i++) {
        if ($lines[$i] -match $pat) { Write-Host "OK Bloco1 L$($i+1): $_.php"; $found = $true; break }
    }
    if (-not $found) { Write-Host "AUSENTE do Bloco1: $_.php"; $blocoOk = $false }
}
if ($blocoOk) { Write-Host "Bloco1: todos os 7 novos requires confirmados" }
# Verificar que nao ha require fora do bloco 1 (apos L935)
$spurious = 0
for ($i = 934; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "require __DIR__") {
        Write-Host "REQUIRE FORA DO BLOCO1: L$($i+1): $($lines[$i].Trim())"
        $spurious++
    }
}
if ($spurious -eq 0) { Write-Host "OK - nenhum require fora do bloco autorizado" }
