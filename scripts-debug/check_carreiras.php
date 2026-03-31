<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$out = fopen(__DIR__ . '/check_log.txt', 'w');

// Encontrar tabelas com CARREIRA no nome
$tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
$names = array_column($tables, 'name');
fwrite($out, "TABELAS COM 'CARR': " . implode(', ', array_filter($names, fn($n) => str_contains(strtoupper($n), 'CARR'))) . "\n");
fwrite($out, "TABELAS COM 'PROG': " . implode(', ', array_filter($names, fn($n) => str_contains(strtoupper($n), 'PROG'))) . "\n");
fwrite($out, "TABELAS COM 'TABELA': " . implode(', ', array_filter($names, fn($n) => str_contains(strtoupper($n), 'TABELA'))) . "\n\n");

// TABELA_SALARIAL — todas as linhas
fwrite($out, "=== TABELA_SALARIAL (todas) ===\n");
try {
    $rows = DB::table('TABELA_SALARIAL')->get();
    foreach ($rows as $r)
        fwrite($out, json_encode($r) . "\n");
} catch (\Throwable $e) {
    fwrite($out, "ERRO TABELA_SALARIAL: " . $e->getMessage() . "\n");
}

// PROGRESSAO_CONFIG
fwrite($out, "\n=== PROGRESSAO_CONFIG (todas) ===\n");
try {
    $rows = DB::table('PROGRESSAO_CONFIG')->get();
    foreach ($rows as $r)
        fwrite($out, json_encode($r) . "\n");
} catch (\Throwable $e) {
    fwrite($out, "ERRO PROGRESSAO_CONFIG: " . $e->getMessage() . "\n");
}

fclose($out);
echo "Log em check_log.txt\n";
