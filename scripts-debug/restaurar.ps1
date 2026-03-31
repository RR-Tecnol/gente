Copy-Item "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php.bak4" "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php" -Force
$l = [System.IO.File]::ReadAllLines("C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php", [System.Text.Encoding]::UTF8)
Write-Host "Restaurado: $($l.Count) linhas"
