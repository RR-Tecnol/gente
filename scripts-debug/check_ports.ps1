Write-Host "=== Processos nas portas 5173 e 8080 ==="
netstat -ano | Select-String ":5173|:8080"

Write-Host ""
Write-Host "=== Node/PHP processos ativos ==="
Get-Process | Where-Object { $_.Name -match 'node|php' } | Select-Object Id, Name, CPU | Format-Table -AutoSize
