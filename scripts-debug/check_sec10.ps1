$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT ==="
Write-Host (php -l "$base\routes\web.php" 2>&1)
Write-Host (php -l "$base\routes\exoneracao.php" 2>&1)
Write-Host (php -l "$base\routes\cargos_salarios.php" 2>&1)
Write-Host (php -l "$base\app\Console\Kernel.php" 2>&1)

Write-Host "=== web.php ==="
$wn = (Get-Content "$base\routes\web.php").Count
Write-Host "Linhas=$wn"

Write-Host "=== Logs adicionados ==="
$w = Get-Content "$base\routes\web.php" -Raw
Write-Host "login_sucesso=$($w.Contains('login_sucesso'))"
Write-Host "acesso_negado=$($w.Contains('acesso_negado'))"

$e = Get-Content "$base\routes\exoneracao.php" -Raw
Write-Host "exoneracao_registrada=$($e.Contains('exoneracao_registrada') -or $e.Contains('operacao_sensivel'))"

$c = Get-Content "$base\routes\cargos_salarios.php" -Raw
Write-Host "cargo_criado=$($c.Contains('cargo_criado'))"
Write-Host "cargo_alterado=$($c.Contains('cargo_alterado'))"

Write-Host "=== logging.php canal security ==="
$l = Get-Content "$base\config\logging.php" -Raw
Write-Host "security canal=$($l.Contains('security'))"
Write-Host "days 90=$($l -match 'days.*90')"

Write-Host "=== Kernel arquivamento ==="
$k = Get-Content "$base\app\Console\Kernel.php" -Raw
Write-Host "monthlyOn=$($k.Contains('monthlyOn'))"
Write-Host "gzopen=$($k.Contains('gzopen'))"
Write-Host "quarterly=$($k.Contains('quarterly'))"
Write-Host "arquivo dir=$($k.Contains('arquivo'))"
