cd 'C:\Users\joaob\Desktop\sisgep-job-main'

Write-Host '=== O3-01 Admin CARREIRA_ID ==='
php artisan tinker --execute="$f = DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID',1)->first(); echo 'CARREIRA_ID=' . $f->CARREIRA_ID . ' CLASSE=' . $f->FUNCIONARIO_CLASSE . ' REF=' . $f->FUNCIONARIO_REFERENCIA . ' PROG=' . $f->FUNCIONARIO_DATA_ULTIMA_PROGRESSAO;" 2>&1

Write-Host ''
Write-Host '=== O3-02 BANCO_HORAS migration exists? ==='
php artisan tinker --execute="echo DB::select(\"SELECT name FROM sqlite_master WHERE type='table' AND name='BANCO_HORAS'\") ? 'EXISTS' : 'MISSING';" 2>&1

Write-Host ''
Write-Host '=== O3-02 Migration file exists? ==='
$migs = Get-ChildItem 'C:\Users\joaob\Desktop\sisgep-job-main\database\migrations' | Where-Object { $_.Name -match 'banco_horas' }
if ($migs) { $migs | ForEach-Object { Write-Host "FOUND: $($_.Name)" } }
else { Write-Host 'No banco_horas migration found' }

Write-Host ''
Write-Host '=== O3-02 LOTACAO admin ==='
php artisan tinker --execute="$l = DB::table('LOTACAO')->where('FUNCIONARIO_ID',1)->whereNull('LOTACAO_DATA_FIM')->first(); echo $l ? 'LOTACAO_ATIVA: SETOR_ID=' . $l->SETOR_ID : 'NENHUMA LOTACAO ATIVA';" 2>&1

Write-Host ''
Write-Host 'DONE'
