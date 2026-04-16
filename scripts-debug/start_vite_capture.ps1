cd 'C:\Users\joaob\Desktop\sisgep-job-main\resources\gente-v3'
Write-Host "Iniciando Vite e capturando erro..."
$output = npm run dev 2>&1 | Select-Object -First 60
$output | ForEach-Object { Write-Host $_ }
