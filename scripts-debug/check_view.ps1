$p = "C:\Users\joaob\Desktop\sisgep-job-main\resources\gente-v3\src\views\rh\AvaliacaoGestorView.vue"
if (-not (Test-Path $p)) { Write-Host "ARQUIVO NAO EXISTE"; exit }
$c     = [System.IO.File]::ReadAllText($p, [System.Text.Encoding]::UTF8)
$lines = $c.Split([char]10).Count
Write-Host "Linhas: $lines"
Write-Host "Fecha template : $($c.Contains('</template>'))"
Write-Host "Fecha script   : $($c.Contains('</script>'))"
Write-Host "Fecha style    : $($c.Contains('</style>'))"
Write-Host "Chama api v3   : $($c.Contains('api/v3/avaliacoes'))"
Write-Host "Tem hero       : $($c.Contains('hero'))"
Write-Host "Tem loaded     : $($c.Contains('loaded'))"
Write-Host "Tem spinner    : $($c.Contains('spinner'))"
Write-Host "Tem estado erro: $($c.Contains('v-else-if'))"
Write-Host "Tem modal      : $($c.Contains('modal'))"
