$base = 'C:\Users\joaob\Desktop\sisgep-job-main'

Write-Host '=== FeriasLicencasView — UTF-8 bytes OK ==='
$enc = New-Object System.Text.UTF8Encoding($false, $true)
$bytes = [System.IO.File]::ReadAllBytes("$base\resources\gente-v3\src\views\rh\FeriasLicencasView.vue")
try { [void]$enc.GetString($bytes); Write-Host 'UTF8: OK' } catch { Write-Host 'UTF8: BAD' }

Write-Host ''
Write-Host '=== FeriasLicencasView — corrupcao residual ==='
$content = [System.IO.File]::ReadAllText("$base\resources\gente-v3\src\views\rh\FeriasLicencasView.vue", [System.Text.Encoding]::UTF8)
$lines = $content -split "`n"
$corrupt = 0
for ($i = 0; $i -lt $lines.Count; $i++) {
    # ?? em posicao de string de template (nao operador JS: nao precedido por espaco/letra)
    if ($lines[$i] -match ">[^<]*\?\?[^<]*<|ico:\s*'\?\?'|nome:\s*'[^']*\?\?") {
        Write-Host "POSSIVEL CORRUPCAO L$($i+1): $($lines[$i].Trim().Substring(0, [Math]::Min(80, $lines[$i].Trim().Length)))"
        $corrupt++
    }
}
if ($corrupt -eq 0) { Write-Host 'Nenhuma corrupcao de template detectada' }

Write-Host ''
Write-Host '=== FeriasLicencasView — emojis presentes ==='
$emojis = @('🏖️','📋','⚠️','📎','✅','📊','📅','✏️','📤','🗓️','🤱','🎓','⚖️','✈️','👨')
foreach ($e in $emojis) {
    if ($content -match [regex]::Escape($e)) { Write-Host "FOUND: $e" }
    else { Write-Host "MISSING: $e" }
}

Write-Host ''
Write-Host '=== AutocadastroGestaoView — transition tag ==='
$auto = [System.IO.File]::ReadAllText("$base\resources\gente-v3\src\views\rh\AutocadastroGestaoView.vue", [System.Text.Encoding]::UTF8)
$transitions = ([regex]::Matches($auto, '</transition>')).Count
$transitionsOpen = ([regex]::Matches($auto, '<transition')).Count
Write-Host "Open <transition>: $transitionsOpen"
Write-Host "Close </transition>: $transitions"
if ($transitions -eq $transitionsOpen) { Write-Host 'Tags balanceadas: OK' }
else { Write-Host "PROBLEMA: $transitions close vs $transitionsOpen open" }

Write-Host ''
Write-Host '=== Vite ainda sobe (porta 5173) ==='
$net = netstat -ano 2>&1 | Select-String ':5173'
if ($net) { Write-Host "Porta 5173: ATIVA — $net" }
else { Write-Host 'Porta 5173: nao escutando — Vite precisa reiniciar' }

Write-Host ''
Write-Host 'DONE'
