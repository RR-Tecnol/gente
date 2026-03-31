$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Encontrar a segunda ocorrencia do bloco de requires (duplicata)
$count = 0
$removeStart = -1; $removeEnd = -1
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "Refatoracao 30/03/2026") {
        $count++
        if ($count -eq 2) { $removeStart = $i }
    }
    if ($removeStart -ge 0 -and $lines[$i] -match "organograma_v3") {
        $removeEnd = $i; break
    }
}
Write-Host "Duplicata: linha $($removeStart+1) ate $($removeEnd+1)"

if ($removeStart -lt 0) { Write-Host "Nenhuma duplicata encontrada."; exit }

$cleaned = $lines[0..($removeStart-1)] + $lines[($removeEnd+1)..($lines.Count-1)]
[System.IO.File]::WriteAllLines($webPath, $cleaned, [System.Text.Encoding]::UTF8)
Write-Host "Duplicata removida. Total linhas: $($cleaned.Count)"
