$base = "C:\Users\joaob\Desktop\sisgep-job-main"
Write-Host "=== LINT ==="
Write-Host (php -l "$base\app\Services\EsocialXmlService.php" 2>&1)
Write-Host (php -l "$base\routes\esocial.php" 2>&1)
Write-Host (php -l "$base\routes\web.php" 2>&1)
Write-Host "Linhas web.php=$((Get-Content "$base\routes\web.php").Count)"
Write-Host "=== EsocialXmlService ==="
$s = Get-Content "$base\app\Services\EsocialXmlService.php" -Raw
Write-Host "gerarS1200=$($s.Contains('gerarS1200'))"
Write-Host "gerarS2200=$($s.Contains('gerarS2200'))"
Write-Host "gerarS2206=$($s.Contains('gerarS2206'))"
Write-Host "gerarS2299=$($s.Contains('gerarS2299'))"
Write-Host "gerarIdEvento=$($s.Contains('gerarIdEvento'))"
Write-Host "esocial.gov.br=$($s.Contains('esocial.gov.br'))"
$ln = (Get-Content "$base\app\Services\EsocialXmlService.php").Count
Write-Host "Linhas=$ln"
Write-Host "=== esocial.php usa Service ==="
$e = Get-Content "$base\routes\esocial.php" -Raw
Write-Host "EsocialXmlService=$($e.Contains('EsocialXmlService'))"
Write-Host "S-1200=$($e.Contains('S-1200') -or $e.Contains('S1200'))"
