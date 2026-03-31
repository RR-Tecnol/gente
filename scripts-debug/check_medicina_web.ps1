$base = "C:\Users\joaob\Desktop\sisgep-job-main"
$w = [System.IO.File]::ReadAllLines("$base\routes\web.php", [System.Text.Encoding]::UTF8)

# Mostrar todas as linhas que contem medicina
Write-Host "=== Ocorrencias de medicina no web.php ==="
for ($i = 0; $i -lt $w.Count; $i++) {
    if ($w[$i] -match "medicina") {
        Write-Host "L$($i+1): $($w[$i])"
    }
}

Write-Host ""
Write-Host "=== Bloco require medicina ==="
for ($i = 770; $i -le 780; $i++) {
    Write-Host "L$($i+1): $($w[$i])"
}
