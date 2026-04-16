$r = 'C:\Users\joaob\Desktop\sisgep-job-main\routes'
$webphp = "$r\web.php"

Write-Host "=== [1] ERP FISCAL FILES CHECK ==="
$erpFiles = 'orcamento.php','execucao_despesa.php','contabilidade.php','tesouraria.php','receita_municipal.php','controle_externo.php'
foreach ($f in $erpFiles) {
    $fp = "$r\$f"
    if (Test-Path $fp) {
        $n = (Get-Content $fp).Count
        Write-Host "EXISTS [$n lines]: $f"
    } else {
        Write-Host "MISSING: $f"
    }
}

Write-Host ""
Write-Host "=== [2] ALL require __DIR__ lines in web.php ==="
$lines = Get-Content $webphp
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match 'require __DIR__') {
        Write-Host ("L" + ($i+1) + ": " + $lines[$i].Trim())
    }
}

Write-Host ""
Write-Host "=== [3] DUPLICATE requires ==="
$seen = @{}
$dupFound = $false
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "require __DIR__ \+ '/(.*?)'") {
        $fname = $Matches[1]
        if ($seen.ContainsKey($fname)) {
            Write-Host "DUPLICATE: $fname  first=L$($seen[$fname])  second=L$($i+1)"
            $dupFound = $true
        } else {
            $seen[$fname] = $i+1
        }
    }
}
if (-not $dupFound) { Write-Host "No duplicates found" }

Write-Host ""
Write-Host "=== [4] Count inline Route:: definitions between L920 and L5008 ==="
$inlineCount = 0
for ($i = 919; $i -lt 5008 -and $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "^\s+Route::(get|post|put|delete|patch|middleware|prefix|resource)\(") {
        $inlineCount++
    }
}
Write-Host "Inline Route:: count (L920-5008): $inlineCount"

Write-Host ""
Write-Host "=== [5] avaliacao inline routes in that range ==="
$avalCount = 0
for ($i = 919; $i -lt 5008 -and $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match 'avalia') {
        $avalCount++
    }
}
Write-Host "Lines mentioning avalia* (L920-5008): $avalCount"

Write-Host ""
Write-Host "=== DONE ==="
