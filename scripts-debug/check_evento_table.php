<?php
require '/var/www/sistemagente.com/vendor/autoload.php';
$app = require '/var/www/sistemagente.com/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = ['EVENTO', 'EVENTO_FOLHA', 'EVENTOS_FOLHA'];

foreach ($tables as $tbl) {
    echo "\n=== $tbl ===\n";
    try {
        $rows = DB::select("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ", [$tbl]);
        if (empty($rows)) {
            echo "  (tabela nao existe)\n";
            continue;
        }
        foreach ($rows as $r) {
            $null = $r->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
            echo "  {$r->COLUMN_NAME} ({$r->DATA_TYPE}, $null)\n";
        }
        $count = DB::table($tbl)->count();
        echo "  -- COUNT: $count linhas --\n";
    } catch (\Throwable $e) {
        echo "  ERRO: " . $e->getMessage() . "\n";
    }
}
