# SCRIPT DE AUDITORIA — GENTE v3 / SISGEP
# Cole o conteúdo do arquivo auditoria_resultado.txt de volta para o Claude.
# Execução: .\auditoria.ps1

$out = "C:\Users\joaob\Desktop\sisgep-job-main\auditoria_resultado.txt"
$base = "C:\Users\joaob\Desktop\sisgep-job-main"

function sec($title) { "`n" + ("="*60) + "`n$title`n" + ("="*60) }

$result = @()
$result += "AUDITORIA GENTE v3 — $(Get-Date -Format 'dd/MM/yyyy HH:mm')"

# 1. SEEDERS — quais existem
$result += sec "1. SEEDERS EXISTENTES"
Get-ChildItem "$base\database\seeders" -Filter "*.php" | Select-Object Name | ForEach-Object { $result += $_.Name }

# 2. DATABASE SEEDER — ordem de execução atual
$result += sec "2. DATABASESEEDER.PHP (ordem atual)"
Get-Content "$base\database\seeders\DatabaseSeeder.php" | ForEach-Object { $result += $_ }

# 3. MIGRATIONS SPRINT 3 — foram rodadas?
$result += sec "3. MIGRATIONS SPRINT 3 (arquivos que existem)"
Get-ChildItem "$base\database\migrations" -Filter "*2026_03_16*" | Select-Object Name | ForEach-Object { $result += $_.Name }

# 4. SERVICES — MotorFolhaService existe?
$result += sec "4. APP/SERVICES"
Get-ChildItem "$base\app\Services" -Filter "*.php" | Select-Object Name | ForEach-Object { $result += $_.Name }

# 5. ROTAS — arquivos em routes/
$result += sec "5. ARQUIVOS EM ROUTES/"
Get-ChildItem "$base\routes" -Filter "*.php" | Select-Object Name | ForEach-Object { $result += $_.Name }

# 6. ROUTES REGISTRADAS — o que está no web.php para o motor
$result += sec "6. ENDPOINTS DO MOTOR NO WEB.PHP"
Select-String -Path "$base\routes\web.php" -Pattern "calcular-proventos|piso-salarial|lancamentos|admin/vinculos|admin/rubricas|admin/tipos-afastamento|folhas/reajuste" | ForEach-Object { $result += "$($_.LineNumber): $($_.Line.Trim())" }

# 7. ROUTES REGISTRADAS — autocadastro
$result += sec "7. ENDPOINTS AUTOCADASTRO NO WEB.PHP"
Select-String -Path "$base\routes\web.php" -Pattern "autocadastro" | ForEach-Object { $result += "$($_.LineNumber): $($_.Line.Trim())" }

# 8. VIEWS VUE — arquivos novos criados no Sprint 3
$result += sec "8. VIEWS VUE — SPRINT 3 (arquivos modificados/criados)"
Get-ChildItem "$base\resources\gente-v3\src\views" -Recurse -Filter "*.vue" | Where-Object { $_.LastWriteTime -gt (Get-Date).AddDays(-3) } | Select-Object Name, LastWriteTime | ForEach-Object { $result += "$($_.Name) — $($_.LastWriteTime)" }

# 9. FUNCION SEED — primeiras 30 linhas do seed atual
$result += sec "9. FuncionariosPMSLzSeeder — INSERT PESSOA (linhas 80-130)"
$lines = Get-Content "$base\database\seeders\FuncionariosPMSLzSeeder.php"
$lines[79..129] | ForEach-Object { $result += $_ }

# 10. VINCULO SEED — verificar se updated_at foi removido
$result += sec "10. VinculosPMSLzSeeder — conteúdo completo"
Get-Content "$base\database\seeders\VinculosPMSLzSeeder.php" | ForEach-Object { $result += $_ }

# 11. ORGANOGRAMA SEED — existe?
$result += sec "11. OrganogramaPMSLzSeeder — existe?"
$orgPath = "$base\database\seeders\OrganogramaPMSLzSeeder.php"
if (Test-Path $orgPath) {
    $result += "EXISTE — primeiras 40 linhas:"
    Get-Content $orgPath | Select-Object -First 40 | ForEach-Object { $result += $_ }
} else {
    $result += "NAO EXISTE"
}

# 12. TABELA SALARIAL SEED — existe?
$result += sec "12. TabelaSalarialPMSLzSeeder — existe?"
$tabPath = "$base\database\seeders\TabelaSalarialPMSLzSeeder.php"
if (Test-Path $tabPath) {
    $result += "EXISTE — primeiras 40 linhas:"
    Get-Content $tabPath | Select-Object -First 40 | ForEach-Object { $result += $_ }
} else {
    $result += "NAO EXISTE"
}

# 13. MOTOR FOLHA SERVICE — primeiras 80 linhas
$result += sec "13. MotorFolhaService.php — primeiras 80 linhas"
$motorPath = "$base\app\Services\MotorFolhaService.php"
if (Test-Path $motorPath) {
    Get-Content $motorPath | Select-Object -First 80 | ForEach-Object { $result += $_ }
} else {
    $result += "NAO EXISTE"
}

# 14. FOLHA.PHP — rotas do Sprint 3
$result += sec "14. ROUTES/FOLHA.PHP — ultimas 60 linhas"
Get-Content "$base\routes\folha.php" | Select-Object -Last 60 | ForEach-Object { $result += $_ }

# 15. TABELAS NO BANCO — colunas reais
$result += sec "15. COLUNAS REAIS DAS TABELAS (via tinker)"
$tables = @("RUBRICA", "VINCULO", "FUNCIONARIO", "PESSOA", "ADICIONAL_SERVIDOR", "LANCAMENTO_FOLHA", "LOTACAO")
foreach ($t in $tables) {
    $result += "--- $t ---"
    $cmd = "php artisan tinker --execute=""echo implode('|', array_column(DB::select('PRAGMA table_info($t)'), 'name'));"" 2>&1"
    $cols = Invoke-Expression "cd '$base'; $cmd"
    $result += $cols
}

# 16. PENDENCIAS — bugs registrados no plano
$result += sec "16. PENDENCIAS POS-IMPLEMENTACAO DO PLANO MESTRE"
Select-String -Path "$base\docs\PLANO_MESTRE_V2.md" -Pattern "BUG-SEED|BUG-AC-0[7-9]" | ForEach-Object { $result += $_.Line.Trim() }

# Salvar
$result | Out-File -FilePath $out -Encoding UTF8
Write-Host "Auditoria salva em: $out"
Write-Host "Linhas: $($result.Count)"
