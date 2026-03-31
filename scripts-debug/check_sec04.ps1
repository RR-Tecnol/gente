$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\web.php" 2>&1)

Write-Host "=== web.php linhas e senha ==="
$wlines = Get-Content "$base\routes\web.php"
Write-Host "Linhas=$($wlines.Count)"
for ($i = 0; $i -lt $wlines.Count; $i++) {
    if ($wlines[$i] -match "SEC-PROD-04|change.password|troca.senha|nova_senha|min:8") {
        Write-Host "L$($i+1): $($wlines[$i].Trim())"
    }
}

Write-Host "=== LoginView.vue - forca de senha ==="
$v = Get-Content "$base\resources\gente-v3\src\views\auth\LoginView.vue" -Raw
Write-Host "forcaSenha computed=$($v.Contains('forcaSenha') -or $v.Contains('forca'))"
Write-Host "regex maiuscula=$($v.Contains('[A-Z]'))"
Write-Host "regex numero=$($v.Contains('[0-9]'))"
Write-Host "regex especial=$($v.Contains('[!@#'))"
Write-Host "barra progresso=$($v.Contains('progress') -or $v.Contains('barra'))"
Write-Host "botao desabilitado=$($v.Contains(':disabled') -or $v.Contains('disabled'))"
